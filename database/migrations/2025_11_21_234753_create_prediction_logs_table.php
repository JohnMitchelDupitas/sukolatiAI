<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('prediction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('farm_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('cacao_tree_id')->nullable()->constrained('cacao_trees')->onDelete('cascade');
            $table->string('image_path');
            $table->string('disease')->nullable();
            $table->float('confidence')->nullable();
            $table->text('recommendation')->nullable();
            $table->longText('model_response')->nullable(); // raw JSON
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('prediction_logs');
    }
};
