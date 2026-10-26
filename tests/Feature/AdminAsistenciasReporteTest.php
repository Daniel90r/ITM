<?php

namespace Tests\Feature;

use App\Models\{Alumno, Asistencia, Curso, DetalleCurso, Maestro, Materia, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAsistenciasReporteTest extends TestCase
{
    use RefreshDatabase;

    private Alumno $alumno;
    private DetalleCurso $detalle;
    private DetalleCurso $otraMateria;
    private DetalleCurso $otroCurso;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->alumno = Alumno::factory()->create();
        $this->detalle = DetalleCurso::factory()->create([
            'curso_id' => Curso::factory()->create(['nombre' => 'Curso de prueba'])->id,
            'materia_id' => Materia::factory()->create(['nombre' => 'Programa de prueba'])->id,
            'maestro_id' => Maestro::factory()->create()->id,
        ]);
        $this->otraMateria = DetalleCurso::factory()->create(['curso_id' => $this->detalle->curso_id]);
        $this->otroCurso = DetalleCurso::factory()->create();
    }

    private function registrar(DetalleCurso $detalle, string $fecha, string $estado, ?Alumno $alumno = null): void
    {
        Asistencia::create([
            'curso_id' => $detalle->curso_id, 'detalle_curso_id' => $detalle->id,
            'alumno_id' => ($alumno ?? $this->alumno)->id, 'registrado_por' => $detalle->maestro_id,
            'fecha' => $fecha, 'estado' => $estado,
        ]);
    }

    private function historial(): void
    {
        $this->registrar($this->detalle, '2026-10-02', 'presente');
        $this->registrar($this->detalle, '2026-10-03', 'ausente');
        $this->registrar($this->detalle, '2026-10-04', 'permiso');
        $this->registrar($this->detalle, '2026-09-30', 'presente');
        $this->registrar($this->otraMateria, '2026-10-02', 'presente');
        $this->registrar($this->otroCurso, '2026-10-02', 'ausente');
    }

    public function test_resumen_real_conserva_permisos_y_no_modifica_datos(): void
    {
        $this->historial();
        $tablas = ['asistencias', 'alumnos', 'cursos', 'maestros', 'inscripciones', 'notas', 'boletas'];
        $antes = [];
        foreach ($tablas as $tabla) $antes[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        DB::connection()->beforeExecuting(function (string $sql): void {
            $this->assertMatchesRegularExpression('/^\s*(select|pragma)\b/i', $sql);
        });

        $this->get(route('admin.asistencias'))->assertOk()
            ->assertViewHas('resumen', ['total' => 6, 'presentes' => 3, 'ausentes' => 2, 'permisos' => 1, 'porcentaje' => 50.0])
            ->assertSee('Reportes de asistencia')->assertSee('Período: todas las fechas')
            ->assertSee('Presente')->assertSee('Ausente')->assertSee('Permiso')
            ->assertDontSee('class="resumen-grid"', false)->assertDontSee('Total de registros')->assertDontSee('Porcentaje de asistencia')
            ->assertSee('Filtrar asistencias')
            ->assertSee($this->alumno->nombre_completo)->assertSee($this->alumno->codigo)
            ->assertDontSee('Programa de prueba')->assertSee('Curso de prueba')
            ->assertSee('role="region"', false)->assertSee('scope="col"', false);

        $detalle = $this->getJson(route('admin.asistencias.detalles', $this->alumno))->assertOk()->json('html');
        $this->assertStringContainsString('Programa de prueba', $detalle);
        $this->assertStringContainsString('Permiso', $detalle);

        foreach ($tablas as $tabla) $this->assertSame($antes[$tabla], DB::table($tabla)->orderBy('id')->get()->toJson(), $tabla);
    }

    public static function filtros(): array
    {
        return [
            'curso' => ['curso_id', 5, 3, 1, 1, 60.0],
            'materia' => ['detalle_curso_id', 4, 2, 1, 1, 50.0],
            'docente' => ['maestro_id', 4, 2, 1, 1, 50.0],
            'desde' => ['fecha_desde', 5, 2, 2, 1, 40.0],
            'hasta' => ['fecha_hasta', 5, 3, 2, 0, 60.0],
        ];
    }

    #[DataProvider('filtros')]
    public function test_cada_filtro_aplica_tambien_al_resumen(string $filtro, int $total, int $presentes, int $ausentes, int $permisos, float $porcentaje): void
    {
        $this->historial();
        $valores = ['curso_id' => $this->detalle->curso_id, 'detalle_curso_id' => $this->detalle->id,
            'maestro_id' => $this->detalle->maestro_id, 'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-03'];
        $this->get(route('admin.asistencias', [$filtro => $valores[$filtro]]))->assertOk()
            ->assertViewHas('resumen', compact('total', 'presentes', 'ausentes', 'permisos', 'porcentaje'))
            ->assertViewHas('alumnos', fn ($filas) => $filas->total() === 1 && (int) $filas->first()->registros === $total);
    }

    public function test_combina_filtros_y_conserva_controles_seleccionados(): void
    {
        $this->historial();
        $filtros = ['curso_id' => $this->detalle->curso_id, 'detalle_curso_id' => $this->detalle->id,
            'maestro_id' => $this->detalle->maestro_id, 'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-04'];
        $response = $this->get(route('admin.asistencias', $filtros))->assertOk()
            ->assertViewHas('resumen', ['total' => 3, 'presentes' => 1, 'ausentes' => 1, 'permisos' => 1, 'porcentaje' => 33.3])
            ->assertSee('Desde 2026-10-01')->assertSee('Hasta 2026-10-04');
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach (['curso_id', 'detalle_curso_id', 'maestro_id'] as $nombre) {
            $this->assertSame((string) $filtros[$nombre], $xpath->evaluate('string(//select[@name="'.$nombre.'"]/option[@selected]/@value)'));
        }
        foreach (['fecha_desde', 'fecha_hasta'] as $nombre) {
            $this->assertSame($filtros[$nombre], $xpath->evaluate('string(//input[@name="'.$nombre.'"]/@value)'));
        }
    }

    public function test_resumen_incluye_todas_las_paginas_y_enlaces_conservan_filtros(): void
    {
        for ($i = 0; $i < 55; $i++) {
            $this->registrar($this->detalle, now()->startOfYear()->addDays($i)->toDateString(), $i === 0 ? 'ausente' : 'presente');
        }
        $this->registrar($this->otroCurso, now()->toDateString(), 'ausente');
        $filtros = ['curso_id' => $this->detalle->curso_id, 'maestro_id' => $this->detalle->maestro_id];
        $response = $this->get(route('admin.asistencias', $filtros))->assertOk()
            ->assertViewHas('resumen', ['total' => 55, 'presentes' => 54, 'ausentes' => 1, 'permisos' => 0, 'porcentaje' => 98.2])
            ->assertViewHas('alumnos', fn ($filas) => $filas->count() === 1 && (int) $filas->first()->registros === 55);
        $detalle = $this->getJson(route('admin.asistencias.detalles', ['alumno' => $this->alumno->id] + $filtros + ['per_page' => 10000]))->assertOk()->json('html');
        $this->assertSame(20, substr_count($detalle, 'data-asistencia-id='));
        $this->assertStringContainsString('maestro_id='.$this->detalle->maestro_id, $detalle);
        $this->assertStringContainsString('detalle_page=2', $detalle);
        $pagina3 = $this->getJson(route('admin.asistencias.detalles', ['alumno' => $this->alumno->id] + $filtros + ['detalle_page' => 3]))->assertOk()->json('html');
        $this->assertSame(15, substr_count($pagina3, 'data-asistencia-id='));
    }

    public function test_estado_vacio_no_inventa_un_porcentaje(): void
    {
        $this->get(route('admin.asistencias'))->assertOk()
            ->assertViewHas('resumen', ['total' => 0, 'presentes' => 0, 'ausentes' => 0, 'permisos' => 0, 'porcentaje' => null])
            ->assertSee('No hay asistencias para mostrar')->assertSee('Limpiar filtros')
            ->assertDontSee('class="resumen-grid"', false)->assertDontSee('0.0%');
    }

    public function test_historial_sin_detalle_de_materia_no_impide_renderizar(): void
    {
        $this->registrar($this->detalle, '2026-10-01', 'presente');
        Asistencia::query()->update(['detalle_curso_id' => null]);
        $this->get(route('admin.asistencias'))->assertOk()->assertSee($this->alumno->nombre_completo)
            ->assertViewHas('resumen', fn ($resumen) => $resumen['total'] === 1 && $resumen['porcentaje'] === 100.0);
        $this->getJson(route('admin.asistencias.detalles', $this->alumno))->assertOk();
    }

    public function test_un_alumno_con_varias_materias_y_cursos_aparece_una_sola_vez(): void
    {
        $this->historial();
        $response = $this->get(route('admin.asistencias'))->assertOk()
            ->assertViewHas('alumnos', fn ($filas) => $filas->total() === 1
                && (int) $filas->first()->registros === 6 && (int) $filas->first()->permisos === 1)
            ->assertViewHas('cursosPorAlumno', fn ($cursos) => $cursos->get($this->alumno->id)->count() === 2)
            ->assertSee('1 alumno')->assertSee('6 registros');
        $this->assertSame(1, substr_count($response->getContent(), 'data-alumno-id='));
        $this->assertStringNotContainsString('data-asistencia-id=', $response->getContent());
        $html = $this->getJson(route('admin.asistencias.detalles', $this->alumno))->assertOk()->json('html');
        preg_match_all('/data-asistencia-id="(\d+)"/', $html, $ids);
        $this->assertSame(Asistencia::orderByDesc('fecha')->orderByDesc('id')->pluck('id')->map(fn ($id) => (string) $id)->all(), $ids[1]);
    }

    public function test_detalles_comparten_todos_los_filtros_del_resumen(): void
    {
        $this->historial();
        $filtros = ['alumno' => $this->alumno->id, 'curso_id' => $this->detalle->curso_id,
            'detalle_curso_id' => $this->detalle->id, 'maestro_id' => $this->detalle->maestro_id,
            'fecha_desde' => '2026-10-03', 'fecha_hasta' => '2026-10-04', 'q' => $this->alumno->codigo];
        $html = $this->getJson(route('admin.asistencias.detalles', $filtros))->assertOk()->json('html');
        $this->assertSame(2, substr_count($html, 'data-asistencia-id='));
        $this->assertStringContainsString('03/10/2026', $html);
        $this->assertStringContainsString('04/10/2026', $html);
        $this->assertStringNotContainsString('02/10/2026', $html);
        $this->assertStringContainsString(e($this->detalle->maestro->nombre_completo), $html);
        $this->getJson(route('admin.asistencias.detalles', $filtros + ['no_usado' => 1]))->assertOk();
        $filtros['q'] = 'Nombre que no coincide';
        $this->getJson(route('admin.asistencias.detalles', $filtros))->assertNotFound();
        $filtros['q'] = '';
        $filtros['maestro_id'] = $this->otroCurso->maestro_id;
        $this->getJson(route('admin.asistencias.detalles', $filtros))->assertNotFound();
    }

    public function test_busqueda_por_nombre_completo_codigo_y_caracteres_literales(): void
    {
        $this->alumno->update(['nombre' => 'Ana', 'apellido' => 'Lopez', 'codigo' => 'ALM-PRUEBA']);
        $this->historial();
        $otro = Alumno::factory()->create(['nombre' => 'Ana', 'apellido' => 'Ruiz']);
        $this->registrar($this->detalle, '2026-10-02', 'ausente', $otro);
        foreach (['Ana Lopez', 'ALM-PRUEBA'] as $busqueda) {
            $this->get(route('admin.asistencias', ['q' => $busqueda]))->assertOk()
                ->assertViewHas('alumnos', fn ($filas) => $filas->total() === 1 && $filas->first()->id === $this->alumno->id)
                ->assertViewHas('resumen', fn ($resumen) => $resumen['total'] === 6)
                ->assertSee('value="'.$busqueda.'"', false);
        }
        $this->get(route('admin.asistencias', ['q' => '%_']))->assertOk()
            ->assertViewHas('resumen', fn ($resumen) => $resumen['total'] === 0);
    }

    public function test_paginacion_por_alumnos_con_orden_estable_sin_duplicados_ni_omisiones(): void
    {
        $this->alumno->update(['nombre' => 'Mismo', 'apellido' => 'Nombre']);
        $alumnos = Alumno::factory()->count(29)->create(['nombre' => 'Mismo', 'apellido' => 'Nombre'])->prepend($this->alumno);
        foreach ($alumnos as $alumno) {
            $this->registrar($this->detalle, '2026-10-02', 'presente', $alumno);
            $this->registrar($this->otraMateria, '2026-10-02', 'ausente', $alumno);
        }
        $filtros = ['q' => 'Mismo Nombre', 'curso_id' => $this->detalle->curso_id];
        $primera = $this->get(route('admin.asistencias', $filtros))->assertOk();
        $segunda = $this->get(route('admin.asistencias', $filtros + ['page' => 2]))->assertOk();
        $p1 = $primera->viewData('alumnos'); $p2 = $segunda->viewData('alumnos');
        $this->assertSame(25, $p1->count()); $this->assertSame(5, $p2->count());
        $ids = array_merge($p1->pluck('id')->all(), $p2->pluck('id')->all());
        $this->assertSame($alumnos->pluck('id')->sort()->values()->all(), $ids);
        $this->assertCount(30, array_unique($ids));
        $this->assertSame(60, $primera->viewData('resumen')['total']);
        $this->assertSame($primera->viewData('resumen'), $segunda->viewData('resumen'));
        parse_str(parse_url($p1->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('Mismo Nombre', $query['q']);
        $this->assertSame((string) $this->detalle->curso_id, $query['curso_id']);
    }

    public function test_consultas_del_listado_no_crecen_por_cada_alumno(): void
    {
        $this->registrar($this->detalle, '2026-10-02', 'presente');
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->get(route('admin.asistencias'))->assertOk();
        $cantidad = count(DB::getQueryLog());
        DB::disableQueryLog();
        foreach (Alumno::factory()->count(19)->create() as $alumno) $this->registrar($this->detalle, '2026-10-02', 'presente', $alumno);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->get(route('admin.asistencias'))->assertOk();
        $this->assertLessThanOrEqual($cantidad + 1, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_carga_de_detalles_es_exclusivamente_administrativa(): void
    {
        $this->historial();
        $url = route('admin.asistencias.detalles', $this->alumno);
        foreach (['docente', 'alumno'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->getJson($url)->assertForbidden();
        }
        $this->app['auth']->guard()->logout();
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_detalle_agrupa_meses_y_anios_en_orden_sin_duplicar_registros(): void
    {
        $this->registrar($this->detalle, '2026-10-17', 'presente');
        $this->registrar($this->otraMateria, '2026-10-03', 'permiso');
        $this->registrar($this->detalle, '2026-09-19', 'ausente');
        $this->registrar($this->detalle, '2025-10-11', 'presente');
        $html = $this->getJson(route('admin.asistencias.detalles', $this->alumno))->assertOk()->json('html');
        $this->assertStringContainsString('Octubre 2026 · 2 registros', $html);
        $this->assertStringContainsString('Septiembre 2026 · 1 registro', $html);
        $this->assertStringContainsString('Octubre 2025 · 1 registro', $html);
        preg_match_all('/data-mes="([0-9-]+)"/', $html, $meses);
        $this->assertSame(['2026-10', '2026-09', '2025-10'], $meses[1]);
        preg_match_all('/data-asistencia-id="(\d+)"/', $html, $ids);
        $this->assertCount(4, $ids[1]); $this->assertCount(4, array_unique($ids[1]));
        $this->assertSame(3, substr_count($html, '<details '));
        $this->assertSame(3, substr_count($html, '<summary>'));
        $this->assertStringContainsString('17/10/2026', $html);
        $this->assertStringContainsString(e($this->detalle->maestro->nombre_completo), $html);
    }

    public function test_mes_anio_y_filtros_existentes_comparten_totales_y_detalles(): void
    {
        $this->registrar($this->detalle, '2026-10-17', 'presente');
        $this->registrar($this->detalle, '2026-10-03', 'ausente');
        $this->registrar($this->detalle, '2026-09-19', 'permiso');
        $this->registrar($this->detalle, '2025-10-11', 'presente');
        $this->registrar($this->otraMateria, '2026-10-17', 'presente');
        $f = ['anio' => 2026, 'mes' => 10, 'curso_id' => $this->detalle->curso_id, 'detalle_curso_id' => $this->detalle->id, 'maestro_id' => $this->detalle->maestro_id, 'q' => $this->alumno->codigo];
        $r = $this->get(route('admin.asistencias', $f))->assertOk();
        $a = $r->viewData('alumnos')->first();
        $this->assertSame(2, (int)$a->registros); $this->assertSame(1, (int)$a->presentes); $this->assertSame(1, (int)$a->ausentes);
        $html = $this->getJson(route('admin.asistencias.detalles', ['alumno' => $this->alumno->id] + $f))->assertOk()->json('html');
        $this->assertSame(2, substr_count($html, 'data-asistencia-id='));
        $this->assertStringNotContainsString('Septiembre', $html);
        $this->assertStringNotContainsString('2025', $html);
        $this->get(route('admin.asistencias', ['anio' => 2026]))->assertViewHas('resumen', fn ($s) => $s['total'] === 4);
        $this->get(route('admin.asistencias'))->assertViewHas('resumen', fn ($s) => $s['total'] === 5);
        $this->get(route('admin.asistencias', $f + ['fecha_desde' => '2026-10-10']))->assertViewHas('resumen', fn ($s) => $s['total'] === 1);
        $this->get(route('admin.asistencias', ['anio' => 2024, 'mes' => 1]))->assertOk()->assertSee('No hay asistencias para mostrar');
    }

    public function test_totales_mensuales_completos_y_paginacion_sin_perder_filtros(): void
    {
        for ($dia = 1; $dia <= 25; $dia++) $this->registrar($this->detalle, sprintf('2026-10-%02d', $dia), 'presente');
        $url = route('admin.asistencias.detalles', ['alumno' => $this->alumno->id, 'anio' => 2026, 'mes' => 10]);
        $uno = $this->getJson($url)->assertOk()->json('html');
        $dos = $this->getJson($url.'&detalle_page=2')->assertOk()->json('html');
        $this->assertStringContainsString('Octubre 2026 · 25 registros', $uno);
        $this->assertStringContainsString('20 en esta página', $uno);
        $this->assertStringContainsString('5 en esta página', $dos);
        $this->assertStringContainsString('anio=2026', $uno); $this->assertStringContainsString('mes=10', $uno);
        preg_match_all('/data-asistencia-id="(\d+)"/', $uno.$dos, $ids);
        $this->assertCount(25, $ids[1]); $this->assertCount(25, array_unique($ids[1]));
    }

    public function test_periodo_invalido_se_rechaza_en_listado_y_detalle(): void
    {
        foreach ([['mes' => 10], ['anio' => 'incorrecto'], ['anio' => 2026, 'mes' => 13]] as $f) {
            $this->getJson(route('admin.asistencias', $f))->assertUnprocessable();
            $this->getJson(route('admin.asistencias.detalles', ['alumno' => $this->alumno->id] + $f))->assertUnprocessable();
        }
    }

    public function test_fetch_reutiliza_el_listado_y_mantiene_validacion_y_get_tradicional(): void
    {
        $this->historial();
        $f = ['anio' => 2026, 'mes' => 10, 'curso_id' => $this->detalle->curso_id];
        $normal = $this->get(route('admin.asistencias', $f))->assertOk();
        $ajax = $this->getJson(route('admin.asistencias', $f))->assertOk();
        $this->assertSame($normal->viewData('alumnos')->pluck('id')->all(), $ajax->viewData('alumnos')->pluck('id')->all());
        $ajax->assertSee('data-asistencias-resultados', false)->assertSee('data-asistencias-filtros', false)
            ->assertSee('data-asistencias-limpiar', false)->assertSee('method="GET"', false)->assertDontSee('onchange="this.form.submit()"', false);
        $this->getJson(route('admin.asistencias', ['mes' => 10]))->assertUnprocessable()->assertJsonValidationErrors('anio');
    }

    public function test_detalles_escapan_contenido_y_no_exponen_expedientes(): void
    {
        $cuenta = User::factory()->create(['role' => 'alumno']);
        $this->alumno->update(['user_id' => $cuenta->id]);
        $this->historial();
        $this->detalle->materia->update(['nombre' => '<script>alert(1)</script>']);
        $html = $this->getJson(route('admin.asistencias.detalles', $this->alumno))->assertOk()->json('html');
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString($cuenta->email, $html);
        $this->getJson(route('admin.asistencias.detalles', ['alumno' => 999999]))->assertNotFound();
    }
}
