<?php

namespace App\Services;

use App\Models\DetalleCurso;
use Illuminate\Database\Eloquent\Builder;

class AsistenciaHistoricaPdf
{
    public function datos(DetalleCurso $detalle, array $filtros, Builder $consulta): array
    {
        // No filtrar por matrícula vigente: exportar el mismo historial consultado.
        $registros = (clone $consulta)->with(['alumno:id,nombre,apellido', 'maestro:id,nombre,apellido'])
            ->whereHas('alumno')->whereHas('curso')->orderByDesc('fecha')->orderByDesc('id')->get()->unique('id')->values();
        $fechas = $registros->map(fn ($r) => $r->fecha->format('Y-m-d'))->unique()->sort()->values();
        $docente = $registros->pluck('maestro')->filter()->unique('id')->map->nombre_completo->implode(', ') ?: 'No registrado';
        $horarios = app(HorariosCurso::class)->lineas($detalle->curso);
        $estadoAplicado = FiltrosHistoricosAsistencia::ESTADOS[$filtros['estado']];
        $totales = $registros->countBy('estado');
        $observaciones = $registros->filter(fn ($r) => filled($r->observacion))->flatMap(fn ($r) =>
            mb_str_split($r->fecha->format('d/m/Y').' · '.$r->alumno->nombre_completo.': '.$r->observacion, 450))->values();
        $paginas = $registros->isEmpty() ? collect([collect()]) : $registros->chunk(10);
        return compact('detalle', 'registros', 'fechas', 'docente', 'horarios', 'estadoAplicado', 'totales', 'observaciones', 'paginas');
    }
}
