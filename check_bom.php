<?php
$f = __DIR__ . '/config/zkteco.php';
$b = file_get_contents($f);
$first3 = bin2hex(substr($b, 0, 3));
echo "First 3 bytes hex: $first3\n";
$hasBom = (substr($b, 0, 3) === "\xEF\xBB\xBF");
echo "Has BOM: " . ($hasBom ? "YES" : "NO") . "\n";

if ($hasBom) {
    echo "Removing BOM...\n";
    $clean = substr($b, 3);
    file_put_contents($f, $clean);
    // verify
    $b2 = file_get_contents($f);
    $first3_after = bin2hex(substr($b2, 0, 3));
    echo "After fix - First 3 bytes hex: $first3_after\n";
    echo "BOM removed: " . (substr($b2, 0, 3) !== "\xEF\xBB\xBF" ? "YES" : "NO") . "\n";
} else {
    echo "No BOM found, file is clean.\n";
}

// Also check other key files
$files = [
    __DIR__ . '/app/Http/Controllers/ZKTeco/AdmsController.php',
    __DIR__ . '/routes/web.php',
];
foreach ($files as $file) {
    $c = file_get_contents($file);
    $bom = (substr($c, 0, 3) === "\xEF\xBB\xBF");
    echo basename(dirname($file)) . '/' . basename($file) . " BOM: " . ($bom ? "YES!" : "no") . "\n";
}
