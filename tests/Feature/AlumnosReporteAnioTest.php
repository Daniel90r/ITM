<?php
namespace Tests\Feature;

use App\Models\{Alumno, Curso, Inscripcion, User};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlumnosReporteAnioTest extends TestCase
{
    use RefreshDatabase;
    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',config('database.connections.sqlite.database'));
        $this->assertSame('sqlite',DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }
    protected function setUp(): void { parent::setUp();$this->withoutVite();$this->actingAs(User::factory()->create(['role'=>'admin'])); }
    private function alumno(int $anio,?Curso $curso=null): Alumno
    {
        $a=Alumno::factory()->create(['activo'=>true]);
        Inscripcion::factory()->create(['alumno_id'=>$a->id,'curso_id'=>($curso??Curso::factory()->create())->id,'fecha_inscripcion'=>$anio.'-06-15','estado'=>'aprobada']);
        return $a;
    }
    public static function calendario(): array
    {
        return [['2026-12-31 23:59:59',2026],['2027-01-01 00:00:00',2027]];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('calendario')]
    public function test_predeterminado_cambia_con_el_calendario_del_servidor(string $instante,int $anio): void
    {
        $this->alumno(2026);$this->alumno(2027);
        $wrapper=app('dompdf.wrapper');
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function($vista,$datos)use($anio,$wrapper){$this->assertSame($anio,$datos['filtros']['anio']);$this->assertSame(1,$datos['totalAlumnos']);return $wrapper->loadHTML(view($vista,$datos)->render());});
            $this->travelTo(\Carbon\Carbon::parse($instante,config('app.timezone')));
            $r=$this->get(route('admin.reportes.show','alumnos'))->assertOk()->assertViewHas('filtros',fn($f)=>$f===['anio'=>$anio])->assertViewHas('reporte',fn($r)=>$r['filas']->total()===1);
            $dom=new \DOMDocument;@$dom->loadHTML($r->getContent());$x=new \DOMXPath($dom);
            $input=$x->query('//input[@name="anio"]')->item(0);$this->assertSame((string)$anio,$input->getAttribute('value'));$this->assertTrue($input->hasAttribute('required'));$this->assertFalse($input->hasAttribute('readonly'));
            $r->assertSee(e(route('admin.reportes.alumnos.pdf',$r->viewData('filtros'))),false);
            $this->get(route('admin.reportes.alumnos.pdf'))->assertOk()->assertHeader('Content-Type','application/pdf');
    }
    public function test_anio_anterior_paginacion_pdf_y_limpiar(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2027-04-01 12:00:00',config('app.timezone')));
        $curso=Curso::factory()->create();foreach(range(1,26) as $_)$this->alumno(2025,$curso);$this->alumno(2027);
        $f=['anio'=>2025,'mes'=>6,'curso_id'=>$curso->id,'estado'=>'activo'];
        $pagina=$this->get(route('admin.reportes.show',['tipo'=>'alumnos']+$f))->assertOk()->assertViewHas('filtros',fn($v)=>(int)$v['anio']===2025);
        $this->assertCount(25,$pagina->viewData('reporte')['filas']);
        $next=$pagina->viewData('reporte')['filas']->nextPageUrl();parse_str(parse_url($next,PHP_URL_QUERY),$query);
        foreach($f as $clave=>$valor)$this->assertEquals($valor,$query[$clave]);
        $this->get($next)->assertOk()->assertViewHas('reporte',fn($r)=>$r['filas']->count()===1 && $r['filas']->total()===26);
        $pdfUrl=route('admin.reportes.alumnos.pdf',$pagina->viewData('filtros'));$pagina->assertSee(e($pdfUrl),false);
        $wrapper=app('dompdf.wrapper');Pdf::shouldReceive('loadView')->once()->andReturnUsing(function($vista,$datos)use($f,$wrapper){$this->assertEquals($f,$datos['filtros']);$this->assertSame(26,$datos['totalAlumnos']);return $wrapper->loadHTML(view($vista,$datos)->render());});
        $this->get($pdfUrl)->assertOk();
        $dom=new \DOMDocument;@$dom->loadHTML($pagina->getContent());$x=new \DOMXPath($dom);$limpiar=$x->query('//form//a[normalize-space(.)="Limpiar"]')->item(0);$this->assertNotNull($limpiar);
        $this->get($limpiar->getAttribute('href'))->assertOk()->assertViewHas('filtros',fn($v)=>$v===['anio'=>2027])->assertViewHas('reporte',fn($r)=>$r['filas']->total()===1);
        $this->get(route('admin.reportes.show',['tipo'=>'alumnos','anio'=>2024]))->assertOk()->assertSee('No hay alumnos inscritos para el año 2024');
    }
    public function test_invalidos_y_mes_sin_anio_no_eliminan_el_filtro(): void
    {
        foreach([['anio'=>''],['anio'=>'abc'],['anio'=>0],['anio'=>2101],['anio'=>['2025']],['mes'=>6]] as $f) {
            foreach([route('admin.reportes.show','alumnos'),route('admin.reportes.alumnos.pdf')] as $url)$this->getJson($url.'?'.http_build_query($f))->assertUnprocessable()->assertJsonValidationErrors('anio');
        }
        // Maestros conserva sus filtros sin incorporar el año de Alumnos.
        $this->get(route('admin.reportes.show','maestros'))->assertOk()->assertViewHas('filtros',fn($f)=>$f===[]);
    }
    public function test_acceso_exclusivo_administrador(): void
    {
        foreach(['docente','alumno'] as $rol){$this->actingAs(User::factory()->create(['role'=>$rol]));$this->get(route('admin.reportes.show','alumnos'))->assertForbidden();$this->get(route('admin.reportes.alumnos.pdf'))->assertForbidden();}
        auth()->forgetGuards();$this->get(route('admin.reportes.show','alumnos'))->assertRedirect(route('login'));$this->get(route('admin.reportes.alumnos.pdf'))->assertRedirect(route('login'));
    }
}
