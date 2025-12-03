<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Vertical Partitioning Migration
     *
     * Separates large/rarely-used columns into separate tables
     * to optimize query performance and storage
     */
    public function up(): void
    {
        try {
            Log::info('🔄 [VERTICAL PARTITIONING] Starting vertical partitioning migration');

            // ✅ Step 1: Vertical partition tree_monitoring_logs
            $this->verticalPartitionTreeMonitoringLogs();

            // ✅ Step 2: Vertical partition disease_detections
            $this->verticalPartitionDiseaseDetections();

            Log::info('✅ [VERTICAL PARTITIONING] Migration completed successfully');

        } catch (\Exception $e) {
            Log::error('❌ [VERTICAL PARTITIONING] Migration failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Vertical Partition: tree_monitoring_logs
     *
     * Split into:
     * - tree_monitoring_logs (main: frequently accessed columns)
     * - tree_monitoring_logs_metadata (rarely used: image_path, timestamps)
     *
     * Benefits:
     * - Faster main queries (smaller row size)
     * - Better cache utilization
     * - Image data in separate table (optional archival)
     * - Timestamps kept for audit trail but not in main query path
     */
    private function verticalPartitionTreeMonitoringLogs(): void
    {
        Log::info('📊 [TREE_MONITORING_LOGS] Starting vertical partitioning');

        try {
            // ✅ Step 1: Create metadata table for images and timestamps
            if (!Schema::hasTable('tree_monitoring_logs_metadata')) {
                Schema::create('tree_monitoring_logs_metadata', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('monitoring_log_id')
                          ->unique()
                          ->constrained('tree_monitoring_logs')
                          ->onDelete('cascade');

                    // Large columns that are rarely accessed
                    $table->longText('image_path')->nullable()->comment('Image path - archived');

                    // Timestamp audit trail - using raw syntax for MariaDB compatibility
                    $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                    $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));

                    // Indexes for lookups
                    $table->index('monitoring_log_id');
                    $table->index('created_at');
                });

                Log::info('✅ [TREE_MONITORING_LOGS_METADATA] Table created');
            }

            // ✅ Step 2: Migrate existing data to metadata table
            $existingLogs = DB::table('tree_monitoring_logs')->get();

            foreach ($existingLogs as $log) {
                DB::table('tree_monitoring_logs_metadata')->insert([
                    'monitoring_log_id' => $log->id,
                    'image_path' => $log->image_path,
                    'created_at' => $log->created_at,
                    'updated_at' => $log->updated_at,
                ]);
            }

            Log::info('✅ [TREE_MONITORING_LOGS] Data migrated to metadata table', [
                'rows_migrated' => $existingLogs->count()
            ]);

            // ✅ Step 3: Drop columns from main table (if they exist)
            if (Schema::hasTable('tree_monitoring_logs')) {
                Schema::table('tree_monitoring_logs', function (Blueprint $table) {
                    // Drop image_path, created_at, updated_at
                    if (Schema::hasColumn('tree_monitoring_logs', 'image_path')) {
                        $table->dropColumn('image_path');
                    }
                    // Note: created_at and updated_at are Laravel timestamps, keep them minimal
                    // We'll manage them via the relationship
                });

                Log::info('✅ [TREE_MONITORING_LOGS] Large columns removed from main table');
            }

        } catch (\Exception $e) {
            Log::error('❌ [TREE_MONITORING_LOGS] Vertical partitioning failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Vertical Partition: disease_detections
     *
     * Split into:
     * - disease_detections (main: detection info)
     * - disease_detections_metadata (rarely used: verification, treatment, timestamps)
     *
     * Benefits:
     * - Lighter main table for queries
     * - Treatment recommendations in separate table
     * - Verification data isolated for audit
     * - Better performance for disease listing
     */
    private function verticalPartitionDiseaseDetections(): void
    {
        Log::info('📊 [DISEASE_DETECTIONS] Starting vertical partitioning');

        try {
            // ✅ Step 1: Create metadata table
            if (!Schema::hasTable('disease_detections_metadata')) {
                Schema::create('disease_detections_metadata', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('detection_id')
                          ->unique()
                          ->constrained('disease_detections')
                          ->onDelete('cascade');

                    // Rarely used verification columns
                    $table->boolean('is_verified_by_user')->default(false)->comment('User verification flag');
                    $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete()->comment('User who verified');

                    // Treatment recommendations (large text field)
                    $table->longText('treatment_recommendations')->nullable()->comment('Treatment suggestions');

                    // Timestamp audit trail - using raw syntax for MariaDB compatibility
                    $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                    $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));

                    // Indexes for lookups
                    $table->index('detection_id');
                    $table->index('is_verified_by_user');
                    $table->index('created_at');
                });

                Log::info('✅ [DISEASE_DETECTIONS_METADATA] Table created');
            }

            // ✅ Step 2: Migrate existing data to metadata table
            $existingDetections = DB::table('disease_detections')->get();

            foreach ($existingDetections as $detection) {
                DB::table('disease_detections_metadata')->insert([
                    'detection_id' => $detection->id,
                    'is_verified_by_user' => $detection->is_verified_by_user ?? false,
                    'verified_by' => $detection->verified_by ?? null,
                    'treatment_recommendations' => $detection->treatment_recommendations ?? null,
                    'created_at' => $detection->created_at,
                    'updated_at' => $detection->updated_at,
                ]);
            }

            Log::info('✅ [DISEASE_DETECTIONS] Data migrated to metadata table', [
                'rows_migrated' => $existingDetections->count()
            ]);

            // ✅ Step 3: Drop columns from main table
            if (Schema::hasTable('disease_detections')) {
                Schema::table('disease_detections', function (Blueprint $table) {
                    // Drop rarely-used columns
                    if (Schema::hasColumn('disease_detections', 'is_verified_by_user')) {
                        $table->dropColumn('is_verified_by_user');
                    }
                    if (Schema::hasColumn('disease_detections', 'verified_by')) {
                        $table->dropColumn('verified_by');
                    }
                    if (Schema::hasColumn('disease_detections', 'treatment_recommendations')) {
                        $table->dropColumn('treatment_recommendations');
                    }
                });

                Log::info('✅ [DISEASE_DETECTIONS] Large columns removed from main table');
            }

        } catch (\Exception $e) {
            Log::error('❌ [DISEASE_DETECTIONS] Vertical partitioning failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Reverse the migrations
     *
     * ⚠️ This will restore columns but data in separate tables will be lost
     * Always backup before running rollback!
     */
    public function down(): void
    {
        try {
            Log::info('🔄 [VERTICAL PARTITIONING] Starting rollback');

            // ✅ Step 1: Restore tree_monitoring_logs columns
            if (Schema::hasTable('tree_monitoring_logs')) {
                Schema::table('tree_monitoring_logs', function (Blueprint $table) {
                    // Restore image_path
                    if (!Schema::hasColumn('tree_monitoring_logs', 'image_path')) {
                        $table->longText('image_path')->nullable()->after('pod_count');
                    }
                });

                // Restore data from metadata table
                if (Schema::hasTable('tree_monitoring_logs_metadata')) {
                    $metadata = DB::table('tree_monitoring_logs_metadata')->get();
                    foreach ($metadata as $meta) {
                        DB::table('tree_monitoring_logs')
                            ->where('id', $meta->monitoring_log_id)
                            ->update(['image_path' => $meta->image_path]);
                    }
                }

                Log::info('✅ [TREE_MONITORING_LOGS] Columns restored');
            }

            // ✅ Step 2: Restore disease_detections columns
            if (Schema::hasTable('disease_detections')) {
                Schema::table('disease_detections', function (Blueprint $table) {
                    // Restore verification columns
                    if (!Schema::hasColumn('disease_detections', 'is_verified_by_user')) {
                        $table->boolean('is_verified_by_user')->default(false)->after('severity_level');
                    }
                    if (!Schema::hasColumn('disease_detections', 'verified_by')) {
                        $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete()->after('is_verified_by_user');
                    }
                    if (!Schema::hasColumn('disease_detections', 'treatment_recommendations')) {
                        $table->longText('treatment_recommendations')->nullable()->after('verified_by');
                    }
                });

                // Restore data from metadata table
                if (Schema::hasTable('disease_detections_metadata')) {
                    $metadata = DB::table('disease_detections_metadata')->get();
                    foreach ($metadata as $meta) {
                        DB::table('disease_detections')
                            ->where('id', $meta->detection_id)
                            ->update([
                                'is_verified_by_user' => $meta->is_verified_by_user,
                                'verified_by' => $meta->verified_by,
                                'treatment_recommendations' => $meta->treatment_recommendations,
                            ]);
                    }
                }

                Log::info('✅ [DISEASE_DETECTIONS] Columns restored');
            }

            // ✅ Step 3: Drop metadata tables
            if (Schema::hasTable('tree_monitoring_logs_metadata')) {
                Schema::dropIfExists('tree_monitoring_logs_metadata');
                Log::info('✅ [TREE_MONITORING_LOGS_METADATA] Table dropped');
            }

            if (Schema::hasTable('disease_detections_metadata')) {
                Schema::dropIfExists('disease_detections_metadata');
                Log::info('✅ [DISEASE_DETECTIONS_METADATA] Table dropped');
            }

            Log::info('✅ [VERTICAL PARTITIONING] Rollback completed');

        } catch (\Exception $e) {
            Log::error('❌ [VERTICAL PARTITIONING] Rollback failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
};
