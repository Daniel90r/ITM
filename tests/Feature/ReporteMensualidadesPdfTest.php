<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, Mensualidad, Pago, User};
use App\Services\ReportesAdministrativos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReporteMensualidadesPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function datos(array $f = []): array
    {
        return app(ReportesAdministrativos::class)->mensualidadesPdf($f);
    }

    private function html(array $f = []): string
    {
        return view('admin.reportes.pdf.mensualidades', $this->datos($f) + [
            'filtros' => $f, 'emitido' => now(), 'responsable' => auth()->user()->name,
        ])->render();
    }

    private function pago(Mensualidad $mensualidad): Pago
    {
        return Pago::create(['mensualidad_id' => $mensualidad->id, 'fecha_pago' => now(), 'monto_pagado' => $mensualidad->monto]);
    }

    public function test_descarga_real_vacio_privacidad_y_nombre(): void
    {
        $this->travelTo(now()->startOfSecond());
        $r = $this->get(route('admin.reportes.mensualidades.pdf'))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private')
            ->assertDownload('estado-pagos-mensualidades-'.now()->timezone('America/El_Salvador')->format('Y-m-d-His').'.pdf');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
        $this->assertSame(0, $this->datos()['totalAlumnos']);
        $this->assertSame(0, $this->datos()['pendienteCentavos']);
        $this->assertStringContainsString('No hay registros', $this->html());
        $this->assertStringNotContainsString('Ejemplo:', $this->html());
        $this->assertStringNotContainsString('No sabe explicar', $this->html());
    }

    public function test_permisos_y_filtros_invalidos(): void
    {
        auth()->forgetGuards();
        $this->get(route('admin.reportes.mensualidades.pdf'))->assertRedirect(route('login'));
        foreach (['alumno', 'docente', 'director', 'maestro'] as $rol) {
            $this->actingAs(User::factory()->make(['role' => $rol]));
            $this->get(route('admin.reportes.mensualidades.pdf'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['estado' => 'Revertido', 'mes' => 13, 'anio' => 1800, 'curso_id' => -1] as $campo => $valor) {
            $this->getJson(route('admin.reportes.mensualidades.pdf', [$campo => $valor]))->assertUnprocessable()->assertJsonValidationErrors($campo);
        }
        $this->post(route('admin.reportes.mensualidades.pdf'))->assertStatus(405);
    }

    public function test_totales_alumnos_unicos_y_centavos_sin_duplicar_pagos(): void
    {
        $alumno = Alumno::factory()->create();
        $curso = Curso::factory()->create();
        $pagada = Mensualidad::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'estado' => 'Pagado', 'monto' => '20.10']);
        $this->pago($pagada);
        Mensualidad::factory()->create(['alumno_id' => $alumno->id, 'curso_id' => $curso->id, 'estado' => 'Pendiente', 'monto' => '10.20']);
        Mensualidad::factory()->create(['estado' => 'Pendiente', 'monto' => '0.10']);
        $r = $this->datos();
        $this->assertCount(3, $r['filas']);
        $this->assertSame(2, $r['totalAlumnos']);
        $this->assertSame(1, $r['pagosRegistrados']);
        $this->assertSame(2, $r['pendientes']);
        $this->assertSame(1030, $r['pendienteCentavos']);
        $this->assertSame(2010, $r['pagadoCentavos']);
        $this->assertSame(0, $r['incidencias']);
        try {
            $this->pago($pagada);
            $this->fail('El esquema debe impedir pagos duplicados por mensualidad.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
        }
        $this->assertSame(1, $this->datos()['pagosRegistrados']);
    }

    public function test_reversion_real_y_movimientos_residuales_no_son_pagos_vigentes(): void
    {
        $m = Mensualidad::factory()->create(['estado' => 'Pagado', 'monto' => '25.00']);
        $this->pago($m);
        $this->assertSame(1, $this->datos()['pagosRegistrados']);
        app(\App\Http\Controllers\Admin\MensualidadController::class)->revertir($m->id);
        $r = $this->datos();
        $this->assertSame(0, $r['pagosRegistrados']);
        $this->assertSame(0, $r['pagadoCentavos']);
        $this->assertSame(2500, $r['pendienteCentavos']);
        $this->assertDatabaseMissing('pagos', ['mensualidad_id' => $m->id]);
        $this->pago($m); // Simula inconsistencia heredada: pago residual con estado pendiente.
        $r = $this->datos();
        $this->assertSame(0, $r['pagosRegistrados']);
        $this->assertSame(1, $r['incidencias']);
        $this->assertSame(2500, $r['pendienteCentavos']);
    }

    public function test_filtros_y_meses_coinciden_con_pantalla(): void
    {
        $curso = Curso::factory()->create();
        foreach (['Febrero', '2', '02'] as $mes) Mensualidad::factory()->create(['curso_id' => $curso->id, 'mes' => $mes, 'anio' => 2026, 'estado' => 'Pendiente', 'monto' => '10.01']);
        Mensualidad::factory()->create(['mes' => 'Marzo', 'anio' => 2025]);
        $f = ['curso_id' => $curso->id, 'anio' => 2026, 'mes' => 2, 'estado' => 'Pendiente'];
        $r = $this->datos($f);
        $pantalla = $this->get(route('admin.reportes.show', ['tipo' => 'mensualidades'] + $f))->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML($pantalla->getContent());
        $enlace = (new \DOMXPath($dom))->query('//a[contains(@href,"/admin/reportes/mensualidades/pdf")]')->item(0);
        $this->assertNotNull($enlace);
        parse_str(parse_url($enlace->getAttribute('href'), PHP_URL_QUERY), $parametros);
        $this->assertEquals($f, $parametros);
        $this->assertSame($pantalla->viewData('reporte')['filas']->total(), $r['filas']->count());
        $this->assertSame(3003, $r['pendienteCentavos']);
        $this->assertSame('$30.03', $pantalla->viewData('reporte')['resumen']['Monto pendiente']);
        $this->get(route('admin.reportes.mensualidades.pdf', $f))->assertOk();
        $this->assertCount(0, $this->datos(['anio' => 2000])['filas']);
    }

    public function test_inconsistencias_se_indican_sin_inventar_parciales(): void
    {
        Mensualidad::factory()->create(['estado' => 'Pagado', 'monto' => '20.00']);
        $m = Mensualidad::factory()->create(['estado' => 'Pagado', 'monto' => '20.00']);
        Pago::create(['mensualidad_id' => $m->id, 'fecha_pago' => now(), 'monto_pagado' => '5.00']);
        $r = $this->datos();
        $this->assertSame(2, $r['incidencias']);
        $this->assertSame(1, $r['pagosRegistrados']);
        $this->assertSame(4000, $r['pagadoCentavos']);
        $this->assertSame(0, $r['pendienteCentavos']);
        $this->assertStringContainsString('discrepancia', $this->html());
    }

    public function test_multipagina_escape_y_solo_lectura(): void
    {
        $alumno = Alumno::factory()->create(['nombre' => '<script>alert(1)</script>']);
        Mensualidad::factory()->count(45)->create(['alumno_id' => $alumno->id, 'monto' => '12.34']);
        DB::enableQueryLog();
        $r = $this->datos();
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($consultas as $consulta) $this->assertMatchesRegularExpression('/^\s*(select|pragma)/i', $consulta['query']);
        $this->assertSame(1, $r['totalAlumnos']);
        $this->assertCount(45, $r['filas']);
        $this->assertSame(55530, $r['pendienteCentavos']);
        $html = $this->html();
        $this->assertStringNotContainsString('<script>', $html);
        $pdf = Pdf::loadHTML($html)->setPaper('letter');
        $pdf->render();
        $this->assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
    }
}
