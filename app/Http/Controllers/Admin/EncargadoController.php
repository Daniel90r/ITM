<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use Illuminate\Http\Request;

class EncargadoController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate(['q' => 'nullable|string|max:100']);
        $busqueda = trim($validated['q'] ?? '');

        $consulta = Alumno::query()
            ->whereDate('fecha_nacimiento', '>', today()->subYears(18)->toDateString())
            ->whereDate('fecha_nacimiento', '<=', today()->toDateString())
            ->with(['encargados' => fn ($query) => $query->orderBy('apellido')->orderBy('nombre')->orderBy('encargados.id')]);

        // Buscar nombres completos por palabras, sin SQL específico de MySQL.
        // Los comodines introducidos por el usuario se tratan como texto literal.
        foreach (preg_split('/\s+/u', $busqueda, -1, PREG_SPLIT_NO_EMPTY) as $termino) {
            $patron = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termino).'%';
            $consulta->where(function ($query) use ($patron) {
                $query->whereRaw("alumnos.nombre LIKE ? ESCAPE '!'", [$patron])
                    ->orWhereRaw("alumnos.apellido LIKE ? ESCAPE '!'", [$patron])
                    ->orWhereRaw("alumnos.codigo LIKE ? ESCAPE '!'", [$patron])
                    ->orWhereHas('encargados', function ($encargados) use ($patron) {
                        $encargados->whereRaw("encargados.nombre LIKE ? ESCAPE '!'", [$patron])
                            ->orWhereRaw("encargados.apellido LIKE ? ESCAPE '!'", [$patron]);
                    });
            });
        }

        if ($request->routeIs('admin.sugerencias.encargados')) return \App\Services\SugerenciasFiltros::personas($consulta);
        $alumnos = $consulta->orderBy('apellido')->orderBy('nombre')->orderBy('id')
            ->paginate(20)->withQueryString();

        return response()->view('admin.encargados', compact('alumnos', 'busqueda'))
            ->header('Cache-Control', 'private, no-store');
    }
}
