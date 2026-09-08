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

        AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $maestro->id,
            'estado' => 'activa',
            'asignado_por' => $admin->id,
        ]);

        return [
            'admin' => $admin,
            'maestro' => $maestro,
            'curso' => $curso,
            'materia' => $materia,
            'detalle' => $detalle,
        ];
    }

    public function test_desasignar_finaliza_asignacion_y_nullifica_maestro(): void
    {
        $data = $this->crearAsignacionBasica();

        $response = $this
            ->actingAs($data['admin'])
            ->delete(route('admin.maestros.desasignar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]));

        $response->assertRedirect(route('admin.maestros.show', $data['maestro']->id));
        $response->assertSessionHas('success', 'Asignación finalizada correctamente. El maestro fue desasignado del programa.');

        // El DetalleCurso sigue existiendo
        $this->assertDatabaseHas('detalle_cursos', ['id' => $data['detalle']->id]);

        // maestro_id queda null
        $this->assertDatabaseHas('detalle_cursos', [
            'id' => $data['detalle']->id,
            'maestro_id' => null,
        ]);

        // La asignación activa fue finalizada (no eliminada)
        $this->assertDatabaseHas('asignaciones_docentes', [
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $data['maestro']->id,
            'estado' => 'finalizada',
        ]);

        // No queda ninguna asignación activa
        $this->assertDatabaseMissing('asignaciones_docentes', [
            'detalle_curso_id' => $data['detalle']->id,
            'estado' => 'activa',
        ]);
    }

    public function test_desasignar_preserva_notas_existentes(): void
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
        $response->assertSessionHas('success');

        // El DetalleCurso sigue existiendo
        $this->assertDatabaseHas('detalle_cursos', ['id' => $data['detalle']->id]);

        // Las notas existentes permanecen
        $this->assertDatabaseHas('notas', ['id' => $nota->id]);

        // La asignación fue finalizada, no eliminada
        $this->assertDatabaseHas('asignaciones_docentes', [
            'detalle_curso_id' => $data['detalle']->id,
            'estado' => 'finalizada',
        ]);
    }

    public function test_desasignar_preserva_asistencias_existentes(): void
    {
        $data = $this->crearAsignacionBasica();

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
            'fecha' => '2025-09-01',
            'estado' => 'presente',
            'registrado_por' => $data['maestro']->id,
        ]);

        $response = $this
            ->actingAs($data['admin'])
            ->delete(route('admin.maestros.desasignar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]));

        $response->assertRedirect(route('admin.maestros.show', $data['maestro']->id));
        $response->assertSessionHas('success');

        // Las asistencias existentes permanecen
        $this->assertDatabaseHas('asistencias', ['id' => $asistencia->id]);

        // El DetalleCurso sigue existiendo
        $this->assertDatabaseHas('detalle_cursos', ['id' => $data['detalle']->id]);
    }

    public function test_desasignar_preserva_historial_finalizado(): void
    {
        $data = $this->crearAsignacionBasica();

        // Una asignación finalizada del historial
        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id' => $data['maestro']->id,
            'estado' => 'finalizada',
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2025-06-30',
            'motivo_cambio' => 'Cambio anterior',
        ]);

        $response = $this
            ->actingAs($data['admin'])
            ->delete(route('admin.maestros.desasignar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]));

        $response->assertRedirect(route('admin.maestros.show', $data['maestro']->id));
        $response->assertSessionHas('success');

        // El DetalleCurso sigue existiendo
        $this->assertDatabaseHas('detalle_cursos', ['id' => $data['detalle']->id]);

        // La asignación histórica finalizada sigue existiendo
        $this->assertDatabaseHas('asignaciones_docentes', [
            'detalle_curso_id' => $data['detalle']->id,
            'estado' => 'finalizada',
            'motivo_cambio' => 'Cambio anterior',
        ]);

        // La asignación histórica conserva su fecha_fin original
        $historial = AsignacionDocente::where('detalle_curso_id', $data['detalle']->id)
            ->where('estado', 'finalizada')
            ->where('motivo_cambio', 'Cambio anterior')
            ->first();
        $this->assertNotNull($historial);
        $this->assertEquals('2025-06-30', $historial->fecha_fin->format('Y-m-d'));

        // No queda ninguna asignación activa
        $this->assertDatabaseMissing('asignaciones_docentes', [
            'detalle_curso_id' => $data['detalle']->id,
            'estado' => 'activa',
        ]);

        // maestro_id queda null
        $this->assertDatabaseHas('detalle_cursos', [
            'id' => $data['detalle']->id,
            'maestro_id' => null,
        ]);
    }

    public function test_desasignar_sin_asignacion_activa_falla(): void
    {
        $data = $this->crearAsignacionBasica();

        // Finalizar la asignación activa manualmente
        AsignacionDocente::where('detalle_curso_id', $data['detalle']->id)
            ->where('estado', 'activa')
            ->update([
                'estado' => 'finalizada',
                'fecha_fin' => today(),
            ]);

        $response = $this
            ->actingAs($data['admin'])
            ->delete(route('admin.maestros.desasignar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]));

        $response->assertRedirect(route('admin.maestros.show', $data['maestro']->id));
        $response->assertSessionHas('error');

        // El DetalleCurso y la asignación no cambian
        $this->assertDatabaseHas('detalle_cursos', ['id' => $data['detalle']->id]);
        $this->assertDatabaseHas('detalle_cursos', [
            'id' => $data['detalle']->id,
            'maestro_id' => $data['maestro']->id,
        ]);
    }

    public function test_desasignar_con_multiples_activas_falla(): void
    {
        $data = $this->crearAsignacionBasica();

        // Crear una segunda asignación activa (simula estado inconsistente)
        AsignacionDocente::create([
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
        $response->assertSessionHas('error', 'Este programa tiene múltiples asignaciones activas. Contacte al administrador.');

        // La asignación activa original sigue activa
        $this->assertDatabaseHas('asignaciones_docentes', [
            'detalle_curso_id' => $data['detalle']->id,
            'estado' => 'activa',
        ]);
    }

    public function test_segunda_desasignacion_simultanea_falla(): void
    {
        $data = $this->crearAsignacionBasica();

        // Primera desasignación: éxito
        $this->actingAs($data['admin'])
            ->delete(route('admin.maestros.desasignar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]));

        // maestro_id fue nullificado por la primera desasignación
        $this->assertDatabaseHas('detalle_cursos', [
            'id' => $data['detalle']->id,
            'maestro_id' => null,
        ]);

        // Segunda desasignación simultánea: falla porque ya no hay
        // una fila DetalleCurso que coincida con maestro_id
        $response = $this
            ->actingAs($data['admin'])
            ->delete(route('admin.maestros.desasignar', [
                'maestro_id' => $data['maestro']->id,
                'detalle_id' => $data['detalle']->id,
            ]));

        $response->assertNotFound();

        // La asignación finalizada de la primera desasignación sigue intacta
        $this->assertDatabaseHas('asignaciones_docentes', [
            'detalle_curso_id' => $data['detalle']->id,
            'estado' => 'finalizada',
        ]);
    }

    public function test_no_puede_desasignar_asignacion_de_otro_maestro(): void
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
