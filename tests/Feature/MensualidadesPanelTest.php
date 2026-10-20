<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, Inscripcion, Mensualidad, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MensualidadesPanelTest extends TestCase
{
    use RefreshDatabase;
    private Curso $curso;
    private Curso $otroCurso;
    private Alumno $sin;
    private Alumno $uno;
    private Alumno $varios;

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
        $this->curso = Curso::factory()->create(['nombre' => 'Curso A', 'activo' => true]);
        $this->otroCurso = Curso::factory()->create(['nombre' => 'Curso B', 'activo' => true]);
        $this->sin = $this->inscribir('Sin', 'Registros');
        $this->uno = $this->inscribir('Ana', 'Prueba');
        $this->varios = $this->inscribir('Maria Fernanda', 'Rodriguez');
        $this->mensualidad($this->uno, 'Enero', 'Pendiente', 17.50);
        $this->mensualidad($this->varios, 'Enero', 'Pagado', 25);
        $this->mensualidad($this->varios, 'Febrero', 'Pendiente', 40);
        $this->mensualidad($this->varios, 'Marzo', 'Pagado', 12.50);
        $this->mensualidad($this->varios, 'Enero', 'Pagado', 99, 2025);
    }

    private function inscribir(string $nombre, string $apellido, ?Curso $curso = null): Alumno
    {
        $alumno = Alumno::factory()->create(compact('nombre', 'apellido'));
        Inscripcion::factory()->create(['curso_id' => ($curso ?? $this->curso)->id, 'alumno_id' => $alumno->id, 'estado' => 'aprobada', 'activa' => true]);
        return $alumno;
    }

    private function mensualidad(Alumno $alumno, string $mes, string $estado, float $monto, int $anio = 2026): Mensualidad
    {
        return Mensualidad::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $this->curso->id, 'mes' => $mes, 'anio' => $anio, 'estado' => $estado, 'monto' => $monto, 'fecha_vencimiento' => '2026-12-31']);
    }

    private function listado(array $filtros = [])
    {
        return $this->get(route('admin.mensualidades', array_merge(['anio' => 2026], $filtros)));
    }

    private function detalles(Alumno $alumno, array $filtros = [], ?Curso $curso = null)
    {
        return $this->getJson(route('admin.mensualidades.detalles', array_merge([
            'curso' => ($curso ?? $this->curso)->id, 'alumno' => $alumno->id, 'anio' => 2026,
        ], $filtros)));
    }

    public function test_listado_agrupa_cero_una_y_varias_sin_cargar_los_meses(): void
    {
        $response = $this->listado()->assertOk()->assertViewHas('totalAlumnos', 3)
            ->assertViewHas('totalPagado', fn ($total) => (float) $total === 37.5)
            ->assertViewHas('totalPendiente', fn ($total) => (float) $total === 57.5)
            ->assertSee('Sin generar')->assertSee('Ver mensualidades');
        $listado = $response->viewData('listado');
        $this->assertSame(3, $listado->total());
        $this->assertSame(3, substr_count($response->getContent(), 'class="mp-student"'));
        $this->assertSame(1, substr_count($response->getContent(), '>Sin generar<'));
        $this->assertStringNotContainsString('data-mensualidad-id=', $response->getContent());
        $this->assertSame(0, (int) $listado->firstWhere('alumno_id', $this->sin->id)->registros);
        $this->assertSame(1, (int) $listado->firstWhere('alumno_id', $this->uno->id)->registros);
        $this->assertSame(3, (int) $listado->firstWhere('alumno_id', $this->varios->id)->registros);
    }

    public static function filtros(): array
    {
        return ['nombre' => [['q' => 'Maria Fernanda'], 1], 'mes' => [['mes' => 'Enero'], 2],
            'estado' => [['estado' => 'Pagado'], 1], 'combinados' => [['q' => 'Rodriguez', 'mes' => 'Febrero', 'estado' => 'Pendiente'], 1],
            'sin resultados' => [['mes' => 'Diciembre'], 0]];
    }

    #[DataProvider('filtros')]
    public function test_filtros_no_cambian_los_indicadores_del_ciclo(array $filtros, int $alumnos): void
    {
        $this->listado($filtros)->assertOk()->assertViewHas('listado', fn ($p) => $p->total() === $alumnos)
            ->assertViewHas('totalAlumnos', 3)->assertViewHas('totalPagado', fn ($v) => (float) $v === 37.5)
            ->assertViewHas('totalPendiente', fn ($v) => (float) $v === 57.5);
    }

    public function test_curso_codigo_y_ciclo_se_conservan_y_el_selector_no_duplica_el_anio(): void
    {
        $this->listado(['curso_id' => $this->otroCurso->id])->assertOk()->assertViewHas('listado', fn ($p) => $p->total() === 0);
        $response = $this->listado(['q' => $this->uno->codigo])->assertOk()->assertViewHas('listado', fn ($p) => $p->total() === 1);
        $dom = new \DOMDocument(); @$dom->loadHTML($response->getContent()); $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//*[@name="anio" and @form="mensualidadesFiltros"]')->length);
        $this->assertSame(0, $xpath->query('//form[@id="mensualidadesFiltros"]//input[@name="anio"]')->length);
        $this->assertSame($this->uno->codigo, $xpath->evaluate('string(//*[@id="buscarAlumno"]/@value)'));
        $this->listado(['anio' => 2025])->assertOk()->assertViewHas('totalPagado', fn ($v) => (float) $v === 99.0);
    }

    public function test_detalles_mantienen_montos_estados_acciones_csrf_y_filtros(): void
    {
        $html = $this->detalles($this->varios)->assertOk()->json('html');
        $this->assertSame(3, substr_count($html, 'data-mensualidad-id='));
        $this->assertStringContainsString('$40.00', $html);
        $this->assertStringContainsString('data-confirm-titulo="Registrar pago"', $html);
        $this->assertStringContainsString('data-confirm-titulo="Revertir pago"', $html);
        $this->assertSame(3, substr_count($html, 'name="_token"'));
        $this->assertStringContainsString('form="formLote"', $html);
        $filtrado = $this->detalles($this->varios, ['mes' => 'Febrero', 'estado' => 'Pendiente', 'q' => 'Rodriguez'])->assertOk()->json('html');
        $this->assertSame(1, substr_count($filtrado, 'data-mensualidad-id='));
        $this->assertStringNotContainsString('Revertir pago', $filtrado);
        $this->assertStringNotContainsString('Marzo', $filtrado);
        $this->assertStringContainsString('No hay mensualidades', $this->detalles($this->sin)->assertOk()->json('html'));
    }

    public function test_todos_los_filtros_se_mantienen_en_controles_y_enlaces(): void
    {
        $filtros = ['curso_id' => $this->curso->id, 'q' => 'Rodriguez', 'mes' => 'Enero', 'estado' => 'Pagado'];
        $response = $this->listado($filtros)->assertOk();
        $dom = new \DOMDocument(); @$dom->loadHTML($response->getContent()); $xpath = new \DOMXPath($dom);
        foreach (['filtrarCurso' => (string) $this->curso->id, 'filtrarMes' => 'Enero', 'filtrarEstado' => 'Pagado'] as $id => $valor) {
            $this->assertSame($valor, $xpath->evaluate('string(//*[@id="'.$id.'"]/option[@selected]/@value)'));
        }
        parse_str(parse_url($response->viewData('listado')->url(2), PHP_URL_QUERY), $query);
        foreach ($filtros + ['anio' => 2026] as $key => $valor) $this->assertSame((string) $valor, $query[$key]);
    }

    public function test_el_alumno_con_dos_cursos_conserva_el_alcance_original_de_sus_mensualidades(): void
    {
        Inscripcion::factory()->create(['curso_id' => $this->otroCurso->id, 'alumno_id' => $this->varios->id, 'estado' => 'aprobada', 'activa' => true]);
        $response = $this->listado(['q' => $this->varios->codigo])->assertOk();
        $this->assertSame(2, $response->viewData('listado')->total());
        foreach ($response->viewData('listado') as $fila) $this->assertSame(3, (int) $fila->registros);
        $a = $this->detalles($this->varios)->assertOk()->json('html');
        $b = $this->detalles($this->varios, [], $this->otroCurso)->assertOk()->json('html');
        preg_match_all('/data-mensualidad-id="(\d+)"/', $a, $idsA);
        preg_match_all('/data-mensualidad-id="(\d+)"/', $b, $idsB);
        $this->assertCount(3, $idsA[1]);
        $this->assertSame($idsA[1], $idsB[1]);
    }

    public function test_detalles_paginados_con_meses_numericos_y_orden_cronologico(): void
    {
        foreach (['12','02','9','4','06','11','05','7','08','10'] as $mes) $this->mensualidad($this->uno, $mes, 'Pendiente', 25);
        $pagina1 = $this->detalles($this->uno)->assertOk()->json('html');
        $pagina2 = $this->detalles($this->uno, ['mensualidad_page' => 2])->assertOk()->json('html');
        $this->assertSame(6, substr_count($pagina1, 'data-mensualidad-id='));
        $this->assertSame(5, substr_count($pagina2, 'data-mensualidad-id='));
        $this->assertLessThan(strpos($pagina1, 'Febrero'), strpos($pagina1, 'Enero'));
        $this->assertStringContainsString('mensualidad_page=2', $pagina1);
        $filtrado = $this->detalles($this->uno, ['mes' => 'Febrero'])->assertOk()->json('html');
        $this->assertSame(1, substr_count($filtrado, 'data-mensualidad-id='));
        $this->listado(['mes' => 'Febrero'])->assertViewHas('listado', fn ($p) => $p->total() === 2);
    }

    public function test_paginacion_de_alumnos_estable_preserva_filtros_y_no_duplica(): void
    {
        for ($i = 0; $i < 27; $i++) $this->inscribir('Prueba', 'Paginacion');
        $filtros = ['q' => 'Prueba Paginacion', 'curso_id' => $this->curso->id];
        $a = $this->listado($filtros)->assertOk()->viewData('listado');
        $b = $this->listado($filtros + ['page' => 2])->assertOk()->viewData('listado');
        $this->assertSame(25, $a->count()); $this->assertSame(2, $b->count());
        $ids = array_merge($a->pluck('alumno_id')->all(), $b->pluck('alumno_id')->all());
        $this->assertCount(27, array_unique($ids)); $ordenados = $ids; sort($ordenados); $this->assertSame($ordenados, $ids);
        parse_str(parse_url($a->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('2026', $query['anio']); $this->assertSame($filtros['q'], $query['q']);
    }

    public function test_consultas_no_crecen_por_alumno_y_las_consultas_no_escriben(): void
    {
        DB::enableQueryLog(); DB::flushQueryLog(); $this->listado()->assertOk(); $cantidad = count(DB::getQueryLog()); DB::disableQueryLog();
        for ($i = 0; $i < 10; $i++) $this->inscribir('Carga', 'Prueba');
        DB::enableQueryLog(); DB::flushQueryLog(); $this->listado()->assertOk();
        $this->assertLessThanOrEqual($cantidad + 1, count(DB::getQueryLog())); DB::disableQueryLog();
        $tablas = ['mensualidades','pagos','alumnos','cursos','inscripciones','notas','boletas']; $antes = [];
        foreach ($tablas as $tabla) $antes[$tabla] = DB::table($tabla)->get()->toJson();
        DB::connection()->beforeExecuting(function (string $sql): void { $this->assertMatchesRegularExpression('/^\s*(select|pragma)\b/i', $sql); });
        $this->listado()->assertOk(); $this->detalles($this->varios)->assertOk();
        foreach ($tablas as $tabla) $this->assertSame($antes[$tabla], DB::table($tabla)->get()->toJson());
    }

    public function test_permisos_y_contextos_de_curso_se_validan_en_el_servidor(): void
    {
        $this->detalles($this->varios, [], $this->otroCurso)->assertNotFound();
        $this->detalles($this->varios, ['curso_id' => $this->otroCurso->id])->assertNotFound();
        $this->detalles($this->varios, ['q' => 'No coincide'])->assertNotFound();
        foreach (['docente','alumno'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->listado()->assertForbidden(); $this->detalles($this->varios)->assertForbidden();
        }
        $this->app['auth']->guard()->logout(); $this->detalles($this->varios)->assertUnauthorized();
    }

    public function test_generacion_y_cobro_en_lote_siguen_accesibles_sin_ejecutarlos(): void
    {
        $this->listado()->assertOk()->assertSee(route('admin.mensualidades.generar'), false)
            ->assertSee(route('admin.mensualidades.pagarLote'), false)->assertSee('Generar mensualidades')
            ->assertSee('id="radioCurso"', false)->assertSee('id="radioUno"', false);
        $this->get(route('admin.mensualidades.corta', ['anio' => 2026]))->assertOk();
    }
}
