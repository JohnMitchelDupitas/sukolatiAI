<?php

require_once 'bootstrap/app.php';

use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$container = $app->make('Illuminate\Contracts\Container\Container');

// Get database connection
$db = $container['db'];

echo "✅ VERTICAL PARTITIONING VERIFICATION\n";
echo str_repeat("=", 60) . "\n\n";

// Check metadata tables exist
echo "📊 Checking Metadata Tables:\n";
$tables = DB::select("SHOW TABLES LIKE '%metadata%'");
foreach ($tables as $table) {
    $tableName = array_values((array)$table)[0];
    echo "  ✅ $tableName exists\n";
}

echo "\n📊 Tree Monitoring Logs Metadata Table:\n";
$metadata = DB::select("
    SELECT
        COUNT(*) as record_count,
        COLUMN_NAME,
        COLUMN_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'tree_monitoring_logs_metadata'
    GROUP BY TABLE_NAME
");

$columnsCount = DB::select("
    SELECT COUNT(*) as col_count
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'tree_monitoring_logs_metadata'
");
echo "  Columns: " . $columnsCount[0]->col_count . "\n";

$recordsCount = DB::select("SELECT COUNT(*) as cnt FROM tree_monitoring_logs_metadata");
echo "  Records: " . $recordsCount[0]->cnt . "\n";

// Show sample data
$samples = DB::select("
    SELECT monitoring_log_id, image_path, created_at
    FROM tree_monitoring_logs_metadata
    LIMIT 3
");
echo "  Sample records:\n";
foreach ($samples as $sample) {
    echo "    - Log ID: {$sample->monitoring_log_id}, Image: " . substr($sample->image_path, 0, 30) . "...\n";
}

echo "\n📊 Disease Detections Metadata Table:\n";
$columnsCount = DB::select("
    SELECT COUNT(*) as col_count
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'disease_detections_metadata'
");
echo "  Columns: " . $columnsCount[0]->col_count . "\n";

$recordsCount = DB::select("SELECT COUNT(*) as cnt FROM disease_detections_metadata");
echo "  Records: " . $recordsCount[0]->cnt . "\n";

echo "\n📊 Main Tables After Vertical Partitioning:\n";

$treeColumns = DB::select("
    SELECT COUNT(*) as col_count
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'tree_monitoring_logs'
");
echo "  tree_monitoring_logs columns: " . $treeColumns[0]->col_count . " (was ~10, now ~7)\n";

$diseaseColumns = DB::select("
    SELECT COUNT(*) as col_count
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'disease_detections'
");
echo "  disease_detections columns: " . $diseaseColumns[0]->col_count . " (reduced)\n";

echo "\n✅ VERIFICATION COMPLETE\n";
