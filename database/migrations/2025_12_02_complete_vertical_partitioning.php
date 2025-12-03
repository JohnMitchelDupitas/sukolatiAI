<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Complete Vertical Partitioning Migration
     *
     * Drops the remaining metadata columns from main tables
     * Keeps timestamps for audit trail but they're managed via metadata tables
     */
    public function up(): void
    {
        try {
            Log::info('[VERTICAL_PARTITIONING_COMPLETE] Finalizing column drops');

            // Drop is_verified_by_user, treatment_recommendations from disease_detections
            if (Schema::hasTable('disease_detections')) {
                Schema::table('disease_detections', function (Blueprint $table) {
                    $columns = DB::select("
                        SELECT COLUMN_NAME
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_NAME = 'disease_detections'
                        AND COLUMN_NAME IN ('is_verified_by_user', 'treatment_recommendation')
                    ");

                    $columnsToRemove = array_map(function($col) {
                        return $col->COLUMN_NAME;
                    }, $columns);

                    if (!empty($columnsToRemove)) {
                        $table->dropColumn($columnsToRemove);
                        Log::info('[DISEASE_DETECTIONS] Dropped columns', ['columns' => $columnsToRemove]);
                    }
                });
            }

            Log::info('[VERTICAL_PARTITIONING_COMPLETE] Migration completed');

        } catch (\Exception $e) {
            Log::error('[VERTICAL_PARTITIONING_COMPLETE] Migration failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Rollback the migration
     */
    public function down(): void
    {
        try {
            Log::info('[VERTICAL_PARTITIONING_COMPLETE] Rolling back column drops');

            // Restore columns
            if (Schema::hasTable('disease_detections')) {
                Schema::table('disease_detections', function (Blueprint $table) {
                    $table->boolean('is_verified_by_user')->default(false)->after('confidence');
                    $table->longText('treatment_recommendation')->nullable()->after('is_verified_by_user');
                });
                Log::info('[DISEASE_DETECTIONS] Restored columns');
            }

            Log::info('[VERTICAL_PARTITIONING_COMPLETE] Rollback completed');

        } catch (\Exception $e) {
            Log::error('[VERTICAL_PARTITIONING_COMPLETE] Rollback failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }
};
