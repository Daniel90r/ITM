<?php
namespace Tests\Feature;

use App\Models\{Alumno, Asistencia, Curso, DetalleCurso, Inscripcion, Maestro, Materia, User};
use App\Services\{AsistenciasPeriodo, FiltrosHistoricosAsistencia};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class AsistenciasPeriodoTest extends TestCase
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
        parent::setUp(); $this->withoutVite();
        $this->actingAs(User::factory()->create(['role'=>'admin']));
    }
    private function detalle(): DetalleCurso
    {
        return DetalleCurso::factory()->create(['curso_id'=>Curso::factory()->create()->id,'materia_id'=>Materia::factory()->create()->id,'maestro_id'=>Maestro::factory()->create()->id])->load(['curso','materia']);
    }
    private function registro($d,$a,$fecha,$estado,$observacion=null): void
    {
        Asistencia::create(['curso_id'=>$d->curso_id,'detalle_curso_id'=>$d->id,'alumno_id'=>$a->id,'fecha'=>$fecha,'estado'=>$estado,'registrado_por'=>$d->maestro_id,'observacion'=>$observacion]);
    }
    private function filtros($d, $extra=[]): array
    {
        return $extra+['curso_id'=>$d->curso_id,'detalle_curso_id'=>$d->id,'modalidad'=>'mensual','anio'=>2026,'mes'=>10];
    }
    public function test_matriz_historica_estados_sin_registro_y_conciliacion_anual(): void
    {
        $d=$this->detalle(); $a=Alumno::factory()->create(); $b=Alumno::factory()->create(['activo'=>false]);
        Inscripcion::factory()->create(['alumno_id'=>$b->id,'curso_id'=>$d->curso_id,'estado'=>'inactiva','activa'=>false]);
        $this->registro($d,$a,'2026-10-03','presente','<b>Observación ficticia</b>'); $this->registro($d,$a,'2026-10-10','ausente');
        $this->registro($d,$b,'2026-10-17','permiso'); $this->registro($d,$a,'2026-11-07','presente');
        $this->registro($d,$a,'2025-10-04','ausente');
        $otro=$this->detalle();$this->registro($otro,$a,'2026-10-24','presente');
        $otraMateria=DetalleCurso::factory()->create(['curso_id'=>$d->curso_id,'materia_id'=>Materia::factory()->create()->id,'maestro_id'=>$d->maestro_id]);
        $this->registro($otraMateria,$a,'2026-10-24','presente');
        $servicio=app(AsistenciasPeriodo::class);$f=$this->filtros($d);$m=$servicio->datos($d,$f);
        $this->assertSame(['2026-10-03','2026-10-10','2026-10-17'],$m['fechas']->all());
        $this->assertCount(2,$m['alumnos']);$this->assertSame(['presente'=>1,'ausente'=>1,'permiso'=>1],$m['totales']);
        $this->assertSame('PM',$m['marcas']->get($b->id.'|2026-10-17'));
        $this->assertCount(1,$m['observaciones']);
        $this->assertNull($m['marcas']->get($b->id.'|2026-10-03'));
        $anual=$servicio->datos($d,array_diff_key($this->filtros($d,['modalidad'=>'anual']),['mes'=>true]));
        foreach (['presente','ausente','permiso'] as $estado) {
            $suma=0;
            foreach (range(1,12) as $mes) $suma+=$servicio->datos($d,$this->filtros($d,['mes'=>$mes]))['totales'][$estado];
            $this->assertSame($suma,$anual['totales'][$estado]);
        }
        $this->assertSame(4,$anual['fechas']->count());$this->assertSame(3,$anual['fechasMes'][10]);
        $this->assertSame(1,$anual['fechasMes'][11]);$this->assertSame(0,$anual['fechasMes'][1]);
        foreach (['presente','ausente','permiso'] as $estado) {
            $f=$this->filtros($d,['estado'=>$estado]);$datos=$servicio->datos($d,$f);
            $this->assertSame(1,array_sum($datos['totales']));$this->assertSame(1,$datos['totales'][$estado]);
            $this->get(route('admin.reportes.show',['tipo'=>'asistencias']+$f))->assertOk()->assertViewHas('reporte',fn($r)=>$r['filas']->total()===1);
            $pdf=$this->get(route('admin.asistencias.pdf',$f))->assertOk()->assertHeader('Content-Type','application/pdf');
            $this->assertStringStartsWith('%PDF-',$pdf->getContent());
            $fAnual = array_diff_key($this->filtros($d,['modalidad'=>'anual','estado'=>$estado]), ['mes'=>true]);
            $this->get(route('admin.asistencias.pdf',$fAnual))->assertOk()->assertHeader('Content-Type','application/pdf');
        }
        $this->get(route('admin.asistencias.pdf',$this->filtros($d)))->assertOk();
        $vacio=$servicio->datos($d,$this->filtros($d,['mes'=>2]));$this->assertCount(0,$vacio['fechas']);$this->assertSame(0,array_sum($vacio['totales']));
        $html=view('admin.reportes.pdf.asistencia-periodo',$vacio+['filtros'=>app(\App\Services\PeriodoAsistencia::class)->conFechas($this->filtros($d,['mes'=>2])),'emitido'=>now()])->render();
        $this->assertStringContainsString('No hay registros para los filtros seleccionados.',$html);
        $fAnualVacio = array_diff_key($this->filtros($d,['modalidad'=>'anual','anio'=>2024]), ['mes'=>true]);
        $this->get(route('admin.asistencias.pdf',$fAnualVacio))->assertOk();
        $this->get(route('admin.reportes.show',['tipo'=>'asistencias']+$fAnualVacio))->assertOk()->assertViewHas('reporte',fn($r)=>$r['filas']->total()===0);
    }
    public function test_duplicados_conflictivos_se_excluyen_antes_del_filtro_estado(): void
    {
        $d=$this->detalle();$a=Alumno::factory()->create();
        Schema::table('asistencias',fn($t)=>$t->dropUnique('asistencias_detalle_curso_alumno_fecha_unique'));
        $this->registro($d,$a,'2026-10-03','presente');$this->registro($d,$a,'2026-10-03','ausente');
        foreach (['','presente','ausente'] as $estado) {
            $r=app(AsistenciasPeriodo::class)->datos($d,$this->filtros($d,['estado'=>$estado]));
            $this->assertCount(1,$r['duplicados']);$this->assertSame('?',$r['marcas']->first());$this->assertSame(0,array_sum($r['totales']));
        }
        $this->assertSame(2,Asistencia::count());
    }
    public function test_validacion_permisos_y_parametros_antiguos(): void
    {
        $d=$this->detalle();$url=route('admin.asistencias.pdf');$f=$this->filtros($d);
        foreach ([['anio'=>null],['mes'=>null],['mes'=>13],['modalidad'=>'otro'],['fecha_desde'=>'2026-10-01'],['fecha_hasta'=>'2026-10-31'],['modalidad'=>'anual']] as $cambio) {
            $this->getJson($url.'?'.http_build_query(array_replace($f,$cambio)))->assertUnprocessable();
        }
        $otro=$this->detalle();$this->getJson($url.'?'.http_build_query(array_replace($f,['detalle_curso_id'=>$otro->id])))->assertUnprocessable();
        $this->getJson(route('admin.reportes.show',['tipo'=>'asistencias']+$f+['fecha_desde'=>'2026-10-01']))->assertUnprocessable();
        $this->get($url.'?'.http_build_query(['curso_id'=>$d->curso_id,'detalle_curso_id'=>$d->id,'fecha_desde'=>'2026-10-01','fecha_hasta'=>'2026-10-31']))->assertOk();
        foreach (['alumno','docente'] as $rol) {
            $this->actingAs(User::factory()->create(['role'=>$rol]));
            $this->get($url.'?'.http_build_query($f))->assertForbidden();
            $this->get($url.'?'.http_build_query(array_diff_key(array_replace($f,['modalidad'=>'anual']),['mes'=>true])))->assertForbidden();
        }
        auth()->forgetGuards();$this->get($url.'?'.http_build_query($f))->assertRedirect(route('login'));
    }
    public function test_interfaz_solo_mensual_anual_y_predeterminados_aplicados(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00','America/El_Salvador'));
        $d=$this->detalle();
        $url=route('admin.reportes.show',['tipo'=>'asistencias','curso_id'=>$d->curso_id,'detalle_curso_id'=>$d->id]);
        $r=$this->get($url)->assertOk()->assertViewHas('filtros',fn($f)=>$f['modalidad']==='mensual' && $f['mes']===10 && $f['anio']===2026)
            ->assertDontSee('name="fecha_desde"',false)->assertDontSee('name="fecha_hasta"',false)->assertDontSee('Control existente / intervalo');
        $dom=new \DOMDocument; @$dom->loadHTML($r->getContent());$x=new \DOMXPath($dom);
        $opciones=$x->query('//select[@name="modalidad"]/option');
        $this->assertSame(2,$opciones->length);$this->assertSame('mensual',$opciones->item(0)->getAttribute('value'));$this->assertSame('anual',$opciones->item(1)->getAttribute('value'));
        $this->assertTrue($opciones->item(0)->hasAttribute('selected'));
        $r->assertSee(e(route('admin.asistencias.pdf',$r->viewData('filtros'))),false);
        foreach(['fecha_desde','fecha_hasta'] as $campo) {
            $this->getJson($url.'&'.$campo.'=2026-10-01')->assertUnprocessable();
            $this->getJson($url.'&modalidad=anual&anio=2026&'.$campo.'=2026-10-01')->assertUnprocessable();
        }
        $anual=$this->get($url.'&modalidad=anual&anio=2026')->assertOk()->assertViewHas('filtros',fn($f)=>$f['modalidad']==='anual' && !isset($f['mes']));
        $anual->assertSee(e(route('admin.asistencias.pdf',$anual->viewData('filtros'))),false);
        $this->getJson($url.'&modalidad=mensual&mes=10')->assertUnprocessable();
        if($dir=getenv('ITM_STAFF_PREVIEW_DIR')) {
            foreach(['asistencia-mensual'=>$r,'asistencia-anual'=>$anual] as $nombre=>$respuesta) {
                $html=$respuesta->getContent();$manifest=json_decode(file_get_contents(public_path('build/manifest.json')),true);
                $html=str_replace('<head>','<head><style>'.file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file'])).'</style>',$html);
                $html=str_replace('http://localhost/images/','file:///C:/wamp64/www/ITM/public/images/',$html);
                file_put_contents($dir.'/'.$nombre.'.html',$html);
            }
        }
    }

    public function test_pdf_multipagina_mensual_anual_y_muestras_sinteticas(): void
    {
        $d=$this->detalle();$d->curso->update(['nombre'=>'Curso Ficticio - PRUEBA']);$d->materia->update(['nombre'=>'Materia Ficticia - PRUEBA']);
        foreach(range(1,13) as $n) {
            $a=Alumno::factory()->create(['nombre'=>'Alumno Ficticio '.$n,'apellido'=>'Nombre Completo de Prueba']);
            foreach(range(1,13) as $dia) $this->registro($d,$a,sprintf('2026-10-%02d',$dia),['presente','ausente','permiso'][$dia%3]);
            $this->registro($d,$a,'2026-11-07','presente');
        }
        foreach (['mensual','anual'] as $modalidad) {
            $f=$this->filtros($d,['modalidad'=>$modalidad]);if($modalidad==='anual')unset($f['mes']);
            $r=app(AsistenciasPeriodo::class)->datos($d,$f);$this->assertGreaterThan(1,$r['paginas']->count());
            $pdf=Pdf::loadView('admin.reportes.pdf.asistencia-periodo',$r+['filtros'=>app(\App\Services\PeriodoAsistencia::class)->conFechas($f),'emitido'=>now()])->setPaper('letter','landscape')->setOption('isRemoteEnabled',false);
            $cajas=[];
            $pdf->getDomPDF()->setCallbacks([['event'=>'end_frame','f'=>function($frame) use (&$cajas) {
                if($frame->get_node()->nodeName === '#text' && trim($frame->get_node()->nodeValue) !== '') $cajas[]=$frame->get_border_box();
            }]]);
            $pdf->render();$this->assertGreaterThan(1,$pdf->getDomPDF()->getCanvas()->get_page_count());
            $this->assertNotEmpty($cajas);
            foreach($cajas as $caja) {
                $this->assertGreaterThanOrEqual(29,$caja['x']);
                $this->assertLessThanOrEqual(764,$caja['x']+$caja['w']);
                $this->assertGreaterThanOrEqual(10,$caja['y']);
                $this->assertLessThanOrEqual(602,$caja['y']+$caja['h']);
            }
            $font=$pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
            $pdf->getDomPDF()->getCanvas()->page_text(650,591,'Página {PAGE_NUM} / {PAGE_COUNT}',$font,7);
            $bytes=$pdf->output();$this->assertStringStartsWith('%PDF-',$bytes);
            $this->assertSame($modalidad === 'mensual' ? 4 : 3, $pdf->getDomPDF()->getCanvas()->get_page_count());
            $this->get(route('admin.asistencias.pdf',$f))->assertOk()->assertHeader('Cache-Control','no-store, private');
            if(getenv('ITM_ASISTENCIA_PERIODOS_VISUAL')) {
                $dir=base_path('storage/app/reportes-referencia');
                file_put_contents($dir.'/asistencia-'.$modalidad.'-prueba.pdf',$bytes);
            }
        }
    }
}
