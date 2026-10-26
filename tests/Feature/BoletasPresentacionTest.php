<?php

namespace Tests\Feature;

use App\Models\{Alumno, Curso, DetalleCurso, Inscripcion, Nota, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BoletasPresentacionTest extends TestCase
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
        $this->actingAs(User::factory()->create(['role'=>'admin']));
    }

    private function curso(string $nombre, bool $historico = false): Curso
    {
        $curso=Curso::factory()->create(['nombre'=>$nombre,'estado'=>'ACTIVO','finalizado_manualmente'=>$historico]);
        $alumno=Alumno::factory()->create();
        Inscripcion::factory()->create(['alumno_id'=>$alumno->id,'curso_id'=>$curso->id,'estado'=>'aprobada','activa'=>true]);
        $detalle=DetalleCurso::factory()->create(['curso_id'=>$curso->id]);
        Nota::factory()->create(['alumno_id'=>$alumno->id,'detalle_curso_id'=>$detalle->id,'laboratorio'=>8,'examen_teorico'=>8,'practica'=>8]);
        return $curso;
    }

    private function captura(string $nombre, $respuesta): void
    {
        if ($dir=getenv('ITM_BOLETAS_PREVIEW_DIR')) file_put_contents($dir.'/'.$nombre.'.html',$respuesta->getContent());
    }

    public function test_panel_compacto_conserva_campos_rutas_y_valores(): void
    {
        $curso=$this->curso('Curso sintético de Boletas');
        $antes=DB::table('notas')->get()->toJson();
        $r=$this->get(route('admin.boletas'))->assertOk()->assertSee('1 curso · 1 alumno')
            ->assertSee('Filtrar cursos')->assertSee('Buscar curso')->assertSee('Incluir cursos históricos')
            ->assertSee('Activo')->assertSee('Promedio general')->assertSee('Ver alumnos');
        $dom=new \DOMDocument(); @$dom->loadHTML($r->getContent()); $x=new \DOMXPath($dom);
        foreach (['select[@name="curso_id"]','input[@id="buscadorCurso"]','input[@name="incluir_historicos" and @type="checkbox"]'] as $control) {
            $this->assertSame(1,$x->query('//form[@id="boletas-filtros-form"]//'.$control)->length);
        }
        $this->assertSame(1,$x->query('//*[@id="gridCursos"]/*[contains(@class,"card-curso")]')->length);
        $this->assertSame('ACTIVO',$x->query('//*[contains(@class,"boleta-curso-estado")]')->item(0)->getAttribute('data-estado'));
        $this->assertSame(8.0,(float)$r['grupos']->first()['alumnos']->first()->notas->avg('promedio'));
        $this->assertSame($antes,DB::table('notas')->get()->toJson());
        $this->assertSame(route('admin.boletas'),$x->query('//form[@id="boletas-filtros-form"]')->item(0)->getAttribute('action'));
        $this->captura('uno',$r);
        $historico=$this->curso('Curso histórico sintético',true);
        $h=$this->get(route('admin.boletas',['curso_id'=>$historico->id]))->assertOk()->assertSee('Finalizado');
        $this->assertSame(1,$h['filtrosCursos']['incluir_historicos']);
        $this->captura('historico',$h);
        foreach(range(1,5) as $i) $this->curso('Curso sintético adicional '.$i);
        $m=$this->get(route('admin.boletas',['incluir_historicos'=>1]))->assertOk()->assertSee('7 cursos · 7 alumnos');
        $this->captura('varios',$m);
    }

    public function test_estado_vacio_y_permisos_se_conservan(): void
    {
        $r=$this->get(route('admin.boletas'))->assertOk()->assertSee('0 cursos · 0 alumnos')
            ->assertSee('No hay cursos con alumnos inscritos para los filtros seleccionados.');
        $this->captura('vacio',$r);
        foreach(['alumno','docente'] as $rol) {
            $this->actingAs(User::factory()->create(['role'=>$rol]));
            $this->get(route('admin.boletas'))->assertForbidden();
        }
    }
}
