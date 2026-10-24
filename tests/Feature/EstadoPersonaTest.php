<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\Asistencia;
use App\Models\Boleta;
use App\Models\CambioEstadoPersona;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Inscripcion;
use App\Models\Maestro;
use App\Models\Mensualidad;
use App\Models\Nota;
use App\Models\Pago;
use App\Models\User;
use App\Services\CambiarEstadoPersona;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EstadoPersonaTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $this->assertNotSame(base_path('storage'), storage_path());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function persona(string $rol = 'alumno', bool $activo = true): array
    {
        $user = User::factory()->create(['role' => $rol, 'remember_token' => 'token-anterior']);
        $persona = $rol === 'alumno'
            ? Alumno::factory()->create(['user_id' => $user->id, 'activo' => $activo])
            : Maestro::factory()->create(['user_id' => $user->id, 'activo' => $activo]);

        return [$user, $persona];
    }

    private function cambiar($persona, bool $activo): bool
    {
        $service = app(CambiarEstadoPersona::class);

        return $service->ejecutar($persona instanceof Alumno ? 'alumnos' : 'maestros', $persona->id, $activo, ! $activo, 'Motivo administrativo de prueba', User::factory()->create(['role' => 'admin']), $service->revision($persona));
    }

    public static function accesos(): array
    {
        return [['alumno', true], ['alumno', false], ['docente', true], ['docente', false]];
    }

    #[DataProvider('accesos')]
    public function test_login_comprueba_rol_y_estado(string $rol, bool $activo): void
    {
        [$user] = $this->persona($rol, $activo);
        $r = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true]);
        if ($activo) {
            $this->assertAuthenticatedAs($user);
            $r->assertSessionHas('auth_version', 1);
        } else {
            $this->assertGuest();
            $r->assertSessionHasErrors('email');
        }
    }

    public function test_admin_no_necesita_expediente_ni_version_de_sesion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.alumnos'))->assertOk();
    }

    public function test_cuenta_sin_expediente_no_puede_autenticarse(): void
    {
        $u = User::factory()->create(['role' => 'alumno']);
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sesion_antigua_es_rechazada_incluso_despues_de_reactivar(): void
    {
        [$u,$p] = $this->persona();
        $this->cambiar($p, false);
        $this->cambiar($p->fresh(), true);
        $this->assertSame(3, $u->fresh()->auth_version);
        $this->assertNotSame('token-anterior', $u->fresh()->remember_token);
        $this->actingAs($u)->withSession(['auth_version' => 1])->get('/alumno/pagos')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sesion_abierta_no_puede_escribir_tras_suspension(): void
    {
        [$u,$p] = $this->persona();
        $this->cambiar($p, false);
        $this->actingAs($u)->withSession(['auth_version' => 1])->postJson('/alumno/inscribirse', ['curso_id' => 1])->assertUnauthorized();
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_sesion_sin_version_exige_nuevo_login(): void
    {
        [$u] = $this->persona('docente');
        $this->actingAs($u)->withSession(['auth_version' => null])->get('/docente/dashboard')->assertRedirect(route('login'));
    }

    public function test_duplicados_no_crean_eventos_y_el_historial_solo_es_admin(): void
    {
        [$u,$p] = $this->persona();
        $this->assertTrue($this->cambiar($p, false));
        $this->assertFalse($this->cambiar($p->fresh(), false));
        $this->assertTrue($this->cambiar($p->fresh(), true));
        $this->assertFalse($this->cambiar($p->fresh(), true));
        $this->assertDatabaseCount('cambios_estado_personas', 2);
        $this->withSession(['auth_version' => 3])->actingAs($u->fresh())->get(route('admin.personas.estado', ['alumnos', $p->id]))->assertForbidden();
    }

    public function test_conserva_matricula_cupo_y_obligaciones_reales(): void
    {
        [$u,$p] = $this->persona();
        $ins = Inscripcion::factory()->create(['alumno_id' => $p->id, 'estado' => 'aprobada', 'activa' => true]);
        $mes = Mensualidad::factory()->create(['alumno_id' => $p->id, 'curso_id' => $ins->curso_id]);
        $before = [$ins->fresh()->getAttributes(), $mes->fresh()->getAttributes()];
        $this->cambiar($p, false);
        $this->cambiar($p->fresh(), true);
        $this->assertSame($before, [$ins->fresh()->getAttributes(), $mes->fresh()->getAttributes()]);
        $this->assertSame(1, Inscripcion::aprobada()->where('curso_id', $ins->curso_id)->count());
    }

    public static function estadosAsignacion(): array
    {
        return [['activa'], ['programada']];
    }

    #[DataProvider('estadosAsignacion')]
    public function test_asignaciones_impiden_desactivar_aunque_curso_oculto(string $estado): void
    {
        [$u,$p] = $this->persona('docente');
        $d = DetalleCurso::factory()->create(['maestro_id' => $p->id, 'curso_id' => Curso::factory()->create(['activo' => false])->id]);
        AsignacionDocente::create(['maestro_id' => $p->id, 'detalle_curso_id' => $d->id, 'estado' => $estado]);
        try {
            $this->cambiar($p, false);
            $this->fail('Debió rechazar la desactivación');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('estado', $e->errors());
        }
        $this->assertTrue($p->fresh()->activo);
        $this->assertDatabaseCount('cambios_estado_personas', 0);
    }

    public function test_finalizar_programada_preserva_fechas_y_permite_desactivar(): void
    {
        [$u,$p] = $this->persona('docente');
        $d = DetalleCurso::factory()->create(['maestro_id' => $p->id]);
        $a = AsignacionDocente::create(['maestro_id' => $p->id, 'detalle_curso_id' => $d->id, 'estado' => 'programada', 'fecha_inicio' => '2030-01-01']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('admin.asignaciones.finalizar-programada', $a->id), ['motivo' => 'Cancelación previa de programación'])->assertRedirect();
        $this->assertSame('finalizada', $a->fresh()->estado);
        $this->assertSame('2030-01-01', $a->fresh()->fecha_inicio->toDateString());
        $this->assertNull($a->fresh()->fecha_fin);
        $this->cambiar($p, false);
        $this->cambiar($p->fresh(), true);
        $this->assertSame('finalizada', $a->fresh()->estado);
        $this->assertNull($d->fresh()->maestro_id);
    }

    public function test_expediente_sin_cuenta_y_vista_administrativa(): void
    {
        $p = Alumno::factory()->create(['user_id' => null]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.personas.estado', ['alumnos', $p->id]))->assertOk()->assertSee('Expediente sin cuenta');
        $this->cambiar($p, false);
        $this->assertFalse($p->fresh()->activo);
        $this->assertNull(CambioEstadoPersona::first()->auth_version);
    }

    public function test_asociacion_duplicada_impide_cambio(): void
    {
        [$u,$p] = $this->persona();
        Alumno::factory()->create(['user_id' => $u->id]);
        $this->expectException(ValidationException::class);
        $this->cambiar($p, false);
    }

    public function test_fallo_auditoria_revierte_estado_y_revocacion(): void
    {
        [$u,$p] = $this->persona();
        CambioEstadoPersona::creating(fn () => throw new \RuntimeException('Fallo simulado'));
        try {
            $this->cambiar($p, false);
            $this->fail('Debió fallar');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo simulado', $e->getMessage());
        } finally {
            CambioEstadoPersona::flushEventListeners();
        }
        $this->assertTrue($p->fresh()->activo);
        $this->assertSame(1, $u->fresh()->auth_version);
        $this->assertSame('token-anterior', $u->fresh()->remember_token);
    }

    public function test_rutas_validan_motivo_y_no_aceptan_get_para_mutar(): void
    {
        [, $p] = $this->persona();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.personas.estado.update', ['alumnos', $p->id]), ['activo' => 0, 'esperado' => 1, 'revision' => 0, 'motivo' => 'no'])->assertSessionHasErrors('motivo');
        $this->get(route('admin.personas.estado', ['alumnos', $p->id]))->assertOk();
        $this->assertTrue($p->fresh()->activo);
        $this->assertDatabaseCount('cambios_estado_personas', 0);
    }

    public function test_recordarme_valido_y_cookie_antigua_tras_reactivacion(): void
    {
        [$u,$p] = $this->persona();
        $u->forceFill(['remember_token' => 'itm-access-v1:1:'.str_repeat('a', 40)])->save();
        $guard = auth()->guard('web');
        $cookie = $guard->getRecallerName();
        $value = $u->id.'|'.$u->remember_token.'|'.$u->password;
        $this->withCookie($cookie, $value)->get('/alumno/pagos')->assertOk()->assertSessionHas('auth_version', 1);
        $this->cambiar($p, false);
        $this->cambiar($p->fresh(), true);
        $this->app['auth']->forgetGuards();
        $this->app['session']->driver()->flush();
        $this->withCookie($cookie, $value)->get('/alumno/pagos')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_cookie_recordarme_anterior_al_despliegue_exige_login(): void
    {
        [$u] = $this->persona();
        $cookie = auth()->guard('web')->getRecallerName();
        $this->withCookie($cookie, $u->id.'|'.$u->remember_token.'|'.$u->password)->get('/alumno/pagos')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_docente_suspendido_no_accede_a_notas_asistencia_o_perfil(): void
    {
        [$u] = $this->persona('docente', false);
        foreach (['/docente/dashboard', '/docente/notas', '/docente/asistencia', '/profile'] as $url) {
            $this->actingAs($u)->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_ruta_administrativa_cambia_estado_y_muestra_auditoria(): void
    {
        [, $p] = $this->persona();
        $admin = User::factory()->create(['role' => 'admin']);
        $url = route('admin.personas.estado', ['alumnos', $p->id]);
        $this->actingAs($admin)->from($url)->patch($url, ['activo' => 0, 'esperado' => 1, 'revision' => 0, 'motivo' => 'Suspensión administrativa autorizada'])->assertRedirect($url);
        $this->assertFalse($p->fresh()->activo);
        $this->get($url)->assertOk()->assertSee('Suspensión administrativa autorizada')->assertSee($admin->name);
    }

    public function test_cuenta_administrativa_vinculada_no_puede_ser_suspendida(): void
    {
        $u = User::factory()->create(['role' => 'admin']);
        $p = Alumno::factory()->create(['user_id' => $u->id]);
        $this->expectException(ValidationException::class);
        $this->cambiar($p, false);
    }

    public function test_rol_incorrecto_con_expediente_no_entra_al_portal(): void
    {
        $u = User::factory()->create(['role' => 'admin']);
        Alumno::factory()->create(['user_id' => $u->id]);
        $this->actingAs($u)->get('/alumno/pagos')->assertForbidden();
    }

    public function test_finalizar_programada_no_permite_finalizar_activa(): void
    {
        [, $p] = $this->persona('docente');
        $d = DetalleCurso::factory()->create(['maestro_id' => $p->id]);
        $a = AsignacionDocente::create(['maestro_id' => $p->id, 'detalle_curso_id' => $d->id, 'estado' => 'activa']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.asignaciones.finalizar-programada', $a->id), ['motivo' => 'Solicitud administrativa de prueba'])->assertSessionHasErrors('estado');
        $this->assertSame('activa', $a->fresh()->estado);
        $this->assertDatabaseCount('cierres_asignaciones_programadas', 0);
    }

    public function test_conserva_notas_asistencias_pagos_y_documento(): void
    {
        [, $p] = $this->persona();
        $ins = Inscripcion::factory()->create(['alumno_id' => $p->id, 'estado' => 'aprobada', 'activa' => true]);
        $d = DetalleCurso::factory()->create(['curso_id' => $ins->curso_id]);
        Nota::factory()->create(['alumno_id' => $p->id, 'detalle_curso_id' => $d->id]);
        Asistencia::create(['alumno_id' => $p->id, 'curso_id' => $ins->curso_id, 'detalle_curso_id' => $d->id, 'fecha' => now()->toDateString(), 'estado' => 'permiso', 'registrado_por' => $d->maestro_id]);
        $mes = Mensualidad::factory()->create(['alumno_id' => $p->id, 'curso_id' => $ins->curso_id]);
        Pago::create(['mensualidad_id' => $mes->id, 'fecha_pago' => now(), 'monto_pagado' => $mes->monto]);
        Boleta::create([
            'alumno_id' => $p->id, 'curso_id' => $ins->curso_id, 'inscripcion_id' => $ins->id,
            'fecha_emision' => now(), 'alumno_nombre' => $p->nombre, 'alumno_apellido' => $p->apellido, 'alumno_codigo' => $p->codigo,
            'curso_nombre' => 'Curso de prueba', 'curso_turno' => 'Matutino', 'curso_seccion' => 'A', 'curso_anio_lectivo' => now()->year,
            'director_nombre' => 'Director de prueba', 'director_firma_snapshot_path' => 'firma-sintetica.png', 'promedio_general' => 8, 'concepto_general' => 'MB',
        ]);
        Storage::disk('local')->put('solicitud-ficticia.pdf', '%PDF-1.4 synthetic');
        $ins->update(['documento_path' => 'solicitud-ficticia.pdf']);
        $snapshot = fn () => collect(['inscripciones', 'notas', 'asistencias', 'mensualidades', 'pagos', 'boletas'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $hash = hash('sha256', Storage::disk('local')->get('solicitud-ficticia.pdf'));
        $this->cambiar($p, false);
        $this->cambiar($p->fresh(), true);
        $this->assertSame($before, $snapshot());
        $this->assertSame($hash, hash('sha256', Storage::disk('local')->get('solicitud-ficticia.pdf')));
    }

    public static function expedientesConRevision(): array
    {
        return [['alumno', true], ['alumno', false], ['docente', true]];
    }

    #[DataProvider('expedientesConRevision')]
    public function test_rechaza_confirmacion_anterior_a_un_ciclo_completo(string $rol, bool $conCuenta): void
    {
        [, $p] = $conCuenta ? $this->persona($rol) : [null, Alumno::factory()->create(['user_id' => null])];
        $service = app(CambiarEstadoPersona::class);
        $tipo = $rol === 'alumno' ? 'alumnos' : 'maestros';
        $admin = User::factory()->create(['role' => 'admin']);
        $revisionInicial = $service->revision($p);
        $this->cambiar($p, false);
        $this->cambiar($p->fresh(), true);
        $before = $p->fresh()->getAttributes();
        try {
            $service->ejecutar($tipo, $p->id, false, true, 'Confirmación obsoleta original', $admin, $revisionInicial);
            $this->fail('No debe aceptar la revisión anterior al ciclo');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('revision', $e->errors());
        }
        $this->assertSame($before, $p->fresh()->getAttributes());
        $this->assertDatabaseCount('cambios_estado_personas', 2);
    }

    public function test_dos_confirmaciones_con_misma_revision_solo_permiten_una_transicion(): void
    {
        [, $p] = $this->persona();
        $admin = User::factory()->create(['role' => 'admin']);
        $url = route('admin.personas.estado.update', ['alumnos', $p->id]);
        $payload = ['activo' => 0, 'esperado' => 1, 'revision' => 0, 'motivo' => 'Confirmación administrativa original'];
        $this->actingAs($admin)->patch($url, $payload)->assertSessionHasNoErrors();
        $this->patch($url, $payload)->assertSessionHasErrors('revision');
        $this->assertDatabaseCount('cambios_estado_personas', 1);
        $this->assertFalse($p->fresh()->activo);
    }

    public function test_revision_es_obligatoria_y_eventos_ajenos_no_la_cambian(): void
    {
        [, $p] = $this->persona();
        [, $otro] = $this->persona();
        $service = app(CambiarEstadoPersona::class);
        $revision = $service->revision($p);
        $this->cambiar($otro, false);
        $this->assertSame($revision, $service->revision($p));
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.personas.estado.update', ['alumnos', $p->id]), ['activo' => 0, 'esperado' => 1, 'motivo' => 'Sin revisión del expediente'])->assertSessionHasErrors('revision');
        $this->assertTrue($p->fresh()->activo);
    }

    public static function otrasProgramaciones(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('otrasProgramaciones')]
    public function test_programadas_del_mismo_detalle_se_resuelven_por_docente(bool $mismoDocente): void
    {
        [, $p] = $this->persona('docente');
        [, $otro] = $this->persona('docente');
        $d = DetalleCurso::factory()->create(['maestro_id' => $p->id]);
        $a = AsignacionDocente::create(['maestro_id' => $p->id, 'detalle_curso_id' => $d->id, 'estado' => 'programada', 'fecha_inicio' => '2030-01-01', 'motivo_cambio' => 'Motivo original conservado']);
        $b = AsignacionDocente::create(['maestro_id' => $mismoDocente ? $p->id : $otro->id, 'detalle_curso_id' => $d->id, 'estado' => 'programada', 'fecha_inicio' => '2031-01-01']);
        $originalB = $b->fresh()->getAttributes();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.asignaciones.finalizar-programada', $a->id), ['motivo' => 'Finalización explícita de prueba'])->assertSessionHasNoErrors();
        $this->assertSame($originalB, $b->fresh()->getAttributes());
        $this->assertSame('Motivo original conservado', $a->fresh()->motivo_cambio);
        $this->assertSame('2030-01-01', $a->fresh()->fecha_inicio->toDateString());
        $this->assertNull($a->fresh()->fecha_fin);
        $this->assertDatabaseHas('cierres_asignaciones_programadas', ['asignacion_id' => $a->id, 'motivo' => 'Finalización explícita de prueba']);
        if ($mismoDocente) {
            $this->assertSame($p->id, $d->fresh()->maestro_id);
            $this->patch(route('admin.asignaciones.finalizar-programada', $b->id), ['motivo' => 'Finalización de segunda programada'])->assertSessionHasNoErrors();
        }
        $this->assertNull($d->fresh()->maestro_id);
        $this->cambiar($p, false);
        $this->assertFalse($p->fresh()->activo);
        if (! $mismoDocente) {
            $this->assertSame($originalB, $b->fresh()->getAttributes());
        }
    }

    public static function auditoriasInvalidas(): array
    {
        return [['ninguna'], ['ambas'], ['igual'], ['fuera_rango']];
    }

    #[DataProvider('auditoriasInvalidas')]
    public function test_base_rechaza_eventos_de_auditoria_invalidos(string $caso): void
    {
        [, $p] = $this->persona();
        [, $m] = $this->persona('docente');
        $data = ['alumno_id' => $p->id, 'maestro_id' => null, 'administrador_id' => User::factory()->create(['role' => 'admin'])->id, 'estado_anterior' => 1, 'estado_nuevo' => 0, 'motivo' => 'Prueba de restricción', 'registrado_at' => now()];
        if ($caso === 'ninguna') {
            $data['alumno_id'] = null;
        }
        if ($caso === 'ambas') {
            $data['maestro_id'] = $m->id;
        }
        if ($caso === 'igual') {
            $data['estado_nuevo'] = 1;
        }
        if ($caso === 'fuera_rango') {
            $data['estado_nuevo'] = 2;
        }
        try {
            DB::table('cambios_estado_personas')->insert($data);
            $this->fail('La base debe rechazar el evento');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Invalid person state audit', $e->getMessage());
        }
        $this->assertDatabaseCount('cambios_estado_personas', 0);
    }

    public function test_migracion_puede_reintentarse_sin_perder_eventos(): void
    {
        [, $p] = $this->persona();
        $this->cambiar($p, false);
        $snapshot = CambioEstadoPersona::first()->getAttributes();
        $version = $p->fresh()->user->auth_version;
        $migration = require database_path('migrations/2026_10_24_000001_add_auth_version_and_persona_audit.php');
        $migration->up();
        $migration->up();
        $this->assertSame($snapshot, CambioEstadoPersona::first()->getAttributes());
        $this->assertSame($version, $p->fresh()->user->auth_version);
    }

    public function test_restriccion_tambien_protege_actualizaciones_directas(): void
    {
        [, $p] = $this->persona();
        $this->cambiar($p, false);
        $before = CambioEstadoPersona::first()->getAttributes();
        try {
            DB::table('cambios_estado_personas')->update(['estado_nuevo' => 1]);
            $this->fail('Debe impedir estados idénticos');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Invalid person state audit', $e->getMessage());
        }
        $this->assertSame($before, CambioEstadoPersona::first()->getAttributes());
    }

    public function test_una_asignacion_activa_del_mismo_docente_conserva_referencia(): void
    {
        [, $p] = $this->persona('docente');
        $d = DetalleCurso::factory()->create(['maestro_id' => $p->id]);
        $a = AsignacionDocente::create(['maestro_id' => $p->id, 'detalle_curso_id' => $d->id, 'estado' => 'programada']);
        $active = AsignacionDocente::create(['maestro_id' => $p->id, 'detalle_curso_id' => $d->id, 'estado' => 'activa']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.asignaciones.finalizar-programada', $a->id), ['motivo' => 'Cierre de planificación duplicada'])->assertSessionHasNoErrors();
        $this->assertSame($p->id, $d->fresh()->maestro_id);
        $this->assertTrue($d->fresh()->esMateriaDelMaestro($p->id));
        $this->assertSame('activa', $active->fresh()->estado);
    }

    public function test_migracion_recupera_columna_ya_creada_en_base_desechable(): void
    {
        $previous = DB::getDefaultConnection();
        config(['database.connections.personas_ddl' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('personas_ddl');
        try {
            $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
            foreach (['users', 'alumnos', 'maestros', 'asignaciones_docentes'] as $table) {
                Schema::create($table, function ($t) use ($table) {
                    $t->increments('id');
                    if ($table === 'users') {
                        $t->unsignedInteger('auth_version')->default(1);
                    }
                });
            }
            DB::table('users')->insert(['id' => 1, 'auth_version' => 7]);
            DB::table('alumnos')->insert(['id' => 1]);
            $migration = require database_path('migrations/2026_10_24_000001_add_auth_version_and_persona_audit.php');
            $interrumpir = true;
            DB::connection()->beforeExecuting(function ($sql) use (&$interrumpir) {
                if ($interrumpir && str_starts_with(strtolower($sql), 'create index "cambios_estado_personas_alumno_id_id_index"')) {
                    $interrumpir = false;
                    throw new \RuntimeException('Interrupción DDL simulada');
                }
            });
            try {
                $migration->up();
                $this->fail('Debe simular una interrupción después de crear la tabla');
            } catch (\RuntimeException $e) {
                $this->assertSame('Interrupción DDL simulada', $e->getMessage());
            }
            DB::table('cambios_estado_personas')->insert(['alumno_id' => 1, 'administrador_id' => 1, 'estado_anterior' => 1, 'estado_nuevo' => 0, 'motivo' => 'Evento conservado tras fallo DDL', 'registrado_at' => now()]);
            $snapshot = DB::table('cambios_estado_personas')->get()->toJson();
            $migration->up();
            $migration->up();
            $this->assertSame($snapshot, DB::table('cambios_estado_personas')->get()->toJson());
            $this->assertSame(7, (int) DB::table('users')->value('auth_version'));
            $this->assertTrue(Schema::hasTable('cambios_estado_personas'));
            $this->assertTrue(Schema::hasTable('cierres_asignaciones_programadas'));
        } finally {
            DB::purge('personas_ddl');
            DB::setDefaultConnection($previous);
        }
    }
}
