<?php

namespace Tests\Support;

use App\Contracts\RegistroConsumosIndependiente;
use PDO;
use PDOException;
use RuntimeException;

/** Solo pruebas. Su PDO independiente sobrevive a recrear servicios, no a un proceso real. */
class RegistroConsumosFicticio implements RegistroConsumosIndependiente
{
    private PDO $estado;
    private string $secret;
    public string $publica;
    public string $registroId;
    public string $epoca;
    public bool $noDisponible = false;
    public bool $perderRespuestaDeConsumo = false;
    public bool $alterarFirma = false;
    public bool $repetirRespuesta = false;
    public ?array $ultimaRespuesta = null;
    public ?\Closure $despuesConsumir = null;

    public function __construct()
    {
        $this->exigirPrueba();
        $this->estado = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->estado->exec('CREATE TABLE usos (registro TEXT, epoca TEXT, id TEXT, solicitud TEXT, PRIMARY KEY(registro, epoca, id))');
        $keys = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($keys);
        $this->publica = base64_encode(sodium_crypto_sign_publickey($keys));
        $this->registroId = bin2hex(random_bytes(16));
        $this->epoca = bin2hex(random_bytes(16));
    }

    public function exigirDisponibleParaCanalReal(): void
    {
        throw new RuntimeException('REGISTRO_INDEPENDIENTE_REAL_PENDIENTE');
    }

    public function consultar(array $solicitud): array
    {
        $this->exigirPrueba();
        $this->exigirRegistro($solicitud);
        if ($this->noDisponible) throw new RuntimeException('TESTIGO_FICTICIO_CAIDO');
        if ($this->repetirRespuesta && $this->ultimaRespuesta) return $this->ultimaRespuesta;
        $q = $this->estado->prepare('SELECT solicitud FROM usos WHERE registro=? AND epoca=? AND id=?');
        $q->execute([$solicitud['registro_id'], $solicitud['epoca'], $solicitud['id']]);
        $stored = $q->fetchColumn();
        $data = $stored === false ? $solicitud : json_decode($stored, true, 16, JSON_THROW_ON_ERROR);
        $data['desafio'] = $solicitud['desafio'];
        return $this->firmar($data, $stored === false ? 'libre' : 'consumida');
    }

    public function consumir(array $solicitud): array
    {
        $this->exigirPrueba();
        $this->exigirRegistro($solicitud);
        if ($this->noDisponible) throw new RuntimeException('TESTIGO_FICTICIO_CAIDO');
        try {
            $q = $this->estado->prepare('INSERT INTO usos VALUES(?,?,?,?)');
            $q->execute([$solicitud['registro_id'], $solicitud['epoca'], $solicitud['id'], json_encode($solicitud, JSON_THROW_ON_ERROR)]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            throw new RuntimeException('AUTORIZACION_CONSUMIDA');
        }
        if ($this->perderRespuestaDeConsumo) throw new RuntimeException('RESPUESTA_PERDIDA_TRAS_CONSUMIR');
        ($this->despuesConsumir)?->__invoke();
        return $this->firmar($solicitud, 'consumida');
    }

    /** Solo para caracterizar una restauración incoherente del servidor ficticio. */
    public function restaurarVacio(): void
    {
        $this->exigirPrueba();
        $this->estado->exec('DELETE FROM usos');
    }

    private function firmar(array $data, string $estado): array
    {
        $payload = json_encode(['formato' => 1] + $data + ['estado' => $estado], JSON_THROW_ON_ERROR);
        $firma = sodium_crypto_sign_detached($payload, $this->secret);
        if ($this->alterarFirma) $firma[0] = chr(ord($firma[0]) ^ 1);
        return $this->ultimaRespuesta = ['payload' => base64_encode($payload), 'firma' => base64_encode($firma)];
    }

    private function exigirPrueba(): void
    {
        if (!app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('TESTIGO_FICTICIO_FUERA_DE_PRUEBAS');
        }
    }

    private function exigirRegistro(array $solicitud): void
    {
        if (($solicitud['registro_id'] ?? null) !== $this->registroId || ($solicitud['epoca'] ?? null) !== $this->epoca) {
            throw new RuntimeException('REGISTRO_FICTICIO_NO_PROVISIONADO');
        }
    }
}
