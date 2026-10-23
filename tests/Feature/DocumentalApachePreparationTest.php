<?php

namespace Tests\Feature;

use App\Models\{Alumno, Inscripcion, User};
use App\Services\ControlTransicionDocumental;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Solo Apache independiente, discos temporales y SQLite en memoria. Nunca transición. */
class DocumentalApachePreparationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach (['public', 'inscripciones'] as $disk) {
            $fake = Storage::fake($disk);
            config(["filesystems.disks.$disk.root" => rtrim($fake->path(''), '/\\')]);
            app(ControlTransicionDocumental::class)->exigirTemporal($fake->path(''));
        }
        config(['documentos_inscripcion.enabled' => true, 'documentos_inscripcion.history_enabled' => false,
            'documentos_inscripcion.legacy_read_enabled' => false]);
    }

    private function requestStatic(int $port, string $method, string $path): array
    {
        $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true,
            'follow_location' => 0, 'timeout' => 3, 'header' => "Connection: close\r\n"]]);
        $body = file_get_contents("http://127.0.0.1:$port$path", false, $context);
        $this->assertNotFalse($body);
        $this->assertNotEmpty($http_response_header);
        preg_match('/^HTTP\/\S+\s+(\d+)/', $http_response_header[0], $match);
        return [(int) $match[1], $body];
    }

    public function test_apache_rechaza_get_head_y_alias_sin_afectar_recursos_ni_descarga_laravel(): void
    {
        $binary = getenv('ITM_APACHE_TEST_BIN') ?: 'C:/wamp64/bin/apache/apache2.4.65/bin/httpd.exe';
        if (!is_file($binary)) {
            if (getenv('ITM_REQUIRE_APACHE_TESTS') === '1') $this->fail('Apache aislado es obligatorio para aceptar esta fase.');
            $this->markTestSkipped('Indicar ITM_APACHE_TEST_BIN; no se utilizó el servidor activo.');
        }
        $base = storage_path('apache-preparacion-'.bin2hex(random_bytes(8)));
        app(ControlTransicionDocumental::class)->exigirTemporal($base);
        mkdir($base.'/web', 0700, true);
        $public = rtrim(Storage::disk('public')->path(''), '/\\');
        $dummy = "%PDF-1.4\nSOLO DOCUMENTO FICTICIO SIN DATOS PERSONALES\n%%EOF";
        Storage::disk('public')->put('solicitudes/ficticio.pdf', $dummy);
        Storage::disk('public')->put('solicitudes-publicas/recurso.pdf', $dummy);
        Storage::disk('public')->put('imagenes/recurso.txt', 'RECURSO PUBLICO FICTICIO');
        file_put_contents($base.'/web/estado.txt', 'INSTANCIA AISLADA');
        $link = $base.'/web/storage';
        if (PHP_OS_FAMILY === 'Windows') {
            (new Process(['cmd.exe', '/d', '/c', 'mklink', '/J', str_replace('/', '\\', $link), str_replace('/', '\\', $public)]))->mustRun();
        } else {
            $this->assertTrue(symlink($public, $link));
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $this->assertNotFalse($socket, $error);
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $normal = fn ($p) => str_replace('\\', '/', $p);
        $server = $normal(dirname(dirname($binary)));
        $root = $normal($base);
        $disk = $normal($public);
        $example = $normal(base_path('docs/examples/apache-solicitudes-private.conf.example'));
        $config = "ServerRoot \"$server\"\nListen 127.0.0.1:$port\nServerName 127.0.0.1\n"
            ."PidFile \"$root/httpd.pid\"\nErrorLog \"$root/error.log\"\nLogLevel warn\n"
            ."LoadModule authz_core_module \"$server/modules/mod_authz_core.so\"\n"
            ."LoadModule authz_host_module \"$server/modules/mod_authz_host.so\"\n"
            ."LoadModule alias_module \"$server/modules/mod_alias.so\"\n"
            ."DocumentRoot \"$root/web\"\n<Directory />\nAllowOverride None\nRequire all denied\n</Directory>\n"
            ."<Directory \"$root/web\">\nOptions FollowSymLinks\nAllowOverride None\nRequire local\n</Directory>\n"
            ."<Directory \"$disk\">\nOptions FollowSymLinks\nAllowOverride None\nRequire local\n</Directory>\n"
            ."Alias /alias-ficticio/ \"$disk/solicitudes/\"\n"
            ."Define DOCUMENTAL_PUBLIC_ROOT \"$disk\"\nInclude \"$example\"\n";
        if (PHP_OS_FAMILY === 'Windows') $config .= "ThreadsPerChild 2\n";
        file_put_contents($base.'/httpd.conf', $config);
        (new Process([$binary, '-t', '-f', $base.'/httpd.conf']))->mustRun();
        // Sin shell ni taskkill /T: -X crea un solo proceso, con salida solo temporal.
        $apache = proc_open([$binary, '-X', '-f', $base.'/httpd.conf'], [
            0 => ['pipe', 'r'], 1 => ['file', $base.'/stdout.log', 'a'], 2 => ['file', $base.'/stderr.log', 'a'],
        ], $pipes, $base, null, ['bypass_shell' => true, 'create_new_console' => false]);
        $this->assertIsResource($apache);
        fclose($pipes[0]);
        try {
            $ready = false;
            for ($i = 0; $i < 100; $i++) {
                if (!proc_get_status($apache)['running']) $this->fail('Apache aislado no arrancó: '.file_get_contents($base.'/stderr.log'));
                $probe = @stream_socket_client("tcp://127.0.0.1:$port", $errno, $error, .1);
                if ($probe) { fclose($probe); $ready = true; break; }
                usleep(50000);
            }
            $this->assertTrue($ready, 'No arrancó el listener aislado.');
            foreach (['GET', 'HEAD'] as $method) {
                foreach (['/storage/solicitudes/ficticio.pdf', '/storage/SOLICITUDES/ficticio.pdf', '/storage/solicitudes/sub/no-existe.pdf',
                    '/storage/%73olicitudes/ficticio.pdf', '/alias-ficticio/ficticio.pdf'] as $path) {
                    [$status, $body] = $this->requestStatic($port, $method, $path);
                    $this->assertSame(403, $status, "$method $path");
                    $this->assertStringNotContainsString('SOLO DOCUMENTO FICTICIO', $body);
                    if ($method === 'HEAD') $this->assertSame('', $body);
                }
                foreach (['/estado.txt', '/storage/imagenes/recurso.txt', '/storage/solicitudes-publicas/recurso.pdf'] as $path) {
                    [$status] = $this->requestStatic($port, $method, $path);
                    $this->assertSame(200, $status, "$method $path");
                }
            }
            [, $body] = $this->requestStatic($port, 'GET', '/estado.txt');
            $this->assertSame('INSTANCIA AISLADA', $body);

            // El mismo fichero denegado por Apache puede leerse tras autorizar en Laravel.
            config(['documentos_inscripcion.legacy_read_enabled' => true]);
            $owner = User::factory()->create(['role' => 'alumno']);
            $alumno = Alumno::factory()->create(['user_id' => $owner->id]);
            $ins = Inscripcion::factory()->create(['alumno_id' => $alumno->id, 'documento_path' => 'solicitudes/ficticio.pdf']);
            foreach ([[$owner, 'alumno.inscripciones.documento'], [User::factory()->create(['role' => 'admin']), 'admin.solicitudes.documento']] as [$actor, $route]) {
                $r = $this->actingAs($actor)->get(route($route, $ins))->assertOk()->assertDownload();
                $this->assertSame($dummy, file_get_contents($r->baseResponse->getFile()->getPathname()));
                $this->actingAs($actor)->head(route($route, $ins))->assertOk();
            }
            Storage::disk('inscripciones')->put('solicitudes/ficticio.pdf', $dummy);
            config(['documentos_inscripcion.legacy_read_enabled' => false]);
            $r = $this->actingAs($owner)->get(route('alumno.inscripciones.documento', $ins))->assertOk();
            $this->assertSame($dummy, file_get_contents($r->baseResponse->getFile()->getPathname()));
        } finally {
            if (proc_get_status($apache)['running']) proc_terminate($apache);
            for ($i = 0; $i < 60 && proc_get_status($apache)['running']; $i++) usleep(50000);
            $this->assertFalse(proc_get_status($apache)['running'], 'No se detuvo la instancia Apache aislada.');
            proc_close($apache);
            // Retirar solo la junction: nunca recorrer recursivamente su destino.
            if (PHP_OS_FAMILY === 'Windows' || !is_link($link)) $this->assertTrue(rmdir($link));
            else $this->assertTrue(unlink($link));
        }
    }
}
