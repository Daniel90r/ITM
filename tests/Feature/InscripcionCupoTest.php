<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\Maestro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InscripcionCupoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $alumno;
    private User $docente;
    private Curso $curso;
    private Materia $ingles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->alumno = User::factory()->create(['role' => 'alumno']);
        $this->docente = User::factory()->create(['role' => 'docente']);

        $this->curso = Curso::factory()->create([
            'activo' => true,
            'anio_lectivo' => date('Y'),
        ]);

        $this->ingles = Materia::factory()->create([
            'nombre' => 'Inglés',
            'codigo' => 'ING-101',
            'activa' => true,
        ]);
    }

    #[Test]
    public function no_se_puede_aprobar_inscripcion_si_curso_ya_tiene_20_aprobadas(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $alumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
            Inscripcion::factory()->create([
                'alumno_id' => $alumno->id,
                'curso_id' => $this->curso->id,
                'estado' => 'aprobada',
            ]);
        }

        $nuevoAlumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
        $solicitud = Inscripcion::factory()->create([
            'alumno_id' => $nuevoAlumno->id,
            'curso_id' => $this->curso->id,
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.solicitudes.aprobar', $solicitud->id));

        $response->assertSessionHasErrors('curso');
        $this->assertDatabaseHas('inscripciones', [
            'id' => $solicitud->id,
            'estado' => 'pendiente',
        ]);
    }

    #[Test]
    public function pendientes_no_ocupan_cupo(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $alumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
            Inscripcion::factory()->create([
                'alumno_id' => $alumno->id,
                'curso_id' => $this->curso->id,
                'estado' => 'pendiente',
            ]);
        }

        $nuevoAlumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
        $solicitud = Inscripcion::factory()->create([
            'alumno_id' => $nuevoAlumno->id,
            'curso_id' => $this->curso->id,
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.solicitudes.aprobar', $solicitud->id));

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('inscripciones', [
            'id' => $solicitud->id,
            'estado' => 'aprobada',
        ]);
    }

    #[Test]
    public function rechazadas_no_ocupan_cupo(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $alumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
            Inscripcion::factory()->create([
                'alumno_id' => $alumno->id,
                'curso_id' => $this->curso->id,
                'estado' => 'rechazada',
            ]);
        }

        $nuevoAlumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
        $solicitud = Inscripcion::factory()->create([
            'alumno_id' => $nuevoAlumno->id,
            'curso_id' => $this->curso->id,
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.solicitudes.aprobar', $solicitud->id));

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('inscripciones', [
            'id' => $solicitud->id,
            'estado' => 'aprobada',
        ]);
    }

    #[Test]
    public function con_19_aprobadas_se_puede_aprobar_una_mas(): void
    {
        for ($i = 0; $i < 19; $i++) {
            $alumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
            Inscripcion::factory()->create([
                'alumno_id' => $alumno->id,
                'curso_id' => $this->curso->id,
                'estado' => 'aprobada',
            ]);
        }

        $nuevoAlumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
        $solicitud = Inscripcion::factory()->create([
            'alumno_id' => $nuevoAlumno->id,
            'curso_id' => $this->curso->id,
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.solicitudes.aprobar', $solicitud->id));

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('inscripciones', [
            'id' => $solicitud->id,
            'estado' => 'aprobada',
        ]);
    }

    #[Test]
    public function con_20_aprobadas_se_rechaza_la_numero_21(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $alumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
            Inscripcion::factory()->create([
                'alumno_id' => $alumno->id,
                'curso_id' => $this->curso->id,
                'estado' => 'aprobada',
            ]);
        }

        $nuevoAlumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
        $solicitud = Inscripcion::factory()->create([
            'alumno_id' => $nuevoAlumno->id,
            'curso_id' => $this->curso->id,
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.solicitudes.aprobar', $solicitud->id));

        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('inscripciones', [
            'id' => $solicitud->id,
            'estado' => 'pendiente',
        ]);
    }

    #[Test]
    public function una_inscripcion_aprobada_no_se_duplica(): void
    {
        $alumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
        $solicitud = Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $this->curso->id,
            'estado' => 'pendiente',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.solicitudes.aprobar', $solicitud->id));

        $this->assertDatabaseCount('inscripciones', 1);
        $this->assertDatabaseHas('inscripciones', [
            'id' => $solicitud->id,
            'estado' => 'aprobada',
        ]);
    }

    #[Test]
    public function tomara_ingles_se_asigna_unicamente_al_aprobar(): void
    {
        $alumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
        $solicitud = Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $this->curso->id,
            'estado' => 'pendiente',
            'tomara_ingles' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.solicitudes.aprobar', $solicitud->id));

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('alumno_materia', [
            'alumno_id' => $alumno->id,
            'curso_id' => $this->curso->id,
            'materia_id' => $this->ingles->id,
        ]);
    }

    #[Test]
    public function si_solicitud_es_rechazada_ingles_no_se_asigna(): void
    {
        $alumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
        $solicitud = Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $this->curso->id,
            'estado' => 'pendiente',
            'tomara_ingles' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.solicitudes.rechazar', $solicitud->id), [
                'motivo_rechazo' => 'No cumple requisitos',
            ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('alumno_materia', [
            'alumno_id' => $alumno->id,
            'curso_id' => $this->curso->id,
            'materia_id' => $this->ingles->id,
        ]);
    }

    #[Test]
    public function si_ingles_no_existe_o_esta_inactivo_aprobacion_falla(): void
    {
        $this->ingles->update(['activa' => false]);

        $alumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
        $solicitud = Inscripcion::factory()->create([
            'alumno_id' => $alumno->id,
            'curso_id' => $this->curso->id,
            'estado' => 'pendiente',
            'tomara_ingles' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.solicitudes.aprobar', $solicitud->id));

        $response->assertSessionHasErrors('tomara_ingles');
        $this->assertDatabaseHas('inscripciones', [
            'id' => $solicitud->id,
            'estado' => 'pendiente',
        ]);
    }

    #[Test]
    public function alumno_no_puede_acceder_a_rutas_administrativas(): void
    {
        $response = $this->actingAs($this->alumno)
            ->get(route('admin.cursos'));

        $response->assertForbidden();
    }

    #[Test]
    public function docente_no_puede_acceder_a_rutas_administrativas(): void
    {
        $response = $this->actingAs($this->docente)
            ->get(route('admin.cursos'));

        $response->assertForbidden();
    }

    #[Test]
    public function administrador_puede_acceder_a_rutas_administrativas(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cursos'));

        $response->assertOk();
    }

    #[Test]
    public function curso_nuevo_incluye_todos_los_programas_activos(): void
    {
        Materia::factory()->count(3)->create(['activa' => true]);
        Materia::factory()->create(['activa' => false]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cursos.store'), [
                'nombre' => 'Curso Test',
                'seccion' => 'A',
                'nivel' => 'Matutino',
                'anio_lectivo' => date('Y'),
                'fecha_inicio' => now()->format('Y-m-d'),
                'fecha_fin' => now()->addMonths(6)->format('Y-m-d'),
                'activo' => true,
            ]);

        $curso = Curso::latest()->first();
        $materiasActivas = Materia::where('activa', true)->count();

        $this->assertDatabaseCount('detalle_cursos', $materiasActivas);
        $this->assertDatabaseMissing('detalle_cursos', [
            'curso_id' => $curso->id,
            'materia_id' => Materia::where('activa', false)->first()->id,
        ]);
    }
}
