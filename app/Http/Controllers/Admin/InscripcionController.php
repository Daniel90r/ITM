<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inscripcion;
use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Materia;
use App\Models\DetalleCurso;
use App\Services\EncargadoInscripcion;
use App\Services\ReactivarInscripcion;
use App\Services\ConsultaSolicitudesInscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InscripcionController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate([
            'incluir_historicos' => ['nullable', 'boolean'],
            'estado_alumno' => ['nullable', 'in:todos,activos,inactivos'],
            'curso_id' => ['nullable', 'integer', 'exists:cursos,id'],
            'q' => ['nullable', 'string', 'max:150'],
        ]);
        $filtros = app(\App\Services\CursosConsulta::class)->normalizar($filtros);
        $estadoAlumno = $filtros['estado_alumno'] ?? 'todos';
        $busqueda = trim($filtros['q'] ?? '');
        // "Alumnos inscritos": únicamente inscripciones APROBADAS.
        $inscripciones = Inscripcion::with(['alumno', 'curso'])
            ->aprobada()
            ->whereIn('curso_id', app(\App\Services\CursosConsulta::class)->opciones($filtros)->pluck('id'))
            ->when($filtros['curso_id'] ?? null, fn ($q, $id) => $q->where('curso_id', $id))
            ->whereHas('alumno', function ($q) use ($estadoAlumno, $busqueda) {
                if ($estadoAlumno !== 'todos') $q->where('activo', $estadoAlumno === 'activos');
                if ($busqueda !== '') {
                    $q->where(function ($q) use ($busqueda) {
                        $q->where('nombre', 'like', '%'.$busqueda.'%')
                            ->orWhere('apellido', 'like', '%'.$busqueda.'%')
                            ->orWhere('codigo', 'like', '%'.$busqueda.'%');
                    });
                }
            })
            ->orderBy('fecha_inscripcion', 'desc')
            ->orderByDesc('id')
            ->paginate(10)->withQueryString();
        $cursos = app(\App\Services\CursosConsulta::class)->opciones($filtros);
        $revisiones = \App\Models\CambioEstadoPersona::query()
            ->whereIn('alumno_id', $inscripciones->pluck('alumno_id'))
            ->selectRaw('alumno_id, MAX(id) AS revision')->groupBy('alumno_id')->pluck('revision', 'alumno_id');
        foreach ($inscripciones as $inscripcion) {
            $inscripcion->revision_estado = (int) ($revisiones[$inscripcion->alumno_id] ?? 0);
        }

        return view('admin.inscripciones', compact('inscripciones', 'cursos', 'estadoAlumno', 'busqueda', 'filtros'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'alumno_id' => 'required|exists:alumnos,id',
            'curso_id' => 'required|exists:cursos,id',
        ]);

        // Regla: un alumno solo puede tener una solicitud pendiente o una inscripción aprobada.
        if (Inscripcion::bloqueaNuevaSolicitud($request->alumno_id)) {
            return back()->with('error', 'El alumno ya tiene una solicitud pendiente o una inscripción aprobada.');
        }

        try {
            DB::transaction(function () use ($request) {
                // Compartir el bloqueo por alumno con solicitudes, aprobación y reactivación.
                Alumno::lockForUpdate()->findOrFail($request->alumno_id);
                if (Inscripcion::bloqueaNuevaSolicitud((int) $request->alumno_id)) {
                    throw ValidationException::withMessages(['alumno_id' => 'El alumno ya tiene una solicitud pendiente o una inscripción aprobada.']);
                }
                $curso = Curso::query()
                    ->lockForUpdate()
                    ->findOrFail($request->curso_id);

                if ($curso->estado_curso === 'FINALIZADO') {
                    throw ValidationException::withMessages([
                        'curso_id' => 'No se puede inscribir en un curso finalizado.',
                    ]);
                }

                $inscritosActuales = Inscripcion::query()
                    ->where('curso_id', $curso->id)
                    ->where('estado', 'aprobada')
                    ->lockForUpdate()->get(['id'])->count();

                if ($inscritosActuales >= Curso::CUPOS_MAXIMO) {
                    throw ValidationException::withMessages([
                        'curso_id' => 'El curso ya alcanzó el límite de 20 alumnos.',
                    ]);
                }

                Inscripcion::create([
                    'alumno_id' => $request->alumno_id,
                    'curso_id' => $request->curso_id,
                    'fecha_inscripcion' => now()->toDateString(),
                    'activa' => 1,
                    'estado' => 'aprobada',
                ]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.inscripciones')->with('success', 'Alumno inscrito correctamente.');
    }

    public function desactivar($id)
    {
        Inscripcion::findOrFail($id)->update(['activa' => 0, 'estado' => 'inactiva']);
        return redirect()->route('admin.inscripciones')->with('success', 'Inscripción desactivada correctamente.');
    }

    public function solicitudes(Request $request, ConsultaSolicitudesInscripcion $solicitudes)
    {
        $validated = $request->validate(ConsultaSolicitudesInscripcion::REGLAS);
        $estadoFiltro = $validated['estado'] ?? 'todos';
        $busqueda = trim($validated['q'] ?? '');
        // Los contadores son globales y proceden de la misma tabla que el listado.
        $porEstado = Inscripcion::selectRaw('estado, COUNT(*) AS cantidad')->groupBy('estado')->pluck('cantidad', 'estado');
        $conteos = [
            'pendientes' => (int) $porEstado->get('pendiente', 0),
            'aprobadas' => (int) $porEstado->get('aprobada', 0),
            'rechazadas' => (int) $porEstado->get('rechazada', 0),
            'inactivas' => (int) $porEstado->get('inactiva', 0),
            'total' => (int) $porEstado->sum(),
        ];
        $conteos['otros'] = $conteos['total'] - $conteos['pendientes'] - $conteos['aprobadas'] - $conteos['rechazadas'] - $conteos['inactivas'];

        $reactivacionDisponible = Schema::hasTable('reactivaciones_inscripcion');
        $inscripciones = $solicitudes->consulta($validated)->paginate(10)->withQueryString();

        return view('admin.solicitudes', compact('inscripciones', 'conteos', 'estadoFiltro', 'busqueda', 'reactivacionDisponible'));
    }

    public function pendientesCount()
    {
        return response()->json([
            'count' => Inscripcion::pendiente()->count(),
        ]);
    }

    public function aprobar($id)
    {
        $inscripcion = Inscripcion::findOrFail($id);

        if ($inscripcion->estado !== 'pendiente') {
            return back()->with('error', 'Solo se pueden aprobar solicitudes pendientes.');
        }

        try {
            DB::transaction(function () use ($inscripcion) {
                // Mismo orden de bloqueos que el portal y la reactivación.
                $alumno = Alumno::lockForUpdate()->findOrFail($inscripcion->alumno_id);
                // También para solicitudes antiguas: validar el expediente actual.
                // El JSON y el PDF históricos se conservan, pero no autorizan crear,
                // vincular ni modificar encargados. Si falta el vínculo, Administración
                // debe regularizarlo antes de aprobar.
                app(EncargadoInscripcion::class)->exigir($alumno);

                $curso = Curso::query()
                    ->lockForUpdate()
                    ->findOrFail($inscripcion->curso_id);

                $inscripcion = Inscripcion::lockForUpdate()->findOrFail($inscripcion->id);
                if ($inscripcion->estado !== 'pendiente') {
                    throw ValidationException::withMessages(['inscripcion' => 'Solo se pueden aprobar solicitudes pendientes.']);
                }
                if (Inscripcion::where('alumno_id', $alumno->id)->where('id', '!=', $inscripcion->id)
                    ->whereIn('estado', ['pendiente', 'aprobada'])->exists()) {
                    throw ValidationException::withMessages(['inscripcion' => 'El alumno ya tiene otra inscripción pendiente o aprobada.']);
                }

                $inscritosActuales = Inscripcion::query()
                    ->where('curso_id', $curso->id)
                    ->where('estado', 'aprobada')
                    ->where('id', '!=', $inscripcion->id)
                    ->lockForUpdate()->get(['id'])->count();

                if ($inscritosActuales >= Curso::CUPOS_MAXIMO) {
                    throw ValidationException::withMessages([
                        'curso' => "El curso {$curso->nombre} ya alcanzó el límite de 20 alumnos.",
                    ]);
                }

                if ($inscripcion->tomara_ingles) {
                    $ingles = Materia::where('activa', true)
                        ->where(function ($q) {
                            $q->where('nombre', 'like', '%Ingles%')
                              ->orWhere('nombre', 'like', '%Inglés%')
                              ->orWhere('codigo', 'like', '%ING%');
                        })
                        ->first();

                    if (!$ingles) {
                        throw ValidationException::withMessages([
                            'tomara_ingles' => 'La materia de Inglés no existe o está inactiva. No se puede aprobar la solicitud.',
                        ]);
                    }

                    DetalleCurso::firstOrCreate(
                        ['curso_id' => $curso->id, 'materia_id' => $ingles->id],
                        ['maestro_id' => null]
                    );

                    DB::table('alumno_materia')->upsert(
                        [
                            'alumno_id' => $inscripcion->alumno_id,
                            'curso_id' => $curso->id,
                            'materia_id' => $ingles->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        ['alumno_id', 'curso_id', 'materia_id'],
                        ['updated_at']
                    );
                }



                $inscripcion->update(['estado' => 'aprobada', 'activa' => 1]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Solicitud aprobada correctamente.');
    }

    public function rechazar(Request $request, $id)
    {
        $request->validate([
            'motivo_rechazo' => 'required|string|max:500',
        ], [
            'motivo_rechazo.required' => 'Debe indicar el motivo del rechazo.',
        ]);

        $inscripcion = Inscripcion::findOrFail($id);

        if ($inscripcion->estado !== 'pendiente') {
            return back()->with('error', 'Solo se pueden rechazar solicitudes pendientes.');
        }

        // Rechazada: no se asigna al curso, no cuenta como inscrito, se conserva como historial.
        $inscripcion->update([
            'estado' => 'rechazada',
            'activa' => 0,
            'motivo_rechazo' => $request->motivo_rechazo,
        ]);

        return back()->with('success', 'Solicitud rechazada correctamente.');
    }

    public function reabrir($id)
    {
        $inscripcion = Inscripcion::findOrFail($id);

        if ($inscripcion->estado !== 'rechazada') {
            return back()->with('error', 'Solo se pueden reabrir solicitudes rechazadas.');
        }

        return DB::transaction(function () use ($inscripcion) {
            Alumno::lockForUpdate()->findOrFail($inscripcion->alumno_id);
            Curso::lockForUpdate()->findOrFail($inscripcion->curso_id);
            $inscripcion = Inscripcion::lockForUpdate()->findOrFail($inscripcion->id);
            if ($inscripcion->estado !== 'rechazada') {
                return back()->with('error', 'Solo se pueden reabrir solicitudes rechazadas.');
            }
            // Antes de reabrir: sin otra aprobada y sin otra pendiente.
            if (Inscripcion::tieneAprobada($inscripcion->alumno_id)) {
                return back()->with('error', 'El alumno ya tiene una inscripción aprobada.');
            }

            if (Inscripcion::tienePendiente($inscripcion->alumno_id, $inscripcion->id)) {
                return back()->with('error', 'El alumno ya tiene una solicitud pendiente.');
            }

            // RECHAZADA → PENDIENTE (vuelve a revisión, sin asignar al curso aún).
            // Al reabrir se limpia el motivo del rechazo previo.
            $inscripcion->update(['estado' => 'pendiente', 'activa' => 0, 'motivo_rechazo' => null]);

            return back()->with('success', 'Solicitud reabierta correctamente.');
        });
    }

    public function reactivar(Request $request, int $id, ReactivarInscripcion $reactivar)
    {
        $validated = $request->validate(['motivo_reactivacion' => 'required|string|min:10|max:500']);
        try {
            $reactivar->ejecutar($id, $request->user(), $validated['motivo_reactivacion']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput($request->only('motivo_reactivacion', 'reactivacion_contexto'));
        }
        return back()->with('success', 'Inscripción reactivada correctamente. Se conservó su historial académico.');
    }
}
