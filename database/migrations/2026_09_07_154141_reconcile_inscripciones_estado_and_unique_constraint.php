<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Reconciliar estados históricos ──────────────────────────────
        // activa = 0 AND estado = 'aprobada'  →  estado = 'inactiva'
        // No se tocan solicitudes pendiente o rechazada.
        // No se eliminan registros. No se exponen datos personales.
        DB::table('inscripciones')
            ->where('activa', 0)
            ->where('estado', 'aprobada')
            ->update(['estado' => 'inactiva']);

        // ── 2. Verificar duplicados (alumno_id + curso_id) ─────────────────
        $duplicados = DB::table('inscripciones')
            ->select('alumno_id', 'curso_id', DB::raw('COUNT(*) as total'))
            ->groupBy('alumno_id', 'curso_id')
            ->having('total', '>', 1)
            ->exists();

        if ($duplicados) {
            throw new RuntimeException(
                'Existen duplicados de (alumno_id, curso_id) en inscripciones. '.
                'Resuelva los registros duplicados antes de aplicar esta migración. '.
                'No se eliminaron filas.'
            );
        }

        // ── 3. Restaurar unique(alumno_id, curso_id) si no existe ──────────
        $indexName = 'inscripciones_alumno_id_curso_id_unique';
        $exists = Schema::hasIndex('inscripciones', ['alumno_id', 'curso_id'])
            || Schema::hasIndex('inscripciones', $indexName);

        if (! $exists) {
            Schema::table('inscripciones', function (Blueprint $table) use ($indexName) {
                $table->unique(['alumno_id', 'curso_id'], $indexName);
            });
        }
    }

    public function down(): void
    {
        // El down() NO cambia estados ni elimina solicitudes.
        // Solo elimina el índice unique si existe.
        $indexName = 'inscripciones_alumno_id_curso_id_unique';
        if (Schema::hasIndex('inscripciones', ['alumno_id', 'curso_id'])
            && Schema::hasIndex('inscripciones', $indexName)) {
            Schema::table('inscripciones', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }
    }
};
