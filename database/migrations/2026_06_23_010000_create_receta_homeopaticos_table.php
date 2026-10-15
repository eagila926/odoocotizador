<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('receta_homeopaticos')) {
            return;
        }

        Schema::create('receta_homeopaticos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_receta')->index();
            $table->string('producto');
            $table->text('composicion');
            $table->unsignedInteger('cantidad_solicitada');
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receta_homeopaticos');
    }
};
