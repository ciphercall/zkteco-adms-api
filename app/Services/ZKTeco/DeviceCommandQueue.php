<?php

namespace App\Services\ZKTeco;

use App\Models\ZktecoDeviceCommand;
use App\Models\ZktecoDeviceUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeviceCommandQueue
{
    public function queueUserRegistration(array $payload): ZktecoDeviceUser
    {
        $deviceSn = trim((string) $payload['device_sn']);
        $pin = (int) $payload['pin'];

        $user = ZktecoDeviceUser::firstOrNew([
            'device_sn' => $deviceSn,
            'pin' => $pin,
        ]);

        $user->name = (string) $payload['name'];
        $user->privilege = (int) ($payload['privilege'] ?? 0);
        $user->card_no = $payload['card_no'] ?? null;
        $user->group_id = (int) ($payload['group_id'] ?? 1);
        $user->disabled = (bool) ($payload['disabled'] ?? false);

        if (array_key_exists('face_template', $payload)) {
            $user->face_template = $payload['face_template'];
        }
        if (array_key_exists('fingerprint_template', $payload)) {
            $user->fingerprint_template = $payload['fingerprint_template'];
        }

        $user->save();

        DB::transaction(function () use ($user, $pin) {
            $this->queueCommand($user->device_sn, 'getrequest', $this->buildUserCommand($user), $pin);
            $this->queueCommand($user->device_sn, 'service_control', $this->buildUserCommand($user), $pin);

            if (! empty($user->face_template)) {
                $this->queueCommand($user->device_sn, 'getrequest', $this->buildFaceCommand($user), $pin);
                $this->queueCommand($user->device_sn, 'service_control', $this->buildFaceCommand($user), $pin);
            }

            if (! empty($user->fingerprint_template)) {
                $this->queueCommand($user->device_sn, 'getrequest', $this->buildFingerprintCommand($user), $pin);
                $this->queueCommand($user->device_sn, 'service_control', $this->buildFingerprintCommand($user), $pin);
            }

            $this->queueUsersQuery($user->device_sn);
        });

        return $user;
    }

    public function queueUserDeletion(ZktecoDeviceUser $user): void
    {
        DB::transaction(function () use ($user) {
            $deleteCommands = $this->buildDeleteUserCommands((int) $user->pin);

            foreach ($deleteCommands as $deleteCommand) {
                $this->queueCommand($user->device_sn, 'getrequest', $deleteCommand, (int) $user->pin);
                $this->queueCommand($user->device_sn, 'service_control', $deleteCommand, (int) $user->pin);
            }
        });
    }

    public function queueUsersQuery(string $deviceSn): void
    {
        $deviceSn = trim($deviceSn);
        if ($deviceSn === '') {
            return;
        }

        DB::transaction(function () use ($deviceSn) {
            $query = 'DATA QUERY tablename=user,fielddesc=*,filter=*';
            $this->queueCommand($deviceSn, 'getrequest', $query, null);
            $this->queueCommand($deviceSn, 'service_control', $query, null);
        });
    }

    public function pullPendingCommands(string $deviceSn, string $channel, int $limit = 3): array
    {
        $commands = ZktecoDeviceCommand::query()
            ->where('device_sn', $deviceSn)
            ->where('channel', $channel)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $lines = [];

        foreach ($commands as $command) {
            $cmdId = $this->nextCmdId((int) $command->id);
            $line = str_replace('{CMDID}', (string) $cmdId, $command->command_template);

            $command->update([
                'cmd_id' => $cmdId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $lines[] = $line;
        }

        return $lines;
    }

    public function markAcknowledged(?int $cmdId, string $rawPayload): void
    {
        if (! $cmdId) {
            return;
        }

        $isSuccess = str_contains($rawPayload, 'Return=0');

        ZktecoDeviceCommand::query()
            ->where('cmd_id', $cmdId)
            ->where('status', 'sent')
            ->update([
                'status' => $isSuccess ? 'acked' : 'failed',
                'ack_payload' => $rawPayload,
                'acknowledged_at' => now(),
            ]);

        $processedCommands = ZktecoDeviceCommand::query()
            ->where('cmd_id', $cmdId)
            ->whereIn('status', ['acked', 'failed'])
            ->get();

        $devicesToReconcile = $processedCommands
            ->filter(function ($command) {
                $template = (string) $command->command_template;
                return str_contains($template, 'DATA UPDATE user') || $this->isUserDeleteCommand($template);
            })
            ->pluck('device_sn')
            ->filter(fn ($sn) => is_string($sn) && trim($sn) !== '')
            ->unique()
            ->values();

        if (! $isSuccess) {
            foreach ($devicesToReconcile as $deviceSn) {
                $this->queueUsersQuery((string) $deviceSn);
            }
            return;
        }

        $ackedCommands = $processedCommands->where('status', 'acked');

        foreach ($ackedCommands as $ackedCommand) {
            ZktecoDeviceCommand::query()
                ->where('device_sn', $ackedCommand->device_sn)
                ->where('pin', $ackedCommand->pin)
                ->where('command_template', $ackedCommand->command_template)
                ->where('id', '!=', $ackedCommand->id)
                ->whereIn('status', ['pending', 'sent'])
                ->update([
                    'status' => 'acked',
                    'ack_payload' => '[cross-channel-acked] ' . $rawPayload,
                    'acknowledged_at' => now(),
                ]);

            if ($this->isUserDeleteCommand((string) $ackedCommand->command_template)) {
                $this->queueUsersQuery((string) $ackedCommand->device_sn);
            }
        }

        foreach ($devicesToReconcile as $deviceSn) {
            $this->queueUsersQuery((string) $deviceSn);
        }
    }

    /**
     * Get all commands for a specific user on a device, with enriched metadata.
     */
    public function getCommandsForUser(string $deviceSn, int $pin): Collection
    {
        return ZktecoDeviceCommand::where('device_sn', $deviceSn)
            ->where('pin', $pin)
            ->orderBy('id')
            ->get()
            ->map(function ($cmd) {
                return [
                    'id' => $cmd->id,
                    'channel' => $cmd->channel,
                    'command' => $this->summarizeCommand($cmd->command_template),
                    'type' => $this->classifyCommand($cmd->command_template),
                    'cmd_id' => $cmd->cmd_id,
                    'status' => $cmd->status,
                    'sent_at' => $cmd->sent_at?->toDateTimeString(),
                    'acknowledged_at' => $cmd->acknowledged_at?->toDateTimeString(),
                    'created_at' => $cmd->created_at->toDateTimeString(),
                ];
            });
    }

    private function queueCommand(string $deviceSn, string $channel, string $commandBody, ?int $pin = null): void
    {
        ZktecoDeviceCommand::create([
            'device_sn' => $deviceSn,
            'pin' => $pin,
            'channel' => $channel,
            'command_template' => 'C:{CMDID}:' . $commandBody,
            'status' => 'pending',
        ]);
    }

    private function buildUserCommand(ZktecoDeviceUser $user): string
    {
        $name = $this->sanitizeTabValue($user->name);
        $card = $this->sanitizeTabValue((string) ($user->card_no ?? ''));

        return "DATA UPDATE user\tPin={$user->pin}\tName={$name}\tPrivilege={$user->privilege}\tCardNo={$card}\tGroup={$user->group_id}\tDisable=" . ($user->disabled ? '1' : '0');
    }

    private function buildFaceCommand(ZktecoDeviceUser $user): string
    {
        $face = $this->sanitizeTabValue((string) $user->face_template);

        return "DATA UPDATE biophoto\tPin={$user->pin}\tContent={$face}";
    }

    private function buildFingerprintCommand(ZktecoDeviceUser $user): string
    {
        $template = $this->sanitizeTabValue((string) $user->fingerprint_template);

        return "DATA UPDATE biodata\tPin={$user->pin}\tFingerID=0\tTemplate={$template}";
    }

    private function buildDeleteUserCommands(int $pin): array
    {
        return [
            "DATA DELETE tablename=user,filter=pin={$pin}",
            "DATA DELETE tablename=user,filter=Pin={$pin}",
            "DATA DELETE user\tPin={$pin}",
            "DELETE USER\tPin={$pin}",
            "DeleteUser\tPin={$pin}",
        ];
    }

    private function sanitizeTabValue(string $value): string
    {
        return trim(str_replace(["\t", "\r", "\n"], ' ', $value));
    }

    private function nextCmdId(int $commandRowId): int
    {
        $timeComponent = (int) now()->format('His');
        $suffix = $commandRowId % 1000;

        return ($timeComponent * 1000) + $suffix;
    }

    private function summarizeCommand(string $template): string
    {
        $cmd = preg_replace('/^C:\{CMDID\}:/', '', $template);
        if (strlen($cmd) > 100) {
            return substr($cmd, 0, 100) . '…';
        }
        return $cmd;
    }

    private function classifyCommand(string $template): string
    {
        if (str_contains($template, 'DATA UPDATE user')) return 'user_sync';
        if (str_contains($template, 'DATA QUERY tablename=user')) return 'user_query';
        if (str_contains($template, 'DATA UPDATE biophoto')) return 'face_push';
        if (str_contains($template, 'DATA UPDATE biodata')) return 'fingerprint_push';
        if ($this->isUserDeleteCommand($template)) return 'user_delete';
        return 'other';
    }

    private function isUserDeleteCommand(string $template): bool
    {
        $needleSet = [
            'DATA DELETE tablename=user',
            'DATA DELETE user',
            'DELETE USER',
            'DeleteUser',
        ];

        foreach ($needleSet as $needle) {
            if (str_contains($template, $needle)) {
                return true;
            }
        }

        return false;
    }
}
