<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Materia;
use App\Models\Maestro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * La migración de unicidad de asistencia por materia debe ser reversible SIN
 * perder historial académico.
 *
 * El esquema nuevo permite dos materias del mismo curso con asistencia del
 * mismo alumno en la misma fecha. El esquema viejo no. Revertir exige decidir
 * qué hacer con esos datos, y ese script se niega a borrarlos por su cuenta.
 *
 * Nota sobre motores: PHPUnit corre en SQLite :memory:. Las pruebas affirmation
 * sobre datos (agrupar y detectar duplicados) se comportan igual en MySQL y en
 * SQLite, así que son portables. No se afirma nada sobre la semántica de NULL
 * en índices únicos, que es específica de MySQL y se documenta en la
 * migración en lugar de probarse.
 */
class AsistenciaUnicaMateriaMigracionTest extends TestCase
{
    use RefreshDatabase;

    private const INDICE_ANTERIOR = 'asistencias_curso_id_alumno_id_fecha_unique';

    private const INDICE_NUEVO = 'asistencias_detalle_curso_alumno_fecha_unique';

    private function migracion(): object
    {
        return require database_path('migrations/2026_09_27_000100_asistencia_unica_por_detalle_curso.php');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    private function curso(string $nombre = 'Curso A'): Curso
    {
        return Curso::create([
            'nombre' => $nombre,
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => 2026,
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => now()->addYear()->toDateString(),
            'activo' => true,
        ]);
    }

    private function materia(string $nombre, string $codigo): Materia
    {
        return Materia::create(['nombre' => $nombre, 'codigo' => $codigo, 'activa' => true]);
    }

    private function detalle(Curso $curso, Materia $materia): DetalleCurso
    {
        return DetalleCurso::create(['curso_id' => $curso->id, 'materia_id' => $materia->id]);
    }

    private function alumno(string $nombre = 'Marcos'): Alumno
    {
        return Alumno::factory()->create(['nombre' => $nombre, 'apellido' => 'Prueba']);
    }

    /** registrado_por referencia a `maestros`, no a `users`. */
    private function maestro(): Maestro
    {
        return Maestro::create([
            'user_id' => User::factory()->create(['role' => 'docente'])->id,
            'nombre' => 'Docente',
            'apellido' => 'Prueba',
            'codigo' => 'MG' . random_int(100, 999),
            'activo' => true,
        ]);
    }

    private function asistencia(
        Curso $curso,
        Alumno $alumno,
        ?DetalleCurso $detalle,
        string $fecha
    ): Asistencia {
        return Asistencia::create([
            'curso_id' => $curso->id,
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $detalle?->id,
            'fecha' => $fecha,
            'estado' => 'presente',
            'registrado_por' => $this->maestro()->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. Tabla sin registros: up y down funcionan
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_up_y_down_funcionan_sin_registros(): void
    {
        $migracion = $this->migracion();

        // RefreshDatabase ya dejó aplicado el estado "nuevo".
        $this->assertTrue(Schema::hasIndex('asistencias', self::INDICE_NUEVO));

        $migracion->down();
        $this->assertTrue(Schema::hasIndex('asistencias', self::INDICE_ANTERIOR));
        $this->assertFalse(Schema::hasIndex('asistencias', self::INDICE_NUEVO));

        $migracion->up();
        $this->assertTrue(Schema::hasIndex('asistencias', self::INDICE_NUEVO));
        $this->assertFalse(Schema::hasIndex('asistencias', self::INDICE_ANTERIOR));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Registros compatibles: down funciona
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_down_funciona_con_registros_compatibles(): void
    {
        $curso = $this->curso();
        $word  = $this->materia('Microsoft Word', 'WRD');
        $marcos = $this->alumno();

        $this->asistencia($curso, $marcos, $this->detalle($curso, $word), '2026-09-27');

        $this->migracion()->down();

        $this->assertTrue(Schema::hasIndex('asistencias', self::INDICE_ANTERIOR));
        $this->assertDatabaseCount('asistencias', 1);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. Varias materias el mismo día: el esquema nuevo lo permite
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_up_permite_varias_materias_del_mismo_alumno_el_mismo_dia(): void
    {
        $curso  = $this->curso();
        $word   = $this->materia('Microsoft Word', 'WRD');
        $excel  = $this->materia('Microsoft Excel', 'EXC');
        $marcos = $this->alumno();

        $this->migracion()->down();

        $detWord  = $this->detalle($curso, $word);
        $detExcel = $this->detalle($curso, $excel);

        // Con el índice viejo, la segunda fila del mismo día es imposible.
        $this->asistencia($curso, $marcos, $detWord, '2026-09-27');

        try {
            $this->asistencia($curso, $marcos, $detExcel, '2026-09-27');
            $this->fail('El esquema anterior no debería permitir dos materias el mismo día.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('UNIQUE', strtoupper($e->getMessage()));
        }

        $this->migracion()->up();

        // Con el esquema por materia, ambas caben.
        $this->asistencia($curso, $marcos, $detExcel, '2026-09-27');

        $this->assertDatabaseCount('asistencias', 2);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4 y 5. Datos incompatibles: down se detiene y NO pierde filas
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_down_se_detiene_ante_datos_incompatibles_y_no_borra_nada(): void
    {
        $curso  = $this->curso();
        $word   = $this->materia('Microsoft Word', 'WRD');
        $excel  = $this->materia('Microsoft Excel', 'EXC');
        $marcos = $this->alumno();

        $this->asistencia($curso, $marcos, $this->detalle($curso, $word), '2026-09-27');
        $this->asistencia($curso, $marcos, $this->detalle($curso, $excel), '2026-09-27');

        $this->assertDatabaseCount('asistencias', 2);

        try {
            $this->migracion()->down();
            $this->fail('down() debió detenerse por incompatibilidad.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No se puede revertir', $e->getMessage());
            $this->assertStringContainsString('curso_id=' . $curso->id, $e->getMessage());
            $this->assertStringContainsString('alumno_id=' . $marcos->id, $e->getMessage());
        }

        // Los registros siguen intactos.
        $this->assertDatabaseCount('asistencias', 2);
        $this->assertEquals(2, Asistencia::distinct()->count('detalle_curso_id'));

        // Y ningún índice se movió: el esquema sigue siendo el de por materia.
        $this->assertTrue(Schema::hasIndex('asistencias', self::INDICE_NUEVO));
        $this->assertFalse(Schema::hasIndex('asistencias', self::INDICE_ANTERIOR));
    }

    #[Test]
    public function test_down_se_detiene_también_con_asistencias_sin_detalle_curso(): void
    {
        // detalle_curso_id es nullable y su FK es ON DELETE SET NULL, así que
        // estas filas son alcanzables. Duplicar (curso, alumno, fecha) con NULL
        // también es incompatible con el índice anterior.
        $curso  = $this->curso();
        $marcos = $this->alumno();

        foreach (range(1, 2) as $i) {
            $this->asistencia($curso, $marcos, null, '2026-09-27');
        }

        $this->expectException(\RuntimeException::class);

        try {
            $this->migracion()->down();
        } finally {
            $this->assertDatabaseCount('asistencias', 2);
            $this->assertTrue(Schema::hasIndex('asistencias', self::INDICE_NUEVO));
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // up() idempotente
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_up_es_idempotente(): void
    {
        $migracion = $this->migracion();

        $migracion->up();
        $migracion->up();

        $this->assertTrue(Schema::hasIndex('asistencias', self::INDICE_NUEVO));
        $this->assertFalse(Schema::hasIndex('asistencias', self::INDICE_ANTERIOR));
    }

    #[Test]
    public function test_down_es_idempotente_cuando_no_hay_conflictos(): void
    {
        $migracion = $this->migracion();

        $migracion->down();
        $migracion->down();

        $this->assertTrue(Schema::hasIndex('asistencias', self::INDICE_ANTERIOR));
    }
}
