<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CambiarMaestroAsignacionRequest;
use App\Http\Requests\Admin\StoreMaestroRequest;
use App\Http\Requests\Admin\UpdateMaestroRequest;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MaestroController extends Controller
{
    public function index()
    {
        $maestros = Maestro::with('detalleCursos.materia', 'detalleCursos.curso', 'user')->get();

        return view('admin.maestros', compact('maestros'));
    }

    public function show($id)
    {
        $maestro = Maestro::with([
            'detalleCursos' => function ($q) {
                $q->orderBy('curso_id')->orderBy('materia_id')
                    ->withCount('notas')
                    ->with([
                        'asignacionesDocentes' => function ($q2) {
                            $q2->orderBy('fecha_inicio', 'desc')->orderBy('id', 'desc')
                                ->with(['maestro', 'asignadoPor']);
                        },
                    ]);
            },
            'detalleCursos.curso',
            'detalleCursos.materia',
            'user',
        ])->findOrFail($id);

        $cursos = Curso::where('activo', 1)->orderBy('nombre')->orderBy('nivel')->orderBy('seccion')->get();

        $maestrosDisponibles = Maestro::where('activo', true)
            ->where('id', '!=', $maestro->id)
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->get();

        return view('admin.maestro-detalle', compact('maestro', 'cursos', 'maestrosDisponibles'));
    }

    private function generarCodigoMaestro(string $nombre, string $apellido): string
    {
        $limpiar = function (string $texto): string {
            $texto = trim($texto);
            $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
            $texto = preg_replace('/[^a-zA-Z\s]/', '', $texto);

            return $texto;
        };

        $nombreLimpio = $limpiar($nombre);
        $apellidoLimpio = $limpiar($apellido);

        $palabrasNombre = array_values(array_filter(explode(' ', $nombreLimpio)));
        $palabrasApellido = array_values(array_filter(explode(' ', $apellidoLimpio)));

        $iniciales = '';
        foreach (array_slice($palabrasNombre, 0, 2) as $p) {
            $iniciales .= strtoupper($p[0] ?? '');
        }
        foreach (array_slice($palabrasApellido, 0, 2) as $p) {
            $iniciales .= strtoupper($p[0] ?? '');
        }

        $max = Maestro::where('codigo', 'like', $iniciales.'%')->max('codigo');
        $numero = 1;
        if ($max) {
            $numStr = preg_replace('/[^0-9]/', '', $max);
            $numero = (int) $numStr + 1;
        }

        return $iniciales.str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
    }

    public function siguienteCodigoMaestro(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
        ]);

        $codigo = $this->generarCodigoMaestro($request->nombre, $request->apellido);

        return response()->json([
            'codigo' => $codigo,
            'email' => strtolower($codigo).'@itm.edu.sv',
        ]);
    }

    public function store(StoreMaestroRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $codigo = $this->generarCodigoMaestro($validated['nombre'], $validated['apellido']);

            $user = User::create([
                'name' => $validated['nombre'].' '.$validated['apellido'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'docente',
            ]);

            Maestro::create([
                'user_id' => $user->id,
                'nombre' => $validated['nombre'],
                'apellido' => $validated['apellido'],
                'telefono' => $validated['telefono'],
                'dui' => $validated['dui'],
                'codigo' => $codigo,
                'activo' => true,
            ]);
        });

        return redirect()->route('admin.maestros')->with('success', 'Maestro registrado correctamente.');
    }

    public function asignar(Request $request, $id)
    {
        $request->validate([
            'curso_id' => ['required', 'integer', Rule::exists('cursos', 'id')->where('activo', 1)],
            'materia_ids' => ['required', 'array', 'min:1'],
            'materia_ids.*' => ['required', 'integer', Rule::exists('materias', 'id')->where('activa', 1)],
        ]);

        $asignados = 0;
        $earlyReturn = false;

        DB::transaction(function () use ($request, $id, &$asignados, &$earlyReturn) {
            $curso = Curso::where('id', $request->curso_id)
                ->where('activo', 1)
                ->lockForUpdate()
                ->firstOrFail();

            $maestro = Maestro::where('id', $id)
                ->where('activo', true)
                ->lockForUpdate()
                ->firstOrFail();

            $materias = Materia::whereIn('id', $request->materia_ids)
                ->where('activa', 1)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($materias->count() !== count($request->materia_ids)) {
                throw ValidationException::withMessages([
                    'materia_ids' => 'Una o más materias seleccionadas no existen o están inactivas.',
                ]);
            }

            $detalles = DetalleCurso::where('curso_id', $request->curso_id)
                ->whereIn('materia_id', $request->materia_ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('materia_id');

            foreach ($request->materia_ids as $materiaId) {
                $materia = $materias[$materiaId];
                $detalle = $detalles->get($materiaId);

                // Verificar si la materia pertenece al curso
                if ($detalle) {
                    // La materia ya existe en el curso
                    if ($detalle->maestro_id !== null && $detalle->maestro_id != $maestro->id) {
                        throw ValidationException::withMessages([
                            'materia_ids' => "El programa {$materia->nombre} ya está asignado a otro maestro. Utilice el flujo de cambio de maestro.",
                        ]);
                    }

                    $asignacionesActivas = AsignacionDocente::where('detalle_curso_id', $detalle->id)
                        ->where('estado', 'activa')
                        ->lockForUpdate()
                        ->get();

                    if ($asignacionesActivas->count() > 1) {
                        throw ValidationException::withMessages([
                            'materia_ids' => "El programa {$materia->nombre} tiene múltiples asignaciones activas. Contacte al administrador.",
                        ]);
                    }

                    if ($asignacionesActivas->count() === 1) {
                        $asignacionActiva = $asignacionesActivas->first();
                        if ($asignacionActiva->maestro_id != $maestro->id) {
                            throw ValidationException::withMessages([
                                'materia_ids' => "El programa {$materia->nombre} ya está asignado a otro maestro. Utilice el flujo de cambio de maestro.",
                            ]);
                        }
                    }

                    if ($asignacionesActivas->isEmpty()) {
                        AsignacionDocente::create([
                            'detalle_curso_id' => $detalle->id,
                            'maestro_id' => $maestro->id,
                            'estado' => 'activa',
                            'asignado_por' => auth()->id(),
                        ]);

                        $detalle->update(['maestro_id' => $maestro->id]);
                        $asignados++;
                    }
                } else {
                    // La materia no existe en el curso, crearla
                    // Verificar que la materia pertenece al curso (ya está en DetalleCurso con unique constraint)
                    // Pero también verificar que no hay asignación activa para esta materia en este curso
                    $detalle = DetalleCurso::create([
                        'curso_id' => $request->curso_id,
                        'materia_id' => $materiaId,
                        'maestro_id' => $maestro->id,
                    ]);

                    AsignacionDocente::create([
                        'detalle_curso_id' => $detalle->id,
                        'maestro_id' => $maestro->id,
                        'estado' => 'activa',
                        'asignado_por' => auth()->id(),
                    ]);
                    $asignados++;
                }
            }

            if ($asignados === 0) {
                $earlyReturn = true;
                return redirect()->route('admin.maestros.show', $id)
                    ->with('success', 'Todos los programas seleccionados ya estaban asignados a este maestro.');
            }
        });

        if ($earlyReturn) {
            return redirect()->route('admin.maestros.show', $id)
                ->with('success', 'Todos los programas seleccionados ya estaban asignados a este maestro.');
        }

        $mensaje = $asignados === 1
            ? 'Programa asignado correctamente al maestro.'
            : "{$asignados} programas asignados correctamente al maestro.";

        return redirect()->route('admin.maestros.show', $id)->with('success', $mensaje);
    }

    public function materiasPorCurso(Request $request, $curso_id)
    {
        $maestroId = $request->query('maestro_id');

        $curso = Curso::where('id', $curso_id)
            ->where('activo', 1)
            ->firstOrFail();

        // Obtener las materias que pertenecen a este curso (a través de detalle_cursos)
        $detalles = DetalleCurso::where('curso_id', $curso_id)
            ->with(['materia', 'asignacionesDocentes' => function ($q) {
                $q->where('estado', 'activa')->with('maestro');
            }])
            ->get();

        $materias = [];

        foreach ($detalles as $detalle) {
            $materia = $detalle->materia;
            if (!$materia || !$materia->activa) continue;

            $asignacionesActivas = $detalle->asignacionesDocentes->where('estado', 'activa');
            $tieneAsignacionActiva = $asignacionesActivas->isNotEmpty();
            $maestroActual = $tieneAsignacionActiva ? $asignacionesActivas->first()->maestro : null;

            // Verificar si ya está asignada al maestro que estamos viendo
            $yaAsignadaAesteMaestro = $maestroActual && $maestroId && $maestroActual->id == $maestroId;

            // Verificar si tiene historial docente (asignaciones finalizadas)
            $tieneHistorial = $detalle->asignacionesDocentes->where('estado', 'finalizada')->isNotEmpty();

            // Si tiene maestro actual y no es el maestro que estamos viendo, no se puede asignar
            $tieneMaestroActual = $tieneAsignacionActiva && !$yaAsignadaAesteMaestro;

            $materias[] = [
                'id' => $materia->id,
                'nombre' => $materia->nombre,
                'codigo' => $materia->codigo,
                'tiene_maestro_actual' => $tieneMaestroActual,
                'maestro_actual' => $maestroActual ? $maestroActual->nombre_completo : null,
                'ya_asignada_a_este_maestro' => $yaAsignadaAesteMaestro,
                'tiene_historial' => $tieneHistorial && !$yaAsignadaAesteMaestro,
            ];
        }

        return response()->json(['materias' => $materias]);
    }

    public function desasignar(Request $request, $maestro_id, $detalle_id)
    {
        return DB::transaction(function () use ($request, $maestro_id, $detalle_id) {
            $detalle = DetalleCurso::where('id', $detalle_id)
                ->where('maestro_id', $maestro_id)
                ->lockForUpdate()
                ->firstOrFail();

            $asignacionesActivas = AsignacionDocente::where('detalle_curso_id', $detalle_id)
                ->where('estado', 'activa')
                ->lockForUpdate()
                ->get();

            if ($asignacionesActivas->isEmpty()) {
                return redirect()->route('admin.maestros.show', $maestro_id)
                    ->with('error', 'No se puede desasignar el programa porque no tiene una asignación activa.');
            }

            if ($asignacionesActivas->count() > 1) {
                return redirect()->route('admin.maestros.show', $maestro_id)
                    ->with('error', 'Este programa tiene múltiples asignaciones activas. Contacte al administrador.');
            }

            $asignacionActiva = $asignacionesActivas->first();

            if ($asignacionActiva->maestro_id != $maestro_id) {
                return redirect()->route('admin.maestros.show', $maestro_id)
                    ->with('error', 'La asignación activa no corresponde al maestro indicado.');
            }

            $motivo = $request->input('motivo', 'Desasignación administrativa');

            $asignacionActiva->update([
                'estado' => 'finalizada',
                'fecha_fin' => today(),
                'motivo_cambio' => $motivo,
            ]);

            $detalle->update(['maestro_id' => null]);

            return redirect()->route('admin.maestros.show', $maestro_id)
                ->with('success', 'Asignación finalizada correctamente. El maestro fue desasignado del programa.');
        });
    }

    public function cambiarMaestro(
        CambiarMaestroAsignacionRequest $request,
        $maestro_id,
        $detalle_id
    ) {
        $datos = $request->validated();
        $nuevoMaestroId = (int) $datos['nuevo_maestro_id'];
        $motivoCambio = $datos['motivo_cambio'];
        $adminId = auth()->id();
        $fechaEfectiva = today()->toImmutable();

        if ($fechaEfectiva->isFuture()) {
            throw ValidationException::withMessages([
                'nuevo_maestro_id' => 'La fecha efectiva no puede ser futura.',
            ]);
        }

        $detalleInicial = DetalleCurso::where('id', $detalle_id)
            ->where('maestro_id', $maestro_id)
            ->firstOrFail();

        $cursoId = $detalleInicial->curso_id;
        $materiaId = $detalleInicial->materia_id;

        DB::transaction(function () use ($nuevoMaestroId, $motivoCambio, $adminId, $fechaEfectiva, $maestro_id, $detalle_id, $cursoId, $materiaId) {
            Curso::where('id', $cursoId)
                ->where('activo', 1)
                ->lockForUpdate()
                ->firstOrFail();

            Materia::where('id', $materiaId)
                ->where('activa', 1)
                ->lockForUpdate()
                ->firstOrFail();

            $nuevoMaestro = Maestro::where('id', $nuevoMaestroId)
                ->where('activo', true)
                ->lockForUpdate()
                ->firstOrFail();

            $detalle = DetalleCurso::where('id', $detalle_id)
                ->where('maestro_id', $maestro_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($detalle->curso_id != $cursoId || $detalle->materia_id != $materiaId) {
                throw ValidationException::withMessages([
                    'nuevo_maestro_id' => 'Los datos del programa han cambiado. Intente nuevamente.',
                ]);
            }

            if ($nuevoMaestro->id == $maestro_id) {
                throw ValidationException::withMessages([
                    'nuevo_maestro_id' => 'El maestro seleccionado ya es el maestro vigente de este programa.',
                ]);
            }

            $asignacionesActivas = AsignacionDocente::where('detalle_curso_id', $detalle_id)
                ->where('estado', 'activa')
                ->lockForUpdate()
                ->get();

            if ($asignacionesActivas->count() === 0) {
                throw ValidationException::withMessages([
                    'nuevo_maestro_id' => 'No se puede cambiar el maestro porque no existe una asignación activa para este programa.',
                ]);
            }

            if ($asignacionesActivas->count() > 1) {
                throw ValidationException::withMessages([
                    'nuevo_maestro_id' => 'Este programa tiene múltiples asignaciones activas. Contacte al administrador.',
                ]);
            }

            $asignacionActiva = $asignacionesActivas->first();

            if ($asignacionActiva->maestro_id != $detalle->maestro_id ||
                $asignacionActiva->maestro_id != $maestro_id) {
                throw ValidationException::withMessages([
                    'nuevo_maestro_id' => 'La asignación activa no coincide con el maestro vigente. Contacte al administrador.',
                ]);
            }

            if (
                $asignacionActiva->fecha_inicio !== null
                && $asignacionActiva->fecha_inicio->greaterThanOrEqualTo($fechaEfectiva)
            ) {
                throw ValidationException::withMessages([
                    'nuevo_maestro_id' => 'Ya se realizó un cambio permanente de maestro para este programa hoy.',
                ]);
            }

        $fechaFinAnterior = $fechaEfectiva->copy()->subDay();

        $asignacionActiva->update([
            'estado' => 'finalizada',
            'fecha_fin' => $fechaFinAnterior,
            'motivo_cambio' => $motivoCambio,
        ]);

        AsignacionDocente::create([
            'detalle_curso_id' => $detalle_id,
            'maestro_id' => $nuevoMaestroId,
            'fecha_inicio' => $fechaEfectiva,
            'fecha_fin' => null,
            'estado' => 'activa',
            'motivo_cambio' => null,
            'asignado_por' => $adminId,
        ]);

        $detalle->update(['maestro_id' => $nuevoMaestroId]);
        }, 3);

        return redirect()->route('admin.maestros.show', $nuevoMaestroId)
            ->with('success', 'El maestro del programa fue cambiado correctamente.');
    }

    public function update(UpdateMaestroRequest $request, $id)
    {
        $maestro = Maestro::findOrFail($id);
        $user = $maestro->user;
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $user, $maestro, $request) {
            $user->update([
                'name' => $validated['nombre'].' '.$validated['apellido'],
                'email' => $validated['email'],
            ]);

            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($validated['password'])]);
            }

            $maestro->update([
                'nombre' => $validated['nombre'],
                'apellido' => $validated['apellido'],
                'telefono' => $validated['telefono'],
                'dui' => $validated['dui'],
            ]);
        });

        return redirect()->route('admin.maestros')->with('success', 'Maestro actualizado correctamente.');
    }
}
