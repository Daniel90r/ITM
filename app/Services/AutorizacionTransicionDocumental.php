<?php

namespace App\Services;

use Closure;
use RuntimeException;

/** Permiso firmado externo. Validar es de lectura; consumir es irreversible y explícito. */
class AutorizacionTransicionDocumental
{
    private ?array $validada = null;
    private ?string $registro = null;
    private bool $activa = false;
    private array $rutasProtegidas = [];
    private ?array $claveConfiable = null;
    private ?array $ticket = null;

    public function __construct(private DocumentosInscripcion $archivos, private ConsumosAutorizacionDocumental $consumos,
        private IdentidadEjecutorDocumental $identidad, private RutasAutorizacionDocumental $rutas) {}

    public function validar(string $ruta, string $sha, string $clave, array $contexto, array $solicitud, string $registro, string $claveSha): array
    {
        if ($this->activa) throw new RuntimeException('AUTORIZACION_EN_USO');
        $this->validada = null; $this->registro = null; $this->ticket = null; $this->claveConfiable = null;
        if (!app()->environment('testing')) {
            $this->consumos->exigirCanalReal();
            $this->rutas->verificar();
            $this->rutas->exigirPermiso($ruta);
        }
        $this->rutasProtegidas = [$ruta, $clave, $registro];
        $this->aislamientoDePrueba($this->rutasProtegidas);
        $json = $this->leer($ruta);
        $this->huella($json, $sha);
        if (!function_exists('sodium_crypto_sign_verify_detached')) throw new RuntimeException('FIRMA_NO_DISPONIBLE');
        $sobre = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($sobre) || !is_string($sobre['payload'] ?? null) || !is_string($sobre['firma'] ?? null)) {
            throw new RuntimeException('AUTORIZACION_INVALIDA');
        }
        $payload = base64_decode($sobre['payload'] ?? '', true);
        $firma = base64_decode($sobre['firma'] ?? '', true);
        $keyBytes = $this->leer($clave); $this->huella($keyBytes, $claveSha);
        $publica = base64_decode(trim($keyBytes), true);
        if ($payload === false || $firma === false || $publica === false
            || strlen($publica) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen($firma) !== SODIUM_CRYPTO_SIGN_BYTES
            || !sodium_crypto_sign_verify_detached($firma, $payload, $publica)) throw new RuntimeException('AUTORIZACION_INVALIDA');
        $p = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        if (($p['formato'] ?? null) !== 2 || ($p['accion'] ?? null) !== 'transicion-documental-inicial'
            || !preg_match('/\A[a-f0-9]{32}\z/D', $p['id'] ?? '')
            || ($p['entorno'] ?? null) !== $contexto || ($p['solicitud'] ?? null) !== $solicitud) {
            throw new RuntimeException('AUTORIZACION_NO_CORRESPONDE');
        }
        $this->entornoPermitido($contexto);
        $this->ventana($p);
        $this->verificarIdentidadYRegistro($contexto, $registro);
        foreach (['inventario', 'respaldo', 'auditoria'] as $tipo) {
            $archivo = $p[$tipo] ?? [];
            $this->aislamientoDePrueba([$archivo['ruta'] ?? '']);
            $this->rutasProtegidas[] = $archivo['ruta'];
            $this->huella($this->leer($archivo['ruta'] ?? '', $tipo === 'respaldo' ? 268435456 : 2097152), $archivo['sha256'] ?? '');
        }
        $this->auditoria($p, $contexto);
        if (($p['inventario']['ruta'] ?? null) !== ($solicitud['inventario'] ?? null)
            || ($p['inventario']['sha256'] ?? null) !== ($solicitud['sha256'] ?? null)
            || ($p['respaldo_verificado'] ?? null) !== true) throw new RuntimeException('RESPALDO_O_INVENTARIO_NO_AUTORIZADO');
        $this->archivos->verificarRutaAbsoluta($registro);
        if (!is_dir($registro) || !is_writable($registro)) throw new RuntimeException('REGISTRO_DE_USOS_NO_DISPONIBLE');
        $ticket = $this->consumos->ticket($registro, $p['id'], $sha,
            hash('sha256', json_encode(['entorno' => $contexto, 'solicitud' => $solicitud, 'clave_sha256' => $claveSha], JSON_THROW_ON_ERROR)),
            $solicitud['lote'], $contexto['ejecutor_sid']);
        $this->consumos->exigirLibre($ticket);
        $this->validada = $p; $this->registro = $registro; $this->ticket = $ticket;
        $this->claveConfiable = ['ruta' => $clave, 'sha256' => $claveSha];
        return $p;
    }

    public function exigirValidada(): void
    {
        if ($this->validada === null) throw new RuntimeException('AUTORIZACION_REAL_REQUERIDA');
        if (!app()->environment('testing')) $this->consumos->exigirCanalReal();
        $this->ventana($this->validada);
        $this->verificarIdentidadYRegistro($this->validada['entorno'], $this->registro);
    }

    /** El llamador ya mantiene la exclusión de la transición. Un fallo también gasta el permiso. */
    public function usar(Closure $accion): mixed
    {
        $this->exigirValidada();
        if ($this->activa) throw new RuntimeException('AUTORIZACION_EN_USO');
        $this->aislamientoDePrueba($this->rutasProtegidas);
        $this->huella($this->leer($this->claveConfiable['ruta']), $this->claveConfiable['sha256']);
        foreach (['inventario', 'respaldo', 'auditoria'] as $tipo) {
            $a = $this->validada[$tipo];
            $this->huella($this->leer($a['ruta'], $tipo === 'respaldo' ? 268435456 : 2097152), $a['sha256']);
        }
        $this->auditoria($this->validada, $this->validada['entorno']);
        try {
            $this->consumos->consumir($this->ticket);
            // El viaje al testigo puede cruzar el final de la ventana.
            $this->exigirValidada();
            $this->activa = true;
            $resultado = $accion();
            $this->exigirActiva();
            return $resultado;
        } finally { $this->activa = false; $this->validada = null; $this->ticket = null; }
    }

    public function exigirActiva(): void
    {
        $this->exigirValidada();
        if (!$this->activa) throw new RuntimeException('AUTORIZACION_NO_CONSUMIDA');
        $this->consumos->exigirConsumida($this->ticket);
    }

    private function verificarIdentidadYRegistro(array $contexto, string $registro): void
    {
        $this->identidad->exigir((string) ($contexto['ejecutor_sid'] ?? ''));
        $normal = fn ($p) => PHP_OS_FAMILY === 'Windows' ? strtolower(str_replace('\\', '/', rtrim($p, '/\\'))) : rtrim($p, '/');
        if (($contexto['registro_consumos_raiz'] ?? null) !== $normal($registro)
            || ($contexto['registro_consumos_sha256'] ?? null) !== hash('sha256', json_encode(config('documentos_inscripcion.consumption_registry'), JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('REGISTRO_FUERA_DE_CONTEXTO_FIRMADO');
        }
        if (!app()->environment('testing') && ($contexto['rutas_autorizacion_sha256'] ?? null)
            !== hash('sha256', json_encode($this->rutas->verificar(), JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('RUTAS_REALES_NO_COINCIDEN');
        }
    }

    private function entornoPermitido(array $c): void
    {
        if (($c['driver'] ?? null) !== 'mysql' || ($c['host'] ?? null) !== '127.0.0.1'
            || ($c['port'] ?? null) !== 3306 || ($c['database'] ?? null) !== 'itm'
            || ($c['version'] ?? null) !== '8.4.7' || !preg_match('/\A[0-9a-f-]{36}\z/D', $c['server_uuid'] ?? '')) {
            throw new RuntimeException('ENTORNO_REAL_NO_PERMITIDO');
        }
    }

    /** Atestación externa firmada + comprobación automática de archivos de configuración revisados. */
    private function auditoria(array $p, array $contexto): void
    {
        $a = json_decode($this->leer($p['auditoria']['ruta']), true, 32, JSON_THROW_ON_ERROR);
        if (($a['formato'] ?? null) !== 1 || ($a['procesos_revisados'] ?? null) !== true
            || ($a['tareas_revisadas'] ?? null) !== true || ($a['escritores_externos_descartados'] ?? null) !== true
            || !is_int($a['revisada_en'] ?? null) || $a['revisada_en'] > now()->timestamp
            || $a['revisada_en'] < $p['ventana']['desde'] || now()->timestamp - $a['revisada_en'] > 300
            || empty($a['publicaciones_http']) || empty($a['configuracion_http'])) throw new RuntimeException('AUDITORIA_NO_VIGENTE');
        foreach ($a['configuracion_http'] as $c) {
            $this->aislamientoDePrueba([$c['ruta']]);
            // Estos archivos pueden estar dentro de Wamp; se leen, nunca se ejecutan.
            $this->archivos->verificarRutaAbsoluta($c['ruta']);
            if (!is_file($c['ruta']) || !hash_equals($c['sha256'], hash_file('sha256', $c['ruta']))) {
                throw new RuntimeException('CONFIGURACION_HTTP_MODIFICADA');
            }
        }
        foreach ($a['publicaciones_http'] as $web) {
            $this->archivos->verificarRutaAbsoluta($web);
            foreach (array_merge([$contexto['privado'], $contexto['cuarentena'], $contexto['barrera'], dirname($p['auditoria']['ruta']),
                dirname($p['respaldo']['ruta']), dirname($p['inventario']['ruta'])], $this->rutasProtegidas) as $destino) {
                $this->archivos->verificarRutaAbsoluta($destino);
                if (app(ControlTransicionDocumental::class)->dentro($destino, $web)) throw new RuntimeException('DESTINO_PRIVADO_PUBLICABLE');
            }
        }
        if (($a['restauracion_verificada'] ?? null) !== true || ($a['respaldo_sha256'] ?? null) !== $p['respaldo']['sha256']
            || ($a['datos_originales_sha256'] ?? null) !== $contexto['datos_originales_sha256']) {
            throw new RuntimeException('RESPALDO_NO_CORRESPONDE_AL_ESTADO');
        }
        $sql = $this->leer($p['respaldo']['ruta'], 268435456);
        if (!str_contains($sql, '-- Dump completed on') || !str_contains($sql, 'USE `itm`;')) {
            throw new RuntimeException('RESPALDO_INCOMPLETO');
        }
    }

    private function ventana(array $p): void
    {
        $v = $p['ventana'] ?? [];
        if (!is_int($v['desde'] ?? null) || !is_int($v['hasta'] ?? null)
            || $v['hasta'] <= $v['desde'] || $v['hasta'] - $v['desde'] > 1800
            || now()->timestamp < $v['desde'] || now()->timestamp >= $v['hasta']
            || ($v['confirmada'] ?? null) !== true || ($v['escritores_externos_revisados'] ?? null) !== true) {
            throw new RuntimeException('VENTANA_NO_VERIFICADA');
        }
    }

    private function leer(string $ruta, int $max = 2097152): string
    {
        $this->archivos->verificarRutaAbsoluta($ruta);
        $control = app(ControlTransicionDocumental::class);
        if ($control->dentro($ruta, base_path()) || $control->dentro($ruta, public_path())
            || !is_file($ruta) || !is_readable($ruta) || filesize($ruta) < 1 || filesize($ruta) > $max) {
            throw new RuntimeException('ARCHIVO_DE_AUTORIZACION_NO_SEGURO');
        }
        $bytes = @file_get_contents($ruta);
        if ($bytes === false) throw new RuntimeException('ARCHIVO_DE_AUTORIZACION_NO_SEGURO');
        return $bytes;
    }

    private function aislamientoDePrueba(array $rutas): void
    {
        if (!app()->environment('testing')) return;
        $control = app(ControlTransicionDocumental::class);
        $control->exigirAislamiento();
        foreach ($rutas as $ruta) $control->exigirTemporal($ruta);
    }

    private function huella(string $bytes, string $sha): void
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $sha) || !hash_equals($sha, hash('sha256', $bytes))) {
            throw new RuntimeException('HUELLA_AUTORIZADA_NO_COINCIDE');
        }
    }
}
