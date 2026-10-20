<?php

namespace Tests\Feature;

use App\Models\{Alumno, DocumentoInscripcion, Inscripcion, User};
use App\Services\{ControlTransicionDocumental, DocumentosInscripcion, HistorialDocumentosInscripcion, TransicionDocumental};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TransicionDocumentalTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->useStoragePath(sys_get_temp_dir().'/itm-transicion-'.bin2hex(random_bytes(10)));
        foreach (['public', 'inscripciones'] as $disk) {
            $fake = Storage::fake($disk);
            config(["filesystems.disks.$disk.root" => rtrim($fake->path(''), '/\\')]);
        }
        config(['documentos_inscripcion.enabled' => false, 'documentos_inscripcion.history_enabled' => false,
            'documentos_inscripcion.legacy_read_enabled' => false, 'documentos_inscripcion.transition_guard_enabled' => false]);
    }

    private function contexto(): array
    {
        $ins = Inscripcion::factory()->create(['documento_path' => 'solicitudes/ficticio.pdf']);
        Storage::disk('public')->put($ins->documento_path, "%PDF-1.4\nDocumento ficticio\n%%EOF");
        Storage::disk('public')->put('solicitudes/desconocido.pdf', "%PDF-1.4\nSin propietario ficticio\n%%EOF");
        return [$ins, $this->parametros()];
    }

    private function parametros(): array
    {
        $inventario = storage_path('inventario-autorizado.json');
        file_put_contents($inventario, json_encode(app(TransicionDocumental::class)->planificar(), JSON_THROW_ON_ERROR));
        config(['documentos_inscripcion.transition_guard_enabled' => true]);
        return ['--ejecutar' => true, '--confirmar' => 'INCORPORAR_DOCUMENTOS', '--inventario' => $inventario,
            '--sha256' => hash_file('sha256', $inventario), '--cuarentena' => storage_path('cuarentena-aislada'), '--lote' => 'prueba'];
    }

    private function ejecutar(array $args): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('inscripciones:transicionar-documentos', $args);
    }

    public function test_simulacion_no_crea_directorios_archivos_bloqueos_ni_registros(): void
    {
        [$ins] = $this->contexto();
        $antes = $ins->fresh()->getRawOriginal();
        $nuevo = storage_path('destino-aun-inexistente');
        config(['filesystems.disks.inscripciones.root' => $nuevo]);
        $publicos = Storage::disk('public')->allFiles();
        $this->artisan('inscripciones:transicionar-documentos')->expectsOutputToContain('"modo":"simulacion"')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($nuevo);
        $this->assertDirectoryDoesNotExist(storage_path('framework/transicion-documental'));
        $this->assertDirectoryDoesNotExist(storage_path('cuarentena-aislada'));
        $this->assertSame($publicos, Storage::disk('public')->allFiles());
        $this->assertSame($antes, $ins->fresh()->getRawOriginal());
        $this->assertSame(0, DocumentoInscripcion::count());
        $this->assertFalse((bool) config('documentos_inscripcion.enabled'));
    }

    public function test_copia_historial_cuarentena_y_reintento_idempotente_conservan_originales(): void
    {
        [$ins, $args] = $this->contexto();
        $antes = $ins->fresh()->getRawOriginal();
        $original = Storage::disk('public')->get($ins->documento_path);
        $this->ejecutar($args)->assertSuccessful();
        $v = DocumentoInscripcion::sole();
        $this->assertSame(1, $v->version); $this->assertTrue($v->vigente);
        $this->assertSame($ins->id, $v->inscripcion_id); $this->assertSame('inscripciones', $v->disco);
        $this->assertSame($ins->documento_path, $v->ruta);
        $this->assertSame(strlen($original), $v->bytes); $this->assertSame(hash('sha256', $original), $v->sha256);
        $this->assertLessThanOrEqual(5, abs(now()->getTimestamp() - $v->registrado_at->getTimestamp()));
        $this->assertSame($original, Storage::disk('inscripciones')->get($v->ruta));
        $this->assertSame($original, Storage::disk('public')->get($v->ruta));
        $this->assertSame($antes, $ins->fresh()->getRawOriginal());
        $id = hash('sha256', "public\0solicitudes/desconocido.pdf");
        $q = $args['--cuarentena'].'/prueba/';
        $this->assertSame(Storage::disk('public')->get('solicitudes/desconocido.pdf'), file_get_contents($q.$id.'.pdf'));
        $manifest = json_decode(file_get_contents($q.'manifiesto.json'), true);
        $this->assertSame($args['--sha256'], $manifest['inventario_sha256']);
        $this->assertNull($manifest['plan']['archivos'][0]['inscripcion_id']); // desconocido, orden léxico.
        $this->assertFileExists($q.'completado.json');
        $this->assertTrue(app(ControlTransicionDocumental::class)->bloqueado());
        $this->ejecutar($args)->assertSuccessful();
        $this->assertSame(1, DocumentoInscripcion::count()); $this->assertSame($v->id, DocumentoInscripcion::sole()->id);
        $this->assertSame(2, count(Storage::disk('public')->allFiles()));
        $this->assertFalse((bool) config('documentos_inscripcion.enabled'));
        $this->assertFalse((bool) config('documentos_inscripcion.history_enabled'));
    }

    public function test_rechaza_ejecucion_sin_confirmacion_y_sin_activar_barrera(): void
    {
        [, $args] = $this->contexto(); unset($args['--confirmar']);
        $this->ejecutar($args)->expectsOutputToContain('CONFIRMACION_EXPLICITA_REQUERIDA')->assertFailed();
        $this->assertDirectoryDoesNotExist(storage_path('framework/transicion-documental'));
        $this->assertSame([], Storage::disk('inscripciones')->allFiles());
    }

    public function test_rechaza_mysql_antes_de_abrir_una_conexion(): void
    {
        [, $args] = $this->contexto();
        $manager = DB::getFacadeRoot();
        config(['database.default' => 'mysql']);
        DB::shouldReceive('connection')->never();
        try { app(TransicionDocumental::class)->ejecutar($args['--inventario'], $args['--sha256'], $args['--cuarentena'], 'prueba'); $this->fail('Debió bloquear MySQL'); }
        catch (RuntimeException $e) { $this->assertSame('EJECUCION_REAL_NO_HABILITADA', $e->getMessage()); }
        finally { DB::swap($manager); config(['database.default' => 'sqlite']); }
    }

    public function test_rechaza_disco_de_pruebas_que_apunta_al_proyecto(): void
    {
        [, $args] = $this->contexto(); config(['filesystems.disks.inscripciones.root' => base_path('storage/app/private/inscripciones')]);
        $this->ejecutar($args)->expectsOutputToContain('ALMACENAMIENTO_NO_AISLADO')->assertFailed();
        $this->assertSame(0, DocumentoInscripcion::count());
    }

    public static function opcionesActivas(): array { return [['enabled'], ['history_enabled'], ['legacy_read_enabled']]; }

    #[DataProvider('opcionesActivas')]
    public function test_rechaza_opciones_documentales_activas(string $opcion): void
    {
        [, $args] = $this->contexto(); config(['documentos_inscripcion.'.$opcion => true]);
        $this->ejecutar($args)->expectsOutputToContain('OPCIONES_INCOMPATIBLES')->assertFailed();
        $this->assertSame([], Storage::disk('inscripciones')->allFiles());
    }

    public function test_rechaza_inventario_alterado_y_archivo_nuevo_no_autorizado(): void
    {
        [, $args] = $this->contexto();
        file_put_contents($args['--inventario'], '{}');
        $this->ejecutar($args)->expectsOutputToContain('INVENTARIO_NO_AUTORIZADO')->assertFailed();
        $args = $this->parametros(); Storage::disk('public')->put('solicitudes/nuevo.pdf', 'ficticio');
        $this->ejecutar($args)->expectsOutputToContain('INVENTARIO_ALTERADO')->assertFailed();
        $this->assertSame(0, DocumentoInscripcion::count());
    }

    public function test_rechaza_archivo_alterado_aunque_conserve_el_tamano(): void
    {
        [$ins, $args] = $this->contexto();
        $bytes = Storage::disk('public')->get($ins->documento_path);
        Storage::disk('public')->put($ins->documento_path, str_repeat('X', strlen($bytes)));
        $this->ejecutar($args)->expectsOutputToContain('INVENTARIO_ALTERADO')->assertFailed();
        $this->assertSame([], Storage::disk('inscripciones')->allFiles());
    }

    public static function rutas(): array { return [['../a.pdf'], ['/tmp/a.pdf'], ['C:\\a.pdf'], ['solicitudes/../a.pdf'], ['https://ejemplo.test/a.pdf']]; }

    #[DataProvider('rutas')]
    public function test_rechaza_referencias_peligrosas(string $ruta): void
    {
        [$ins] = $this->contexto(); $ins->update(['documento_path' => $ruta]);
        $this->artisan('inscripciones:transicionar-documentos')->expectsOutputToContain('REFERENCIA_INSEGURA')->assertFailed();
    }

    public function test_rechaza_referencias_compartidas_y_original_faltante(): void
    {
        [$ins] = $this->contexto();
        $otro = Inscripcion::factory()->create(['documento_path' => $ins->documento_path]);
        $this->artisan('inscripciones:transicionar-documentos')->expectsOutputToContain('REFERENCIA_COMPARTIDA')->assertFailed();
        $otro->update(['documento_path' => null]); Storage::disk('public')->delete($ins->documento_path);
        $this->artisan('inscripciones:transicionar-documentos')->expectsOutputToContain('ORIGINAL_FALTANTE')->assertFailed();
    }

    public function test_destino_existente_distinto_no_se_sobrescribe_e_identico_se_reutiliza(): void
    {
        [$ins, $args] = $this->contexto();
        Storage::disk('inscripciones')->put($ins->documento_path, 'ocupado');
        $this->ejecutar($args)->expectsOutputToContain('DESTINO_DIFERENTE')->assertFailed();
        $this->assertSame('ocupado', Storage::disk('inscripciones')->get($ins->documento_path));
        Storage::disk('inscripciones')->put($ins->documento_path, Storage::disk('public')->get($ins->documento_path));
        $this->ejecutar($args)->assertSuccessful();
        $this->assertSame(1, DocumentoInscripcion::count());
    }

    public function test_rechaza_destino_publico_y_cuarentena_dentro_del_publico(): void
    {
        [, $args] = $this->contexto();
        $args['--cuarentena'] = Storage::disk('public')->path('cuarentena');
        $this->ejecutar($args)->expectsOutputToContain('CUARENTENA_O_INVENTARIO_INSEGURO')->assertFailed();
        config(['filesystems.disks.inscripciones.root' => config('filesystems.disks.public.root')]);
        $this->artisan('inscripciones:transicionar-documentos')->expectsOutputToContain('DESTINO_PUBLICO')->assertFailed();
    }

    public function test_fallo_parcial_de_copia_conserva_original_y_permite_reanudar(): void
    {
        [, $args] = $this->contexto();
        $mock = Mockery::mock(TransicionDocumental::class, [app(DocumentosInscripcion::class), app(ControlTransicionDocumental::class), app(HistorialDocumentosInscripcion::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('transferir')->once()->andReturnUsing(function ($in, $out) { fwrite($out, 'X'); return 1; });
        $this->app->instance(TransicionDocumental::class, $mock);
        $this->ejecutar($args)->expectsOutputToContain('COPIA_INCOMPLETA')->assertFailed();
        $this->assertSame(0, DocumentoInscripcion::count());
        $this->assertSame(2, count(Storage::disk('public')->allFiles()));
        $this->assertTrue(app(ControlTransicionDocumental::class)->bloqueado());
        $this->app->forgetInstance(TransicionDocumental::class);
        $this->ejecutar($args)->assertSuccessful();
    }

    public function test_fallo_sql_revierte_todo_el_historial_y_conserva_copias_para_reanudar(): void
    {
        [$ins] = $this->contexto();
        $otro = Inscripcion::factory()->create(['documento_path' => 'solicitudes/segundo.pdf']);
        Storage::disk('public')->put($otro->documento_path, '%PDF-ficticio-segundo');
        $args = $this->parametros(); $n = 0;
        DocumentoInscripcion::creating(function () use (&$n) { if (++$n === 2) throw new RuntimeException('FALLO_SQL_SIMULADO'); });
        try { $this->ejecutar($args)->assertFailed(); }
        finally { DocumentoInscripcion::flushEventListeners(); }
        $this->assertSame(0, DocumentoInscripcion::count());
        $this->assertTrue(Storage::disk('inscripciones')->exists($ins->documento_path));
        $this->assertTrue(Storage::disk('inscripciones')->exists($otro->documento_path));
        $this->ejecutar($args)->assertSuccessful();
        $this->assertSame(2, DocumentoInscripcion::count());
    }

    public static function locks(): array { return [[LOCK_EX], [LOCK_SH]]; }

    #[DataProvider('locks')]
    public function test_transicion_rechaza_otro_transicionista_o_escritor_en_curso(int $mode): void
    {
        [, $args] = $this->contexto(); $dir = app(ControlTransicionDocumental::class)->directorio(); mkdir($dir, 0700, true);
        $hold = fopen($dir.'/transicion.lock', 'c+b'); $this->assertTrue(flock($hold, $mode | LOCK_NB));
        try { $this->ejecutar($args)->expectsOutputToContain('TRANSICION_CONCURRENTE')->assertFailed(); }
        finally { flock($hold, LOCK_UN); fclose($hold); }
        $this->assertSame(0, DocumentoInscripcion::count());
    }

    public function test_historial_contradictorio_detiene_sin_cambiar_documento_path(): void
    {
        [$ins, $args] = $this->contexto(); $this->ejecutar($args)->assertSuccessful();
        DocumentoInscripcion::sole()->update(['version' => 2]);
        $this->ejecutar($args)->expectsOutputToContain('HISTORIAL_INCOMPATIBLE')->assertFailed();
        $this->assertSame('solicitudes/ficticio.pdf', $ins->fresh()->documento_path);
        $this->assertSame(2, DocumentoInscripcion::sole()->version);
    }

    public function test_estructura_ausente_y_barrera_desactivada_impiden_ejecutar(): void
    {
        [, $args] = $this->contexto(); config(['documentos_inscripcion.transition_guard_enabled' => false]);
        $this->ejecutar($args)->expectsOutputToContain('BARRERA_NO_DISPONIBLE')->assertFailed();
        config(['documentos_inscripcion.transition_guard_enabled' => true]); Schema::drop('documentos_inscripcion');
        $this->ejecutar($args)->expectsOutputToContain('ESTRUCTURA_DOCUMENTAL_NO_DISPONIBLE')->assertFailed();
    }

    public function test_barrera_bloquea_las_tres_rutas_generacion_pdf_y_tareas_eloquent(): void
    {
        $u = User::factory()->create(['role' => 'alumno']); Alumno::factory()->create(['user_id' => $u->id]);
        config(['documentos_inscripcion.transition_guard_enabled' => true]);
        $this->artisan('inscripciones:bloqueo-documental', ['--activar' => true, '--confirmar' => 'CAMBIAR_BLOQUEO_DOCUMENTAL'])->assertSuccessful();
        foreach (['alumno.inscribirse', 'alumno.inscripcion.subir', 'alumno.inscripcion.enviar'] as $name) {
            $this->actingAs($u)->post(route($name), [])->assertStatus(503);
        }
        $this->get(route('alumno.inscripcion.pdf'))->assertStatus(503);
        $this->assertSame(0, Inscripcion::count());
        try { Inscripcion::factory()->create(); $this->fail('No debe escribir durante bloqueo'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(503, $e->getStatusCode()); }
        $this->artisan('inscripciones:bloqueo-documental', ['--retirar' => true, '--confirmar' => 'CAMBIAR_BLOQUEO_DOCUMENTAL'])->assertSuccessful();
        $this->assertFalse(app(ControlTransicionDocumental::class)->bloqueado());
        $this->assertTrue(app(ControlTransicionDocumental::class)->operacion(fn () => true));
    }

    public function test_enlaces_y_junctions_se_rechazan(): void
    {
        [$ins] = $this->contexto();
        $outside = storage_path('fuera-de-disco'); mkdir($outside); file_put_contents($outside.'/a.pdf', '%PDF-ficticio');
        $link = Storage::disk('public')->path('solicitudes/escape'); $created = @symlink($outside, $link);
        if (!$created && PHP_OS_FAMILY === 'Windows') {
            exec('cmd /c mklink /J '.escapeshellarg($link).' '.escapeshellarg($outside).' 2>NUL', $output, $exit);
            $created = $exit === 0;
        }
        if (!$created) $this->markTestSkipped('No se pueden crear enlaces aislados.');
        try { $this->artisan('inscripciones:transicionar-documentos')->assertFailed(); }
        finally { if (PHP_OS_FAMILY === 'Windows' || !is_link($link)) rmdir($link); else unlink($link); }
        $this->assertSame('solicitudes/ficticio.pdf', $ins->fresh()->documento_path);
    }

    public function test_original_modificado_durante_copia_se_detecta_antes_de_insertar(): void
    {
        [$ins, $args] = $this->contexto();
        $source = Storage::disk('public')->path($ins->documento_path);
        $mock = Mockery::mock(TransicionDocumental::class, [app(DocumentosInscripcion::class), app(ControlTransicionDocumental::class), app(HistorialDocumentosInscripcion::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('transferir')->once()->andReturnUsing(function ($in, $out) {
            $copied = stream_copy_to_stream($in, $out);
            file_put_contents(stream_get_meta_data($in)['uri'], 'cambio concurrente ficticio');
            return $copied;
        });
        $this->app->instance(TransicionDocumental::class, $mock);
        $this->ejecutar($args)->expectsOutputToContain('INTEGRIDAD_NO_COINCIDE')->assertFailed();
        $this->assertSame(0, DocumentoInscripcion::count());
        $this->assertSame($ins->documento_path, $ins->fresh()->documento_path);
        $this->assertFileExists($source);
    }

    public function test_error_de_escritura_no_elimina_origen_y_no_expone_paths(): void
    {
        [$ins, $args] = $this->contexto();
        // Un archivo ocupando el directorio impide escribir también en Windows,
        // sin depender de ACL o del usuario privilegiado que ejecute la suite.
        $blocked = Storage::disk('inscripciones')->path('solicitudes');
        file_put_contents($blocked, 'destino ocupado ficticio');
        $code = \Illuminate\Support\Facades\Artisan::call('inscripciones:transicionar-documentos', $args);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertSame(1, $code);
        $this->assertStringNotContainsString(storage_path(), $output);
        $this->assertStringNotContainsString($ins->documento_path, $output);
        $this->assertSame('destino ocupado ficticio', file_get_contents($blocked));
        $this->assertTrue(Storage::disk('public')->exists($ins->documento_path));
        $this->assertSame(0, DocumentoInscripcion::count());
    }

    public function test_huellas_repetidas_no_asocian_ni_fusionan_documentos_sueltos(): void
    {
        [$ins] = $this->contexto();
        Storage::disk('public')->put('solicitudes/duplicado.pdf', Storage::disk('public')->get('solicitudes/desconocido.pdf'));
        $args = $this->parametros();
        $this->artisan('inscripciones:transicionar-documentos')->expectsOutputToContain('"contenidos_repetidos":1')->assertSuccessful();
        $this->ejecutar($args)->assertSuccessful();
        $files = glob($args['--cuarentena'].'/prueba/*.pdf');
        $this->assertCount(2, $files);
        $this->assertNotSame(basename($files[0]), basename($files[1]));
        $this->assertSame(hash_file('sha256', $files[0]), hash_file('sha256', $files[1]));
        $this->assertSame(1, DocumentoInscripcion::count());
    }

    public function test_barrera_entre_procesos_independientes(): void
    {
        [, $args] = $this->contexto();
        $dir = app(ControlTransicionDocumental::class)->directorio(); mkdir($dir, 0700, true);
        $script = '$f=fopen('.var_export($dir.'/transicion.lock', true).',"c+b");'
            .'if(!flock($f,LOCK_EX|LOCK_NB)){exit(2);}echo "READY\\n";fflush(STDOUT);fgets(STDIN);flock($f,LOCK_UN);fclose($f);';
        $process = proc_open([PHP_BINARY, '-d', 'xdebug.mode=off', '-r', $script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        $this->assertIsResource($process);
        try {
            stream_set_timeout($pipes[1], 10);
            $this->assertSame("READY\n", str_replace("\r\n", "\n", fgets($pipes[1])));
            $this->ejecutar($args)->expectsOutputToContain('TRANSICION_CONCURRENTE')->assertFailed();
            $this->assertSame(0, DocumentoInscripcion::count());
        } finally {
            fwrite($pipes[0], "fin\n"); fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
            $this->assertSame(0, proc_close($process));
        }
    }

    public function test_versions_incorporadas_funcionan_con_descargas_autorizadas_existentes(): void
    {
        [$ins, $args] = $this->contexto(); $this->ejecutar($args)->assertSuccessful();
        $user = User::factory()->create(['role' => 'alumno']);
        $ins->alumno->update(['user_id' => $user->id]);
        config(['documentos_inscripcion.enabled' => true, 'documentos_inscripcion.history_enabled' => true]);
        $v = DocumentoInscripcion::sole();
        $response = $this->actingAs($user)->get(route('alumno.inscripciones.documentos.version', [$ins, $v]))
            ->assertOk()->assertDownload('solicitud-inscripcion.pdf');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($v->sha256, hash_file('sha256', $response->baseResponse->getFile()->getPathname()));
        $otro = User::factory()->create(['role' => 'alumno']); Alumno::factory()->create(['user_id' => $otro->id]);
        $this->actingAs($otro)->get(route('alumno.inscripciones.documentos.version', [$ins, $v]))->assertNotFound();
    }

    public function test_nuevos_escritores_se_rechazan_mientras_transicion_retiene_exclusiva(): void
    {
        $ins = Inscripcion::factory()->create();
        config(['documentos_inscripcion.transition_guard_enabled' => true]);
        app(ControlTransicionDocumental::class)->exclusiva(function () use ($ins) {
            try { $ins->update(['documento_path' => 'solicitudes/no-permitido.pdf']); $this->fail('Debió impedir la escritura'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(503, $e->getStatusCode()); }
        });
        $this->assertNull($ins->fresh()->documento_path);
    }

    public function test_query_builder_puede_eludir_barrera_cooperativa_en_sqlite_aislado(): void
    {
        // Caracteriza un riesgo: los eventos/save de Eloquent no protegen SQL directo.
        $ins = Inscripcion::factory()->create();
        config(['documentos_inscripcion.transition_guard_enabled' => true]);
        app(ControlTransicionDocumental::class)->exclusiva(function () use ($ins) {
            app(ControlTransicionDocumental::class)->congelar();
            $this->assertSame(1, DB::table('inscripciones')->where('id', $ins->id)
                ->update(['documento_path' => 'solicitudes/ficticio-sin-barrera.pdf']));
        });
        $this->assertTrue(app(ControlTransicionDocumental::class)->bloqueado());
        $this->assertSame('solicitudes/ficticio-sin-barrera.pdf', $ins->fresh()->documento_path);
    }

    public function test_no_retira_barrera_con_historial_incorporado_y_flujo_publico_heredado(): void
    {
        [, $args] = $this->contexto(); $this->ejecutar($args)->assertSuccessful();
        $options = ['--retirar' => true, '--confirmar' => 'CAMBIAR_BLOQUEO_DOCUMENTAL'];
        $this->artisan('inscripciones:bloqueo-documental', $options)->assertFailed();
        $this->assertTrue(app(ControlTransicionDocumental::class)->bloqueado());
        config(['documentos_inscripcion.enabled' => true, 'documentos_inscripcion.history_enabled' => true]);
        $this->artisan('inscripciones:bloqueo-documental', $options)->assertSuccessful();
        $this->assertFalse(app(ControlTransicionDocumental::class)->bloqueado());
    }
}
