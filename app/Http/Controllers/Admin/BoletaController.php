<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Inscripcion;
use Barryvdh\DomPDF\Facade\Pdf;

class BoletaController extends Controller
{
    public function index()
    {
        $alumnos = Alumno::with(['notas.detalleCurso.materia', 'inscripciones.curso'])->get();
        return view('admin.boletas', compact('alumnos'));
    }

    /**
     * Resuelve el curso de contexto para la boleta de un alumno.
     *
     * Fase 1 — Integridad de la boleta:
     *  - Sólo se consideran inscripciones con estado = 'aprobada'.
     *  - Si se envía ?curso_id=, debe existir una inscripción APROBADA del alumno
     *    para ese curso. Un curso con inscripción rechazada/pendiente (o sin
     *    inscripción) no produce boleta.
     *  - Un sólo curso aprobado → se asume automáticamente (sin selector).
     *  - Varios aprobados y sin curso_id → null (selector) para que elija.
     *  - Ninguno aprobado → null.
     *
     * @return array{0: \App\Models\Curso|null, 1: list<\App\Models\Curso>, 2: bool}
     *         [cursoResuelto, cursosAprobados, selectorActivo]
     */
    private function resolverCurso(Alumno $alumno, ?int $cursoId): array
    {
        $inscripcionesAprobadas = $alumno->inscripciones->where('estado', 'aprobada');

        $cursosAprobados = $inscripcionesAprobadas
            ->map(fn (Inscripcion $i) => $i->curso)
            ->filter()
            ->values();

        if ($cursoId !== null) {
            $curso = $cursosAprobados->firstWhere('id', $cursoId);

            return [$curso, $cursosAprobados, false];
        }

        if ($cursosAprobados->count() === 1) {
            return [$cursosAprobados->first(), $cursosAprobados, false];
        }

        // 0 o >1 inscripciones aprobadas sin curso_id → selector (o vacío).
        return [null, $cursosAprobados, true];
    }

    /**
     * Notas del alumno pertenecientes exclusivamente al curso indicado.
     * Se filtra por detalleCurso.curso_id (NO por nombre de materia).
     */
    private function notasDelCurso(Alumno $alumno, int $cursoId)
    {
        return $alumno->notas
            ->filter(fn ($n) => $n->detalleCurso?->curso_id === $cursoId)
            ->values();
    }

    public function show($alumno_id)
    {
        $alumno = Alumno::with([
            'inscripciones' => fn ($q) => $q->aprobada(),
            'inscripciones.curso',
            'notas.detalleCurso.curso',
            'notas.detalleCurso.materia',
            'notas.detalleCurso.maestro',
            'notas.maestro',
        ])->findOrFail($alumno_id);

        [$curso, $cursosAprobados, $selectorActivo] = $this->resolverCurso(
            $alumno,
            request()->query('curso_id')
        );

        // Si se solicitó explícitamente un curso que no corresponde a una
        // inscripción APROBADA, no se produce boleta (404), en lugar de caer
        // en el selector con un contexto inconsistente.
        if (request()->filled('curso_id') && $curso === null) {
            abort(404, 'Curso no aprobado para este alumno.');
        }

        if ($selectorActivo) {
            return view('admin.boleta-detalle', compact('alumno', 'cursosAprobados', 'curso'));
        }

        $notas = $curso ? $this->notasDelCurso($alumno, $curso->id) : collect();
        $promedio_general = $notas->whereNotNull('promedio')->avg('promedio');

        return view('admin.boleta-detalle', compact(
            'alumno', 'curso', 'notas', 'promedio_general'
        ));
    }

    public function generarPdf($alumno_id)
    {
        $alumno = Alumno::with([
            'inscripciones' => fn ($q) => $q->aprobada(),
            'inscripciones.curso',
            'notas.detalleCurso.curso',
            'notas.detalleCurso.materia',
            'notas.detalleCurso.maestro',
        ])->findOrFail($alumno_id);

        [$curso, $cursosAprobados, $selectorActivo] = $this->resolverCurso(
            $alumno,
            request()->query('curso_id')
        );

        // Una boleta PDF representa un curso académico concreto.
        abort_if(! $curso, 404, 'Debe seleccionar un curso para generar la boleta.');

        $notas = $this->notasDelCurso($alumno, $curso->id);
        $promedio_general = $notas->whereNotNull('promedio')->avg('promedio');

        $concepto = function ($p) {
            if (! $p) return '';
            if ($p >= 9) return 'E';
            if ($p >= 8) return 'MB';
            if ($p >= 7) return 'B';
            if ($p >= 6) return 'R';
            return 'D';
        };

        $pdf = Pdf::loadView('admin.pdf.boleta', compact(
            'alumno', 'curso', 'notas', 'promedio_general', 'concepto'
        ))->setPaper('a4', 'portrait');

        return $pdf->download('boleta-' . $alumno->codigo . '-' . now()->year . '.pdf');
    }
}
