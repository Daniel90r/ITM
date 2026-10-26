<?php
namespace Tests\Feature;

use App\Models\{Alumno, AsignacionDocente, Asistencia, Curso, DetalleCurso, Inscripcion, Maestro, Materia, Mensualidad, Nota, User};
use App\Services\{CursosConsulta, ReportesAdministrativos};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CursosConsultaTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertNotSame(base_path('storage'), storage_path());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 25)->startOfDay());
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function escenarios(): array
    {
        $a = Curso::factory()->create(['nombre'=>'ActivoVerificable', 'estado'=>'ACTIVO', 'activo'=>false]);
        $p = Curso::factory()->create(['nombre'=>'ProximoExcluido', 'estado'=>'PROXIMO']);
        $f = Curso::factory()->create(['nombre'=>'HistoricoPorFecha', 'estado'=>'ACTIVO', 'fecha_fin'=>now()->subDay()]);
        $m = Curso::factory()->create(['nombre'=>'HistoricoManual', 'estado'=>'ACTIVO', 'finalizado_manualmente'=>true]);
        foreach ([$a,$p,$f,$m] as $c) {
            $d = DetalleCurso::factory()->create(['curso_id'=>$c->id]);
            $alumno = Alumno::factory()->create();
            Inscripcion::factory()->create(['curso_id'=>$c->id,'alumno_id'=>$alumno->id,'estado'=>'aprobada','activa'=>true,'fecha_inscripcion'=>'2026-10-10']);
            Nota::factory()->create(['detalle_curso_id'=>$d->id,'alumno_id'=>$alumno->id]);
            Asistencia::create(['curso_id'=>$c->id,'detalle_curso_id'=>$d->id,'alumno_id'=>$alumno->id,'fecha'=>'2026-10-17','estado'=>'presente','registrado_por'=>$d->maestro_id]);
            Mensualidad::create(['alumno_id'=>$alumno->id,'curso_id'=>$c->id,'mes'=>'Octubre','anio'=>2026,'monto'=>25,'estado'=>'Pendiente','fecha_vencimiento'=>'2026-10-10']);
        }
        return [$a,$p,$f,$m];
    }

    public function test_catalogo_reutiliza_el_estado_canonico_y_visibilidad_es_independiente(): void
    {
        [$a,$p,$f,$m] = $this->escenarios();
        $service=app(CursosConsulta::class);
        $this->assertSame([$a->id],$service->opciones([])->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$a->id,$f->id,$m->id],$service->opciones(['incluir_historicos'=>1])->pluck('id')->all());
        $this->assertSame(1,$service->normalizar(['curso_id'=>$f->id,'incluir_historicos'=>0])['incluir_historicos']);
    }

    public function test_pantallas_y_urls_historicas_conservan_alcance_y_filtros(): void
    {
        [$a,$p,$f] = $this->escenarios();
        foreach (['admin.notas','admin.asistencias','admin.inscripciones','admin.boletas','admin.alumnos'] as $ruta) {
            $r=$this->get(route($ruta))->assertOk()->assertSee('Todos los cursos activos')->assertSee('Incluir cursos históricos');
            if ($dir = getenv('ITM_CURSOS_PREVIEW_DIR')) file_put_contents($dir.'/'.$ruta.'-activos.html', $r->getContent());
            $this->assertStringNotContainsString($p->nombre,$r->getContent());
            $this->assertStringNotContainsString($f->nombre,$r->getContent());
            $historica=$this->get(route($ruta,['curso_id'=>$f->id]))->assertOk()->assertSee($f->nombre)->assertSee('FINALIZADO');
            if ($dir = getenv('ITM_CURSOS_PREVIEW_DIR')) file_put_contents($dir.'/'.$ruta.'-historicos.html', $historica->getContent());
            $this->get(route($ruta,['incluir_historicos'=>1]))->assertOk()->assertSee($f->nombre);
        }
        $this->get(route('admin.notas',['curso_id'=>$p->id]))->assertSessionHasErrors('curso_id');
        $this->get(route('admin.notas',['incluir_historicos'=>'incorrecto']))->assertSessionHasErrors('incluir_historicos');
        $this->get(route('admin.notas',['curso_id'=>$f->id,'materia_id'=>$f->detalleCursos->first()->materia_id]))->assertOk()->assertViewHas('alumnos',fn ($a)=>$a->total()===1);
        $inscrito=$f->inscripciones()->first()->alumno;
        $this->get(route('admin.alumnos',['curso_id'=>$f->id,'q'=>$inscrito->nombre_completo]))->assertOk()
            ->assertViewHas('alumnos',fn ($alumnos)=>$alumnos->pluck('id')->all()===[$inscrito->id]);
    }

    public function test_paginacion_historica_conserva_el_alcance_y_alumnos_unicos(): void
    {
        $curso=Curso::factory()->create(['finalizado_manualmente'=>true]);
        $detalle=DetalleCurso::factory()->create(['curso_id'=>$curso->id]);
        foreach (range(1,26) as $i) Nota::factory()->create(['detalle_curso_id'=>$detalle->id]);
        $r=$this->get(route('admin.notas',['incluir_historicos'=>1]))->assertOk();
        $this->assertSame(26,$r['alumnos']->total());
        $this->assertCount(25,$r['alumnos']);
        $this->assertStringContainsString('incluir_historicos=1',$r['alumnos']->nextPageUrl());
        $s=$this->get($r['alumnos']->nextPageUrl())->assertOk();
        $this->assertCount(1,$s['alumnos']);
        $this->assertEmpty($r['alumnos']->pluck('id')->intersect($s['alumnos']->pluck('id')));
        $this->get(route('admin.notas'))->assertOk()->assertViewHas('alumnos',fn ($a)=>$a->total()===0);
    }

    public function test_reportes_y_pdf_comparten_alcance(): void
    {
        [$a,$p,$f,$m]=$this->escenarios();
        foreach(['alumnos','cursos','asistencias','mensualidades','calificaciones','maestros'] as $tipo) {
            $extra=['anio'=>2026] + ($tipo==='asistencias'?['modalidad'=>'anual']:[]);
            $this->get(route('admin.reportes.show',['tipo'=>$tipo]+$extra))->assertOk()->assertSee('Incluir cursos históricos');
            $this->get(route('admin.reportes.show',['tipo'=>$tipo,'curso_id'=>$f->id]+$extra))->assertOk()->assertSee($f->nombre);
        }
        $s=app(ReportesAdministrativos::class);
        foreach([0=>1,1=>3] as $historicos=>$total) {
            $filtros=['incluir_historicos'=>$historicos,'anio'=>2026,'modalidad'=>'anual'];
            $tabla=$s->consultar('cursos',$filtros);
            $pdf=$s->cursosPdf($filtros);
            $this->assertSame($total,$pdf['totalCursos']);
            $this->assertSame($tabla['filas']->total(),$pdf['totalCursos']);
            $this->assertCount($total,$s->alumnosPdf($filtros)['filas']);
            $this->assertCount($total,$s->mensualidadesPdf($filtros)['filas']);
        }
        $url=route('admin.reportes.visor',['tipo'=>'cursos','anio'=>2026,'incluir_historicos'=>1]);
        $this->get($url)->assertOk()->assertViewHas('pdfUrl',fn ($u)=>str_contains($u,'incluir_historicos=1'));
        $this->get(route('admin.reportes.cursos.pdf',['anio'=>2026,'incluir_historicos'=>1]))->assertOk()->assertHeader('content-type','application/pdf');
    }

    public static function consultasDocente(): array
    {
        return [['docente.notas', false], ['docente.notas', true], ['docente.asistencia', false], ['docente.asistencia', true]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('consultasDocente')]
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_docentes_solo_ven_cursos_de_sus_asignaciones(string $ruta, bool $historico): void
    {
        [$a,$p,$f]=$this->escenarios();
        $user=User::factory()->create(['role'=>'docente']);
        $maestro=Maestro::factory()->create(['user_id'=>$user->id]);
        foreach([$a,$f] as $c) {
            $c->update(['activo'=>true]);
            $d=$c->detalleCursos->first(); $d->update(['maestro_id'=>$maestro->id]);
            AsignacionDocente::create(['detalle_curso_id'=>$d->id,'maestro_id'=>$maestro->id,'estado'=>'activa']);
        }
        $ajeno=Curso::factory()->create(['nombre'=>'AsignacionAjena','estado'=>'ACTIVO']);
        $this->actingAs($user);
        $r=$this->get(route($ruta,$historico ? ['curso_id'=>$f->id] : []))->assertOk()->assertDontSee($ajeno->nombre);
        if ($historico) $r->assertSee($f->nombre); else $r->assertDontSee($f->nombre);
        $this->get(route('admin.notas'))->assertForbidden();
    }

    public function test_ajax_y_sugerencias_conservan_alcance(): void
    {
        [$a,$p,$f]=$this->escenarios();
        foreach([0=>1,1=>3] as $h=>$total) {
            $r=$this->get(route('admin.sugerencias.busqueda',['modulo'=>'notas-cursos','q'=>'Ver','incluir_historicos'=>$h]));
            $r->assertOk();
            $this->assertNotContains($p->id,collect($r->json('alumnos'))->pluck('id')->all());
            $this->get(route('admin.asistencias',['incluir_historicos'=>$h,'anio'=>2026,'mes'=>10]),['X-Requested-With'=>'XMLHttpRequest'])
                ->assertOk()->assertViewHas('alumnos',fn ($a)=>$a->total()===$total);
        }
        $this->get(route('admin.sugerencias.busqueda',['modulo'=>'notas-cursos','q'=>'Historico','incluir_historicos'=>1]))->assertOk()->assertJsonCount(2,'alumnos');
    }

    public function test_mensualidades_separa_el_filtro_del_catalogo_operativo_sin_escribir(): void
    {
        [$a,$p,$f]=$this->escenarios();
        $antes = DB::table('mensualidades')->orderBy('id')->get()->toJson();
        $r=$this->get(route('admin.mensualidades',['anio'=>2026]))->assertOk();
        $this->assertSame([$a->id],$r['cursosFiltro']->pluck('id')->all());
        $this->assertTrue($r['cursos']->contains('id',$p->id));
        $this->assertSame([$a->id],$r['grupos']->keys()->all());
        $h=$this->get(route('admin.mensualidades',['anio'=>2026,'curso_id'=>$f->id]))->assertOk();
        $this->assertSame(1,$h['filtros']['incluir_historicos']);
        $this->assertSame([$f->id],$h['grupos']->keys()->all());
        $this->assertSame($antes,DB::table('mensualidades')->orderBy('id')->get()->toJson());
    }
}
