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

        $user = ZktecoDeviceUser::updateOrCreate(
            [
                'device_sn' => $deviceSn,
                'pin' => $pin,
            ],
            [
                'name' => (string) $payload['name'],
                'privilege' => (int) ($payload['privilege'] ?? 0),
                'card_no' => $payload['card_no'] ?? null,
                'group_id' => (int) ($payload['group_id'] ?? 1),
                'disabled' => (bool) ($payload['disabled'] ?? false),
                'face_template' => $payload['face_template'] ?? null,
                'fingerprint_template' => $payload['fingerprint_template'] ?? null,
            ]
        );

        $enrollFace = (bool) ($payload['enroll_face'] ?? false);
        $enrollFingerprint = (bool) ($payload['enroll_fingerprint'] ?? false);

        DB::transaction(function () use ($user, $pin, $enrollFace, $enrollFingerprint) {
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

            if ($enrollFace) {
                $this->queueCommand($user->device_sn, 'getrequest', "ENROLL_FP Pin={$pin} FingerID=50", $pin);
            }
            if ($enrollFingerprint) {
                $this->queueCommand($user->device_sn, 'getrequest', "ENROLL_FP Pin={$pin} FingerID=0", $pin);
            }
        });

        return $user;
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
        if (str_contains($template, 'DATA UPDATE biophoto')) return 'face_push';
        if (str_contains($template, 'DATA UPDATE biodata')) return 'fingerprint_push';
        if (str_contains($template, 'ENROLL_FP') && str_contains($template, 'FingerID=50')) return 'face_enroll';
        if (str_contains($template, 'ENROLL_FP') && str_contains($template, 'FingerID=0')) return 'fingerprint_enroll';
        if (str_contains($template, 'ENROLL_FP')) return 'enroll';
        return 'other';
    }
}
