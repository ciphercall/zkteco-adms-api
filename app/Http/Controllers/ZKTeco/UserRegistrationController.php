<?php

namespace App\Http\Controllers\ZKTeco;

use App\Http\Controllers\Controller;
use App\Models\ZktecoDeviceCommand;
use App\Models\ZktecoDeviceUser;
use App\Models\ZktecoRawLog;
use App\Services\ZKTeco\DeviceCommandQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'enroll_face' => ['nullable', 'boolean'],
            'enroll_fingerprint' => ['nullable', 'boolean'],
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

            $summary = [
                'total' => $commands->count(),
                'pending' => $commands->where('status', 'pending')->count(),
                'sent' => $commands->where('status', 'sent')->count(),
                'acked' => $commands->where('status', 'acked')->count(),
                'failed' => $commands->where('status', 'failed')->count(),
            ];

            $syncStatus = 'pending';
            if ($summary['failed'] > 0) {
                $syncStatus = 'failed';
            } elseif ($summary['acked'] === $summary['total'] && $summary['total'] > 0) {
                $syncStatus = 'synced';
            } elseif ($summary['sent'] > 0 || $summary['acked'] > 0) {
                $syncStatus = 'syncing';
            }

            $hasEnrollFace = $commands->contains(fn ($c) => str_contains($c->command_template, 'ENROLL_FP') && str_contains($c->command_template, 'FingerID=50'));
            $hasEnrollFp = $commands->contains(fn ($c) => str_contains($c->command_template, 'ENROLL_FP') && str_contains($c->command_template, 'FingerID=0'));

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
                'face_requested' => $hasEnrollFace,
                'fingerprint_requested' => $hasEnrollFp,
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

        return response()->json([
            'device_sn' => $device_sn,
            'pin' => $pin,
            'commands' => $commands,
            'summary' => [
                'total' => $commands->count(),
                'pending' => $commands->where('status', 'pending')->count(),
                'sent' => $commands->where('status', 'sent')->count(),
                'acked' => $commands->where('status', 'acked')->count(),
                'failed' => $commands->where('status', 'failed')->count(),
            ],
        ]);
    }

    /**
     * Check biometric enrollment status for a user.
     */
    public function enrollmentStatus(string $device_sn, int $pin): JsonResponse
    {
        $user = ZktecoDeviceUser::where('device_sn', $device_sn)
            ->where('pin', $pin)
            ->first();

        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $commands = ZktecoDeviceCommand::where('device_sn', $device_sn)
            ->where('pin', $pin)
            ->get();

        $faceCmd = $commands->first(fn ($c) => str_contains($c->command_template, 'ENROLL_FP') && str_contains($c->command_template, 'FingerID=50'));
        $fpCmd = $commands->first(fn ($c) => str_contains($c->command_template, 'ENROLL_FP') && str_contains($c->command_template, 'FingerID=0'));

        $faceUploaded = $this->checkBiometricUpload($device_sn, $pin, 'biophoto');
        $fpUploaded = $this->checkBiometricUpload($device_sn, $pin, 'biodata');

        return response()->json([
            'face' => [
                'requested' => $faceCmd !== null,
                'command_status' => $faceCmd?->status,
                'command_sent_at' => $faceCmd?->sent_at?->toDateTimeString(),
                'command_acked_at' => $faceCmd?->acknowledged_at?->toDateTimeString(),
                'data_uploaded' => $faceUploaded,
            ],
            'fingerprint' => [
                'requested' => $fpCmd !== null,
                'command_status' => $fpCmd?->status,
                'command_sent_at' => $fpCmd?->sent_at?->toDateTimeString(),
                'command_acked_at' => $fpCmd?->acknowledged_at?->toDateTimeString(),
                'data_uploaded' => $fpUploaded,
            ],
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
     * Check if biometric data has been uploaded from device for a specific PIN.
     */
    private function checkBiometricUpload(string $deviceSn, int $pin, string $tablename): bool
    {
        $pinStr = (string) $pin;
        $tab = "\t";

        return ZktecoRawLog::where('device_sn', $deviceSn)
            ->where('endpoint', '/iclock/cdata')
            ->where('method', 'POST')
            ->where(function ($q) use ($tablename) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(query_params, '$.tablename')) = ?", [$tablename]);
            })
            ->where(function ($q) use ($pinStr, $tab) {
                $q->where('raw_body', 'LIKE', "Pin={$pinStr}{$tab}%")
                  ->orWhere('raw_body', 'LIKE', "%{$tab}Pin={$pinStr}{$tab}%")
                  ->orWhere('raw_body', 'LIKE', "%{$tab}Pin={$pinStr}")
                  ->orWhere('raw_body', 'LIKE', "%\nPin={$pinStr}{$tab}%");
            })
            ->exists();
    }
}
