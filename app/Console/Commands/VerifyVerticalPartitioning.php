<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyVerticalPartitioning extends Command
{
    protected $signature = 'verify:partitioning';
    protected $description = 'Verify vertical partitioning was successful';

    public function handle()
    {
        $this->line("\n✅ VERTICAL PARTITIONING VERIFICATION");
        $this->line(str_repeat("=", 60));

        // Check metadata tables exist
        $this->line("\n📊 Checking Metadata Tables:");
        $tables = DB::select("SHOW TABLES LIKE '%metadata%'");
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            $this->info("  ✅ $tableName exists");
        }

        $this->line("\n📊 Tree Monitoring Logs Metadata Table:");
        $columnsCount = DB::select("
            SELECT COUNT(*) as col_count
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'tree_monitoring_logs_metadata'
        ");
        $this->line("  Columns: " . $columnsCount[0]->col_count);

        $recordsCount = DB::select("SELECT COUNT(*) as cnt FROM tree_monitoring_logs_metadata");
        $this->line("  Records: " . $recordsCount[0]->cnt);

        // Show sample data
        $samples = DB::select("
            SELECT monitoring_log_id, image_path, created_at
            FROM tree_monitoring_logs_metadata
            LIMIT 3
        ");
        $this->line("  Sample records:");
        foreach ($samples as $sample) {
            $imagePath = substr($sample->image_path ?? '', 0, 30) . (strlen($sample->image_path ?? '') > 30 ? '...' : '');
            $this->line("    - Log ID: {$sample->monitoring_log_id}, Image: $imagePath");
        }

        $this->line("\n📊 Disease Detections Metadata Table:");
        $columnsCount = DB::select("
            SELECT COUNT(*) as col_count
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'disease_detections_metadata'
        ");
        $this->line("  Columns: " . $columnsCount[0]->col_count);

        $recordsCount = DB::select("SELECT COUNT(*) as cnt FROM disease_detections_metadata");
        $this->line("  Records: " . $recordsCount[0]->cnt);

        $this->line("\n📊 Main Tables After Vertical Partitioning:");

        $treeColumns = DB::select("
            SELECT GROUP_CONCAT(COLUMN_NAME) as columns
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'tree_monitoring_logs'
        ");
        $this->line("  tree_monitoring_logs columns:");
        $cols = explode(',', $treeColumns[0]->columns);
        foreach ($cols as $col) {
            $this->line("    - $col");
        }

        $diseaseColumns = DB::select("
            SELECT GROUP_CONCAT(COLUMN_NAME) as columns
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'disease_detections'
        ");
        $this->line("\n  disease_detections columns:");
        $cols = explode(',', $diseaseColumns[0]->columns);
        foreach ($cols as $col) {
            $this->line("    - $col");
        }

        $this->info("\n✅ VERIFICATION COMPLETE");
    }
}
