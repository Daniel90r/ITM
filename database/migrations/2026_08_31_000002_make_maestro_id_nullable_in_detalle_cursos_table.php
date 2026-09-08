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
        // NOTA: Las filas con maestro_id NULL representan programas sin profesor
        // asignado todavía. Antes de restaurar NOT NULL, verificamos que no existan
        // valores nulos. No se eliminan registros bajo ninguna circunstancia.

        $nulos = DB::table('detalle_cursos')->whereNull('maestro_id')->exists();

        if ($nulos) {
            throw new \RuntimeException(
                'No se puede revertir maestro_id a NOT NULL porque existen registros ' .
                'en detalle_cursos con maestro_id NULL. Asocie un maestro a esos ' .
                'programas antes de revertir.'
            );
        }

        Schema::table('detalle_cursos', function (Blueprint $table) {
            $table->foreignId('maestro_id')->nullable(false)->change();
        });
    }
};
