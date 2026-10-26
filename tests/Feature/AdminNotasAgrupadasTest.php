<?php
namespace Tests\Feature;

use App\Models\{Alumno, Curso, DetalleCurso, Materia, Maestro, Nota, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminNotasAgrupadasTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    public function test_agrupa_sin_recalcular_y_separa_cursos_y_materias_opcionales(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $alumno=Alumno::factory()->create(['activo'=>false]);
        $a=Nota::factory()->create(['alumno_id'=>$alumno->id]);
        $ingles=Materia::factory()->create(['nombre'=>'Inglés', 'es_opcional'=>true]);
        $detalle=DetalleCurso::factory()->create(['materia_id'=>$ingles->id]);
        $b=Nota::factory()->create(['alumno_id'=>$alumno->id,'detalle_curso_id'=>$detalle->id]);
        DB::table('notas')->where('id',$a->id)->update(['promedio'=>9]);
        $antes=DB::table('notas')->orderBy('id')->get()->toJson();
        $r=$this->get(route('admin.notas'))->assertOk()->assertSee('Inglés')->assertSee('Ver notas')->assertSee('Ocultar notas');
        if ($archivo = getenv('ITM_NOTAS_PREVIEW')) file_put_contents($archivo, $r->getContent());
        $this->assertSame(1, substr_count($r->getContent(),'data-notas-alumno="'.$alumno->id.'"'));
        $this->assertCount(2,$r['alumnos']->first()->notas);
        $this->assertEquals(9,$r['alumnos']->first()->notas->firstWhere('id',$a->id)->promedio);
        $this->assertCount(2,$r['alumnos']->first()->notas->groupBy(fn($n)=>$n->detalleCurso->curso_id));
        $this->assertSame($antes,DB::table('notas')->orderBy('id')->get()->toJson());
        foreach (['curso_id'=>$detalle->curso_id,'materia_id'=>$ingles->id,'maestro_id'=>$detalle->maestro_id] as $campo=>$valor) {
            $f=$this->get(route('admin.notas',[$campo=>$valor]))->assertOk();
            $this->assertSame([$b->id],$f['alumnos']->first()->notas->pluck('id')->all());
        }
        $this->get(route('admin.notas',['q'=>$alumno->codigo]))->assertOk()->assertViewHas('alumnos',fn($a)=>$a->total()===1);
        $this->get(route('admin.notas',['q'=>'NingunaCoincidencia']))->assertOk()->assertSee('No se encontraron alumnos');
        $this->get(route('admin.notas',['curso_id'=>'incorrecto']))->assertSessionHasErrors('curso_id');
    }

    public function test_pagina_alumnos_unicos_y_conserva_todas_sus_notas_y_filtros(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $detalle=DetalleCurso::factory()->create();
        foreach(range(1,26) as $i){
            $alumno=Alumno::factory()->create(['nombre'=>'Sintético']);
            Nota::factory()->create(['alumno_id'=>$alumno->id,'detalle_curso_id'=>$detalle->id]);
            Nota::factory()->create(['alumno_id'=>$alumno->id]);
        }
        $r=$this->get(route('admin.notas',['q'=>'Sintético']))->assertOk();
        $this->assertSame(26,$r['alumnos']->total());
        $this->assertCount(25,$r['alumnos']);
        foreach($r['alumnos'] as $a)$this->assertCount(2,$a->notas);
        $this->assertStringContainsString('q=', $r['alumnos']->nextPageUrl());
        $s=$this->get(route('admin.notas',['q'=>'Sintético','page'=>2]))->assertOk();
        $this->assertCount(1,$s['alumnos']);
        $this->assertEmpty($r['alumnos']->pluck('id')->intersect($s['alumnos']->pluck('id')));
    }

    public function test_solo_administradores(): void
    {
        $this->get(route('admin.notas'))->assertRedirect(route('login'));
        foreach(['docente','alumno'] as $role){
            $this->actingAs(User::factory()->create(['role'=>$role]));
            $this->get(route('admin.notas'))->assertForbidden();
        }
    }
}
