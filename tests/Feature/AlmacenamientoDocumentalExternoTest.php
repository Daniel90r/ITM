<?php

namespace Tests\Feature;

use App\Services\{DocumentosInscripcion, EntornoTransicionReal};
use Illuminate\Support\Env;
use Illuminate\Support\Facades\{DB, File, Storage};
use RuntimeException;
use Tests\TestCase;

class AlmacenamientoDocumentalExternoTest extends TestCase
{
    private string $temporal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
        $this->temporal = sys_get_temp_dir().'/itm-raices-ficticias-'.bin2hex(random_bytes(12));
        mkdir($this->temporal, 0700);
    }

    protected function tearDown(): void
    {
        // Solo el directorio temporal exclusivo de esta prueba, nunca raíces configuradas reales.
        if (isset($this->temporal) && str_starts_with($this->temporal, sys_get_temp_dir().'/itm-raices-ficticias-')) {
            File::deleteDirectory($this->temporal);
        }
        parent::tearDown();
    }

    public function test_configuracion_externa_no_activa_opciones_documentales(): void
    {
        $repo = Env::getRepository();
        $keys = ['INSCRIPCION_DOCUMENTOS_ROOT', 'INSCRIPCION_TRANSICION_CONTROL_ROOT'];
        $previous = array_map(fn ($key) => $repo->get($key), $keys);
        try {
            // El bootstrap define estas variables antes de Dotenv: su repositorio
            // inmutable no permite sobrescribirlas con Repository::set().
            foreach ([$this->temporal.'/privado', $this->temporal.'/control'] as $i => $value) {
                putenv($keys[$i].'='.$value);
                $_ENV[$keys[$i]] = $_SERVER[$keys[$i]] = $value;
            }
            $fs = require base_path('config/filesystems.php');
            $docs = require base_path('config/documentos_inscripcion.php');
            $this->assertSame($this->temporal.'/privado', $fs['disks']['inscripciones']['root']);
            $this->assertSame($this->temporal.'/control', $docs['transition_control_root']);
            foreach (['enabled', 'history_enabled', 'legacy_read_enabled', 'transition_guard_enabled'] as $key) {
                $this->assertFalse((bool) $docs[$key]);
            }
            $this->assertFalse($fs['disks']['inscripciones']['serve']);
            $this->assertSame('private', $fs['disks']['inscripciones']['visibility']);
        } finally {
            foreach ($keys as $i => $key) {
                if ($previous[$i] === null) {
                    putenv($key);
                    unset($_ENV[$key], $_SERVER[$key]);
                } else {
                    putenv($key.'='.$previous[$i]);
                    $_ENV[$key] = $_SERVER[$key] = $previous[$i];
                }
            }
        }
    }

    public function test_arranque_aislado_no_hereda_raices_documentales_reales(): void
    {
        $control = app(\App\Services\ControlTransicionDocumental::class);
        $privado = (string) config('filesystems.disks.inscripciones.root');
        $control->exigirTemporal($privado);
        $control->exigirTemporal($control->directorio());
        $this->assertSame(storage_path('framework/transicion-documental'), $control->directorio());
        foreach (['enabled', 'history_enabled', 'legacy_read_enabled', 'transition_guard_enabled'] as $flag) {
            $this->assertFalse((bool) config('documentos_inscripcion.'.$flag));
        }
        $this->assertStringContainsString('itm-documentos-tests-', str_replace('\\', '/', $privado));
    }

    public function test_destino_en_raiz_http_es_rechazado_sin_crearlo(): void
    {
        $web = $this->temporal.'/http';
        $target = $web.'/privado';
        try {
            app(EntornoTransicionReal::class)->verificarFueraDeWeb($target, $web, $web.'/public');
            $this->fail('No debe admitir una raíz publicable.');
        } catch (RuntimeException $e) {
            $this->assertSame('DESTINO_PRIVADO_PUBLICABLE', $e->getMessage());
            $this->assertDirectoryDoesNotExist($target);
        }
    }

    public function test_rutas_inseguras_son_rechazadas(): void
    {
        foreach (['relativa/archivo', $this->temporal.'/../escape', $this->temporal.'/archivo:stream', $this->temporal."/nul\0"] as $path) {
            try {
                app(DocumentosInscripcion::class)->verificarRutaAbsoluta($path);
                $this->fail('Se admitió una ruta insegura.');
            } catch (RuntimeException $e) {
                $this->assertSame('Ruta documental no autorizada.', $e->getMessage());
            }
        }
    }

    public function test_almacenamiento_externo_conserva_bytes_y_rechaza_traversal(): void
    {
        config(['filesystems.disks.inscripciones.root' => $this->temporal.'/privado',
            'documentos_inscripcion.enabled' => true, 'documentos_inscripcion.transition_guard_enabled' => false]);
        Storage::forgetDisk('inscripciones');
        $bytes = "%PDF-1.4\nDOCUMENTO FICTICIO\n%%EOF";
        $ruta = app(DocumentosInscripcion::class)->guardar($bytes);
        $archivo = app(DocumentosInscripcion::class)->localizarEnDisco('inscripciones', $ruta);
        $this->assertSame(hash('sha256', $bytes), hash_file('sha256', $archivo));
        $this->assertStringStartsWith(str_replace('\\', '/', $this->temporal).'/privado/', str_replace('\\', '/', $archivo));
        $this->expectException(RuntimeException::class);
        app(DocumentosInscripcion::class)->rutaSegura('inscripciones', 'solicitudes/../../escape.pdf');
    }

    public function test_junction_o_enlace_no_puede_redirigir_el_destino(): void
    {
        mkdir($this->temporal.'/destino');
        $link = $this->temporal.'/enlace';
        if (PHP_OS_FAMILY === 'Windows') {
            $encoded = base64_encode(json_encode([$link, $this->temporal.'/destino']));
            $script = '$p=ConvertFrom-Json ([Text.Encoding]::UTF8.GetString([Convert]::FromBase64String("'.$encoded.'"))); New-Item -ItemType Junction -Path $p[0] -Target $p[1] -ErrorAction Stop | Out-Null';
            $this->powershell($script);
        } elseif (!symlink($this->temporal.'/destino', $link)) {
            $this->fail('No se pudo preparar el enlace ficticio.');
        }
        try {
            $this->expectException(RuntimeException::class);
            app(DocumentosInscripcion::class)->verificarRutaAbsoluta($link.'/ficticio.pdf');
        } finally {
            PHP_OS_FAMILY === 'Windows' ? rmdir($link) : unlink($link);
        }
    }

    public function test_acl_temporal_heredada_amplia_es_rechazada(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') $this->markTestSkipped('DACL de Windows.');
        // Solo lectura: no requiere privilegios WRITE_DAC del subproceso PHPUnit.
        // La aceptación de las DACL preparadas se verifica aparte en la inspección local.
        $this->expectExceptionMessage('ACL_REAL_NO_PRIVADA');
        app(EntornoTransicionReal::class)->verificarAcl($this->temporal);
    }

    private function powershell(string $script): void
    {
        $p = proc_open(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', '-'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($p);
        fwrite($pipes[0], $script."\n"); fclose($pipes[0]);
        stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $this->assertSame(0, proc_close($p), 'Preparación ficticia de Windows fallida: '.$error);
    }
}
