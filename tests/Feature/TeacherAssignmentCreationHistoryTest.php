<?php

namespace Tests\Feature;

use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAssignmentCreationHistoryTest extends TestCase
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

        $maestroUser = User::factory()->create(['role' => 'docente']);
        $maestro = Maestro::create([
            'user_id' => $maestroUser->id,
            'nombre' => 'Test',
            'apellido' => 'Maestro',
            'codigo' => 'MST80001',
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
            'codigo' => 'MAT80001',
            'descripcion' => 'Test',
            'activa' => true,
        ]);

        return [
            'admin' => $admin,
            'maestro' => $maestro,
            'curso' => $curso,
            'materia' => $materia,
        ];
    }

    public function test_creates_detalle_and_asignacion_on_first_assign(): void
    {
        $data = $this->crearDatosBase();

        $response = $this->actingAs($data['admin'])->post(
            route('admin.maestros.asignar', $data['maestro']->id),
            ['curso_id' => $data['curso']->id, 'materia_ids' => [$data['materia']->id]]
        );

        $response->assertRedirect(route('admin.maestros.show', $data['maestro']->id));
        $response->assertSessionHas('success', 'Programa asignado correctamente al maestro.');

        $detalle = DetalleCurso::where('curso_id', $data['curso']->id)
            ->where('materia_id', $data['materia']->id)
            ->first();

        $this->assertNotNull($detalle, 'No se creó el detalle_cursos.');
        $this->assertSame($data['maestro']->id, $detalle->maestro_id);

        $this->assertDatabaseHas('asignaciones_docentes', [
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $data['maestro']->id,
            'estado' => 'activa',
            'asignado_por' => $data['admin']->id,
        ]);
    }

    public function test_no_duplicate_on_repeat_assignment(): void
    {
        $data = $this->crearDatosBase();

        $this->actingAs($data['admin'])->post(
            route('admin.maestros.asignar', $data['maestro']->id),
            ['curso_id' => $data['curso']->id, 'materia_ids' => [$data['materia']->id]]
        );

        $response = $this->actingAs($data['admin'])->post(
            route('admin.maestros.asignar', $data['maestro']->id),
            ['curso_id' => $data['curso']->id, 'materia_ids' => [$data['materia']->id]]
        );

        $response->assertSessionHas('success', 'Todos los programas seleccionados ya estaban asignados a este maestro.');

        $this->assertSame(1, DetalleCurso::where('curso_id', $data['curso']->id)
            ->where('materia_id', $data['materia']->id)->count());

        $detalle = DetalleCurso::where('curso_id', $data['curso']->id)
            ->where('materia_id', $data['materia']->id)->first();

        $this->assertSame(1, AsignacionDocente::where('detalle_curso_id', $detalle->id)
            ->where('estado', 'activa')->count());
    }

    public function test_asigna_varias_materias_del_mismo_curso_en_un_solo_envio(): void
    {
        $data = $this->crearDatosBase();

        $materias = collect([
            Materia::create(['nombre' => 'Historia', 'codigo' => 'MAT80002', 'activa' => true]),
            Materia::create(['nombre' => 'Ciencia', 'codigo' => 'MAT80003', 'activa' => true]),
            Materia::create(['nombre' => 'Arte', 'codigo' => 'MAT80004', 'activa' => true]),
        ]);

        $ids = $materias->pluck('id')->all();

        $response = $this->actingAs($data['admin'])->post(
            route('admin.maestros.asignar', $data['maestro']->id),
            ['curso_id' => $data['curso']->id, 'materia_ids' => $ids]
        );

        $response->assertRedirect(route('admin.maestros.show', $data['maestro']->id));
        $response->assertSessionHas('success', '3 programas asignados correctamente al maestro.');

        $detalles = DetalleCurso::where('curso_id', $data['curso']->id)
            ->whereIn('materia_id', $ids)
            ->get();

        $this->assertCount(3, $detalles);

        foreach ($detalles as $detalle) {
            $this->assertSame($data['maestro']->id, $detalle->maestro_id);

            $this->assertDatabaseHas('asignaciones_docentes', [
                'detalle_curso_id' => $detalle->id,
                'maestro_id' => $data['maestro']->id,
                'estado' => 'activa',
            ]);
        }
    }

    public function test_reenvio_de_las_mismas_materias_no_duplica_nada(): void
    {
        $data = $this->crearDatosBase();

        $materias = collect([
            Materia::create(['nombre' => 'Historia', 'codigo' => 'MAT80002', 'activa' => true]),
            Materia::create(['nombre' => 'Ciencia', 'codigo' => 'MAT80003', 'activa' => true]),
        ]);

        $ids = $materias->pluck('id')->all();

        $this->actingAs($data['admin'])->post(
            route('admin.maestros.asignar', $data['maestro']->id),
            ['curso_id' => $data['curso']->id, 'materia_ids' => $ids]
        );

        $this->actingAs($data['admin'])->post(
            route('admin.maestros.asignar', $data['maestro']->id),
            ['curso_id' => $data['curso']->id, 'materia_ids' => $ids]
        );

        $this->assertSame(2, DetalleCurso::count());
        $this->assertSame(2, AsignacionDocente::where('estado', 'activa')->count());
    }

    public function test_error_assigning_same_program_to_another_maestro(): void
    {
        $data = $this->crearDatosBase();

        $this->actingAs($data['admin'])->post(
            route('admin.maestros.asignar', $data['maestro']->id),
            ['curso_id' => $data['curso']->id, 'materia_ids' => [$data['materia']->id]]
        );

        $otroMaestroUser = User::factory()->create(['role' => 'docente']);
        $otroMaestro = Maestro::create([
            'user_id' => $otroMaestroUser->id,
            'nombre' => 'Otro',
            'apellido' => 'Maestro',
            'codigo' => 'MST80002',
            'activo' => true,
        ]);

        $response = $this->actingAs($data['admin'])->post(
            route('admin.maestros.asignar', $otroMaestro->id),
            ['curso_id' => $data['curso']->id, 'materia_ids' => [$data['materia']->id]]
        );

        $response->assertSessionHasErrors('materia_ids');

        $detalle = DetalleCurso::where('curso_id', $data['curso']->id)
            ->where('materia_id', $data['materia']->id)->first();

        $this->assertNotNull($detalle);
        $this->assertSame($data['maestro']->id, $detalle->maestro_id, 'No se debe cambiar maestro_id.');
        $this->assertSame(1, DetalleCurso::count());
        $this->assertSame(1, AsignacionDocente::count());
    }

    public function test_no_permite_asignar_curso_inactivo(): void
    {
        $data = $this->crearDatosBase();
        $data['curso']->update(['activo' => false]);

        $response = $this->actingAs($data['admin'])->post(
            route('admin.maestros.asignar', $data['maestro']->id),
            ['curso_id' => $data['curso']->id, 'materia_ids' => [$data['materia']->id]]
        );

        $response->assertSessionHasErrors('curso_id');
        $this->assertSame(0, DetalleCurso::count());
        $this->assertSame(0, AsignacionDocente::count());
    }

    public function test_no_permite_asignar_materia_inactiva(): void
    {
        $data = $this->crearDatosBase();
        $data['materia']->update(['activa' => false]);

        $response = $this->actingAs($data['admin'])->post(
            route('admin.maestros.asignar', $data['maestro']->id),
            ['curso_id' => $data['curso']->id, 'materia_ids' => [$data['materia']->id]]
        );

        $response->assertSessionHasErrors('materia_ids.*');
        $this->assertSame(0, DetalleCurso::count());
        $this->assertSame(0, AsignacionDocente::count());
    }

    public function test_no_permite_asignar_maestro_inactivo(): void
    {
        $data = $this->crearDatosBase();
        $data['maestro']->update(['activo' => false]);

        $response = $this->actingAs($data['admin'])->post(
            route('admin.maestros.asignar', $data['maestro']->id),
            ['curso_id' => $data['curso']->id, 'materia_ids' => [$data['materia']->id]]
        );

        $response->assertNotFound();
        $this->assertSame(0, DetalleCurso::count());
        $this->assertSame(0, AsignacionDocente::count());
    }

    public function test_active_assignment_with_different_maestro_than_detalle_rejected(): void
    {
        $data = $this->crearDatosBase();

        $detalle = DetalleCurso::create([
            'curso_id' => $data['curso']->id,
            'materia_id' => $data['materia']->id,
            'maestro_id' => $data['maestro']->id,
        ]);

        $otroMaestroUser = User::factory()->create(['role' => 'docente']);
        $otroMaestro = Maestro::create([
            'user_id' => $otroMaestroUser->id,
            'nombre' => 'Otro',
            'apellido' => 'Maestro',
            'codigo' => 'MST80002',
            'activo' => true,
        ]);

        AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $otroMaestro->id,
            'estado' => 'activa',
        ]);

        $response = $this->actingAs($data['admin'])->post(
            route('admin.maestros.asignar', $data['maestro']->id),
            ['curso_id' => $data['curso']->id, 'materia_ids' => [$data['materia']->id]]
        );

        $response->assertSessionHasErrors('materia_ids');
        $this->assertSame(1, DetalleCurso::count());
        $this->assertSame(1, AsignacionDocente::count());
    }

    public function test_two_active_assignments_for_same_detalle_rejected(): void
    {
        $data = $this->crearDatosBase();

        $detalle = DetalleCurso::create([
            'curso_id' => $data['curso']->id,
            'materia_id' => $data['materia']->id,
            'maestro_id' => $data['maestro']->id,
        ]);

        AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $data['maestro']->id,
            'estado' => 'activa',
        ]);

        AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $data['maestro']->id,
            'estado' => 'activa',
        ]);

        $response = $this->actingAs($data['admin'])->post(
            route('admin.maestros.asignar', $data['maestro']->id),
            ['curso_id' => $data['curso']->id, 'materia_ids' => [$data['materia']->id]]
        );

        $response->assertSessionHasErrors('materia_ids');
        $this->assertSame(1, DetalleCurso::count());
        $this->assertSame(2, AsignacionDocente::count());
    }
}
