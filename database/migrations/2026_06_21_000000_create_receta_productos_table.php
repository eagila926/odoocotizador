<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('receta_productos')) {
            return;
        }

        Schema::create('receta_productos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_receta')->index();
            $table->string('cod_product')->index();
            $table->string('nombre');
            $table->timestamps();

            $table->foreign('id_receta')
                  ->references('id_receta')
                  ->on('recetas')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receta_productos');
    }
};
