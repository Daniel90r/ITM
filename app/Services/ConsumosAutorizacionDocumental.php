<?php

namespace App\Services;

use App\Contracts\RegistroConsumosIndependiente;
use RuntimeException;
use Throwable;

/** Espejo local verificable + testigo independiente obligatorio; nunca TOFU. */
class ConsumosAutorizacionDocumental
{
    public function __construct(private RegistroConsumosIndependiente $testigo, private DocumentosInscripcion $archivos) {}

    public function exigirCanalReal(): void
    {
        $this->testigo->exigirDisponibleParaCanalReal();
    }

    public function ticket(string $directorio, string $id, string $permisoSha, string $vinculo, string $lote, string $ejecutor): array
    {
        $this->archivos->verificarRutaAbsoluta($directorio);
        $config = config('documentos_inscripcion.consumption_registry');
        if (!is_array($config) || !preg_match('/\A[a-f0-9]{32}\z/D', $config['id'] ?? '')
            || !preg_match('/\A[a-f0-9]{32}\z/D', $config['epoch'] ?? '')
            || !preg_match('/\A[a-f0-9]{32}\z/D', $id)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $permisoSha) || !preg_match('/\A[a-f0-9]{64}\z/D', $vinculo)) {
            throw new RuntimeException('REGISTRO_CONFIABLE_NO_CONFIGURADO');
        }
        $publica = base64_decode($config['public_key'] ?? '', true);
        if ($publica === false || strlen($publica) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('CLAVE_TESTIGO_NO_VERIFICADA');
        }
        $ticket = ['registro_id' => $config['id'], 'epoca' => $config['epoch'], 'id' => $id,
            'permiso_sha256' => $permisoSha, 'vinculo_sha256' => $vinculo,
            'directorio' => rtrim($directorio, '/\\'), 'public_key' => $config['public_key'], 'lote' => $lote, 'ejecutor_sid' => $ejecutor];
        $this->continuidad($ticket);
        return $ticket;
    }

    /** Lectura, también después de reiniciar la aplicación. */
    public function exigirLibre(array $ticket): void
    {
        $this->continuidad($ticket);
        $estado = $this->remoto($ticket, false);
        $archivo = $this->archivo($ticket);
        if (file_exists($archivo)) {
            $this->local($ticket);
            if ($estado['estado'] !== 'consumida') throw new RuntimeException('REGISTROS_DE_CONSUMO_INCONSISTENTES');
            throw new RuntimeException('AUTORIZACION_CONSUMIDA');
        }
        if ($estado['estado'] !== 'libre') throw new RuntimeException('AUTORIZACION_CONSUMIDA_SIN_ESPEJO');
    }

    public function consumir(array $ticket): void
    {
        $this->exigirLibre($ticket);
        // La decisión irreversible ocurre fuera del host ANTES de escribir el espejo.
        // Una respuesta perdida no se convierte en permiso para reintentar el ID.
        $sobre = $this->remoto($ticket, true);
        $archivo = $this->archivo($ticket);
        $this->archivos->verificarRutaAbsoluta($archivo);
        $f = @fopen($archivo, 'xb');
        if (!$f) throw new RuntimeException('CONSUMO_LOCAL_NO_VERIFICADO');
        try {
            $bytes = json_encode($sobre['sobre'], JSON_THROW_ON_ERROR);
            if (fwrite($f, $bytes) !== strlen($bytes) || !fflush($f) || !function_exists('fsync') || !fsync($f)) {
                throw new RuntimeException('CONSUMO_LOCAL_NO_VERIFICADO');
            }
        } finally { fclose($f); }
        $this->exigirConsumida($ticket);
    }

    public function exigirConsumida(array $ticket): void
    {
        $this->continuidad($ticket);
        $this->local($ticket);
        if ($this->remoto($ticket, false)['estado'] !== 'consumida') throw new RuntimeException('REGISTROS_DE_CONSUMO_INCONSISTENTES');
    }

    private function continuidad(array $ticket): void
    {
        $c = config('documentos_inscripcion.consumption_registry');
        if (($c['id'] ?? null) !== $ticket['registro_id'] || ($c['epoch'] ?? null) !== $ticket['epoca']
            || ($c['public_key'] ?? null) !== $ticket['public_key']) throw new RuntimeException('CONTINUIDAD_DE_REGISTRO_PERDIDA');
        $path = $ticket['directorio'].'/registro.json';
        $data = $this->json($path);
        if ($data !== ['formato' => 1, 'registro_id' => $ticket['registro_id'], 'epoca' => $ticket['epoca'],
            'clave_sha256' => hash('sha256', base64_decode($ticket['public_key'], true))]) {
            throw new RuntimeException('CONTINUIDAD_DE_REGISTRO_PERDIDA');
        }
    }

    private function remoto(array $ticket, bool $consumir): array
    {
        $solicitud = array_intersect_key($ticket, array_flip(['registro_id', 'epoca', 'id', 'permiso_sha256', 'vinculo_sha256', 'lote', 'ejecutor_sid']));
        $solicitud['desafio'] = bin2hex(random_bytes(32));
        try { $sobre = $consumir ? $this->testigo->consumir($solicitud) : $this->testigo->consultar($solicitud); }
        catch (Throwable $e) {
            if ($e instanceof RuntimeException && in_array($e->getMessage(), ['AUTORIZACION_CONSUMIDA', 'CONSUMO_REMOTO_INCIERTO', 'TESTIGO_TIEMPO_AGOTADO'], true)) throw $e;
            throw new RuntimeException('TESTIGO_NO_VERIFICABLE');
        }
        $data = $this->verificarSobre($sobre, $ticket);
        foreach ($solicitud as $key => $value) if (($data[$key] ?? null) !== $value) throw new RuntimeException('RESPUESTA_TESTIGO_NO_CORRESPONDE');
        if (!in_array($data['estado'] ?? null, ['libre', 'consumida'], true) || $consumir && $data['estado'] !== 'consumida') {
            throw new RuntimeException('ESTADO_DE_CONSUMO_AMBIGUO');
        }
        return $data + ['sobre' => $sobre];
    }

    private function local(array $ticket): void
    {
        $data = $this->verificarSobre($this->json($this->archivo($ticket)), $ticket);
        foreach (['registro_id', 'epoca', 'id', 'permiso_sha256', 'vinculo_sha256', 'lote', 'ejecutor_sid'] as $key) {
            if (($data[$key] ?? null) !== $ticket[$key]) throw new RuntimeException('REGISTRO_LOCAL_SUSTITUIDO');
        }
        if (($data['estado'] ?? null) !== 'consumida') throw new RuntimeException('REGISTRO_LOCAL_SUSTITUIDO');
        if ($this->testigo instanceof AdaptadorTestigoDocumental
            && (($data['operacion'] ?? null) !== 'consumir' || ($data['resultado'] ?? null) !== 'nuevo')) {
            throw new RuntimeException('REGISTRO_LOCAL_SUSTITUIDO');
        }
    }

    private function verificarSobre(array $sobre, array $ticket): array
    {
        if ($this->testigo instanceof AdaptadorTestigoDocumental) {
            $data = ProtocoloTestigoDocumental::verificar($sobre, 'recibo', $ticket['public_key']);
            ProtocoloTestigoDocumental::recibo($data);
            return $data;
        }
        // Compatibilidad exclusiva con la caracterización ficticia de fase 1.6.
        // Un adaptador externo nunca puede negociar una degradación a formato 1.
        if (!app()->environment('testing')) throw new RuntimeException('PROTOCOLO_INVALIDO');
        $payload = is_string($sobre['payload'] ?? null) ? base64_decode($sobre['payload'], true) : false;
        $firma = is_string($sobre['firma'] ?? null) ? base64_decode($sobre['firma'], true) : false;
        if ($payload === false || $firma === false || strlen($payload) > 16384 || strlen($firma) !== SODIUM_CRYPTO_SIGN_BYTES
            || !sodium_crypto_sign_verify_detached($firma, $payload, base64_decode($ticket['public_key'], true))) {
            throw new RuntimeException('FIRMA_DE_CONSUMO_INVALIDA');
        }
        try { $data = json_decode($payload, true, 16, JSON_THROW_ON_ERROR); }
        catch (Throwable) { throw new RuntimeException('ESTADO_DE_CONSUMO_AMBIGUO'); }
        if (!is_array($data) || ($data['formato'] ?? null) !== 1) throw new RuntimeException('ESTADO_DE_CONSUMO_AMBIGUO');
        return $data;
    }

    private function json(string $path): array
    {
        $this->archivos->verificarRutaAbsoluta($path);
        clearstatcache(true, $path);
        if (!is_file($path) || !is_readable($path) || filesize($path) < 1 || filesize($path) > 32768) {
            throw new RuntimeException('ESTADO_DE_CONSUMO_NO_VERIFICABLE');
        }
        try { $data = json_decode(file_get_contents($path), true, 16, JSON_THROW_ON_ERROR); }
        catch (Throwable) { throw new RuntimeException('ESTADO_DE_CONSUMO_NO_VERIFICABLE'); }
        if (!is_array($data)) throw new RuntimeException('ESTADO_DE_CONSUMO_NO_VERIFICABLE');
        return $data;
    }

    private function archivo(array $ticket): string { return $ticket['directorio'].'/'.$ticket['id'].'.consumida'; }
}
