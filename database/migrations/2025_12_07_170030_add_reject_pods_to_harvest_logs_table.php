<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('harvest_logs', function (Blueprint $table) {
            $table->integer('reject_pods')
                  ->unsigned()
                  ->default(0)
                  ->after('pod_count')
                  ->comment('Number of pods rejected (damaged by pests/disease)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('harvest_logs', function (Blueprint $table) {
            $table->dropColumn('reject_pods');
        });
    }
};
