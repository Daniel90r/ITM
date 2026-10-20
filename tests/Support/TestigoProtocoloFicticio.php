<?php

namespace Tests\Support;

use App\Contracts\TransporteTestigoDocumental;
use App\Services\ProtocoloTestigoDocumental as P;
use Closure;
use PDO;
use RuntimeException;
use Throwable;

/** Estado remoto simulado independiente: no forma parte de Laravel ni de sus recibos. */
final class TestigoProtocoloFicticio implements TransporteTestigoDocumental
{
    private PDO $db;
    private string $secret;
    public string $publica;
    public string $registro;
    public string $epoca;
    public ?Closure $antes = null;
    public ?Closure $despues = null;
    public ?Closure $mensajeFirmado = null;
    public ?Closure $persistir = null;
    public array $viajes = [];

    public function __construct(private string $principal, private Closure $reloj)
    {
        $this->aislado();
        $this->db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->exec('CREATE TABLE retos(nonce TEXT PRIMARY KEY, mensaje TEXT NOT NULL, usado INTEGER NOT NULL DEFAULT 0)');
        $this->db->exec('CREATE TABLE usos(id TEXT PRIMARY KEY, vinculo TEXT NOT NULL, recibo TEXT NOT NULL UNIQUE)');
        $keys = sodium_crypto_sign_keypair(); $this->secret = sodium_crypto_sign_secretkey($keys);
        $this->publica = base64_encode(sodium_crypto_sign_publickey($keys));
        $this->registro = bin2hex(random_bytes(16)); $this->epoca = bin2hex(random_bytes(16));
    }

    public function intercambiar(string $op, array $m, int $plazoMilisegundos): array
    {
        $this->aislado(); $this->viajes[] = [$op, $plazoMilisegundos];
        if ($plazoMilisegundos < 1 || $plazoMilisegundos > 5000) throw new RuntimeException('PLAZO_INVALIDO');
        ($this->antes)?->__invoke($op, $m);
        $s = array_intersect_key($m, array_flip(P::BASE)); P::solicitud($s);
        if ($s['registro_id'] !== $this->registro || $s['epoca'] !== $this->epoca) throw new RuntimeException('TESTIGO_DESCONOCIDO');
        if ($s['ejecutor_sid'] !== $this->principal) throw new RuntimeException('EJECUTOR_NO_AUTENTICADO');
        if (($m['formato'] ?? null) !== 2 || !in_array($m['operacion'] ?? null, ['consultar', 'consumir'], true)) throw new RuntimeException('PROTOCOLO_INVALIDO');
        if ($op === 'desafiar') {
            P::exacto($m, [...P::BASE, 'formato', 'operacion']);
            $r = $m + ['tipo' => 'reto', 'nonce' => bin2hex(random_bytes(32)), 'desde' => ($this->reloj)(), 'hasta' => ($this->reloj)() + 30];
            $q = $this->db->prepare('INSERT INTO retos(nonce,mensaje) VALUES(?,?)'); $q->execute([$r['nonce'], P::canonico($r)]);
            $sobre = P::firmar($this->mensajeFirmado ? ($this->mensajeFirmado)($r) : $r, 'reto', $this->secret);
        } else {
            if ($op !== $m['operacion']) throw new RuntimeException('PROTOCOLO_INVALIDO');
            P::exacto($m, [...P::BASE, 'formato', 'operacion', 'tipo', 'nonce', 'desde', 'hasta']);
            $this->db->beginTransaction();
            try {
                $q = $this->db->prepare('UPDATE retos SET usado=1 WHERE nonce=? AND mensaje=? AND usado=0');
                $q->execute([$m['nonce'], P::canonico($m)]);
                if ($q->rowCount() !== 1 || ($this->reloj)() < $m['desde'] || ($this->reloj)() >= $m['hasta']) throw new RuntimeException('DESAFIO_REUTILIZADO_O_VENCIDO');
                $vinculo = $s; unset($vinculo['desafio']); $vinculo = P::canonico($vinculo);
                $q = $this->db->prepare('SELECT vinculo FROM usos WHERE id=?'); $q->execute([$s['id']]); $previo = $q->fetchColumn();
                if ($previo !== false && $previo !== $vinculo) throw new RuntimeException('AUTORIZACION_CON_VINCULO_DISTINTO');
                $nuevo = $op === 'consumir' && $previo === false;
                $r = array_replace($m, ['tipo' => 'recibo']) + ['estado' => $previo !== false || $nuevo ? 'consumida' : 'libre',
                    'resultado' => $op === 'consultar' ? 'consulta' : ($nuevo ? 'nuevo' : 'rechazado'),
                    'recibo_id' => bin2hex(random_bytes(32)), 'emitido_en' => ($this->reloj)()];
                $sobre = P::firmar($this->mensajeFirmado ? ($this->mensajeFirmado)($r) : $r, 'recibo', $this->secret);
                if ($nuevo) {
                    ($this->persistir)?->__invoke();
                    $q = $this->db->prepare('INSERT INTO usos VALUES(?,?,?)'); $q->execute([$s['id'], $vinculo, $r['recibo_id']]);
                }
                $this->db->commit();
            } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
        }
        // Fallo aquí simula respuesta perdida DESPUÉS del commit remoto.
        return $this->despues ? ($this->despues)($op, $sobre) : $sobre;
    }

    public function consumos(): int { return (int) $this->db->query('SELECT COUNT(*) FROM usos')->fetchColumn(); }

    private function aislado(): void
    {
        if (!app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url')) {
            throw new RuntimeException('TESTIGO_FICTICIO_FUERA_DE_PRUEBAS');
        }
    }
}
