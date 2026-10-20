<?php

namespace Tests\Feature;

use App\Services\{AdaptadorTestigoDocumental, ConsumosAutorizacionDocumental, DocumentosInscripcion, ProtocoloTestigoDocumental as P};
use Fiber;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\TestigoProtocoloFicticio;
use Tests\TestCase;

class TestigoExternoDocumentalTest extends TestCase
{
    private TestigoProtocoloFicticio $remoto;
    private int $ahora = 100000;
    private string $sid = 'S-1-5-21-111-222-333-1001';
    private array $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->remoto = new TestigoProtocoloFicticio($this->sid, fn () => $this->ahora);
        $this->s = ['registro_id' => $this->remoto->registro, 'epoca' => $this->remoto->epoca,
            'id' => bin2hex(random_bytes(16)), 'permiso_sha256' => hash('sha256', 'permiso-ficticio'),
            'vinculo_sha256' => hash('sha256', 'entorno-ficticio'), 'lote' => 'lote-ficticio',
            'ejecutor_sid' => $this->sid, 'desafio' => bin2hex(random_bytes(32))];
    }

    private function cliente(): AdaptadorTestigoDocumental
    {
        return new AdaptadorTestigoDocumental($this->remoto, $this->remoto->registro, $this->remoto->epoca,
            $this->remoto->publica, fn () => $this->ahora);
    }

    private function fresca(): array { return array_replace($this->s, ['desafio' => bin2hex(random_bytes(32))]); }

    private function falla(string $codigo, callable $accion): void
    {
        try { $accion(); $this->fail('Debía fallar cerrado.'); }
        catch (RuntimeException $e) { $this->assertSame($codigo, $e->getMessage()); }
    }

    public function test_canonico_orden_y_separacion_de_dominios(): void
    {
        $this->assertSame('{"a":1,"z":"ascii/valor"}', P::canonico(['z' => 'ascii/valor', 'a' => 1]));
        $keys = sodium_crypto_sign_keypair();
        $sobre = P::firmar(['formato' => 2, 'tipo' => 'reto'], 'reto', sodium_crypto_sign_secretkey($keys));
        $this->falla('FIRMA_DE_CONSUMO_INVALIDA', fn () => P::verificar($sobre, 'recibo', base64_encode(sodium_crypto_sign_publickey($keys))));
    }

    public static function ambiguos(): array { return [[[1]], [['a' => 1.0]], [['a' => true]], [['a' => ['x']]], [['a' => 'ñ']], [['a' => -1]]]; }
    #[DataProvider('ambiguos')]
    public function test_canonico_rechaza_tipos_ambiguos(array $m): void { $this->falla('PROTOCOLO_INVALIDO', fn () => P::canonico($m)); }

    public function test_json_no_canonico_aunque_tenga_firma_correcta_se_rechaza(): void
    {
        $keys = sodium_crypto_sign_keypair(); $publica = base64_encode(sodium_crypto_sign_publickey($keys));
        foreach (['{"tipo":"reto","formato":2}', '{"formato":2, "tipo":"reto"}', '{"formato":2,"tipo":"otro","tipo":"reto"}'] as $bytes) {
            $sobre = ['payload' => base64_encode($bytes), 'firma' => base64_encode(sodium_crypto_sign_detached("ITM/testigo/v2/reto\0".$bytes, sodium_crypto_sign_secretkey($keys)))];
            $this->falla('PROTOCOLO_INVALIDO', fn () => P::verificar($sobre, 'reto', $publica));
        }
        $sobre = P::firmar(['formato' => 2, 'tipo' => 'reto'], 'reto', sodium_crypto_sign_secretkey($keys));
        $sobre['payload'] .= "\n";
        $this->falla('FIRMA_DE_CONSUMO_INVALIDA', fn () => P::verificar($sobre, 'reto', $publica));
    }

    public function test_consulta_fresca_consumo_y_rechazo_firmado_del_segundo(): void
    {
        $a = P::verificar($this->cliente()->consultar($this->s), 'recibo', $this->remoto->publica);
        $b = P::verificar($this->cliente()->consultar($this->fresca()), 'recibo', $this->remoto->publica);
        $this->assertNotSame($a['nonce'], $b['nonce']); $this->assertNotSame($a['recibo_id'], $b['recibo_id']);
        $r = P::verificar($this->cliente()->consumir($this->fresca()), 'recibo', $this->remoto->publica);
        $this->assertSame('nuevo', $r['resultado']);
        $this->falla('AUTORIZACION_CONSUMIDA', fn () => $this->cliente()->consumir($this->fresca()));
        $this->assertSame(1, $this->remoto->consumos());
    }

    public function test_dos_solicitudes_solapadas_un_ganador_y_perdedor_verificable(): void
    {
        $recibos = [];
        $this->remoto->antes = function ($op) { if ($op === 'consumir') Fiber::suspend(); };
        $this->remoto->despues = function ($op, $sobre) use (&$recibos) { if ($op === 'consumir') $recibos[] = P::verificar($sobre, 'recibo', $this->remoto->publica); return $sobre; };
        $a = new Fiber(fn () => $this->cliente()->consumir($this->fresca()));
        $b = new Fiber(fn () => $this->cliente()->consumir($this->fresca()));
        $a->start(); $b->start(); // Ambos tienen desafío vigente antes de decidir el CAS.
        $this->assertTrue($a->isSuspended() && $b->isSuspended());
        $b->resume(); $this->falla('AUTORIZACION_CONSUMIDA', fn () => $a->resume());
        $this->assertSame(['nuevo', 'rechazado'], array_column($recibos, 'resultado'));
        $this->assertSame(1, $this->remoto->consumos());
    }

    public function test_respuesta_perdida_no_reintenta_y_reinicio_consulta_consumida(): void
    {
        $this->remoto->despues = function ($op, $sobre) { if ($op === 'consumir') throw new RuntimeException('detalle secreto ficticio'); return $sobre; };
        $this->falla('CONSUMO_REMOTO_INCIERTO', fn () => $this->cliente()->consumir($this->s));
        $this->assertSame(['desafiar', 'consumir'], array_column($this->remoto->viajes, 0));
        $this->remoto->despues = null;
        $r = P::verificar($this->cliente()->consultar($this->fresca()), 'recibo', $this->remoto->publica);
        $this->assertSame('consumida', $r['estado']); $this->assertSame('consulta', $r['resultado']);
        $this->falla('AUTORIZACION_CONSUMIDA', fn () => $this->cliente()->consumir($this->fresca()));
    }

    public function test_timeout_y_desconexion_antes_de_consumo_no_otorgan_uso(): void
    {
        $this->remoto->antes = fn () => throw new \App\Exceptions\TestigoTiempoAgotado('ficticio');
        $this->falla('TESTIGO_TIEMPO_AGOTADO', fn () => $this->cliente()->consumir($this->s));
        $this->remoto->antes = fn () => throw new RuntimeException('sin conexion');
        $this->falla('TESTIGO_COMUNICACION_FALLIDA', fn () => $this->cliente()->consultar($this->s));
        $this->assertSame(0, $this->remoto->consumos());
        foreach ($this->remoto->viajes as $v) $this->assertTrue($v[1] > 0 && $v[1] <= 5000);
    }

    public function test_timeout_despues_del_commit_es_incierto(): void
    {
        $this->remoto->despues = function ($op, $s) { if ($op === 'consumir') throw new \App\Exceptions\TestigoTiempoAgotado(); return $s; };
        $this->falla('CONSUMO_REMOTO_INCIERTO', fn () => $this->cliente()->consumir($this->s));
        $this->assertSame(1, $this->remoto->consumos());
    }

    public function test_presupuesto_total_monotonico_no_se_reinicia_entre_viajes(): void
    {
        $ticks = 0;
        $c = new AdaptadorTestigoDocumental($this->remoto, $this->remoto->registro, $this->remoto->epoca,
            $this->remoto->publica, fn () => $this->ahora, function () use (&$ticks) { return $ticks; });
        $this->remoto->despues = function ($op, $r) use (&$ticks) { $ticks += $op === 'desafiar' ? 3000000000 : 2000000000; return $r; };
        $this->falla('CONSUMO_REMOTO_INCIERTO', fn () => $c->consumir($this->s));
        $this->assertSame([5000, 2000], array_column($this->remoto->viajes, 1));
        $this->assertSame(1, $this->remoto->consumos());
    }

    public function test_desafio_reutilizado_y_vencido_rechazados(): void
    {
        $r = P::verificar($this->remoto->intercambiar('desafiar', $this->s + ['formato' => 2, 'operacion' => 'consumir'], 5000), 'reto', $this->remoto->publica);
        $this->remoto->intercambiar('consumir', $r, 5000);
        $this->falla('DESAFIO_REUTILIZADO_O_VENCIDO', fn () => $this->remoto->intercambiar('consumir', $r, 5000));
        $this->remoto->despues = function ($op, $s) { if ($op === 'desafiar') $this->ahora += 30; return $s; };
        $this->falla('DESAFIO_NO_VIGENTE', fn () => $this->cliente()->consumir($this->fresca()));
    }

    public function test_firma_alterada_clave_desconocida_y_respuesta_duplicada(): void
    {
        $vieja = $this->cliente()->consultar($this->s);
        $this->remoto->despues = fn ($op, $s) => $op === 'consultar' ? $vieja : $s;
        $this->falla('RESPUESTA_TESTIGO_NO_CORRESPONDE', fn () => $this->cliente()->consultar($this->fresca()));
        $this->remoto->despues = function ($op, $s) { $s['firma'] = base64_encode(str_repeat('x', 64)); return $s; };
        $this->falla('FIRMA_DE_CONSUMO_INVALIDA', fn () => $this->cliente()->consultar($this->fresca()));
        $this->remoto->despues = null;
        $c = new AdaptadorTestigoDocumental($this->remoto, $this->remoto->registro, $this->remoto->epoca,
            base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair())), fn () => $this->ahora);
        $this->falla('FIRMA_DE_CONSUMO_INVALIDA', fn () => $c->consultar($this->s));
    }

    public static function cambios(): array { return [['lote', 'otro'], ['ejecutor_sid', 'S-1-5-21-111-222-333-1002'], ['permiso_sha256', null], ['id', null]]; }
    #[DataProvider('cambios')]
    public function test_respuesta_de_otro_vinculo_se_rechaza(string $campo, ?string $valor): void
    {
        $vieja = $this->cliente()->consultar($this->s);
        $this->remoto->despues = fn ($op, $s) => $vieja; // Firmada válidamente, pero corresponde a otro mensaje/tipo.
        $m = $this->fresca(); $m[$campo] = $valor ?? str_repeat('a', $campo === 'id' ? 32 : 64);
        $this->expectException(RuntimeException::class); $this->cliente()->consultar($m);
    }

    public function test_cambio_de_lote_o_ejecutor_no_elude_consumo_por_id(): void
    {
        $this->cliente()->consumir($this->s);
        $m = $this->fresca(); $m['lote'] = 'otro';
        $this->falla('TESTIGO_COMUNICACION_FALLIDA', fn () => $this->cliente()->consultar($m));
        $m = $this->fresca(); $m['ejecutor_sid'] = 'S-1-5-21-111-222-333-1002';
        $this->falla('TESTIGO_COMUNICACION_FALLIDA', fn () => $this->cliente()->consumir($m));
        $this->assertSame(1, $this->remoto->consumos());
    }

    public function test_campos_extra_version_incompatible_y_testigo_desconocido(): void
    {
        $this->falla('PROTOCOLO_INVALIDO', fn () => $this->cliente()->consultar($this->s + ['extra' => 'x']));
        $m = $this->s; $m['registro_id'] = str_repeat('0', 32);
        $this->falla('TESTIGO_DESCONOCIDO', fn () => $this->cliente()->consultar($m));
        $this->falla('PROTOCOLO_INVALIDO', fn () => $this->remoto->intercambiar('desafiar', $this->s + ['formato' => 3, 'operacion' => 'consultar'], 5000));
        $this->falla('REGISTRO_INDEPENDIENTE_REAL_PENDIENTE', fn () => $this->cliente()->exigirDisponibleParaCanalReal());
    }

    public static function recibosInvalidos(): array
    {
        return [['id', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'RESPUESTA_TESTIGO_NO_CORRESPONDE'],
            ['formato', 3, 'PROTOCOLO_INVALIDO'], ['estado', 'desconocido', 'ESTADO_DE_CONSUMO_AMBIGUO'],
            ['recibo_id', null, 'PROTOCOLO_INVALIDO'], ['extra', 'x', 'PROTOCOLO_INVALIDO'],
            ['resultado', 'nuevo', 'ESTADO_DE_CONSUMO_AMBIGUO']];
    }

    #[DataProvider('recibosInvalidos')]
    public function test_recibos_firmados_incompletos_incompatibles_o_ambiguos(string $campo, mixed $valor, string $error): void
    {
        $this->remoto->mensajeFirmado = function ($r) use ($campo, $valor) {
            if ($r['tipo'] === 'recibo') { if ($valor === null) unset($r[$campo]); else $r[$campo] = $valor; }
            return $r;
        };
        $this->falla($error, fn () => $this->cliente()->consultar($this->s));
    }

    public function test_error_de_persistencia_no_produce_recibo_de_exito(): void
    {
        $this->remoto->persistir = fn () => throw new RuntimeException('fallo de persistencia ficticio');
        $this->falla('CONSUMO_REMOTO_INCIERTO', fn () => $this->cliente()->consumir($this->s));
        $this->assertSame(0, $this->remoto->consumos());
        $this->remoto->persistir = null;
        $r = P::verificar($this->cliente()->consultar($this->fresca()), 'recibo', $this->remoto->publica);
        $this->assertSame('libre', $r['estado']);
        // Consultar aclara el estado; no ejecuta una acción ni reintenta el consumo.
        $this->assertSame(1, count(array_filter($this->remoto->viajes, fn ($v) => $v[0] === 'consumir')));
    }

    public function test_espejo_eliminado_restaurado_y_sustituido_no_rehabilita_permiso(): void
    {
        $dir = sys_get_temp_dir().'/itm-testigo-protocolo-'.bin2hex(random_bytes(12)); mkdir($dir);
        $registro = ['id' => $this->remoto->registro, 'epoch' => $this->remoto->epoca, 'public_key' => $this->remoto->publica];
        config(['documentos_inscripcion.consumption_registry' => $registro]);
        $meta = json_encode(['formato' => 1, 'registro_id' => $registro['id'], 'epoca' => $registro['epoch'], 'clave_sha256' => hash('sha256', base64_decode($registro['public_key']))]);
        file_put_contents($dir.'/registro.json', $meta);
        $c = new ConsumosAutorizacionDocumental($this->cliente(), app(DocumentosInscripcion::class));
        $ticket = $c->ticket($dir, $this->s['id'], $this->s['permiso_sha256'], $this->s['vinculo_sha256'], $this->s['lote'], $this->sid);
        $uso = $dir.'/'.$this->s['id'].'.consumida';
        try {
            $c->consumir($ticket); $recibo = file_get_contents($uso); unlink($uso);
            $c = new ConsumosAutorizacionDocumental($this->cliente(), app(DocumentosInscripcion::class));
            $this->falla('AUTORIZACION_CONSUMIDA_SIN_ESPEJO', fn () => $c->exigirLibre($ticket));
            file_put_contents($dir.'/registro.json', $meta);
            $this->falla('AUTORIZACION_CONSUMIDA_SIN_ESPEJO', fn () => $c->consumir($ticket));
            file_put_contents($uso, '{}'); $this->falla('PROTOCOLO_INVALIDO', fn () => $c->exigirLibre($ticket));
            file_put_contents($uso, $recibo); $this->falla('AUTORIZACION_CONSUMIDA', fn () => $c->exigirLibre($ticket));
            $this->assertSame(1, $this->remoto->consumos());
        } finally { if (is_file($uso)) unlink($uso); unlink($dir.'/registro.json'); rmdir($dir); }
    }
}
