<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_cursos', function (Blueprint $table) {
            $table->foreignId('maestro_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // NOTA: Las filas con maestro_id NULL representan programas automáticos
        // asignados a un curso nuevo que todavía no tienen profesor asignado.
        // Antes de volver a NOT NULL, eliminamos esas filas temporales.
        DB::table('detalle_cursos')->whereNull('maestro_id')->delete();

        Schema::table('detalle_cursos', function (Blueprint $table) {
            $table->foreignId('maestro_id')->nullable(false)->change();
        });
    }
};
