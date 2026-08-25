<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = [
        'alumno_encargado',
        'alumnos',
        'asistencias',
        'boletas',
        'cache',
        'cache_locks',
        'conductas',
        'cursos',
        'detalle_cursos',
        'detalles_boletas',
        'directores',
        'encargados',
        'failed_jobs',
        'inscripciones',
        'job_batches',
        'jobs',
        'maestros',
        'materias',
        'mensualidades',
        'migrations',
        'notas',
        'pagos',
        'password_reset_tokens',
        'sessions',
        'tipo_evaluaciones',
        'users',
    ];

    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
        }
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        throw new LogicException(
            'La conversión a InnoDB no puede revertirse de forma segura a MyISAM.'
        );
    }
};
