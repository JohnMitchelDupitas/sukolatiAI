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
        // 2024_01_02_create_tree_monitoring_logs_table.php
        Schema::create('tree_monitoring_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cacao_tree_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained(); // Who inspected it?

            // The Condition
            $table->string('status'); // 'healthy', 'diseased', 'needs_pruning'
            $table->string('disease_type')->nullable(); // 'black_pod_rot', 'stem_borer'
            $table->text('remedy_applied')->nullable(); // 'fungicide_sprayed', 'pruned'

            // Visual Evidence (Crucial for your image processing interest)
            $table->string('image_path')->nullable();

            // Data for Inventory
            $table->integer('pod_count')->default(0); // Estimated yield

            $table->date('inspection_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tree_monitoring_logs');
    }
};
