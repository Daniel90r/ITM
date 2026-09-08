<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\Materia;
use App\Models\DetalleCurso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeccionController extends Controller
{
    public function index()
    {
        $cursos = Curso::withCount('inscripciones')->orderBy('seccion')->get();
        return view('admin.cursos', compact('cursos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'seccion' => 'required|string|max:2',
            'nivel' => 'required|string|in:Matutino,Vespertino',
            'anio_lectivo' => 'required|integer|min:2020|max:2099',

            'fecha_inicio' => [
                'required',
                'date',
            ],

            'fecha_fin' => [
                'required',
                'date',
                'after:fecha_inicio',
            ],

            'activo' => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $curso = Curso::create([
                'nombre' => $validated['nombre'],
                'seccion' => strtoupper($validated['seccion']),
                'nivel' => $validated['nivel'],
                'anio_lectivo' => $validated['anio_lectivo'],
                'fecha_inicio' => $validated['fecha_inicio'],
                'fecha_fin' => $validated['fecha_fin'],
                'activo' => $request->filled('activo') ? 1 : 0,
            ]);

            $materiasActivas = Materia::where('activa', true)->get();

            foreach ($materiasActivas as $materia) {
                DetalleCurso::firstOrCreate(
                    ['curso_id' => $curso->id, 'materia_id' => $materia->id],
                    ['maestro_id' => null]
                );
            }
        });

        return redirect()->route('admin.cursos')->with('success', 'Curso registrado correctamente.');
    }

    public function show($id)
    {
        $curso = Curso::with([
            'inscripciones.alumno',
            'detalleCursos.materia',
            'detalleCursos.maestro',
        ])->findOrFail($id);

        return view('admin.curso-detalle', compact('curso'));
    }

    public function edit($id)
    {
        $curso = Curso::findOrFail($id);
        return view('admin.curso-editar', compact('curso'));
    }

    public function update(Request $request, $id)
    {
        $curso = Curso::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'nivel' => 'required|string|in:Matutino,Vespertino',
            'seccion' => 'required|string|max:2',
            'anio_lectivo' => 'required|integer|min:2020|max:2099',

            'fecha_inicio' => [
                'required',
                'date',
            ],

            'fecha_fin' => [
                'required',
                'date',
                'after:fecha_inicio',
            ],

            'activo' => 'sometimes|boolean',
        ]);

        $curso->update([
            'nombre' => $validated['nombre'],
            'nivel' => $validated['nivel'],
            'seccion' => $validated['seccion'],
            'anio_lectivo' => $validated['anio_lectivo'],
            'fecha_inicio' => $validated['fecha_inicio'],
            'fecha_fin' => $validated['fecha_fin'],
            'activo' => $request->filled('activo') ? 1 : 0,
        ]);

        return redirect()->route('admin.cursos')->with('success', 'Curso actualizado correctamente.');
    }

    public function finalizar(Curso $curso)
    {
        if ($curso->estado_curso === 'FINALIZADO') {
            return back()->with('error', 'El curso ya está finalizado.');
        }

        if ($curso->estado_curso === 'PROXIMO') {
            return back()->with('error', 'No se puede finalizar un curso que todavía no ha iniciado.');
        }

        $curso->update([
            'finalizado_manualmente' => true,
        ]);

        return back()->with('success', 'El curso fue finalizado correctamente.');
    }
}
