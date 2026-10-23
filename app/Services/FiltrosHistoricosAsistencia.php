<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/** Criterios compartidos por el listado histórico y las descargas. */
class FiltrosHistoricosAsistencia
{
    public const ESTADOS = ['presente' => 'Presente', 'ausente' => 'Ausente', 'permiso' => 'Permiso'];

    public function aplicar(Builder|QueryBuilder $consulta, array $filtros, string $prefijo = ''): Builder|QueryBuilder
    {
        $filtros = app(PeriodoAsistencia::class)->conFechas($filtros);
        return $consulta
            ->when($filtros['curso_id'] ?? null, fn ($q, $id) => $q->where($prefijo.'curso_id', $id))
            ->when($filtros['detalle_curso_id'] ?? null, fn ($q, $id) => $q->where($prefijo.'detalle_curso_id', $id))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where($prefijo.'estado', $estado))
            ->when($filtros['fecha_desde'] ?? null, fn ($q, $fecha) => $q->whereDate($prefijo.'fecha', '>=', $fecha))
            ->when($filtros['fecha_hasta'] ?? null, fn ($q, $fecha) => $q->whereDate($prefijo.'fecha', '<=', $fecha));
    }
}
