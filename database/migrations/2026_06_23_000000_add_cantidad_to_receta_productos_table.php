<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('receta_productos', 'cantidad')) {
            return;
        }

        Schema::table('receta_productos', function (Blueprint $table) {
            $table->unsignedInteger('cantidad')->default(1)->after('nombre');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('receta_productos', 'cantidad')) {
            return;
        }

        Schema::table('receta_productos', function (Blueprint $table) {
            $table->dropColumn('cantidad');
        });
    }
};
