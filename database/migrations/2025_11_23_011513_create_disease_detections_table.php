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
        Schema::create('disease_detections', function (Blueprint $table) {
            $table->id();

            // 1. LINK TO YOUR EXISTING TABLES
            // Who scanned it?
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            // Which specific tree?
            $table->foreignId('cacao_tree_id')->constrained('cacao_trees')->onDelete('cascade');

            // 2. AI PREDICTION RESULTS
            $table->string('image_path');        // "scans/tree_5_blackpod.jpg"
            $table->string('detected_disease');  // "Black Pod Disease"
            $table->decimal('confidence', 5, 2); // 0.95 (95%)
            $table->json('ai_response_log')->nullable(); // Save full JSON for debugging

            // 3. OPTIONAL: FARMER FEEDBACK
            // Did the farmer confirm the AI was right?
            $table->boolean('is_verified_by_user')->default(false);
            $table->text('treatment_recommendation')->nullable(); // Store logic later

            $table->timestamps(); // Created_at = Date of Scan
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disease_detections');
    }
};
