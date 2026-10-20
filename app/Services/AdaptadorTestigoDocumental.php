<?php

namespace App\Services;

use App\Contracts\{RegistroConsumosIndependiente, TransporteTestigoDocumental};
use Closure;
use RuntimeException;
use Throwable;

/** Cliente experimental; deliberadamente incapaz de habilitar el canal real. */
final class AdaptadorTestigoDocumental implements RegistroConsumosIndependiente
{
    public function __construct(private TransporteTestigoDocumental $transporte, private string $registro,
        private string $epoca, private string $publica, private Closure $reloj, private ?Closure $monotonico = null) {}

    public function exigirDisponibleParaCanalReal(): void { throw new RuntimeException('REGISTRO_INDEPENDIENTE_REAL_PENDIENTE'); }
    public function consultar(array $solicitud): array { return $this->operar($solicitud, 'consultar'); }
    public function consumir(array $solicitud): array { return $this->operar($solicitud, 'consumir'); }

    private function operar(array $s, string $op): array
    {
        ProtocoloTestigoDocumental::solicitud($s);
        if ($s['registro_id'] !== $this->registro || $s['epoca'] !== $this->epoca) throw new RuntimeException('TESTIGO_DESCONOCIDO');
        $inicio = $this->ticks();
        $peticion = $s + ['formato' => 2, 'operacion' => $op];
        $reto = ProtocoloTestigoDocumental::verificar($this->viajar('desafiar', $peticion, $inicio, false), 'reto', $this->publica);
        ProtocoloTestigoDocumental::exacto($reto, [...array_keys($peticion), 'tipo', 'nonce', 'desde', 'hasta']);
        $this->corresponde($reto, $peticion);
        if (!is_string($reto['nonce']) || !preg_match('/\A[a-f0-9]{64}\z/D', $reto['nonce'])
            || !is_int($reto['desde']) || !is_int($reto['hasta']) || $reto['hasta'] - $reto['desde'] !== 30
            || ($this->reloj)() < $reto['desde'] || ($this->reloj)() >= $reto['hasta']) throw new RuntimeException('DESAFIO_NO_VIGENTE');
        $sobre = $this->viajar($op, $reto, $inicio, $op === 'consumir');
        $recibo = ProtocoloTestigoDocumental::verificar($sobre, 'recibo', $this->publica);
        ProtocoloTestigoDocumental::recibo($recibo);
        ProtocoloTestigoDocumental::exacto($recibo, [...array_keys($reto), 'estado', 'resultado', 'recibo_id', 'emitido_en']);
        $this->corresponde($recibo, array_replace($reto, ['tipo' => 'recibo']));
        if (!is_string($recibo['recibo_id']) || !preg_match('/\A[a-f0-9]{64}\z/D', $recibo['recibo_id'])
            || !is_int($recibo['emitido_en']) || $recibo['emitido_en'] < $reto['desde']
            || $recibo['emitido_en'] >= $reto['hasta'] || ($this->reloj)() >= $reto['hasta']) throw new RuntimeException('DESAFIO_NO_VIGENTE');
        $valido = $op === 'consultar'
            ? in_array([$recibo['estado'], $recibo['resultado']], [['libre', 'consulta'], ['consumida', 'consulta']], true)
            : in_array([$recibo['estado'], $recibo['resultado']], [['consumida', 'nuevo'], ['consumida', 'rechazado']], true);
        if (!$valido) throw new RuntimeException('ESTADO_DE_CONSUMO_AMBIGUO');
        if ($recibo['resultado'] === 'rechazado') throw new RuntimeException('AUTORIZACION_CONSUMIDA');
        return $sobre;
    }

    private function corresponde(array $data, array $esperado): void
    {
        foreach ($esperado as $k => $v) if (($data[$k] ?? null) !== $v) throw new RuntimeException('RESPUESTA_TESTIGO_NO_CORRESPONDE');
    }

    private function viajar(string $op, array $data, int $inicio, bool $incierto): array
    {
        // Presupuesto TOTAL de dos viajes: 5000 ms. No hay bucles de reintento.
        $restante = 5000 - (int) (($this->ticks() - $inicio) / 1000000);
        if ($restante <= 0) throw new RuntimeException($incierto ? 'CONSUMO_REMOTO_INCIERTO' : 'TESTIGO_TIEMPO_AGOTADO');
        try { $r = $this->transporte->intercambiar($op, $data, $restante); }
        catch (Throwable $e) { throw new RuntimeException($incierto ? 'CONSUMO_REMOTO_INCIERTO'
            : ($e instanceof \App\Exceptions\TestigoTiempoAgotado ? 'TESTIGO_TIEMPO_AGOTADO' : 'TESTIGO_COMUNICACION_FALLIDA')); }
        if (($this->ticks() - $inicio) / 1000000 >= 5000) throw new RuntimeException($incierto ? 'CONSUMO_REMOTO_INCIERTO' : 'TESTIGO_TIEMPO_AGOTADO');
        return $r;
    }

    private function ticks(): int { return $this->monotonico ? ($this->monotonico)() : hrtime(true); }
}
