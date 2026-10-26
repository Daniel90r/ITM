<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\Materia;
use App\Models\DetalleCurso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SeccionController extends Controller
{
    public function index()
    {
        $cursos = Curso::withCount(['inscripciones' => function ($query) {
            $query->aprobada();
        }])->orderBy('seccion')->get();
        return view('admin.cursos', compact('cursos'));
    }

    public function store(Request $request)
    {
        $horarios = app(\App\Services\HorariosCurso::class)->validar($request);
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'seccion' => 'required|string|max:2',
            'nivel' => 'required|string|in:Matutino,Vespertino',
            'anio_lectivo' => 'required|integer|min:2020|max:2099',

            'fecha_inicio' => [
                'required',
                'date',
            ],

            'fecha_fin' => [
                'required',
                'date',
                'after:fecha_inicio',
            ],
        ]);

        DB::transaction(function () use ($validated, $horarios) {
            $curso = Curso::create([
                'nombre' => $validated['nombre'],
                'seccion' => strtoupper($validated['seccion']),
                'nivel' => $validated['nivel'],
                'anio_lectivo' => $validated['anio_lectivo'],
                'fecha_inicio' => $validated['fecha_inicio'],
                'fecha_fin' => $validated['fecha_fin'],
                'activo' => true,
            ]);

            $materiasActivas = Materia::where('activa', true)->get();

            app(\App\Services\HorariosCurso::class)->guardar($curso, $horarios);

            foreach ($materiasActivas as $materia) {
                DetalleCurso::firstOrCreate(
                    ['curso_id' => $curso->id, 'materia_id' => $materia->id],
                    ['maestro_id' => null]
                );
            }
        });

        return redirect()->route('admin.cursos')->with('success', 'Curso registrado correctamente.');
    }

    public function show($id)
    {
        $curso = Curso::with([
            'inscripciones' => function ($query) {
                $query->aprobada()->with('alumno');
            },
        ])->findOrFail($id);

        // Obtener alumnos aprobados
        $inscritosAprobados = $curso->inscripciones->pluck('alumno');

        // Única fuente de verdad: las materias visibles del curso.
        // El contador y el listado de la vista usan esta misma colección.
        $detalleCursosVisibles = $curso->detalleCursos()
            ->visibles()
            ->with(['materia', 'maestro'])
            ->orderBy('id')
            ->get();

        // Cargar materias opcionales con sus alumnos inscritos usando eager loading
        $materiasOpcionalesPorId = $detalleCursosVisibles
            ->filter(fn ($detalle) => $detalle->materia && $detalle->materia->es_opcional)
            ->mapWithKeys(fn ($detalle) => [$detalle->materia_id => $detalle->materia->nombre]);

        $materiasOpcionalesIds = $materiasOpcionalesPorId->keys()->all();

        $alumnosPorMateriaOpcional = [];
        $materiasOpcionalesPorAlumno = collect();

        if (!empty($materiasOpcionalesIds)) {
            // Una sola consulta para obtener todos los alumnos de materias opcionales del curso
            $alumnosMaterias = DB::table('alumno_materia')
                ->where('curso_id', $curso->id)
                ->whereIn('materia_id', $materiasOpcionalesIds)
                ->join('alumnos', 'alumno_materia.alumno_id', '=', 'alumnos.id')
                ->select('alumnos.id', 'alumnos.nombre', 'alumnos.apellido', 'alumnos.codigo', 'alumno_materia.materia_id')
                ->get()
                ->groupBy('materia_id');

            foreach ($materiasOpcionalesIds as $materiaId) {
                $alumnosPorMateriaOpcional[$materiaId] = $alumnosMaterias->get($materiaId, collect());
            }

            // Nombres de las materias opcionales que realmente lleva cada alumno.
            // Se derivan de la misma consulta para que la vista no tenga que
            // consultar nada: nada de ids hardcodeados ni de N+1.
            $materiasOpcionalesPorAlumno = $alumnosMaterias
                ->flatten(1)
                ->groupBy('id')
                ->map(fn ($filas) => $filas
                    ->map(fn ($fila) => $materiasOpcionalesPorId[$fila->materia_id] ?? null)
                    ->filter()
                    ->unique()
                    ->values());
        }

        // Preparar detalleCursos con información de materias opcionales
        $detalleCursosConOpcional = $detalleCursosVisibles->map(function ($detalle) use ($inscritosAprobados, $alumnosPorMateriaOpcional) {
            if ($detalle->materia->es_opcional) {
                $detalle->alumnos_opcional = $alumnosPorMateriaOpcional[$detalle->materia_id] ?? collect();
                $detalle->total_alumnos_curso = $inscritosAprobados->count();
            }
            return $detalle;
        });

        return view('admin.curso-detalle', compact(
            'curso',
            'detalleCursosConOpcional',
            'detalleCursosVisibles',
            'materiasOpcionalesPorAlumno'
        ));
    }

    public function edit($id)
    {
        $curso = Curso::findOrFail($id);
        return view('admin.curso-editar', compact('curso'));
    }

    public function update(Request $request, $id)
    {
        $curso = Curso::findOrFail($id);
        $horarios = app(\App\Services\HorariosCurso::class)->validar($request);

        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'nivel' => 'required|string|in:Matutino,Vespertino',
            'seccion' => 'required|string|max:2',
            'anio_lectivo' => 'required|integer|min:2020|max:2099',

            'fecha_inicio' => [
                'required',
                'date',
            ],

            'fecha_fin' => [
                'required',
                'date',
                'after:fecha_inicio',
            ],

            'estado' => [
                'nullable',
                Rule::in(Curso::ESTADOS_EDITABLES),
            ],
        ]);

        // «Finalizado» es un estado TERMINAL. La regla no puede depender del
        // <select disabled> del formulario: los inputs deshabilitados no se
        // envían, así que un request manipulado podría mover fecha_fin al
        // futuro y devolver el curso a ACTIVO/PROXIMO. Se bloquea el update
        // completo mientras el curso esté finalizado.
        if ($curso->estado_curso === Curso::ESTADO_FINALIZADO) {
            return back()
                ->withInput()
                ->with('error', 'Un curso finalizado no puede modificarse: «Finalizado» es un estado terminal.');
        }

        $datos = [
            'nombre' => $validated['nombre'],
            'nivel' => $validated['nivel'],
            'seccion' => $validated['seccion'],
            'anio_lectivo' => $validated['anio_lectivo'],
            'fecha_inicio' => $validated['fecha_inicio'],
            'fecha_fin' => $validated['fecha_fin'],
        ];

        if (array_key_exists('estado', $validated)) {
            $estadoSolicitado = $validated['estado'];

            if ($estadoSolicitado !== $curso->estado) {
                $datos['estado'] = $estadoSolicitado;
            }
        }

        DB::transaction(function () use ($id, $datos, $horarios) {
            $curso = Curso::lockForUpdate()->findOrFail($id);
            if ($curso->estado_curso === Curso::ESTADO_FINALIZADO) {
                throw \Illuminate\Validation\ValidationException::withMessages(['estado' => 'Un curso finalizado no puede modificarse.']);
            }
            if ($curso->estado_curso === Curso::ESTADO_ACTIVO
                && $curso->fecha_inicio?->format('Y-m-d') !== \Illuminate\Support\Carbon::parse($datos['fecha_inicio'])->format('Y-m-d')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'fecha_inicio' => 'La fecha de inicio no puede modificarse porque el curso ya está activo',
                ]);
            }
            $curso->update($datos);
            app(\App\Services\HorariosCurso::class)->guardar($curso, $horarios);
        });

        return redirect()->route('admin.cursos')->with('success', 'Curso actualizado correctamente.');
    }

