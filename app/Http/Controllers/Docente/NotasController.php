<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Docente\Concerns\ResuelveMateriasDelDocente;
use App\Models\Nota;
use App\Models\DetalleCurso;
use Illuminate\Http\Request;

class NotasController extends Controller
{
    use ResuelveMateriasDelDocente;
    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // DOCENTE: REGISTRO DE NOTAS
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * REGLA: la unidad académica es Curso + Materia + Maestro.
     *
     * Un curso no pertenece a un maestro; cada materia del curso puede
     * impartirla alguien distinto. La fuente de verdad es
     * asignaciones_docentes con estado = 'activa', igual que en el dashboard
     * y en el registro de asistencia.
     */

    /**
     * Mostrar vista de notas con filtros por curso y materia
     */
    public function index(Request $request)
    {
        $maestro = $this->maestroAutenticado();

        $detalleCursos = $this->detallesAsignados($maestro);

        $cursos = $detalleCursos
            ->groupBy('curso_id')
            ->map(fn ($items) => [
                'curso'    => $items->first()->curso,
                'materias' => $items->pluck('materia')->values(),
            ])
            ->values();

        $detalleCursoId    = $request->input('detalle_curso_id');
        $cursoSeleccionado = $request->input('curso_id');

        if ($detalleCursoId && ! $cursoSeleccionado) {
            $dc = $detalleCursos->firstWhere('id', $detalleCursoId);
            $cursoSeleccionado = $dc?->curso_id;
        }

        $alumnos = collect();
        $notas   = collect();
        $detalle = null;

        if ($detalleCursoId) {
            $detalle = $detalleCursos->firstWhere('id', $detalleCursoId);

            abort_if($detalle === null, 403, 'No tiene esa materia asignada actualmente.');

            $alumnos = $this->alumnosDeLaMateria($detalle);

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
        $maestro = $this->maestroAutenticado();

        // ── Validar datos de entrada ──────────────────────────────────────
        $request->validate([
            'detalle_curso_id'       => 'required|integer|exists:detalle_cursos,id',
            'notas'                  => 'required|array',
            'notas.*.laboratorio'    => 'nullable|numeric|min:0|max:10',
            'notas.*.examen_teorico' => 'nullable|numeric|min:0|max:10',
            'notas.*.practica'       => 'nullable|numeric|min:0|max:10',
            'notas.*.sos'            => 'nullable|numeric|min:0|max:10',
            'notas.*.conducta'       => 'nullable|string|max:2',
        ]);

        // ── Autorización real: la materia debe estar asignada al maestro ──
        $detalle = DetalleCurso::with(['curso', 'materia'])
            ->where('id', $request->detalle_curso_id)
            ->firstOrFail();

        abort_unless(
            $detalle->curso->activo,
            403,
            'El curso no está activo.'
        );

        abort_unless(
            $detalle->esMateriaDelMaestro($maestro->id),
            403,
            'No tiene una asignación vigente para esa materia.'
        );

        $alumnosPermitidos = $this->alumnosDeLaMateria($detalle)->pluck('id');

        // Se valida el lote COMPLETO antes de guardar nada: un alumno
        // manipulado no debe dejar notas a medias.
        $idsEnviados = collect(array_keys($request->notas))->map(fn ($id) => (int) $id);

        abort_if(
            $idsEnviados->diff($alumnosPermitidos)->isNotEmpty(),
            403,
            'Uno de los alumnos no corresponde a la materia seleccionada.'
        );

        // ── Guardar notas de cada alumno ─────────────────────────────────
        foreach ($request->notas as $alumnoId => $valores) {
            Nota::updateOrCreate(
                [
                    'alumno_id'        => (int) $alumnoId,
                    'detalle_curso_id' => $detalle->id,
                ],
                [
                    'laboratorio'    => $valores['laboratorio'] ?? null,
                    'examen_teorico' => $valores['examen_teorico'] ?? null,
                    'practica'       => $valores['practica'] ?? null,
                    'sos'            => $valores['sos'] ?? null,
                    'conducta'       => $valores['conducta'] ?? null,
                    'registrado_por' => $maestro->id,
                ]
            );
        }

        return redirect()
            ->route('docente.notas', ['detalle_curso_id' => $detalle->id])
            ->with('success', 'Notas guardadas correctamente.');
    }
}
