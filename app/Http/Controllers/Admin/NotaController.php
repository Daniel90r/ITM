<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\Alumno;
use App\Models\Materia;
use App\Models\Maestro;
use Illuminate\Http\Request;

class NotaController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate([
            'incluir_historicos' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:150'],
            'curso_id' => ['nullable', 'integer', 'exists:cursos,id'],
            'materia_id' => ['nullable', 'integer', 'exists:materias,id',
                ...($request->filled('curso_id') ? [\Illuminate\Validation\Rule::exists('detalle_cursos', 'materia_id')->where('curso_id', $request->input('curso_id'))] : [])],
            'maestro_id' => ['nullable', 'integer', 'exists:maestros,id'],
        ]);
        $filtros = app(\App\Services\CursosConsulta::class)->normalizar($filtros);
        // El mismo alcance se aplica a la selección de alumnos y a sus notas.
        $alcance = function ($query) use ($filtros) {
            $query->whereHas('detalleCurso', function ($detalle) use ($filtros) {
                app(\App\Services\CursosConsulta::class)->aplicar($detalle, $filtros, 'curso_id');
                foreach (['curso_id', 'materia_id', 'maestro_id'] as $campo) {
                    if (!empty($filtros[$campo])) $detalle->where($campo, $filtros[$campo]);
                }
            });
        };
        $alumnos = Alumno::query()->whereHas('notas', $alcance)
            ->when($filtros['q'] ?? null, function ($query, $texto) {
                foreach (preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) as $parte) {
                    $query->where(fn ($q) => $q->where('nombre', 'like', "%{$parte}%")
                        ->orWhere('apellido', 'like', "%{$parte}%")->orWhere('codigo', 'like', "%{$parte}%"));
                }
            })
            ->with(['notas' => function ($query) use ($alcance) {
                $alcance($query);
                $query->with(['detalleCurso.curso', 'detalleCurso.materia'])->orderBy('detalle_curso_id')->orderBy('id');
            }])
            ->orderBy('apellido')->orderBy('nombre')->orderBy('id')->paginate(25)->withQueryString();
        $cursos = app(\App\Services\CursosConsulta::class)->opciones($filtros);
        $materias = Materia::whereHas('detalleCursos', fn ($q) => app(\App\Services\CursosConsulta::class)->aplicar($q, $filtros)
            ->when($filtros['curso_id'] ?? null, fn ($q, $id) => $q->where('curso_id', $id)))->orderBy('nombre')->get();
        $maestros = Maestro::orderBy('nombre')->get();
        return view('admin.notas', compact('alumnos', 'cursos', 'materias', 'maestros', 'filtros'));
    }

    // Fase 0: el método `store` fue eliminado. POST /admin/notas (admin.notas.store)
    // quedó retirado porque:
    //  - la columna `notas.registrado_por` referencia `maestros.id`, pero se
    //    asignaba `auth()->id()` (users.id), produciendo una FK inválida o un
    //    maestro incorrecto;
    //  - la vista admin/notas.blade.php es exclusivamente de lectura y no había
    //    consumidor del POST;
    //  - el registro real de notas corresponde al docente (docente.notas.guardar),
    //    que valida la asignación del maestro antes de guardar.
}
