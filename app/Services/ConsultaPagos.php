<?php

namespace App\Services;

use App\Models\Pago;
use Illuminate\Database\Eloquent\Builder;

class ConsultaPagos
{
    public const REGLAS = [
        'alumno_id' => 'nullable|integer|min:1|exists:alumnos,id',
        'q' => 'nullable|string|max:100',
        'anio' => 'nullable|integer|min:1900|max:2100',
        'mes' => 'nullable|integer|min:1|max:12',
        'page' => 'nullable|integer|min:1',
    ];

    public function consulta(array $filtros): Builder
    {
        $query = Pago::query()->join('mensualidades as cuota', 'cuota.id', '=', 'pagos.mensualidad_id')
            ->join('alumnos as alumno', 'alumno.id', '=', 'cuota.alumno_id')
            ->leftJoin('cursos as curso_historico', 'curso_historico.id', '=', 'cuota.curso_id')
            ->select(['pagos.*', 'cuota.alumno_id', 'cuota.mes as cuota_mes', 'cuota.anio as cuota_anio',
                'cuota.estado as cuota_estado', 'alumno.nombre', 'alumno.apellido', 'alumno.codigo',
                'curso_historico.nombre as curso_nombre', 'cuota.curso_id'])
            ->when($filtros['alumno_id'] ?? null, fn ($q, $id) => $q->where('cuota.alumno_id', $id))
            ->when($filtros['anio'] ?? null, fn ($q, $anio) => $q->whereYear('pagos.fecha_pago', $anio))
            ->when($filtros['mes'] ?? null, fn ($q, $mes) => $q->whereMonth('pagos.fecha_pago', $mes));
        foreach (preg_split('/\s+/u', trim($filtros['q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $termino) {
            $patron = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($termino)).'%';
            $query->where(fn ($q) => $q->whereRaw("LOWER(alumno.nombre) LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("LOWER(alumno.apellido) LIKE ? ESCAPE '!'", [$patron])->orWhereRaw("LOWER(alumno.codigo) LIKE ? ESCAPE '!'", [$patron]));
        }
        return $query;
    }
}
