<?php

namespace Tests\Feature;

use App\Services\{ConsumosAutorizacionDocumental, EntornoTransicionReal, IdentidadEjecutorDocumental, RutasAutorizacionDocumental};
use Illuminate\Support\Facades\{DB, File};
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RutasAutorizacionDocumentalTest extends TestCase
{
    private string $temporal;
    private const SID = 'S-1-5-21-111-222-333-1001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->temporal = sys_get_temp_dir().'/itm-rutas-autorizacion-'.bin2hex(random_bytes(12));
        mkdir($this->temporal);
        mkdir($this->temporal.'/http');
        $this->mock(IdentidadEjecutorDocumental::class)->makePartial()->shouldReceive('sid')->andReturn(self::SID);
        config(['documentos_inscripcion.executor_sid' => self::SID,
            'documentos_inscripcion.http_roots' => [$this->temporal.'/http']]);
        foreach (['autoridad' => 'authorization_root', 'permisos' => 'permits_root', 'consumos' => 'consumption_root',
            'cuarentena' => 'quarantine_root', 'barrera' => 'transition_control_root', 'privado' => null] as $name => $key) {
            mkdir($this->temporal.'/'.$name);
            $marker = 'ITM-RAIZ-FICTICIA-'.$name.'-'.bin2hex(random_bytes(16));
            file_put_contents($this->temporal.'/'.$name.'/.itm-raiz', $marker);
            config([$key ? 'documentos_inscripcion.'.$key : 'filesystems.disks.inscripciones.root' => $this->temporal.'/'.$name,
                'documentos_inscripcion.root_identity_sha256.'.$name => hash('sha256', $marker)]);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->temporal) && str_starts_with($this->temporal, sys_get_temp_dir().'/itm-rutas-autorizacion-')) {
            File::deleteDirectory($this->temporal);
        }
        parent::tearDown();
    }

    public function test_raices_absolutas_verificadas_no_dependen_de_localappdata(): void
    {
        $original = getenv('LOCALAPPDATA');
        try {
            putenv('LOCALAPPDATA=perfil-ficticio-distinto');
            $rutas = app(RutasAutorizacionDocumental::class)->verificar();
            $this->assertCount(6, $rutas['rutas']);
            $this->assertSame(str_replace('\\', '/', $this->temporal).'/autoridad', app(EntornoTransicionReal::class)->autoridad());
            $this->assertSame(hash('sha256', file_get_contents($this->temporal.'/autoridad/.itm-raiz')), $rutas['identidades']['autoridad']);
        } finally { $original === false ? putenv('LOCALAPPDATA') : putenv('LOCALAPPDATA='.$original); }
    }

    public static function ambiguas(): array
    {
        return [['relativa'], ['%LOCALAPPDATA%/autoridad'], ['C:autoridad'], ['//servidor/recurso'], [''], [null]];
    }

    #[DataProvider('ambiguas')]
    public function test_raices_relativas_ambiguas_o_ausentes_son_rechazadas(?string $ruta): void
    {
        config(['documentos_inscripcion.authorization_root' => $ruta]);
        $this->expectException(RuntimeException::class);
        app(RutasAutorizacionDocumental::class)->verificar();
    }

    public function test_identidad_ejecutora_diferente_impide_verificar_raices(): void
    {
        config(['documentos_inscripcion.executor_sid' => 'S-1-5-21-111-222-333-1002']);
        $this->expectExceptionMessage('IDENTIDAD_EJECUTORA_NO_COINCIDE');
        app(RutasAutorizacionDocumental::class)->verificar();
    }

    public function test_destino_publicado_es_rechazado(): void
    {
        config(['documentos_inscripcion.authorization_root' => $this->temporal.'/http']);
        $this->expectExceptionMessage('DESTINO_PRIVADO_PUBLICABLE');
        app(RutasAutorizacionDocumental::class)->verificar();
    }

    public function test_raices_superpuestas_son_rechazadas(): void
    {
        config(['documentos_inscripcion.permits_root' => $this->temporal.'/autoridad']);
        $this->expectExceptionMessage('RAICES_PRIVADAS_SUPERPUESTAS');
        app(RutasAutorizacionDocumental::class)->verificar();
    }

    public function test_sin_inventario_http_completo_no_hay_preflight(): void
    {
        config(['documentos_inscripcion.http_roots' => []]);
        $this->expectExceptionMessage('PUBLICACIONES_HTTP_NO_CONFIGURADAS');
        app(RutasAutorizacionDocumental::class)->verificar();
    }

    public function test_marcador_de_raiz_sustituido_es_rechazado(): void
    {
        file_put_contents($this->temporal.'/autoridad/.itm-raiz', 'otra-identidad-ficticia');
        $this->expectExceptionMessage('IDENTIDAD_DE_RAIZ_NO_VERIFICADA');
        app(RutasAutorizacionDocumental::class)->verificar();
    }

    public function test_marcador_ausente_no_se_crea_automaticamente(): void
    {
        unlink($this->temporal.'/autoridad/.itm-raiz');
        try { app(RutasAutorizacionDocumental::class)->verificar(); $this->fail('Debe detenerse.'); }
        catch (RuntimeException $e) { $this->assertSame('IDENTIDAD_DE_RAIZ_NO_VERIFICADA', $e->getMessage()); }
        $this->assertFileDoesNotExist($this->temporal.'/autoridad/.itm-raiz');
    }

    public function test_dos_raices_no_pueden_compartir_marcador_de_identidad(): void
    {
        copy($this->temporal.'/autoridad/.itm-raiz', $this->temporal.'/permisos/.itm-raiz');
        config(['documentos_inscripcion.root_identity_sha256.permisos' => config('documentos_inscripcion.root_identity_sha256.autoridad')]);
        $this->expectExceptionMessage('IDENTIDADES_DE_RAIZ_REPETIDAS');
        app(RutasAutorizacionDocumental::class)->verificar();
    }

    public function test_permiso_fuera_de_su_raiz_no_se_admite(): void
    {
        file_put_contents($this->temporal.'/autoridad/permiso.json', '{}');
        $this->expectExceptionMessage('PERMISO_FUERA_DE_RAIZ_APROBADA');
        app(RutasAutorizacionDocumental::class)->exigirPermiso($this->temporal.'/autoridad/permiso.json');
    }

    public function test_permiso_en_su_raiz_absoluta_es_compatible(): void
    {
        $path = $this->temporal.'/permisos/permiso.json'; file_put_contents($path, '{}');
        app(RutasAutorizacionDocumental::class)->exigirPermiso($path);
        $this->assertFileExists($path);
    }

    public function test_adaptador_por_defecto_no_ofrece_garantias_reales(): void
    {
        $this->expectExceptionMessage('REGISTRO_INDEPENDIENTE_REAL_PENDIENTE');
        app(ConsumosAutorizacionDocumental::class)->exigirCanalReal();
    }

    public function test_junction_no_puede_sustituir_la_raiz_aprobada(): void
    {
        $link = $this->temporal.'/enlace'; $target = $this->temporal.'/autoridad';
        if (PHP_OS_FAMILY === 'Windows') {
            $encoded = base64_encode(json_encode([$link, $target]));
            $script = '$p=ConvertFrom-Json ([Text.Encoding]::UTF8.GetString([Convert]::FromBase64String("'.$encoded.'"))); New-Item -ItemType Junction -Path $p[0] -Target $p[1] -ErrorAction Stop | Out-Null';
            $process = proc_open(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', '-'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertIsResource($process);
            fwrite($pipes[0], $script."\n"); fclose($pipes[0]);
            stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            $this->assertSame(0, proc_close($process));
        } else { $this->assertTrue(symlink($target, $link)); }
        try {
            config(['documentos_inscripcion.authorization_root' => $link]);
            $this->expectException(RuntimeException::class);
            app(RutasAutorizacionDocumental::class)->verificar();
        } finally { PHP_OS_FAMILY === 'Windows' ? rmdir($link) : unlink($link); }
    }
}
