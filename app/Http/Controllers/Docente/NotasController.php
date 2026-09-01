<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Nota;
use App\Models\Maestro;
use App\Models\DetalleCurso;
use App\Models\Inscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotasController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // DOCENTE: REGISTRO DE NOTAS
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * Mostrar vista de notas con filtros por curso y materia
     */
    public function index(Request $request)
    {
        $maestro = Maestro::where('user_id', Auth::id())->firstOrFail();

        // ─────────────────────────────────────────────────────────────────────────────────
        // Obtener cursos y materias asignados al docente
        // ─────────────────────────────────────────────────────────────────────────────────
        $detalleCursos = DetalleCurso::with(['curso', 'materia'])
            ->where('maestro_id', $maestro->id)
            ->whereHas('curso', fn($q) => $q->where('activo', true))
            ->get();

        $cursos = $detalleCursos->groupBy('curso_id')->map(function ($items) {
            return [
                'curso'    => $items->first()->curso,
                'materias' => $items->map(fn($i) => $i->materia),
            ];
        })->values();

        $detalleCursoId    = $request->input('detalle_curso_id');
        $cursoSeleccionado = $request->input('curso_id');

        if ($detalleCursoId && !$cursoSeleccionado) {
            $dc = $detalleCursos->firstWhere('id', $detalleCursoId);
            $cursoSeleccionado = $dc?->curso_id;
        }

        // ─────────────────────────────────────────────────────────────────────────────────
        // Obtener alumnos y notas si hay materia seleccionada
        // ─────────────────────────────────────────────────────────────────────────────────
        $alumnos = collect();
        $notas   = collect();
        $detalle = null;

        if ($detalleCursoId) {
            $detalle = DetalleCurso::with(['curso', 'materia'])
                ->where('id', $detalleCursoId)
                ->where('maestro_id', $maestro->id)
                ->firstOrFail();

            $alumnos = Inscripcion::with('alumno')
                ->where('curso_id', $detalle->curso_id)
                ->where('estado', 'aprobada')
                ->get()
                ->map(fn($i) => $i->alumno)
                ->sortBy('apellido');

            $notas = Nota::where('detalle_curso_id', $detalleCursoId)
                ->get()
                ->keyBy('alumno_id');
        }

        return view('docente.notas', compact(
            'maestro', 'cursos', 'detalleCursos',
            'detalleCursoId', 'cursoSeleccionado',
            'alumnos', 'notas', 'detalle'
        ));
    }

    /**
     * Guardar notas de los alumnos
     */
    public function guardar(Request $request)
    {
        $maestro = Maestro::where('user_id', Auth::id())->firstOrFail();

        // ─────────────────────────────────────────────────────────────────────────────────
        // Validar datos de entrada
        // ─────────────────────────────────────────────────────────────────────────────────
        $request->validate([
            'detalle_curso_id'       => 'required|exists:detalle_cursos,id',
            'notas.*.laboratorio'    => 'nullable|numeric|min:0|max:10',
            'notas.*.examen_teorico' => 'nullable|numeric|min:0|max:10',
            'notas.*.practica'       => 'nullable|numeric|min:0|max:10',
            'notas.*.sos'            => 'nullable|numeric|min:0|max:10',
            'notas.*.conducta'       => 'nullable|string|max:2',
        ]);

        $detalle = DetalleCurso::where('id', $request->detalle_curso_id)
            ->where('maestro_id', $maestro->id)
            ->firstOrFail();

        // ─────────────────────────────────────────────────────────────────────────────────
        // Guardar notas de cada alumno
        // ─────────────────────────────────────────────────────────────────────────────────
        foreach ($request->notas as $alumnoId => $valores) {
            Nota::updateOrCreate(
                [
                    'alumno_id'        => $alumnoId,
                    'detalle_curso_id' => $detalle->id,
                ],
                [
                    'laboratorio'    => $valores['laboratorio']    ?? null,
                    'examen_teorico' => $valores['examen_teorico'] ?? null,
                    'practica'       => $valores['practica']       ?? null,
                    'sos'            => $valores['sos']            ?? null,
                    'conducta'       => $valores['conducta']       ?? null,
                    'registrado_por' => $maestro->id,
                ]
            );
        }

        return redirect()
            ->route('docente.notas', ['detalle_curso_id' => $detalle->id])
            ->with('success', 'Notas guardadas correctamente.');
    }
}
