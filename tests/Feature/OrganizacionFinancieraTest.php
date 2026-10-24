<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, Inscripcion, Mensualidad, Pago, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizacionFinancieraTest extends TestCase
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
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 12:00:00'));
    }

    private function cuota(Alumno $alumno, ?Curso $curso = null, array $extra = []): Mensualidad
    {
        return Mensualidad::factory()->create($extra + ['alumno_id' => $alumno->id, 'curso_id' => $curso?->id, 'mes' => 'Enero', 'anio' => 2025,
            'estado' => 'Pendiente', 'monto' => 25, 'fecha_vencimiento' => '2025-01-31']);
    }

    private function pago(Mensualidad $cuota, string $fecha, int $monto = 25): Pago
    {
        $cuota->update(['estado' => 'Pagado']);
        return Pago::create(['mensualidad_id' => $cuota->id, 'fecha_pago' => $fecha, 'monto_pagado' => $monto]);
    }

    public function test_movimientos_solo_pagos_y_curso_historico_sin_inscripcion_vigente(): void
    {
        $alumno = Alumno::factory()->create(['activo' => false]);
        $historico = Curso::factory()->create(['nombre' => 'Historico verificable', 'activo' => false]);
        $actual = Curso::factory()->create(['nombre' => 'Curso actual distinto']);
        Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $actual->id, 'estado' => 'pendiente']);
        $pago = $this->pago($this->cuota($alumno, $historico), '2026-10-10');
        $this->cuota($alumno);
        $this->get(route('admin.pagos', ['alumno_id' => $alumno->id]))->assertOk()->assertSee('Historico verificable')
            ->assertDontSee('Curso actual distinto')->assertSee('Enero / 2025')->assertSee('10/10/2026')
            ->assertViewHas('pagos', fn ($pagos) => $pagos->total() === 1 && $pagos->first()->id === $pago->id);
        $this->get(route('admin.pagos.estado-cuenta', ['alumno' => $alumno->id, 'anio' => 2026]))->assertOk()
            ->assertSee('Pagos realizados durante 2026')->assertSee('data-pago="'.$pago->id.'"', false);
        $this->assertDatabaseCount('pagos', 1);
    }

    public function test_indicadores_y_filtros_usan_mes_y_anio_del_pago_no_de_la_cuota(): void
    {
        $alumno = Alumno::factory()->create();
        foreach (['2026-10-10' => 10, '2025-10-10' => 20, '2026-09-10' => 30] as $fecha => $monto) {
            $this->pago($this->cuota($alumno), $fecha, $monto);
        }
        $this->get(route('admin.pagos'))->assertOk()->assertViewHas('pagosMesActual', 1)
            ->assertViewHas('recaudadoMes', fn ($monto) => (float) $monto === 10.0)
            ->assertViewHas('totalRecaudado', fn ($monto) => (float) $monto === 40.0);
        $this->get(route('admin.pagos', ['anio' => '']))->assertOk()
            ->assertViewHas('totalRecaudado', fn ($monto) => (float) $monto === 60.0);
        $this->get(route('admin.pagos', ['anio' => 2026, 'mes' => 10]))->assertOk()
            ->assertViewHas('pagos', fn ($pagos) => $pagos->total() === 1)
            ->assertViewHas('totalRecaudado', fn ($monto) => (float) $monto === 10.0);
        $this->get(route('admin.pagos', ['anio' => 2025, 'mes' => 10]))->assertOk()->assertViewHas('pagosMesActual', 0)
            ->assertViewHas('pagos', fn ($pagos) => $pagos->total() === 1);
    }

    public function test_paginacion_conserva_filtros_totales_y_no_duplica_por_inscripciones(): void
    {
        $alumno = Alumno::factory()->create();
        foreach (range(1, 2) as $_) Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'estado' => 'aprobada']);
        foreach (range(1, 27) as $_) $this->pago($this->cuota($alumno), '2026-10-10');
        $filtros = ['alumno_id' => $alumno->id, 'q' => $alumno->codigo, 'anio' => 2026, 'mes' => 10];
        $response = $this->get(route('admin.pagos', $filtros))->assertOk()->assertViewHas('totalRecaudado', fn ($monto) => (float) $monto === 675.0);
        $primera = $response->viewData('pagos');
        $segunda = $this->get(route('admin.pagos', $filtros + ['page' => 2]))->assertOk()->viewData('pagos');
        $this->assertSame(27, $primera->total());
        $this->assertSame(25, $primera->count());
        $this->assertSame(2, $segunda->count());
        $this->assertCount(27, $primera->pluck('id')->merge($segunda->pluck('id'))->unique());
        parse_str(parse_url($primera->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertEquals($filtros + ['page' => 2], $query);
    }

    public function test_anio_predeterminado_cambia_con_el_calendario_y_limpiar_restaura_el_actual(): void
    {
        foreach (['2026-12-31 23:59:59' => 2026, '2027-01-01 00:00:00' => 2027] as $fecha => $anio) {
            $this->travelTo(\Carbon\Carbon::parse($fecha, config('app.timezone')));
            $response = $this->get(route('admin.pagos'))->assertOk()
                ->assertViewHas('filtros', fn ($f) => $f['anio'] === $anio)
                ->assertSee('No hay pagos registrados para los filtros seleccionados.');
            $dom = new \DOMDocument(); @$dom->loadHTML($response->getContent()); $xpath = new \DOMXPath($dom);
            $this->assertSame((string) $anio, $xpath->query('//input[@name="anio"]')->item(0)->getAttribute('value'));
            $this->assertSame(route('admin.pagos'), $xpath->query('//a[span="Limpiar"]')->item(0)->getAttribute('href'));
        }
    }

    public function test_anio_anterior_y_todos_los_anios_se_conservan_al_paginar(): void
    {
        $alumno = Alumno::factory()->create();
        foreach (range(1, 26) as $_) $this->pago($this->cuota($alumno), '2025-10-10');
        $this->pago($this->cuota($alumno), '2026-10-10');
        foreach ([2025 => 26, '' => 27] as $anio => $total) {
            $primera = $this->get(route('admin.pagos', ['anio' => $anio]))->assertOk()
                ->assertViewHas('pagos', fn ($p) => $p->total() === $total)->viewData('pagos');
            parse_str(parse_url($primera->nextPageUrl(), PHP_URL_QUERY), $query);
            $this->assertSame((string) $anio, $query['anio']);
            $this->get($primera->nextPageUrl())->assertOk()
                ->assertViewHas('filtros', fn ($f) => (string) $f['anio'] === (string) $anio)
                ->assertViewHas('pagos', fn ($p) => $p->count() === $total - 25);
        }
        $actual = $this->get(route('admin.pagos'))->assertOk()->viewData('pagos');
        $this->assertSame(1, $actual->total());
    }

    public function test_redirecciones_conservan_busqueda_y_anio(): void
    {
        $alumno = Alumno::factory()->create();
        $this->get(route('admin.mensualidades.historial.index', ['q' => 'Nombre ejemplo', 'anio' => 2025, 'page' => 2]))
            ->assertRedirect(route('admin.pagos.historial.index', ['q' => 'Nombre ejemplo', 'anio' => 2025, 'page' => 2]));
        $this->get(route('admin.mensualidades.historial.show', ['alumno' => $alumno->id, 'anio' => 2025]))
            ->assertRedirect(route('admin.pagos.estado-cuenta', ['alumno' => $alumno->id, 'anio' => 2025]));
    }

    public function test_validaciones_permisos_y_no_habilita_solicitudes_pendientes(): void
    {
        foreach ([['alumno_id' => 999999], ['alumno_id' => ['1']], ['mes' => 13], ['anio' => 'invalido'], ['page' => 0]] as $filtro) {
            $this->getJson(route('admin.pagos', $filtro))->assertUnprocessable();
        }
        $alumno = Alumno::factory()->create();
        Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'estado' => 'pendiente']);
        $this->get(route('admin.pagos.historial.index'))->assertOk()->assertViewHas('alumnos', fn ($alumnos) => $alumnos->isEmpty());
        $this->get(route('admin.pagos.estado-cuenta', $alumno))->assertNotFound();
        foreach (['alumno', 'docente'] as $rol) {
            $this->actingAs(User::factory()->create(['role' => $rol]));
            foreach (['admin.pagos', 'admin.pagos.historial.index'] as $ruta) $this->get(route($ruta))->assertForbidden();
            $this->get(route('admin.pagos.estado-cuenta', $alumno))->assertForbidden();
        }
        $this->app['auth']->guard()->logout();
        $this->get(route('admin.pagos'))->assertRedirect(route('login'));
    }

    public function test_registro_individual_y_lote_reutilizan_los_endpoints_existentes(): void
    {
        $alumno = Alumno::factory()->create(['activo' => false]);
        $curso = Curso::factory()->create(['activo' => true]);
        Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'estado' => 'aprobada', 'activa' => true]);
        $uno = $this->cuota($alumno, $curso);
        $dos = $this->cuota($alumno, $curso);
        $this->get(route('admin.pagos.estado-cuenta', ['alumno' => $alumno->id, 'anio' => 2025]))->assertOk()
            ->assertDontSee(route('admin.mensualidades.pagar', $uno->id), false)
            ->assertDontSee(route('admin.mensualidades.pagarLote'), false);
        $this->get(route('admin.mensualidades', ['anio' => 2025]))->assertOk()
            ->assertSee(route('admin.mensualidades.pagarLote'), false)->assertSee('Generar mensualidades');
        $html = $this->getJson(route('admin.mensualidades.detalles', ['alumno' => $alumno->id, 'curso' => $curso->id, 'anio' => 2025]))
            ->assertOk()->json('html');
        $this->assertStringContainsString(route('admin.mensualidades.pagar', $uno->id), $html);
        $this->post(route('admin.mensualidades.pagar', $uno->id))->assertRedirect();
        $this->post(route('admin.mensualidades.pagarLote'), ['ids' => [$dos->id]])->assertRedirect();
        $this->assertDatabaseCount('pagos', 2);
        $this->assertSame('Pagado', $uno->fresh()->estado);
        $this->assertSame('Pagado', $dos->fresh()->estado);
    }

    public function test_todas_las_pantallas_de_pagos_son_de_consulta_incluso_con_parametro_antiguo(): void
    {
        $alumno = Alumno::factory()->create(['activo' => false]);
        $this->cuota($alumno);
        $this->pago($this->cuota($alumno, null, ['mes' => 'Febrero']), '2025-02-10');
        foreach ([route('admin.pagos'), route('admin.pagos.historial.index', ['registrar' => 1]),
            route('admin.pagos.estado-cuenta', ['alumno' => $alumno->id, 'anio' => 2025, 'registrar' => 1])] as $url) {
            $response = $this->get($url)->assertOk()->assertDontSee('Registrar pago')->assertDontSee('Revertir pago');
            $dom = new \DOMDocument(); @$dom->loadHTML($response->getContent()); $xpath = new \DOMXPath($dom);
            $this->assertSame(0, $xpath->query('//input[@type="checkbox"]')->length);
            $this->assertSame(0, $xpath->query('//form[@method="POST" and not(contains(@action,"logout"))]')->length);
            $this->assertSame(0, $xpath->query('//a[contains(@href,"registrar=")]')->length);
        }
        $this->get(route('admin.pagos.estado-cuenta', ['alumno' => $alumno->id, 'anio' => 2025]))->assertOk()
            ->assertViewHas('totalPagos', fn ($total) => (float) $total === 25.0)
            ->assertViewHas('resumen', fn ($r) => $r['pendiente'] === 2500);
        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseCount('mensualidades', 2);
    }

    public function test_textos_del_estado_de_cuenta_personalizan_alumno_y_anio_sin_confundir_periodos(): void
    {
        foreach ([['Ana Isabel', 'Prueba', 2025], ['Luis Alberto', 'Ejemplo', 2026]] as [$nombre, $apellido, $anio]) {
            $alumno = Alumno::factory()->create(['nombre' => $nombre, 'apellido' => $apellido]);
            $this->cuota($alumno, null, ['anio' => $anio]);
            $this->get(route('admin.pagos.estado-cuenta', ['alumno' => $alumno->id, 'anio' => $anio]))
                ->assertOk()->assertSee('Estado de cuenta de '.$alumno->nombre_completo)
                ->assertSee($alumno->codigo)->assertSee('Volver a Estados de cuenta')
                ->assertSee('Año del estado de cuenta')->assertSee('Consultar año')
                ->assertSee('Total pagado durante '.$anio)->assertSee('Total pendiente de pago')
                ->assertSee('Importe vencido')->assertSee('incluido en el total pendiente de pago')
                ->assertSee('Sin mensualidad registrada')->assertSee('no representa una deuda')
                ->assertSee('Curso de la inscripción activa actual')
                ->assertSee('Fecha de referencia del registro de inscripción')
                ->assertSee('Pagos realizados durante '.$anio)
                ->assertDontSee('Fecha oficial de aprobación')->assertDontSee('Registrar pago');
        }
    }

    public function test_movimientos_no_realizan_consultas_por_fila_y_no_escriben(): void
    {
        $alumno = Alumno::factory()->create();
        $this->pago($this->cuota($alumno), '2026-10-10');
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->get(route('admin.pagos'))->assertOk(); $antes = count(DB::getQueryLog());
        DB::disableQueryLog();
        foreach (range(1, 5) as $_) $this->pago($this->cuota($alumno), '2026-10-10');
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->get(route('admin.pagos'))->assertOk();
        $this->assertLessThanOrEqual($antes + 1, count(DB::getQueryLog())); DB::disableQueryLog();
        DB::connection()->beforeExecuting(fn ($sql) => $this->assertMatchesRegularExpression('/^\s*(select|pragma)/i', $sql));
        $this->get(route('admin.pagos'))->assertOk();
    }

    public function test_sugerencias_parciales_por_nombre_apellido_codigo_y_periodo_sin_duplicados(): void
    {
        $ana = Alumno::factory()->create(['nombre' => 'Ana Isabel', 'apellido' => 'Orellana', 'codigo' => 'ITM-ABC-789', 'activo' => false]);
        $this->pago($this->cuota($ana), '2025-10-10');
        $this->pago($this->cuota($ana), '2025-10-11');
        $sinPagos = Alumno::factory()->create(['nombre' => 'Ana Sin pagos']);
        $this->cuota($sinPagos);
        foreach (['aNA', 'RELL', 'abc-7'] as $q) {
            $response = $this->getJson(route('admin.pagos.alumnos', ['q' => $q, 'anio' => 2025, 'mes' => 10]))
                ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(1, 'alumnos');
            $this->assertSame(['id', 'nombre', 'codigo'], array_keys($response->json('alumnos.0')));
            $this->assertSame($ana->id, $response->json('alumnos.0.id'));
        }
        foreach ([['q' => 'Ana'], ['q' => 'Ana', 'anio' => 2025, 'mes' => 9], ['q' => 'inexistente', 'anio' => ''], ['q' => 'A', 'anio' => '']] as $f) {
            $this->getJson(route('admin.pagos.alumnos', $f))->assertOk()->assertJsonCount(0, 'alumnos');
        }
        $this->getJson(route('admin.pagos.alumnos', ['q' => 'ana', 'anio' => '']))->assertOk()->assertJsonCount(1, 'alumnos');
        $this->assertDatabaseCount('pagos', 2);
    }

    public function test_sugerencias_limitadas_y_protegidas_con_validacion(): void
    {
        foreach (range(1, 10) as $i) {
            $a = Alumno::factory()->create(['nombre' => 'Estudiante '.$i]);
            $this->pago($this->cuota($a), '2026-10-10');
        }
        $this->getJson(route('admin.pagos.alumnos', ['q' => 'Est']))->assertOk()->assertJsonCount(8, 'alumnos');
        foreach ([['q' => ['x']], ['q' => str_repeat('x', 101)], ['q' => 'Est', 'anio' => 'mal'], ['q' => 'Est', 'mes' => 13]] as $f) {
            $this->getJson(route('admin.pagos.alumnos', $f))->assertUnprocessable();
        }
        foreach (['alumno', 'docente'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->getJson(route('admin.pagos.alumnos', ['q' => 'Est']))->assertForbidden();
        }
        $this->app['auth']->guard()->logout();
        $this->getJson(route('admin.pagos.alumnos', ['q' => 'Est']))->assertUnauthorized();
    }
}
