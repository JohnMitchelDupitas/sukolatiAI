<?php
define('LARAVEL_START', microtime(true));

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\CacaoTree;
use App\Models\TreeMonitoringLogs;

echo "Testing latestLog relationship...\n\n";

$tree = CacaoTree::find(7);
if ($tree) {
    echo "=== TREE DATA ===\n";
    echo "Tree ID: " . $tree->id . "\n";
    echo "Tree Code: " . $tree->tree_code . "\n";
    echo "Tree pod_count (cacao_trees table): " . $tree->pod_count . "\n\n";

    echo "=== CHECKING LATEST LOG ===\n";
    $latestLog = $tree->latestLog;
    if ($latestLog) {
        echo "✅ Latest Log Found!\n";
        echo "Latest Log ID: " . $latestLog->id . "\n";
        echo "Latest Log pod_count: " . $latestLog->pod_count . "\n";
        echo "Latest Log inspection_date: " . $latestLog->inspection_date . "\n\n";
    } else {
        echo "❌ NO LATEST LOG FOUND!\n\n";
    }

    echo "=== ALL MONITORING LOGS ===\n";
    $allLogs = TreeMonitoringLogs::where('cacao_tree_id', 7)->orderBy('inspection_date', 'desc')->get();
    echo "Total monitoring logs: " . count($allLogs) . "\n";
    foreach ($allLogs as $log) {
        echo "  - Log ID " . $log->id . ": pod_count = " . $log->pod_count . ", date = " . $log->inspection_date . "\n";
    }
} else {
    echo "❌ Tree with ID 7 not found\n";
}

?>
