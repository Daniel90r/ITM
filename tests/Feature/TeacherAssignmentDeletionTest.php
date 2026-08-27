<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAssignmentDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function crearAsignacionBasica(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $maestroUser = User::factory()->create(['role' => 'docente']);
        $maestro = Maestro::create([
            'user_id' => $maestroUser->id,
            'nombre' => 'Test',
            'apellido' => 'Maestro',
            'codigo' => 'MST00001',
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
            'maestro_id' => $maestro->id,
        ]);

        return [
            'admin' => $admin,
            'maestro' => $maestro,
            'curso' => $curso,
            'materia' => $materia,
            'detalle' => $detalle,
        ];
    }

    public function test_admin_puede_eliminar_asignacion_sin_notas(): void
    {
        $data = $this->crearAsignacionBasica();

        $response = $this
            ->actingAs($data['admin'])
            ->delete(route('admin.maestros.desasignar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]));

        $response->assertRedirect(route('admin.maestros.show', $data['maestro']->id));
        $response->assertSessionHas('success', 'Asignación eliminada correctamente.');
        $this->assertDatabaseMissing('detalle_cursos', ['id' => $data['detalle']->id]);
    }

    public function test_asignacion_con_notas_no_puede_eliminarse(): void
    {
        $data = $this->crearAsignacionBasica();

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
            'registrado_por' => $data['maestro']->id,
        ]);

        $asignacion = AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $data['maestro']->id,
            'estado' => 'activa',
        ]);

        $response = $this
            ->actingAs($data['admin'])
            ->delete(route('admin.maestros.desasignar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]));

        $response->assertRedirect(route('admin.maestros.show', $data['maestro']->id));
        $response->assertSessionHas('error', 'No se puede eliminar la asignación porque contiene notas registradas.');
        $this->assertDatabaseHas('detalle_cursos', ['id' => $data['detalle']->id]);
        $this->assertDatabaseHas('notas', ['id' => $nota->id]);
        $this->assertDatabaseHas('asignaciones_docentes', ['id' => $asignacion->id]);
    }

    public function test_sin_notas_con_asignacion_activa_elimina_asignacion_y_detalle(): void
    {
        $data = $this->crearAsignacionBasica();

        $asignacion = AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $data['maestro']->id,
            'estado' => 'activa',
        ]);

        $response = $this
            ->actingAs($data['admin'])
            ->delete(route('admin.maestros.desasignar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]));

        $response->assertRedirect(route('admin.maestros.show', $data['maestro']->id));
        $response->assertSessionHas('success', 'Asignación eliminada correctamente.');
        $this->assertDatabaseMissing('asignaciones_docentes', ['detalle_curso_id' => $data['detalle']->id]);
        $this->assertDatabaseMissing('detalle_cursos', ['id' => $data['detalle']->id]);
    }

    public function test_con_historial_finalizado_no_se_elimina(): void
    {
        $data = $this->crearAsignacionBasica();

        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $data['maestro']->id,
            'estado' => 'activa',
        ]);

        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $data['maestro']->id,
            'estado' => 'finalizada',
            'fecha_fin' => '2025-06-30',
        ]);

        $response = $this
            ->actingAs($data['admin'])
            ->delete(route('admin.maestros.desasignar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]));

        $response->assertRedirect(route('admin.maestros.show', $data['maestro']->id));
        $response->assertSessionHas('error', 'No se puede eliminar el programa porque contiene historial de asignaciones docentes.');
        $this->assertDatabaseHas('asignaciones_docentes', ['detalle_curso_id' => $data['detalle']->id]);
        $this->assertDatabaseHas('detalle_cursos', ['id' => $data['detalle']->id]);
    }

    public function test_no_puede_eliminarse_asignacion_de_otro_maestro(): void
    {
        $data = $this->crearAsignacionBasica();

        $otroUser = User::factory()->create(['role' => 'docente']);
        $otroMaestro = Maestro::create([
            'user_id' => $otroUser->id,
            'nombre' => 'Otro',
            'apellido' => 'Maestro',
            'codigo' => 'MST00002',
            'activo' => true,
        ]);

        $response = $this
            ->actingAs($data['admin'])
            ->delete(route('admin.maestros.desasignar', [
                'maestro_id' => $otroMaestro->id,
                'detalle_id' => $data['detalle']->id,
            ]));

        $response->assertNotFound();
        $this->assertDatabaseHas('detalle_cursos', ['id' => $data['detalle']->id]);
    }

    public function test_desasignar_rechaza_asignacion_cuyo_maestro_no_coincide(): void
    {
        $data = $this->crearAsignacionBasica();

        $otroMaestroUser = User::factory()->create(['role' => 'docente']);
        $otroMaestro = Maestro::create([
            'user_id' => $otroMaestroUser->id,
            'nombre' => 'Otro',
            'apellido' => 'Maestro',
            'codigo' => 'MST00002',
            'activo' => true,
        ]);

        $asignacion = AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $otroMaestro->id,
            'estado' => 'activa',
        ]);

        $response = $this
            ->actingAs($data['admin'])
            ->delete(route('admin.maestros.desasignar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]));

        $response->assertRedirect(route('admin.maestros.show', $data['maestro']->id));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('asignaciones_docentes', ['id' => $asignacion->id]);
        $this->assertDatabaseHas('detalle_cursos', ['id' => $data['detalle']->id]);
    }
}
