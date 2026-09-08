<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Asegurar la columna "estado" (PENDIENTE / APROBADA / RECHAZADA / INACTIVA)
        if (! Schema::hasColumn('inscripciones', 'estado')) {
            Schema::table('inscripciones', function (Blueprint $table) {
                $table->string('estado')->default('aprobada')->after('activa');
            });
        }

        // Backfill: las inscripciones activas históricas se consideran "aprobada"
        DB::table('inscripciones')
            ->where('activa', 1)
            ->where(fn ($q) => $q->whereNull('estado')->orWhere('estado', ''))
            ->update(['estado' => 'aprobada']);

        // Permitir rechazar y volver a solicitar el mismo curso:
        // un alumno puede tener varias solicitudes (rechazada + nueva pendiente).
        // La restricción unique(alumno_id, curso_id) se restaura en la migración
        // de reconciliación posterior, verificando previamente que no existan
        // duplicados.
    }

    public function down(): void
    {
        Schema::table('inscripciones', function (Blueprint $table) {
            $table->unique(['alumno_id', 'curso_id']);
        });

        if (Schema::hasColumn('inscripciones', 'estado')) {
            Schema::table('inscripciones', function (Blueprint $table) {
                $table->dropColumn('estado');
            });
        }
    }
};
