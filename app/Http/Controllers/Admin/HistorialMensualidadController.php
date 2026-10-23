<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Services\HistorialMensualidades;
use Illuminate\Http\Request;

class HistorialMensualidadController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $q = trim($filtros['q'] ?? '');
        $consulta = Alumno::select('id', 'nombre', 'apellido', 'codigo', 'activo');
        foreach (preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) as $termino) {
            $patron = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termino).'%';
            $consulta->where(fn ($a) => $a->whereRaw("nombre LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("apellido LIKE ? ESCAPE '!'", [$patron])->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$patron]));
        }
        $alumnos = $consulta->orderBy('apellido')->orderBy('nombre')->orderBy('id')->paginate(25)->appends(['q' => $q]);
        return response()->view('admin.mensualidades-historial-alumnos', compact('alumnos', 'q'))->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Alumno $alumno, HistorialMensualidades $historial)
    {
        $filtros = $request->validate(['anio' => 'nullable|integer|min:1900|max:2100']);
        $anio = (int) ($filtros['anio'] ?? now()->timezone('America/El_Salvador')->year);
        // Parametros antiguos de curso no deben ocultar obligaciones del alumno.
        $datos = $historial->consultar($alumno, $anio);
        return response()->view('admin.mensualidades-historial', $datos)->header('Cache-Control', 'private, no-store');
    }
}
