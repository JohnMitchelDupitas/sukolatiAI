<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cacao_tree_id')->constrained('cacao_trees')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->text('notes')->nullable();
            $table->decimal('height_m', 5, 2)->nullable();
            $table->decimal('canopy_diameter_m', 5, 2)->nullable();
            $table->boolean('flowers')->nullable();
            $table->boolean('pods')->nullable();
            $table->string('observed_pests')->nullable();
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('health_logs');
    }
};
