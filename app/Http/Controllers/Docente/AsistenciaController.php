<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\DetalleCurso;
use App\Models\Inscripcion;
use App\Models\Maestro;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AsistenciaController extends Controller
{
    public function index(Request $request)
    {
        $maestro = Maestro::where('user_id', Auth::id())
            ->where('activo', true)
            ->firstOrFail();

        $cursos = DetalleCurso::with(['curso', 'materia'])
            ->where('maestro_id', $maestro->id)
            ->whereHas('curso', fn($q) => $q->where('activo', true))
            ->get()
            ->unique('curso_id')
            ->map(fn($detalle) => $detalle->curso)
            ->values();

        $cursoId = $request->input('curso_id');
        $fecha = $request->input('fecha') ?: today()->toDateString();

        $curso = null;
        $alumnos = collect();
        $asistencias = collect();

        if ($cursoId) {
            $detalleCurso = DetalleCurso::with(['curso', 'materia'])
                ->where('maestro_id', $maestro->id)
                ->whereHas('curso', fn($q) => $q->where('activo', true))
                ->where('curso_id', $cursoId)
                ->firstOrFail();

            $curso = $detalleCurso->curso->load('detalleCursos.materia');

            $alumnos = Inscripcion::with('alumno')
                ->where('curso_id', $curso->id)
                ->where('estado', 'aprobada')
                ->get()
                ->map(fn($i) => $i->alumno)
                ->sortBy(fn($a) => $a->apellido . ' ' . $a->nombre)
                ->values();

            $asistencias = Asistencia::where('curso_id', $curso->id)
                ->whereDate('fecha', $fecha)
                ->get()
                ->keyBy('alumno_id');
        }

        return view('docente.asistencia', compact(
            'maestro', 'cursos', 'curso', 'cursoId', 'fecha', 'alumnos', 'asistencias'
        ));
    }

    public function guardar(Request $request)
    {
        $maestro = Maestro::where('user_id', Auth::id())
            ->where('activo', true)
            ->firstOrFail();

        $request->validate([
            'curso_id' => 'required|exists:cursos,id',
            'fecha' => 'required|date',
            'asistencias' => 'required|array',
            'asistencias.*.estado' => 'required|in:presente,ausente,permiso',
            'asistencias.*.observacion' => 'nullable|string|max:500',
        ]);

        $detalleCurso = DetalleCurso::with('curso')
            ->where('maestro_id', $maestro->id)
            ->whereHas('curso', fn($q) => $q->where('activo', true))
            ->where('curso_id', $request->curso_id)
            ->firstOrFail();

        $curso = $detalleCurso->curso;

        $fecha = Carbon::parse($request->fecha)->toDateString();
        $alumnosInscritos = Inscripcion::where('curso_id', $curso->id)
            ->where('estado', 'aprobada')
            ->pluck('alumno_id');

        foreach ($request->asistencias as $alumnoId => $datos) {
            if (!$alumnosInscritos->contains((int) $alumnoId)) {
                continue;
            }

            Asistencia::updateOrCreate(
                [
                    'curso_id' => $curso->id,
                    'alumno_id' => $alumnoId,
                    'fecha' => $fecha,
                ],
                [
                    'estado' => $datos['estado'] ?? 'presente',
                    'observacion' => trim($datos['observacion'] ?? '') ?: null,
                    'registrado_por' => $maestro->id,
                ]
            );
        }

        return redirect()
            ->route('docente.asistencia', ['curso_id' => $curso->id, 'fecha' => $fecha])
            ->with('success', 'Asistencia guardada correctamente.');
    }

    public function reporte(Request $request)
    {
        $maestro = Maestro::where('user_id', Auth::id())
            ->where('activo', true)
            ->firstOrFail();

        $request->validate([
            'curso_id' => 'required|exists:cursos,id',
            'fecha' => 'required|date',
        ]);

        $curso = \App\Models\Curso::with('detalleCursos.materia')
            ->where('activo', true)
            ->findOrFail($request->curso_id);

        $detalleCurso = DetalleCurso::with(['curso', 'materia'])
            ->where('maestro_id', $maestro->id)
            ->whereHas('curso', fn($q) => $q->where('activo', true))
            ->where('curso_id', $request->curso_id)
            ->firstOrFail();

        $alumnos = Inscripcion::with('alumno')
            ->where('curso_id', $curso->id)
            ->where('estado', 'aprobada')
            ->get()
            ->map(fn($i) => $i->alumno)
            ->sortBy(fn($a) => $a->apellido . ' ' . $a->nombre)
            ->values();

        $asistencias = Asistencia::where('curso_id', $curso->id)
            ->whereDate('fecha', $request->fecha)
            ->get()
            ->keyBy('alumno_id');

        $fecha = $request->fecha;
        $fechaFormateada = \Carbon\Carbon::parse($fecha)->isoFormat('D [de] MMMM YYYY');

        return view('docente.reporte-asistencia', compact(
            'maestro', 'curso', 'alumnos', 'asistencias', 'fecha', 'fechaFormateada'
        ));
    }
}