<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TeacherAssignmentChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function crearDatosBase(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $oldMaestroUser = User::factory()->create(['role' => 'docente']);
        $oldMaestro = Maestro::create([
            'user_id' => $oldMaestroUser->id,
            'nombre' => 'Old',
            'apellido' => 'Maestro',
            'codigo' => 'MST00001',
            'activo' => true,
        ]);

        $nuevoMaestroUser = User::factory()->create(['role' => 'docente']);
        $nuevoMaestro = Maestro::create([
            'user_id' => $nuevoMaestroUser->id,
            'nombre' => 'New',
            'apellido' => 'Maestro',
            'codigo' => 'MST00002',
            'activo' => true,
        ]);

        $curso = Curso::create([
            'nombre' => 'Test Curso',
            'nivel' => 'Matutino',
            'seccion' => 'A',
            'anio_lectivo' => 2025,
            'activo' => true,
        ]);

        $materia = Materia::create([
            'nombre' => 'Test Materia',
            'codigo' => 'MAT00001',
            'descripcion' => 'Test',
            'activa' => true,
        ]);

        $detalle = DetalleCurso::create([
            'curso_id' => $curso->id,
            'materia_id' => $materia->id,
            'maestro_id' => $oldMaestro->id,
        ]);

        $asignacion = AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $oldMaestro->id,
            'fecha_inicio' => null,
            'fecha_fin' => null,
            'estado' => 'activa',
            'motivo_cambio' => null,
            'asignado_por' => $admin->id,
        ]);

        return [
            'admin' => $admin,
            'oldMaestro' => $oldMaestro,
            'nuevoMaestro' => $nuevoMaestro,
            'curso' => $curso,
            'materia' => $materia,
            'detalle' => $detalle,
            'asignacion' => $asignacion,
        ];
    }

    public function test_admin_cambia_correctamente_el_maestro(): void
    {
        $this->travelTo('2025-06-15');

        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Cambio por traslado de área.',
            ]
        );

        $response->assertRedirect(route('admin.maestros.show', $data['nuevoMaestro']->id));
        $response->assertSessionHas('success', 'El maestro del programa fue cambiado correctamente.');
    }

    public function test_asignacion_anterior_queda_finalizada(): void
    {
        $this->travelTo('2025-06-15');

        $data = $this->crearDatosBase();

        $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Cambio por traslado de área.',
            ]
        );

        $asignacionAnterior = AsignacionDocente::find($data['asignacion']->id);
        $this->assertEquals('finalizada', $asignacionAnterior->estado);
        $this->assertEquals('2025-06-14', $asignacionAnterior->fecha_fin->format('Y-m-d'));
        $this->assertEquals('Cambio por traslado de área.', $asignacionAnterior->motivo_cambio);
    }

    public function test_asignacion_anterior_no_se_elimina(): void
    {
        $this->travelTo('2025-06-15');

        $data = $this->crearDatosBase();

        $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Cambio por traslado de área.',
            ]
        );

        $this->assertSame(
            2,
            AsignacionDocente::where('detalle_curso_id', $data['detalle']->id)->count()
        );
        $this->assertDatabaseHas('asignaciones_docentes', [
            'id' => $data['asignacion']->id,
            'estado' => 'finalizada',
        ]);
    }

    public function test_nueva_asignacion_queda_activa_con_fecha_correcta(): void
    {
        $this->travelTo('2025-06-15');

        $data = $this->crearDatosBase();

        $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Cambio por traslado de área.',
            ]
        );

        $nuevaAsignacion = AsignacionDocente::where('detalle_curso_id', $data['detalle']->id)
            ->where('maestro_id', $data['nuevoMaestro']->id)
            ->where('estado', 'activa')
            ->first();

        $this->assertNotNull($nuevaAsignacion);
        $this->assertEquals('2025-06-15', $nuevaAsignacion->fecha_inicio->format('Y-m-d'));
        $this->assertNull($nuevaAsignacion->fecha_fin);
        $this->assertNull($nuevaAsignacion->motivo_cambio);
        $this->assertEquals($data['admin']->id, $nuevaAsignacion->asignado_por);

        $this->assertSame(
            1,
            AsignacionDocente::where('detalle_curso_id', $data['detalle']->id)
                ->where('estado', 'activa')
                ->count()
        );
    }

    public function test_detalle_cursos_maestro_id_queda_actualizado(): void
    {
        $this->travelTo('2025-06-15');

        $data = $this->crearDatosBase();

        $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Cambio por traslado de área.',
            ]
        );

        $detalle = DetalleCurso::find($data['detalle']->id);
        $this->assertEquals($data['nuevoMaestro']->id, $detalle->maestro_id);
    }

    public function test_cambio_al_mismo_maestro_es_rechazado(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['oldMaestro']->id,
                'motivo_cambio' => 'Motivo de cambio válido.',
            ]
        );

        $response->assertSessionHasErrors('nuevo_maestro_id');
        $detalle = DetalleCurso::find($data['detalle']->id);
        $this->assertEquals($data['oldMaestro']->id, $detalle->maestro_id);
    }

    public function test_nuevo_maestro_inactivo_es_rechazado(): void
    {
        $data = $this->crearDatosBase();
        $data['nuevoMaestro']->update(['activo' => false]);

        $response = $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Motivo de cambio válido.',
            ]
        );

        $response->assertSessionHasErrors('nuevo_maestro_id');
        $detalle = DetalleCurso::find($data['detalle']->id);
        $this->assertEquals($data['oldMaestro']->id, $detalle->maestro_id);
    }

    public function test_detalle_que_no_pertenece_al_maestro_devuelve_404(): void
    {
        $data = $this->crearDatosBase();

        $otroMaestroUser = User::factory()->create(['role' => 'docente']);
        $otroMaestro = Maestro::create([
            'user_id' => $otroMaestroUser->id,
            'nombre' => 'Otro',
            'apellido' => 'Maestro',
            'codigo' => 'MST00003',
            'activo' => true,
        ]);

        $response = $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $otroMaestro->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Motivo de cambio válido.',
            ]
        );

        $response->assertNotFound();
    }

    public function test_ausencia_de_asignacion_activa_es_rechazada(): void
    {
        $data = $this->crearDatosBase();
        $data['asignacion']->update(['estado' => 'finalizada']);

        $response = $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Motivo de cambio válido.',
            ]
        );

        $response->assertSessionHasErrors('nuevo_maestro_id');
        $detalle = DetalleCurso::find($data['detalle']->id);
        $this->assertEquals($data['oldMaestro']->id, $detalle->maestro_id);
    }

    public function test_dos_asignaciones_activas_son_rechazadas(): void
    {
        $data = $this->crearDatosBase();

        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $data['oldMaestro']->id,
            'fecha_inicio' => null,
            'fecha_fin' => null,
            'estado' => 'activa',
            'motivo_cambio' => null,
        ]);

        $response = $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Motivo de cambio válido.',
            ]
        );

        $response->assertSessionHasErrors('nuevo_maestro_id');
        $this->assertSame(
            2,
            AsignacionDocente::where('detalle_curso_id', $data['detalle']->id)
                ->where('estado', 'activa')
                ->count()
        );
    }

    public function test_asignacion_cuyo_maestro_no_coincide_con_detalle_es_rechazada(): void
    {
        $data = $this->crearDatosBase();

        $otroMaestroUser = User::factory()->create(['role' => 'docente']);
        $otroMaestro = Maestro::create([
            'user_id' => $otroMaestroUser->id,
            'nombre' => 'Otro',
            'apellido' => 'Maestro',
            'codigo' => 'MST00003',
            'activo' => true,
        ]);

        $data['asignacion']->update(['maestro_id' => $otroMaestro->id]);

        $response = $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Motivo de cambio válido.',
            ]
        );

        $response->assertSessionHasErrors('nuevo_maestro_id');
        $detalle = DetalleCurso::find($data['detalle']->id);
        $this->assertEquals($data['oldMaestro']->id, $detalle->maestro_id);
    }

    public function test_segundo_cambio_mismo_dia_es_rechazado(): void
    {
        $this->travelTo('2025-06-15');

        $data = $this->crearDatosBase();
        $data['asignacion']->update(['fecha_inicio' => today()]);

        $response = $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Motivo de cambio válido.',
            ]
        );

        $response->assertSessionHasErrors('nuevo_maestro_id');
        $detalle = DetalleCurso::find($data['detalle']->id);
        $this->assertEquals($data['oldMaestro']->id, $detalle->maestro_id);
    }

    public function test_guest_es_redirigido_al_login(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Motivo de cambio válido.',
            ]
        );

        $response->assertRedirect(route('login'));
        $detalle = DetalleCurso::find($data['detalle']->id);
        $this->assertEquals($data['oldMaestro']->id, $detalle->maestro_id);
    }

    public function test_docente_recibe_403(): void
    {
        $data = $this->crearDatosBase();
        $docente = User::factory()->create(['role' => 'docente']);

        $response = $this->actingAs($docente)->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Motivo de cambio válido.',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_alumno_recibe_403(): void
    {
        $data = $this->crearDatosBase();
        $alumno = User::factory()->create(['role' => 'alumno']);

        $response = $this->actingAs($alumno)->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Motivo de cambio válido.',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_ruta_cambiar_maestro_contiene_middleware_admin(): void
    {
        $route = Route::getRoutes()
            ->getByName('admin.maestros.asignaciones.cambiar');

        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('verified', $route->gatherMiddleware());
        $this->assertContains('role:admin', $route->gatherMiddleware());
    }

    public function test_notas_existentes_no_son_modificadas(): void
    {
        $data = $this->crearDatosBase();

        $alumno = Alumno::create([
            'nombre' => 'Test',
            'apellido' => 'Alumno',
            'codigo' => 'ALU00001',
            'fecha_nacimiento' => '2000-01-01',
            'genero' => 'M',
            'activo' => true,
        ]);

        $nota = Nota::create([
            'alumno_id' => $alumno->id,
            'detalle_curso_id' => $data['detalle']->id,
            'laboratorio' => 8,
            'examen_teorico' => 7,
            'practica' => 9,
            'sos' => 8,
            'registrado_por' => $data['oldMaestro']->id,
        ]);

        $promedioAntes = $nota->promedio;

        $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Motivo de cambio válido.',
            ]
        );

        $notaActualizada = Nota::find($nota->id);
        $this->assertEquals($nota->id, $notaActualizada->id);
        $this->assertEquals($nota->alumno_id, $notaActualizada->alumno_id);
        $this->assertEquals($nota->detalle_curso_id, $notaActualizada->detalle_curso_id);
        $this->assertEquals(8, $notaActualizada->laboratorio);
        $this->assertEquals(7, $notaActualizada->examen_teorico);
        $this->assertEquals(9, $notaActualizada->practica);
        $this->assertEquals(8, $notaActualizada->sos);
        $this->assertEquals($promedioAntes, $notaActualizada->promedio);
        $this->assertEquals($data['oldMaestro']->id, $notaActualizada->registrado_por);
    }

    public function test_asistencias_existentes_no_son_modificadas(): void
    {
        $data = $this->crearDatosBase();

        $alumno = Alumno::create([
            'nombre' => 'Test',
            'apellido' => 'Alumno',
            'codigo' => 'ALU00002',
            'fecha_nacimiento' => '2000-01-01',
            'genero' => 'M',
            'activo' => true,
        ]);

        $asistencia = Asistencia::create([
            'curso_id' => $data['curso']->id,
            'alumno_id' => $alumno->id,
            'fecha' => '2025-06-14',
            'estado' => 'presente',
            'observacion' => 'Observación de prueba',
            'registrado_por' => $data['oldMaestro']->id,
        ]);

        $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'Motivo de cambio válido.',
            ]
        );

        $asistenciaActualizada = Asistencia::find($asistencia->id);
        $this->assertEquals($asistencia->id, $asistenciaActualizada->id);
        $this->assertEquals('presente', $asistenciaActualizada->estado);
        $this->assertEquals('Observación de prueba', $asistenciaActualizada->observacion);
        $this->assertEquals($data['oldMaestro']->id, $asistenciaActualizada->registrado_por);
    }

    public function test_falla_de_validacion_no_modifica_registros(): void
    {
        $data = $this->crearDatosBase();

        $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['oldMaestro']->id,
                'motivo_cambio' => 'Motivo de cambio válido.',
            ]
        );

        $detalle = DetalleCurso::find($data['detalle']->id);
        $this->assertEquals($data['oldMaestro']->id, $detalle->maestro_id);

        $asignacion = AsignacionDocente::find($data['asignacion']->id);
        $this->assertEquals('activa', $asignacion->estado);
        $this->assertNull($asignacion->fecha_fin);
        $this->assertNull($asignacion->motivo_cambio);
    }

    public function test_motivo_menor_de_10_caracteres_es_rechazado(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => 'cambio',
            ]
        );

        $response->assertSessionHasErrors('motivo_cambio');
    }

    public function test_motivo_mayor_de_255_caracteres_es_rechazado(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])->put(
            route('admin.maestros.asignaciones.cambiar', [
                'maestro_id' => $data['oldMaestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]),
            [
                'nuevo_maestro_id' => $data['nuevoMaestro']->id,
                'motivo_cambio' => str_repeat('a', 256),
            ]
        );

        $response->assertSessionHasErrors('motivo_cambio');
    }
}
