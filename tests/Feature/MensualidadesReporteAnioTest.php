<?php
namespace Tests\Feature;
use App\Models\{Curso, Mensualidad, User};
use App\Services\ReportesAdministrativos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MensualidadesReporteAnioTest extends TestCase
{
    use RefreshDatabase;
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite',config('database.default'));$this->assertSame(':memory:',config('database.connections.sqlite.database'));
        $this->assertSame('sqlite',DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }
    protected function setUp(): void {parent::setUp();$this->withoutVite();$this->actingAs(User::factory()->create(['role'=>'admin']));}
    public static function calendario(): array {return [['2026-12-31 23:59:59',2026],['2027-01-01 00:00:00',2027]];}
    #[\PHPUnit\Framework\Attributes\DataProvider('calendario')]
    public function test_predeterminado_y_cambio_de_anio(string $instante,int $anio): void
    {
        foreach([2026,2027] as $y)Mensualidad::factory()->create(['anio'=>$y,'mes'=>'6','estado'=>'Pendiente','monto'=>'10.25']);
        $this->travelTo(\Carbon\Carbon::parse($instante,config('app.timezone')));
        $r=$this->get(route('admin.reportes.show','mensualidades'))->assertOk()->assertViewHas('filtros',fn($f)=>$f===['anio'=>$anio])->assertViewHas('reporte',fn($r)=>$r['filas']->total()===1 && $r['resumen']['Monto pendiente']==='$10.25');
        $dom=new \DOMDocument;@$dom->loadHTML($r->getContent());$x=new \DOMXPath($dom);$campo=$x->query('//input[@name="anio"]')->item(0);
        $this->assertSame((string)$anio,$campo->getAttribute('value'));$this->assertTrue($campo->hasAttribute('required'));$this->assertFalse($campo->hasAttribute('readonly'));$this->assertFalse($campo->hasAttribute('placeholder'));
        $r->assertSee(e(route('admin.reportes.mensualidades.pdf',$r->viewData('filtros'))),false);
        $wrapper=app('dompdf.wrapper');Pdf::shouldReceive('loadView')->once()->andReturnUsing(function($vista,$datos)use($anio,$wrapper){$this->assertSame($anio,$datos['filtros']['anio']);$this->assertCount(1,$datos['filas']);$this->assertSame(1025,$datos['pendienteCentavos']);return $wrapper->loadHTML(view($vista,$datos)->render());});
        $this->get(route('admin.reportes.mensualidades.pdf'))->assertOk()->assertHeader('Content-Type','application/pdf');
    }
    public function test_anio_anterior_todos_los_meses_y_totales_filtrados(): void
    {
        $c=Curso::factory()->create();
        Mensualidad::factory()->create(['curso_id'=>$c->id,'anio'=>2025,'mes'=>'Junio','estado'=>'Pendiente','monto'=>'10.25']);
        Mensualidad::factory()->create(['curso_id'=>$c->id,'anio'=>2025,'mes'=>'7','estado'=>'Pagado','monto'=>'20.75']);
        Mensualidad::factory()->create(['curso_id'=>$c->id,'anio'=>2026,'mes'=>'6','estado'=>'Pendiente','monto'=>'999.00']);
        Mensualidad::factory()->create(['anio'=>2025,'mes'=>'6','estado'=>'Pendiente','monto'=>'200.00']);
        $f=['anio'=>2025,'mes'=>'','curso_id'=>$c->id];
        $r=$this->get(route('admin.reportes.show',['tipo'=>'mensualidades']+$f))->assertOk();
        $this->assertSame(2,$r->viewData('reporte')['filas']->total());$this->assertSame('$10.25',$r->viewData('reporte')['resumen']['Monto pendiente']);$this->assertSame('$20.75',$r->viewData('reporte')['resumen']['Monto marcado pagado']);
        $datos=app(ReportesAdministrativos::class)->mensualidadesPdf($r->viewData('filtros'));$this->assertCount(2,$datos['filas']);$this->assertSame(1025,$datos['pendienteCentavos']);$this->assertSame(2075,$datos['pagadoCentavos']);
        $mensual=$this->get(route('admin.reportes.show',['tipo'=>'mensualidades','anio'=>2025,'mes'=>6,'curso_id'=>$c->id,'estado'=>'Pendiente']))->assertOk();
        $this->assertSame(1,$mensual->viewData('reporte')['filas']->total());$this->assertSame('$0.00',$mensual->viewData('reporte')['resumen']['Monto marcado pagado']);
        $this->get(route('admin.reportes.show',['tipo'=>'mensualidades','anio'=>2024,'mes'=>6]))->assertOk()->assertSee('No hay mensualidades para el año 2024')->assertSee('Junio');
    }
    public function test_paginacion_pdf_y_limpiar(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2027-04-01 12:00:00',config('app.timezone')));$c=Curso::factory()->create();
        foreach(range(1,26) as $_)Mensualidad::factory()->create(['curso_id'=>$c->id,'anio'=>2025,'mes'=>'06','estado'=>'Pendiente','monto'=>'10.00']);
        Mensualidad::factory()->create(['anio'=>2027,'mes'=>'6','estado'=>'Pendiente','monto'=>'5.00']);
        $f=['anio'=>2025,'mes'=>6,'curso_id'=>$c->id,'estado'=>'Pendiente'];
        $r=$this->get(route('admin.reportes.show',['tipo'=>'mensualidades']+$f))->assertOk();$this->assertCount(25,$r->viewData('reporte')['filas']);$this->assertSame('$260.00',$r->viewData('reporte')['resumen']['Monto pendiente']);
        $next=$r->viewData('reporte')['filas']->nextPageUrl();parse_str(parse_url($next,PHP_URL_QUERY),$query);foreach($f as $campo=>$valor)$this->assertEquals($valor,$query[$campo]);
        $this->get($next)->assertOk()->assertViewHas('reporte',fn($r)=>$r['filas']->count()===1 && $r['filas']->total()===26);
        $pdfUrl=route('admin.reportes.mensualidades.pdf',$r->viewData('filtros'));$r->assertSee(e($pdfUrl),false);
        $wrapper=app('dompdf.wrapper');Pdf::shouldReceive('loadView')->once()->andReturnUsing(function($vista,$datos)use($f,$wrapper){$this->assertEquals($f,$datos['filtros']);$this->assertCount(26,$datos['filas']);$this->assertSame(26000,$datos['pendienteCentavos']);$this->assertSame(0,$datos['pagadoCentavos']);return $wrapper->loadHTML(view($vista,$datos)->render());});
        $this->get($pdfUrl)->assertOk();
        $dom=new \DOMDocument;@$dom->loadHTML($r->getContent());$x=new \DOMXPath($dom);$limpiar=$x->query('//form//a[normalize-space(.)="Limpiar"]')->item(0);$this->assertNotNull($limpiar);
        $this->get($limpiar->getAttribute('href'))->assertOk()->assertViewHas('filtros',fn($f)=>$f===['anio'=>2027])->assertViewHas('reporte',fn($r)=>$r['filas']->total()===1);
    }
    public function test_validaciones_y_permisos(): void
    {
        foreach([['anio'=>''],['anio'=>'incorrecto'],['anio'=>0],['anio'=>2101],['anio'=>['2025']],['anio'=>2025,'mes'=>13]] as $f)foreach([route('admin.reportes.show','mensualidades'),route('admin.reportes.mensualidades.pdf')] as $url)$this->getJson($url.'?'.http_build_query($f))->assertUnprocessable();
        foreach(['alumno','docente'] as $rol){$this->actingAs(User::factory()->create(['role'=>$rol]));$this->get(route('admin.reportes.show','mensualidades'))->assertForbidden();$this->get(route('admin.reportes.mensualidades.pdf'))->assertForbidden();}
        auth()->forgetGuards();$this->get(route('admin.reportes.show','mensualidades'))->assertRedirect(route('login'));$this->get(route('admin.reportes.mensualidades.pdf'))->assertRedirect(route('login'));
    }
}
