<?php

namespace App\Http\Controllers\Docente\Concerns;

use App\Models\DetalleCurso;
use App\Models\Inscripcion;
use App\Models\Maestro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Resolución de materias y alumnos para el panel del docente.
 *
 * Asistencia y Notas comparten EXACTAMENTE estas tres reglas, y las tres
 * sostienen la autorización:
 *
 *  - maestroAutenticado(): el usuario debe ser un maestro ACTIVO.
 *  - detallesAsignados(): la materia debe tener una asignación docente VIGENTE
 *    (asignaciones_docentes.estado = 'activa'). Un curso no pertenece a un
 *    maestro; cada materia puede impartirla alguien distinto.
 *  - alumnosDeLaMateria(): quién puede recibir asistencia o notas de ESA
 *    materia. Inscritos con inscripción aprobada/activa y, si la materia es
 *    opcional (materias.es_opcional), además asociados en alumno_materia
 *    para ese curso. Nunca se decide por nombre de materia.
 *
 * Se extrae aquí para que una corrección futura se aplique a las dos
 * pantallas a la vez: si asistencia y notas resolvieran alumnos de forma
 * distinta, un docente podría calificar a quien no puede asistir.
 */
trait ResuelveMateriasDelDocente
{
    /**
     * Maestro activo de la sesión. 404 si el usuario no es un maestro vigente.
     */
    protected function maestroAutenticado(): Maestro
    {
        return Maestro::where('user_id', Auth::id())
            ->where('activo', true)
            ->firstOrFail();
    }

    /**
     * Materias del maestro con asignación vigente, con curso y materia cargados.
     * Sin N+1: 1 consulta de asignaciones + 2 de eager loading.
     *
     * @return Collection<int, DetalleCurso>
     */
    protected function detallesAsignados(Maestro $maestro): Collection
    {
        return $maestro->asignacionesDocentes()
            ->detallesDelMaestro($maestro->id)
            ->with(['detalleCurso.curso', 'detalleCurso.materia'])
            ->get()
            ->pluck('detalleCurso')
            ->unique('id')
            ->sortBy(fn ($d) => [$d->curso->anio_lectivo, $d->curso->seccion, $d->materia->nombre])
            ->values();
    }

    /**
     * Alumnos que corresponden a ESA materia dentro del curso.
     *
     * - Materia normal: inscritos aprobados/activos en el curso.
     * - Materia opcional: además deben estar asociados en alumno_materia
     *   para ese curso (propiedad es_opcional, sin hardcodear nombres).
     *
     * @return Collection<int, \App\Models\Alumno>
     */
    protected function alumnosDeLaMateria(DetalleCurso $detalle): Collection
    {
        $inscripciones = Inscripcion::with('alumno')
            ->where('curso_id', $detalle->curso_id)
            ->aprobada()
            ->get();

        if ($detalle->materia->es_opcional) {
            $inscritosEnLaMateria = DB::table('alumno_materia')
                ->where('curso_id', $detalle->curso_id)
                ->where('materia_id', $detalle->materia_id)
                ->pluck('alumno_id');

            $inscripciones = $inscripciones
                ->filter(fn ($i) => $inscritosEnLaMateria->contains($i->alumno_id));
        }

        return $inscripciones
            ->map(fn ($i) => $i->alumno)
            ->filter()
            ->unique('id')
            ->sortBy(fn ($a) => $a->apellido . ' ' . $a->nombre)
            ->values();
    }
}
