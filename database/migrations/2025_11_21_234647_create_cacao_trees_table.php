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
            $table->string('tree_code')->unique(); // e.g., 'TR-001-BLK-A'

            // Grouping
            $table->string('block_name')->nullable(); // Good for filtering
            $table->string('variety')->nullable(); // e.g., 'BR-25', 'UF-18'

            // Lifecycle
            $table->date('date_planted')->nullable();

            // GIS Precision (10,7 is perfect for ~1cm accuracy)
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('cacao_trees');
    }
};
