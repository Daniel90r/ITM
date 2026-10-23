<?php
namespace App\Services;

use App\Models\{Asistencia, DetalleCurso};

/** Historial real: no depende de la inscripción o asignación vigente. */
class AsistenciasPeriodo
{
    public function datos(DetalleCurso $detalle, array $filtros): array
    {
        $base = $filtros;
        unset($base['estado']);
        $base['curso_id'] = $detalle->curso_id;
        $base['detalle_curso_id'] = $detalle->id;
        $consulta = app(FiltrosHistoricosAsistencia::class)->aplicar(Asistencia::query(), $base);
        foreach (preg_split('/\s+/u', trim($filtros['q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $termino) {
            $patron = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termino).'%';
            $consulta->whereHas('alumno', fn ($q) => $q->where(fn ($a) => $a->whereRaw("nombre LIKE ? ESCAPE '!'", [$patron])->orWhereRaw("apellido LIKE ? ESCAPE '!'", [$patron])->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$patron])));
        }
        $todos = $consulta->with(['alumno:id,nombre,apellido', 'maestro:id,nombre,apellido'])->whereHas('alumno')->orderBy('fecha')->orderBy('id')->get();
        $grupos = $todos->groupBy(fn ($r) => $r->alumno_id.'|'.$r->fecha->format('Y-m-d'));
        $estado = $filtros['estado'] ?? null;
        $incluidos = $grupos->filter(fn ($g) => !$estado || $g->contains('estado', $estado));
        $duplicados = $incluidos->filter(fn ($g) => $g->count() > 1);
        $validos = $incluidos->filter(fn ($g) => $g->count() === 1)->map->first()->values();
        $alumnos = $incluidos->flatten(1)->pluck('alumno')->unique('id')->sortBy(fn ($a) => [$a->apellido, $a->nombre, $a->id])->values();
        $fechas = $incluidos->flatten(1)->map(fn ($r) => $r->fecha->format('Y-m-d'))->unique()->sort()->values();
        $marcas = $incluidos->map(fn ($g) => $g->count() > 1 ? '?' : ['presente'=>'P', 'ausente'=>'A', 'permiso'=>'PM'][$g->first()->estado]);
        $vacio = ['presente'=>0, 'ausente'=>0, 'permiso'=>0];
        $porAlumno = []; $porMes = [];
        foreach ($alumnos as $alumno) {
            $porAlumno[$alumno->id] = $vacio;
            foreach (range(1,12) as $mes) $porMes[$alumno->id][$mes] = $vacio;
        }
        foreach ($validos as $r) {
            $porAlumno[$r->alumno_id][$r->estado]++;
            $porMes[$r->alumno_id][(int) $r->fecha->format('n')][$r->estado]++;
        }
        $totales = $vacio;
        foreach ($porAlumno as $fila) foreach ($vacio as $e => $_) $totales[$e] += $fila[$e];
        $fechasMes = collect(range(1,12))->mapWithKeys(fn ($m) => [$m => $fechas->filter(fn ($f) => (int) substr($f,5,2) === $m)->count()]);
        $docente = $incluidos->flatten(1)->pluck('maestro')->filter()->unique('id')->map->nombre_completo->implode(', ') ?: 'No registrado';
        $horarios = app(HorariosCurso::class)->lineas($detalle->curso);
        $observaciones = $incluidos->flatten(1)->filter(fn ($r) => filled($r->observacion))->flatMap(fn ($r) => mb_str_split($r->fecha->format('d/m/Y').' · '.$r->alumno->nombre_completo.': '.$r->observacion, 450))->values();
        $paginas = collect();
        $bloques = ($filtros['modalidad'] === 'mensual' ? $fechas->chunk(10) : collect([collect(range(1,12))]));
        if ($bloques->isEmpty()) $bloques = collect([collect()]);
        $longitud = $alumnos->max(fn ($a) => mb_strlen($a->nombre_completo)) ?? 0;
        $gruposAlumnos = $alumnos->chunk($longitud > 120 ? 3 : ($longitud > 60 ? 6 : ($filtros['modalidad'] === 'anual' ? 6 : 10)));
        if ($gruposAlumnos->isEmpty()) $gruposAlumnos = collect([collect()]);
        foreach ($bloques as $columnas) foreach ($gruposAlumnos as $grupo) $paginas->push(['columnas'=>$columnas, 'alumnos'=>$grupo]);
        return compact('detalle','alumnos','fechas','marcas','porAlumno','porMes','totales','fechasMes','duplicados','docente','horarios','observaciones','paginas');
    }
}
