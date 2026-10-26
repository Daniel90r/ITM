<?php

namespace Tests\Feature;

use App\Models\{Alumno, AsignacionDocente, Asistencia, Curso, DetalleCurso, Inscripcion, Mensualidad, Nota, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CursoFechaInicioProtegidaTest extends TestCase
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
        parent::setUp(); $this->withoutVite();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 12:00:00'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function curso(array $extra = []): Curso
    {
        return Curso::factory()->create($extra + ['nombre' => 'Curso original', 'nivel' => 'Matutino', 'seccion' => 'A',
            'anio_lectivo' => 2026, 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-12-31', 'estado' => null]);
    }

    private function datos(Curso $curso, array $extra = []): array
    {
        return $extra + ['nombre' => 'Nombre actualizado', 'nivel' => 'Matutino', 'seccion' => 'A', 'anio_lectivo' => 2026,
            'fecha_inicio' => $curso->fecha_inicio->format('Y-m-d'), 'fecha_fin' => $curso->fecha_fin->format('Y-m-d')];
    }

    public function test_activo_rechaza_cambio_incluso_en_el_mismo_envio_que_solicita_proximo(): void
    {
        $curso = $this->curso();
        foreach ([[], ['estado' => 'PROXIMO']] as $estado) {
            $this->putJson(route('admin.cursos.update', $curso), $this->datos($curso, $estado + ['fecha_inicio' => '2026-11-01']))
                ->assertUnprocessable()->assertJsonValidationErrors('fecha_inicio');
            $this->assertSame('2026-09-01', $curso->fresh()->fecha_inicio->format('Y-m-d'));
            $this->assertSame('Curso original', $curso->fresh()->nombre);
        }
    }

    public function test_otro_campo_en_activo_se_guarda_sin_alterar_historial_academico_o_financiero(): void
    {
        $curso = $this->curso(); $a = Alumno::factory()->create(); $dc = DetalleCurso::factory()->create(['curso_id' => $curso->id]);
        Inscripcion::factory()->create(['alumno_id' => $a->id, 'curso_id' => $curso->id, 'estado' => 'aprobada']);
        AsignacionDocente::create(['detalle_curso_id' => $dc->id, 'maestro_id' => $dc->maestro_id, 'estado' => 'activa']);
        Nota::factory()->create(['alumno_id' => $a->id, 'detalle_curso_id' => $dc->id]);
        Asistencia::create(['alumno_id' => $a->id, 'curso_id' => $curso->id, 'detalle_curso_id' => $dc->id, 'registrado_por' => $dc->maestro_id, 'fecha' => '2026-10-03', 'estado' => 'presente']);
        Mensualidad::factory()->create(['alumno_id' => $a->id, 'curso_id' => $curso->id]);
        $tablas = ['inscripciones','detalle_cursos','asignaciones_docentes','notas','asistencias','mensualidades','pagos'];
        $antes = []; foreach ($tablas as $tabla) $antes[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        $this->put(route('admin.cursos.update', $curso), $this->datos($curso))->assertRedirect(route('admin.cursos'))->assertSessionHasNoErrors();
        $this->assertSame('Nombre actualizado', $curso->fresh()->nombre);
        $this->assertSame('2026-09-01', $curso->fresh()->fecha_inicio->format('Y-m-d'));
        foreach ($tablas as $tabla) $this->assertSame($antes[$tabla], DB::table($tabla)->orderBy('id')->get()->toJson(), $tabla);
    }

    public function test_proximo_permite_fecha_y_conserva_validacion_de_finalizacion(): void
    {
        $curso = $this->curso(['fecha_inicio' => '2026-11-01']);
        $this->put(route('admin.cursos.update', $curso), $this->datos($curso, ['fecha_inicio' => '2026-11-08']))
            ->assertRedirect(route('admin.cursos'))->assertSessionHasNoErrors();
        $this->assertSame('2026-11-08', $curso->fresh()->fecha_inicio->format('Y-m-d'));
        $this->putJson(route('admin.cursos.update', $curso), $this->datos($curso, ['fecha_inicio' => '2027-01-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
    }

    public function test_finalizado_conserva_bloqueo_terminal_y_fecha(): void
    {
        foreach ([['fecha_fin' => '2026-10-01'], ['finalizado_manualmente' => true]] as $extra) {
            $curso = $this->curso($extra);
            $this->put(route('admin.cursos.update', $curso), $this->datos($curso, ['fecha_inicio' => '2026-09-02', 'estado' => 'PROXIMO']))
                ->assertSessionHas('error');
            $this->assertSame('2026-09-01', $curso->fresh()->fecha_inicio->format('Y-m-d'));
        }
    }

    public function test_formulario_bloquea_inicio_con_valor_guardado_y_mensaje_por_estado(): void
    {
        foreach ([['estado' => 'ACTIVO'], ['fecha_fin' => '2026-10-01'], ['fecha_inicio' => '2026-11-01']] as $extra) {
            $curso = $this->curso($extra); $estado = $curso->estado_curso;
            $r = $this->get(route('admin.cursos.edit', $curso))->assertOk();
            $dom = new \DOMDocument; @$dom->loadHTML($r->getContent()); $xp = new \DOMXPath($dom);
            $input = $xp->query('//input[@name="fecha_inicio"]')->item(0);
            $this->assertSame($estado !== 'PROXIMO', $input->hasAttribute('readonly'));
            $this->assertSame($curso->fecha_inicio->format('Y-m-d'), $input->getAttribute('value'));
            if ($estado !== 'PROXIMO') $r->assertSee('La fecha de inicio no puede modificarse porque el curso ya está '.($estado === 'ACTIVO' ? 'activo' : 'finalizado'));
        }
    }
}
