<?php

namespace Tests\Feature;

use App\Models\{Alumno, Asistencia, Curso, DetalleCurso, Inscripcion, Maestro, Materia, Mensualidad, Nota, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutocompletadoFiltrosTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertNotSame(base_path('storage'), storage_path());
    }

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function url(string $modulo, array $f = []): string
    {
        return route('admin.sugerencias.busqueda', ['modulo' => $modulo] + $f);
    }

    public function test_busqueda_parcial_por_nombre_apellido_codigo_y_limite_sin_datos_privados(): void
    {
        $a = Alumno::factory()->create(['nombre' => 'Ana Isabel', 'apellido' => 'Orellana', 'codigo' => 'ITM-ABC-789']);
        foreach (['aNA', 'RELL', 'abc-7'] as $q) {
            $r = $this->getJson($this->url('alumnos', ['q' => $q]))->assertOk()->assertJsonCount(1, 'alumnos');
            $this->assertSame(['id', 'nombre', 'codigo'], array_keys($r->json('alumnos.0')));
            $this->assertSame($a->id, $r->json('alumnos.0.id'));
        }
        foreach (range(1, 10) as $i) Alumno::factory()->create(['nombre' => 'Ana '.$i]);
        $this->getJson($this->url('alumnos', ['q' => 'ana']))->assertOk()->assertJsonCount(8, 'alumnos');
        $this->getJson($this->url('alumnos', ['q' => '%_']))->assertOk()->assertJsonCount(0, 'alumnos');
        foreach (['maestros' => Maestro::factory()->create(['nombre' => 'Roberto', 'codigo' => 'DOC-123']),
            'materias' => Materia::factory()->create(['nombre' => 'Informatica', 'codigo' => 'MAT-123'])] as $tipo => $registro) {
            $this->getJson($this->url($tipo, ['q' => $registro->codigo]))->assertOk()->assertJsonPath('alumnos.0.id', $registro->id);
        }
    }

    public function test_cursos_respetan_turno_y_notas_solo_cursos_activos(): void
    {
        $c = Curso::factory()->create(['nombre' => 'Windows', 'nivel' => 'Matutino', 'activo' => true]);
        Curso::factory()->create(['nombre' => 'Windows Noche', 'nivel' => 'Nocturno', 'activo' => false]);
        $this->getJson($this->url('cursos', ['q' => 'WIN', 'turno' => 'matutino']))->assertOk()->assertJsonCount(1, 'alumnos')->assertJsonPath('alumnos.0.id', $c->id);
        $this->getJson($this->url('notas-cursos', ['q' => 'win']))->assertOk()->assertJsonCount(1, 'alumnos');
        $this->getJson($this->url('boletas-cursos', ['q' => 'win']))->assertOk()->assertJsonCount(0, 'alumnos');
    }

    public function test_solicitudes_separan_estados_y_no_duplican_alumno(): void
    {
        $a = Alumno::factory()->create(['nombre' => 'Ana Pendiente']);
        foreach (range(1, 2) as $_) Inscripcion::factory()->create(['alumno_id' => $a->id, 'estado' => 'pendiente']);
        $this->getJson($this->url('solicitudes', ['q' => 'Ana', 'estado' => 'pendiente']))->assertOk()->assertJsonCount(1, 'alumnos');
        $this->getJson($this->url('solicitudes', ['q' => 'Ana', 'estado' => 'aprobada']))->assertOk()->assertJsonCount(0, 'alumnos');
    }

    public function test_mensualidades_reutiliza_elegibilidad_y_filtros_sin_exigir_pagos(): void
    {
        $curso = Curso::factory()->create(['activo' => true]);
        $a = Alumno::factory()->create(['nombre' => 'Ana Aprobada']);
        Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => $curso->id, 'estado' => 'aprobada', 'activa' => true]);
        $pendiente = Alumno::factory()->create(['nombre' => 'Ana Pendiente']);
        Inscripcion::factory()->create(['alumno_id' => $pendiente->id, 'curso_id' => $curso->id, 'estado' => 'pendiente']);
        Mensualidad::factory()->create(['alumno_id' => $a->id, 'curso_id' => $curso->id, 'anio' => 2025, 'mes' => 'Enero', 'estado' => 'Pendiente']);
        $url = route('admin.sugerencias.mensualidades', ['q' => 'Ana', 'curso_id' => $curso->id, 'anio' => 2025, 'mes' => 'Enero']);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'alumnos')->assertJsonPath('alumnos.0.id', $a->id);
        $this->getJson(route('admin.sugerencias.mensualidades', ['q' => 'Ana', 'anio' => 2025, 'mes' => 'Febrero']))->assertOk()->assertJsonCount(0, 'alumnos');
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_estado_cuenta_conserva_retirados_con_obligaciones_y_encargados_solo_menores(): void
    {
        $a = Alumno::factory()->create(['nombre' => 'Ana Historial', 'activo' => false, 'fecha_nacimiento' => today()->subYears(30)]);
        Mensualidad::factory()->create(['alumno_id' => $a->id]);
        $this->getJson(route('admin.sugerencias.estados-cuenta', ['q' => 'Ana']))->assertOk()->assertJsonPath('alumnos.0.id', $a->id);
        $this->getJson(route('admin.sugerencias.encargados', ['q' => 'Ana']))->assertOk()->assertJsonCount(0, 'alumnos');
        $menor = Alumno::factory()->create(['nombre' => 'Ana Menor', 'fecha_nacimiento' => today()->subYears(12)]);
        $this->getJson(route('admin.sugerencias.encargados', ['q' => 'Ana']))->assertOk()->assertJsonPath('alumnos.0.id', $menor->id);
    }

    public function test_asistencia_y_notas_respetan_curso_y_materia_y_conservan_historicos(): void
    {
        $curso = Curso::factory()->create(['activo' => true]);
        $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id]);
        $otro = DetalleCurso::factory()->create();
        $a = Alumno::factory()->create(['nombre' => 'Ana Historica', 'activo' => false]);
        Asistencia::create(['alumno_id' => $a->id, 'curso_id' => $curso->id, 'detalle_curso_id' => $dc->id, 'fecha' => '2025-10-04', 'estado' => 'presente', 'registrado_por' => $dc->maestro_id]);
        Nota::factory()->create(['alumno_id' => $a->id, 'detalle_curso_id' => $dc->id]);
        $this->getJson(route('admin.sugerencias.asistencias', ['q' => 'Ana', 'curso_id' => $curso->id, 'detalle_curso_id' => $dc->id, 'fecha_desde' => '2025-10-01', 'fecha_hasta' => '2025-10-31']))
            ->assertOk()->assertJsonPath('alumnos.0.id', $a->id);
        $this->getJson(route('admin.sugerencias.asistencias', ['q' => 'Ana', 'detalle_curso_id' => $otro->id]))->assertOk()->assertJsonCount(0, 'alumnos');
        $this->getJson($this->url('notas-alumnos', ['q' => 'Ana', 'curso' => $curso->nombre]))->assertOk()->assertJsonPath('alumnos.0.id', $a->id);
        $this->getJson($this->url('boletas-alumnos', ['q' => 'Ana', 'curso_id' => $curso->id]))->assertOk()->assertJsonCount(0, 'alumnos');
        Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => $curso->id, 'estado' => 'aprobada']);
        $this->getJson($this->url('boletas-alumnos', ['q' => 'Ana', 'curso_id' => $curso->id]))->assertOk()->assertJsonPath('alumnos.0.id', $a->id);
        $this->getJson($this->url('boletas-alumnos', ['q' => 'Ana', 'curso_id' => $otro->curso_id]))->assertOk()->assertJsonCount(0, 'alumnos');
    }

    public function test_endpoints_solo_lectura_y_roles_y_validaciones(): void
    {
        $urls = array_map(fn ($tipo) => $this->url($tipo, ['q' => 'Ana']), ['alumnos','maestros','materias','cursos','solicitudes','notas-cursos','notas-alumnos','boletas-cursos','boletas-alumnos']);
        foreach (['asistencias','mensualidades','encargados','estados-cuenta'] as $tipo) $urls[] = route('admin.sugerencias.'.$tipo, ['q' => 'Ana']);
        DB::connection()->beforeExecuting(fn ($sql) => $this->assertMatchesRegularExpression('/^\s*(select|pragma)/i', $sql));
        foreach ($urls as $url) $this->getJson($url)->assertOk();
    }

    public function test_acceso_exclusivo_admin_y_rechazo_parametros_invalidos(): void
    {
        $this->getJson($this->url('alumnos', ['q' => ['Ana']]))->assertUnprocessable();
        $this->getJson($this->url('alumnos', ['q' => 'Ana', 'anio' => 'mal']))->assertUnprocessable();
        foreach (['docente', 'alumno'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['alumnos','maestros','cursos','materias','solicitudes','notas-cursos','notas-alumnos','boletas-alumnos','boletas-cursos'] as $tipo)
                $this->getJson($this->url($tipo, ['q' => 'Ana']))->assertForbidden();
            foreach (['asistencias','mensualidades','encargados','estados-cuenta'] as $tipo)
                $this->getJson(route('admin.sugerencias.'.$tipo, ['q' => 'Ana']))->assertForbidden();
        }
        $this->app['auth']->guard()->logout();
        $this->getJson($this->url('alumnos', ['q' => 'Ana']))->assertUnauthorized();
    }

    public function test_filtro_academico_alumnos_y_numero_de_consultas_no_crece_por_sugerencia(): void
    {
        $primero = Curso::factory()->create(['nombre' => 'Curso primero', 'anio_lectivo' => 2025]);
        $segundo = Curso::factory()->create(['nombre' => 'Curso segundo', 'anio_lectivo' => 2026]);
        $a = Alumno::factory()->create(['nombre' => 'Ana Ejemplo']);
        foreach ([$primero, $segundo] as $curso) Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => $curso->id, 'estado' => 'aprobada']);
        $this->getJson($this->url('alumnos', ['q' => 'Ana', 'curso' => 'curso primero', 'anio' => 2025]))->assertOk()->assertJsonCount(1, 'alumnos');
        $this->getJson($this->url('alumnos', ['q' => 'Ana', 'curso' => 'curso segundo', 'anio' => 2026]))->assertOk()->assertJsonCount(0, 'alumnos');
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->getJson($this->url('alumnos', ['q' => 'Ana']))->assertOk(); $antes = count(DB::getQueryLog()); DB::disableQueryLog();
        foreach (range(1, 12) as $i) Alumno::factory()->create(['nombre' => 'Ana '.$i]);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->getJson($this->url('alumnos', ['q' => 'Ana']))->assertOk()->assertJsonCount(8, 'alumnos');
        $this->assertLessThanOrEqual($antes, count(DB::getQueryLog())); DB::disableQueryLog();
    }
}
