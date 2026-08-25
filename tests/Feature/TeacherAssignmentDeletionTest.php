<?php

namespace Tests\Feature;

use App\Models\Alumno;
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
}
