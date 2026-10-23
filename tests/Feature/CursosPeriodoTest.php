<?php
namespace Tests\Feature;
use App\Models\{Curso, Inscripcion, User};
use App\Services\ReportesAdministrativos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class CursosPeriodoTest extends TestCase
{
    use RefreshDatabase;
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',config('database.connections.sqlite.database'));
        $this->assertSame('sqlite',DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }
    protected function setUp(): void { parent::setUp();$this->withoutVite();$this->actingAs(User::factory()->create(['role'=>'admin'])); }
    private function filtros($extra=[]): array { return $extra+['modalidad'=>'anual','anio'=>2026]; }
    public function test_limites_estados_cupos_y_misma_poblacion(): void
    {
        $c=Curso::factory()->create(['activo'=>false,'estado'=>'ACTIVO','fecha_inicio'=>null,'fecha_fin'=>null]);
        foreach(['2026-09-30','2026-10-01','2026-10-31','2026-11-01','2025-10-01'] as $fecha) Inscripcion::factory()->create(['curso_id'=>$c->id,'fecha_inscripcion'=>$fecha,'estado'=>'aprobada']);
        foreach(['pendiente','rechazada','inactiva'] as $estado) Inscripcion::factory()->create(['curso_id'=>$c->id,'fecha_inscripcion'=>'2026-10-15','estado'=>$estado]);
        $sinAprobadas=Curso::factory()->create();Inscripcion::factory()->create(['curso_id'=>$sinAprobadas->id,'fecha_inscripcion'=>'2026-10-15','estado'=>'pendiente']);
        $sinFechaPeriodo=Curso::factory()->create();Inscripcion::factory()->create(['curso_id'=>$sinFechaPeriodo->id,'fecha_inscripcion'=>'2025-10-15','estado'=>'aprobada']);
        $f=$this->filtros(['estado'=>'ACTIVO']);$s=app(ReportesAdministrativos::class);$pdf=$s->cursosPdf($f);$tabla=$s->consultar('cursos',$f);
        $this->assertSame([$c->id],$pdf['filas']->pluck('id')->all());$this->assertSame(1,$tabla['filas']->total());
        $this->assertEquals(4,$pdf['totalPeriodo']);$this->assertEquals(5,$pdf['totalInscritos']);$this->assertEquals(15,$pdf['totalDisponibles']);
        $this->assertSame('Oculto',$tabla['filas'][0][2]);$this->assertEquals(4,$tabla['filas'][0][3]);$this->assertEquals(5,$tabla['filas'][0][4]);$this->assertEquals(20,$tabla['filas'][0][5]);$this->assertEquals(15,$tabla['filas'][0][6]);
        $anual=$s->cursosPdf(['modalidad'=>'anual','anio'=>2026,'curso_id'=>$c->id]);$this->assertEquals(4,$anual['totalPeriodo']);$this->assertEquals(5,$anual['totalInscritos']);
        $this->assertSame(0,$s->cursosPdf($this->filtros(['curso_id'=>$sinAprobadas->id]))['totalCursos']);
        $r=$this->get(route('admin.reportes.show',['tipo'=>'cursos']+$f))->assertOk()->assertSee('Estado actual del curso');
        $r->assertSee(e(route('admin.reportes.cursos.pdf',$r->viewData('filtros'))),false);
        $this->get(route('admin.reportes.cursos.pdf',$f))->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->get(route('admin.reportes.show',['tipo'=>'cursos']+$this->filtros(['anio'=>2024])))->assertOk()->assertSee('No hay cursos con inscripciones actualmente aprobadas');
        $c->update(['finalizado_manualmente'=>true]);$this->assertSame(0,$s->cursosPdf($f)['totalCursos']);
    }
    public function test_validaciones_y_permisos(): void
    {
        foreach([['mes'=>''],['anio'=>''],['mes'=>13],['anio'=>1800],['modalidad'=>'otro'],['modalidad'=>'mensual'],['estado'=>'inventado'],['curso_id'=>999999]] as $cambio) {
            foreach([route('admin.reportes.show','cursos'),route('admin.reportes.cursos.pdf')] as $url) $this->getJson($url.'?'.http_build_query(array_replace($this->filtros(),$cambio)))->assertUnprocessable();
        }
        foreach(['docente','alumno'] as $rol) {
            $this->actingAs(User::factory()->create(['role'=>$rol]));
            foreach([route('admin.reportes.show','cursos'),route('admin.reportes.cursos.pdf')] as $url) $this->get($url.'?'.http_build_query($this->filtros()))->assertForbidden();
        }
        auth()->forgetGuards();$this->get(route('admin.reportes.cursos.pdf',$this->filtros()))->assertRedirect(route('login'));
    }
    public function test_solo_tres_filtros_predeterminados_y_compatibilidad_de_parametros(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00','America/El_Salvador'));
        $r=$this->get(route('admin.reportes.show','cursos'))->assertOk()->assertViewHas('filtros',fn($f)=>$f['modalidad']==='anual' && $f['anio']===2026 && !isset($f['mes']));
        $dom=new \DOMDocument;@$dom->loadHTML($r->getContent());$x=new \DOMXPath($dom);
        $campos=$x->query('//form[@id="filtros-cursos"]//input | //form[@id="filtros-cursos"]//select');
        $this->assertSame(['curso_id','estado','anio'],array_map(fn($e)=>$e->getAttribute('name'),iterator_to_array($campos)));
        $this->assertSame('2026',$x->query('//input[@name="anio"]')->item(0)->getAttribute('value'));
        $this->assertTrue($x->query('//input[@name="anio"]')->item(0)->hasAttribute('required'));
        $r->assertSee(e(route('admin.reportes.cursos.pdf',$r->viewData('filtros'))),false);
        foreach([['modalidad'=>'mensual'],['mes'=>10],['mes'=>''],['modalidad'=>'anual','mes'=>10]] as $f) {
            foreach([route('admin.reportes.show','cursos'),route('admin.reportes.cursos.pdf')] as $url) $this->getJson($url.'?'.http_build_query($f))->assertUnprocessable();
        }
        $this->get(route('admin.reportes.cursos.pdf',['anio'=>2024]))->assertOk()->assertHeader('Content-Type','application/pdf');
    }

    public function test_exportacion_completa_muestras_y_diseno(): void
    {
        foreach(range(1,28) as $n) {
            $c=Curso::factory()->create(['nombre'=>'Curso Ficticio de Prueba '.$n,'estado'=>'ACTIVO','fecha_fin'=>'2099-12-31']);
            Inscripcion::factory()->create(['curso_id'=>$c->id,'estado'=>'aprobada','fecha_inscripcion'=>'2026-10-15']);
            Inscripcion::factory()->create(['curso_id'=>$c->id,'estado'=>'aprobada','fecha_inscripcion'=>'2026-09-15']);
        }
        foreach(['anual'] as $modo) {
            $f=$this->filtros(['modalidad'=>$modo]);if($modo==='anual')unset($f['mes']);
            $s=app(ReportesAdministrativos::class);$r=$s->cursosPdf($f);$tabla=$s->consultar('cursos',$f+['page'=>2]);
            $this->assertCount(28,$r['filas']);$this->assertSame(28,$tabla['filas']->total());$this->assertEquals(56,$r['totalPeriodo']);$this->assertEquals(504,$r['totalDisponibles']);
            $pagina=$this->get(route('admin.reportes.show',['tipo'=>'cursos','page'=>2]+$f))->assertOk();$this->assertCount(3,$pagina->viewData('reporte')['filas']);
            if($dir=getenv('ITM_STAFF_PREVIEW_DIR')) {
                $htmlVisual=$pagina->getContent();$manifest=json_decode(file_get_contents(public_path('build/manifest.json')),true);
                $htmlVisual=str_replace('<head>','<head><style>'.file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file'])).'</style>',$htmlVisual);
                $htmlVisual=str_replace('http://localhost/images/','file:///C:/wamp64/www/ITM/public/images/',$htmlVisual);
                file_put_contents($dir.'/cursos-'.$modo.'.html',$htmlVisual);
            }

            $html=view('admin.reportes.pdf.cursos',$r+['filtros'=>$f,'emitido'=>now()])->render();
            $this->assertStringContainsString('no históricos',$html);$this->assertStringContainsString('REPORTE ANUAL DE CURSOS',$html);$this->assertStringContainsString('AÑO CONSULTADO',$html);$this->assertStringNotContainsString('MES:',$html);
            $pdf=Pdf::loadHTML($html)->setPaper('letter','landscape');$cajas=[];
            $pdf->getDomPDF()->setCallbacks([['event'=>'end_frame','f'=>function($frame)use(&$cajas){if($frame->get_node()->nodeName==='#text'&&trim($frame->get_node()->nodeValue)!=='')$cajas[]=$frame->get_border_box();}]]);
            $pdf->render();$this->assertGreaterThan(1,$pdf->getDomPDF()->getCanvas()->get_page_count());
            foreach($cajas as $caja){$this->assertGreaterThanOrEqual(18,$caja['x']);$this->assertLessThanOrEqual(774,$caja['x']+$caja['w']);$this->assertGreaterThanOrEqual(10,$caja['y']);$this->assertLessThanOrEqual(603,$caja['y']+$caja['h']);}
            $pdf->getDomPDF()->getCanvas()->page_text(650,590,'Página {PAGE_NUM} / {PAGE_COUNT}',null,8);$bytes=$pdf->output();
            $this->assertStringStartsWith('%PDF-',$bytes);
            $this->get(route('admin.reportes.cursos.pdf',$f+['page'=>2]))->assertOk()->assertHeader('Cache-Control','no-store, private');
            if(getenv('ITM_CURSOS_PERIODOS_VISUAL'))file_put_contents(base_path('storage/app/reportes-referencia/cursos-'.$modo.'-prueba.pdf'),$bytes);
        }
    }
}
