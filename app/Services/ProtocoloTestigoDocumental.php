<?php

namespace App\Services;

use RuntimeException;
use Throwable;

/** Protocolo ITM experimental v2, no compatible implícitamente con proveedores externos. */
final class ProtocoloTestigoDocumental
{
    public const BASE = ['registro_id', 'epoca', 'id', 'permiso_sha256', 'vinculo_sha256', 'lote', 'ejecutor_sid', 'desafio'];

    /** Objeto plano ASCII, claves ordenadas byte a byte; solo strings e enteros. */
    public static function canonico(array $data): string
    {
        if (!$data || array_is_list($data)) throw new RuntimeException('PROTOCOLO_INVALIDO');
        foreach ($data as $k => $v) {
            if (!is_string($k) || !preg_match('/\A[a-z_0-9]+\z/D', $k)
                || !(is_int($v) && $v >= 0 || is_string($v) && preg_match('/\A[\x20-\x7e]*\z/D', $v))) {
                throw new RuntimeException('PROTOCOLO_INVALIDO');
            }
        }
        ksort($data, SORT_STRING);
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($json) > 16384) throw new RuntimeException('PROTOCOLO_INVALIDO');
        return $json;
    }

    public static function exacto(array $data, array $keys): void
    {
        $actual = array_keys($data); sort($actual); sort($keys);
        if ($actual !== $keys) throw new RuntimeException('PROTOCOLO_INVALIDO');
        self::canonico($data);
    }

    public static function solicitud(array $s): void
    {
        self::exacto($s, self::BASE);
        foreach (['registro_id' => 32, 'epoca' => 32, 'id' => 32, 'permiso_sha256' => 64, 'vinculo_sha256' => 64, 'desafio' => 64] as $k => $n) {
            if (!is_string($s[$k]) || !preg_match('/\A[a-f0-9]{'.$n.'}\z/D', $s[$k])) throw new RuntimeException('PROTOCOLO_INVALIDO');
        }
        if (!is_string($s['lote']) || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}\z/D', $s['lote'])
            || !is_string($s['ejecutor_sid']) || !preg_match('/\AS-1-5-21-(?:[0-9]+-){3}[0-9]+\z/D', $s['ejecutor_sid'])) {
            throw new RuntimeException('PROTOCOLO_INVALIDO');
        }
    }

    private static function dominio(string $tipo): string
    {
        if (!in_array($tipo, ['reto', 'recibo'], true)) throw new RuntimeException('PROTOCOLO_INVALIDO');
        return "ITM/testigo/v2/".$tipo."\0";
    }

    public static function firmar(array $data, string $tipo, string $secret): array
    {
        $json = self::canonico($data);
        return ['payload' => base64_encode($json), 'firma' => base64_encode(sodium_crypto_sign_detached(self::dominio($tipo).$json, $secret))];
    }

    public static function verificar(array $sobre, string $tipo, string $publica): array
    {
        if (array_diff(array_keys($sobre), ['payload', 'firma']) || count($sobre) !== 2) throw new RuntimeException('PROTOCOLO_INVALIDO');
        $bytes = is_string($sobre['payload'] ?? null) && strlen($sobre['payload']) <= 22000 ? base64_decode($sobre['payload'], true) : false;
        $firma = is_string($sobre['firma'] ?? null) && strlen($sobre['firma']) <= 88 ? base64_decode($sobre['firma'], true) : false;
        $key = base64_decode($publica, true);
        if ($bytes === false || $firma === false || $key === false || strlen($key) !== 32 || strlen($firma) !== 64
            || base64_encode($bytes) !== $sobre['payload'] || base64_encode($firma) !== $sobre['firma'] || base64_encode($key) !== $publica
            || !sodium_crypto_sign_verify_detached($firma, self::dominio($tipo).$bytes, $key)) throw new RuntimeException('FIRMA_DE_CONSUMO_INVALIDA');
        try { $data = json_decode($bytes, true, 4, JSON_THROW_ON_ERROR); }
        catch (Throwable) { throw new RuntimeException('PROTOCOLO_INVALIDO'); }
        if (!is_array($data) || self::canonico($data) !== $bytes || ($data['formato'] ?? null) !== 2 || ($data['tipo'] ?? null) !== $tipo) {
            throw new RuntimeException('PROTOCOLO_INVALIDO');
        }
        return $data;
    }

    public static function recibo(array $r): void
    {
        self::exacto($r, [...self::BASE, 'formato', 'operacion', 'tipo', 'nonce', 'desde', 'hasta', 'estado', 'resultado', 'recibo_id', 'emitido_en']);
        self::solicitud(array_intersect_key($r, array_flip(self::BASE)));
        foreach (['nonce', 'recibo_id'] as $k) if (!is_string($r[$k]) || !preg_match('/\A[a-f0-9]{64}\z/D', $r[$k])) throw new RuntimeException('PROTOCOLO_INVALIDO');
        if ($r['formato'] !== 2 || $r['tipo'] !== 'recibo' || !is_int($r['desde']) || !is_int($r['hasta']) || !is_int($r['emitido_en'])
            || $r['hasta'] - $r['desde'] !== 30 || $r['emitido_en'] < $r['desde'] || $r['emitido_en'] >= $r['hasta']) throw new RuntimeException('PROTOCOLO_INVALIDO');
        $estados = $r['operacion'] === 'consultar' ? [['libre', 'consulta'], ['consumida', 'consulta']]
            : ($r['operacion'] === 'consumir' ? [['consumida', 'nuevo'], ['consumida', 'rechazado']] : []);
        if (!in_array([$r['estado'], $r['resultado']], $estados, true)) throw new RuntimeException('ESTADO_DE_CONSUMO_AMBIGUO');
    }
}
