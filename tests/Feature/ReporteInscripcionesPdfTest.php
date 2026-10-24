<?php

namespace Tests\Feature;

use App\Models\{Alumno, Inscripcion, ReactivacionInscripcion, User};
use App\Services\ConsultaSolicitudesInscripcion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReporteInscripcionesPdfTest extends TestCase
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
        $this->actingAs(User::factory()->create(['role' => 'admin', 'name' => 'Generador del informe']));
    }

    public function test_reporte_en_reportes_conserva_filtros_y_retorno_del_visor_sin_controles_en_gestion(): void
    {
        $this->get(route('admin.solicitudes'))->assertOk()->assertSee('Solicitudes')
            ->assertSee('id="buscarSolicitud"', false)->assertDontSee('Descargar PDF')->assertDontSee('Visualizar PDF');
        $this->get(route('admin.reportes'))->assertOk()->assertSee('Reporte de solicitudes de inscripción')
            ->assertSee(route('admin.reportes.solicitudes'), false);
        foreach (['todos', 'pendiente', 'aprobada', 'rechazada', 'inactiva', 'otros'] as $estado) {
            $f = ['estado' => $estado, 'q' => 'Ana prueba'];
            $this->get(route('admin.reportes.solicitudes', $f))->assertOk()->assertViewHas('filtros', $f)
                ->assertSee('Descargar PDF')->assertSee('Visualizar PDF')->assertSee(route('admin.reportes.inscripciones.pdf', $f));
            $this->get(route('admin.reportes.visor', ['tipo' => 'inscripciones'] + $f))->assertOk()
                ->assertViewHas('volverUrl', route('admin.reportes.solicitudes', $f))
                ->assertViewHas('pdfUrl', route('admin.reportes.inscripciones.pdf', $f));
        }
        $this->getJson(route('admin.reportes.solicitudes', ['estado' => 'invalido']))->assertUnprocessable();
        $this->getJson(route('admin.reportes.solicitudes', ['q' => ['Ana']]))->assertUnprocessable();
        foreach (['docente', 'alumno'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('admin.reportes.solicitudes'))->assertForbidden();
        }
        $this->app['auth']->guard()->logout();
        $this->get(route('admin.reportes.solicitudes'))->assertRedirect(route('login'));
    }

    private function html(array $filtros = []): string
    {
        return view('admin.reportes.pdf.inscripciones', app(ConsultaSolicitudesInscripcion::class)->informe($filtros) + [
            'filtros' => $filtros, 'emitido' => now(), 'responsable' => auth()->user()->name,
        ])->render();
    }

    public function test_descarga_pdf_real_vacio_y_privado(): void
    {
        $this->travelTo(now()->startOfSecond());
        $r = $this->get(route('admin.reportes.inscripciones.pdf'))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertDownload('reporte-inscripciones-'.now()->timezone('America/El_Salvador')->format('Y-m-d-His').'.pdf');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
        $this->assertStringContainsString('No hay registros', $this->html());
        $this->assertSame(0, app(ConsultaSolicitudesInscripcion::class)->informe([])['total']);
    }

    public function test_permisos_metodos_y_parametros_invalidos(): void
    {
        auth()->forgetGuards();
        $this->get(route('admin.reportes.inscripciones.pdf'))->assertRedirect(route('login'));
        foreach (['alumno', 'docente', 'director', 'maestro'] as $rol) {
            $this->actingAs(User::factory()->make(['role' => $rol]));
            $this->get(route('admin.reportes.inscripciones.pdf'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['estado' => 'inexistente', 'q' => str_repeat('a', 101)] as $campo => $valor) {
            $this->getJson(route('admin.reportes.inscripciones.pdf', [$campo => $valor]))
                ->assertUnprocessable()->assertJsonValidationErrors($campo);
        }
        $this->post(route('admin.reportes.inscripciones.pdf'))->assertStatus(405);
    }

    public function test_estados_totales_y_filtros_coinciden_con_listado(): void
    {
        $servicio = app(ConsultaSolicitudesInscripcion::class);
        $ids = [];
        foreach (ConsultaSolicitudesInscripcion::ESTADOS as $estado) {
            $inscripcion = Inscripcion::factory()->create(['estado' => $estado]);
            $ids[$estado] = $inscripcion->id;
        }
        $todos = $servicio->informe([]);
        $this->assertSame(4, $todos['total']);
        foreach (['pendientes', 'aprobadas', 'rechazadas', 'inactivas'] as $metrica) $this->assertSame(1, $todos[$metrica]);
        foreach (ConsultaSolicitudesInscripcion::ESTADOS as $estado) {
            $f = ['estado' => $estado];
            $pantalla = $this->get(route('admin.solicitudes', $f))->assertOk();
            $pdf = $servicio->informe($f);
            $this->assertSame([$ids[$estado]], $pdf['filas']->pluck('id')->all());
            $this->assertSame($pantalla->viewData('inscripciones')->pluck('id')->all(), $pdf['filas']->pluck('id')->all());
            $this->assertSame(1, $pdf['total']);
            $this->get(route('admin.reportes.inscripciones.pdf', $f))->assertOk();
        }
        $this->assertSame(0, $servicio->informe(['estado' => 'otros'])['total']);
        $this->assertStringContainsString('1 inactivas', $this->html());
    }

    public function test_busqueda_literal_combinada_y_boton_conserva_filtros(): void
    {
        $alumno = Alumno::factory()->create(['nombre' => 'Ana %_!', 'apellido' => 'Pérez', 'codigo' => 'PDF123']);
        $buscada = Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'estado' => 'rechazada']);
        Inscripcion::factory()->create(['estado' => 'rechazada']);
        $f = ['estado' => 'rechazada', 'q' => 'Ana %_!'];
        $pantalla = $this->get(route('admin.solicitudes', $f))->assertOk()->assertDontSee('Descargar PDF')->assertDontSee('Visualizar PDF');
        $this->assertSame([$buscada->id], $pantalla->viewData('inscripciones')->pluck('id')->all());
        $this->assertSame([$buscada->id], app(ConsultaSolicitudesInscripcion::class)->informe($f)['filas']->pluck('id')->all());
        $this->get(route('admin.reportes.solicitudes', $f))->assertOk()->assertSee(route('admin.reportes.inscripciones.pdf', $f));
        $this->assertStringContainsString('Ana %_!', $this->html($f));
        $this->assertSame(1, app(ConsultaSolicitudesInscripcion::class)->informe(['q' => 'PDF123'])['total']);
        $this->assertStringContainsString('No hay registros', $this->html(['q' => 'sin coincidencia']));
    }

    public function test_no_inventa_responsables_y_muestra_solo_accion_auditada(): void
    {
        $autor = User::factory()->create(['role' => 'admin', 'name' => 'Autor de reactivación']);
        $inscripcion = Inscripcion::factory()->create([
            'estado' => 'aprobada', 'observacion' => '<script>alert(1)</script>', 'motivo_rechazo' => 'Motivo histórico',
        ]);
        $sinAuditoria = Inscripcion::factory()->create(['estado' => 'rechazada']);
        ReactivacionInscripcion::create([
            'inscripcion_id' => $inscripcion->id, 'administrador_id' => $autor->id,
            'estado_anterior' => 'inactiva', 'estado_nuevo' => 'aprobada', 'reactivada_at' => now(), 'motivo' => 'Retorno autorizado',
        ]);
        $r = app(ConsultaSolicitudesInscripcion::class)->informe([]);
        $fila = $r['detalle']->first(fn ($fila) => str_contains($fila[5], 'Autor de reactivación'));
        $this->assertNotNull($fila);
        $this->assertStringContainsString('Reactivación registrada el', $fila[7]);
        $this->assertStringContainsString('Retorno autorizado', $fila[6]);
        $otra = $r['detalle']->first(fn ($fila) => $fila[5] === 'No registrado');
        $this->assertSame('No registrada', $otra[7]);
        $html = $this->html();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Generador del informe', $html);
        $this->assertStringNotContainsString('documento_path', $html);
    }

    public function test_exporta_todas_las_paginas_y_textos_extensos_sin_duplicar_totales(): void
    {
        Inscripcion::factory()->count(40)->create(['estado' => 'pendiente']);
        Inscripcion::factory()->create(['estado' => 'rechazada', 'observacion' => str_repeat('Observación extensa. ', 150)]);
        $r = app(ConsultaSolicitudesInscripcion::class)->informe([]);
        $this->assertCount(41, $r['filas']);
        $this->assertSame(41, $r['total']);
        $this->assertGreaterThan(41, $r['detalle']->count());
        $pdf = Pdf::loadView('admin.reportes.pdf.inscripciones', $r + [
            'filtros' => [], 'emitido' => now(), 'responsable' => 'Generador del informe',
        ])->setPaper('letter');
        $pdf->render();
        $this->assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
    }

    public function test_no_exporta_snapshot_del_encargado_como_observacion(): void
    {
        Inscripcion::factory()->create(['observacion' => json_encode([
            'encargado_nombre' => 'Nombre privado', 'encargado_dui' => '12345678-9', 'encargado_telefono' => '7000-0000',
        ])]);
        Inscripcion::factory()->create(['observacion' => '[]']);
        $html = $this->html();
        foreach (['Nombre privado', '12345678-9', '7000-0000', 'encargado_', 'Observación: []'] as $privado) {
            $this->assertStringNotContainsString($privado, $html);
        }
    }

    public function test_consulta_del_informe_solo_lee(): void
    {
        Inscripcion::factory()->create();
        DB::enableQueryLog();
        app(ConsultaSolicitudesInscripcion::class)->informe([]);
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($consultas as $consulta) {
            $this->assertMatchesRegularExpression('/^\s*(select|pragma)/i', $consulta['query']);
        }
    }
}
