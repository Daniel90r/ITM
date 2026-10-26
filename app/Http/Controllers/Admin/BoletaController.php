<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Boleta;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Services\BoletaService;
use App\Exceptions\BoletaEmissionException;
use App\Exceptions\BoletaStorageException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class BoletaController extends Controller
{
    public function __construct(
        private BoletaService $boletaService
    ) {}

    public function index(Request $request)
    {
        $filtrosCursos = app(\App\Services\CursosConsulta::class)->normalizar($request->validate([
            'incluir_historicos' => 'nullable|boolean', 'curso_id' => 'nullable|integer|exists:cursos,id',
        ]));
        $cursosFiltro = app(\App\Services\CursosConsulta::class)->opciones($filtrosCursos);
        $inscripcionesAprobadas = Inscripcion::where('estado', 'aprobada')
            ->whereIn('curso_id', $cursosFiltro->pluck('id'))
            ->when($filtrosCursos['curso_id'] ?? null, fn ($q, $id) => $q->where('curso_id', $id))
            ->with([
                'alumno.notas.detalleCurso.materia',
                'curso'
            ])
            ->get();

        $grupos = $inscripcionesAprobadas
            ->groupBy('curso_id')
            ->map(function ($inscripcionesDelCurso, $cursoId) {
                $curso = $inscripcionesDelCurso->first()->curso;

                $alumnos = $inscripcionesDelCurso
                    ->map(fn ($i) => $i->alumno)
                    ->unique('id')
                    ->values();

                return [
                    'curso_id' => (int) $cursoId,
                    'curso'    => $curso,
                    'alumnos'  => $alumnos,
                ];
            })
            ->sortBy(fn ($g) => $g['curso']?->nombre ?? 'zzz')
            ->values();

        return view('admin.boletas', compact('grupos', 'filtrosCursos', 'cursosFiltro'));
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
            $filtrosCursos = app(\App\Services\CursosConsulta::class)->normalizar(request()->validate(['incluir_historicos'=>'nullable|boolean']));
            $ids = app(\App\Services\CursosConsulta::class)->opciones($filtrosCursos)->pluck('id');
            $cursosAprobados = $cursosAprobados->whereIn('id', $ids)->values();
            return view('admin.boleta-detalle', compact('alumno', 'cursosAprobados', 'curso', 'filtrosCursos'));
        }

        // Usar el service para obtener los datos de preview (igual que preview())
        try {
            $preview = $this->boletaService->previsualizar($alumno->id, $curso->id);
        } catch (BoletaEmissionException $e) {
            abort(404, $e->getMessage());
        }

        $boletaHistorica = Boleta::where('alumno_id', $alumno->id)
            ->where('curso_id', $curso->id)
            ->first();

        return view('admin.boleta-detalle', compact(
            'alumno', 'curso', 'boletaHistorica'
        ) + $preview);
    }

    public function preview(Alumno $alumno, Curso $curso)
    {
        try {
            $preview = $this->boletaService->previsualizar($alumno->id, $curso->id);
        } catch (BoletaEmissionException $e) {
            abort(404, $e->getMessage());
        }

        $boletaHistorica = Boleta::where('alumno_id', $alumno->id)
            ->where('curso_id', $curso->id)
            ->first();

        return view('admin.boleta-detalle', compact(
            'alumno', 'curso', 'boletaHistorica'
        ) + $preview);
    }

    public function emitir(Request $request)
    {
        $datos = $request->validate([
            'alumno_id' => ['required', 'integer', 'exists:alumnos,id'],
            'curso_id'  => ['required', 'integer', 'exists:cursos,id'],
        ]);

        $alumnoId = (int) $datos['alumno_id'];
        $cursoId  = (int) $datos['curso_id'];

        try {
            $boleta = $this->boletaService->emitir($alumnoId, $cursoId);

            return redirect()
                ->route('admin.boletas.preview', [
                    'alumno' => $alumnoId,
                    'curso'  => $cursoId,
                ])
                ->with('success', 'Boleta emitida correctamente.');

        } catch (BoletaEmissionException $e) {
            return redirect()
                ->route('admin.boletas.preview', [
                    'alumno' => $alumnoId,
                    'curso'  => $cursoId,
                ])
                ->withErrors(['emision' => $e->getMessage()]);
        } catch (BoletaStorageException $e) {
            // No registrar mensajes de adaptadores: pueden contener rutas o datos privados.
            \Illuminate\Support\Facades\Log::error('boleta.emision_almacenamiento_fallido', [
                'operacion' => $e->operacion,
                'causa' => get_class($e->getPrevious() ?? $e),
                'administrador_id' => $request->user()?->getAuthIdentifier(),
            ]);
            return redirect()->route('admin.boletas.preview', ['alumno' => $alumnoId, 'curso' => $cursoId])
                ->withErrors(['emision' => BoletaStorageException::MENSAJE_USUARIO]);
        }
    }

    public function generarPdf($alumno_id)
    {
        request()->validate(['curso_id'=>'nullable|integer|min:1']);
        $historicas = Boleta::where('alumno_id', $alumno_id)
            ->when(request()->filled('curso_id'), fn ($q) => $q->where('curso_id', request()->query('curso_id')))
            ->get();
        if ($historicas->count() === 1) {
            return $this->descargarEmision($historicas->first());
        }
        $alumno = Alumno::with([
            'inscripciones' => fn ($q) => $q->aprobada(),
            'inscripciones.curso',
        ])->findOrFail($alumno_id);

        [$curso, $cursosAprobados, $selectorActivo] = $this->resolverCurso(
            $alumno,
            request()->query('curso_id')
        );

        // Una boleta PDF representa un curso académico concreto.
        abort_if(! $curso, 404, 'Debe seleccionar un curso para generar la boleta.');
        if ($historica = $historicas->firstWhere('curso_id', $curso->id)) {
            return $this->descargarEmision($historica);
        }

        // Reutilizar la elegibilidad de la emisión: incluye opcionales solamente
        // cuando están asociadas a este alumno y muestra las materias sin nota.
        try {
            $preview = $this->boletaService->previsualizar($alumno->id, $curso->id);
        } catch (BoletaEmissionException $e) {
            abort(404, $e->getMessage());
        }
        $notas = \App\Support\BoletaPdfPresentacion::notasDesdeFilas($preview['filas'], $curso->id);
        $promedio_general = $notas->whereNotNull('promedio')->avg('promedio');
        $calificacionesCompletas = $preview['puede_emitir_academicamente'] && $preview['materias_esperadas'] > 0;
        $estado_academico = $this->boletaService->estadoAcademicoCurso($preview['filas']);
        $docenteUltimaMateria = $this->boletaService->docenteUltimaMateria($curso->id);

        $concepto = function ($p) {
            if ($p === null || $p === '') return '';
            if ($p >= 9) return 'E';
            if ($p >= 8) return 'MB';
            if ($p >= 7) return 'B';
            if ($p >= 6) return 'R';
            return 'D';
        };

        // Sin emisión: generar una vista actual. No crea registros ni archivos oficiales.
        $boletaEmitida = null;
        $firmaDirector = null;
        $fechaBoleta = now();

        $pdf = Pdf::loadView('admin.pdf.boleta', compact(
            'alumno', 'curso', 'notas', 'promedio_general', 'concepto', 'boletaEmitida', 'firmaDirector', 'fechaBoleta', 'calificacionesCompletas', 'estado_academico', 'docenteUltimaMateria'
        ))->setPaper('a4', 'portrait');

        return $pdf->download('boleta-' . $alumno->codigo . '-' . now()->year . '.pdf');
    }

    public function descargarEmision(Boleta $boleta)
    {
        return app(\App\Services\DocumentoBoleta::class)->descargar($boleta);
    }
}
