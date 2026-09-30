<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inscripcion;
use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Materia;
use App\Models\DetalleCurso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InscripcionController extends Controller
{
    public function index()
    {
        // "Alumnos inscritos": únicamente inscripciones APROBADAS.
        $inscripciones = Inscripcion::with(['alumno', 'curso'])
            ->aprobada()
            ->orderBy('fecha_inscripcion', 'desc')
            ->paginate(10);

        return view('admin.inscripciones', compact('inscripciones'));
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
                    ->count();

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

    public function solicitudes()
    {
        $inscripciones = Inscripcion::with(['alumno', 'curso'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $conteos = [
            'pendientes' => Inscripcion::pendiente()->count(),
            'aprobadas'  => Inscripcion::aprobada()->count(),
            'rechazadas' => Inscripcion::rechazada()->count(),
        ];

        return view('admin.solicitudes', compact('inscripciones', 'conteos'));
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
                $curso = Curso::query()
                    ->lockForUpdate()
                    ->findOrFail($inscripcion->curso_id);

                $inscritosActuales = Inscripcion::query()
                    ->where('curso_id', $curso->id)
                    ->where('estado', 'aprobada')
                    ->where('id', '!=', $inscripcion->id)
                    ->count();

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
    }
}