public function updateOrdenMaterias(Request $request, Curso $curso)
    {
        $validated = $request->validate([
            'orden' => 'required|array',
            'orden.*' => 'nullable|integer|min:1',
        ]);

        $ordenes = array_filter($validated['orden'], fn ($valor) => $valor !== null && $valor !== '');
        if (count($ordenes) !== count(array_unique($ordenes))) {
            return back()->with('error', 'No se pueden repetir posiciones dentro del mismo curso.');
        }

        $detalleIds = DetalleCurso::where('curso_id', $curso->id)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $enviados = array_keys($validated['orden']);
        if (array_diff($enviados, $detalleIds) !== []) {
            return back()->with('error', 'No se pueden modificar materias de otro curso.');
        }

        DB::transaction(function () use ($curso, $validated) {
            foreach ($validated['orden'] as $detalleId => $orden) {
                DetalleCurso::where('curso_id', $curso->id)
                    ->where('id', $detalleId)
                    ->update(['orden_academico' => $orden === null || $orden === '' ? null : (int) $orden]);
            }
        });

        return redirect()->route('admin.cursos.show', $curso->id)
            ->with('success', 'Orden académico actualizado correctamente.');
    }
public function finalizar(Curso $curso)
    {
        if ($curso->estado_curso === 'FINALIZADO') {
            return back()->with('error', 'El curso ya está finalizado.');
        }

        if ($curso->estado_curso === 'PROXIMO') {
            return back()->with('error', 'No se puede finalizar un curso que todavía no ha iniciado.');
        }

        $curso->update([
            'finalizado_manualmente' => true,
        ]);

        return back()->with('success', 'El curso fue finalizado correctamente.');
    }
}
