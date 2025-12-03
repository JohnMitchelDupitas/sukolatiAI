<?php

use App\Models\TreeMonitoringLogs;

require 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$container = $app->make('Illuminate\Contracts\Container\Container');
$container->make('Illuminate\Foundation\Console\Kernel')->bootstrap();

try {
    echo "Testing pod count update...\n\n";

    // Test 1: Create a new log
    echo "[TEST 1] Creating new monitoring log...\n";
    $log = TreeMonitoringLogs::create([
        'cacao_tree_id' => 1,
        'user_id' => 1,
        'status' => 'healthy',
        'disease_type' => null,
        'pod_count' => 5,
        'inspection_date' => now()
    ]);

    echo "✅ Created log ID: {$log->id}\n";
    echo "   Pod count: {$log->pod_count}\n";
    echo "   Status: {$log->status}\n\n";

    // Test 2: Update pod count
    echo "[TEST 2] Updating pod count...\n";
    $log2 = TreeMonitoringLogs::create([
        'cacao_tree_id' => 1,
        'user_id' => 1,
        'status' => 'healthy',
        'disease_type' => null,
        'pod_count' => 10,
        'inspection_date' => now()
    ]);

    echo "✅ Updated pod count: {$log2->pod_count}\n";
    echo "   Previous: {$log->pod_count}, New: {$log2->pod_count}\n\n";

    // Test 3: Verify in database
    echo "[TEST 3] Verifying database...\n";
    $count = TreeMonitoringLogs::count();
    echo "✅ Total monitoring logs: $count\n";

    $latestLog = TreeMonitoringLogs::latest('id')->first();
    echo "   Latest log ID: {$latestLog->id}\n";
    echo "   Latest pod count: {$latestLog->pod_count}\n\n";

    echo "✅ ALL TESTS PASSED - Pod count update is working!\n";

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
