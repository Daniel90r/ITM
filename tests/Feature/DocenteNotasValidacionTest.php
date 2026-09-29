<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\Maestro;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * El lote de notas debe validarse ANTES de tocarlo.
 *
 * Antes, guardar() hacía array_keys($request->notas) sin declarar la regla
 * `notas => required|array`: un POST sin el campo pasaba la validación y
 * terminaba en un TypeError (HTTP 500) en lugar de un error de validación.
 */
class DocenteNotasValidacionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Maestro $maestro;

    private Curso $curso;

    private DetalleCurso $detalle;

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
            'nombre' => 'Curso Notas',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => 2026,
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => now()->addYear()->toDateString(),
            'activo' => true,
        ]);

        $materia = Materia::create([
            'nombre' => 'Microsoft Word',
            'codigo' => 'WRD',
            'activa' => true,
        ]);

        $this->detalle = DetalleCurso::create([
            'curso_id' => $this->curso->id,
            'materia_id' => $materia->id,
        ]);

        AsignacionDocente::create([
            'detalle_curso_id' => $this->detalle->id,
            'maestro_id' => $this->maestro->id,
            'estado' => 'activa',
            'fecha_inicio' => now()->subMonth()->toDateString(),
        ]);
    }

    private function alumnoEnCurso(): Alumno
    {
        $alumno = Alumno::factory()->create();

        Inscripcion::create([
            'alumno_id' => $alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => true,
            'estado' => 'aprobada',
        ]);

        return $alumno;
    }

    #[Test]
    public function test_post_valido_con_notas_guarda_correctamente(): void
    {
        $alumno = $this->alumnoEnCurso();

        $response = $this->actingAs($this->user)
            ->post(route('docente.notas.guardar'), [
                'detalle_curso_id' => $this->detalle->id,
                'notas' => [
                    $alumno->id => [
                        'laboratorio' => 8,
                        'examen_teorico' => 9,
                        'practica' => 7,
                        'conducta' => 'B',
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('notas', [
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $this->detalle->id,
            'laboratorio' => 8,
        ]);
    }

    #[Test]
    public function test_post_sin_notas_devuelve_error_de_validacion_y_no_500(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('docente.notas.guardar'), [
                'detalle_curso_id' => $this->detalle->id,
            ]);

        $response->assertSessionHasErrors('notas');
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseCount('notas', 0);
    }

    #[Test]
    public function test_post_con_notas_como_string_devuelve_error_de_validacion(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('docente.notas.guardar'), [
                'detalle_curso_id' => $this->detalle->id,
                'notas' => 'no-es-un-array',
            ]);

        $response->assertSessionHasErrors('notas');
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseCount('notas', 0);
    }

    #[Test]
    public function test_post_sin_notas_no_persiste_alumnos_manipulados(): void
    {
        // El guardia de lote se evalúa DESPUÉS de la validación: si `notas`
        // llega con un alumno ajeno, se rechaza el lote completo.
        $alumno = $this->alumnoEnCurso();
        $ajeno = Alumno::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('docente.notas.guardar'), [
                'detalle_curso_id' => $this->detalle->id,
                'notas' => [
                    $alumno->id => ['laboratorio' => 8],
                    $ajeno->id   => ['laboratorio' => 10],
                ],
            ]);

        $response->assertForbidden();

        $this->assertSame(0, Nota::count());
    }
}
