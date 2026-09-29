<?php

namespace Tests\Feature;

use App\Http\Controllers\Docente\AsistenciaController;
use App\Http\Controllers\Docente\Concerns\ResuelveMateriasDelDocente;
use App\Http\Controllers\Docente\NotasController;
use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\Maestro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Asistencia y Notas deben resolver SIEMPRE los mismos alumnos.
 *
 * Si las dos pantallas usaran reglas distintas, un docente podría calificar a
 * un alumno al que no le permite registrar asistencia (o al revés). Este test
 * es la red que detecta esa divergencia y que alguien vuelva a copiar los
 * helpers dentro de un controlador.
 */
class ResolucionCompartidaDocenteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Maestro $maestro;

    private Curso $curso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->user = User::factory()->create(['role' => 'docente']);
        $this->maestro = Maestro::create([
            'user_id' => $this->user->id,
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'codigo' => 'CM001',
            'activo' => true,
        ]);

        $this->curso = Curso::create([
            'nombre' => 'Curso A',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => 2026,
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => now()->addYear()->toDateString(),
            'activo' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    private function materia(string $nombre, string $codigo, bool $opcional = false): Materia
    {
        return Materia::create([
            'nombre' => $nombre,
            'codigo' => $codigo,
            'activa' => true,
            'es_opcional' => $opcional,
        ]);
    }

    private function detalle(Materia $materia): DetalleCurso
    {
        return DetalleCurso::create([
            'curso_id' => $this->curso->id,
            'materia_id' => $materia->id,
        ]);
    }

    private function asignar(DetalleCurso $detalle, ?Maestro $maestro = null, string $estado = 'activa'): AsignacionDocente
    {
        return AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => ($maestro ?? $this->maestro)->id,
            'estado' => $estado,
            'fecha_inicio' => now()->subMonth()->toDateString(),
        ]);
    }

    private function alumnoInscrito(string $nombre = 'Alumno'): Alumno
    {
        $alumno = Alumno::factory()->create([
            'nombre' => $nombre,
            'apellido' => 'Prueba',
        ]);

        Inscripcion::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => true,
            'estado' => 'aprobada',
        ]);

        return $alumno;
    }

    private function anotarEnOpcional(Alumno $alumno, Materia $materia): void
    {
        DB::table('alumno_materia')->insert([
            'alumno_id' => $alumno->id,
            'curso_id' => $this->curso->id,
            'materia_id' => $materia->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function otroMaestro(): Maestro
    {
        $user = User::factory()->create(['role' => 'docente']);

        return Maestro::create([
            'user_id' => $user->id,
            'nombre' => 'Ana',
            'apellido' => 'Lopez',
            'codigo' => 'CM002',
            'activo' => true,
        ]);
    }

    /** IDs de alumnos que resuelve un controlador dado. */
    private function alumnosResueltosPor(object $controlador, DetalleCurso $detalle): array
    {
        $metodo = new ReflectionMethod($controlador, 'alumnosDeLaMateria');
        $metodo->setAccessible(true);

        return $metodo->invoke($controlador, $detalle)->pluck('id')->sort()->values()->all();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. La lógica es literalmente la misma en ambas pantallas
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_ambos_controladores_usan_el_mismo_trait_y_no_declaran_sus_propias_copias(): void
    {
        $archivoTrait = (new ReflectionClass(ResuelveMateriasDelDocente::class))->getFileName();

        foreach ([AsistenciaController::class, NotasController::class] as $clase) {
            $this->assertContains(
                ResuelveMateriasDelDocente::class,
                class_uses($clase),
                "{$clase} debe usar el trait compartido."
            );

            foreach (['maestroAutenticado', 'detallesAsignados', 'alumnosDeLaMateria'] as $metodo) {
                $this->assertSame(
                    $archivoTrait,
                    (new ReflectionMethod($clase, $metodo))->getFileName(),
                    "{$clase} no debe volver a declarar {$metodo}(): debe venir del trait compartido."
                );
            }
        }
    }

    #[Test]
    public function test_materia_normal_asistencia_y_notas_resuelven_los_mismos_alumnos(): void
    {
        $materia = $this->materia('Microsoft Word', 'WRD');
        $this->asignar($this->detalle($materia));

        $ana = $this->alumnoInscrito('Ana');
        $beto = $this->alumnoInscrito('Beto');

        $asistencia = $this->alumnosResueltosPor(new AsistenciaController, $this->detalleDe($materia));
        $notas      = $this->alumnosResueltosPor(new NotasController, $this->detalleDe($materia));

        $this->assertEquals([$ana->id, $beto->id], $asistencia);
        $this->assertEquals($asistencia, $notas);
    }

    #[Test]
    public function test_materia_opcional_asistencia_y_notas_filtran_por_alumno_materia(): void
    {
        $opcional = $this->materia('Inglés', 'ING', true);
        $this->asignar($this->detalle($opcional));

        $marcos = $this->alumnoInscrito('Marcos');
        $mario  = $this->alumnoInscrito('Mario');

        $this->anotarEnOpcional($marcos, $opcional);

        $detalle = $this->detalleDe($opcional);

        $asistencia = $this->alumnosResueltosPor(new AsistenciaController, $detalle);
        $notas      = $this->alumnosResueltosPor(new NotasController, $detalle);

        $this->assertEquals([$marcos->id], $asistencia, 'Solo el alumno anotado en la opcional.');
        $this->assertEquals($asistencia, $notas);
        $this->assertNotContains($mario->id, $asistencia);
    }

    #[Test]
    public function test_una_segunda_materia_opcional_no_requiere_cambios_de_codigo(): void
    {
        // Nada de nombres ni ids hardcodeados: cualquier es_opcional filtra igual.
        $ingles      = $this->materia('Inglés', 'ING', true);
        $computacion = $this->materia('Computación Avanzada', 'CPA', true);

        $this->asignar($this->detalle($ingles));
        $this->asignar($this->detalle($computacion));

        $marcos = $this->alumnoInscrito('Marcos');
        $mario  = $this->alumnoInscrito('Mario');

        $this->anotarEnOpcional($marcos, $ingles);
        $this->anotarEnOpcional($mario, $computacion);

        $this->assertEquals(
            [$marcos->id],
            $this->alumnosResueltosPor(new AsistenciaController, $this->detalleDe($ingles))
        );
        $this->assertEquals(
            [$mario->id],
            $this->alumnosResueltosPor(new NotasController, $this->detalleDe($computacion))
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. La autorización sigue idéntica en las dos pantallas
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_ambos_rechazan_alumno_ajeno_a_la_materia(): void
    {
        // Materia OPCIONAL: el alumnoAjeno está inscrito en el curso pero no en
        // la materia, así que no eslegible para asistencia ni para notas.
        $opcional = $this->materia('Inglés', 'ING', true);
        $detalle = $this->detalle($opcional);
        $this->asignar($detalle);

        $legitimo = $this->alumnoInscrito('Ana');
        $alumnoAjeno = $this->alumnoInscrito('Ajeno');
        $this->anotarEnOpcional($legitimo, $opcional);

        $this->actingAs($this->user)->post(route('docente.asistencia.guardar'), [
            'detalle_curso_id' => $detalle->id,
            'fecha' => '2026-09-27',
            'asistencias' => [
                $legitimo->id    => ['estado' => 'presente'],
                $alumnoAjeno->id => ['estado' => 'presente'],
            ],
        ])->assertForbidden();

        $this->actingAs($this->user)->post(route('docente.notas.guardar'), [
            'detalle_curso_id' => $detalle->id,
            'notas' => [
                $legitimo->id    => ['laboratorio' => 8],
                $alumnoAjeno->id => ['laboratorio' => 9],
            ],
        ])->assertForbidden();

        $this->assertDatabaseCount('asistencias', 0);
        $this->assertDatabaseCount('notas', 0);
    }

    #[Test]
    public function test_ambos_rechazan_alumno_no_inscrito_en_el_curso(): void
    {
        $materia = $this->materia('Microsoft Word', 'WRD');
        $detalle = $this->detalle($materia);
        $this->asignar($detalle);

        $legitimo = $this->alumnoInscrito('Ana');
        $ajeno    = Alumno::factory()->create(['nombre' => 'Ajeno', 'apellido' => 'Prueba']);

        $this->actingAs($this->user)->post(route('docente.asistencia.guardar'), [
            'detalle_curso_id' => $detalle->id,
            'fecha' => '2026-09-27',
            'asistencias' => [
                $legitimo->id => ['estado' => 'presente'],
                $ajeno->id    => ['estado' => 'presente'],
            ],
        ])->assertForbidden();

        $this->actingAs($this->user)->post(route('docente.notas.guardar'), [
            'detalle_curso_id' => $detalle->id,
            'notas' => [
                $legitimo->id => ['laboratorio' => 8],
                $ajeno->id    => ['laboratorio' => 9],
            ],
        ])->assertForbidden();

        $this->assertDatabaseCount('asistencias', 0);
        $this->assertDatabaseCount('notas', 0);
    }

    #[Test]
    public function test_ambos_rechazan_una_asignacion_finalizada(): void
    {
        $materia = $this->materia('Microsoft Word', 'WRD');
        $detalle = $this->detalle($materia);
        $this->asignar($detalle, null, 'finalizada');

        $alumno = $this->alumnoInscrito('Ana');

        $this->actingAs($this->user)->post(route('docente.asistencia.guardar'), [
            'detalle_curso_id' => $detalle->id,
            'fecha' => '2026-09-27',
            'asistencias' => [$alumno->id => ['estado' => 'presente']],
        ])->assertForbidden();

        $this->actingAs($this->user)->post(route('docente.notas.guardar'), [
            'detalle_curso_id' => $detalle->id,
            'notas' => [$alumno->id => ['laboratorio' => 8]],
        ])->assertForbidden();

        $this->assertDatabaseCount('asistencias', 0);
        $this->assertDatabaseCount('notas', 0);
    }

    #[Test]
    public function test_ambos_rechazan_una_materia_asignada_a_otro_maestro(): void
    {
        $materia = $this->materia('Microsoft Word', 'WRD');
        $detalle = $this->detalle($materia);
        $this->asignar($detalle, $this->otroMaestro());

        $alumno = $this->alumnoInscrito('Ana');

        $this->actingAs($this->user)->post(route('docente.asistencia.guardar'), [
            'detalle_curso_id' => $detalle->id,
            'fecha' => '2026-09-27',
            'asistencias' => [$alumno->id => ['estado' => 'presente']],
        ])->assertForbidden();

        $this->actingAs($this->user)->post(route('docente.notas.guardar'), [
            'detalle_curso_id' => $detalle->id,
            'notas' => [$alumno->id => ['laboratorio' => 8]],
        ])->assertForbidden();

        $this->assertDatabaseCount('asistencias', 0);
        $this->assertDatabaseCount('notas', 0);
    }

    #[Test]
    public function test_ambos_ven_exactamente_las_mismas_materias_asignadas(): void
    {
        $word  = $this->materia('Microsoft Word', 'WRD');
        $excel = $this->materia('Microsoft Excel', 'EXC');
        $foto  = $this->materia('Photoshop', 'PSD');

        $this->asignar($this->detalle($word));
        $this->asignar($this->detalle($excel));
        $this->asignar($this->detalle($foto), $this->otroMaestro());

        $visibles = fn (object $controlador) => (new ReflectionMethod($controlador, 'detallesAsignados'))
            ->invoke($controlador, $this->maestro)
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertNotEmpty($visibles(new AsistenciaController));
        $this->assertEquals(
            $visibles(new AsistenciaController),
            $visibles(new NotasController)
        );
    }

    /** Detalle ya creado para esa materia (el helper detalle() crea uno nuevo). */
    private function detalleDe(Materia $materia): DetalleCurso
    {
        return DetalleCurso::where('curso_id', $this->curso->id)
            ->where('materia_id', $materia->id)
            ->firstOrFail();
    }
}
