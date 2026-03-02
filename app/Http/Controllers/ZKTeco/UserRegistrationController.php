<?php

namespace App\Http\Controllers\ZKTeco;

use App\Http\Controllers\Controller;
use App\Models\ZktecoDeviceCommand;
use App\Models\ZktecoDeviceUser;
use App\Models\ZktecoRawLog;
use App\Services\ZKTeco\DeviceCommandQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class UserRegistrationController extends Controller
{
    public function __construct(private readonly DeviceCommandQueue $commandQueue)
    {
    }

    /**
     * Show the registration page.
     */
    public function index()
    {
        return view('zkteco.register');
    }

    /**
     * Register a new user and queue sync commands.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_sn' => ['required', 'string', 'max:100'],
            'pin' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:100'],
            'privilege' => ['nullable', 'integer', 'min:0', 'max:14'],
            'card_no' => ['nullable', 'string', 'max:50'],
            'group_id' => ['nullable', 'integer', 'min:1', 'max:99'],
            'disabled' => ['nullable', 'boolean'],
            'face_template' => ['nullable', 'string'],
            'fingerprint_template' => ['nullable', 'string'],
        ]);

        $user = $this->commandQueue->queueUserRegistration($validated);

        return response()->json([
            'message' => 'User registration queued for device sync',
            'user' => [
                'id' => $user->id,
                'device_sn' => $user->device_sn,
                'pin' => $user->pin,
                'name' => $user->name,
                'privilege' => $user->privilege,
                'card_no' => $user->card_no,
                'group_id' => $user->group_id,
                'disabled' => $user->disabled,
                'has_face_template' => ! empty($user->face_template),
                'has_fingerprint_template' => ! empty($user->fingerprint_template),
            ],
        ], 201);
    }

    /**
     * Update an existing app-side device user and queue sync commands.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = ZktecoDeviceUser::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'privilege' => ['nullable', 'integer', 'min:0', 'max:14'],
            'card_no' => ['nullable', 'string', 'max:50'],
            'group_id' => ['nullable', 'integer', 'min:1', 'max:99'],
            'disabled' => ['nullable', 'boolean'],
            'face_template' => ['nullable', 'string'],
            'fingerprint_template' => ['nullable', 'string'],
        ]);

        $payload = array_merge($validated, [
            'device_sn' => $user->device_sn,
            'pin' => (int) $user->pin,
        ]);

        $updatedUser = $this->commandQueue->queueUserRegistration($payload);

        return response()->json([
            'message' => 'User update queued for device sync',
            'user' => [
                'id' => $updatedUser->id,
                'device_sn' => $updatedUser->device_sn,
                'pin' => $updatedUser->pin,
                'name' => $updatedUser->name,
                'privilege' => $updatedUser->privilege,
                'card_no' => $updatedUser->card_no,
                'group_id' => $updatedUser->group_id,
                'disabled' => $updatedUser->disabled,
                'has_face_template' => ! empty($updatedUser->face_template),
                'has_fingerprint_template' => ! empty($updatedUser->fingerprint_template),
            ],
        ]);
    }

    /**
     * Queue deletion of a user on the device and remove locally once acked.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = ZktecoDeviceUser::find($id);
        if (! $user) {
            return response()->json([
                'message' => 'User already absent in local cache. Sync from device to confirm current machine users.',
                'already_absent' => true,
            ]);
        }

        $this->commandQueue->queueUserDeletion($user);

        return response()->json([
            'message' => 'User delete queued for device sync. Local record is removed after device user-list reconciliation confirms deletion.',
            'user' => [
                'id' => $user->id,
                'device_sn' => $user->device_sn,
                'pin' => $user->pin,
                'name' => $user->name,
            ],
        ]);
    }

    /**
     * Queue a full device user query to reconcile app-side users with machine users.
     */
    public function syncDeviceUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_sn' => ['required', 'string', 'max:100'],
        ]);

        $deviceSn = trim((string) $validated['device_sn']);
        $this->commandQueue->queueUsersQuery($deviceSn);

        return response()->json([
            'message' => 'Device user query queued. User list will update after device response.',
            'device_sn' => $deviceSn,
        ]);
    }

    /**
     * List all registered device users with their sync status.
     */
    public function deviceUsersList(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->query('per_page', 25)), 100);

        $users = ZktecoDeviceUser::orderBy('id', 'desc')
            ->paginate($perPage);

        $items = collect($users->items())->map(function ($user) {
            $commands = ZktecoDeviceCommand::where('device_sn', $user->device_sn)
                ->where('pin', $user->pin)
                ->get();

            $summary = $this->summarizeCommandsForSync($commands);

            $syncStatus = 'pending';
            if ($summary['failed'] > 0) {
                $syncStatus = 'failed';
            } elseif ($summary['acked'] === $summary['total'] && $summary['total'] > 0) {
                $syncStatus = 'synced';
            } elseif ($summary['sent'] > 0 || $summary['acked'] > 0) {
                $syncStatus = 'syncing';
            }

            return [
                'id' => $user->id,
                'device_sn' => $user->device_sn,
                'pin' => $user->pin,
                'name' => $user->name,
                'privilege' => $user->privilege,
                'card_no' => $user->card_no,
                'group_id' => $user->group_id,
                'disabled' => $user->disabled,
                'created_at' => $user->created_at->toDateTimeString(),
                'sync_status' => $syncStatus,
                'command_summary' => $summary,
            ];
        });

        return response()->json([
            'users' => $items,
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'total' => $users->total(),
            'per_page' => $users->perPage(),
        ]);
    }

    /**
     * Get command status for a specific user registration.
     */
    public function commandStatus(string $device_sn, int $pin): JsonResponse
    {
        $commands = $this->commandQueue->getCommandsForUser($device_sn, $pin);
        $rawCommands = ZktecoDeviceCommand::where('device_sn', $device_sn)
            ->where('pin', $pin)
            ->get();
        $summary = $this->summarizeCommandsForSync($rawCommands);

        return response()->json([
            'device_sn' => $device_sn,
            'pin' => $pin,
            'commands' => $commands,
            'summary' => $summary,
        ]);
    }

    /**
     * Get list of known device serial numbers.
     */
    public function knownDevices(): JsonResponse
    {
        $devices = ZktecoRawLog::select('device_sn')
            ->whereNotNull('device_sn')
            ->where('device_sn', '!=', '')
            ->where('ip', '!=', '127.0.0.1')
            ->groupBy('device_sn')
            ->pluck('device_sn');

        return response()->json(['devices' => $devices]);
    }

    /**
     * Registration page stats.
     */
    public function registrationStats(): JsonResponse
    {
        $totalUsers = ZktecoDeviceUser::count();

        $commandStats = ZktecoDeviceCommand::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'acked' THEN 1 ELSE 0 END) as acked,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        ")->first();

        return response()->json([
            'total_users' => $totalUsers,
            'commands' => [
                'total' => (int) $commandStats->total,
                'pending' => (int) $commandStats->pending,
                'sent' => (int) $commandStats->sent,
                'acked' => (int) $commandStats->acked,
                'failed' => (int) $commandStats->failed,
            ],
        ]);
    }

    /**
     * Build an effective per-user sync summary by collapsing channel duplicates.
     *
     * A single logical command may be queued for both getrequest and service_control.
     * For UI sync flow we treat the command as complete when any channel is acked.
     */
    private function summarizeCommandsForSync(Collection $commands): array
    {
        $summary = [
            'total' => 0,
            'pending' => 0,
            'sent' => 0,
            'acked' => 0,
            'failed' => 0,
        ];

        if ($commands->isEmpty()) {
            return $summary;
        }

        $logicalGroups = $commands
            ->sortByDesc('id')
            ->groupBy(function ($command) {
                return $this->logicalCommandKey((string) $command->command_template);
            })
            ->map(fn ($group) => $group->first());

        foreach ($logicalGroups as $command) {
            $logicalKey = $this->logicalCommandKey((string) $command->command_template);
            if (! in_array($logicalKey, ['user_sync', 'face_push', 'fingerprint_push'], true)) {
                continue;
            }

            $summary['total']++;

            $status = (string) $command->status;

            if ($status === 'acked') {
                $summary['acked']++;
                continue;
            }

            if ($status === 'sent') {
                $summary['sent']++;
                continue;
            }

            if ($status === 'pending') {
                $summary['pending']++;
                continue;
            }

            if ($status === 'failed') {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    private function logicalCommandKey(string $template): string
    {
        $body = preg_replace('/^C:\{CMDID\}:/', '', $template);

        if (str_contains($body, 'DATA UPDATE user')) {
            return 'user_sync';
        }
        if (str_contains($body, 'DATA UPDATE biophoto')) {
            return 'face_push';
        }
        if (str_contains($body, 'DATA UPDATE biodata')) {
            return 'fingerprint_push';
        }
        if (
            str_contains($body, 'DATA DELETE tablename=user')
            || str_contains($body, 'DATA DELETE user')
            || str_contains($body, 'DELETE USER')
            || str_contains($body, 'DeleteUser')
        ) {
            return 'user_delete';
        }
        if (str_contains($body, 'DATA QUERY tablename=user')) {
            return 'user_query';
        }

        return trim((string) $body);
    }

}
