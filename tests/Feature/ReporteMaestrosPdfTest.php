<?php

namespace Tests\Feature;

use App\Models\{AsignacionDocente, Curso, DetalleCurso, Maestro, Materia, User};
use App\Services\ReportesAdministrativos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReporteMaestrosPdfTest extends TestCase
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

    private function asignar(Maestro $maestro, Curso $curso, string $estado = 'activa', array $atributos = []): AsignacionDocente
    {
        $d = DetalleCurso::factory()->create(['maestro_id' => $maestro->id, 'curso_id' => $curso->id]);
        return AsignacionDocente::create($atributos + ['maestro_id' => $maestro->id, 'detalle_curso_id' => $d->id, 'estado' => $estado]);
    }

    private function datos(array $filtros = []): array
    {
        return app(ReportesAdministrativos::class)->maestrosPdf($filtros);
    }

    public function test_descarga_vacia_privada_y_nombre(): void
    {
        $this->travelTo(now()->startOfSecond());
        $r = $this->get(route('admin.reportes.maestros.pdf'))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private')
            ->assertDownload('reporte-maestros-asignaciones-'.now()->timezone('America/El_Salvador')->format('Y-m-d-His').'.pdf');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
        $datos = $this->datos();
        $this->assertSame(0, $datos['totalDocentes']);
        $this->assertSame(0, $datos['totalActivas']);
        $html = view('admin.reportes.pdf.maestros', $datos + ['emitido' => now(), 'filtros' => []])->render();
        $this->assertStringContainsString('No hay registros', $html);
        $this->assertStringContainsString('No definido', $html);
        $this->assertStringNotContainsString('analizar', $html);
    }

    public function test_permisos_y_validacion(): void
    {
        auth()->forgetGuards();
        $this->get(route('admin.reportes.maestros.pdf'))->assertRedirect(route('login'));
        foreach (['alumno', 'docente', 'director', 'maestro'] as $rol) {
            $this->actingAs(User::factory()->make(['role' => $rol]));
            $this->get(route('admin.reportes.maestros.pdf'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['estado' => 'pendiente', 'curso_id' => -1, 'page' => 0] as $campo => $valor) {
            $this->getJson(route('admin.reportes.maestros.pdf', [$campo => $valor]))->assertUnprocessable()->assertJsonValidationErrors($campo);
        }
        $this->post(route('admin.reportes.maestros.pdf'))->assertStatus(405);
    }

    public function test_materias_fechas_historial_y_docentes_sin_asignacion(): void
    {
        $maestro = Maestro::factory()->create();
        Maestro::factory()->create();
        $curso = Curso::factory()->create(['anio_lectivo' => 2024]);
        $a = $this->asignar($maestro, $curso, 'activa', ['fecha_inicio' => '2024-02-03', 'motivo_cambio' => 'Observación verificada']);
        $b = $this->asignar($maestro, $curso);
        $this->asignar($maestro, $curso, 'programada', ['motivo_cambio' => 'PROGRAMADA EXCLUIDA']);
        $this->asignar($maestro, $curso, 'finalizada', ['motivo_cambio' => 'FINALIZADA EXCLUIDA']);
        $r = $this->datos();
        $this->assertSame(2, $r['totalDocentes']);
        $this->assertSame(2, $r['totalActivas']);
        $this->assertCount(3, $r['detalle']);
        $this->assertEquals([2024], $r['anios']->all());
        $texto = $r['detalle']->flatten()->implode(' ');
        $this->assertStringContainsString($a->detalleCurso->materia->nombre, $texto);
        $this->assertStringContainsString($b->detalleCurso->materia->nombre, $texto);
        $this->assertStringContainsString('03/02/2024', $texto);
        $this->assertStringContainsString('Observación verificada', $texto);
        $this->assertStringContainsString('Sin asignaciones activas', $texto);
        $this->assertStringNotContainsString('PROGRAMADA EXCLUIDA', $texto);
        $this->assertStringNotContainsString('FINALIZADA EXCLUIDA', $texto);
        $filaSinFecha = $r['detalle']->first(fn ($fila) => str_contains($fila[1], $b->detalleCurso->materia->nombre));
        $this->assertSame('—', $filaSinFecha[3]);
    }

    public function test_filtros_coinciden_con_pantalla_y_totales_sin_duplicar_docentes(): void
    {
        $m = Maestro::factory()->create();
        $otro = Maestro::factory()->create(['activo' => false]);
        $c = Curso::factory()->create();
        $this->asignar($m, $c);
        $this->asignar($m, $c);
        $this->asignar($otro, $c);
        $this->asignar($m, Curso::factory()->create(), 'activa', ['motivo_cambio' => 'CURSO EXCLUIDO']);
        $f = ['curso_id' => $c->id, 'estado' => 'activo'];
        $pantalla = $this->get(route('admin.reportes.show', ['tipo' => 'maestros'] + $f))->assertOk();
        $r = $this->datos($f);
        $this->assertSame($pantalla->viewData('reporte')['filas']->total(), $r['totalDocentes']);
        $this->assertSame(1, $r['totalDocentes']);
        $this->assertSame(2, $r['totalActivas']);
        $this->assertStringNotContainsString('CURSO EXCLUIDO', $r['detalle']->flatten()->implode(' '));
        $pantalla->assertSee(route('admin.reportes.maestros.pdf', $f));
        $this->get(route('admin.reportes.maestros.pdf', $f))->assertOk();
        $inactivos = $this->datos(['estado' => 'inactivo']);
        $this->assertSame(1, $inactivos['totalDocentes']);
        $this->assertStringContainsString('Docente inactivo', $inactivos['detalle']->flatten()->implode(' '));
    }

    public function test_inconsistencias_se_senalan_sin_reescribir_estado_ni_fechas(): void
    {
        $m = Maestro::factory()->create();
        $c = Curso::factory()->create(['activo' => false]);
        $a = $this->asignar($m, $c);
        $a->detalleCurso->update(['maestro_id' => null]);
        $a->detalleCurso->materia->update(['activa' => false]);
        $r = $this->datos();
        $this->assertSame(1, $r['totalActivas']);
        $this->assertSame('Activa', $r['detalle'][0][2]);
        $this->assertSame('—', $r['detalle'][0][3]);
        $this->assertStringContainsString('Curso no disponible o inactivo', $r['detalle'][0][4]);
        $this->assertStringContainsString('Materia no disponible o inactiva', $r['detalle'][0][4]);
    }

    public function test_multipagina_textos_largos_escape_y_consultas_solo_lectura(): void
    {
        $m = Maestro::factory()->create(['nombre' => '<script>alert(1)</script>']);
        $c = Curso::factory()->create();
        $this->asignar($m, $c, 'activa', ['motivo_cambio' => str_repeat('Observación extensa. ', 70)]);
        Maestro::factory()->count(40)->create();
        DB::enableQueryLog();
        $r = $this->datos();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) $this->assertMatchesRegularExpression('/^\s*(select|pragma)/i', $query['query']);
        $this->assertSame(41, $r['totalDocentes']);
        $this->assertSame(1, $r['totalActivas']);
        $html = view('admin.reportes.pdf.maestros', $r + ['emitido' => now(), 'filtros' => []])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString($m->dui, $html);
        $pdf = Pdf::loadHTML($html)->setPaper('letter');
        $pdf->render();
        $this->assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
    }
}
