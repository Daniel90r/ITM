<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\Maestro;
use App\Models\DetalleCurso;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AsistenciaController extends Controller
{
    public function index(Request $request)
    {
        $cursos = Curso::where('activo', true)->orderBy('nombre')->get();
        $maestros = Maestro::where('activo', true)->orderBy('nombre')->get();

        $cursoId = $request->input('curso_id');
        $maestroId = $request->input('maestro_id');
        $detalleCursoId = $request->input('detalle_curso_id');
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');

        $asistencias = Asistencia::with(['alumno', 'curso', 'detalleCurso.materia', 'detalleCurso.maestro'])
            ->when($cursoId, fn($q) => $q->where('curso_id', $cursoId))
            ->when($detalleCursoId, fn($q) => $q->where('detalle_curso_id', $detalleCursoId))
            ->when($maestroId, fn($q) => $q->whereHas('detalleCurso', fn($q) => $q->where('maestro_id', $maestroId)))
            ->when($fechaDesde, fn($q) => $q->whereDate('fecha', '>=', $fechaDesde))
            ->when($fechaHasta, fn($q) => $q->whereDate('fecha', '<=', $fechaHasta))
            ->orderBy('fecha', 'desc')
            ->paginate(50)
            ->appends($request->except('page'));

        // Si se seleccionó un curso, cargar sus materias para el filtro
        $detalleCursos = collect();
        if ($cursoId) {
            $detalleCursos = DetalleCurso::where('curso_id', $cursoId)
                ->with('materia')
                ->get();
        }

        return view('admin.asistencias', compact(
            'cursos', 'maestros', 'detalleCursos', 'asistencias',
            'cursoId', 'maestroId', 'detalleCursoId', 'fechaDesde', 'fechaHasta'
        ));
    }
}