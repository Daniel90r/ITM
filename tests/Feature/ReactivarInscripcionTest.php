<?php

namespace Tests\Feature;

use App\Models\{Alumno, Asistencia, Boleta, Curso, DetalleCurso, Encargado, Inscripcion, Maestro, Materia, Nota, ReactivacionInscripcion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReactivarInscripcionTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private Alumno $alumno;
    private Curso $curso;
    private Inscripcion $inscripcion;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->alumno = Alumno::factory()->create(['fecha_nacimiento' => now()->subYears(25)->toDateString(), 'activo' => true]);
        $this->curso = Curso::factory()->create(['estado' => Curso::ESTADO_PROXIMO, 'activo' => true, 'finalizado_manualmente' => false, 'fecha_fin' => now()->addYear()]);
        $this->inscripcion = Inscripcion::factory()->create([
            'alumno_id' => $this->alumno->id, 'curso_id' => $this->curso->id,
            'estado' => 'inactiva', 'activa' => false, 'fecha_inscripcion' => '2026-09-16',
            'observacion' => 'Historial conservado', 'documento_path' => 'solicitudes/historico.pdf',
        ]);
        $this->actingAs($this->admin);
    }

    private function enviar(array $datos = [])
    {
        return $this->from(route('admin.solicitudes', ['estado' => 'inactiva']))
            ->post(route('admin.solicitudes.reactivar', $this->inscripcion->id), array_merge([
                'motivo_reactivacion' => 'Recuperar desactivación accidental.',
                'reactivacion_contexto' => $this->inscripcion->id,
            ], $datos));
    }

    public static function cursosPermitidos(): array { return [[Curso::ESTADO_PROXIMO], [Curso::ESTADO_ACTIVO]]; }

    #[DataProvider('cursosPermitidos')]
    public function test_reactiva_con_auditoria_sin_cambiar_identidad_ni_fecha(string $estado): void
    {
        $this->curso->update(['estado' => $estado]);
        $antes = $this->inscripcion->fresh()->getAttributes();
        $this->enviar(['estado' => 'rechazada', 'curso_id' => 999, 'administrador_id' => 999])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $despues = $this->inscripcion->fresh()->getAttributes();
        foreach ($antes as $campo => $valor) {
            if (!in_array($campo, ['estado', 'activa', 'updated_at'], true)) $this->assertSame($valor, $despues[$campo]);
        }
        $this->assertSame('aprobada', $despues['estado']);
        $this->assertSame(1, (int) $despues['activa']);
        $this->assertDatabaseCount('inscripciones', 1);
        $audit = ReactivacionInscripcion::sole();
        $this->assertSame($this->inscripcion->id, $audit->inscripcion_id);
        $this->assertSame($this->admin->id, $audit->administrador_id);
        $this->assertSame('inactiva', $audit->estado_anterior);
        $this->assertSame('aprobada', $audit->estado_nuevo);
        $this->assertSame('Recuperar desactivación accidental.', $audit->motivo);
        $this->assertNotNull($audit->reactivada_at);
        $this->get(route('admin.inscripciones'))->assertOk()->assertSee($this->alumno->codigo);
        $respuesta = $this->get(route('admin.solicitudes'))->assertOk();
        $this->assertSame(1, $respuesta->viewData('conteos')['aprobadas']);
        $this->assertSame(0, $respuesta->viewData('conteos')['inactivas']);
    }

    public function test_conserva_notas_asistencias_optativas_y_boletas(): void
    {
        $maestro = Maestro::factory()->create();
        $materia = Materia::factory()->create(['es_opcional' => true]);
        $detalle = DetalleCurso::factory()->create(['curso_id' => $this->curso->id, 'materia_id' => $materia->id, 'maestro_id' => $maestro->id]);
        Nota::factory()->create(['alumno_id' => $this->alumno->id, 'detalle_curso_id' => $detalle->id]);
        Asistencia::create(['alumno_id' => $this->alumno->id, 'curso_id' => $this->curso->id, 'detalle_curso_id' => $detalle->id, 'fecha' => now()->toDateString(), 'estado' => 'presente', 'registrado_por' => $maestro->id]);
        DB::table('alumno_materia')->insert(['alumno_id' => $this->alumno->id, 'curso_id' => $this->curso->id, 'materia_id' => $materia->id, 'created_at' => now(), 'updated_at' => now()]);
        Boleta::create([
            'alumno_id' => $this->alumno->id, 'curso_id' => $this->curso->id, 'inscripcion_id' => $this->inscripcion->id,
            'fecha_emision' => now(), 'alumno_nombre' => 'Histórico', 'alumno_apellido' => 'Conservado', 'alumno_codigo' => 'HIST',
            'curso_nombre' => 'Curso histórico', 'curso_turno' => 'Matutino', 'curso_seccion' => 'A', 'curso_anio_lectivo' => 2026,
            'director_nombre' => 'Director histórico', 'director_firma_snapshot_path' => 'boletas/firmas/historica.png',
            'promedio_general' => 8, 'concepto_general' => 'MB',
        ]);
        $tablas = ['notas', 'asistencias', 'alumno_materia', 'boletas', 'detalles_boletas', 'detalle_cursos'];
        $antes = [];
        foreach ($tablas as $tabla) $antes[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
        $this->enviar()->assertSessionHasNoErrors()->assertSessionHas('success');
        foreach ($tablas as $tabla) $this->assertSame($antes[$tabla], DB::table($tabla)->orderBy('id')->get()->toJson(), $tabla);
    }

    public static function bloqueos(): array
    {
        return array_map(fn ($caso) => [$caso], ['otra_aprobada', 'otra_pendiente', 'sin_cupo', 'finalizado', 'vencido', 'deshabilitado', 'sin_fechas', 'alumno_inactivo', 'sin_nacimiento', 'no_inactiva', 'activa_incoherente', 'sin_encargado', 'encargado_inactivo', 'encargado_incompleto', 'varios_encargados']);
    }

    #[DataProvider('bloqueos')]
    public function test_validaciones_no_modifican_ninguna_inscripcion(string $caso): void
    {
        if (in_array($caso, ['otra_aprobada', 'otra_pendiente'])) Inscripcion::factory()->create(['alumno_id' => $this->alumno->id, 'estado' => $caso === 'otra_aprobada' ? 'aprobada' : 'pendiente', 'activa' => $caso === 'otra_aprobada']);
        if ($caso === 'sin_cupo') for ($i=0; $i<20; $i++) Inscripcion::factory()->create(['curso_id' => $this->curso->id, 'estado' => 'aprobada', 'activa' => true]);
        if ($caso === 'finalizado') $this->curso->update(['finalizado_manualmente' => true]);
        if ($caso === 'vencido') $this->curso->update(['fecha_fin' => now()->subDay()]);
        if ($caso === 'deshabilitado') $this->curso->update(['activo' => false]);
        if ($caso === 'sin_fechas') $this->curso->update(['estado' => null, 'fecha_inicio' => null, 'fecha_fin' => null]);
        if ($caso === 'alumno_inactivo') $this->alumno->update(['activo' => false]);
        if ($caso === 'sin_nacimiento') $this->alumno->update(['fecha_nacimiento' => null]);
        if ($caso === 'no_inactiva') $this->inscripcion->update(['estado' => 'rechazada']);
        if ($caso === 'activa_incoherente') $this->inscripcion->update(['activa' => true]);
        if (str_contains($caso, 'encargado')) {
            $this->alumno->update(['fecha_nacimiento' => now()->subYears(16)->toDateString()]);
            if ($caso !== 'sin_encargado') {
                $enc = Encargado::create(['nombre' => $caso === 'encargado_incompleto' ? '' : 'Oficial', 'apellido' => 'Prueba', 'dui' => '12345678-9', 'activo' => $caso !== 'encargado_inactivo']);
                $this->alumno->encargados()->attach($enc->id, ['parentesco' => 'Tutor']);
                if ($caso === 'varios_encargados') {
                    $otro = Encargado::create(['nombre' => 'Otro', 'apellido' => 'Prueba', 'dui' => '87654321-9', 'activo' => true]);
                    $this->alumno->encargados()->attach($otro->id, ['parentesco' => 'Padre']);
                }
            }
        }
        $antes = DB::table('inscripciones')->orderBy('id')->get()->toJson();
        $this->enviar()->assertSessionHasErrors();
        $this->assertSame($antes, DB::table('inscripciones')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('reactivaciones_inscripcion', 0);
    }

    public function test_menor_con_encargado_valido_y_telefono_opcional(): void
    {
        $this->alumno->update(['fecha_nacimiento' => now()->subYears(16)->toDateString()]);
        $enc = Encargado::create(['nombre' => 'Oficial', 'apellido' => 'Prueba', 'dui' => '12345678-9', 'activo' => true, 'telefono' => null]);
        $this->alumno->encargados()->attach($enc->id, ['parentesco' => 'Tutor']);
        $this->enviar()->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    public static function motivosInvalidos(): array { return [[null], [''], ['          '], ['corto'], [str_repeat('x', 501)], [['array']]]; }

    #[DataProvider('motivosInvalidos')]
    public function test_motivo_obligatorio_y_validado($motivo): void
    {
        $this->enviar(['motivo_reactivacion' => $motivo])->assertSessionHasErrors('motivo_reactivacion');
        $this->assertSame('inactiva', $this->inscripcion->fresh()->estado);
        $this->assertDatabaseCount('reactivaciones_inscripcion', 0);
    }

    public function test_doble_envio_y_reactivacion_previa_quedan_bloqueados(): void
    {
        $this->enviar()->assertSessionHasNoErrors();
        $this->enviar()->assertSessionHasErrors('reactivacion');
        $this->inscripcion->refresh()->update(['estado' => 'inactiva', 'activa' => false]);
        $this->enviar()->assertSessionHasErrors('reactivacion');
        $this->assertSame('inactiva', $this->inscripcion->fresh()->estado);
        $this->assertDatabaseCount('reactivaciones_inscripcion', 1);
    }

    public function test_sin_permiso_o_get_no_se_reactiva(): void
    {
        $url = route('admin.solicitudes.reactivar', $this->inscripcion->id);
        $this->get($url)->assertStatus(405);
        foreach (['alumno', 'docente'] as $rol) {
            $this->actingAs(User::factory()->create(['role' => $rol]));
            $this->enviar()->assertForbidden();
        }
        auth()->forgetGuards();
        $this->post($url, ['motivo_reactivacion' => 'Prueba no autenticada'])->assertRedirect(route('login'));
        $this->assertSame('inactiva', $this->inscripcion->fresh()->estado);
        $this->assertDatabaseCount('reactivaciones_inscripcion', 0);
    }

    public function test_auditoria_fallida_revierte_reactivacion(): void
    {
        ReactivacionInscripcion::creating(function () { throw new \RuntimeException('Fallo de auditoría simulado'); });
        try {
            $this->withoutExceptionHandling();
            $this->enviar();
            $this->fail('Debió fallar la auditoría.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo de auditoría simulado', $e->getMessage());
        } finally { ReactivacionInscripcion::flushEventListeners(); }
        $this->assertSame('inactiva', $this->inscripcion->fresh()->estado);
        $this->assertDatabaseCount('reactivaciones_inscripcion', 0);
    }

    public function test_listado_y_modal_no_escriben_y_solo_ofrecen_accion_para_inactivas(): void
    {
        $antes = $this->inscripcion->fresh()->getAttributes();
        $response = $this->get(route('admin.solicitudes'))->assertOk()->assertSee('Reactivar inscripción')->assertSee('name="motivo_reactivacion"', false);
        $response->assertSee('data-confirm-titulo="Reactivar inscripción"', false)->assertSee('name="_token"', false);
        $this->assertSame($antes, $this->inscripcion->fresh()->getAttributes());
        $this->assertDatabaseCount('reactivaciones_inscripcion', 0);
        $this->enviar();
        $this->get(route('admin.solicitudes'))->assertOk()->assertDontSee('name="motivo_reactivacion"', false)->assertSee('Reactivación registrada');
    }

    public function test_migracion_pendiente_bloquea_operacion_sin_romper_listado(): void
    {
        Schema::drop('reactivaciones_inscripcion'); // Solo SQLite en memoria.
        $this->get(route('admin.solicitudes'))->assertOk()->assertSee('todavía no está habilitada');
        $this->enviar()->assertSessionHasErrors('reactivacion');
        $this->assertSame('inactiva', $this->inscripcion->fresh()->estado);
        // Restaurar el esquema aislado para las siguientes pruebas del proceso.
        (require database_path('migrations/2026_10_17_020000_create_reactivaciones_inscripcion_table.php'))->up();
    }

    public function test_aprobacion_revalida_exclusividad_frente_a_reactivacion(): void
    {
        $pendiente = Inscripcion::factory()->create(['alumno_id' => $this->alumno->id, 'estado' => 'pendiente']);
        $this->enviar()->assertSessionHasErrors('reactivacion');
        $this->post(route('admin.solicitudes.aprobar', $pendiente->id))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->enviar()->assertSessionHasErrors('reactivacion');
        $this->assertSame(1, Inscripcion::aprobada()->where('alumno_id', $this->alumno->id)->count());
    }

    public function test_aprobar_rechaza_otra_aprobada_y_reabrir_no_omite_exclusividad(): void
    {
        $this->enviar()->assertSessionHasNoErrors();
        $pendiente = Inscripcion::factory()->create(['alumno_id' => $this->alumno->id, 'estado' => 'pendiente']);
        $this->post(route('admin.solicitudes.aprobar', $pendiente->id))->assertSessionHasErrors('inscripcion');
        $rechazada = Inscripcion::factory()->create(['alumno_id' => $this->alumno->id, 'estado' => 'rechazada']);
        $this->post(route('admin.solicitudes.reabrir', $rechazada->id))->assertSessionHas('error');
        $this->assertSame('rechazada', $rechazada->fresh()->estado);
        $this->assertSame(1, Inscripcion::aprobada()->where('alumno_id', $this->alumno->id)->count());
    }
}
