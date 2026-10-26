<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Docente\Concerns\ResuelveMateriasDelDocente;
use App\Models\Asistencia;
use App\Models\DetalleCurso;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AsistenciaController extends Controller
{
    use ResuelveMateriasDelDocente;

    /**
     * REGLA: la unidad académica es Curso + Materia + Maestro.
     *
     * Un curso NO pertenece a un maestro: cada materia del curso puede
     * impartirla alguien distinto. Por eso todo el flujo se apoya en
     * asignaciones_docentes (estado = activa) y en detalle_curso.
     */

    public function index(Request $request)
    {
        $maestro = $this->maestroAutenticado();

        // Materias del maestro, agrupadas por curso. 1 consulta + eager loading.
        $detalles = $this->detallesAsignados($maestro);
        $filtrosCursos = app(\App\Services\CursosConsulta::class)->normalizar($request->validate([
            'incluir_historicos' => 'nullable|boolean', 'curso_id' => 'nullable|integer|exists:cursos,id',
        ]));
        $idsOpciones = app(\App\Services\CursosConsulta::class)->opciones($filtrosCursos)->pluck('id');

        $cursos = $detalles
            ->pluck('curso')
            ->filter(fn ($c) => $idsOpciones->contains($c->id))
            ->unique('id')
            ->sortBy(fn ($c) => $c->anio_lectivo . ' ' . $c->seccion)
            ->values();

        $cursoId = $request->input('curso_id');
        $detalleCursoId = $request->input('detalle_curso_id');
        $fecha = $request->input('fecha') ?: today()->toDateString();

        $curso = null;
        $materias = collect();
        $detalle = null;
        $alumnos = collect();
        $asistencias = collect();

        if ($cursoId) {
            $materias = $detalles
                ->where('curso_id', (int) $cursoId)
                ->sortBy(fn ($d) => $d->materia->nombre)
                ->values();

            abort_if($materias->isEmpty(), 404);

            $curso = $materias->first()->curso;

            if ($detalleCursoId) {
                $detalle = $materias->firstWhere('id', (int) $detalleCursoId);

                abort_if($detalle === null, 403, 'No tiene esa materia asignada actualmente.');

                $alumnos = $this->alumnosDeLaMateria($detalle);

                $asistencias = Asistencia::where('detalle_curso_id', $detalle->id)
                    ->whereDate('fecha', $fecha)
                    ->get()
                    ->keyBy('alumno_id');
            }
        }

        return view('docente.asistencia', compact(
            'maestro', 'cursos', 'curso', 'materias', 'detalle',
            'cursoId', 'detalleCursoId', 'fecha', 'alumnos', 'asistencias', 'filtrosCursos'
        ));
    }

    public function guardar(Request $request)
    {
        $maestro = $this->maestroAutenticado();

        $request->validate([
            'detalle_curso_id' => 'required|integer|exists:detalle_cursos,id',
            'fecha' => 'required|date',
            'asistencias' => 'required|array',
            'asistencias.*.estado' => 'required|in:presente,ausente,permiso',
            'asistencias.*.observacion' => 'nullable|string|max:500',
        ]);

        // Autorización real en el servidor: la materia debe estar en una
        // asignación VIGENTE del maestro autenticado.
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

        $fechaCarbon = Carbon::parse($request->fecha)->startOfDay();
        $fecha = $fechaCarbon->toDateString();

        // Alumnos legítimos de ESA materia: no basta con que estén en el curso.
        $alumnosPermitidos = $this->alumnosDeLaMateria($detalle)->pluck('id');

        // Se valida el lote COMPLETO antes de guardar nada: un alumno
        // manipulado no debe dejar la asistencia a medias.
        $idsEnviados = collect(array_keys($request->asistencias))->map(fn ($id) => (int) $id);

        $invalidos = $idsEnviados->diff($alumnosPermitidos);

        abort_if(
            $invalidos->isNotEmpty(),
            403,
            'Uno de los alumnos no corresponde a la materia seleccionada.'
        );

        $guardadas = 0;

        foreach ($request->asistencias as $alumnoId => $datos) {
            Asistencia::updateOrCreate(
                [
                    'detalle_curso_id' => $detalle->id,
                    'alumno_id' => (int) $alumnoId,
                    'fecha' => $fechaCarbon,
                ],
                [
                    'curso_id' => $detalle->curso_id,
                    'estado' => $datos['estado'] ?? 'presente',
                    'observacion' => trim($datos['observacion'] ?? '') ?: null,
                    'registrado_por' => $maestro->id,
                ]
            );

            $guardadas++;
        }

        return redirect()
            ->route('docente.asistencia', [
                'curso_id' => $detalle->curso_id,
                'detalle_curso_id' => $detalle->id,
                'fecha' => $fecha,
            ])
            ->with('success', "Asistencia guardada correctamente ({$guardadas} alumnos).");
    }

    public function reporte(Request $request)
    {
        $maestro = $this->maestroAutenticado();

        $request->validate([
            'detalle_curso_id' => 'required|integer|exists:detalle_cursos,id',
            'fecha' => 'required|date',
        ]);

        $detalle = DetalleCurso::with(['curso', 'materia'])
            ->where('id', $request->detalle_curso_id)
            ->firstOrFail();

        abort_unless(
            $detalle->curso->activo && $detalle->esMateriaDelMaestro($maestro->id),
            403,
            'No tiene una asignación vigente para esa materia.'
        );

        $curso = $detalle->curso;

        $alumnos = $this->alumnosDeLaMateria($detalle);

        $asistencias = Asistencia::where('detalle_curso_id', $detalle->id)
            ->whereDate('fecha', $request->fecha)
            ->get()
            ->keyBy('alumno_id');

        $fecha = $request->fecha;
        $fechaFormateada = Carbon::parse($fecha)->isoFormat('D [de] MMMM YYYY');

        return view('docente.reporte-asistencia', compact(
            'maestro', 'curso', 'detalle', 'alumnos', 'asistencias', 'fecha', 'fechaFormateada'
        ));
    }
}
