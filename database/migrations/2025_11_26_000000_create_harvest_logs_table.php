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
        Schema::create('harvest_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tree_id')
                  ->constrained('cacao_trees')
                  ->onDelete('cascade'); // If tree is deleted, delete harvest logs

            $table->integer('pod_count')
                  ->unsigned()
                  ->comment('Number of pods harvested'); // Validation ensures > 0

            $table->date('harvest_date')
                  ->default(now())
                  ->comment('Date when harvest occurred');

            $table->foreignId('harvester_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('User who performed the harvest');

            // Timestamps for tracking when this log was created/updated
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harvest_logs');
    }
};
