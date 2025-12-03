<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // 1. Add indexes to farms table
            if (Schema::hasTable('farms')) {
                Schema::table('farms', function (Blueprint $table) {
                    if (!$this->indexExists('farms', 'farms_user_id_index')) {
                        $table->index('user_id');
                    }
                    if (!$this->indexExists('farms', 'farms_created_at_index')) {
                        $table->index('created_at');
                    }
                });
            }

            // 2. Add indexes to cacao_trees table
            if (Schema::hasTable('cacao_trees')) {
                Schema::table('cacao_trees', function (Blueprint $table) {
                    if (!$this->indexExists('cacao_trees', 'cacao_trees_farm_id_index')) {
                        $table->index('farm_id');
                    }
                    if (!$this->indexExists('cacao_trees', 'cacao_trees_tree_code_index')) {
                        $table->index('tree_code');
                    }
                    if (!$this->indexExists('cacao_trees', 'cacao_trees_variety_index')) {
                        $table->index('variety');
                    }
                    if (!$this->indexExists('cacao_trees', 'cacao_trees_created_at_index')) {
                        $table->index('created_at');
                    }
                });
            }

            // 3. Add indexes to harvest_logs table
            if (Schema::hasTable('harvest_logs')) {
                Schema::table('harvest_logs', function (Blueprint $table) {
                    if (!$this->indexExists('harvest_logs', 'harvest_logs_tree_id_index')) {
                        $table->index('tree_id');
                    }
                    if (!$this->indexExists('harvest_logs', 'harvest_logs_harvester_id_index')) {
                        $table->index('harvester_id');
                    }
                    if (!$this->indexExists('harvest_logs', 'harvest_logs_harvest_date_index')) {
                        $table->index('harvest_date');
                    }
                    if (!$this->indexExists('harvest_logs', 'harvest_logs_tree_id_harvest_date_index')) {
                        $table->index(['tree_id', 'harvest_date']);
                    }
                    if (!$this->indexExists('harvest_logs', 'harvest_logs_created_at_index')) {
                        $table->index('created_at');
                    }
                });
            }

            // 4. Add indexes to health_logs table
            if (Schema::hasTable('health_logs')) {
                Schema::table('health_logs', function (Blueprint $table) {
                    if (!$this->indexExists('health_logs', 'health_logs_tree_id_index')) {
                        $table->index('tree_id');
                    }
                    if (!$this->indexExists('health_logs', 'health_logs_created_at_index')) {
                        $table->index('created_at');
                    }
                });
            }

            // 5. Add indexes to prediction_logs table
            if (Schema::hasTable('prediction_logs')) {
                Schema::table('prediction_logs', function (Blueprint $table) {
                    if (!$this->indexExists('prediction_logs', 'prediction_logs_tree_id_index')) {
                        $table->index('tree_id');
                    }
                    if (!$this->indexExists('prediction_logs', 'prediction_logs_created_at_index')) {
                        $table->index('created_at');
                    }
                });
            }

            // 6. Add indexes to disease_detections table
            if (Schema::hasTable('disease_detections')) {
                Schema::table('disease_detections', function (Blueprint $table) {
                    if (!$this->indexExists('disease_detections', 'disease_detections_cacao_tree_id_index')) {
                        $table->index('cacao_tree_id');
                    }
                    if (!$this->indexExists('disease_detections', 'disease_detections_detected_date_index')) {
                        $table->index('detected_date');
                    }
                    if (!$this->indexExists('disease_detections', 'disease_detections_created_at_index')) {
                        $table->index('created_at');
                    }
                });
            }

            // 7. Add indexes to weather_logs table
            if (Schema::hasTable('weather_logs')) {
                Schema::table('weather_logs', function (Blueprint $table) {
                    if (!$this->indexExists('weather_logs', 'weather_logs_farm_id_index')) {
                        $table->index('farm_id');
                    }
                    if (!$this->indexExists('weather_logs', 'weather_logs_date_index')) {
                        $table->index('date');
                    }
                    if (!$this->indexExists('weather_logs', 'weather_logs_created_at_index')) {
                        $table->index('created_at');
                    }
                });
            }

            // 8. Add indexes to notifications table
            if (Schema::hasTable('notifications')) {
                Schema::table('notifications', function (Blueprint $table) {
                    if (!$this->indexExists('notifications', 'notifications_user_id_index')) {
                        $table->index('user_id');
                    }
                    if (!$this->indexExists('notifications', 'notifications_created_at_index')) {
                        $table->index('created_at');
                    }
                });
            }

            // 9. Add indexes to login_histories table
            if (Schema::hasTable('login_histories')) {
                Schema::table('login_histories', function (Blueprint $table) {
                    if (!$this->indexExists('login_histories', 'login_histories_user_id_index')) {
                        $table->index('user_id');
                    }
                    if (!$this->indexExists('login_histories', 'login_histories_login_date_index')) {
                        $table->index('login_date');
                    }
                    if (!$this->indexExists('login_histories', 'login_histories_created_at_index')) {
                        $table->index('created_at');
                    }
                });
            }

            // 10. Add indexes to audits table
            if (Schema::hasTable('audits')) {
                Schema::table('audits', function (Blueprint $table) {
                    if (!$this->indexExists('audits', 'audits_user_id_index')) {
                        $table->index('user_id');
                    }
                    if (!$this->indexExists('audits', 'audits_auditable_id_index')) {
                        $table->index('auditable_id');
                    }
                    if (!$this->indexExists('audits', 'audits_auditable_type_index')) {
                        $table->index('auditable_type');
                    }
                    if (!$this->indexExists('audits', 'audits_created_at_index')) {
                        $table->index('created_at');
                    }
                });
            }

        } catch (\Exception $e) {
            // Silent fail - continue anyway
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            // Drop indexes safely with existence checks
            if (Schema::hasTable('farms')) {
                Schema::table('farms', function (Blueprint $table) {
                    if ($this->indexExists('farms', 'farms_user_id_index')) {
                        $table->dropIndex('farms_user_id_index');
                    }
                    if ($this->indexExists('farms', 'farms_created_at_index')) {
                        $table->dropIndex('farms_created_at_index');
                    }
                });
            }

            if (Schema::hasTable('cacao_trees')) {
                Schema::table('cacao_trees', function (Blueprint $table) {
                    if ($this->indexExists('cacao_trees', 'cacao_trees_farm_id_index')) {
                        $table->dropIndex('cacao_trees_farm_id_index');
                    }
                    if ($this->indexExists('cacao_trees', 'cacao_trees_tree_code_index')) {
                        $table->dropIndex('cacao_trees_tree_code_index');
                    }
                    if ($this->indexExists('cacao_trees', 'cacao_trees_variety_index')) {
                        $table->dropIndex('cacao_trees_variety_index');
                    }
                    if ($this->indexExists('cacao_trees', 'cacao_trees_created_at_index')) {
                        $table->dropIndex('cacao_trees_created_at_index');
                    }
                });
            }

            if (Schema::hasTable('harvest_logs')) {
                Schema::table('harvest_logs', function (Blueprint $table) {
                    if ($this->indexExists('harvest_logs', 'harvest_logs_tree_id_index')) {
                        $table->dropIndex('harvest_logs_tree_id_index');
                    }
                    if ($this->indexExists('harvest_logs', 'harvest_logs_harvester_id_index')) {
                        $table->dropIndex('harvest_logs_harvester_id_index');
                    }
                    if ($this->indexExists('harvest_logs', 'harvest_logs_harvest_date_index')) {
                        $table->dropIndex('harvest_logs_harvest_date_index');
                    }
                    if ($this->indexExists('harvest_logs', 'harvest_logs_tree_id_harvest_date_index')) {
                        $table->dropIndex('harvest_logs_tree_id_harvest_date_index');
                    }
                    if ($this->indexExists('harvest_logs', 'harvest_logs_created_at_index')) {
                        $table->dropIndex('harvest_logs_created_at_index');
                    }
                });
            }

        } catch (\Exception $e) {
            // Silent fail - continue anyway
        }
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists($table, $index): bool
    {
        try {
            $indexes = DB::select("SHOW INDEXES FROM `{$table}` WHERE Key_name = ?", [$index]);
            return count($indexes) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};

