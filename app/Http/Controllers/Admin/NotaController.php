<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Nota;
use App\Models\Curso;

class NotaController extends Controller
{
    public function index()
    {
        $cursos = Curso::with('detalleCursos.materia')->where('activo', 1)->get();
        $notas  = Nota::with(['alumno', 'detalleCurso.materia', 'detalleCurso.maestro'])->get();
        return view('admin.notas', compact('cursos', 'notas'));
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
