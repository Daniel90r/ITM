<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Services\HorariosCurso;
use Illuminate\View\View;

/**
 * Portal público: oferta académica visible sin iniciar sesión (HU-18 / SCRUM-2).
 *
 * Solo muestra cursos visibles (activo = true) que no han finalizado, con sus
 * materias y horario. No expone datos personales de alumnos ni de docentes.
 */
class OfertaAcademicaController extends Controller
{
    public function __invoke(HorariosCurso $horarios): View
    {
        $cursos = Curso::query()
            ->where('activo', true)
            ->noFinalizado()
            ->with('detalleCursos.materia')
            ->orderBy('fecha_inicio')
            ->orderBy('nombre')
            ->get()
            ->map(function (Curso $curso) use ($horarios) {
                // Se ordena en PHP para no depender de la columna orden_academico,
                // que puede no existir aún en instalaciones sin la última migración.
                $curso->materias_publicas = $curso->detalleCursos
                    ->sortBy(fn ($d) => [$d->orden_academico ?? PHP_INT_MAX, $d->id])
                    ->pluck('materia')
                    ->filter(fn ($materia) => $materia && $materia->activa !== false)
                    ->values();
                $curso->horario_publico = $horarios->lineas($curso);
                $curso->inscripcion_abierta = $curso->admiteNuevaInscripcion();

                return $curso;
            });

        return view('publico.oferta', compact('cursos'));
    }
}
