<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceUserForeignKey('activos_homeo_temp', 'usuarios', 'id_user', false);
        $this->replaceUserForeignKey('formulas_homeo', 'usuarios', 'id_user', false);
    }

    public function down(): void
    {
        $this->replaceUserForeignKey('activos_homeo_temp', 'users', 'id', true);
        $this->replaceUserForeignKey('formulas_homeo', 'users', 'id', true);
    }

    private function replaceUserForeignKey(
        string $table,
        string $referencedTable,
        string $referencedColumn,
        bool $unsignedBigInteger
    ): void {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'user_id')) {
            return;
        }

        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', 'user_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($constraints as $constraint) {
            Schema::table($table, function (Blueprint $blueprint) use ($constraint) {
                $blueprint->dropForeign($constraint);
            });
        }

        Schema::table($table, function (Blueprint $blueprint) use ($unsignedBigInteger) {
            if ($unsignedBigInteger) {
                $blueprint->unsignedBigInteger('user_id')->change();
            } else {
                $blueprint->integer('user_id')->change();
            }
        });

        Schema::table($table, function (Blueprint $blueprint) use (
            $referencedTable,
            $referencedColumn
        ) {
            $blueprint->foreign('user_id')
                ->references($referencedColumn)
                ->on($referencedTable)
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }
};
