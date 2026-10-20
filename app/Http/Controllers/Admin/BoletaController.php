<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Boleta;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Services\BoletaService;
use App\Exceptions\BoletaEmissionException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BoletaController extends Controller
{
    public function __construct(
        private BoletaService $boletaService
    ) {}

    public function index()
    {
        $inscripcionesAprobadas = Inscripcion::where('estado', 'aprobada')
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

        return view('admin.boletas', compact('grupos'));
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
            return view('admin.boleta-detalle', compact('alumno', 'cursosAprobados', 'curso'));
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
        }
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

        // La firma y el nombre proceden de la emisión, nunca del Director actual
        // ni de las firmas del alumno o encargado. No modificar el snapshot.
        $boletaEmitida = Boleta::where('alumno_id', $alumno->id)->where('curso_id', $curso->id)->first();
        $firmaDirector = null;
        $fechaBoleta = $boletaEmitida?->fecha_emision ?? now();
        if ($boletaEmitida) {
            $path = $boletaEmitida->director_firma_snapshot_path;
            if (!str_starts_with($path, 'boletas/firmas/'.$boletaEmitida->id.'/')
                || str_contains($path, '..') || !Storage::disk('local')->exists($path)) {
                throw ValidationException::withMessages(['emision' => 'La firma histórica de esta boleta no está disponible. Contacta a Administración.']);
            }
            $bytes = Storage::disk('local')->get($path);
            $info = @getimagesizefromstring($bytes);
            if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
                throw ValidationException::withMessages(['emision' => 'La firma histórica de esta boleta no es una imagen válida. Contacta a Administración.']);
            }
            $firmaDirector = 'data:'.$info['mime'].';base64,'.base64_encode($bytes);
        }

        $pdf = Pdf::loadView('admin.pdf.boleta', compact(
            'alumno', 'curso', 'notas', 'promedio_general', 'concepto', 'boletaEmitida', 'firmaDirector', 'fechaBoleta'
        ))->setPaper('a4', 'portrait');

        return $pdf->download('boleta-' . $alumno->codigo . '-' . now()->year . '.pdf');
    }
}
