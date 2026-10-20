<?php

namespace Tests\Feature;

use App\Models\{Alumno, AsignacionDocente, Curso, DetalleCurso, Inscripcion, Maestro, Materia, Nota, User};
use App\Services\ResumenCursoAlumno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MiCursoAlumnoTest extends TestCase
{
    use RefreshDatabase;

    private Alumno $alumno;
    private Curso $curso;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'alumno']);
        $this->alumno = Alumno::factory()->create(['user_id' => $user->id, 'nombre' => 'Alumno Ficticio', 'apellido' => 'Prueba de Curso']);
        $this->curso = Curso::factory()->create(['nombre' => 'Curso Ficticio de Computación', 'estado' => Curso::ESTADO_ACTIVO, 'nivel' => 'Matutino']);
        $this->actingAs($user);
    }

    private function inscribir(string $estado = 'aprobada', ?Curso $curso = null, array $extra = []): Inscripcion
    {
        return Inscripcion::factory()->create(array_merge([
            'alumno_id' => $this->alumno->id, 'curso_id' => ($curso ?? $this->curso)->id,
            'estado' => $estado, 'activa' => $estado === 'aprobada',
        ], $extra));
    }

    private function materia(string $nombre = 'Materia Obligatoria', bool $opcional = false, ?Maestro $docente = null): DetalleCurso
    {
        $materia = Materia::factory()->create(['nombre' => $nombre, 'activa' => true, 'es_opcional' => $opcional]);
        $detalle = DetalleCurso::factory()->create(['curso_id' => $this->curso->id, 'materia_id' => $materia->id, 'maestro_id' => $docente?->id]);
        if ($docente) {
            AsignacionDocente::create(['detalle_curso_id' => $detalle->id, 'maestro_id' => $docente->id, 'estado' => 'activa']);
        }
        return $detalle;
    }

    public function test_aprobada_muestra_datos_reales_y_docente_valido(): void
    {
        $this->inscribir();
        $maestro = Maestro::factory()->create(['nombre' => 'Docente Ficticio', 'apellido' => 'Vigente', 'activo' => true]);
        $this->materia('Herramientas Digitales', false, $maestro);
        $respuesta = $this->get(route('alumno.dashboard'))->assertOk()->assertSee('Mi curso')
            ->assertSee('Inscripción aprobada')->assertSee($this->curso->nombre)
            ->assertSee($this->curso->fecha_inicio->format('d/m/Y'))->assertSee('Matutino')
            ->assertSee('Herramientas Digitales')->assertSee($maestro->nombre_completo)
            ->assertSee('Horario de clases')->assertDontSee('7:30');
        $this->exportar('aprobada', $respuesta->getContent());
    }

    public static function estadosSinVigencia(): array
    {
        return [['pendiente', 'Solicitud pendiente'], ['inactiva', 'Inscripción inactiva'], ['rechazada', 'Solicitud rechazada']];
    }

    #[DataProvider('estadosSinVigencia')]
    public function test_estados_no_aprobados_no_se_presentan_como_inscrito(string $estado, string $etiqueta): void
    {
        $inscripcion = $this->inscribir($estado);
        $this->materia('Materia no vigente');
        $response = $this->get(route('alumno.dashboard'))->assertOk()->assertSee($etiqueta)->assertDontSee('Materia no vigente');
        $this->assertNull($response->viewData('inscripcion'));
        $this->assertFalse($response->viewData('miCurso')['vigente']);
        $this->assertSame($estado, $inscripcion->fresh()->estado);
        $this->exportar($estado, $response->getContent());
    }

    public function test_sin_inscripciones_no_inventa_un_curso(): void
    {
        $respuesta = $this->get(route('alumno.dashboard'))->assertOk()->assertSee('No tienes una inscripción vigente que pueda mostrarse.')
            ->assertDontSee($this->curso->nombre);
        $this->exportar('sin-inscripcion', $respuesta->getContent());
    }

    public function test_historial_de_otro_curso_no_se_mezcla_con_la_vigente(): void
    {
        $anterior = Curso::factory()->create(['nombre' => 'Curso Histórico Ajeno al Resumen']);
        $this->inscribir('inactiva', $anterior, ['fecha_inscripcion' => '2025-01-01']);
        $actual = $this->inscribir();
        $response = $this->get(route('alumno.dashboard'))->assertOk()->assertSee($this->curso->nombre)->assertDontSee($anterior->nombre);
        $this->assertSame($actual->id, $response->viewData('miCurso')['inscripcion']->id);
    }

    public function test_aprobada_vigente_se_prioriza_ante_una_pendiente_sin_mezclar_cursos(): void
    {
        $actual = $this->inscribir();
        $pendiente = Curso::factory()->create(['nombre' => 'Curso Pendiente Separado']);
        $this->inscribir('pendiente', $pendiente);
        $response = $this->get(route('alumno.dashboard'))->assertOk()->assertSee('Existe también una solicitud pendiente.')
            ->assertDontSee($pendiente->nombre);
        $this->assertSame($actual->id, $response->viewData('miCurso')['inscripcion']->id);
    }

    public function test_sin_vigente_solo_muestra_el_ultimo_registro_historico(): void
    {
        $antiguo = Curso::factory()->create(['nombre' => 'Histórico Antiguo Oculto']);
        $this->inscribir('rechazada', $antiguo, ['fecha_inscripcion' => '2025-01-01']);
        $ultimo = $this->inscribir('inactiva', extra: ['fecha_inscripcion' => '2026-01-01']);
        $response = $this->get(route('alumno.dashboard'))->assertOk()->assertSee('Inscripción inactiva')->assertDontSee($antiguo->nombre);
        $this->assertSame($ultimo->id, $response->viewData('miCurso')['inscripcion']->id);
        $this->assertFalse($response->viewData('miCurso')['vigente']);
    }

    public function test_ingles_requiere_pivote_del_alumno_y_del_curso_aunque_haya_nota_o_flag(): void
    {
        $this->inscribir(extra: ['tomara_ingles' => true]);
        $ingles = $this->materia('Inglés Opcional', true);
        $maestro = Maestro::factory()->create();
        Nota::factory()->create(['alumno_id' => $this->alumno->id, 'detalle_curso_id' => $ingles->id, 'registrado_por' => $maestro->id]);
        $otro = Alumno::factory()->create();
        $otro->materias()->attach($ingles->materia_id, ['curso_id' => $this->curso->id]);
        $cursoHistorico = Curso::factory()->create();
        $this->alumno->materias()->attach($ingles->materia_id, ['curso_id' => $cursoHistorico->id]);
        $this->get(route('alumno.dashboard'))->assertOk()->assertDontSee('Inglés Opcional');
        $this->alumno->materias()->attach($ingles->materia_id, ['curso_id' => $this->curso->id]);
        $this->get(route('alumno.dashboard'))->assertOk()->assertSee('Inglés Opcional');
    }

    public function test_datos_incompletos_y_sin_docente_se_muestran_sin_inventarlos(): void
    {
        $this->curso->update(['fecha_inicio' => null, 'fecha_fin' => null, 'seccion' => '', 'nivel' => '']);
        $this->inscribir();
        $this->materia('Materia sin docente');
        $response = $this->get(route('alumno.dashboard'))->assertOk()->assertSee('Materia sin docente')->assertSee('No disponible');
        $this->assertNull($response->viewData('miCurso')['materias'][0]['docente']);
        $this->exportar('incompleto', $response->getContent());
    }

    public function test_asignaciones_inconsistentes_no_muestran_un_docente_arbitrario(): void
    {
        $this->inscribir();
        $docente = Maestro::factory()->create(['nombre' => 'Docente que no debe aparecer']);
        $detalle = $this->materia('Materia con conflicto', false, $docente);
        AsignacionDocente::create(['detalle_curso_id' => $detalle->id, 'maestro_id' => $docente->id, 'estado' => 'activa']);
        $this->get(route('alumno.dashboard'))->assertOk()->assertDontSee($docente->nombre)
            ->assertSee('Hay una asignación docente que requiere revisión');
    }

    public function test_docente_inactivo_no_se_presenta_como_vigente(): void
    {
        $this->inscribir();
        $docente = Maestro::factory()->create(['nombre' => 'Docente Inactivo Oculto', 'activo' => false]);
        $this->materia('Materia sin docente habilitado', false, $docente);
        $this->get(route('alumno.dashboard'))->assertOk()->assertDontSee($docente->nombre)->assertSee('requiere revisión');
    }

    public function test_varias_aprobadas_vigentes_producen_aviso_sin_elegir_arbitrariamente(): void
    {
        $this->inscribir();
        $this->inscribir(curso: Curso::factory()->create(['estado' => Curso::ESTADO_ACTIVO]));
        $response = $this->get(route('alumno.dashboard'))->assertOk()->assertSee('Hay varias inscripciones aprobadas vigentes.');
        $this->assertNull($response->viewData('inscripcion'));
        $this->assertEmpty($response->viewData('miCurso')['materias']);
        $this->exportar('conflicto', $response->getContent());
    }

    public function test_curso_finalizado_o_inscripcion_con_flag_inactivo_no_se_presenta_vigente(): void
    {
        $inscripcion = $this->inscribir(extra: ['activa' => false]);
        $this->get(route('alumno.dashboard'))->assertOk()->assertSee('información inconsistente');
        $inscripcion->update(['activa' => true]);
        $this->curso->update(['finalizado_manualmente' => true]);
        $response = $this->get(route('alumno.dashboard'))->assertOk()->assertSee('Sin inscripción vigente');
        $this->assertFalse($response->viewData('miCurso')['vigente']);
    }

    public function test_parametros_manipulados_no_permiten_consultar_otro_alumno(): void
    {
        $otro = Alumno::factory()->create();
        $curso = Curso::factory()->create(['nombre' => 'Curso Privado de Otro Alumno']);
        Inscripcion::factory()->create(['alumno_id' => $otro->id, 'curso_id' => $curso->id, 'estado' => 'aprobada', 'activa' => true]);
        $this->get(route('alumno.dashboard', ['alumno_id' => $otro->id, 'curso_id' => $curso->id]))
            ->assertOk()->assertDontSee($curso->nombre);
    }

    public function test_consulta_no_modifica_el_expediente_y_no_tiene_n_mas_uno(): void
    {
        $this->inscribir();
        $docente = Maestro::factory()->create(['activo' => true]);
        $this->materia('Primera materia', false, $docente);
        $consultar = function () {
            DB::enableQueryLog(); DB::flushQueryLog();
            app(ResumenCursoAlumno::class)->consultar($this->alumno);
            $queries = DB::getQueryLog(); DB::disableQueryLog();
            foreach ($queries as $query) $this->assertStringStartsWith('select', strtolower($query['query']));
            return count($queries);
        };
        $n = $consultar();
        for ($i = 0; $i < 12; $i++) $this->materia('Materia adicional '.$i, false, $docente);
        $antes = [Inscripcion::all()->toJson(), DetalleCurso::all()->toJson(), AsignacionDocente::all()->toJson()];
        $this->assertSame($n, $consultar());
        $this->assertSame($antes, [Inscripcion::all()->toJson(), DetalleCurso::all()->toJson(), AsignacionDocente::all()->toJson()]);
    }

    private function exportar(string $nombre, string $html): void
    {
        if (! $directorio = getenv('ITM_MI_CURSO_PREVIEW_DIR')) return;
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $css = 'file:///'.str_replace('\\', '/', public_path('build/'.$manifest['resources/css/app.css']['file']));
        $html = preg_replace('/<link[^>]+https:\/\/fonts\.bunny\.net[^>]*>/', '', $html);
        $html = str_replace('</head>', '<link rel="stylesheet" href="'.$css.'"><style>'.file_get_contents(resource_path('css/alumno/portal.css')).'</style></head>', $html);
        $html = str_replace('http://localhost/images/logo_itm.jpg', 'file:///C:/wamp64/www/ITM/public/images/logo_itm.jpg', $html);
        $script = preg_replace('/^import .*;\s*/m', '', file_get_contents(resource_path('js/alumno/portal.js')));
        $html = str_replace('</body>', '<script>'.$script.'</script></body>', $html);
        file_put_contents($directorio.'/curso-'.$nombre.'.html', $html);
    }
}
