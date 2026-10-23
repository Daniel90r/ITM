<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ReporteVisorController;
use App\Models\{DetalleCurso, User};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReporteVisorTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_visor_no_genera_pdf_ni_acepta_destinos_externos(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Pdf::shouldReceive('loadView')->never();
        foreach (ReporteVisorController::REPORTES as $tipo => [$titulo, $pdfRoute]) {
            $filtros = $tipo === 'inscripciones' ? ['estado' => 'pendiente', 'q' => '<script>alert(1)</script>'] : ['anio' => 2025];
            $r = $this->get(route('admin.reportes.visor', ['tipo' => $tipo] + $filtros + ['url' => 'https://example.org/privado.pdf', 'path' => '../../.env']))
                ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('X-Content-Type-Options', 'nosniff')->assertSee(e($titulo.' — ITM Aguilares'), false)
                ->assertSee('Imprimir')->assertSee('Volver al reporte')->assertDontSee('https://example.org')->assertDontSee('../../.env');
            $this->assertSame(route($pdfRoute, $filtros), $r->viewData('pdfUrl'));
            $this->assertStringContainsString("frame-ancestors 'self'", $r->headers->get('Content-Security-Policy'));
            $this->assertStringContainsString('anio=2025', $tipo === 'inscripciones' ? 'anio=2025' : $r->viewData('volverUrl'));
        }
        $this->get('/admin/reportes/calificaciones/visor')->assertNotFound();
    }

    public function test_permisos_de_las_seis_paginas(): void
    {
        foreach (['alumno', 'docente'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (array_keys(ReporteVisorController::REPORTES) as $tipo) {
                $this->get(route('admin.reportes.visor', $tipo))->assertForbidden();
            }
        }
        auth()->forgetGuards();
        foreach (array_keys(ReporteVisorController::REPORTES) as $tipo) {
            $this->get(route('admin.reportes.visor', $tipo))->assertRedirect(route('login'));
        }
    }

    public function test_enlaces_filtros_descargas_y_exportacion_sintetica(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $detalle = DetalleCurso::factory()->create();
        $manifest = [];
        $dir = base_path('storage/app/reportes-referencia/visor');
        foreach (ReporteVisorController::REPORTES as $tipo => [$titulo, $pdfRoute]) {
            $filtros = match ($tipo) {
                'asistencias' => ['curso_id' => $detalle->curso_id, 'detalle_curso_id' => $detalle->id, 'modalidad' => 'mensual', 'anio' => 2025, 'mes' => 6, 'estado' => 'permiso'],
                'cursos' => ['modalidad' => 'anual', 'anio' => 2025],
                'alumnos', 'mensualidades' => ['anio' => 2025, 'mes' => 6],
                'maestros' => ['estado' => 'activo'],
                default => ['estado' => 'aprobada', 'q' => 'Prueba ficticia'],
            };
            $url = route('admin.reportes.visor', ['tipo' => $tipo] + $filtros);
            $listado = $tipo === 'inscripciones' ? route('admin.solicitudes', $filtros) : route('admin.reportes.show', ['tipo' => $tipo] + $filtros);
            $html = $this->get($listado)->assertOk()->assertSee('Visualizar PDF')->getContent();
            $dom = new \DOMDocument;@$dom->loadHTML($html);$xpath = new \DOMXPath($dom);
            $link = $xpath->query('//a[contains(@class,"reportes-preview")]')->item(0);
            $this->assertNotNull($link);
            $this->assertSame(parse_url($url, PHP_URL_PATH), parse_url($link->getAttribute('href'), PHP_URL_PATH));
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $actual);
            $this->assertEquals($filtros, $actual);
            $visor = $this->get($url)->assertOk();
            $pdf = $this->get($visor->viewData('pdfUrl'))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertDownload();
            $this->assertStringStartsWith('%PDF-', $pdf->getContent());
            $this->assertEquivalentUrl(route($pdfRoute, $filtros), $visor->viewData('pdfUrl'));
            $this->assertEquivalentUrl($listado, $visor->viewData('volverUrl'));
            $this->assertSame('no-store, private', $pdf->headers->get('Cache-Control'));
            if (getenv('ITM_EXPORT_VISOR_FIXTURES') === '1') {
                if (!is_dir($dir)) mkdir($dir, 0700, true);
                file_put_contents($dir.'/'.$tipo.'.html', $visor->getContent());
                file_put_contents($dir.'/'.$tipo.'.pdf', $pdf->getContent());
                $manifest[$tipo] = ['url' => $visor->viewData('pdfUrl'), 'disposition' => $pdf->headers->get('Content-Disposition'), 'csp' => $visor->headers->get('Content-Security-Policy')];
            }
        }
        if ($manifest) file_put_contents($dir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
    }

    public function test_rutas_pdf_siguen_validando_filtros_y_curso_materia(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (ReporteVisorController::REPORTES as $tipo => [$titulo, $pdfRoute]) {
            $f = $tipo === 'inscripciones' ? ['estado' => 'invalido'] : ['anio' => 'invalido', 'estado' => 'invalido'];
            $visor = $this->get(route('admin.reportes.visor', ['tipo' => $tipo] + $f))->assertOk();
            $this->getJson($visor->viewData('pdfUrl'))->assertUnprocessable();
        }
        $a = DetalleCurso::factory()->create();$b = DetalleCurso::factory()->create();
        $this->getJson(route('admin.asistencias.pdf', ['curso_id' => $a->curso_id, 'detalle_curso_id' => $b->id]))->assertUnprocessable();
    }

    private function assertEquivalentUrl(string $expected, string $actual): void
    {
        $this->assertSame(parse_url($expected, PHP_URL_PATH), parse_url($actual, PHP_URL_PATH));
        parse_str(parse_url($expected, PHP_URL_QUERY) ?? '', $expectedQuery);
        parse_str(parse_url($actual, PHP_URL_QUERY) ?? '', $actualQuery);
        $this->assertEquals($expectedQuery, $actualQuery);
    }
}
