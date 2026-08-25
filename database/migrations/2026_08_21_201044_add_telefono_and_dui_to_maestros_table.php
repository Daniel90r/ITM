<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maestros', function (Blueprint $table) {
            $table->string('telefono', 9)->nullable()->after('apellido');
            $table->string('dui', 10)->nullable()->after('telefono');
            $table->unique('dui', 'maestros_dui_unique');
        });
    }

    public function down(): void
    {
        Schema::table('maestros', function (Blueprint $table) {
            $table->dropUnique('maestros_dui_unique');
            $table->dropColumn(['telefono', 'dui']);
        });
    }
};
