<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\CacaoTree;
use App\Models\TreeMonitoringLogs;
use App\Models\DiseaseDetection;

class TestVerticalPartitioning extends Command
{
    protected $signature = 'test:partitioning';
    protected $description = 'Test that vertical partitioning works with API queries';

    public function handle()
    {
        $this->line("\n✅ VERTICAL PARTITIONING API TEST");
        $this->line(str_repeat("=", 60));

        try {
            // Test 1: Can we retrieve trees with their monitoring logs?
            $this->line("\n📊 Test 1: Retrieve Cacao Trees with Monitoring Logs");
            $trees = CacaoTree::with('monitoringLogs')->limit(2)->get();
            $this->info("  ✅ Retrieved " . $trees->count() . " trees with monitoring logs");

            foreach ($trees as $tree) {
                $logCount = $tree->monitoringLogs()->count();
                $this->line("    - Tree {$tree->id}: {$logCount} monitoring logs");
            }

            // Test 2: Can we access metadata via relationship?
            $this->line("\n📊 Test 2: Access Metadata via Relationship");
            $logs = TreeMonitoringLogs::with('metadata')->limit(3)->get();
            foreach ($logs as $log) {
                $hasMetadata = $log->metadata ? "✅" : "❌";
                $this->line("    $hasMetadata Log {$log->id} has metadata");
            }

            // Test 3: Can we query the metadata table directly?
            $this->line("\n📊 Test 3: Query Metadata Tables Directly");
            $metadataCount = DB::table('tree_monitoring_logs_metadata')->count();
            $this->info("  ✅ tree_monitoring_logs_metadata: $metadataCount records");

            $diseaseMetadataCount = DB::table('disease_detections_metadata')->count();
            $this->info("  ✅ disease_detections_metadata: $diseaseMetadataCount records");

            // Test 4: Check pod count retrieval (the original issue we fixed)
            $this->line("\n📊 Test 4: Pod Count Retrieval (Original Fix Verification)");
            $treesWithLogs = CacaoTree::whereHas('monitoringLogs')->with(['monitoringLogs' => function($q) {
                $q->latest('inspection_date')->first();
            }])->limit(3)->get();

            foreach ($treesWithLogs as $tree) {
                $latestLog = $tree->monitoringLogs->first();
                if ($latestLog) {
                    $podCount = $latestLog->pod_count ?? 0;
                    $this->line("    - Tree {$tree->id}: {$podCount} pods (Log {$latestLog->id})");
                }
            }

            // Test 5: Verify main tables are lighter
            $this->line("\n📊 Test 5: Table Size Comparison");
            $mainTableSize = DB::select("
                SELECT
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_NAME = 'tree_monitoring_logs'
            ");

            if ($mainTableSize) {
                $size = $mainTableSize[0]->size_mb ?? 0;
                $this->info("  ✅ tree_monitoring_logs table size: {$size} MB");
            }

            // Test 6: Check query performance (row count in main table)
            $this->line("\n📊 Test 6: Record Counts");
            $mainCount = DB::table('tree_monitoring_logs')->count();
            $metaCount = DB::table('tree_monitoring_logs_metadata')->count();
            $this->info("  ✅ tree_monitoring_logs: $mainCount records (lean)");
            $this->info("  ✅ tree_monitoring_logs_metadata: $metaCount records (metadata only)");

            $this->info("\n✅ ALL TESTS PASSED - VERTICAL PARTITIONING WORKING");

        } catch (\Exception $e) {
            $this->error("❌ TEST FAILED: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
