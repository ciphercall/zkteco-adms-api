<?php

namespace App\Http\Controllers\ZKTeco;

use App\Http\Controllers\Controller;
use App\Models\ZktecoRawLog;
use App\Services\ZKTeco\DeviceCommandQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;

class AdmsController extends Controller
{
    public function __construct(private readonly DeviceCommandQueue $commandQueue)
    {
    }

    /**
     * Monotonically incrementing command ID for Security PUSH protocol.
     * In production, persist this in DB/cache. For now, use timestamp-based IDs.
     */
    private function nextCmdId(): int
    {
        return (int) (microtime(true) * 10) % 100000;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Section 7.1 — Initialize Information Interaction
    //  GET /iclock/cdata?SN=...&options=all&pushver=...
    //  → If device is NOT registered: respond "OK"
    //  → If device IS registered: respond with registry=ok + config params
    //
    //  Section 10 — Upload
    //  POST /iclock/cdata?SN=...&table=rtlog|rtstate|tabledata|options
    // ─────────────────────────────────────────────────────────────────────────
    public function cdata(Request $request)
    {
        $this->storeRaw($request, '/iclock/cdata');

        if ($request->isMethod('get')) {
            return $this->handleCdataGet($request);
        }

        // POST — device is uploading data
        return $this->handleCdataPost($request);
    }

    /**
     * GET /iclock/cdata — Initialization exchange (Section 7.1).
     *
     * Per the Security PUSH Communication Protocol doc:
     * - If device is registered, return "registry=ok", RegistryCode, and config params.
     * - If device is NOT registered, return "OK" so device proceeds to POST /iclock/registry.
     *
     * We always treat the device as "registered" and return full config so the
     * device can immediately start uploading data and polling getrequest.
     */
    private function handleCdataGet(Request $request)
    {
        $sn = (string) ($request->query('SN') ?? $request->query('sn') ?? 'UNKNOWN');
        $pushver = (string) ($request->query('pushver') ?? '');

        Log::info('ZKTeco: GET /iclock/cdata (init)', [
            'device_sn' => $sn,
            'pushver' => $pushver,
            'all_query' => $request->query(),
        ]);

        // Generate a stable RegistryCode per device (MD5 of SN).
        $registryCode = md5('zkteco-adms-' . $sn);
        // SessionID for token calculation — stable per session.
        $sessionId = strtoupper(md5($sn . '-session-' . date('Ymd')));

        $lines = [
            'registry=ok',
            "RegistryCode={$registryCode}",
            'ServerVersion=3.0.1',
            'ServerName=LaravelADMS',
            'PushProtVer=3.1.2',
            'ErrorDelay=60',
            'RequestDelay=5',
            'TransTimes=00:00;14:00',
            'TransInterval=1',
            'TransTables=User Transaction',
            'Realtime=1',
            "SessionID={$sessionId}",
            'TimeoutSec=10',
        ];

        $body = implode("\r\n", $lines) . "\r\n";

        Log::info('ZKTeco: sending init response (registered)', [
            'device_sn' => $sn,
            'line_count' => count($lines),
        ]);

        return response($body, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Date', gmdate('D, d M Y H:i:s') . ' GMT');
    }

    /**
     * POST /iclock/cdata — Device uploads data (Sections 10.2–10.12).
     *
     * Tables: rtlog, rtstate, tabledata (user, biophoto, biodata, userpic, etc.), options.
     * Response format depends on the table type.
     */
    private function handleCdataPost(Request $request)
    {
        $sn = (string) ($request->query('SN') ?? $request->query('sn') ?? 'UNKNOWN');
        $table = strtolower((string) ($request->query('table') ?? ''));
        $tablename = strtolower((string) ($request->query('tablename') ?? ''));
        $count = (string) ($request->query('count') ?? '');
        $body = $request->getContent();

        Log::info('ZKTeco: POST /iclock/cdata data upload', [
            'device_sn' => $sn,
            'table' => $table,
            'tablename' => $tablename,
            'count' => $count,
            'body_length' => strlen($body),
            'body_preview' => substr($body, 0, 500),
        ]);

        // Return proper acknowledgment per table type (Section 10).
        // rtlog → "OK" (Section 10.2)
        // rtstate → "OK" (Section 10.3)
        // tabledata → "<tablename>=<count>" (Sections 10.5–10.12)
        // options → "OK" (Section 7.6)

        if ($table === 'tabledata' && $tablename !== '') {
            // For tabledata uploads, return "<tablename>=<count>"
            $ackCount = $count ?: '1';
            $ack = "{$tablename}={$ackCount}";
            return response($ack . "\r\n", 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response("OK\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Section 7.4 — Registration
    //  POST /iclock/registry?SN=... with device capabilities in body
    //  → Return RegistryCode=<random>
    // ─────────────────────────────────────────────────────────────────────────
    public function registry(Request $request)
    {
        $this->storeRaw($request, '/iclock/registry');

        $sn = (string) ($request->query('SN') ?? $request->input('SN') ?? 'UNKNOWN');
        $body = $request->getContent();

        Log::info('ZKTeco: POST /iclock/registry', [
            'device_sn' => $sn,
            'body_length' => strlen($body),
            'body_preview' => substr($body, 0, 500),
        ]);

        // Generate stable RegistryCode (up to 32 bytes).
        $registryCode = md5('zkteco-adms-' . $sn);

        // Protocol spec: return RegistryCode + Set-Cookie with session.
        $sessionId = strtoupper(md5($sn . '-session-' . date('Ymd')));

        return response("RegistryCode={$registryCode}\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Set-Cookie', "PHPSESSID={$sessionId}; Path=/; HttpOnly");
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Section 7.5 — Download Configuration Parameters
    //  POST /iclock/push?SN=...
    //  → Return ServerVersion, Realtime, TransInterval, SessionID, etc.
    // ─────────────────────────────────────────────────────────────────────────
    public function push(Request $request)
    {
        $this->storeRaw($request, '/iclock/push');

        $sn = (string) ($request->query('SN') ?? $request->query('sn') ?? 'UNKNOWN');

        Log::info('ZKTeco: /iclock/push (download config)', [
            'device_sn' => $sn,
        ]);

        $sessionId = strtoupper(md5($sn . '-session-' . date('Ymd')));

        $lines = [
            'ServerVersion=3.0.1',
            'ServerName=LaravelADMS',
            'PushVersion=3.1.2',
            'ErrorDelay=60',
            'RequestDelay=5',
            'TransTimes=00:00;14:00',
            'TransInterval=1',
            'TransTables=User Transaction',
            'Realtime=1',
            "SessionID={$sessionId}",
            'TimeoutSec=10',
        ];

        return response(implode("\r\n", $lines) . "\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Section 9 — Heartbeat
    //  GET /iclock/ping?SN=...
    //  → Return "OK"
    // ─────────────────────────────────────────────────────────────────────────
    public function ping(Request $request)
    {
        $this->storeRaw($request, '/iclock/ping');

        Log::debug('ZKTeco: ping (heartbeat)', [
            'device_sn' => $request->query('SN'),
        ]);

        return response("OK\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Section 11.1 — Download Cache Command
    //  GET /iclock/getrequest?SN=...
    //  → Return "OK" if no pending commands, or "C:<CmdID>:<CmdDesc> <CmdDetail>"
    // ─────────────────────────────────────────────────────────────────────────
    public function getRequest(Request $request)
    {
        $this->storeRaw($request, '/iclock/getrequest');

        $sn = (string) ($request->query('SN') ?? $request->query('sn') ?? 'UNKNOWN');

        $queued = $this->commandQueue->pullPendingCommands($sn, 'getrequest');
        if (! empty($queued)) {
            Log::info('ZKTeco: sending queued commands via getrequest', [
                'device_sn' => $sn,
                'commands' => $queued,
            ]);

            return response(implode("\n", $queued) . "\n", 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        // Check for pending commands in config (or future: database queue).
        $commands = config('zkteco.iclock.getrequest_commands', []);

        if (! empty($commands)) {
            $lines = [];
            foreach ($commands as $cmd) {
                $line = str_replace('{SN}', $sn, $cmd);
                $line = str_replace('{CMDID}', (string) $this->nextCmdId(), $line);
                $lines[] = $line;
            }

            Log::info('ZKTeco: sending commands via getrequest', [
                'device_sn' => $sn,
                'commands' => $lines,
            ]);

            return response(implode("\n", $lines) . "\n", 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        // No pending commands — return OK.
        return response("OK\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Section 10.4 — Upload Returned Result of Command
    //  POST /iclock/devicecmd?SN=...
    //  Body: ID=<CmdID>&Return=<code>&CMD=<type>
    // ─────────────────────────────────────────────────────────────────────────
    public function deviceCmd(Request $request)
    {
        $this->storeRaw($request, '/iclock/devicecmd');

        $body = $request->getContent();
        $cmdId = $this->extractCmdIdFromPayload($body);
        $this->commandQueue->markAcknowledged($cmdId, $body);

        Log::info('ZKTeco: devicecmd (command result)', [
            'device_sn' => $request->query('SN'),
            'cmd_id' => $cmdId,
            'body' => $body,
        ]);

        return response("OK\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Security PUSH: /iclock/service/control — heartbeat + commands
    //  (Some devices use this instead of /iclock/getrequest)
    // ─────────────────────────────────────────────────────────────────────────
    public function serviceControl(Request $request)
    {
        $this->storeRaw($request, '/iclock/service/control');

        $sn = (string) ($request->query('SN') ?? $request->query('sn') ?? 'UNKNOWN');

        if ($request->isMethod('post')) {
            $body = $request->getContent();
            $cmdId = $this->extractCmdIdFromPayload($body);
            $this->commandQueue->markAcknowledged($cmdId, $body);

            Log::info('ZKTeco: service/control POST (command result)', [
                'device_sn' => $sn,
                'cmd_id' => $cmdId,
                'body' => $body,
            ]);
            return response("OK\r\n", 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        // GET — heartbeat / poll for commands.
        // Use same command queue as getrequest.
        $queued = $this->commandQueue->pullPendingCommands($sn, 'service_control');

        if (! empty($queued)) {
            Log::info('ZKTeco: sending queued commands via service/control', [
                'device_sn' => $sn,
                'commands' => $queued,
            ]);

            return response(implode("\n", $queued) . "\n", 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $commands = config('zkteco.iclock.security_push_commands', []);

        if (! empty($commands)) {
            $lines = [];
            foreach ($commands as $cmd) {
                $line = str_replace('{SN}', $sn, $cmd);
                $line = str_replace('{CMDID}', (string) $this->nextCmdId(), $line);
                $lines[] = $line;
            }

            Log::info('ZKTeco: sending commands via service/control', [
                'device_sn' => $sn,
                'commands' => $lines,
            ]);

            return response(implode("\n", $lines) . "\n", 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response("OK\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * GET/POST /iclock/querydata — query results after DATA QUERY command.
     */
    public function queryData(Request $request)
    {
        $this->storeRaw($request, '/iclock/querydata');

        $body = $request->getContent();
        Log::info('ZKTeco: querydata', [
            'device_sn' => $request->query('SN'),
            'type' => $request->query('type'),
            'cmdid' => $request->query('cmdid'),
            'body_length' => strlen($body),
            'body_preview' => substr($body, 0, 1000),
        ]);

        return response("OK\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * GET/POST /iclock/fdata — File/photo data
     */
    public function fdata(Request $request)
    {
        $this->storeRaw($request, '/iclock/fdata');

        Log::info('ZKTeco: fdata', [
            'device_sn' => $request->query('SN'),
            'body_length' => strlen($request->getContent()),
        ]);

        return response("OK\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * Section 7.2/7.3 — Key exchange for communication encryption.
     * POST /iclock/exchange?SN=...&type=publickey|factors
     */
    public function exchange(Request $request)
    {
        $this->storeRaw($request, '/iclock/exchange');

        Log::info('ZKTeco: exchange', [
            'device_sn' => $request->query('SN'),
            'type' => $request->query('type'),
        ]);

        // We don't implement encryption; acknowledge the request.
        return response("OK\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Fallback — handles double-path bugs and unrecognized endpoints
    // ─────────────────────────────────────────────────────────────────────────
    public function fallback(Request $request, string $any)
    {
        $endpoint = '/iclock/' . ltrim($any, '/');
        $this->storeRaw($request, $endpoint);

        $lower = strtolower($endpoint);

        // Route double-path hits to correct handlers.
        if (str_contains($lower, '/push') && ! str_contains($lower, '/cdata')) {
            return $this->push($request);
        }
        if (str_contains($lower, '/cdata')) {
            return $this->cdata($request);
        }
        if (str_contains($lower, '/service/control')) {
            return $this->serviceControl($request);
        }
        if (str_contains($lower, '/querydata')) {
            return $this->queryData($request);
        }
        if (str_contains($lower, '/getrequest') || str_contains($lower, '/getreq')) {
            return $this->getRequest($request);
        }
        if (str_contains($lower, '/devicecmd')) {
            return $this->deviceCmd($request);
        }
        if (str_contains($lower, '/ping')) {
            return $this->ping($request);
        }
        if (str_contains($lower, '/registry')) {
            return $this->registry($request);
        }
        if (str_contains($lower, '/exchange')) {
            return $this->exchange($request);
        }
        if (str_contains($lower, '/fdata')) {
            return $this->fdata($request);
        }

        Log::info('ZKTeco: unhandled fallback', [
            'endpoint' => $endpoint,
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);

        return response("OK\r\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Raw log storage
    // ─────────────────────────────────────────────────────────────────────────
    private function storeRaw(Request $request, string $endpoint): void
    {
        $deviceSn = $request->query('SN')
            ?? $request->input('SN')
            ?? $request->query('sn')
            ?? $request->input('sn');

        $record = ZktecoRawLog::create([
            'device_sn' => $deviceSn ? (string) $deviceSn : null,
            'endpoint' => $endpoint,
            'method' => strtoupper($request->method()),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'query_params' => $request->query(),
            'form_params' => $request->request->all(),
            'headers' => $request->headers->all(),
            'raw_body' => $request->getContent(),
        ]);

        Log::debug('ZKTeco: raw log stored', [
            'id' => $record->id,
            'endpoint' => $endpoint,
            'device_sn' => $record->device_sn,
        ]);
    }

    private function extractCmdIdFromPayload(string $payload): ?int
    {
        if (preg_match('/(?:^|[&\s])ID=(\d+)/i', $payload, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\bcmdid=(\d+)\b/i', $payload, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
