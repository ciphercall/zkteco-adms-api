<?php

namespace App\Http\Controllers\ZKTeco;

use App\Http\Controllers\Controller;
use App\Models\ZktecoDeviceCommand;
use App\Models\ZktecoDeviceUser;
use App\Models\ZktecoRawLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Main dashboard view.
     */
    public function index()
    {
        return view('zkteco.dashboard');
    }

    /**
     * API: Dashboard summary statistics.
     */
    public function stats()
    {
        $totalLogs = ZktecoRawLog::count();

        // Distinct devices that have communicated (exclude localhost test traffic)
        $devices = ZktecoRawLog::select('device_sn')
            ->whereNotNull('device_sn')
            ->where('device_sn', '!=', '')
            ->where('ip', '!=', '127.0.0.1')
            ->groupBy('device_sn')
            ->get()
            ->map(function ($row) {
                $latest = ZktecoRawLog::where('device_sn', $row->device_sn)
                    ->where('ip', '!=', '127.0.0.1')
                    ->orderBy('created_at', 'desc')
                    ->first();

                $lastSeen = $latest ? $latest->created_at : null;
                $ip = $latest ? $latest->ip : null;
                $isOnline = $lastSeen && Carbon::parse($lastSeen)->gt(now()->subMinutes(2));

                return [
                    'sn' => $row->device_sn,
                    'ip' => $ip,
                    'last_seen' => $lastSeen,
                    'is_online' => $isOnline,
                ];
            });

        // Endpoint hit counts
        $endpointStats = ZktecoRawLog::selectRaw('endpoint, method, count(*) as cnt, max(created_at) as last_hit')
            ->groupBy('endpoint', 'method')
            ->orderBy('cnt', 'desc')
            ->get();

        // Count by table type for POST /iclock/cdata
        $uploadStats = ZktecoRawLog::where('endpoint', '/iclock/cdata')
            ->where('method', 'POST')
            ->get()
            ->groupBy(function ($row) {
                $params = is_array($row->query_params) ? $row->query_params : [];
                return $params['table'] ?? '(none)';
            })
            ->map(fn($group) => $group->count());

        // Today's event counts
        $todayStart = now()->startOfDay();
        $todayEvents = ZktecoRawLog::where('endpoint', '/iclock/cdata')
            ->where('method', 'POST')
            ->where('created_at', '>=', $todayStart)
            ->count();

        return response()->json([
            'total_logs' => $totalLogs,
            'devices' => $devices,
            'endpoint_stats' => $endpointStats,
            'upload_stats' => $uploadStats,
            'today_events' => $todayEvents,
        ]);
    }

    /**
     * API: Real-time access events (rtlog) — paginated.
     */
    public function accessEvents(Request $request)
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min((int) ($request->query('per_page', 25)), 100);

        $paginated = ZktecoRawLog::where('endpoint', '/iclock/cdata')
            ->where('method', 'POST')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(query_params, '$.table')) = 'rtlog'")
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $events = collect($paginated->items())
            ->map(fn($log) => $this->parseRtlog($log))
            ->filter()
            ->values();

        return response()->json([
            'events'       => $events,
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
        ]);
    }

    /**
     * API: Device status (rtstate) — paginated.
     */
    public function deviceStatus(Request $request)
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min((int) ($request->query('per_page', 20)), 100);

        $paginated = ZktecoRawLog::where('endpoint', '/iclock/cdata')
            ->where('method', 'POST')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(query_params, '$.table')) = 'rtstate'")
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $states = collect($paginated->items())
            ->map(fn($log) => $this->parseRtstate($log))
            ->filter()
            ->values();

        return response()->json([
            'states'       => $states,
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
        ]);
    }

    /**
     * API: Users synced from device (tabledata/user) — paginated.
     */
    public function users(Request $request)
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min((int) ($request->query('per_page', 25)), 100);

        $allUsers = ZktecoDeviceUser::query()
            ->orderBy('updated_at', 'desc')
            ->get()
            ->values();

        $allUsers = $this->filterUsersToLatestDeviceSnapshot($allUsers);

        $total = $allUsers->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $safePage = min($page, $lastPage);
        $pagedUsers = $allUsers->forPage($safePage, $perPage)->values();

        $users = $pagedUsers->map(function ($user) {
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
                'privilege' => $this->privilegeLabel((string) $user->privilege),
                'card' => $user->card_no ?? '',
                'group' => (string) $user->group_id,
                'disabled' => (bool) $user->disabled,
                'sync_status' => $syncStatus,
                'command_summary' => $summary,
                'synced_at' => optional($user->updated_at)->toDateTimeString(),
            ];
        });

        return response()->json([
            'users'        => $users,
            'current_page' => $safePage,
            'last_page'    => $lastPage,
            'total'        => $total,
            'per_page'     => $perPage,
        ]);
    }

    private function filterUsersToLatestDeviceSnapshot(Collection $users): Collection
    {
        if ($users->isEmpty()) {
            return $users;
        }

        $snapshots = $this->latestDeviceUserPinsBySn();
        if (empty($snapshots)) {
            return $users;
        }

        return $users
            ->filter(function ($user) use ($snapshots) {
                $deviceSn = (string) $user->device_sn;
                if (! array_key_exists($deviceSn, $snapshots)) {
                    return true;
                }

                return in_array((int) $user->pin, $snapshots[$deviceSn], true);
            })
            ->values();
    }

    private function latestDeviceUserPinsBySn(): array
    {
        $logs = ZktecoRawLog::query()
            ->where('endpoint', '/iclock/querydata')
            ->where('method', 'POST')
            ->whereNotNull('device_sn')
            ->orderByDesc('id')
            ->limit(500)
            ->get(['device_sn', 'query_params', 'raw_body']);

        $map = [];

        foreach ($logs as $log) {
            $deviceSn = (string) ($log->device_sn ?? '');
            if ($deviceSn === '' || array_key_exists($deviceSn, $map)) {
                continue;
            }

            $query = is_array($log->query_params) ? $log->query_params : [];
            $type = strtolower((string) ($query['type'] ?? ''));
            $tablename = strtolower((string) ($query['tablename'] ?? ''));
            if ($type !== 'tabledata' || $tablename !== 'user') {
                continue;
            }

            $packCnt = (int) ($query['packcnt'] ?? 1);
            $packIdx = (int) ($query['packidx'] ?? 1);
            if ($packCnt > 1 && $packIdx !== $packCnt) {
                continue;
            }

            $expectedCount = isset($query['count']) && is_numeric($query['count'])
                ? (int) $query['count']
                : null;

            if ($expectedCount === 0) {
                $map[$deviceSn] = [];
                continue;
            }

            $rows = $this->parseUserTabledata((string) $log->raw_body);
            $pins = collect($rows)
                ->map(fn ($row) => isset($row['pin']) ? (int) $row['pin'] : 0)
                ->filter(fn ($pin) => $pin > 0)
                ->unique()
                ->values()
                ->all();

            if (empty($pins) && $expectedCount !== null && $expectedCount > 0) {
                continue;
            }

            $map[$deviceSn] = $pins;
        }

        return $map;
    }

    /**
     * API: Raw logs browser with pagination.
     */
    public function rawLogs(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min((int) ($request->query('per_page', 25)), 100);
        $endpoint = $request->query('endpoint');
        $method = $request->query('method');
        $sn = $request->query('sn');

        $query = ZktecoRawLog::orderBy('id', 'desc');

        if ($endpoint) {
            $query->where('endpoint', $endpoint);
        }
        if ($method) {
            $query->where('method', strtoupper($method));
        }
        if ($sn) {
            $query->where('device_sn', $sn);
        }

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json($paginated);
    }

    /**
     * API: Activity timeline — recent hits over time.
     */
    public function timeline(Request $request)
    {
        $hours = min((int) ($request->query('hours', 24)), 168);
        $since = now()->subHours($hours);

        $data = ZktecoRawLog::where('created_at', '>=', $since)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour, endpoint, count(*) as cnt")
            ->groupBy('hour', 'endpoint')
            ->orderBy('hour')
            ->get();

        return response()->json(['timeline' => $data]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Parsers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parse an rtlog POST body into structured event data.
     */
    private function parseRtlog(ZktecoRawLog $log): ?array
    {
        $body = $log->raw_body ?? '';
        if (empty($body)) return null;

        $fields = $this->parseTabFields($body);
        if (empty($fields)) return null;

        $eventCode = (int) ($fields['event'] ?? 0);
        $verifyType = (int) ($fields['verifytype'] ?? 0);

        return [
            'id' => $log->id,
            'device_sn' => $log->device_sn,
            'time' => $fields['time'] ?? $log->created_at->toDateTimeString(),
            'pin' => $fields['pin'] ?? '0',
            'card_no' => $fields['cardno'] ?? '',
            'event_code' => $eventCode,
            'event_label' => $this->eventLabel($eventCode),
            'event_category' => $this->eventCategory($eventCode),
            'door' => $fields['eventaddr'] ?? '1',
            'in_out' => $this->inOutLabel((int) ($fields['inoutstatus'] ?? 0)),
            'verify_type' => $verifyType,
            'verify_label' => $this->verifyLabel($verifyType),
            'index' => $fields['index'] ?? '',
            'mask_flag' => $fields['maskflag'] ?? '0',
            'temperature' => $fields['temperature'] ?? '0',
            'recorded_at' => $log->created_at->toDateTimeString(),
        ];
    }

    /**
     * Parse an rtstate POST body.
     */
    private function parseRtstate(ZktecoRawLog $log): ?array
    {
        $body = $log->raw_body ?? '';
        if (empty($body)) return null;

        // rtstate can have multiple status lines
        $lines = preg_split('/\r?\n/', trim($body));
        $entries = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $fields = $this->parseTabFields($line);
            if (!empty($fields)) {
                $entries[] = [
                    'time' => $fields['time'] ?? '',
                    'sensor' => $fields['sensor'] ?? '',
                    'relay' => $fields['relay'] ?? '',
                    'alarm' => $fields['alarm'] ?? '',
                    'door' => $fields['door'] ?? '',
                ];
            }
        }

        if (empty($entries)) return null;

        return [
            'id' => $log->id,
            'device_sn' => $log->device_sn,
            'recorded_at' => $log->created_at->toDateTimeString(),
            'entries' => $entries,
        ];
    }

    /**
     * Parse tab-separated key=value body into associative array (first line only).
     */
    private function parseTabFields(string $body): array
    {
        // Body is tab-separated key=value pairs, e.g.:
        // time=2026-02-23 14:13:48\tpin=0\tcardno=0\t...
        $first = strtok(trim($body), "\r\n");
        $parts = preg_split('/\t+/', $first);
        $fields = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if (str_contains($part, '=')) {
                [$key, $val] = explode('=', $part, 2);
                $fields[strtolower(trim($key))] = trim($val);
            }
        }

        return $fields;
    }

    /**
     * Parse tab-separated multi-row upload (tabledata).
     */
    private function parseTabSeparated(string $body): array
    {
        $lines = preg_split('/\r?\n/', trim($body));
        $rows = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = preg_split('/\t+/', $line);
            $row = [];
            foreach ($parts as $part) {
                if (str_contains($part, '=')) {
                    [$key, $val] = explode('=', $part, 2);
                    $row[trim($key)] = trim($val);
                }
            }
            if (!empty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Parse user tabledata body.
     * Format: "user uid=1\tcardno=\tpin=1\t...user uid=2\tcardno=\tpin=120\t..."
     * Each user record starts with the word "user" followed by tab-separated key=value pairs.
     */
    private function parseUserTabledata(string $body): array
    {
        // Split on "user " prefix to get individual user records
        // The body may have multiple "user uid=..." blocks concatenated
        $segments = preg_split('/(?=user\s+uid=)/i', $body);
        $users = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if (empty($segment)) continue;

            // Remove leading "user " prefix if present
            $segment = preg_replace('/^user\s+/i', '', $segment);

            $parts = preg_split('/\t+/', $segment);
            $row = [];
            foreach ($parts as $part) {
                $part = trim($part);
                if (str_contains($part, '=')) {
                    [$key, $val] = explode('=', $part, 2);
                    $row[strtolower(trim($key))] = trim($val);
                }
            }

            if (!empty($row) && isset($row['pin'])) {
                $users[] = $row;
            }
        }

        return $users;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Label helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function eventLabel(int $code): string
    {
        return match ($code) {
            0 => 'Normal Punch Open',
            1 => 'Punch During Normal Open Period',
            2 => 'First Card Normal Open',
            3 => 'Multi-Card Open',
            4 => 'Emergency Password Open',
            5 => 'Door Open During Normal Open Period',
            6 => 'Linkage Event Triggered',
            7 => 'Cancel Alarm',
            8 => 'Remote Open',
            9 => 'Remote Close',
            10 => 'Disable Intraday Normal Open',
            11 => 'Enable Intraday Normal Open',
            20 => 'Auxiliary Input Disconnect',
            21 => 'Auxiliary Input Short Circuit',
            23 => 'Press Fingerprint Open',
            24 => 'Multi-Card Open (Fingerprint)',
            25 => 'Press Fingerprint During Normal Open',
            26 => 'Card + Fingerprint Open',
            27 => 'Last Card Close',
            28 => 'First Card Normal Open (Fingerprint)',
            29 => 'First Card Normal Open (Card)',
            30 => 'Normal Open Period Door Close',
            31 => 'Door Close During Normal Open Period',
            32 => 'Fingerprint + Password Open',
            33 => 'Multi-Card + Fingerprint Open',
            34 => 'Remote Normal Open',
            50 => 'Threat Alarm',
            51 => 'Entry Keypad Tamper Alarm',
            52 => 'Host Unit Tamper Alarm',
            53 => 'Verify Failed Alarm',
            54 => 'Forced Lock Alarm',
            55 => 'Anti-Passback Alarm',
            56 => 'Inter-Lock Timeout',
            57 => 'Misused Alarm',
            100 => 'Door Open',
            101 => 'Door Close',
            102 => 'Door Close (Timeout)',
            103 => 'Door Forced Open',
            104 => 'Door Held Too Long',
            200 => 'Normal Open Time Start',
            201 => 'Normal Open Time End',
            202 => 'Normal Open Mode Alarm',
            203 => 'Normal Open Resume',
            204 => 'Disable Intraday Normal Open',
            205 => 'Enable Intraday Normal Open',
            206 => 'Door Opened Correctly',
            220 => 'Auxiliary Output Open',
            221 => 'Auxiliary Output Close',
            default => "Unknown ({$code})",
        };
    }

    private function eventCategory(int $code): string
    {
        if ($code >= 0 && $code <= 49) return 'access';
        if ($code >= 50 && $code <= 99) return 'alarm';
        if ($code >= 100 && $code <= 199) return 'door';
        if ($code >= 200 && $code <= 255) return 'system';
        return 'other';
    }

    private function verifyLabel(int $type): string
    {
        return match ($type) {
            0 => 'Password',
            1 => 'Fingerprint',
            2 => 'Card',
            3, 4, 5 => 'Multi-Factor',
            6, 7, 8, 9 => 'Other',
            15 => 'Face',
            200 => 'System/Auto',
            default => "Type {$type}",
        };
    }

    private function inOutLabel(int $status): string
    {
        return match ($status) {
            0 => 'Entry',
            1 => 'Exit',
            2 => 'N/A',
            default => "Unknown ({$status})",
        };
    }

    private function privilegeLabel(string $pri): string
    {
        return match ((int) $pri) {
            0 => 'User',
            2 => 'Enroller',
            6 => 'Admin',
            14 => 'Super Admin',
            default => "Level {$pri}",
        };
    }

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
