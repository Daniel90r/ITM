<?php

namespace Tests\Feature;

use App\Models\{Curso, Inscripcion, User};
use App\Services\ReportesAdministrativos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReporteCursosPdfTest extends TestCase
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

    public function test_descarga_real_vacia_privada_y_nombre(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->get(route('admin.reportes.cursos.pdf'))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertDownload('reporte-cursos-'.now()->timezone('America/El_Salvador')->format('Y-m-d-His').'.pdf');
        $r = app(ReportesAdministrativos::class)->cursosPdf([]);
        $this->assertSame(0, $r['totalCursos']);
        $this->assertSame(0, $r['totalInscritos']);
        $this->assertSame(0, $r['totalDisponibles']);
        $this->assertStringContainsString('No hay registros', view('admin.reportes.pdf.cursos', $r + [
            'filtros' => [], 'emitido' => now(),
        ])->render());
    }

    public function test_permisos_y_validacion_compartida(): void
    {
        auth()->forgetGuards();
        $this->get(route('admin.reportes.cursos.pdf'))->assertRedirect(route('login'));
        foreach (['alumno', 'docente', 'director', 'maestro'] as $rol) {
            $this->actingAs(User::factory()->make(['role' => $rol]));
            $this->get(route('admin.reportes.cursos.pdf'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['estado' => 'inventado', 'curso_id' => -1, 'page' => 0] as $filtro => $valor) {
            $this->getJson(route('admin.reportes.cursos.pdf', [$filtro => $valor]))
                ->assertUnprocessable()->assertJsonValidationErrors($filtro);
        }
        $this->post(route('admin.reportes.cursos.pdf'))->assertStatus(405);
    }

    public function test_filtros_resumen_y_datos_coinciden_con_pantalla(): void
    {
        $curso = Curso::factory()->create(['nombre' => 'Curso seleccionado', 'estado' => 'ACTIVO']);
        Curso::factory()->create(['nombre' => 'Curso excluido']);
        Inscripcion::factory()->count(3)->create(['curso_id' => $curso->id, 'estado' => 'aprobada']);
        Inscripcion::factory()->create(['curso_id' => $curso->id, 'estado' => 'pendiente']);
        Inscripcion::factory()->create(['curso_id' => $curso->id, 'estado' => 'rechazada']);
        $f = ['curso_id' => $curso->id, 'estado' => 'ACTIVO'];
        $servicio = app(ReportesAdministrativos::class);
        $pdf = $servicio->cursosPdf($f);
        $pantalla = $servicio->consultar('cursos', $f);
        $this->assertSame($pantalla['filas']->total(), $pdf['totalCursos']);
        $this->assertEquals($pantalla['filas'][0][4], $pdf['totalInscritos']);
        $this->assertEquals($pantalla['filas'][0][5], $pdf['totalDisponibles']);
        $this->assertEquals(3, $pdf['totalInscritos']);
        $this->assertEquals(17, $pdf['totalDisponibles']);
        $this->get(route('admin.reportes.show', ['tipo' => 'cursos'] + $f))
            ->assertOk()->assertSee('Descargar PDF')->assertSee('curso_id='.$curso->id, false);
        $html = view('admin.reportes.pdf.cursos', $pdf + ['filtros' => $f, 'emitido' => now()])->render();
        $this->assertStringContainsString('Curso seleccionado', $html);
        $this->assertStringNotContainsString('Curso excluido', $html);
        $this->assertStringNotContainsString('10 / 5', $html);
        $this->get(route('admin.reportes.cursos.pdf', $f))->assertOk();
    }

    public function test_sobrecupo_no_produce_disponibilidad_negativa(): void
    {
        $curso = Curso::factory()->create();
        Inscripcion::factory()->count(21)->create(['curso_id' => $curso->id, 'estado' => 'aprobada']);
        $r = app(ReportesAdministrativos::class)->cursosPdf([]);
        $this->assertEquals(21, $r['totalInscritos']);
        $this->assertEquals(0, $r['totalDisponibles']);
    }

    public function test_exporta_todas_las_filas_y_repite_encabezados_en_varias_paginas(): void
    {
        Curso::factory()->count(65)->create();
        $r = app(ReportesAdministrativos::class)->cursosPdf([]);
        $this->assertCount(65, $r['filas']);
        $this->assertEquals(1300, $r['totalDisponibles']);
        $pdf = Pdf::loadView('admin.reportes.pdf.cursos', $r + ['filtros' => [], 'emitido' => now()])
            ->setPaper('letter');
        $pdf->render();
        $this->assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $this->get(route('admin.reportes.cursos.pdf', ['page' => 2]))->assertOk();
    }
}
