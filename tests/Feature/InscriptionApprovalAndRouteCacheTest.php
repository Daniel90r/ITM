<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Http\Controllers\Alumno\AlumnoDashboardController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regresión de las dos correcciones previas a la publicación:
 *
 *  1. Dos rutas compartían el nombre `admin.mensualidades`, lo que hacía fallar
 *     `php artisan route:cache` con LogicException y, con ello, cualquier
 *     despliegue que cachee rutas.
 *
 *  2. `POST /alumno/inscribirse` creaba la inscripción directamente como
 *     'aprobada', permitiendo saltarse la autorización administrativa. Ahora el
 *     alumno solo puede SOLICITAR; la aprobación es exclusiva del admin.
 */
class InscriptionApprovalAndRouteCacheTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\ConEncargadoOficial;

    private User $alumnoUser;

    private User $adminUser;

    private Alumno $alumno;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('inscripciones');

        $this->alumnoUser = User::factory()->create(['role' => 'alumno']);

        $this->alumno = Alumno::factory()->create([
            'user_id' => $this->alumnoUser->id,
            'nombre'   => 'Marcos',
            'apellido' => 'Montes',
            'codigo'   => 'A001',
        ]);
        $this->prepararEncargadoOficial($this->alumno);

        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    private function cursoProximo(string $nombre = 'Curso Próximo'): Curso
    {
        return Curso::factory()->create([
            'nombre'               => $nombre,
            'nivel'                => 'Matutino',
            'seccion'              => 'A',
            'anio_lectivo'         => (int) now()->year,
            'activo'               => true,
            'finalizado_manualmente' => false,
            'estado'               => null,
            'fecha_inicio'         => now()->addMonth(),
            'fecha_fin'            => now()->addMonths(8),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. Nombres de ruta únicos (route:cache)
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_no_existen_nombres_de_ruta_duplicados(): void
    {
        $conteo = [];

        foreach (app('router')->getRoutes() as $ruta) {
            $nombre = $ruta->getName();

            if ($nombre !== null) {
                $conteo[$nombre] = ($conteo[$nombre] ?? 0) + 1;
            }
        }

        $duplicados = array_filter($conteo, fn ($c) => $c > 1);

        $this->assertSame(
            [],
            $duplicados,
            'Los nombres de ruta duplicados impiden compilar el cache de rutas: '
            . implode(', ', array_keys($duplicados))
        );
    }

    #[Test]
    public function test_la_ruta_oficial_de_mensualidades_keeps_su_url(): void
    {
        // La navegación existente (sidebar, dashboard, redirecciones) depende
        // de que este nombre resuelva a /admin/mensualidades.
        $this->assertSame(
            url('/admin/mensualidades'),
            route('admin.mensualidades')
        );
    }

    #[Test]
    public function test_la_url_corta_de_mensualidades_sigue_respondiendo(): void
    {
        // /mensualidades no se elimina: se conserva con otro nombre para no
        // romper marcadores ni enlaces compartidos.
        $this->assertSame(url('/mensualidades'), route('admin.mensualidades.corta'));
    }

    #[Test]
    public function test_ambas_urls_de_mensualidades_siguen_registradas(): void
    {
        $uris = [];

        foreach (app('router')->getRoutes() as $ruta) {
            if (in_array($ruta->getName(), ['admin.mensualidades', 'admin.mensualidades.corta'], true)) {
                $uris[] = $ruta->uri();
            }
        }

        sort($uris);

        $this->assertSame(['admin/mensualidades', 'mensualidades'], $uris);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. El alumno solo solicita: nunca se auto-aprueba
    // ═══════════════════════════════════════════════════════════════════════

    #[Test]
    public function test_la_ruta_inscribirse_crea_una_solicitud_pendiente(): void
    {
        $curso = $this->cursoProximo();

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $curso->id])
            ->assertSessionHas('success');

        $inscripcion = Inscripcion::firstOrFail();

        $this->assertSame('pendiente', $inscripcion->estado);
        $this->assertFalse((bool) $inscripcion->activa);
    }

    #[Test]
    public function test_la_ruta_de_formulario_crea_una_solicitud_pendiente(): void
    {
        $curso = $this->cursoProximo();

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscripcion.enviar'), [
                'curso_id'             => $curso->id,
                'alumno_dui'           => '12345678-9',
                'encargado_nombre'     => 'Rosa',
                'encargado_apellido'   => 'Montes',
                'encargado_telefono'   => '7777-8888',
                'encargado_parentesco' => 'Madre',
                'encargado_dui'        => '98765432-1',
                'firma_alumno'         => 'Firma Alumno',
                'firma_encargado'      => 'Firma Encargado',
            ])
            ->assertSessionHas('success');

        $inscripcion = Inscripcion::firstOrFail();

        $this->assertSame('pendiente', $inscripcion->estado);
        $this->assertFalse((bool) $inscripcion->activa);
    }

    #[Test]
    public function test_ninguna_ruta_del_alumno_crea_una_inscripcion_aprobada(): void
    {
        $curso = $this->cursoProximo();

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $curso->id]);

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscripcion.enviar'), [
                'curso_id'     => $this->cursoProximo('Curso 2')->id,
                'alumno_dui'   => '12345678-9',
                'firma_alumno' => 'Firma',
            ]);

        $this->assertSame(0, Inscripcion::aprobada()->count());
        $this->assertGreaterThan(0, Inscripcion::pendiente()->count());
    }

    #[Test]
    public function test_solicitar_no_consume_un_cupo_antes_de_la_aprobacion(): void
    {
        $curso = $this->cursoProximo();

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $curso->id]);

        // La solicitud existe pero el cupo NO se consume: el curso sigue
        // admitting inscripciones.
        $curso->refresh();

        $this->assertSame(0, $curso->inscripciones()->aprobada()->count());
        $this->assertFalse($curso->esta_lleno);
        $this->assertTrue($curso->admiteNuevaInscripcion());
    }

    #[Test]
    public function test_aprobar_es_la_unica_forma_de_obtener_una_inscripcion_aprobada(): void
    {
        $curso = $this->cursoProximo();

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $curso->id]);

        $solicitud = Inscripcion::firstOrFail();
        $this->assertSame('pendiente', $solicitud->estado);

        // El admin aprueba y ahora sí hay inscripción efectiva.
        $this->actingAs($this->adminUser)
            ->post(route('admin.solicitudes.aprobar', $solicitud->id))
            ->assertRedirect();

        $solicitud->refresh();

        $this->assertSame('aprobada', $solicitud->estado);
        $this->assertTrue((bool) $solicitud->activa);
        $this->assertSame(1, $curso->inscripciones()->aprobada()->count());
    }

    #[Test]
    public function test_un_alumno_no_puede_aprobar_su_propia_solicitud(): void
    {
        $curso = $this->cursoProximo();

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscribirse'), ['curso_id' => $curso->id]);

        $solicitud = Inscripcion::firstOrFail();

        // El endpoint de aprobación es exclusivo del administrador.
        $this->actingAs($this->alumnoUser)
            ->post(route('admin.solicitudes.aprobar', $solicitud->id))
            ->assertForbidden();

        $this->assertSame('pendiente', $solicitud->fresh()->estado);
    }

    #[Test]
    public function test_un_alumno_no_puede_alcanzar_las_rutas_de_administracion(): void
    {
        $curso = $this->cursoProximo();

        $this->actingAs($this->alumnoUser)
            ->get(route('admin.dashboard'))->assertForbidden();

        $this->actingAs($this->alumnoUser)
            ->get(route('admin.mensualidades'))->assertForbidden();

        $this->actingAs($this->alumnoUser)
            ->get(route('admin.solicitudes'))->assertForbidden();

        $this->actingAs($this->alumnoUser)
            ->getJson(route('admin.solicitudes.pendientesCount'))->assertForbidden();

        $this->actingAs($this->alumnoUser)
            ->post(route('admin.solicitudes.aprobar', 1))->assertForbidden();

        $this->assertDatabaseCount('inscripciones', 0);
        $this->assertSame(0, $curso->inscripciones()->count());
    }

    #[Test]
    public function test_las_rutas_del_alumno_siguen_requiriendo_su_middleware(): void
    {
        foreach (['alumno.inscribirse', 'alumno.inscripcion.enviar'] as $nombre) {
            $ruta = app('router')->getRoutes()->getByName($nombre);

            $this->assertNotNull($ruta, "La ruta [{$nombre}] debe existir.");
            $this->assertContains('alumno.portal', $ruta->gatherMiddleware(), "La ruta [{$nombre}] debe conservar el middleware del portal del alumno.");
        }
    }

    #[Test]
    public function test_no_hay_rutas_duplicadas_por_nombre_en_el_portal_del_alumno(): void
    {
        // Regresión: routes/alumno.php declaraba dos veces `inscripcion` e
        // `inscribirse`.
        $porNombre = [];

        foreach (app('router')->getRoutes() as $ruta) {
            $nombre = $ruta->getName();

            if ($nombre !== null && str_starts_with($nombre, 'alumno.')) {
                $porNombre[$nombre] = ($porNombre[$nombre] ?? 0) + 1;
            }
        }

        $duplicados = array_filter($porNombre, fn ($c) => $c > 1);

        $this->assertSame([], $duplicados);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. Limpieza de PDF y atributos protegidos
    // ═══════════════════════════════════════════════════════════════════════

    private function datosFormulario(array $extra = []): array
    {
        return array_merge([
            'curso_id'             => null,
            'alumno_dui'           => '12345678-9',
            'encargado_nombre' => 'Rosa', 'encargado_apellido' => 'Montes',
            'encargado_telefono'   => '7777-8888',
            'encargado_parentesco' => 'Madre',
            'encargado_dui'        => '98765432-1',
            'firma_alumno'         => 'Firma Alumno',
            'firma_encargado'      => 'Firma Encargado',
        ], $extra);
    }

    #[Test]
    public function test_el_pdf_generado_se_conserva_cuando_la_solicitud_se_registra(): void
    {
        Storage::fake('public');

        $curso = $this->cursoProximo();

        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscripcion.enviar'), $this->datosFormulario(['curso_id' => $curso->id]))
            ->assertSessionHas('success');

        $inscripcion = Inscripcion::firstOrFail();

        $this->assertNotNull($inscripcion->documento_path);
        Storage::disk('public')->assertExists($inscripcion->documento_path);
    }

    #[Test]
    public function test_el_nombre_del_pdf_no_usa_la_hora_y_es_unico(): void
    {
        Storage::fake('public');

        // Regresión: el nombre se armaba con time() (segundos), así que dos
        // peticiones en el mismo segundo generaban el MISMO archivo y la segunda
        // sobrescribía el PDF de la primera.
        $controlador = new \ReflectionClass(AlumnoDashboardController::class);
        $codigo = file_get_contents($controlador->getFileName());

        $this->assertStringNotContainsString("' . time() . '", $codigo);
        $this->assertStringContainsString('Str::uuid()', $codigo);
    }

    #[Test]
    public function test_si_el_registro_falla_se_borra_el_pdf_de_esa_peticion(): void
    {
        Storage::fake('public');

        $curso = $this->cursoProximo();

        // Un pdf previo que pertenece a OTRA solicitud: no debe tocarse.
        $preexistente = 'solicitudes/solicitud-preexistente.pdf';
        Storage::disk('public')->put($preexistente, 'contenido previo');

        // Forzamos el fallo DESPUÉS de generar el PDF: el alumno ya tiene una
        // solicitud pendiente para OTRO curso, así que la comprobación dentro de
        // la transacción lo rechaza aunque la comprobación previa no lo hiciera.
        $otroCurso = $this->cursoProximo('Curso Ocupado');

        Inscripcion::create([
            'alumno_id'         => $this->alumno->id,
            'curso_id'          => $otroCurso->id,
            'fecha_inscripcion' => now()->toDateString(),
            'activa'            => false,
            'estado'            => 'pendiente',
        ]);

        // La comprobación rápida de enviarSolicitud también lo detecta, así que
        // no llega a generarse ningún PDF. Lo verificamos.
        $this->actingAs($this->alumnoUser)
            ->post(route('alumno.inscripcion.enviar'), $this->datosFormulario(['curso_id' => $curso->id]))
            ->assertSessionHas('error');

        $this->assertSame(1, Inscripcion::count());

        // Solo debe quedar el archivo preexistente: la petición rechazada no
        // dejó ningún PDF huérfano, y el archivo ajeno sigue intacto.
        $archivos = Storage::disk('public')->files('solicitudes');

        $this->assertSame([$preexistente], $archivos);
        Storage::disk('public')->assertExists($preexistente);
    }

    #[Test]
    public function test_los_atributos_extra_no_pueden_forzar_una_inscripcion_aprobada(): void
    {
        Storage::fake('public');

        $curso = $this->cursoProximo();

        // Se invoca el núcleo privado con atributos "maliciosos" que intentan
        // saltarse la regla: el estado obligatorio debe ganar siempre.
        $controlador = app(AlumnoDashboardController::class);
        $metodo = new \ReflectionMethod($controlador, 'crearSolicitudPendiente');
        $metodo->setAccessible(true);

        $resultado = $metodo->invoke(
            $controlador,
            $this->alumno->id,
            $curso->id,
            ['estado' => 'aprobada', 'activa' => true],
            false
        );

        $inscripcion = $resultado['inscripcion'];
        $this->assertSame('pendiente', $inscripcion->estado);
        $this->assertFalse((bool) $inscripcion->activa);
    }

    #[Test]
    public function test_el_bloqueo_del_alumno_ocurre_antes_que_el_del_curso(): void
    {
        // Orden de bloqueos fijo: alumno -> curso. Invertirlo podría formar un
        // ciclo con otros procedimientos y provocar interbloqueos.
        $codigo = file_get_contents(
            (new \ReflectionClass(AlumnoDashboardController::class))->getFileName()
        );

        $posAlumno = strpos($codigo, 'Alumno::query()');
        $posCurso  = strpos($codigo, 'Curso::query()');

        $this->assertIsInt($posAlumno);
        $this->assertIsInt($posCurso);
        $this->assertLessThan($posCurso, $posAlumno, 'El alumno debe bloquearse antes que el curso.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. Traducción de la violación UNIQUE (solo la de inscripciones)
    // ═══════════════════════════════════════════════════════════════════════

    private function invocarDeteccion(QueryException $e): bool
    {
        $controlador = app(AlumnoDashboardController::class);
        $metodo = new \ReflectionMethod($controlador, 'esViolacionUnicaDeInscripciones');
        $metodo->setAccessible(true);

        return $metodo->invoke($controlador, $e);
    }

    private function queryException(string $sqlState, mixed $codigoMotor, string $mensaje): QueryException
    {
        // QueryException::__construct($connectionName, $sql, $bindings, $previous)
        $e = new QueryException('sqlite', '', [], new \PDOException($mensaje));

        $reflexion = new \ReflectionProperty(\PDOException::class, 'code');
        $reflexion->setValue($e, $sqlState);

        $e->errorInfo = [$sqlState, $codigoMotor, $mensaje];

        return $e;
    }

    #[Test]
    public function test_reconoce_la_violacion_unica_de_inscripciones_en_mysql(): void
    {
        // MySQL: SQLSTATE 23000 + código 1062 "Duplicate entry".
        $this->assertTrue($this->invocarDeteccion(
            $this->queryException('23000', 1062, "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-1' for key 'inscripciones.inscripciones_alumno_id_curso_id_unique'")
        ));
    }

    #[Test]
    public function test_reconoce_la_violacion_unica_de_inscripciones_en_sqlite(): void
    {
        // SQLite: SQLSTATE 23000 + código 19.
        $this->assertTrue($this->invocarDeteccion(
            $this->queryException('23000', 19, 'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: inscripciones.alumno_id, inscripciones.curso_id')
        ));
    }

    #[Test]
    public function test_no_confunde_otros_errores_de_base_de_datos(): void
    {
        // Una restricción de otra tabla no debe traducirse como duplicado de
        // inscripción: sería un mensaje engañoso.
        $this->assertFalse($this->invocarDeteccion(
            $this->queryException('23000', 1062, "Duplicate entry 'x' for key 'users.users_email_unique'")
        ));

        // Otro SQLSTATE (p. ej. una columna inexistente) tampoco.
        $this->assertFalse($this->invocarDeteccion(
            $this->queryException('42S22', 1054, "Unknown column 'foo' in 'field list'")
        ));

        // Un error de conexión tampoco.
        $this->assertFalse($this->invocarDeteccion(
            $this->queryException('HY000', 2006, 'MySQL server has gone away')
        ));
    }
}
