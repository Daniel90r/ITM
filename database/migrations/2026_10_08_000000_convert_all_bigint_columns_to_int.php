<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Conversión a aplicar en cada columna BIGINT.
     *
     * Formato:  'tabla.columna' => 'definición MySQL objetivo'.
     *
     * - Las columnas `id` conservan AUTO_INCREMENT (clave primaria preservada).
     * - cache.expiration y cache_locks.expiration son BIGINT SIGNED  -> INT SIGNED.
     * - sessions.user_id, cache.expiration, cache_locks.expiration no tienen FK.
     */
    public const CONVERSIONS = [
        'alumno_encargado.id'              => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'alumno_encargado.alumno_id'       => 'INT UNSIGNED NOT NULL',
        'alumno_encargado.encargado_id'    => 'INT UNSIGNED NOT NULL',

        'alumno_materia.id'                => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'alumno_materia.alumno_id'         => 'INT UNSIGNED NOT NULL',
        'alumno_materia.curso_id'          => 'INT UNSIGNED NOT NULL',
        'alumno_materia.materia_id'        => 'INT UNSIGNED NOT NULL',

        'alumnos.id'                       => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'alumnos.user_id'                  => 'INT UNSIGNED NULL',

        'asignaciones_docentes.id'         => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'asignaciones_docentes.detalle_curso_id' => 'INT UNSIGNED NOT NULL',
        'asignaciones_docentes.maestro_id' => 'INT UNSIGNED NOT NULL',
        'asignaciones_docentes.asignado_por' => 'INT UNSIGNED NULL',

        'asistencias.id'                   => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'asistencias.curso_id'             => 'INT UNSIGNED NOT NULL',
        'asistencias.alumno_id'            => 'INT UNSIGNED NOT NULL',
        'asistencias.registrado_por'       => 'INT UNSIGNED NOT NULL',
        'asistencias.detalle_curso_id'     => 'INT UNSIGNED NULL',

        'boletas.id'                       => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',

        'cache.expiration'                 => 'INT NOT NULL',            // signed
        'cache_locks.expiration'           => 'INT NOT NULL',            // signed

        'cursos.id'                        => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',

        'detalle_cursos.id'                => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'detalle_cursos.curso_id'          => 'INT UNSIGNED NOT NULL',
        'detalle_cursos.materia_id'        => 'INT UNSIGNED NOT NULL',
        'detalle_cursos.maestro_id'        => 'INT UNSIGNED NULL',

        'detalles_boletas.id'              => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',

        'directores.id'                    => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'directores.user_id'               => 'INT UNSIGNED NULL',

        'encargados.id'                    => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',

        'failed_jobs.id'                   => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',

        'inscripciones.id'                 => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'inscripciones.alumno_id'          => 'INT UNSIGNED NOT NULL',
        'inscripciones.curso_id'           => 'INT UNSIGNED NOT NULL',

        'jobs.id'                          => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',

        'maestros.id'                      => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'maestros.user_id'                 => 'INT UNSIGNED NOT NULL',

        'materias.id'                      => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',

        'mensualidades.id'                 => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'mensualidades.alumno_id'          => 'INT UNSIGNED NOT NULL',
        'mensualidades.curso_id'           => 'INT UNSIGNED NULL',

        'notas.id'                         => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'notas.alumno_id'                  => 'INT UNSIGNED NOT NULL',
        'notas.detalle_curso_id'           => 'INT UNSIGNED NOT NULL',
        'notas.registrado_por'             => 'INT UNSIGNED NOT NULL',

        'pagos.id'                         => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'pagos.mensualidad_id'             => 'INT UNSIGNED NOT NULL',   // UNIQUE preservado por MODIFY

        'sessions.user_id'                 => 'INT UNSIGNED NULL',       // index sin FK

        'users.id'                         => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
    ];

    /**
     * Las 26 constraints FK reales detectadas en INFORMATION_SCHEMA.
     *
     * Formato: 'nombre_constraint' => [tabla, columna, ref_tabla, ref_columna, on_delete]
     * on_update es NO ACTION para todas (default MySQL).
     */
    public const FOREIGN_KEYS = [
        'alumno_encargado_alumno_id_foreign'      => ['alumno_encargado', 'alumno_id', 'alumnos', 'id', 'CASCADE'],
        'alumno_encargado_encargado_id_foreign'   => ['alumno_encargado', 'encargado_id', 'encargados', 'id', 'CASCADE'],
        'alumno_materia_alumno_id_foreign'        => ['alumno_materia', 'alumno_id', 'alumnos', 'id', 'CASCADE'],
        'alumno_materia_curso_id_foreign'         => ['alumno_materia', 'curso_id', 'cursos', 'id', 'CASCADE'],
        'alumno_materia_materia_id_foreign'       => ['alumno_materia', 'materia_id', 'materias', 'id', 'CASCADE'],
        'alumnos_user_id_foreign'                 => ['alumnos', 'user_id', 'users', 'id', 'SET NULL'],
        'asignaciones_docentes_asignado_por_foreign'       => ['asignaciones_docentes', 'asignado_por', 'users', 'id', 'SET NULL'],
        'asignaciones_docentes_detalle_curso_id_foreign'  => ['asignaciones_docentes', 'detalle_curso_id', 'detalle_cursos', 'id', 'RESTRICT'],
        'asignaciones_docentes_maestro_id_foreign'        => ['asignaciones_docentes', 'maestro_id', 'maestros', 'id', 'RESTRICT'],
        'asistencias_alumno_id_foreign'                   => ['asistencias', 'alumno_id', 'alumnos', 'id', 'RESTRICT'],
        'asistencias_curso_id_foreign'                    => ['asistencias', 'curso_id', 'cursos', 'id', 'RESTRICT'],
        'asistencias_detalle_curso_id_foreign'            => ['asistencias', 'detalle_curso_id', 'detalle_cursos', 'id', 'SET NULL'],
        'asistencias_registrado_por_foreign'              => ['asistencias', 'registrado_por', 'maestros', 'id', 'RESTRICT'],
        'detalle_cursos_curso_id_foreign'                 => ['detalle_cursos', 'curso_id', 'cursos', 'id', 'RESTRICT'],
        'detalle_cursos_materia_id_foreign'               => ['detalle_cursos', 'materia_id', 'materias', 'id', 'RESTRICT'],
        'detalle_cursos_maestro_id_foreign'               => ['detalle_cursos', 'maestro_id', 'maestros', 'id', 'RESTRICT'],
        'directores_user_id_foreign'                      => ['directores', 'user_id', 'users', 'id', 'SET NULL'],
        'inscripciones_alumno_id_foreign'                 => ['inscripciones', 'alumno_id', 'alumnos', 'id', 'RESTRICT'],
        'inscripciones_curso_id_foreign'                  => ['inscripciones', 'curso_id', 'cursos', 'id', 'RESTRICT'],
        'maestros_user_id_foreign'                        => ['maestros', 'user_id', 'users', 'id', 'RESTRICT'],
        'mensualidades_alumno_id_foreign'                 => ['mensualidades', 'alumno_id', 'alumnos', 'id', 'RESTRICT'],
        'mensualidades_curso_id_foreign'                  => ['mensualidades', 'curso_id', 'cursos', 'id', 'SET NULL'],
        'notas_alumno_id_foreign'                         => ['notas', 'alumno_id', 'alumnos', 'id', 'RESTRICT'],
        'notas_detalle_curso_id_foreign'                  => ['notas', 'detalle_curso_id', 'detalle_cursos', 'id', 'RESTRICT'],
        'notas_registrado_por_foreign'                    => ['notas', 'registrado_por', 'maestros', 'id', 'RESTRICT'],
        'pagos_mensualidad_id_foreign'                    => ['pagos', 'mensualidad_id', 'mensualidades', 'id', 'RESTRICT'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Guarda de idempotencia: si ya no existen columnas BIGINT, nada que hacer.
        // Se omite bajo `migrate --pretend` (los SELECT no se ejecutan y devuelven []),
        // de modo que el SQL generado sigue siendo visible.
        if (! DB::connection()->pretending()) {
            $hasBigint = DB::selectOne(
                "SELECT 1 AS one FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'bigint' LIMIT 1"
            );
            if (! $hasBigint) {
                return;
            }
        }

        // 1) DROP de las 26 FK reales (antes de tocar tipos para evitar
        //    conflictos de tipos entre padre e hijo).
        foreach (self::FOREIGN_KEYS as $constraint => $def) {
            $table = $def[0];
            DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`");
        }

        // 2) Conversión explícita de cada una de las 49 columnas BIGINT.
        foreach (self::CONVERSIONS as $spec => $definition) {
            [$table, $column] = explode('.', $spec, 2);
            DB::statement("ALTER TABLE `$table` MODIFY COLUMN `$column` $definition");
        }

        // 3) Recreación de las 26 FK con las mismas reglas (ON UPDATE NO ACTION).
        foreach (self::FOREIGN_KEYS as $constraint => $def) {
            [$table, $column, $refTable, $refColumn, $onDelete] = $def;
            DB::statement(sprintf(
                "ALTER TABLE `$table` ADD CONSTRAINT `$constraint` FOREIGN KEY (`$column`) REFERENCES `$refTable` (`$refColumn`) ON DELETE %s ON UPDATE NO ACTION",
                $onDelete
            ));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // 1) DROP de las 26 FK.
        foreach (self::FOREIGN_KEYS as $constraint => $def) {
            $table = $def[0];
            DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`");
        }

        // 2) INT -> BIGINT (BIGINT UNSIGNED / BIGINT según origen).
        foreach (self::CONVERSIONS as $spec => $definition) {
            [$table, $column] = explode('.', $spec, 2);
            $bigintDef = preg_replace('/^INT/', 'BIGINT', $definition);
            DB::statement("ALTER TABLE `$table` MODIFY COLUMN `$column` $bigintDef");
        }

        // 3) Recreación de las 26 FK.
        foreach (self::FOREIGN_KEYS as $constraint => $def) {
            [$table, $column, $refTable, $refColumn, $onDelete] = $def;
            DB::statement(sprintf(
                "ALTER TABLE `$table` ADD CONSTRAINT `$constraint` FOREIGN KEY (`$column`) REFERENCES `$refTable` (`$refColumn`) ON DELETE %s ON UPDATE NO ACTION",
                $onDelete
            ));
        }
    }
};
