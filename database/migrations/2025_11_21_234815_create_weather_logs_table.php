<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('weather_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->onDelete('cascade');
            $table->float('temperature')->nullable();
            $table->float('humidity')->nullable();
            $table->float('rainfall')->nullable();
            $table->float('wind_speed')->nullable();
            $table->integer('cloudiness')->nullable();
            $table->longText('raw')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('weather_logs');
    }
};
