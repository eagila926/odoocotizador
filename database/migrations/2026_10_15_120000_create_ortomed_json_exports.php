<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('odoo_formula_exports', function (Blueprint $table) {
            $table->id();
            $table->string('internal_code')->unique();
            $table->char('composition_hash', 64)->unique();
            $table->timestamps();
        });
        Schema::create('odoo_json_exports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->char('content_hash', 64);
            $table->uuid('reference')->unique();
            $table->longText('payload');
            $table->timestamps();
            $table->unique(['user_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_json_exports');
        Schema::dropIfExists('odoo_formula_exports');
    }
};
