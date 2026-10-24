<?php

namespace Tests\Feature;

use App\Models\{Alumno, AsignacionDocente, Asistencia, Curso, DetalleCurso, Inscripcion, Maestro, Materia, Mensualidad, Nota, Pago, User};
use App\Services\ReportesAdministrativos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportesAdministrativosTest extends TestCase
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

    private function reporte(string $tipo, array $filtros = [])
    {
        return $this->get(route('admin.reportes.show', ['tipo' => $tipo] + $filtros))->assertOk();
    }

    public function test_portada_con_seis_tarjetas_incluye_reporte_de_solicitudes(): void
    {
        $respuesta = $this->get(route('admin.reportes'))->assertOk()->assertSee('Selecciona un reporte')
            ->assertSee('sidebar-link active', false)->assertSee('Menú de administración');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$respuesta->getContent());
        $xpath = new \DOMXPath($dom);
        $tarjetas = $xpath->query('//nav[@aria-label="Tipos de reporte"]/a');
        $this->assertSame(6, $tarjetas->length);
        $this->assertSame(route('admin.reportes.solicitudes'), $tarjetas->item(5)->getAttribute('href'));
        $this->assertStringContainsString('Reporte de solicitudes de inscripción', $tarjetas->item(5)->textContent);
        foreach (['alumnos', 'cursos', 'asistencias', 'mensualidades', 'maestros'] as $i => $tipo) {
            $tarjeta = $tarjetas->item($i);
            $this->assertSame(route('admin.reportes.show', $tipo), $tarjeta->getAttribute('href'));
            $this->assertStringContainsString(ReportesAdministrativos::TIPOS[$tipo], $tarjeta->textContent);
            $this->assertSame(sprintf('%02d', $i + 1), trim($tarjeta->getElementsByTagName('span')->item(0)->textContent));
        }
        $respuesta->assertDontSee('href="'.route('admin.reportes.show', 'calificaciones').'"', false);
        foreach (ReportesAdministrativos::TIPOS as $tipo => $titulo) {
            $this->reporte($tipo)->assertSee($titulo)->assertSee($tipo === 'cursos' ? 'No hay cursos con inscripciones actualmente aprobadas' : ($tipo === 'alumnos' ? 'No hay alumnos inscritos para el año' : ($tipo === 'mensualidades' ? 'No hay mensualidades para el año' : 'No hay registros para los filtros seleccionados.')))
                ->assertSee('scope="col"', false)->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public static function roles(): array
    {
        return [['alumno'], ['docente'], ['maestro'], ['director']];
    }

    #[DataProvider('roles')]
    public function test_nunca_autoriza_otros_roles(string $role): void
    {
        // El rol maestro no está en el enum; los roles reales se auditan aparte.
        if ($role === 'maestro' || $role === 'director') {
            $usuario = User::factory()->make(['role' => $role]);
        } else {
            $usuario = User::factory()->create(['role' => $role]);
        }
        $this->actingAs($usuario);
        $this->get(route('admin.reportes'))->assertForbidden();
        foreach (array_keys(ReportesAdministrativos::TIPOS) as $tipo) {
            $this->get(route('admin.reportes.show', $tipo))->assertForbidden();
        }
    }

    public function test_invitado_y_metodos_de_escritura(): void
    {
        auth()->forgetGuards();
        $this->get(route('admin.reportes'))->assertRedirect(route('login'));
        foreach (array_keys(ReportesAdministrativos::TIPOS) as $tipo) {
            $this->get(route('admin.reportes.show', $tipo))->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->{$method}(route('admin.reportes.show', 'alumnos'))->assertStatus(405);
        }
        $this->get('/admin/reportes/inexistente')->assertNotFound();
    }

    public function test_alumnos_unicos_con_historia_y_repeticiones(): void
    {
        $a = Alumno::factory()->create(['nombre' => 'Alumno demostración', 'activo' => true]);
        $c = Curso::factory()->create(['nombre' => 'Curso histórico', 'fecha_fin' => now()->subDay()]);
        $otro = Curso::factory()->create(['nombre' => 'Curso actual']);
        foreach ([$c, $otro] as $curso) Inscripcion::factory()->create([
            'alumno_id' => $a->id, 'curso_id' => $curso->id, 'estado' => 'aprobada', 'activa' => true,
        ]);
        try {
            Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => $c->id, 'estado' => 'aprobada']);
            $this->fail('El esquema debe rechazar una inscripción repetida del mismo alumno y curso.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
        }
        Inscripcion::factory()->create(['estado' => 'pendiente']);
        Inscripcion::factory()->create(['estado' => 'rechazada']);
        Inscripcion::factory()->create(['estado' => 'inactiva']);
        $this->reporte('alumnos')->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 1
            && $r['resumen']['Alumnos únicos'] === 1 && substr_count($r['filas'][0][2], 'Curso histórico') === 1);
        $this->reporte('alumnos', ['curso_id' => $c->id])->assertSee('Curso histórico')->assertViewHas('reporte', fn ($r) =>
            $r['filas']->total() === 1 && !str_contains($r['filas'][0][2], 'Curso actual'));
        $this->reporte('alumnos', ['estado' => 'inactivo'])->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 0);
        $a->update(['activo' => false]);
        $this->reporte('alumnos', ['estado' => 'inactivo'])->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 1);
    }

    public function test_cupos_y_estados_reutilizan_reglas_del_curso(): void
    {
        $c = Curso::factory()->create(['estado' => 'ACTIVO', 'fecha_fin' => now()->subDay()]);
        Inscripcion::factory()->count(21)->create(['curso_id' => $c->id, 'estado' => 'aprobada']);
        Inscripcion::factory()->create(['curso_id' => $c->id, 'estado' => 'pendiente']);
        Curso::factory()->create(['estado' => 'ACTIVO', 'fecha_fin' => now()->addDay()]);
        $this->reporte('cursos', ['estado' => 'FINALIZADO'])->assertViewHas('reporte', fn ($r) =>
            $r['filas']->total() === 1 && $r['filas'][0][1] === $c->estado_curso
            && $r['filas'][0][5] === Curso::CUPOS_MAXIMO && $r['filas'][0][4] === $c->inscritos_count && $r['filas'][0][6] === 0);
        $this->reporte('cursos', ['curso_id' => $c->id, 'estado' => 'ACTIVO'])->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 0);
    }

    public function test_calificaciones_no_recalcula_promedios_y_respeta_opcionales_por_curso(): void
    {
        $a = Alumno::factory()->create();
        $d = DetalleCurso::factory()->create(['materia_id' => Materia::factory()->create(['activa' => true, 'es_opcional' => false])->id]);
        Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => $d->curso_id, 'estado' => 'aprobada']);
        $n = Nota::factory()->create(['alumno_id' => $a->id, 'detalle_curso_id' => $d->id,
            'laboratorio' => 0, 'examen_teorico' => 7, 'practica' => 10, 'conducta' => 'E']);
        $this->assertSame(6, $n->fresh()->promedio);
        $opcional = Materia::factory()->create(['activa' => true, 'es_opcional' => true]);
        $od = DetalleCurso::factory()->create(['curso_id' => $d->curso_id, 'materia_id' => $opcional->id]);
        Nota::factory()->create(['alumno_id' => $a->id, 'detalle_curso_id' => $od->id]);
        $this->reporte('calificaciones')->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 1 && $r['filas'][0][8] === 6);
        $a->materias()->attach($opcional->id, ['curso_id' => Curso::factory()->create()->id]);
        $this->reporte('calificaciones', ['materia_id' => $opcional->id])->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 0);
        $a->materias()->attach($opcional->id, ['curso_id' => $d->curso_id]);
        $this->reporte('calificaciones', ['curso_id' => $d->curso_id])->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 2);
        DB::table('notas')->where('id', $n->id)->update(['promedio' => 9]);
        $this->reporte('calificaciones', ['materia_id' => $d->materia_id])->assertViewHas('reporte', fn ($r) => $r['filas'][0][8] === 9);
        $this->assertSame(9, $n->fresh()->promedio);
        $opcional->update(['activa' => false]);
        $this->reporte('calificaciones')->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 1);
    }

    public function test_asistencias_solo_fechas_registradas_y_periodo_mensual(): void
    {
        $d = DetalleCurso::factory()->create();
        $a = Alumno::factory()->create();
        foreach (['2026-10-01' => 'presente', '2026-10-03' => 'ausente', '2026-10-04' => 'permiso'] as $fecha => $estado) {
            Asistencia::create(['curso_id' => $d->curso_id, 'detalle_curso_id' => $d->id, 'alumno_id' => $a->id,
                'registrado_por' => $d->maestro_id, 'fecha' => $fecha, 'estado' => $estado]);
        }
        $this->reporte('asistencias', ['modalidad'=>'mensual','mes'=>10,'anio'=>2026])->assertViewHas('reporte', fn ($r) => $r['resumen'] === [
            'Registros' => 3, 'Presentes' => 1, 'Ausentes' => 1, 'Permisos' => 1]);
        $this->reporte('asistencias', ['curso_id' => $d->curso_id, 'modalidad'=>'mensual','mes'=>10,'anio'=>2026, 'estado' => 'ausente'])
            ->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 1 && $r['resumen']['Ausentes'] === 1);
        $this->reporte('asistencias', ['modalidad'=>'mensual','mes'=>11,'anio'=>2026])
            ->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 0);
    }

    public function test_mensualidades_sumadas_una_vez_y_el_esquema_rechaza_pagos_duplicados(): void
    {
        $v = Mensualidad::factory()->create(['estado' => 'Pagado', 'monto' => 30.25, 'mes' => '10', 'anio' => 2026]);
        Pago::create(['mensualidad_id' => $v->id, 'fecha_pago' => '2026-10-01', 'monto_pagado' => 30.25]);
        try {
            Pago::create(['mensualidad_id' => $v->id, 'fecha_pago' => '2026-10-01', 'monto_pagado' => 30.25]);
            $this->fail('El esquema debe rechazar un pago duplicado.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
        }
        Mensualidad::factory()->create(['curso_id' => $v->curso_id, 'estado' => 'Pendiente', 'monto' => 15.50, 'mes' => '10', 'anio' => 2026]);
        $this->reporte('mensualidades')->assertViewHas('reporte', fn ($r) => $r['resumen'] === [
            'Mensualidades' => 2, 'Monto pendiente' => '$15.50', 'Monto marcado pagado' => '$30.25']);
        $this->reporte('mensualidades', ['curso_id' => $v->curso_id, 'estado' => 'Pagado', 'mes' => 10, 'anio' => 2026])
            ->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 1 && $r['resumen']['Monto pendiente'] === '$0.00');
        $this->reporte('mensualidades', ['anio' => 2025])->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 0);
    }

    public function test_maestros_sin_asignacion_y_con_historial_no_se_confunden(): void
    {
        $m = Maestro::factory()->create(['activo' => true]);
        $d = DetalleCurso::factory()->create(['maestro_id' => $m->id]);
        foreach (['activa', 'finalizada', 'programada'] as $estado) AsignacionDocente::create([
            'detalle_curso_id' => $d->id, 'maestro_id' => $m->id, 'estado' => $estado, 'fecha_inicio' => '2026-01-01',
        ]);
        $sin = Maestro::factory()->create(['activo' => false]);
        $this->reporte('maestros', ['curso_id' => $d->curso_id])->assertViewHas('reporte', fn ($r) =>
            $r['filas']->total() === 1 && str_contains($r['filas'][0][3], '(activa)'));
        $this->reporte('maestros', ['estado' => 'inactivo'])->assertSee($sin->nombre_completo)->assertSee('Sin asignaciones activas');
        $d->update(['maestro_id' => $sin->id]);
        $this->reporte('maestros', ['curso_id' => $d->curso_id])->assertSee('revisar disponibilidad o asignación');
    }

    public function test_meses_guardados_como_nombre_numero_y_numero_con_cero(): void
    {
        foreach (['Enero', '1', '01', 'Febrero'] as $mes) Mensualidad::factory()->create(['mes' => $mes, 'anio' => 2026]);
        $this->reporte('mensualidades', ['mes' => 1, 'anio' => 2026])
            ->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 3);
    }

    public function test_asignaciones_activas_ambiguas_se_senalan_y_no_hay_n_mas_uno(): void
    {
        $m = Maestro::factory()->create();
        $d = DetalleCurso::factory()->create(['maestro_id' => $m->id]);
        foreach ([$m->id, Maestro::factory()->create()->id] as $id) AsignacionDocente::create([
            'detalle_curso_id' => $d->id, 'maestro_id' => $id, 'estado' => 'activa', 'fecha_inicio' => '2026-01-01',
        ]);
        $this->reporte('maestros', ['curso_id' => $d->curso_id])->assertDontSee('(activa)')->assertSee('revisar disponibilidad o asignación');
        $servicio = app(ReportesAdministrativos::class);
        DB::enableQueryLog(); DB::flushQueryLog();
        $servicio->consultar('maestros', []);
        $unaPagina = count(DB::getQueryLog());
        DB::disableQueryLog();
        Maestro::factory()->count(20)->create();
        DB::enableQueryLog(); DB::flushQueryLog();
        $servicio->consultar('maestros', []);
        $this->assertSame($unaPagina, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public static function parametrosInvalidos(): array
    {
        return [
            ['alumnos', ['curso_id' => '1 OR 1=1']], ['cursos', ['estado' => 'inventado']],
            ['maestros', ['estado' => ['activo']]], ['calificaciones', ['materia_id' => -1]],
            ['mensualidades', ['mes' => 13]], ['mensualidades', ['anio' => 1899]],
            ['asistencias', ['fecha_desde' => '2026-02-30']],
            ['asistencias', ['fecha_desde' => '2026-10-05', 'fecha_hasta' => '2026-10-01']],
            ['alumnos', ['page' => '0']], ['alumnos', ['curso_id' => 999999]],
        ];
    }

    #[DataProvider('parametrosInvalidos')]
    public function test_rechaza_parametros_inseguros_o_invalidos(string $tipo, array $filtros): void
    {
        $this->getJson(route('admin.reportes.show', ['tipo' => $tipo] + $filtros))->assertUnprocessable();
    }

    public function test_pagina_resumen_total_escape_html_y_consultas_de_solo_lectura(): void
    {
        Curso::factory()->count(26)->create()->each(fn ($c) => Inscripcion::factory()->create(['curso_id'=>$c->id,'estado'=>'aprobada']));
        $a = Alumno::factory()->create(['nombre' => '<script>alert(1)</script>']);
        Inscripcion::factory()->create(['alumno_id' => $a->id, 'estado' => 'aprobada']);
        DB::connection()->beforeExecuting(function (string $sql): void {
            $this->assertMatchesRegularExpression('/^\s*(select|pragma)\b/i', $sql);
        });
        foreach (array_keys(ReportesAdministrativos::TIPOS) as $tipo) $this->reporte($tipo);
        $this->reporte('alumnos')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee($a->dui)->assertDontSee($a->direccion)->assertDontSee($a->telefono);
        $this->reporte('cursos', ['page' => 2])->assertViewHas('reporte', fn ($r) =>
            $r['filas']->total() === 27 && count($r['filas']) === 2 && $r['resumen']['Cursos'] === 27);
    }

    public function test_menu_y_paginas_independientes_conservan_urls(): void
    {
        $menu = $this->get('/admin/reportes')->assertOk()->assertViewIs('admin.reportes.index')
            ->assertDontSee('<form method="GET"', false)->assertDontSee('25 por página')
            ->assertSee(route('admin.solicitudes'), false);
        foreach (ReportesAdministrativos::TIPOS as $tipo => $titulo) {
            $this->assertSame(url('/admin/reportes/'.$tipo), route('admin.reportes.show', $tipo));
            if ($tipo === 'calificaciones') {
                $menu->assertDontSee(route('admin.reportes.show', $tipo), false);
            } else {
                $menu->assertSee(route('admin.reportes.show', $tipo), false);
            }
            $pagina = $this->reporte($tipo)->assertViewIs('admin.reportes.'.$tipo)
                ->assertSee('Reporte de '.$titulo)->assertSee('Volver a Reportes')
                ->assertDontSee('aria-label="Tipos de reporte"', false);
            if ($tipo === 'calificaciones') {
                $pagina->assertDontSee('>Descargar PDF<', false);
            }
        }
    }

    public function test_enlaces_pdf_y_paginacion_conservan_filtros_sin_exportar_page(): void
    {
        Curso::factory()->count(26)->create(['fecha_inicio' => null, 'fecha_fin' => null])->each(fn ($c) => Inscripcion::factory()->create(['curso_id'=>$c->id,'estado'=>'aprobada']));
        foreach (['cursos' => ['estado' => 'SIN_FECHAS'], 'maestros' => ['estado' => 'activo'],
            'mensualidades' => ['anio' => 2026, 'mes' => 10, 'estado' => 'Pendiente']] as $tipo => $filtros) {
            $pagina = $this->reporte($tipo, $filtros + ['page' => 2]);
            $this->assertEquals($tipo === 'cursos' ? $filtros + ['modalidad'=>'anual','anio'=>now('America/El_Salvador')->year] : $filtros, $pagina->viewData('filtros'));
            $pagina->assertSee(e(route('admin.reportes.'.$tipo.'.pdf', $pagina->viewData('filtros'))), false);
        }
        $this->reporte('cursos', ['estado' => 'SIN_FECHAS'])->assertSee('estado=SIN_FECHAS', false)
            ->assertSee('page=2', false);
    }

    public function test_materia_asistencia_filtra_historial_y_valida_pertenencia(): void
    {
        $d = DetalleCurso::factory()->create();
        $otra = DetalleCurso::factory()->create(['curso_id' => $d->curso_id]);
        $alumno = Alumno::factory()->create();
        foreach ([$d, $otra] as $detalle) Asistencia::create([
            'alumno_id' => $alumno->id, 'curso_id' => $detalle->curso_id,
            'detalle_curso_id' => $detalle->id, 'fecha' => '2026-10-01',
            'estado' => 'ausente', 'registrado_por' => $detalle->maestro_id,
        ]);
        $f = ['curso_id' => $d->curso_id, 'detalle_curso_id' => $d->id,
            'modalidad'=>'mensual','mes'=>10,'anio'=>2026];
        $pagina = $this->reporte('asistencias', $f)->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 1)
            ->assertSee('PDF histórico mensual');
        $pagina->assertSee(e(route('admin.asistencias.pdf', $pagina->viewData('filtros'))), false);
        $paginaEstado = $this->reporte('asistencias', $f + ['estado' => 'ausente'])
            ->assertSee('estado=ausente', false)
            ->assertViewHas('filtros', fn ($v) => $v['estado'] === 'ausente');
        $paginaEstado->assertSee(e(route('admin.asistencias.pdf', $paginaEstado->viewData('filtros'))), false);
        $this->reporte('asistencias', $f + ['estado' => 'presente'])
            ->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 0);
        $this->reporte('asistencias', array_replace($f, ['mes' => 11]))
            ->assertViewHas('reporte', fn ($r) => $r['filas']->total() === 0);
        $ajeno = DetalleCurso::factory()->create();
        $this->getJson(route('admin.reportes.show', ['tipo' => 'asistencias',
            'curso_id' => $d->curso_id, 'detalle_curso_id' => $ajeno->id]))->assertUnprocessable();
        $this->getJson(route('admin.reportes.show', ['tipo' => 'asistencias',
            'detalle_curso_id' => $d->id]))->assertUnprocessable();
        // El PDF se obtiene desde el mismo enlace de la nueva página sin alterar su alcance.
        $this->get(route('admin.asistencias.pdf', $f))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_asistencias_carga_materias_reales_del_curso_y_explica_vacios(): void
    {
        $sinMaterias = Curso::factory()->create();
        $this->reporte('asistencias')->assertSee('Selecciona un curso')
            ->assertDontSee('href="'.route('admin.asistencias.pdf'), false);
        $this->reporte('asistencias', ['curso_id' => $sinMaterias->id])
            ->assertSee('Este curso no tiene materias asignadas')
            ->assertSee('Sin materias asignadas')->assertViewHas('detalleCursos', fn ($d) => $d->isEmpty());
        $detalle = DetalleCurso::factory()->create();
        $otro = DetalleCurso::factory()->create();
        $this->reporte('asistencias', ['curso_id' => $detalle->curso_id])
            ->assertSee($detalle->materia->nombre)
            ->assertViewHas('detalleCursos', fn ($d) => $d->pluck('id')->all() === [$detalle->id])
            ->assertSee('form.requestSubmit()', false)
            ->assertSee("materia.value = ''", false)
            ->assertSee("descarga.removeAttribute('href')", false);
        $this->getJson(route('admin.reportes.show', ['tipo' => 'asistencias',
            'curso_id' => $detalle->curso_id, 'detalle_curso_id' => $otro->id]))->assertUnprocessable();
    }
}
