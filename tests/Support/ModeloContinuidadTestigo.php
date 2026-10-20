<?php

namespace Tests\Support;

use App\Services\ProtocoloTestigoDocumental;
use PDO;
use RuntimeException;

/** Modelo de diseño, NO servicio ni implementación del protocolo de producción.
 * Dos SQLite independientes simulan réplica y ancla bajo administraciones distintas.
 * No demuestra durabilidad real; permite caracterizar restauraciones y fallos.
 */
final class ModeloContinuidadTestigo
{
    private PDO $replica;
    private PDO $ancla;
    private string $secret;
    private string $pin;
    private ?string $anterior = null;
    private ?array $ultima = null;
    public bool $anclaDisponible = true;
    public bool $repetirAtestacion = false;

    public function __construct()
    {
        $this->aislado();
        foreach (['replica', 'ancla'] as $nombre) {
            $this->$nombre = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->$nombre->exec('CREATE TABLE consumos(id TEXT PRIMARY KEY, permiso TEXT NOT NULL, recibo TEXT NOT NULL UNIQUE)');
        }
        $this->secret = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
        $this->pin = sodium_crypto_sign_publickey_from_secretkey($this->secret);
    }

    public function consumir(string $id, string $permiso, bool $interrumpirTrasReserva = false): void
    {
        $this->continuidad();
        $q = $this->ancla->prepare('SELECT COUNT(*) FROM consumos WHERE id=?'); $q->execute([$id]);
        if ((int) $q->fetchColumn() !== 0) throw new RuntimeException('AUTORIZACION_CONSUMIDA');
        $fila = [$id, $permiso, hash('sha256', random_bytes(32))];
        // La reserva independiente gasta el ID ANTES de escribir la réplica.
        $this->insertar($this->ancla, $fila);
        if ($interrumpirTrasReserva) throw new RuntimeException('CONSUMO_REMOTO_INCIERTO');
        $this->insertar($this->replica, $fila);
        $this->continuidad();
    }

    public function consultar(string $id): string
    {
        $this->continuidad();
        $q = $this->replica->prepare('SELECT COUNT(*) FROM consumos WHERE id=?'); $q->execute([$id]);
        return (int) $q->fetchColumn() === 0 ? 'libre' : 'consumida';
    }

    public function continuidad(): void
    {
        $this->aislado();
        if (!$this->anclaDisponible) throw new RuntimeException('CONTINUIDAD_NO_VERIFICABLE');
        $nonce = bin2hex(random_bytes(32));
        $data = ['nonce' => $nonce, 'cantidad' => count($this->filas($this->ancla)), 'raiz' => $this->raiz($this->ancla)];
        $bytes = ProtocoloTestigoDocumental::canonico($data);
        $sobre = $this->repetirAtestacion && $this->ultima ? $this->ultima : [
            'payload' => $bytes, 'firma' => sodium_crypto_sign_detached("ITM/ancla-diseno/v1/head\0".$bytes, $this->secret)];
        $this->ultima = $sobre;
        if (!sodium_crypto_sign_verify_detached($sobre['firma'], "ITM/ancla-diseno/v1/head\0".$sobre['payload'], $this->pin)) {
            throw new RuntimeException('IDENTIDAD_ANCLA_NO_VERIFICADA');
        }
        $leida = json_decode($sobre['payload'], true, 8, JSON_THROW_ON_ERROR);
        if ($leida['nonce'] !== $nonce) throw new RuntimeException('ATESTACION_NO_FRESCA');
        if ($leida['raiz'] !== $this->raiz($this->replica) || $leida['cantidad'] !== count($this->filas($this->replica))) {
            throw new RuntimeException('CONTINUIDAD_PERDIDA');
        }
    }

    public function copiaReplica(): array { $this->aislado(); return $this->filas($this->replica); }
    public function restaurarReplica(array $filas): void { $this->restaurar($this->replica, $filas); }
    public function borrarReciboReplica(string $id): void
    {
        $this->aislado(); $q = $this->replica->prepare('UPDATE consumos SET recibo=? WHERE id=?'); $q->execute(['ausente', $id]);
    }

    /** Contraejemplo de pérdida de TODAS las fronteras; nunca API operativa. */
    public function restaurarTambienAnclaParaCaracterizacion(array $filas): void { $this->restaurar($this->ancla, $filas); }

    public function rotarClaveAncla(): string
    {
        $this->aislado(); $this->anterior = $this->secret;
        $this->secret = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
        return hash('sha256', sodium_crypto_sign_publickey_from_secretkey($this->secret));
    }

    public function aprobarPinDePrueba(string $huella): void
    {
        $this->aislado(); $key = sodium_crypto_sign_publickey_from_secretkey($this->secret);
        if (!hash_equals(hash('sha256', $key), $huella)) throw new RuntimeException('PIN_NO_APROBADO');
        $this->pin = $key;
    }

    public function restaurarClaveAnteriorParaCaracterizacion(): void
    {
        $this->aislado();
        if ($this->anterior === null) throw new RuntimeException('SIN_CLAVE_ANTERIOR');
        $this->secret = $this->anterior;
    }

    private function filas(PDO $db): array { return $db->query('SELECT id, permiso, recibo FROM consumos ORDER BY id')->fetchAll(PDO::FETCH_NUM); }
    private function raiz(PDO $db): string { return hash('sha256', json_encode($this->filas($db), JSON_THROW_ON_ERROR)); }
    private function insertar(PDO $db, array $fila): void { $q = $db->prepare('INSERT INTO consumos VALUES(?,?,?)'); $q->execute($fila); }
    private function restaurar(PDO $db, array $filas): void
    {
        $this->aislado(); $db->beginTransaction();
        try { $db->exec('DELETE FROM consumos'); foreach ($filas as $fila) $this->insertar($db, $fila); $db->commit(); }
        catch (\Throwable $e) { $db->rollBack(); throw $e; }
    }
    private function aislado(): void
    {
        if (!app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url')) {
            throw new RuntimeException('MODELO_FUERA_DE_PRUEBAS');
        }
    }
}
