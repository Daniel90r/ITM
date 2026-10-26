<?php

namespace Tests\Feature;

use App\Models\{Alumno, AsignacionDocente, Asistencia, Curso, DetalleCurso, Inscripcion, Mensualidad, Nota, Pago, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CursoFinalizacionOriginalTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertNotSame(base_path('storage'), storage_path());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-25 12:00:00'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function curso(array $extra = []): Curso
    {
        return Curso::factory()->create($extra + ['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-12-31', 'estado' => null, 'finalizado_manualmente' => false]);
    }

    public function test_finaliza_sin_columnas_nuevas_sin_motivo_y_conserva_fechas(): void
    {
        foreach (['fecha_finalizacion_real', 'motivo_finalizacion', 'finalizado_por'] as $columna) {
            $this->assertFalse(Schema::hasColumn('cursos', $columna));
        }
        $curso = $this->curso();
        $this->patch(route('admin.cursos.finalizar', $curso))->assertRedirect()->assertSessionHas('success');
        $curso->refresh();
        $this->assertTrue($curso->finalizado_manualmente);
        $this->assertSame(Curso::ESTADO_FINALIZADO, $curso->estado_curso);
        $this->assertSame('2026-09-01', $curso->fecha_inicio->toDateString());
        $this->assertSame('2026-12-31', $curso->fecha_fin->toDateString());
        $antes = $curso->getRawOriginal();
        $this->patch(route('admin.cursos.finalizar', $curso))->assertSessionHas('error');
        $this->assertSame($antes, $curso->fresh()->getRawOriginal());
        $this->get(route('admin.cursos.show', $curso))->assertOk();
    }

    public function test_conserva_rechazo_original_de_proximos_y_finalizados_por_fecha(): void
    {
        foreach ([['fecha_inicio' => '2026-11-01'], ['fecha_fin' => '2026-10-01'], ['finalizado_manualmente' => true]] as $extra) {
            $curso = $this->curso($extra)->fresh(); $antes = $curso->getRawOriginal();
            $this->patch(route('admin.cursos.finalizar', $curso))->assertRedirect()->assertSessionHas('error');
            $this->assertSame($antes, $curso->fresh()->getRawOriginal());
        }
    }

    public function test_formulario_conserva_confirmacion_csrf_y_patch_sin_motivo(): void
    {
        $this->curso();
        $r = $this->get(route('admin.cursos'))->assertOk();
        $r->assertSee('data-confirm-titulo="¿Estás seguro de finalizar este curso?"', false)
            ->assertSee('data-finalizar-confirm="El curso cambiará al estado Finalizado. ¿Deseas continuar?"', false)
            ->assertSee('data-confirm-boton="Sí, finalizar curso"', false)
            ->assertDontSee('data-confirm=', false)
            ->assertSee('name="_token"', false)->assertSee('value="PATCH"', false);
        foreach (['motivo_finalizacion', 'fecha_finalizacion_real', 'finalizado_por', 'Motivo de finalización'] as $texto) $r->assertDontSee($texto);
    }

    public function test_solo_administradores_pueden_finalizar(): void
    {
        $curso = $this->curso();
        foreach (['docente', 'alumno'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->patch(route('admin.cursos.finalizar', $curso))->assertForbidden();
        }
        auth()->logout();
        $this->patch(route('admin.cursos.finalizar', $curso))->assertRedirect(route('login'));
        $this->assertFalse($curso->fresh()->finalizado_manualmente);
    }

    public function test_finalizacion_no_altera_registros_academicos_o_financieros(): void
    {
        $curso = $this->curso(); $a = Alumno::factory()->create(); $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id]);
        Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => $curso->id, 'estado' => 'aprobada']);
        AsignacionDocente::create(['detalle_curso_id' => $dc->id, 'maestro_id' => $dc->maestro_id, 'estado' => 'activa']);
        Nota::factory()->create(['alumno_id' => $a->id, 'detalle_curso_id' => $dc->id]);
        Asistencia::create(['alumno_id' => $a->id, 'curso_id' => $curso->id, 'detalle_curso_id' => $dc->id, 'registrado_por' => $dc->maestro_id, 'fecha' => '2026-10-03', 'estado' => 'presente']);
        $m = Mensualidad::factory()->create(['alumno_id' => $a->id, 'curso_id' => $curso->id]);
        Pago::create(['mensualidad_id' => $m->id, 'fecha_pago' => '2026-10-03', 'monto_pagado' => 25]);
        $tablas = ['alumnos','inscripciones','detalle_cursos','asignaciones_docentes','notas','asistencias','mensualidades','pagos'];
        $antes = []; foreach ($tablas as $t) $antes[$t] = DB::table($t)->orderBy('id')->get()->toJson();
        $this->patch(route('admin.cursos.finalizar', $curso))->assertSessionHas('success');
        foreach ($tablas as $t) $this->assertSame($antes[$t], DB::table($t)->orderBy('id')->get()->toJson(), $t);
    }
}
