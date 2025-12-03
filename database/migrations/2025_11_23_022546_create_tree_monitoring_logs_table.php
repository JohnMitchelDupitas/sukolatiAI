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
        Schema::create('tree_monitoring_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cacao_tree_id')->constrained('cacao_trees')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['healthy', 'diseased'])->default('healthy');
            $table->string('disease_type')->nullable();
            $table->integer('pod_count')->default(0);
            $table->string('image_path')->nullable();
            $table->date('inspection_date');
            $table->timestamps();

            // Add indexes for better query performance
            $table->index('cacao_tree_id');
            $table->index('user_id');
            $table->index('inspection_date');
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
