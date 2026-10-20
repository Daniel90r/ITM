<?php

namespace Tests\Feature;

use App\Models\{DocumentoInscripcion, Inscripcion};
use App\Services\{AutorizacionTransicionDocumental, ControlTransicionDocumental, DocumentosInscripcion, EntornoTransicionReal, TransicionDocumental};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AutorizacionTransicionDocumentalTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;
    private string $secret;
    private array $contexto;
    private array $solicitud;
    private array $payload;
    private \Tests\Support\RegistroConsumosFicticio $testigo;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/itm-permiso-ficticio-'.bin2hex(random_bytes(10));
        $this->app->useStoragePath($this->dir.'/storage');
        foreach (['public', 'inscripciones'] as $disk) {
            $fake = Storage::fake($disk);
            config(["filesystems.disks.$disk.root" => rtrim($fake->path(''), '/\\')]);
        }
        config(['documentos_inscripcion.enabled' => false, 'documentos_inscripcion.history_enabled' => false,
            'documentos_inscripcion.legacy_read_enabled' => false, 'documentos_inscripcion.transition_guard_enabled' => false]);
        mkdir($this->dir.'/usos');
        $this->testigo = new \Tests\Support\RegistroConsumosFicticio();
        $this->app->instance(\App\Contracts\RegistroConsumosIndependiente::class, $this->testigo);
        $sid = 'S-1-5-21-111-222-333-1001';
        $this->mock(\App\Services\IdentidadEjecutorDocumental::class)->makePartial()->shouldReceive('sid')->andReturn($sid);
        $registry = ['id' => $this->testigo->registroId, 'epoch' => $this->testigo->epoca, 'public_key' => $this->testigo->publica];
        config(['documentos_inscripcion.executor_sid' => $sid, 'documentos_inscripcion.consumption_registry' => $registry]);
        file_put_contents($this->dir.'/usos/registro.json', json_encode(['formato' => 1, 'registro_id' => $registry['id'],
            'epoca' => $registry['epoch'], 'clave_sha256' => hash('sha256', base64_decode($registry['public_key']))], JSON_THROW_ON_ERROR));
        $keys = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($keys);
        file_put_contents($this->dir.'/clave.txt', base64_encode(sodium_crypto_sign_publickey($keys)));
        file_put_contents($this->dir.'/http.conf', 'DocumentRoot ficticio-sin-datos-reales');
        // SQL ficticio: nunca se importa; sirve exclusivamente para verificar el artefacto firmado.
        file_put_contents($this->dir.'/respaldo.sql', "USE `itm`;\n-- Dump completed on fecha-ficticia\n");
        $this->contexto = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'itm', 'version' => '8.4.7',
            'server_uuid' => '00000000-0000-0000-0000-000000000000',
            'privado' => config('filesystems.disks.inscripciones.root'), 'cuarentena' => $this->dir.'/cuarentena',
            'barrera' => storage_path('framework/transicion-documental'),
            'ejecutor_sid' => $sid,
            'registro_consumos_raiz' => PHP_OS_FAMILY === 'Windows' ? strtolower(str_replace('\\', '/', $this->dir.'/usos')) : $this->dir.'/usos',
            'registro_consumos_sha256' => hash('sha256', json_encode($registry, JSON_THROW_ON_ERROR)),
            'datos_originales_sha256' => hash('sha256', 'estado ficticio')];
        $this->solicitud = ['inventario' => $this->dir.'/inventario.json', 'sha256' => '',
            'cuarentena' => $this->dir.'/cuarentena', 'lote' => 'lote-ficticio'];
    }

    private function preparar(): array
    {
        $ins = Inscripcion::factory()->create(['documento_path' => 'solicitudes/ficticio.pdf']);
        Storage::disk('public')->put($ins->documento_path, "%PDF-1.4\nFicticio\n%%EOF");
        Storage::disk('public')->put('solicitudes/suelto.pdf', '%PDF-ficticio-sin-asociacion');
        return [$ins, $this->firmarInventario()];
    }

    private function firmarInventario(): array
    {
        file_put_contents($this->solicitud['inventario'], json_encode(app(TransicionDocumental::class)->planificar(), JSON_THROW_ON_ERROR));
        $this->solicitud['sha256'] = hash_file('sha256', $this->solicitud['inventario']);
        $ref = fn ($ruta) => ['ruta' => $ruta, 'sha256' => hash_file('sha256', $ruta)];
        $audit = ['formato' => 1, 'revisada_en' => now()->timestamp, 'procesos_revisados' => true,
            'tareas_revisadas' => true, 'escritores_externos_descartados' => true, 'restauracion_verificada' => true,
            'publicaciones_http' => [$this->dir.'/http-public'], 'configuracion_http' => [$ref($this->dir.'/http.conf')],
            'respaldo_sha256' => hash_file('sha256', $this->dir.'/respaldo.sql'),
            'datos_originales_sha256' => $this->contexto['datos_originales_sha256']];
        file_put_contents($this->dir.'/auditoria.json', json_encode($audit, JSON_THROW_ON_ERROR));
        $this->payload = ['formato' => 2, 'accion' => 'transicion-documental-inicial', 'id' => bin2hex(random_bytes(16)),
            'entorno' => $this->contexto, 'solicitud' => $this->solicitud,
            'ventana' => ['desde' => now()->timestamp - 5, 'hasta' => now()->timestamp + 600,
                'confirmada' => true, 'escritores_externos_revisados' => true],
            'inventario' => $ref($this->solicitud['inventario']), 'respaldo' => $ref($this->dir.'/respaldo.sql'),
            'auditoria' => $ref($this->dir.'/auditoria.json'), 'respaldo_verificado' => true];
        return $this->firmar();
    }

    private function firmar(): array
    {
        $bytes = json_encode($this->payload, JSON_THROW_ON_ERROR);
        $path = $this->dir.'/autorizacion.json';
        file_put_contents($path, json_encode(['payload' => base64_encode($bytes),
            'firma' => base64_encode(sodium_crypto_sign_detached($bytes, $this->secret))], JSON_THROW_ON_ERROR));
        return [$path, hash_file('sha256', $path)];
    }

    private function validar(array $signed): array
    {
        return app(AutorizacionTransicionDocumental::class)->validar($signed[0], $signed[1], $this->dir.'/clave.txt',
            $this->contexto, $this->solicitud, $this->dir.'/usos', hash_file('sha256', $this->dir.'/clave.txt'));
    }

    private function argumentos(): array
    {
        return ['--ejecutar' => true, '--confirmar' => 'INCORPORAR_DOCUMENTOS', '--inventario' => $this->solicitud['inventario'],
            '--sha256' => $this->solicitud['sha256'], '--cuarentena' => $this->solicitud['cuarentena'], '--lote' => $this->solicitud['lote']];
    }

    public function test_validacion_firmada_es_lectura_y_permiso_no_se_reutiliza(): void
    {
        [, $signed] = $this->preparar(); $before = glob($this->dir.'/usos/*.consumida');
        $this->validar($signed); $this->assertSame($before, glob($this->dir.'/usos/*.consumida'));
        $service = app(AutorizacionTransicionDocumental::class);
        $this->assertTrue($service->usar(fn () => true));
        $this->assertCount(1, glob($this->dir.'/usos/*.consumida'));
        $this->expectExceptionMessage('AUTORIZACION_CONSUMIDA'); $this->validar($signed);
    }

    public function test_permiso_consumido_tras_fallo_no_se_libera_automaticamente(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed);
        try { app(AutorizacionTransicionDocumental::class)->usar(fn () => throw new RuntimeException('INTERRUPCION_FICTICIA')); }
        catch (RuntimeException $e) { $this->assertSame('INTERRUPCION_FICTICIA', $e->getMessage()); }
        $this->expectExceptionMessage('AUTORIZACION_CONSUMIDA'); $this->validar($signed);
    }

    public function test_registro_vacio_por_interrupcion_tambien_impide_reutilizar_permiso(): void
    {
        [, $signed] = $this->preparar();
        $uso = $this->dir.'/usos/'.$this->payload['id'].'.consumida';
        $f = fopen($uso, 'xb');
        $this->assertIsResource($f);
        fclose($f); // Simula interrupción después de crear y antes de escribir.
        $this->assertSame(0, filesize($uso));
        $this->expectExceptionMessage('ESTADO_DE_CONSUMO_NO_VERIFICABLE');
        $this->validar($signed);
    }

    public function test_ventana_expirada_despues_de_validar_no_consume_permiso(): void
    {
        [, $signed] = $this->preparar();
        $this->validar($signed);
        $this->travelTo(now()->addMinutes(31));
        try {
            app(AutorizacionTransicionDocumental::class)->usar(fn () => true);
            $this->fail('La autorización expirada debe detenerse antes de consumir.');
        } catch (RuntimeException $e) {
            $this->assertSame('VENTANA_NO_VERIFICADA', $e->getMessage());
            $this->assertSame([], glob($this->dir.'/usos/*.consumida'));
        } finally { $this->travelBack(); }
    }

    public function test_registro_local_eliminado_no_permite_reutilizar_autorizacion(): void
    {
        // El testigo separado conserva el consumo aunque se borre el espejo.
        [, $signed] = $this->preparar();
        $this->validar($signed);
        $this->assertTrue(app(AutorizacionTransicionDocumental::class)->usar(fn () => true));
        $uso = $this->dir.'/usos/'.$this->payload['id'].'.consumida';
        $this->assertTrue(unlink($uso));
        $this->expectExceptionMessage('AUTORIZACION_CONSUMIDA_SIN_ESPEJO');
        $this->validar($signed);
    }

    public function test_restaurar_directorio_local_antiguo_no_restaura_el_permiso(): void
    {
        [, $signed] = $this->preparar();
        $anterior = file_get_contents($this->dir.'/usos/registro.json');
        $this->validar($signed);
        app(AutorizacionTransicionDocumental::class)->usar(fn () => true);
        unlink($this->dir.'/usos/'.$this->payload['id'].'.consumida');
        file_put_contents($this->dir.'/usos/registro.json', $anterior);
        $this->app->forgetInstance(AutorizacionTransicionDocumental::class);
        $this->expectExceptionMessage('AUTORIZACION_CONSUMIDA_SIN_ESPEJO');
        $this->validar($signed);
    }

    public function test_recrear_aplicacion_no_olvida_el_consumo_del_testigo(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed);
        app(AutorizacionTransicionDocumental::class)->usar(fn () => true);
        $config = config()->all(); $storage = storage_path(); $testigo = $this->testigo;
        $this->refreshApplication();
        $this->app->useStoragePath($storage);
        config($config);
        $this->app->instance(\App\Contracts\RegistroConsumosIndependiente::class, $testigo);
        $this->mock(\App\Services\IdentidadEjecutorDocumental::class)->makePartial()->shouldReceive('sid')->andReturn($this->contexto['ejecutor_sid']);
        $this->expectExceptionMessage('AUTORIZACION_CONSUMIDA'); $this->validar($signed);
    }

    public function test_estado_externo_restaurado_con_recibo_local_consume_fallo_cerrado(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed);
        app(AutorizacionTransicionDocumental::class)->usar(fn () => true);
        $this->testigo->restaurarVacio();
        $this->expectExceptionMessage('REGISTROS_DE_CONSUMO_INCONSISTENTES'); $this->validar($signed);
    }

    public function test_borrar_espejo_durante_accion_impide_continuar_autorizada(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed);
        $this->expectExceptionMessage('ESTADO_DE_CONSUMO_NO_VERIFICABLE');
        app(AutorizacionTransicionDocumental::class)->usar(function () {
            unlink($this->dir.'/usos/'.$this->payload['id'].'.consumida');
            app(AutorizacionTransicionDocumental::class)->exigirActiva();
        });
    }

    public function test_testigo_atomicamente_rechaza_segundo_reclamante(): void
    {
        [, $signed] = $this->preparar();
        $c = config('documentos_inscripcion.consumption_registry');
        $solicitud = ['registro_id' => $c['id'], 'epoca' => $c['epoch'], 'id' => $this->payload['id'],
            'permiso_sha256' => $signed[1], 'vinculo_sha256' => hash('sha256', 'vinculo-ficticio'), 'desafio' => bin2hex(random_bytes(32))];
        $this->assertIsArray($this->testigo->consultar($solicitud));
        $segundo = $solicitud; $segundo['desafio'] = bin2hex(random_bytes(32));
        $this->assertIsArray($this->testigo->consultar($segundo));
        $this->assertIsArray($this->testigo->consumir($solicitud));
        $this->expectExceptionMessage('AUTORIZACION_CONSUMIDA'); $this->testigo->consumir($segundo);
    }

    public function test_recibo_local_alterado_no_se_acepta(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed);
        app(AutorizacionTransicionDocumental::class)->usar(fn () => true);
        $path = $this->dir.'/usos/'.$this->payload['id'].'.consumida';
        $sobre = json_decode(file_get_contents($path), true);
        $data = json_decode(base64_decode($sobre['payload']), true); $data['estado'] = 'libre';
        $sobre['payload'] = base64_encode(json_encode($data)); file_put_contents($path, json_encode($sobre));
        $this->expectExceptionMessage('FIRMA_DE_CONSUMO_INVALIDA'); $this->validar($signed);
    }

    public function test_recibo_valido_de_otro_id_no_puede_sustituir_el_consumo(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed);
        app(AutorizacionTransicionDocumental::class)->usar(fn () => true);
        $old = $this->payload['id'];
        $this->payload['id'] = bin2hex(random_bytes(16)); $nuevo = $this->firmar();
        copy($this->dir.'/usos/'.$old.'.consumida', $this->dir.'/usos/'.$this->payload['id'].'.consumida');
        $this->expectExceptionMessage('REGISTRO_LOCAL_SUSTITUIDO'); $this->validar($nuevo);
    }

    public function test_marcador_de_continuidad_faltante_no_se_recrea(): void
    {
        [, $signed] = $this->preparar(); unlink($this->dir.'/usos/registro.json');
        try { $this->validar($signed); $this->fail('Debe fallar sin continuidad.'); }
        catch (RuntimeException $e) { $this->assertSame('ESTADO_DE_CONSUMO_NO_VERIFICABLE', $e->getMessage()); }
        $this->assertFileDoesNotExist($this->dir.'/usos/registro.json');
    }

    public function test_sustituir_epoca_local_produce_fallo_cerrado(): void
    {
        [, $signed] = $this->preparar();
        $path = $this->dir.'/usos/registro.json'; $data = json_decode(file_get_contents($path), true);
        $data['epoca'] = bin2hex(random_bytes(16)); file_put_contents($path, json_encode($data));
        $this->expectExceptionMessage('CONTINUIDAD_DE_REGISTRO_PERDIDA'); $this->validar($signed);
    }

    public function test_rotar_configuracion_del_registro_no_revalida_permiso_antiguo(): void
    {
        [, $signed] = $this->preparar();
        config(['documentos_inscripcion.consumption_registry.epoch' => bin2hex(random_bytes(16))]);
        $this->expectExceptionMessage('REGISTRO_FUERA_DE_CONTEXTO_FIRMADO'); $this->validar($signed);
    }

    public function test_falta_testigo_independiente_impide_validar_y_consumir(): void
    {
        [, $signed] = $this->preparar(); $this->testigo->noDisponible = true;
        $this->expectExceptionMessage('TESTIGO_NO_VERIFICABLE'); $this->validar($signed);
    }

    public function test_testigo_cae_despues_de_validar_y_no_se_ejecuta_accion(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed); $this->testigo->noDisponible = true;
        $ejecutada = false;
        try { app(AutorizacionTransicionDocumental::class)->usar(function () use (&$ejecutada) { $ejecutada = true; }); $this->fail('No debe continuar.'); }
        catch (RuntimeException $e) { $this->assertSame('TESTIGO_NO_VERIFICABLE', $e->getMessage()); }
        $this->assertFalse($ejecutada);
        $this->assertSame([], glob($this->dir.'/usos/*.consumida'));
    }

    public function test_respuesta_firmada_antigua_no_satisface_desafio_nuevo(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed); $this->testigo->repetirRespuesta = true;
        $this->expectExceptionMessage('RESPUESTA_TESTIGO_NO_CORRESPONDE');
        app(AutorizacionTransicionDocumental::class)->usar(fn () => $this->fail('No debe ejecutar.'));
    }

    public function test_firma_incorrecta_del_testigo_se_rechaza(): void
    {
        [, $signed] = $this->preparar(); $this->testigo->alterarFirma = true;
        $this->expectExceptionMessage('FIRMA_DE_CONSUMO_INVALIDA'); $this->validar($signed);
    }

    public function test_clave_publica_incorrecta_del_firmante_impide_consumir(): void
    {
        [, $signed] = $this->preparar();
        file_put_contents($this->dir.'/clave.txt', base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair())));
        $this->expectExceptionMessage('AUTORIZACION_INVALIDA'); $this->validar($signed);
    }

    public function test_respuesta_perdida_despues_de_consumir_no_permite_reintento(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed); $this->testigo->perderRespuestaDeConsumo = true;
        try { app(AutorizacionTransicionDocumental::class)->usar(fn () => $this->fail('No debe ejecutar.')); }
        catch (RuntimeException $e) { $this->assertSame('TESTIGO_NO_VERIFICABLE', $e->getMessage()); }
        $this->assertSame([], glob($this->dir.'/usos/*.consumida'));
        $this->app->forgetInstance(AutorizacionTransicionDocumental::class);
        $this->expectExceptionMessage('AUTORIZACION_CONSUMIDA_SIN_ESPEJO'); $this->validar($signed);
    }

    public function test_expirar_durante_consumo_gasta_permiso_pero_no_inicia_accion(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed);
        $this->testigo->despuesConsumir = fn () => $this->travelTo(now()->addMinutes(31));
        try {
            app(AutorizacionTransicionDocumental::class)->usar(fn () => $this->fail('No debe ejecutar.'));
            $this->fail('La ventana debe volver a comprobarse.');
        } catch (RuntimeException $e) { $this->assertSame('VENTANA_NO_VERIFICADA', $e->getMessage()); }
        finally { $this->travelBack(); }
        $this->assertCount(1, glob($this->dir.'/usos/*.consumida'));
    }

    public function test_identidad_distinta_despues_de_validar_no_consume(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed);
        config(['documentos_inscripcion.executor_sid' => 'S-1-5-21-111-222-333-1002']);
        $this->expectExceptionMessage('IDENTIDAD_EJECUTORA_NO_COINCIDE');
        app(AutorizacionTransicionDocumental::class)->usar(fn () => $this->fail('No debe ejecutar.'));
    }

    public function test_formato_antiguo_no_es_permiso_valido_del_canal_nuevo(): void
    {
        $this->preparar(); $this->payload['formato'] = 1;
        $this->expectExceptionMessage('AUTORIZACION_NO_CORRESPONDE'); $this->validar($this->firmar());
    }

    public function test_modo_real_no_puede_habilitarse_solo_con_barrera_o_testigo_ficticio(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['documentos_inscripcion.transition_guard_enabled' => true]);
        $manager = DB::getFacadeRoot(); DB::shouldReceive('connection')->never();
        try { app(EntornoTransicionReal::class)->preparar($this->dir.'/cuarentena', str_repeat('0', 64)); $this->fail('No puede abrir MySQL.'); }
        catch (RuntimeException $e) { $this->assertSame('REGISTRO_INDEPENDIENTE_REAL_PENDIENTE', $e->getMessage()); }
        finally { DB::swap($manager); $this->app->detectEnvironment(fn () => 'testing'); }
    }

    public function test_firma_invalida_y_huella_incorrecta_se_rechazan(): void
    {
        [, $signed] = $this->preparar();
        $envelope = json_decode(file_get_contents($signed[0]), true);
        $envelope['firma'] = base64_encode(str_repeat('a', SODIUM_CRYPTO_SIGN_BYTES));
        file_put_contents($signed[0], json_encode($envelope)); $signed[1] = hash_file('sha256', $signed[0]);
        try { $this->validar($signed); $this->fail('Debió rechazar firma'); }
        catch (RuntimeException $e) { $this->assertSame('AUTORIZACION_INVALIDA', $e->getMessage()); }
        $signed = $this->firmar(); $signed[1] = str_repeat('0', 64);
        $this->expectExceptionMessage('HUELLA_AUTORIZADA_NO_COINCIDE'); $this->validar($signed);
    }

    public static function entornos(): array
    {
        return [['host', 'localhost'], ['host', 'remoto.test'], ['port', 4000], ['database', 'otra'], ['driver', 'sqlite'], ['version', '8.0.0']];
    }

    #[DataProvider('entornos')]
    public function test_objetivos_distintos_no_se_autorizan_aunque_estan_firmados(string $key, mixed $value): void
    {
        $this->preparar(); $this->contexto[$key] = $value; $this->payload['entorno'] = $this->contexto;
        $this->expectExceptionMessage('ENTORNO_REAL_NO_PERMITIDO'); $this->validar($this->firmar());
    }

    public function test_no_acepta_cambiar_el_entorno_o_el_lote_del_permiso(): void
    {
        [, $signed] = $this->preparar(); $this->solicitud['lote'] = 'otro';
        $this->expectExceptionMessage('AUTORIZACION_NO_CORRESPONDE'); $this->validar($signed);
    }

    public static function artefactos(): array { return [['inventario', false], ['respaldo', false], ['respaldo', true]]; }

    #[DataProvider('artefactos')]
    public function test_inventario_o_respaldo_faltante_o_alterado_no_se_consumen(string $artifact, bool $delete): void
    {
        [, $signed] = $this->preparar(); $file = $this->payload[$artifact]['ruta'];
        if ($delete) unlink($file); else file_put_contents($file, 'contenido modificado ficticio');
        try { $this->validar($signed); $this->fail('Debió rechazar artefacto'); }
        catch (RuntimeException) { $this->assertSame([], glob($this->dir.'/usos/*.consumida')); }
    }

    public static function ventanas(): array { return [['confirmada', false], ['escritores_externos_revisados', false], ['hasta', 0]]; }

    #[DataProvider('ventanas')]
    public function test_ventana_no_confirmada_o_expirada_impide_permiso(string $key, mixed $value): void
    {
        $this->preparar(); $this->payload['ventana'][$key] = $value;
        $this->expectExceptionMessage('VENTANA_NO_VERIFICADA'); $this->validar($this->firmar());
    }

    public function test_detecta_cambio_http_despues_de_la_revision(): void
    {
        [, $signed] = $this->preparar(); file_put_contents($this->dir.'/http.conf', 'configuracion cambiada');
        $this->expectExceptionMessage('CONFIGURACION_HTTP_MODIFICADA'); $this->validar($signed);
    }

    public function test_ruta_privada_publicable_impide_habilitacion(): void
    {
        $this->preparar(); $this->contexto['privado'] = $this->dir.'/http-public/documentos';
        $this->payload['entorno'] = $this->contexto;
        $this->expectExceptionMessage('DESTINO_PRIVADO_PUBLICABLE'); $this->validar($this->firmar());
    }

    public function test_canal_real_no_puede_abrir_mysql_desde_testing(): void
    {
        $manager = DB::getFacadeRoot(); DB::shouldReceive('connection')->never();
        try { app(EntornoTransicionReal::class)->verificarConexion(); $this->fail('No debe conectarse'); }
        catch (RuntimeException $e) { $this->assertSame('MODO_REAL_NO_PERMITIDO', $e->getMessage()); }
        finally { DB::swap($manager); }
    }

    public function test_canal_real_sin_autorizacion_no_llega_al_adaptador_mysql(): void
    {
        $this->preparar(); $args = $this->argumentos(); $args['--real'] = true; $args['--confirmar'] = 'INCORPORAR_DOCUMENTOS_REALES';
        $this->mock(EntornoTransicionReal::class)->shouldNotReceive('preparar');
        $this->artisan('inscripciones:transicionar-documentos', $args)->expectsOutputToContain('AUTORIZACION_REAL_REQUERIDA')->assertFailed();
        $this->assertSame([], glob($this->dir.'/usos/*.consumida'));
        $this->assertDirectoryDoesNotExist(storage_path('framework/transicion-documental'));
    }

    public function test_permiso_ficticio_se_combina_con_motor_sqlite_y_reanudacion_requiere_otro_permiso(): void
    {
        [$ins, $signed] = $this->preparar(); $this->validar($signed);
        config(['documentos_inscripcion.transition_guard_enabled' => true]);
        DocumentoInscripcion::creating(fn () => throw new RuntimeException('FALLO_SQL_FICTICIO'));
        try {
            $code = app(AutorizacionTransicionDocumental::class)->usar(fn () => \Illuminate\Support\Facades\Artisan::call(
                'inscripciones:transicionar-documentos', $this->argumentos()));
            $this->assertSame(1, $code);
        } finally { DocumentoInscripcion::flushEventListeners(); }
        $this->assertSame(0, DocumentoInscripcion::count());
        $this->assertTrue(Storage::disk('public')->exists($ins->documento_path));
        $this->assertTrue(Storage::disk('inscripciones')->exists($ins->documento_path));
        $old = $this->payload['id']; $this->payload['id'] = bin2hex(random_bytes(16));
        $this->validar($this->firmar());
        $result = app(AutorizacionTransicionDocumental::class)->usar(fn () => \Illuminate\Support\Facades\Artisan::call(
            'inscripciones:transicionar-documentos', $this->argumentos()));
        $this->assertSame(0, $result); $this->assertSame(1, DocumentoInscripcion::count());
        $this->assertSame('solicitudes/ficticio.pdf', $ins->fresh()->documento_path);
        $this->assertFileExists($this->dir.'/usos/'.$old.'.consumida');
        $this->assertCount(2, glob($this->dir.'/usos/*.consumida'));
    }

    public function test_adaptador_v2_integrado_con_permiso_y_motor_solo_ejecuta_una_transicion_ficticia(): void
    {
        $remoto = new \Tests\Support\TestigoProtocoloFicticio($this->contexto['ejecutor_sid'], fn () => now()->timestamp);
        $registro = ['id' => $remoto->registro, 'epoch' => $remoto->epoca, 'public_key' => $remoto->publica];
        config(['documentos_inscripcion.consumption_registry' => $registro]);
        $this->contexto['registro_consumos_sha256'] = hash('sha256', json_encode($registro, JSON_THROW_ON_ERROR));
        file_put_contents($this->dir.'/usos/registro.json', json_encode(['formato' => 1, 'registro_id' => $registro['id'],
            'epoca' => $registro['epoch'], 'clave_sha256' => hash('sha256', base64_decode($registro['public_key']))], JSON_THROW_ON_ERROR));
        $adaptador = new \App\Services\AdaptadorTestigoDocumental($remoto, $registro['id'], $registro['epoch'], $registro['public_key'], fn () => now()->timestamp);
        $this->app->instance(\App\Contracts\RegistroConsumosIndependiente::class, $adaptador);
        [, $signed] = $this->preparar(); $this->validar($signed);
        $primera = app(AutorizacionTransicionDocumental::class); $segunda = clone $primera;
        $acciones = 0; config(['documentos_inscripcion.transition_guard_enabled' => true]);
        $accion = function () use (&$acciones) { $acciones++; return \Illuminate\Support\Facades\Artisan::call('inscripciones:transicionar-documentos', $this->argumentos()); };
        $this->assertSame(0, $primera->usar($accion));
        try { $segunda->usar($accion); $this->fail('No debe ejecutar dos transiciones.'); }
        catch (RuntimeException $e) { $this->assertSame('AUTORIZACION_CONSUMIDA', $e->getMessage()); }
        $this->assertSame(1, $acciones); $this->assertSame(1, $remoto->consumos());
        $this->assertSame(1, DocumentoInscripcion::count()); $this->assertCount(2, Storage::disk('public')->allFiles());
        $sobre = json_decode(file_get_contents($this->dir.'/usos/'.$this->payload['id'].'.consumida'), true, 16, JSON_THROW_ON_ERROR);
        $r = \App\Services\ProtocoloTestigoDocumental::verificar($sobre, 'recibo', $registro['public_key']);
        $this->assertSame(2, $r['formato']); $this->assertSame('nuevo', $r['resultado']);
        $this->assertSame($this->solicitud['lote'], $r['lote']); $this->assertSame($this->contexto['ejecutor_sid'], $r['ejecutor_sid']);
    }

    public function test_cambio_de_artefacto_despues_de_validar_impide_consumir(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed);
        file_put_contents($this->dir.'/respaldo.sql', 'cambio ficticio');
        try { app(AutorizacionTransicionDocumental::class)->usar(fn () => true); $this->fail('Debió fallar'); }
        catch (RuntimeException $e) { $this->assertSame('HUELLA_AUTORIZADA_NO_COINCIDE', $e->getMessage()); }
        $this->assertSame([], glob($this->dir.'/usos/*.consumida'));
    }

    public function test_dos_validaciones_concurrentes_no_pueden_consumir_el_mismo_permiso(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed);
        $primera = app(AutorizacionTransicionDocumental::class); $segunda = clone $primera;
        $this->assertTrue($primera->usar(fn () => true));
        $this->expectExceptionMessage('AUTORIZACION_CONSUMIDA'); $segunda->usar(fn () => true);
    }

    public function test_clave_modificada_despues_de_validar_se_detecta_antes_de_consumir(): void
    {
        [, $signed] = $this->preparar(); $this->validar($signed);
        file_put_contents($this->dir.'/clave.txt', base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair())));
        $this->expectExceptionMessage('HUELLA_AUTORIZADA_NO_COINCIDE');
        app(AutorizacionTransicionDocumental::class)->usar(fn () => true);
    }

    public function test_root_de_barrera_real_no_puede_colarse_en_ejecucion_de_pruebas(): void
    {
        $this->preparar(); config(['documentos_inscripcion.transition_control_root' => base_path('storage/framework/transicion-documental')]);
        $this->expectExceptionMessage('ALMACENAMIENTO_NO_AISLADO'); app(ControlTransicionDocumental::class)->exigirAislamiento();
    }

    public function test_auditoria_sin_prueba_de_restauracion_es_rechazada_aunque_esta_firmada(): void
    {
        $this->preparar(); $path = $this->payload['auditoria']['ruta'];
        $a = json_decode(file_get_contents($path), true); $a['restauracion_verificada'] = false;
        file_put_contents($path, json_encode($a)); $this->payload['auditoria']['sha256'] = hash_file('sha256', $path);
        $this->expectExceptionMessage('RESPALDO_NO_CORRESPONDE_AL_ESTADO'); $this->validar($this->firmar());
    }

    public function test_permiso_no_validado_no_puede_abrir_barrera_real(): void
    {
        $manager = DB::getFacadeRoot(); DB::shouldReceive('connection')->never();
        try { app(ControlTransicionDocumental::class)->exclusivaReal(fn () => true); $this->fail('Debió bloquear'); }
        catch (RuntimeException $e) { $this->assertSame('AUTORIZACION_REAL_REQUERIDA', $e->getMessage()); }
        finally { DB::swap($manager); }
    }

    public function test_descartar_documento_nuevo_desde_tarea_cli_respeta_barrera(): void
    {
        $ruta = 'solicitudes/11111111-1111-1111-1111-111111111111.pdf';
        Storage::disk('inscripciones')->put($ruta, '%PDF-ficticio');
        config(['documentos_inscripcion.transition_guard_enabled' => true]);
        app(ControlTransicionDocumental::class)->exclusiva(fn () => app(ControlTransicionDocumental::class)->congelar());
        try { app(DocumentosInscripcion::class)->descartarNuevo($ruta); $this->fail('Debió bloquear'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(503, $e->getStatusCode()); }
        $this->assertTrue(Storage::disk('inscripciones')->exists($ruta));
    }

    public function test_dar_un_permiso_a_simulacion_no_lo_consume_ni_activa_barrera(): void
    {
        [, $signed] = $this->preparar();
        $this->artisan('inscripciones:transicionar-documentos', ['--autorizacion' => $signed[0], '--autorizacion-sha256' => $signed[1]])
            ->expectsOutputToContain('"modo":"simulacion"')->assertSuccessful();
        $this->assertSame([], glob($this->dir.'/usos/*.consumida'));
        $this->assertDirectoryDoesNotExist(storage_path('framework/transicion-documental'));
        $this->assertSame([], Storage::disk('inscripciones')->allFiles());
        $this->assertSame(0, DocumentoInscripcion::count());
    }

    public function test_barrera_real_desactivada_impide_preparar_sin_abrir_mysql(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $manager = DB::getFacadeRoot(); DB::shouldReceive('connection')->never();
        try { app(EntornoTransicionReal::class)->preparar($this->dir.'/cuarentena', str_repeat('0', 64)); $this->fail('Debió impedir preparación'); }
        catch (RuntimeException $e) { $this->assertSame('BARRERA_REAL_DESACTIVADA', $e->getMessage()); }
        finally { DB::swap($manager); $this->app->detectEnvironment(fn () => 'testing'); }
    }

    public static function conexionesIncorrectas(): array { return [['host', 'localhost'], ['host', 'cloud.test'], ['port', 4000], ['database', 'otro']]; }

    #[DataProvider('conexionesIncorrectas')]
    public function test_adaptador_rechaza_configuracion_mysql_distinta_antes_de_conectar(string $key, mixed $value): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['database.default' => 'mysql', 'database.connections.mysql.url' => null,
            'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => 3306,
            'database.connections.mysql.database' => 'itm', 'database.connections.mysql.'.$key => $value]);
        $manager = DB::getFacadeRoot(); DB::shouldReceive('connection')->never();
        try { app(EntornoTransicionReal::class)->verificarConexion(); $this->fail('Debió rechazar configuración'); }
        catch (RuntimeException $e) { $this->assertSame('CONEXION_REAL_NO_PERMITIDA', $e->getMessage()); }
        finally { DB::swap($manager); config(['database.default' => 'sqlite']); $this->app->detectEnvironment(fn () => 'testing'); }
    }

    public function test_registro_de_consumos_de_prueba_no_puede_apuntar_al_proyecto(): void
    {
        [, $signed] = $this->preparar();
        $this->expectExceptionMessage('ALMACENAMIENTO_NO_AISLADO');
        app(AutorizacionTransicionDocumental::class)->validar($signed[0], $signed[1], $this->dir.'/clave.txt',
            $this->contexto, $this->solicitud, base_path('storage/permisos-no-crear'), hash_file('sha256', $this->dir.'/clave.txt'));
    }

    public function test_lote_ficticio_completo_seis_versiones_y_ciento_tres_sueltos_conservados(): void
    {
        foreach (range(1, 8) as $n) {
            $ruta = $n <= 6 ? 'solicitudes/ficticio-'.$n.'.pdf' : null;
            Inscripcion::factory()->create(['documento_path' => $ruta]);
            if ($ruta) Storage::disk('public')->put($ruta, '%PDF-ficticio-asociado-'.$n);
        }
        foreach (range(1, 103) as $n) Storage::disk('public')->put('solicitudes/suelto-'.$n.'.pdf', '%PDF-ficticio-sin-propietario-'.$n);
        $signed = $this->firmarInventario(); $this->validar($signed);
        $before = Inscripcion::orderBy('id')->get()->map->getRawOriginal()->all();
        config(['documentos_inscripcion.transition_guard_enabled' => true]);
        $exit = app(AutorizacionTransicionDocumental::class)->usar(fn () => \Illuminate\Support\Facades\Artisan::call(
            'inscripciones:transicionar-documentos', $this->argumentos()));
        $this->assertSame(0, $exit); $this->assertSame(6, DocumentoInscripcion::count());
        $this->assertSame($before, Inscripcion::orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertCount(109, Storage::disk('public')->allFiles());
        $this->assertCount(6, Storage::disk('inscripciones')->allFiles());
        $this->assertCount(103, glob($this->solicitud['cuarentena'].'/lote-ficticio/*.pdf'));
        $plan = json_decode(file_get_contents($this->solicitud['inventario']), true);
        foreach ($plan['archivos'] as $f) {
            $dest = $f['inscripcion_id'] !== null ? Storage::disk('inscripciones')->path($f['ruta'])
                : $this->solicitud['cuarentena'].'/lote-ficticio/'.$f['id'].'.pdf';
            $this->assertSame($f['sha256'], hash_file('sha256', $dest));
            $this->assertSame($f['sha256'], hash_file('sha256', Storage::disk('public')->path($f['ruta'])));
        }
    }
}
