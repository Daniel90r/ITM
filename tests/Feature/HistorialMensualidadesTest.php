<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, Inscripcion, Mensualidad, Pago, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistorialMensualidadesTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->travelTo(\Carbon\Carbon::parse('2026-09-15 12:00:00', 'America/El_Salvador'));
    }

    private function obligacion(Alumno $a, ?Curso $c, string $mes, array $otros = []): Mensualidad
    {
        return Mensualidad::factory()->create($otros + ['alumno_id' => $a->id, 'curso_id' => $c?->id,
            'mes' => $mes, 'anio' => 2026, 'monto' => '25.00', 'estado' => 'Pendiente', 'fecha_vencimiento' => '2026-09-30']);
    }

    private function url(Alumno $a, array $f = []): string
    {
        return route('admin.mensualidades.historial.show', ['alumno' => $a->id] + $f);
    }

    public function test_doce_meses_cronologicos_e_importes_solo_de_obligaciones_reales(): void
    {
        $a = Alumno::factory()->create(); $c = Curso::factory()->create();
        foreach (['Junio', '07'] as $mes) {
            $m = $this->obligacion($a, $c, $mes, ['estado' => 'Pagado']);
            Pago::create(['mensualidad_id' => $m->id, 'fecha_pago' => '2026-07-15', 'monto_pagado' => 25]);
        }
        $this->obligacion($a, $c, '8', ['fecha_vencimiento' => '2026-08-31']);
        $this->obligacion($a, $c, 'Septiembre');
        $r = $this->get($this->url($a))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(['pagado' => 5000, 'pendiente' => 5000, 'vencido' => 2500, 'en_revision' => 0, 'posibles_duplicados' => 0], $r->viewData('resumen'));
        $dom = new \DOMDocument(); @$dom->loadHTML($r->getContent()); $xp = new \DOMXPath($dom);
        $numeros = [];
        foreach ($xp->query('//tbody[@data-mes]') as $nodo) $numeros[] = (int) $nodo->getAttribute('data-mes');
        $this->assertSame(range(1, 12), $numeros);
        $this->assertSame(8, substr_count($r->getContent(), '>Sin registro<'));
        $r->assertSee('Vencido')->assertSee('Pendiente')->assertSee('Pagado')->assertSee('15/07/2026');
        $this->assertDatabaseCount('mensualidades', 4);
        $this->assertDatabaseCount('pagos', 2);
    }

    public function test_retirados_cursos_historicos_y_filtros_reales(): void
    {
        $a = Alumno::factory()->create(['activo' => false, 'nombre' => 'Retirado', 'apellido' => 'Prueba']);
        $antiguo = Curso::factory()->create(['activo' => false]); $actual = Curso::factory()->create();
        Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => $actual->id, 'estado' => 'inactiva', 'activa' => false]);
        $m = $this->obligacion($a, $antiguo, 'Marzo');
        $this->obligacion($a, $actual, 'Mayo', ['anio' => 2025]);
        $sinCurso = $this->obligacion($a, null, 'Abril');
        $this->get(route('admin.mensualidades.historial.index', ['q' => 'Retirado']))->assertOk()->assertSee($this->url($a), false);
        $r = $this->get($this->url($a, ['anio' => 2026, 'curso_id' => $antiguo->id]))->assertOk();
        $this->assertSame(5000, $r->viewData('resumen')['pendiente']);
        $this->assertSame([$m->id, $sinCurso->id], $r->viewData('meses')->flatMap(fn ($mes) => $mes['filas'])->pluck('registro.id')->all());
        $this->get($this->url($a, ['curso_id' => 'sin_curso']))->assertOk()->assertSee('Sin curso asociado')
            ->assertViewHas('resumen', fn ($s) => $s['pendiente'] === 5000);
        $this->get($this->url($a, ['anio' => 2025]))->assertOk()->assertViewHas('resumen', fn ($s) => $s['pendiente'] === 2500);
    }

    public function test_duplicados_normalizados_no_se_ocultan_ni_se_concilian(): void
    {
        $a = Alumno::factory()->create(); $c = Curso::factory()->create();
        $uno = $this->obligacion($a, $c, 'Agosto');
        $dos = $this->obligacion($a, null, '08');
        $r = $this->get($this->url($a))->assertOk()->assertSee('Posible duplicado');
        $this->assertSame(0, $r->viewData('resumen')['pendiente']);
        $this->assertSame(5000, $r->viewData('resumen')['en_revision']);
        $this->assertSame(2, $r->viewData('resumen')['posibles_duplicados']);
        foreach ([$uno, $dos] as $m) $this->assertSame(1, substr_count($r->getContent(), 'data-obligacion="'.$m->id.'"'));
        $this->get($this->url($a, ['curso_id' => $c->id]))->assertOk()->assertSee('Posible duplicado');
        $this->assertDatabaseCount('mensualidades', 2);
    }

    public function test_vacio_vencimiento_hoy_y_consultas_de_solo_lectura(): void
    {
        $a = Alumno::factory()->create();
        $this->get($this->url($a))->assertOk()->assertViewHas('resumen', fn ($s) => $s['pendiente'] === 0);
        $this->obligacion($a, null, 'Septiembre', ['fecha_vencimiento' => '2026-09-15', 'monto' => '17.35']);
        DB::connection()->beforeExecuting(function ($sql) { $this->assertMatchesRegularExpression('/^\s*(select|pragma)/i', $sql); });
        $this->get($this->url($a))->assertOk()->assertViewHas('resumen', fn ($s) => $s['pendiente'] === 1735 && $s['vencido'] === 0);
    }

    public function test_movimientos_inconsistentes_y_meses_invalidos_se_senalan(): void
    {
        $a = Alumno::factory()->create();
        $m = $this->obligacion($a, null, 'mes desconocido', ['estado' => 'Pagado']);
        $pendiente = $this->obligacion($a, null, 'Junio');
        Pago::create(['mensualidad_id' => $pendiente->id, 'fecha_pago' => '2026-06-01', 'monto_pagado' => 25]);
        $r = $this->get($this->url($a))->assertOk()->assertSee('Registros con mes no reconocido')->assertSee('requieren revisión');
        $this->assertSame(2500, $r->viewData('resumen')['pagado']);
        $this->assertCount(1, $r->viewData('sinMes'));
        $this->assertSame('', $r->viewData('meses')->get(6)['filas']->first()['fechas_pago']);
        $this->assertDatabaseHas('mensualidades', ['id' => $m->id, 'estado' => 'Pagado']);
    }

    public function test_permisos_validacion_y_busqueda_paginada(): void
    {
        $a = Alumno::factory()->create();
        foreach ([['anio' => 1899], ['anio' => 'x']] as $f) {
            $this->getJson($this->url($a, $f))->assertUnprocessable();
        }
        Alumno::factory()->count(26)->create(['apellido' => 'Busqueda']);
        $this->get(route('admin.mensualidades.historial.index', ['q' => 'Busqueda', 'page' => 2]))->assertOk()
            ->assertViewHas('alumnos', fn ($p) => $p->total() === 26 && $p->count() === 1);
        foreach (['alumno', 'docente', 'director'] as $rol) {
            $this->actingAs(User::factory()->make(['role' => $rol]));
            $this->get($this->url($a))->assertForbidden();
            $this->get(route('admin.mensualidades.historial.index'))->assertForbidden();
        }
        auth()->forgetGuards();
        $this->get($this->url($a))->assertRedirect(route('login'));
        $this->get(route('admin.mensualidades.historial.index'))->assertRedirect(route('login'));
    }

    private function referenciaInscripcion(Alumno $a, Curso $c, string $fecha): void
    {
        Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => $c->id,
            'fecha_inscripcion' => $fecha, 'estado' => 'inactiva', 'activa' => false]);
    }

    public function test_referencia_reciente_limita_meses_sin_ocultar_obligaciones(): void
    {
        $a = Alumno::factory()->create(['activo' => false]); $c = Curso::factory()->create();
        $this->referenciaInscripcion($a, $c, '2026-07-20');
        $this->get($this->url($a, ['curso_id' => $c->id]))->assertOk()
            ->assertViewHas('meses', fn ($m) => $m->keys()->all() === [7, 8, 9])
            ->assertSee('Fecha registrada de solicitud/inscripción')->assertSee('20/07/2026');
        $antigua = $this->obligacion($a, $c, 'Junio');
        $futura = $this->obligacion($a, $c, '11');
        $r = $this->get($this->url($a, ['curso_id' => $c->id]))->assertOk();
        $this->assertSame([6, 7, 8, 9, 11], $r->viewData('meses')->keys()->all());
        foreach ([$antigua, $futura] as $m) $r->assertSee('data-obligacion="'.$m->id.'"', false);
        $this->assertSame(5000, $r->viewData('resumen')['pendiente']);
        $this->assertDatabaseCount('mensualidades', 2);
    }

    public function test_anos_anteriores_y_posteriores_conservan_solo_intervalo_o_registros(): void
    {
        $a = Alumno::factory()->create(); $c = Curso::factory()->create();
        $this->referenciaInscripcion($a, $c, '2024-06-15');
        foreach ([2023 => [], 2024 => [6,7,8,9,10,11,12], 2025 => range(1,12), 2026 => range(1,9), 2027 => []] as $anio => $esperados) {
            $this->get($this->url($a, ['anio' => $anio, 'curso_id' => $c->id]))->assertOk()
                ->assertViewHas('meses', fn ($m) => $m->keys()->all() === $esperados);
        }
        $m = $this->obligacion($a, $c, 'Febrero', ['anio' => 2023]);
        $this->get($this->url($a, ['anio' => 2023, 'curso_id' => $c->id]))->assertOk()
            ->assertViewHas('meses', fn ($meses) => $meses->keys()->all() === [2])
            ->assertSee('data-obligacion="'.$m->id.'"', false);
    }

    public function test_fechas_historicas_distintas_conservan_calendario_completo(): void
    {
        $a = Alumno::factory()->create(); $primero = Curso::factory()->create(); $segundo = Curso::factory()->create();
        $this->referenciaInscripcion($a, $primero, '2026-06-01');
        $this->referenciaInscripcion($a, $segundo, '2026-08-10');
        $otro = $this->obligacion($a, $primero, 'Enero');
        foreach ([[], ['curso_id' => $segundo->id]] as $parametros) {
            $this->get($this->url($a, $parametros))->assertOk()
                ->assertViewHas('meses', fn ($m) => $m->keys()->all() === range(1, 12))
                ->assertViewHas('referencia', fn ($r) => $r['fecha'] === null)
                ->assertSee('data-obligacion="'.$otro->id.'"', false);
        }
    }

    public function test_fechas_faltantes_invalidas_y_origen_desconocido_conservan_calendario_seguro(): void
    {
        foreach (['', '2026-02-30', '0000-00-00'] as $fecha) {
            $a = Alumno::factory()->create(); $c = Curso::factory()->create();
            $this->referenciaInscripcion($a, $c, $fecha);
            $this->get($this->url($a, ['curso_id' => $c->id]))->assertOk()
                ->assertViewHas('meses', fn ($m) => $m->count() === 12)
                ->assertViewHas('referencia', fn ($r) => $r['fecha'] === null)
                ->assertSee('No se pudo determinar el inicio');
        }
        $a = Alumno::factory()->create(); $c = Curso::factory()->create();
        $this->referenciaInscripcion($a, $c, '2026-08-01');
        $this->obligacion($a, null, 'Marzo');
        $this->get($this->url($a))->assertOk()->assertViewHas('meses', fn ($m) => $m->count() === 12);
        $this->get($this->url($a, ['curso_id' => 'sin_curso']))->assertOk()
            ->assertViewHas('referencia', fn ($r) => $r['fecha'] === null);
        $sinOrigen = Curso::factory()->create();
        $this->obligacion($a, $sinOrigen, 'Abril');
        $this->get($this->url($a, ['curso_id' => $sinOrigen->id]))->assertOk()
            ->assertViewHas('meses', fn ($m) => $m->count() === 12);
    }

    public function test_interfaz_solo_anio_conserva_todos_los_cursos_y_saldos(): void
    {
        $a = Alumno::factory()->create();
        $antiguo = Curso::factory()->create(['nombre' => 'Curso anterior']);
        $actual = Curso::factory()->create(['nombre' => 'Curso asignado actual']);
        $this->referenciaInscripcion($a, $antiguo, '2025-03-01');
        Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => $actual->id,
            'fecha_inscripcion' => '2026-07-01', 'estado' => 'aprobada', 'activa' => true]);
        $pagada = $this->obligacion($a, $antiguo, 'Enero', ['estado' => 'Pagado']);
        Pago::create(['mensualidad_id' => $pagada->id, 'fecha_pago' => '2026-01-15', 'monto_pagado' => 25]);
        $sinCurso = $this->obligacion($a, null, 'Febrero', ['fecha_vencimiento' => '2026-08-31']);
        $vigente = $this->obligacion($a, $actual, 'Marzo');
        foreach ([[], ['curso_id' => $actual->id], ['curso_id' => 'sin_curso'], ['curso_id' => ['1']], ['curso_id' => 999999]] as $parametros) {
            $r = $this->get($this->url($a, $parametros))->assertOk()
                ->assertDontSee('name="curso_id"', false)->assertDontSee('Todos los cursos')
                ->assertSee('Consultar')->assertSee('Volver a Alumnos')->assertSee('Sin curso asociado')
                ->assertSee('Curso anterior')->assertSee('Curso asignado actual');
            $this->assertSame($actual->id, $r->viewData('cursoAsignado')->id);
            $this->assertSame(2500, $r->viewData('resumen')['pagado']);
            $this->assertSame(5000, $r->viewData('resumen')['pendiente']);
            $this->assertSame(2500, $r->viewData('resumen')['vencido']);
            foreach ([$pagada, $sinCurso, $vigente] as $m) {
                $this->assertSame(1, substr_count($r->getContent(), 'data-obligacion="'.$m->id.'"'));
            }
        }
        $this->assertDatabaseHas('mensualidades', ['id' => $pagada->id, 'curso_id' => $antiguo->id, 'estado' => 'Pagado']);
        $this->assertDatabaseCount('mensualidades', 3);
        $this->assertDatabaseCount('pagos', 1);
    }

    public function test_no_inventa_curso_actual_si_hay_varias_inscripciones_activas(): void
    {
        $a = Alumno::factory()->create();
        foreach (range(1, 2) as $i) {
            Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => Curso::factory()->create()->id,
                'estado' => 'aprobada', 'activa' => true]);
        }
        $this->get($this->url($a))->assertOk()->assertViewHas('asignacionAmbigua', true)
            ->assertViewHas('cursoAsignado', null)->assertSee('Hay varias inscripciones activas');
    }
}
