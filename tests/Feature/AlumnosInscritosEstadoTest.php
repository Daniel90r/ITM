<?php

namespace Tests\Feature;

use App\Models\{Alumno, Asistencia, Curso, DetalleCurso, Inscripcion, Mensualidad, Nota, Pago, User};
use App\Services\CambiarEstadoPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlumnosInscritosEstadoTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertNotSame(base_path('storage'), storage_path());
    }

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    private function inscrito(bool $activo = true, ?Curso $curso = null, array $datos = []): Inscripcion
    {
        $a = Alumno::factory()->create($datos + ['activo' => $activo]);
        return Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => ($curso ?? Curso::factory()->create())->id, 'estado' => 'aprobada', 'activa' => true]);
    }

    public function test_todos_por_defecto_y_filtros_institucionales(): void
    {
        $activo = $this->inscrito(); $inactivo = $this->inscrito(false);
        foreach (['pendiente', 'rechazada', 'inactiva'] as $estado) Inscripcion::factory()->create(['estado' => $estado]);
        foreach (['' => [$activo->alumno_id, $inactivo->alumno_id], 'todos' => [$activo->alumno_id, $inactivo->alumno_id], 'activos' => [$activo->alumno_id], 'inactivos' => [$inactivo->alumno_id]] as $filtro => $esperados) {
            $r = $this->get(route('admin.inscripciones', $filtro ? ['estado_alumno' => $filtro] : []))->assertOk();
            $this->assertEqualsCanonicalizing($esperados, $r->viewData('inscripciones')->pluck('alumno_id')->all());
        }
        $this->get(route('admin.inscripciones'))->assertSee('Activo')->assertSee('Inactivo')->assertSee('Desactivar')->assertSee('Activar')->assertDontSee('Estado y acceso');
    }

    public function test_desactivar_y_reactivar_con_flujo_existente_conserva_curso_e_historial(): void
    {
        $i = $this->inscrito(); $a = $i->alumno; $curso = $i->curso;
        $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id]);
        Nota::factory()->create(['alumno_id' => $a->id, 'detalle_curso_id' => $dc->id]);
        Asistencia::create(['alumno_id' => $a->id, 'curso_id' => $curso->id, 'detalle_curso_id' => $dc->id, 'registrado_por' => $dc->maestro_id, 'fecha' => '2026-10-03', 'estado' => 'presente']);
        $m = Mensualidad::factory()->create(['alumno_id' => $a->id, 'curso_id' => $curso->id]);
        Pago::create(['mensualidad_id' => $m->id, 'fecha_pago' => '2026-10-03', 'monto_pagado' => 25]);
        $tablas = ['inscripciones', 'cursos', 'detalle_cursos', 'notas', 'asistencias', 'mensualidades', 'pagos', 'boletas'];
        $antes = []; foreach ($tablas as $t) $antes[$t] = DB::table($t)->get()->toJson();
        foreach ([false, true] as $activo) {
            $revision = app(CambiarEstadoPersona::class)->revision($a);
            $this->from(route('admin.personas.estado', ['tipo' => 'alumnos', 'id' => $a->id]))
                ->patch(route('admin.personas.estado.update', ['tipo' => 'alumnos', 'id' => $a->id]), ['activo' => $activo, 'esperado' => !$activo, 'revision' => $revision, 'motivo' => 'Cambio institucional de prueba'])
                ->assertRedirect()->assertSessionHas('success');
            $r = $this->get(route('admin.inscripciones'))->assertOk()->assertSee($activo ? 'Activo' : 'Inactivo');
            $this->assertContains($i->id, $r->viewData('inscripciones')->pluck('id')->all());
            $this->assertSame($activo, $a->fresh()->activo);
            $this->assertSame('aprobada', $i->fresh()->estado);
            $this->assertSame($curso->id, $i->fresh()->curso_id);
            $this->assertSame(1, $curso->inscritos_count);
            foreach ($tablas as $t) $this->assertSame($antes[$t], DB::table($t)->get()->toJson(), $t);
        }
    }

    public function test_busqueda_curso_y_paginacion_preservan_filtros_sin_duplicados(): void
    {
        $curso = Curso::factory()->create();
        for ($n = 0; $n < 12; $n++) $this->inscrito(false, $curso, ['nombre' => 'Coincidencia', 'apellido' => 'Prueba', 'codigo' => 'HIST'.$n]);
        $this->inscrito(false, null, ['nombre' => 'Coincidencia']);
        $params = ['q' => 'Coincidencia', 'curso_id' => $curso->id, 'estado_alumno' => 'inactivos'];
        $p1 = $this->get(route('admin.inscripciones', $params))->assertOk()->viewData('inscripciones');
        $this->assertSame(12, $p1->total()); $this->assertCount(10, $p1);
        parse_str(parse_url($p1->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('inactivos', $query['estado_alumno']); $this->assertSame('Coincidencia', $query['q']);
        $this->assertSame((string)$curso->id, $query['curso_id']);
        $p2 = $this->get($p1->nextPageUrl())->assertOk()->viewData('inscripciones');
        $this->assertCount(2, $p2);
        $this->assertCount(12, array_unique([...$p1->pluck('id')->all(), ...$p2->pluck('id')->all()]));
        $this->assertSame(1, $this->get(route('admin.inscripciones', ['q' => 'HIST11']))->assertOk()->viewData('inscripciones')->total());
    }

    public function test_accion_del_listado_no_cancela_la_inscripcion(): void
    {
        $i = $this->inscrito();
        $r = $this->get(route('admin.inscripciones'))->assertOk();
        $r->assertSee('action="'.route('admin.personas.estado.update', ['tipo' => 'alumnos', 'id' => $i->alumno_id]).'"', false)
            ->assertSee('name="esperado"', false)->assertSee('name="revision"', false)
            ->assertDontSee('action="'.route('admin.inscripciones.desactivar', $i->id).'"', false);
    }

    public function test_validaciones_y_vacio(): void
    {
        foreach ([['estado_alumno' => 'aprobada'], ['curso_id' => 999999], ['q' => str_repeat('a',151)]] as $filtro) {
            $this->getJson(route('admin.inscripciones', $filtro))->assertUnprocessable();
        }
        $this->get(route('admin.inscripciones'))->assertOk()->assertSee('No hay alumnos con inscripción aprobada que coincidan con los filtros.');
    }

    public function test_permisos_de_listado_y_restriccion_de_alumno_inactivo(): void
    {
        $i = $this->inscrito(false);
        foreach (['docente', 'alumno'] as $rol) {
            $this->actingAs(User::factory()->create(['role' => $rol]))->get(route('admin.inscripciones'))->assertForbidden();
        }
        $user = User::factory()->create(['role' => 'alumno']); $i->alumno->update(['user_id' => $user->id]);
        $this->actingAs($user)->get('/alumno/dashboard')->assertRedirect(route('login'));
        auth()->logout(); $this->get(route('admin.inscripciones'))->assertRedirect(route('login'));
    }
}
