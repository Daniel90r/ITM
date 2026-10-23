<?php

namespace App\Services;

use App\Http\Controllers\Docente\Concerns\ResuelveMateriasDelDocente;
use App\Models\{Alumno, DetalleCurso};
use Illuminate\Database\Eloquent\Builder;

class ControlAsistenciaPdf
{
    use ResuelveMateriasDelDocente;

    public function datos(DetalleCurso $detalle, array $filtros, Builder $consulta): array
    {
        $ids = $this->alumnosDeLaMateria($detalle)->pluck('id');
        $q = Alumno::select('id', 'nombre', 'apellido')->whereIn('id', $ids);
        foreach (preg_split('/\s+/u', trim($filtros['q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $termino) {
            $patron = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termino).'%';
            $q->where(fn ($a) => $a->whereRaw("nombre LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("apellido LIKE ? ESCAPE '!'", [$patron])->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$patron]));
        }
        $alumnos = $q->orderBy('apellido')->orderBy('nombre')->orderBy('id')->get();
        $registros = (clone $consulta)->where('curso_id', $detalle->curso_id)->where('detalle_curso_id', $detalle->id)
            ->whereIn('alumno_id', $alumnos->pluck('id'))->with('maestro:id,nombre,apellido')->orderBy('fecha')->orderBy('id')->get();
        $fechas = $registros->map(fn ($a) => $a->fecha->format('Y-m-d'))->unique()->sort()->values();
        $celdas = $registros->groupBy(fn ($a) => $a->alumno_id.'|'.$a->fecha->format('Y-m-d'));
        $marcas = $celdas->map(fn ($grupo) => $grupo->count() === 1 ? match ($grupo->first()->estado) {
            'presente' => 'P', 'ausente' => 'A', 'permiso' => 'PM', default => '?',
        } : '?');
        $totalesAlumno = $alumnos->mapWithKeys(fn ($a) => [$a->id => $fechas->filter(fn ($f) => $marcas->get($a->id.'|'.$f) === 'P')->count()]);
        $totalesFecha = $fechas->mapWithKeys(fn ($f) => [$f => $alumnos->filter(fn ($a) => $marcas->get($a->id.'|'.$f) === 'P')->count()]);
        $docentes = $registros->pluck('maestro')->filter()->unique('id')->map->nombre_completo;
        $docente = $docentes->implode(', ') ?: 'No registrado';
        if ($registros->isEmpty()) {
            $activas = $detalle->asignacionesDocentes()->activas()->with('maestro')->get();
            if ($activas->count() === 1 && (int) $activas->first()->maestro_id === (int) $detalle->maestro_id) {
                $docente = ($activas->first()->maestro?->nombre_completo ?? 'No registrado').' (asignación actual)';
            }
        }
        $observaciones = $registros->filter(fn ($r) => filled($r->observacion))->map(function ($r) use ($alumnos) {
            $nombre = $alumnos->firstWhere('id', $r->alumno_id)?->nombre_completo;
            return $r->fecha->format('d/m/Y').' · '.$nombre.': '.$r->observacion;
        })->flatMap(fn ($texto) => mb_str_split($texto, 450))->values();
        $duplicadas = $celdas->filter(fn ($g) => $g->count() > 1)->count();
        $horarios = app(HorariosCurso::class)->lineas($detalle->curso);
        $paginas = collect();
        $bloquesFechas = $fechas->isEmpty() ? collect([collect()]) : $fechas->chunk(12);
        $bloquesAlumnos = $alumnos->isEmpty() ? collect([collect()]) : $alumnos->chunk(10);
        foreach ($bloquesFechas as $dias) foreach ($bloquesAlumnos as $grupo) {
            $paginas->push(['fechas' => $dias, 'alumnos' => $grupo]);
        }
        return compact('detalle', 'alumnos', 'registros', 'fechas', 'marcas', 'totalesAlumno', 'totalesFecha',
            'docente', 'observaciones', 'duplicadas', 'horarios', 'paginas');
    }
}
