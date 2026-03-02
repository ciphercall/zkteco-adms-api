<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check for BOM in config file
$configBytes = file_get_contents(__DIR__ . '/config/zkteco.php');
$firstThreeHex = bin2hex(substr($configBytes, 0, 3));
echo "Config first 3 bytes hex: {$firstThreeHex}\n";
echo "Has BOM: " . ($firstThreeHex === 'efbbbf' ? 'YES - PROBLEM!' : 'No') . "\n\n";

// Show recent endpoints from device
$logs = App\Models\ZktecoRawLog::where('created_at', '>=', now()->subMinutes(2))
    ->where('ip', '192.168.10.97')
    ->orderByDesc('id')
    ->take(15)
    ->get(['id', 'endpoint', 'method', 'created_at']);

echo "=== RECENT DEVICE REQUESTS (last 2 min) ===\n";
foreach ($logs as $log) {
    echo "{$log->id}: {$log->method} {$log->endpoint} at {$log->created_at}\n";
}
echo "Total: {$logs->count()}\n";

// Show unique endpoints
$unique = App\Models\ZktecoRawLog::where('created_at', '>=', now()->subMinutes(2))
    ->where('ip', '192.168.10.97')
    ->select('endpoint')
    ->distinct()
    ->pluck('endpoint');
echo "\nUnique endpoints: " . $unique->implode(', ') . "\n";

// Check what the latest cdata GET response looks like (simulate)
echo "\n=== SIMULATED CDATA RESPONSE ===\n";
$sn = 'VGU6251500098';
$staticLines = config('zkteco.iclock.cdata_get_lines', []);
$dynamicLines = [
    "GET OPTION FROM: {$sn}",
    'Stamp=9999',
    'OpStamp=' . time(),
];
$allLines = array_merge($dynamicLines, $staticLines);
$response = implode("\r\n", $allLines) . "\r\n";
echo $response;
echo "Response length: " . strlen($response) . " bytes\n";
