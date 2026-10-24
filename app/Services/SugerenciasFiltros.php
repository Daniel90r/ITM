<?php

namespace App\Services;

use App\Models\Alumno;
use Illuminate\Database\Eloquent\Builder;

/** Solo presentación de resultados; la elegibilidad pertenece a cada módulo. */
class SugerenciasFiltros
{
    public static function personas(Builder $consulta)
    {
        if (mb_strlen(trim(request('q', ''))) < 2) return response()->json(['alumnos' => []])->header('Cache-Control', 'private, no-store');
        $filas = $consulta->withoutEagerLoads()->reorder()->orderBy('apellido')->orderBy('nombre')->orderBy('id')
            ->limit(8)->get(['id', 'nombre', 'apellido', 'codigo']);
        return response()->json(['alumnos' => $filas->map(fn ($a) => [
            'id' => $a->id, 'nombre' => trim($a->nombre.' '.$a->apellido), 'codigo' => $a->codigo,
        ])])->header('Cache-Control', 'private, no-store');
    }

    public static function alumnosDe(Builder $consulta, string $columna = 'alumno_id')
    {
        return Alumno::whereIn('id', $consulta->withoutEagerLoads()->reorder()->select($columna));
    }
}
