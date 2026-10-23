<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, Inscripcion, User};
use App\Services\ReportesAdministrativos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReporteAlumnosPdfTest extends TestCase
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

    private function inscripcion(string $fecha, string $estado = 'aprobada', ?Alumno $a = null, ?Curso $c = null): Inscripcion
    {
        return Inscripcion::factory()->create(['alumno_id' => ($a ?? Alumno::factory()->create())->id,
            'curso_id' => ($c ?? Curso::factory()->create())->id, 'fecha_inscripcion' => $fecha,
            'estado' => $estado, 'activa' => $estado === 'aprobada']);
    }

    private function pantalla(array $f = [])
    {
        return $this->get(route('admin.reportes.show', ['tipo' => 'alumnos'] + $f))->assertOk();
    }

    public function test_periodo_se_aplica_a_alumnos_cursos_y_fechas_con_la_misma_poblacion(): void
    {
        $a = Alumno::factory()->create();
        $junio = Curso::factory()->create(['nombre' => 'Curso incluido junio']);
        $julio = Curso::factory()->create(['nombre' => 'Curso fuera julio']);
        $this->inscripcion('2026-06-01', 'aprobada', $a, $junio);
        $this->inscripcion('2026-07-01', 'aprobada', $a, $julio);
        $this->inscripcion('2026-06-30');
        $this->inscripcion('2025-06-15');
        foreach (['pendiente', 'rechazada', 'inactiva'] as $estado) $this->inscripcion('2026-06-15', $estado);
        $servicio = app(ReportesAdministrativos::class);
        $f = ['mes' => 6, 'anio' => 2026];
        $pantalla = $this->pantalla($f);
        $pdf = $servicio->alumnosPdf($f);
        $this->assertSame(2, $pdf['totalAlumnos']);
        $this->assertSame($pantalla->viewData('reporte')['filas']->items(), $pdf['filas']->all());
        $fila = $pdf['filas']->first(fn ($fila) => $fila[0] === $a->codigo);
        $this->assertStringContainsString($junio->nombre, $fila[2]);
        $this->assertStringNotContainsString($julio->nombre, $fila[2]);
        $this->assertSame('01/06/2026', $fila[5]);
        $this->assertSame(2, $servicio->alumnosPdf(['anio' => 2026])['totalAlumnos']);
        // El alumno con junio y julio sigue siendo una sola fila.
        $this->assertSame(3, $servicio->alumnosPdf([])['totalAlumnos']);
        $this->assertSame(0, $servicio->alumnosPdf(['anio' => 2024])['totalAlumnos']);
    }

    public function test_estado_del_alumno_y_curso_no_se_confunden_con_estado_de_solicitud(): void
    {
        $activo = Alumno::factory()->create(['activo' => true]);
        $inactivo = Alumno::factory()->create(['activo' => false]);
        $curso = Curso::factory()->create();
        $this->inscripcion('2026-06-10', 'aprobada', $activo, $curso);
        $this->inscripcion('2026-06-11', 'aprobada', $inactivo);
        $s = app(ReportesAdministrativos::class);
        $this->assertSame([$activo->codigo], $s->alumnosPdf(['curso_id' => $curso->id])['filas']->pluck(0)->all());
        $this->assertSame([$inactivo->codigo], $s->alumnosPdf(['estado' => 'inactivo'])['filas']->pluck(0)->all());
        $this->pantalla(['estado' => 'activo', 'anio' => 2026, 'mes' => 6])->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 1);
    }

    public static function filtrosInvalidos(): array
    {
        return [[['mes' => 6]], [['mes' => 13, 'anio' => 2026]], [['mes' => 0, 'anio' => 2026]],
            [['anio' => 1899]], [['anio' => 'incorrecto']], [['estado' => 'aprobada']], [['curso_id' => 999999]]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('filtrosInvalidos')]
    public function test_validacion_compartida_de_pantalla_y_pdf(array $f): void
    {
        $this->getJson(route('admin.reportes.show', ['tipo' => 'alumnos'] + $f))->assertUnprocessable();
        $this->getJson(route('admin.reportes.alumnos.pdf', $f))->assertUnprocessable();
    }

    public function test_descarga_exporta_todos_los_resultados_y_conserva_filtros(): void
    {
        $curso = Curso::factory()->create(['nombre' => 'Curso de prueba institucional']);
        for ($i = 0; $i < 26; $i++) {
            $alumno = Alumno::factory()->create(['nombre' => 'Alumno de prueba '.($i + 1), 'apellido' => 'Aguilares', 'activo' => true]);
            $this->inscripcion('2026-06-15', 'aprobada', $alumno, $curso);
        }
        $f = ['curso_id' => $curso->id, 'estado' => 'activo', 'mes' => 6, 'anio' => 2026];
        $pagina = $this->pantalla($f);
        $this->assertCount(25, $pagina->viewData('reporte')['filas']);
        $pagina->assertSee(e(route('admin.reportes.alumnos.pdf', $pagina->viewData('filtros'))), false);
        $this->pantalla($f + ['page' => 2])->assertViewHas('reporte', fn ($r) => $r['filas']->count() === 1);
        $wrapper = app('dompdf.wrapper');
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($vista, $datos) use ($wrapper) {
            $this->assertSame('admin.reportes.pdf.alumnos', $vista);
            $this->assertSame(26, $datos['totalAlumnos']);
            $this->assertCount(26, $datos['filas']);
            $this->assertSame(6, (int) $datos['filtros']['mes']);
            $this->assertArrayNotHasKey('page', $datos['filtros']);
            return $wrapper->loadHTML(view($vista, $datos)->render());
        });
        $r = $this->get(route('admin.reportes.alumnos.pdf', $f + ['page' => 2]))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
        if (getenv('ITM_ALUMNOS_PDF_VISUAL') === '1') {
            $directorio = base_path('storage/app/reportes-referencia');
            if (!is_dir($directorio)) mkdir($directorio, 0700, true);
            file_put_contents($directorio.'/alumnos-inscritos-prueba.pdf', $r->getContent());
        }
    }

    public function test_pdf_vacio_permisos_y_solicitudes_originales_se_conservan(): void
    {
        $this->get(route('admin.reportes.alumnos.pdf', ['anio' => 2024]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $datos = app(ReportesAdministrativos::class)->alumnosPdf([]);
        $html = view('admin.reportes.pdf.alumnos', $datos + ['filtros' => [], 'emitido' => now(), 'responsable' => 'Administrador'])->render();
        $this->assertStringContainsString('No hay registros para los filtros seleccionados.', $html);
        $this->assertStringContainsString('no acredita cuándo se aprobó', $html);
        $menu = $this->get(route('admin.reportes'))->assertOk()->assertDontSee('Reporte de inscripciones: consultar solicitudes');
        $dom = new \DOMDocument(); @$dom->loadHTML($menu->getContent());
        $this->assertSame(5, (new \DOMXPath($dom))->query('//nav[@aria-label="Tipos de reporte"]/a')->length);
        $this->assertSame(url('/admin/reportes/inscripciones/pdf'), route('admin.reportes.inscripciones.pdf'));
        $this->get(route('admin.solicitudes'))->assertOk()->assertSee(route('admin.reportes.inscripciones.pdf'), false);
        $this->get(route('admin.reportes.inscripciones.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->post(route('admin.reportes.alumnos.pdf'))->assertStatus(405);
        foreach (['alumno', 'docente', 'director', 'maestro'] as $rol) {
            $this->actingAs(User::factory()->make(['role' => $rol]));
            $this->get(route('admin.reportes.alumnos.pdf'))->assertForbidden();
        }
        auth()->forgetGuards();
        $this->get(route('admin.reportes.alumnos.pdf'))->assertRedirect(route('login'));
    }
}
