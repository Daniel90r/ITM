<?php

namespace App\Services;

use App\Models\{Alumno, Curso, DetalleCurso};

/** Consulta de presentación: no modifica el expediente ni sus reglas académicas. */
class ResumenCursoAlumno
{
    public function consultar(Alumno $alumno): array
    {
        $avisos = [];
        $aprobadas = $alumno->inscripciones()->aprobada()->with('curso')->get();
        $vigentes = $aprobadas->filter(fn ($i) => (bool) $i->activa && $i->curso?->activo
            && $i->curso->estado_curso !== Curso::ESTADO_FINALIZADO)->values();
        if ($aprobadas->contains(fn ($i) => ! $i->activa || ! $i->curso)) {
            $avisos[] = 'Hay una inscripción aprobada con información inconsistente. Contacta a Administración.';
        }
        $resumen = ['inscripcion' => null, 'vigente' => false, 'materias' => collect(), 'avisos' => $avisos, 'horarios' => ['Horario no configurado']];
        if ($vigentes->count() > 1) {
            $resumen['avisos'][] = 'Hay varias inscripciones aprobadas vigentes. Administración debe confirmar cuál corresponde; no se han combinado sus datos.';
            return $resumen;
        }
        $inscripcion = $vigentes->first();
        if (! $inscripcion) {
            $pendientes = $alumno->inscripciones()->pendiente()->with('curso')->limit(2)->get();
            if ($pendientes->count() > 1) {
                $resumen['avisos'][] = 'Hay varias solicitudes pendientes. Contacta a Administración para revisar tu expediente.';
                return $resumen;
            }
            $resumen['inscripcion'] = $pendientes->first()
                ?? $alumno->inscripciones()->with('curso')->orderByDesc('fecha_inscripcion')->orderByDesc('id')->first();
            return $resumen;
        }

        $resumen['inscripcion'] = $inscripcion;
        $resumen['vigente'] = true;
        $resumen['horarios'] = app(HorariosCurso::class)->lineas($inscripcion->curso);
        if ($alumno->inscripciones()->pendiente()->exists()) {
            $resumen['avisos'][] = 'Existe también una solicitud pendiente. Administración debe revisar el expediente.';
        }
        // Mismo criterio de pertenencia que las boletas y el portal docente:
        // catálogo activo; las opcionales requieren el pivote del alumno y de ESTE curso.
        $opcionales = $alumno->materiasOpcionalesEnCurso($inscripcion->curso_id)->pluck('materias.id');
        $detalles = DetalleCurso::where('curso_id', $inscripcion->curso_id)
            ->whereHas('materia', fn ($q) => $q->where('activa', true)
                ->where(fn ($q) => $q->where('es_opcional', false)->orWhereIn('id', $opcionales)))
            ->with(['materia', 'asignacionesDocentes' => fn ($q) => $q->activas()->with('maestro')])
            ->orderBy('materia_id')->orderBy('id')->get();
        $resumen['materias'] = $detalles->map(function ($detalle) use (&$resumen) {
            $asignacion = $detalle->asignacionesDocentes->count() === 1 ? $detalle->asignacionesDocentes->first() : null;
            $maestro = $asignacion?->maestro;
            $valida = $asignacion && $maestro?->activo
                && (int) $asignacion->maestro_id === (int) $detalle->maestro_id;
            if (! $valida && ($detalle->maestro_id || $detalle->asignacionesDocentes->isNotEmpty())) {
                $resumen['avisos'][] = 'Hay una asignación docente que requiere revisión de Administración; no se mostrará como vigente.';
            }
            return ['detalle_id' => $detalle->id, 'nombre' => $detalle->materia->nombre,
                'docente' => $valida ? $maestro->nombre_completo : null];
        });
        $resumen['avisos'] = array_values(array_unique($resumen['avisos']));
        return $resumen;
    }
}
