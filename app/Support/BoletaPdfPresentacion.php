<?php

namespace App\Support;

use App\Models\Nota;
use Illuminate\Support\Collection;

final class BoletaPdfPresentacion
{
    /** Usa exclusivamente las materias que BoletaService ya determinó para el alumno. */
    public static function notasDesdeFilas(array $filas, int $cursoId): Collection
    {
        return collect($filas)->filter(fn ($fila) => (int) $fila['detalle_curso']->curso_id === $cursoId)
            ->map(function ($fila) {
                $nota = $fila['nota'] ? clone $fila['nota'] : new Nota(['detalle_curso_id' => $fila['detalle_curso']->id]);
                $nota->setRelation('detalleCurso', $fila['detalle_curso']);
                return $nota;
            })->values();
    }

    /** La asignación vigente no se presenta como autoría histórica ni como instructor general. */
    public static function docentesActuales($detalle): string
    {
        if (! $detalle || ! $detalle->relationLoaded('asignacionesDocentes')) {
            return 'Sin asignación docente vigente verificable';
        }
        $hoy = now()->startOfDay();
        $nombres = $detalle->asignacionesDocentes->filter(fn ($a) => $a->estado === 'activa'
            && $a->fecha_inicio && $a->fecha_inicio->lte($hoy)
            && (! $a->fecha_fin || $a->fecha_fin->gte($hoy)))
            ->map(fn ($a) => $a->relationLoaded('maestro') && $a->maestro
                ? trim($a->maestro->nombre.' '.$a->maestro->apellido) : null)
            ->filter()->unique()->values();

        return $nombres->isEmpty() ? 'Sin asignación docente vigente verificable' : $nombres->implode(' / ');
    }
}
