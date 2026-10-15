<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('formulas_homeo', 'cedula_medico')) {
            Schema::table('formulas_homeo', function (Blueprint $table) {
                $table->string('cedula_medico', 50)->nullable()->after('medico')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('formulas_homeo', 'cedula_medico')) {
            Schema::table('formulas_homeo', function (Blueprint $table) {
                $table->dropColumn('cedula_medico');
            });
        }
    }
};
