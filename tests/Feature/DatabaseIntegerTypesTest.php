<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionObject;
use Tests\TestCase;

class DatabaseIntegerTypesTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_10_08_000000_convert_all_bigint_columns_to_int.php';

    private const EXPECTED_COLUMNS = [
        'alumno_encargado.id',
        'alumno_encargado.alumno_id',
        'alumno_encargado.encargado_id',
        'alumno_materia.id',
        'alumno_materia.alumno_id',
        'alumno_materia.curso_id',
        'alumno_materia.materia_id',
        'alumnos.id',
        'alumnos.user_id',
        'asignaciones_docentes.id',
        'asignaciones_docentes.detalle_curso_id',
        'asignaciones_docentes.maestro_id',
        'asignaciones_docentes.asignado_por',
        'asistencias.id',
        'asistencias.curso_id',
        'asistencias.alumno_id',
        'asistencias.registrado_por',
        'asistencias.detalle_curso_id',
        'boletas.id',
        'cache.expiration',
        'cache_locks.expiration',
        'cursos.id',
        'detalle_cursos.id',
        'detalle_cursos.curso_id',
        'detalle_cursos.materia_id',
        'detalle_cursos.maestro_id',
        'detalles_boletas.id',
        'directores.id',
        'directores.user_id',
        'encargados.id',
        'failed_jobs.id',
        'inscripciones.id',
        'inscripciones.alumno_id',
        'inscripciones.curso_id',
        'jobs.id',
        'maestros.id',
        'maestros.user_id',
        'materias.id',
        'mensualidades.id',
        'mensualidades.alumno_id',
        'mensualidades.curso_id',
        'notas.id',
        'notas.alumno_id',
        'notas.detalle_curso_id',
        'notas.registrado_por',
        'pagos.id',
        'pagos.mensualidad_id',
        'sessions.user_id',
        'users.id',
    ];

    private const EXPECTED_NULLABLE = [
        'alumnos.user_id',
        'asignaciones_docentes.asignado_por',
        'asistencias.detalle_curso_id',
        'detalle_cursos.maestro_id',
        'directores.user_id',
        'mensualidades.curso_id',
        'sessions.user_id',
    ];

    private const EXPECTED_SIGNED = [
        'cache.expiration',
        'cache_locks.expiration',
    ];

    private const EXPECTED_FK = [
        'alumno_encargado_alumno_id_foreign',
        'alumno_encargado_encargado_id_foreign',
        'alumno_materia_alumno_id_foreign',
        'alumno_materia_curso_id_foreign',
        'alumno_materia_materia_id_foreign',
        'alumnos_user_id_foreign',
        'asignaciones_docentes_asignado_por_foreign',
        'asignaciones_docentes_detalle_curso_id_foreign',
        'asignaciones_docentes_maestro_id_foreign',
        'asistencias_alumno_id_foreign',
        'asistencias_curso_id_foreign',
        'asistencias_detalle_curso_id_foreign',
        'asistencias_registrado_por_foreign',
        'detalle_cursos_curso_id_foreign',
        'detalle_cursos_materia_id_foreign',
        'detalle_cursos_maestro_id_foreign',
        'directores_user_id_foreign',
        'inscripciones_alumno_id_foreign',
        'inscripciones_curso_id_foreign',
        'maestros_user_id_foreign',
        'mensualidades_alumno_id_foreign',
        'mensualidades_curso_id_foreign',
        'notas_alumno_id_foreign',
        'notas_detalle_curso_id_foreign',
        'notas_registrado_por_foreign',
        'pagos_mensualidad_id_foreign',
    ];

    private const EXPECTED_DELETE_RULES = [
        'alumno_encargado_alumno_id_foreign'        => 'CASCADE',
        'alumno_encargado_encargado_id_foreign'     => 'CASCADE',
        'alumno_materia_alumno_id_foreign'          => 'CASCADE',
        'alumno_materia_curso_id_foreign'           => 'CASCADE',
        'alumno_materia_materia_id_foreign'         => 'CASCADE',
        'alumnos_user_id_foreign'                   => 'SET NULL',
        'asignaciones_docentes_asignado_por_foreign'=> 'SET NULL',
        'asignaciones_docentes_detalle_curso_id_foreign' => 'RESTRICT',
        'asignaciones_docentes_maestro_id_foreign'  => 'RESTRICT',
        'asistencias_alumno_id_foreign'             => 'RESTRICT',
        'asistencias_curso_id_foreign'              => 'RESTRICT',
        'asistencias_detalle_curso_id_foreign'      => 'SET NULL',
        'asistencias_registrado_por_foreign'        => 'RESTRICT',
        'detalle_cursos_curso_id_foreign'           => 'RESTRICT',
        'detalle_cursos_materia_id_foreign'         => 'RESTRICT',
        'detalle_cursos_maestro_id_foreign'         => 'RESTRICT',
        'directores_user_id_foreign'                => 'SET NULL',
        'inscripciones_alumno_id_foreign'           => 'RESTRICT',
        'inscripciones_curso_id_foreign'            => 'RESTRICT',
        'maestros_user_id_foreign'                  => 'RESTRICT',
        'mensualidades_alumno_id_foreign'           => 'RESTRICT',
        'mensualidades_curso_id_foreign'            => 'SET NULL',
        'notas_alumno_id_foreign'                   => 'RESTRICT',
        'notas_detalle_curso_id_foreign'            => 'RESTRICT',
        'notas_registrado_por_foreign'              => 'RESTRICT',
        'pagos_mensualidad_id_foreign'              => 'RESTRICT',
    ];

    private function migrationInstance(): object
    {
        return require database_path('migrations/'.self::MIGRATION);
    }

    private function migrationSource(): string
    {
        return file_get_contents(database_path('migrations/'.self::MIGRATION));
    }

    public function test_migration_file_exists(): void
    {
        $this->assertFileExists(database_path('migrations/'.self::MIGRATION));
    }

    public function test_conversion_map_covers_exactly_49_columns(): void
    {
        $conv = (new ReflectionObject($this->migrationInstance()))->getConstant('CONVERSIONS');

        $this->assertCount(49, $conv, 'Debe cubrir exactamente 49 columnas BIGINT.');
        $this->assertSame(
            self::EXPECTED_COLUMNS,
            array_keys($conv),
            'Las 49 columnas deben coincidir exactamente con el inventario de auditoría.'
        );
        $this->assertSame(
            count(self::EXPECTED_COLUMNS),
            count(array_unique(self::EXPECTED_COLUMNS)),
            'No se permiten duplicados en la lista de columnas esperadas.'
        );
    }

    public function test_signed_and_unsigned_targets_are_correct(): void
    {
        $conv = (new ReflectionObject($this->migrationInstance()))->getConstant('CONVERSIONS');

        foreach (self::EXPECTED_SIGNED as $spec) {
            $this->assertStringNotContainsString(
                'UNSIGNED',
                $conv[$spec],
                "{$spec} debe ser INT SIGNED (no UNSIGNED)."
            );
        }

        foreach (array_diff(self::EXPECTED_COLUMNS, self::EXPECTED_SIGNED) as $spec) {
            $this->assertStringContainsString(
                'UNSIGNED',
                $conv[$spec],
                "{$spec} debe ser INT UNSIGNED."
            );
        }
    }

    public function test_primary_keys_keep_auto_increment(): void
    {
        $conv = (new ReflectionObject($this->migrationInstance()))->getConstant('CONVERSIONS');

        foreach (self::EXPECTED_COLUMNS as $spec) {
            if (str_ends_with($spec, '.id')) {
                $this->assertStringContainsString(
                    'AUTO_INCREMENT',
                    $conv[$spec],
                    "{$spec} (PK) debe conservar AUTO_INCREMENT."
                );
            }
        }
    }

    public function test_nullable_columns_are_preserved(): void
    {
        $conv = (new ReflectionObject($this->migrationInstance()))->getConstant('CONVERSIONS');

        foreach (self::EXPECTED_NULLABLE as $spec) {
            $this->assertStringNotContainsString(
                'NOT NULL',
                $conv[$spec],
                "{$spec} debe preservar nullable."
            );
        }

        foreach (array_diff(self::EXPECTED_COLUMNS, self::EXPECTED_NULLABLE) as $spec) {
            $this->assertStringContainsString(
                'NOT NULL',
                $conv[$spec],
                "{$spec} no debe ser nullable."
            );
        }
    }

    public function test_foreign_keys_cover_all_26_constraints(): void
    {
        $fks = (new ReflectionObject($this->migrationInstance()))->getConstant('FOREIGN_KEYS');

        $this->assertCount(26, $fks, 'Deben declararse exactamente 26 FK.');
        $this->assertSame(self::EXPECTED_FK, array_keys($fks));
    }

    public function test_foreign_key_delete_rules_match_audit(): void
    {
        $fks = (new ReflectionObject($this->migrationInstance()))->getConstant('FOREIGN_KEYS');

        foreach (self::EXPECTED_DELETE_RULES as $constraint => $rule) {
            $this->assertArrayHasKey($constraint, $fks);
            $this->assertSame($rule, $fks[$constraint][4], "Regla ON DELETE de {$constraint}.");
        }
    }

    public function test_must_have_mysql_driver_guard(): void
    {
        $source = $this->migrationSource();

        $this->assertStringContainsString('DB::getDriverName()', $source);
        $this->assertStringContainsString('!== \'mysql\'', $source);
    }

    public function test_does_not_execute_alter_under_sqlite(): void
    {
        DB::connection()->enableQueryLog();

        $migration = $this->migrationInstance();
        $migration->up();
        $migration->down();

        $alter = collect(DB::getQueryLog())
            ->filter(fn (array $q) => str_contains($q['query'], 'ALTER TABLE'));

        $this->assertEmpty($alter, 'La migración NO debe emitir ALTER sobre SQLite.');

        DB::connection()->disableQueryLog();
    }

    public function test_does_not_reference_project_models_or_services(): void
    {
        $source = $this->migrationSource();

        $this->assertStringNotContainsString('App\\Models\\', $source);
        $this->assertStringNotContainsString('App\\Services\\', $source);
    }
}
