<?php

namespace Tests\Feature;

use App\Models\{Alumno, Asistencia, Curso, DetalleCurso, Inscripcion, Maestro, User};
use App\Services\ControlAsistenciaPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ControlAsistenciaPdfTest extends TestCase
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

    private function alumno(DetalleCurso $d, string $estado = 'aprobada'): Alumno
    {
        $a = Alumno::factory()->create();
        Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => $d->curso_id, 'estado' => $estado]);
        return $a;
    }

    private function registro(DetalleCurso $d, Alumno $a, string $fecha, string $estado, array $otros = []): Asistencia
    {
        return Asistencia::create($otros + ['curso_id' => $d->curso_id, 'detalle_curso_id' => $d->id,
            'alumno_id' => $a->id, 'fecha' => $fecha, 'estado' => $estado, 'registrado_por' => $d->maestro_id]);
    }

    private function datos(DetalleCurso $d, array $f = []): array
    {
        return app(ControlAsistenciaPdf::class)->datos($d->load(['curso', 'materia']), $f, Asistencia::query());
    }

    private function filtros(DetalleCurso $d): array
    {
        return ['curso_id' => $d->curso_id, 'detalle_curso_id' => $d->id];
    }

    public static function modalidadesEstado(): array
    {
        return [[''], ['presente'], ['ausente'], ['permiso']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('modalidadesEstado')]
    public function test_pdf_por_estado_comparte_historial_filtros_y_totales(string $estado): void
    {
        $d = DetalleCurso::factory()->create();
        $actual = $this->alumno($d);
        $historico = $this->alumno($d, 'inactiva');
        $idsPorEstado = [];
        foreach (['presente', 'ausente', 'permiso'] as $i => $valor) {
            foreach ([$actual, $historico] as $a) {
                $idsPorEstado[$valor][] = $this->registro($d, $a, '2026-10-0'.($i + 1), $valor)->id;
            }
        }
        $this->registro($d, $actual, '2026-10-04', 'presente');
        $otraMateria = DetalleCurso::factory()->create(['curso_id' => $d->curso_id]);
        $this->registro($otraMateria, $historico, '2026-10-01', 'presente');
        $otroCurso = DetalleCurso::factory()->create();
        $this->registro($otroCurso, $historico, '2026-10-01', 'ausente');
        $f = $this->filtros($d) + ['estado' => $estado, 'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-03'];
        $wrapper = app('dompdf.wrapper');
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($vista, $datos) use ($estado, $idsPorEstado, $actual, $historico, $wrapper, $f) {
            $this->assertSame($estado === '' ? 'admin.reportes.pdf.asistencia' : 'admin.reportes.pdf.asistencia-filtrada', $vista);
            if ($estado !== '') {
                $this->assertEqualsCanonicalizing($idsPorEstado[$estado], $datos['registros']->pluck('id')->all());
                $this->assertEqualsCanonicalizing([$actual->id, $historico->id], $datos['registros']->pluck('alumno_id')->unique()->all());
                $this->assertSame(2, $datos['totales']->get($estado));
                $this->assertSame(2, $datos['totales']->sum());
                $this->assertSame([$estado], $datos['registros']->pluck('estado')->unique()->values()->all());
                $this->assertCount(1, $datos['fechas']);
                $listado = app(\App\Services\ReportesAdministrativos::class)->consultar('asistencias', $f);
                $this->assertSame($listado['filas']->total(), $datos['registros']->count());
            } else {
                $this->assertSame([$actual->id], $datos['alumnos']->pluck('id')->all());
                $this->assertCount(3, $datos['registros']);
                $this->assertSame(1, $datos['totalesAlumno']->sum());
            }
            $html = view($vista, $datos)->render();
            if ($estado !== '') {
                $this->assertStringContainsString('HISTÓRICO · ESTADO:', $html);
                $this->assertStringContainsString('no es el control completo', $html);
                $this->assertStringContainsString(e($historico->nombre_completo), $html);
            }
            return $wrapper->loadHTML($html);
        });
        $respuesta = $this->get(route('admin.asistencias.pdf', $f))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());
        $this->assertStringContainsString($estado === '' ? 'control-asistencia-' : 'asistencias-historicas-'.$estado, $respuesta->headers->get('Content-Disposition'));
    }

    public function test_pdf_estado_vacio_validacion_y_permisos(): void
    {
        $d = DetalleCurso::factory()->create();
        $f = $this->filtros($d) + ['estado' => 'ausente', 'fecha_desde' => '2030-01-01'];
        $wrapper = app('dompdf.wrapper');
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($vista, $datos) use ($wrapper) {
            $this->assertCount(0, $datos['registros']);
            $this->assertSame(0, $datos['totales']->sum());
            $html = view($vista, $datos)->render();
            $this->assertStringContainsString('No hay registros para los filtros seleccionados.', $html);
            $this->assertStringContainsString('Ausente', $html);
            return $wrapper->loadHTML($html);
        });
        $this->get(route('admin.asistencias.pdf', $f))->assertOk();
        foreach (['inventado', ['presente']] as $estado) {
            $this->getJson(route('admin.asistencias.pdf', array_replace($f, ['estado' => $estado])))->assertUnprocessable();
        }
        $this->getJson(route('admin.asistencias.pdf', array_replace($f, ['curso_id' => Curso::factory()->create()->id])))->assertUnprocessable();
        $this->getJson(route('admin.asistencias.pdf', $f + ['fecha_hasta' => '2026-01-01']))->assertUnprocessable();
        foreach (['alumno', 'docente', 'director'] as $rol) {
            $this->actingAs(User::factory()->make(['role' => $rol]));
            foreach (['presente', 'ausente', 'permiso'] as $estado) {
                $this->get(route('admin.asistencias.pdf', array_replace($f, ['estado' => $estado])))->assertForbidden();
            }
        }
        auth()->forgetGuards();
        $this->get(route('admin.asistencias.pdf', $f))->assertRedirect(route('login'));
    }

    public function test_historico_multipagina_conserva_registros_sin_matricula_y_observaciones(): void
    {
        $d = DetalleCurso::factory()->create();
        $d->materia->update(['es_opcional' => true]);
        $a = Alumno::factory()->create(['nombre' => 'Alumno de prueba', 'apellido' => 'Historico']);
        for ($i = 1; $i <= 21; $i++) {
            $this->registro($d, $a, '2026-10-'.str_pad($i, 2, '0', STR_PAD_LEFT), 'ausente',
                ['observacion' => $i === 1 ? 'Observacion historica de prueba' : null]);
        }
        $f = $this->filtros($d) + ['estado' => 'ausente'];
        $consulta = app(\App\Services\FiltrosHistoricosAsistencia::class)->aplicar(Asistencia::query(), $f);
        $datos = app(\App\Services\AsistenciaHistoricaPdf::class)->datos($d->load(['curso', 'materia']), $f, $consulta);
        $this->assertCount(21, $datos['registros']);
        $this->assertCount(21, $datos['registros']->unique('id'));
        $this->assertCount(21, $datos['fechas']);
        $this->assertSame(21, $datos['totales']->get('ausente'));
        $this->assertCount(3, $datos['paginas']);
        $this->assertCount(1, $datos['observaciones']);
        $html = view('admin.reportes.pdf.asistencia-filtrada', $datos + ['filtros' => $f, 'emitido' => now()])->render();
        $pdf = Pdf::loadHTML($html)->setPaper('letter', 'landscape');
        $pdf->render();
        $this->assertSame(4, $pdf->getDomPDF()->getCanvas()->get_page_count());
        if (getenv('ITM_ASISTENCIA_VISUAL') === '1') {
            $directorio = storage_path('app/reportes-referencia');
            if (!is_dir($directorio)) mkdir($directorio, 0700, true);
            $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
            $pdf->getDomPDF()->getCanvas()->page_text(650, 591, 'Página {PAGE_NUM} / {PAGE_COUNT}', $font, 7);
            file_put_contents($directorio.'/asistencia-historica-ausente-prueba.pdf', $pdf->output());
        }
    }

    public function test_descarga_vacia_y_permisos(): void
    {
        $d = DetalleCurso::factory()->create();
        $f = $this->filtros($d);
        $this->travelTo(now()->startOfSecond());
        $r = $this->get(route('admin.asistencias.pdf', $f))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private')
            ->assertDownload('control-asistencia-'.$d->curso_id.'-'.$d->id.'-'.now()->timezone('America/El_Salvador')->format('Y-m-d-His').'.pdf');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
        $this->assertCount(0, $this->datos($d)['alumnos']);
        auth()->forgetGuards();
        $this->get(route('admin.asistencias.pdf', $f))->assertRedirect(route('login'));
        foreach (['alumno', 'docente', 'maestro', 'director'] as $rol) {
            $this->actingAs(User::factory()->make(['role' => $rol]));
            $this->get(route('admin.asistencias.pdf', $f))->assertForbidden();
        }
    }

    public function test_validacion_y_relaciones_fuera_del_curso(): void
    {
        $d = DetalleCurso::factory()->create();
        $this->getJson(route('admin.asistencias.pdf'))->assertUnprocessable();
        $this->getJson(route('admin.asistencias.pdf', ['curso_id' => Curso::factory()->create()->id, 'detalle_curso_id' => $d->id]))->assertUnprocessable();
        $this->getJson(route('admin.asistencias.pdf', $this->filtros($d) + ['fecha_desde' => '2026-10-20', 'fecha_hasta' => '2026-10-01']))->assertUnprocessable();
        $this->getJson(route('admin.asistencias.pdf', $this->filtros($d) + ['maestro_id' => Maestro::factory()->create()->id]))->assertUnprocessable();
        $this->post(route('admin.asistencias.pdf', $this->filtros($d)))->assertStatus(405);
    }

    public function test_estados_sin_registro_totales_y_aislamiento_por_materia(): void
    {
        $d = DetalleCurso::factory()->create();
        $a = $this->alumno($d);
        $b = $this->alumno($d);
        $rechazado = $this->alumno($d, 'rechazada');
        $this->registro($d, $a, '2026-10-01', 'presente');
        $this->registro($d, $a, '2026-10-02', 'ausente');
        $this->registro($d, $b, '2026-10-02', 'permiso');
        $this->registro($d, $rechazado, '2026-10-03', 'presente');
        $otra = DetalleCurso::factory()->create(['curso_id' => $d->curso_id]);
        $this->registro($otra, $a, '2026-10-04', 'presente');
        Asistencia::create(['curso_id' => $d->curso_id, 'alumno_id' => $a->id, 'fecha' => '2026-10-05', 'estado' => 'presente', 'registrado_por' => $d->maestro_id]);
        $r = $this->datos($d);
        $this->assertCount(2, $r['alumnos']);
        $this->assertSame(['2026-10-01', '2026-10-02'], $r['fechas']->all());
        $this->assertSame('P', $r['marcas'][$a->id.'|2026-10-01']);
        $this->assertSame('A', $r['marcas'][$a->id.'|2026-10-02']);
        $this->assertSame('PM', $r['marcas'][$b->id.'|2026-10-02']);
        $this->assertFalse($r['marcas']->has($b->id.'|2026-10-01'));
        $this->assertSame(1, $r['totalesAlumno']->sum());
        $this->assertSame(1, $r['totalesFecha']->sum());
        $this->assertSame(0, $r['duplicadas']);
    }

    public function test_materia_opcional_respeta_asignacion_en_ese_curso(): void
    {
        $d = DetalleCurso::factory()->create();
        $d->materia->update(['es_opcional' => true]);
        $a = $this->alumno($d);
        $b = $this->alumno($d);
        DB::table('alumno_materia')->insert(['alumno_id' => $a->id, 'curso_id' => $d->curso_id, 'materia_id' => $d->materia_id]);
        DB::table('alumno_materia')->insert(['alumno_id' => $b->id, 'curso_id' => Curso::factory()->create()->id, 'materia_id' => $d->materia_id]);
        $this->assertSame([$a->id], $this->datos($d)['alumnos']->pluck('id')->all());
    }

    public function test_periodo_docente_busqueda_y_boton_se_conservan(): void
    {
        $d = DetalleCurso::factory()->create();
        $a = $this->alumno($d);
        $a->update(['nombre' => 'Elegido', 'apellido' => 'Alumno']);
        $this->registro($d, $a, '2026-10-01', 'presente');
        $this->registro($d, $a, '2026-10-02', 'ausente');
        $f = $this->filtros($d) + ['fecha_desde' => '2026-10-02', 'fecha_hasta' => '2026-10-02', 'maestro_id' => $d->maestro_id, 'q' => 'Elegido'];
        $this->get(route('admin.asistencias', $f))->assertOk()->assertSee('Descargar PDF');
        $pdf = app('dompdf.wrapper')->loadHTML('<p>Prueba</p>');
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($vista, $datos) use ($a) {
            $this->assertSame('admin.reportes.pdf.asistencia', $vista);
            $this->assertSame(['2026-10-02'], $datos['fechas']->all());
            $this->assertSame([$a->id], $datos['alumnos']->pluck('id')->all());
            $this->assertSame(0, $datos['totalesAlumno']->sum());
            return true;
        })->andReturn($pdf);
        $this->get(route('admin.asistencias.pdf', $f))->assertOk();
    }

    public function test_muchas_fechas_alumnos_observaciones_y_privacidad(): void
    {
        $d = DetalleCurso::factory()->create();
        $a = $this->alumno($d);
        $a->update(['nombre' => '<script>privado</script>', 'dui' => '12345678-9']);
        for ($i = 1; $i <= 25; $i++) $this->registro($d, $a, '2026-10-'.str_pad($i, 2, '0', STR_PAD_LEFT), 'presente', ['observacion' => $i === 1 ? '<b>Observación</b>' : null]);
        for ($i = 0; $i < 18; $i++) $this->alumno($d);
        DB::enableQueryLog();
        $r = $this->datos($d);
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($consultas as $sql) $this->assertMatchesRegularExpression('/^\s*(select|pragma)/i', $sql['query']);
        $this->assertSame(25, $r['totalesAlumno']->sum());
        $this->assertCount(25, $r['fechas']);
        $this->assertCount(6, $r['paginas']);
        foreach ($r['paginas'] as $pagina) $this->assertLessThanOrEqual(12, $pagina['fechas']->count());
        $html = view('admin.reportes.pdf.asistencia', $r + ['filtros' => $this->filtros($d), 'emitido' => now()])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>Observación</b>', $html);
        $this->assertStringNotContainsString('12345678-9', $html);
        $pdf = Pdf::loadHTML($html)->setPaper('letter', 'landscape');
        $pdf->render();
        $this->assertSame(6, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
    }

    public function test_presentacion_institucional_nombres_largos_y_anexo(): void
    {
        $d = DetalleCurso::factory()->create();
        $d->curso->update(['nombre' => 'MUESTRA DE PRUEBA - Curso de computacion']);
        $d->materia->update(['nombre' => 'MUESTRA DE PRUEBA - Aplicaciones de oficina']);
        $primero = null;
        for ($i = 1; $i <= 11; $i++) {
            $a = $this->alumno($d);
            $a->update(['nombre' => 'Alumno de prueba '.$i.' Maria Alejandra',
                'apellido' => 'Hernandez de los Angeles Rodriguez Martinez']);
            $primero ??= $a;
        }
        for ($i = 1; $i <= 13; $i++) {
            $this->registro($d, $primero, '2026-10-'.str_pad($i, 2, '0', STR_PAD_LEFT),
                ['presente', 'ausente', 'permiso'][($i - 1) % 3],
                ['observacion' => $i <= 6 ? str_repeat('Observacion de prueba para comprobar lectura completa. ', 6) : null]);
        }
        $datos = $this->datos($d);
        $html = view('admin.reportes.pdf.asistencia', $datos + [
            'filtros' => $this->filtros($d) + ['fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-31'],
            'emitido' => now(),
        ])->render();
        foreach (['Instituto de Computación de Aguilares', 'CONTROL DE ASISTENCIA',
            'Firma del Director', 'Firma del Instructor', 'ANEXO', $primero->nombre_completo] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertStringContainsString('height: auto', $html);
        $this->assertSame(5, $datos['totalesAlumno']->sum());
        $advertencia = view('admin.reportes.pdf.asistencia', array_replace($datos, [
            'duplicadas' => 1, 'filtros' => $this->filtros($d), 'emitido' => now(),
        ]))->render();
        $this->assertStringContainsString('no suman asistencia hasta su revisión', $advertencia);
        $pdf = Pdf::loadHTML($html)->setPaper('letter', 'landscape');
        $cajasTexto = [];
        $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use (&$cajasTexto) {
            if ($frame->get_node()->nodeName === '#text' && trim($frame->get_node()->nodeValue) !== '') {
                $cajasTexto[] = $frame->get_border_box();
            }
        }]]);
        $pdf->render();
        $this->assertNotEmpty($cajasTexto);
        foreach ($cajasTexto as $caja) {
            $this->assertGreaterThanOrEqual(29, $caja['x']);
            $this->assertLessThanOrEqual(764, $caja['x'] + $caja['w']);
            $this->assertGreaterThanOrEqual(10, $caja['y']);
            $this->assertLessThanOrEqual(602, $caja['y'] + $caja['h']);
        }
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $pdf->getDomPDF()->getCanvas()->page_text(650, 591, 'Página {PAGE_NUM} / {PAGE_COUNT}', $font, 7);
        $bytes = $pdf->output();
        $this->assertStringStartsWith('%PDF-', $bytes);
        // Exportacion opcional de fixtures sinteticos para inspeccion visual local.
        if (getenv('ITM_ASISTENCIA_VISUAL') === '1') {
            $directorio = storage_path('app/reportes-referencia');
            if (!is_dir($directorio)) mkdir($directorio, 0700, true);
            file_put_contents($directorio.'/asistencia-diseno-prueba.pdf', $bytes);
        }
        // Tres grupos por bloque de fechas (6 + 4 + 1 alumnos) y un anexo.
        $this->assertSame(7, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }
}
