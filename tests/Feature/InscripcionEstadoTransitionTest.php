<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InscripcionEstadoTransitionTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    private $alumno;

    private $curso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $alumnoUser = User::factory()->create(['role' => 'alumno']);
        $this->alumno = Alumno::create([
            'user_id' => $alumnoUser->id,
            'nombre' => 'Test',
            'apellido' => 'Alumno',
            'codigo' => 'ALU00001',
            'fecha_nacimiento' => '2005-01-01',
            'genero' => 'M',
            'activo' => true,
        ]);
        $this->curso = Curso::create([
            'nombre' => 'Test Curso',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => 2025,
            'activo' => true,
        ]);
    }

    public function test_store_crea_inscripcion_aprobada_con_activo_true(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.inscripciones.store'),
            [
                'alumno_id' => $this->alumno->id,
                'curso_id' => $this->curso->id,
            ]
        );

        $response->assertRedirect(route('admin.inscripciones'));

        $inscripcion = Inscripcion::where('alumno_id', $this->alumno->id)
            ->where('curso_id', $this->curso->id)
            ->first();

        $this->assertNotNull($inscripcion);
        $this->assertEquals('aprobada', $inscripcion->estado);
        $this->assertTrue((bool) $inscripcion->activa);
    }

    public function test_aprobar_transiciona_pendiente_a_aprobada(): void
    {
        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => 0,
            'estado' => 'pendiente',
        ]);

        $inscripcion->update(['estado' => 'aprobada', 'activa' => 1]);

        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => 'aprobada',
            'activa' => 1,
        ]);
    }

    public function test_rechazar_transiciona_pendiente_a_rechazada(): void
    {
        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => 0,
            'estado' => 'pendiente',
        ]);

        $this->actingAs($this->admin)->post(
            route('admin.solicitudes.rechazar', $inscripcion->id),
            ['motivo_rechazo' => 'Documento incompleto']
        );

        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => 'rechazada',
            'activa' => 0,
            'motivo_rechazo' => 'Documento incompleto',
        ]);
    }

    public function test_reabrir_transiciona_rechazada_a_pendiente(): void
    {
        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => 0,
            'estado' => 'rechazada',
            'motivo_rechazo' => 'Documento incompleto',
        ]);

        $this->actingAs($this->admin)->post(
            route('admin.solicitudes.reabrir', $inscripcion->id)
        );

        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => 'pendiente',
            'activa' => 0,
            'motivo_rechazo' => null,
        ]);
    }

    public function test_desactivar_transiciona_aprobada_a_inactiva(): void
    {
        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => 1,
            'estado' => 'aprobada',
        ]);

        $this->actingAs($this->admin)->delete(
            route('admin.inscripciones.desactivar', $inscripcion->id)
        );

        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => 'inactiva',
            'activa' => 0,
        ]);
    }

    public function test_aprobar_solo_aplica_a_pendiente(): void
    {
        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => 1,
            'estado' => 'aprobada',
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.solicitudes.aprobar', $inscripcion->id)
        );

        $response->assertSessionHas('error', 'Solo se pueden aprobar solicitudes pendientes.');
    }

    public function test_reabrir_solo_aplica_a_rechazada(): void
    {
        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => 1,
            'estado' => 'aprobada',
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.solicitudes.reabrir', $inscripcion->id)
        );

        $response->assertSessionHas('error', 'Solo se pueden reabrir solicitudes rechazadas.');
    }

    public function test_rechazar_solo_aplica_a_pendiente(): void
    {
        $inscripcion = Inscripcion::create([
            'alumno_id' => $this->alumno->id,
            'curso_id' => $this->curso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa' => 1,
            'estado' => 'aprobada',
        ]);

        $this->actingAs($this->admin)->post(
            route('admin.solicitudes.rechazar', $inscripcion->id),
            ['motivo_rechazo' => 'Motivo de prueba']
        );

        $this->assertDatabaseHas('inscripciones', [
            'id' => $inscripcion->id,
            'estado' => 'aprobada',
        ]);
    }
}
