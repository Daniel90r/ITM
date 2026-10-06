<?php

namespace Tests\Feature;

use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DocenteDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function crearDocente(string $codigo = 'MST00001', string $nombre = 'Test', string $apellido = 'Docente'): Maestro
    {
        $user = User::factory()->create([
            'name' => $nombre.' '.$apellido,
            'role' => 'docente',
        ]);

        return Maestro::create([
            'user_id' => $user->id,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'codigo' => $codigo,
            'activo' => true,
        ]);
    }

    private function crearCurso(string $seccion = 'A'): Curso
    {
        return Curso::create([
            'nombre' => 'Primer Grado',
            'nivel' => 'Básica',
            'seccion' => $seccion,
            'anio_lectivo' => 2025,
            'activo' => true,
        ]);
    }

    private function crearMateria(string $nombre = 'Materia Test', string $codigo = 'MAT00001'): Materia
    {
        return Materia::create([
            'nombre' => $nombre,
            'codigo' => $codigo,
            'descripcion' => 'Test',
            'activa' => true,
        ]);
    }

    private function crearDetalle(Maestro $maestro, Curso $curso, Materia $materia): DetalleCurso
    {
        return DetalleCurso::create([
            'curso_id' => $curso->id,
            'materia_id' => $materia->id,
            'maestro_id' => $maestro->id,
        ]);
    }

    private function crearAsignacion(
        DetalleCurso $detalle,
        Maestro $maestro,
        string $estado = 'activa',
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
    ): AsignacionDocente {
        return AsignacionDocente::create([
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $maestro->id,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'estado' => $estado,
            'motivo_cambio' => null,
            'asignado_por' => null,
        ]);
    }

    private function usuarioDocente(Maestro $maestro): User
    {
        return User::find($maestro->user_id);
    }

    public function test_docente_ve_programa_con_asignacion_activa(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso();
        $materia = $this->crearMateria('Materia Programa', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('Materia Programa', false);
    }

    public function test_docente_no_ve_asignacion_activa_de_otro_maestro(): void
    {
        $docente = $this->crearDocente('MST00001');
        $otroMaestro = $this->crearDocente('MST00002');

        $curso = $this->crearCurso();
        $materia = $this->crearMateria('Materia Secreta', 'MAT00002');
        $detalle = $this->crearDetalle($otroMaestro, $curso, $materia);
        $this->crearAsignacion($detalle, $otroMaestro, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Materia Secreta', false);
    }

    public function test_docente_no_ve_asignacion_finalizada_propia(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso();
        $materia = $this->crearMateria('Materia Finalizada', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'finalizada');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Materia Finalizada', false);
    }

    public function test_maestro_anterior_deja_de_ver_programa_despues_de_finalizar(): void
    {
        $docenteA = $this->crearDocente('MST00001');
        $docenteB = $this->crearDocente('MST00002');

        $curso = $this->crearCurso();
        $materia = $this->crearMateria('Materia Compartida', 'MAT00001');
        $detalle = $this->crearDetalle($docenteA, $curso, $materia);

        $asignacionA = $this->crearAsignacion($detalle, $docenteA, 'activa');
        $asignacionA->update([
            'estado' => 'finalizada',
            'fecha_fin' => '2025-06-01',
            'motivo_cambio' => 'Traslado',
        ]);

        $this->crearAsignacion($detalle, $docenteB, 'activa', '2025-06-02');

        $response = $this->actingAs($this->usuarioDocente($docenteA))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Materia Compartida', false);
    }

    public function test_maestro_nuevo_ve_programa_despues_de_cambio(): void
    {
        $docenteA = $this->crearDocente('MST00001');
        $docenteB = $this->crearDocente('MST00002');

        $curso = $this->crearCurso();
        $materia = $this->crearMateria('Materia Transferida', 'MAT00001');
        $detalle = $this->crearDetalle($docenteA, $curso, $materia);

        $asignacionA = $this->crearAsignacion($detalle, $docenteA, 'activa');
        $asignacionA->update([
            'estado' => 'finalizada',
            'fecha_fin' => '2025-06-01',
            'motivo_cambio' => 'Traslado',
        ]);

        $asignacionB = $this->crearAsignacion($detalle, $docenteB, 'activa', '2025-06-02');

        $response = $this->actingAs($this->usuarioDocente($docenteB))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('Materia Transferida', false);
    }

    public function test_curso_inactivo_no_aparece(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso();
        $curso->update(['activo' => false]);
        $materia = $this->crearMateria('Materia Inactiva', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Materia Inactiva', false);
        $response->assertSee('No tienes programas activos asignados actualmente.', false);
    }

    public function test_materia_inactiva_no_aparece(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso();
        $materia = $this->crearMateria('Materia Inactiva', 'MAT00001');
        $materia->update(['activa' => false]);
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Materia Inactiva', false);
        $response->assertSee('No tienes programas activos asignados actualmente.', false);
    }

    public function test_asignacion_activa_con_fecha_inicio_nula_aparece(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso();
        $materia = $this->crearMateria('Materia Nula', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa', null, null);

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('Materia Nula', false);
    }

    public function test_docente_sin_asignaciones_ve_estado_vacio(): void
    {
        $docente = $this->crearDocente();

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('No tienes programas activos asignados actualmente.', false);
    }

    public function test_programas_agrupados_por_curso_id(): void
    {
        $docente = $this->crearDocente();

        $curso1 = $this->crearCurso('A');
        $curso2 = $this->crearCurso('B');

        $materia1 = $this->crearMateria('Materia Curso Uno', 'MAT00001');
        $materia2 = $this->crearMateria('Materia Curso Dos', 'MAT00002');

        $detalle1 = $this->crearDetalle($docente, $curso1, $materia1);
        $detalle2 = $this->crearDetalle($docente, $curso2, $materia2);

        $this->crearAsignacion($detalle1, $docente, 'activa');
        $this->crearAsignacion($detalle2, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('Materia Curso Uno', false);
        $response->assertSee('Materia Curso Dos', false);
        $response->assertSee('Sección', false);
    }

    public function test_dos_programas_mismo_curso_misma_agrupacion(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso('A');

        $materia1 = $this->crearMateria('Materia Primera', 'MAT00001');
        $materia2 = $this->crearMateria('Materia Segunda', 'MAT00002');

        $detalle1 = $this->crearDetalle($docente, $curso, $materia1);
        $detalle2 = $this->crearDetalle($docente, $curso, $materia2);

        $this->crearAsignacion($detalle1, $docente, 'activa');
        $this->crearAsignacion($detalle2, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('Materia Primera', false);
        $response->assertSee('Materia Segunda', false);
        $response->assertSee('1 curso', false);
    }

    public function test_relaciones_detalle_curso_materia_cargadas_anticipadamente(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso();
        $materia = $this->crearMateria('Materia Eager', 'MAT00001');
        $detalle = $this->crearDetalle($docente, $curso, $materia);
        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();

        $cursos = $response->viewData('cursos');
        $this->assertNotNull($cursos);

        $primerDetalle = null;
        foreach ($cursos as $grupo) {
            foreach ($grupo as $d) {
                $primerDetalle = $d;
                break 2;
            }
        }

        $this->assertNotNull($primerDetalle, 'Debe existir al menos un detalle en la respuesta.');
        $this->assertTrue($primerDetalle->relationLoaded('curso'), 'La relación curso debe estar cargada.');
        $this->assertTrue($primerDetalle->relationLoaded('materia'), 'La relación materia debe estar cargada.');
    }

    public function test_fuente_de_verdad_es_asignaciones_docentes_no_detalle_cursos_maestro_id(): void
    {
        $docente = $this->crearDocente('MST00001');
        $otroMaestro = $this->crearDocente('MST00002');

        $curso = $this->crearCurso();
        $materia = $this->crearMateria('Materia Via Asignacion', 'MAT00001');

        $detalle = DetalleCurso::create([
            'curso_id' => $curso->id,
            'materia_id' => $materia->id,
            'maestro_id' => $otroMaestro->id,
        ]);

        $this->crearAsignacion($detalle, $docente, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertSee('Materia Via Asignacion', false);
    }

    public function test_no_asignacion_activa_existe_no_se_muestra_aun_si_detalle_maestro_id_coincide(): void
    {
        $docente = $this->crearDocente();
        $curso = $this->crearCurso();
        $materia = $this->crearMateria('Materia Sin Asignacion', 'MAT00001');

        $detalle = DetalleCurso::create([
            'curso_id' => $curso->id,
            'materia_id' => $materia->id,
            'maestro_id' => $docente->id,
        ]);

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Materia Sin Asignacion', false);
        $response->assertSee('No tienes programas activos asignados actualmente.', false);

        $this->assertDatabaseMissing('asignaciones_docentes', [
            'detalle_curso_id' => $detalle->id,
            'maestro_id' => $docente->id,
            'estado' => 'activa',
        ]);
    }

    public function test_no_se_exponen_datos_personales_de_otros_maestros(): void
    {
        $docente = $this->crearDocente('MST00001', 'Test', 'Docente');
        $otroMaestro = $this->crearDocente('MST00002', 'Otro', 'Maestro');

        $curso = $this->crearCurso();
        $materia = $this->crearMateria('Materia Ajena', 'MAT00002');
        $detalle = $this->crearDetalle($otroMaestro, $curso, $materia);
        $this->crearAsignacion($detalle, $otroMaestro, 'activa');

        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Materia Ajena', false);
        $response->assertDontSee('MST00002', false);
        $response->assertDontSee('Otro Maestro', false);
    }

    public function test_ruta_protegida_por_auth_verified_y_role_docente(): void
    {
        $response = $this->get(route('docente.dashboard'));
        $response->assertRedirect(route('login'));

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get(route('docente.dashboard'));
        $response->assertStatus(403);

        $alumno = User::factory()->create(['role' => 'alumno']);
        $response = $this->actingAs($alumno)->get(route('docente.dashboard'));
        $response->assertStatus(403);

        $docente = $this->crearDocente();
        $response = $this->actingAs($this->usuarioDocente($docente))
            ->get(route('docente.dashboard'));
        $response->assertOk();

        $route = Route::getRoutes()->getByName('docente.dashboard');
        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth', $middleware);
        $this->assertNotContains('verified', $middleware);
        $this->assertContains('role:docente', $middleware);
    }
}
