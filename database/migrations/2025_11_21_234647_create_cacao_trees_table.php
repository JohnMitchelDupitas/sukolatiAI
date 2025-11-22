<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('cacao_trees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->onDelete('cascade');
            $table->string('block_name')->nullable(); // grouping e.g., Block A
            $table->integer('tree_count')->default(1);
            $table->string('variety')->nullable();
            $table->date('date_planted')->nullable();
            $table->string('growth_stage')->nullable(); // seedling, vegetative, flowering, podding, mature
            $table->string('status')->default('healthy');
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('cacao_trees');
    }
};
