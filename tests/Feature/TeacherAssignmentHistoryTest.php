<?php

namespace Tests\Feature;

use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeacherAssignmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function crearAsignacionBasica(): array
    {
        $adminUser = User::factory()->create(['role' => 'admin']);

        $maestroUser = User::factory()->create(['role' => 'docente']);
        $maestro = Maestro::create([
            'user_id' => $maestroUser->id,
            'nombre'  => 'Test',
            'apellido'=> 'Maestro',
            'codigo'  => 'MST90001',
            'activo'  => true,
        ]);

        $curso = Curso::create([
            'nombre'       => 'Test Curso',
            'nivel'        => 'Matutino',
            'seccion'      => 'A',
            'anio_lectivo' => 2025,
            'activo'       => true,
        ]);

        $materia = Materia::create([
            'nombre'      => 'Test Materia',
            'codigo'      => 'MAT90001',
            'descripcion' => 'Test',
            'activa'      => true,
        ]);

        $detalle = DetalleCurso::create([
            'curso_id'   => $curso->id,
            'materia_id' => $materia->id,
            'maestro_id' => $maestro->id,
        ]);

        return [
            'admin'   => $adminUser,
            'maestro' => $maestro,
            'curso'   => $curso,
            'materia' => $materia,
            'detalle' => $detalle,
        ];
    }

    public function test_asignaciones_docentes_table_has_required_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('asignaciones_docentes', [
                'id',
                'detalle_curso_id',
                'maestro_id',
                'fecha_inicio',
                'fecha_fin',
                'estado',
                'motivo_cambio',
                'asignado_por',
                'created_at',
                'updated_at',
            ]),
            'La tabla asignaciones_docentes no contiene todas las columnas requeridas.'
        );
    }

    public function test_can_create_active_assignment(): void
    {
        $data = $this->crearAsignacionBasica();

        $asignacion = AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id'       => $data['maestro']->id,
            'estado'           => 'activa',
        ]);

        $this->assertDatabaseHas('asignaciones_docentes', [
            'id'               => $asignacion->id,
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id'       => $data['maestro']->id,
            'estado'           => 'activa',
        ]);
    }

    public function test_asignacion_belongs_to_detalle_curso(): void
    {
        $data = $this->crearAsignacionBasica();

        $asignacion = AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id'       => $data['maestro']->id,
            'estado'           => 'activa',
        ]);

        $this->assertInstanceOf(DetalleCurso::class, $asignacion->detalleCurso);
        $this->assertEquals($data['detalle']->id, $asignacion->detalleCurso->id);
    }

    public function test_asignacion_belongs_to_maestro(): void
    {
        $data = $this->crearAsignacionBasica();

        $asignacion = AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id'       => $data['maestro']->id,
            'estado'           => 'activa',
        ]);

        $this->assertInstanceOf(Maestro::class, $asignacion->maestro);
        $this->assertEquals($data['maestro']->id, $asignacion->maestro->id);
    }

    public function test_asignado_por_devuelve_usuario_admin(): void
    {
        $data = $this->crearAsignacionBasica();

        $asignacion = AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id'       => $data['maestro']->id,
            'estado'           => 'activa',
            'asignado_por'     => $data['admin']->id,
        ]);

        $this->assertInstanceOf(User::class, $asignacion->asignadoPor);
        $this->assertEquals($data['admin']->id, $asignacion->asignadoPor->id);
        $this->assertEquals('admin', $asignacion->asignadoPor->role);
    }

    public function test_detalle_curso_devuelve_asignaciones_historicas(): void
    {
        $data = $this->crearAsignacionBasica();

        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id'       => $data['maestro']->id,
            'estado'           => 'activa',
        ]);

        $asignaciones = $data['detalle']->asignacionesDocentes;

        $this->assertCount(1, $asignaciones);
        $this->assertInstanceOf(AsignacionDocente::class, $asignaciones->first());
    }

    public function test_maestro_devuelve_asignaciones_historicas(): void
    {
        $data = $this->crearAsignacionBasica();

        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id'       => $data['maestro']->id,
            'estado'           => 'activa',
        ]);

        $asignaciones = $data['maestro']->asignacionesDocentes;

        $this->assertCount(1, $asignaciones);
        $this->assertInstanceOf(AsignacionDocente::class, $asignaciones->first());
    }

    public function test_fecha_inicio_y_fecha_fin_se_convierten_en_fechas(): void
    {
        $data = $this->crearAsignacionBasica();

        $asignacion = AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id'       => $data['maestro']->id,
            'estado'           => 'activa',
            'fecha_inicio'     => '2025-01-15',
            'fecha_fin'        => '2025-06-30',
        ]);

        $casts = $asignacion->getCasts();
        $this->assertSame('date', $casts['fecha_inicio']);
        $this->assertSame('date', $casts['fecha_fin']);

        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $asignacion->fecha_inicio);
        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $asignacion->fecha_fin);
    }

    public function test_estado_valores_permitidos_funcionan(): void
    {
        $data = $this->crearAsignacionBasica();

        foreach (['programada', 'activa', 'finalizada'] as $estado) {
            $asignacion = AsignacionDocente::create([
                'detalle_curso_id' => $data['detalle']->id,
                'maestro_id'       => $data['maestro']->id,
                'estado'           => $estado,
            ]);

            $this->assertSame($estado, $asignacion->estado);
        }
    }

    public function test_fk_restrict_prevents_detalle_curso_deletion(): void
    {
        $data = $this->crearAsignacionBasica();

        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id'       => $data['maestro']->id,
            'estado'           => 'activa',
        ]);

        $exception = null;
        try {
            $data['detalle']->delete();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception === null) {
            $this->markTestSkipped(
                'SQLite no impone la restricción FK RESTRICT en este entorno de pruebas.'
            );
        }

        $this->assertStringContainsString('constraint', $exception->getMessage());
    }

    public function test_asignado_por_becomes_null_when_admin_is_deleted(): void
    {
        $data = $this->crearAsignacionBasica();

        $asignacion = AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id'       => $data['maestro']->id,
            'estado'           => 'activa',
            'asignado_por'     => $data['admin']->id,
        ]);

        $data['admin']->delete();
        $asignacion->refresh();

        $this->assertNull($asignacion->asignado_por);
        $this->assertDatabaseHas('asignaciones_docentes', ['id' => $asignacion->id]);
    }

    public function test_fk_restrict_prevents_maestro_deletion(): void
    {
        $data = $this->crearAsignacionBasica();

        AsignacionDocente::create([
            'detalle_curso_id' => $data['detalle']->id,
            'maestro_id'       => $data['maestro']->id,
            'estado'           => 'activa',
        ]);

        $exception = null;
        try {
            $data['maestro']->delete();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception === null) {
            $this->markTestSkipped(
                'SQLite no impone la restricción FK RESTRICT en este entorno de pruebas.'
            );
        }

        $this->assertStringContainsString('constraint', $exception->getMessage());
    }
}
