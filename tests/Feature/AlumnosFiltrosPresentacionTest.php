<?php
namespace Tests\Feature;

use App\Models\{Alumno, Curso, Inscripcion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlumnosFiltrosPresentacionTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite',DB::connection()->getDriverName());
        $this->assertSame(':memory:',DB::connection()->getDatabaseName());
        $this->assertNotSame(base_path('storage'),storage_path());
    }

    public function test_distribucion_conserva_campos_eventos_e_historial(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        foreach ([['Alfonso',2026,false],['Beatriz',2025,false],['Carla',2024,true]] as [$nombre,$anio,$historico]) {
            $c=Curso::factory()->create(['estado'=>'ACTIVO','anio_lectivo'=>$anio,'finalizado_manualmente'=>$historico]);
            $a=Alumno::factory()->create(['nombre'=>$nombre,'apellido'=>'Sintético','activo'=>$nombre!=='Beatriz']);
            Inscripcion::factory()->create(['curso_id'=>$c->id,'alumno_id'=>$a->id,'estado'=>'aprobada','activa'=>true]);
            if ($nombre==='Alfonso') $actual=$c;
        }
        Alumno::factory()->create(['nombre'=>'Daniel','apellido'=>'Sin inscripción']);
        $antes=DB::table('inscripciones')->get()->toJson();
        $r=$this->get(route('admin.alumnos'))->assertOk()->assertSee('Listado de Alumnos')->assertSee('Año lectivo')->assertSee('Limpiar filtros');
        $dom=new \DOMDocument();@$dom->loadHTML($r->getContent());$x=new \DOMXPath($dom);
        foreach (['select[@name="curso_id"]','select[@name="anio"]','input[@name="q"]','input[@name="incluir_historicos" and @type="checkbox"]'] as $campo) {
            $this->assertSame(1,$x->query('//form[@id="alumnos-alcance"]//'.$campo)->length);
        }
        $this->assertSame('filtrarAlumnos()',$x->query('//*[@id="buscarAlumno"]')->item(0)->getAttribute('oninput'));
        $this->assertSame('filtrarAlumnos()',$x->query('//*[@id="filtrarAnio"]')->item(0)->getAttribute('onchange'));
        $this->assertSame('this.form.requestSubmit()',$x->query('//*[@id="alumnos-curso-consulta"]')->item(0)->getAttribute('onchange'));
        $this->assertSame('button',$x->query('//button[contains(@class,"alumnos-limpiar")]')->item(0)->getAttribute('type'));
        $this->assertSame(route('admin.alumnos'),$x->query('//form[@id="alumnos-alcance"]')->item(0)->getAttribute('action'));
        $this->assertCount(3,$r['alumnos']);
        $this->assertSame(2,$r['activos']);$this->assertSame(1,$r['inactivos']);
        $r->assertSee('Daniel')->assertDontSee('Carla');
        $this->captura('activos',$r);
        $cursoActivo=$this->get(route('admin.alumnos',['curso_id'=>$actual->id]))->assertOk();$this->captura('curso',$cursoActivo);
        $anio=$this->get(route('admin.alumnos',['anio'=>2025]))->assertOk();$this->captura('anio',$anio);
        $busqueda=$this->get(route('admin.alumnos',['q'=>'Alfonso']))->assertOk();$this->captura('busqueda',$busqueda);
        foreach (['totalAlumnos','activos','inactivos'] as $dato) {
            $ajax=$this->get(route('admin.alumnos',['curso_id'=>$actual->id]),['X-Requested-With'=>'XMLHttpRequest','Accept'=>'application/json'])->assertOk();
            $this->assertSame($cursoActivo[$dato],$ajax[$dato]);
        }
        $ajax->assertSee('data-alumnos-resultados')->assertSee('data-alumnos-resumen')->assertSee('Estado y acceso');
        $h=$this->get(route('admin.alumnos',['incluir_historicos'=>1]))->assertOk()->assertSee('Carla');
        $this->assertCount(4,$h['alumnos']);$this->captura('historicos',$h);
        $this->captura('historico-curso',$this->get(route('admin.alumnos',['curso_id'=>$c->id]))->assertOk());
        $s=$this->get(route('admin.alumnos',['curso_id'=>$c->id,'q'=>'Carla','anio'=>2024]))->assertOk();
        $this->assertCount(1,$s['alumnos']);$this->assertSame(1,$s['filtrosCursos']['incluir_historicos']);$this->captura('seleccionado',$s);
        $v=$this->get(route('admin.alumnos',['q'=>'SinCoincidencias']))->assertOk();$this->assertCount(0,$v['alumnos']);$this->captura('vacio',$v);
        $this->assertSame($antes,DB::table('inscripciones')->get()->toJson());
        if($dir=getenv('ITM_ALUMNOS_FILTROS_PREVIEW_DIR')) file_put_contents($dir.'/filtros.json',json_encode(['activo'=>$actual->id,'historico'=>$c->id]));
    }

    private function captura(string $nombre,$respuesta): void
    {
        if($dir=getenv('ITM_ALUMNOS_FILTROS_PREVIEW_DIR')) file_put_contents($dir.'/'.$nombre.'.html',$respuesta->getContent());
    }
}
