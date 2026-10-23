<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\Maestro;
use App\Models\DetalleCurso;
use App\Models\Alumno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AsistenciaController extends Controller
{
    public function index(Request $request)
    {
        $cursos = Curso::where('activo', true)->orderBy('nombre')->get();
        $maestros = Maestro::where('activo', true)->orderBy('nombre')->get();

        $filtros = $this->filtros($request);
        $cursoId = $filtros['curso_id'] ?? null;
        $maestroId = $filtros['maestro_id'] ?? null;
        $detalleCursoId = $filtros['detalle_curso_id'] ?? null;
        $fechaDesde = $filtros['fecha_desde'] ?? null;
        $fechaHasta = $filtros['fecha_hasta'] ?? null;
        $busqueda = $filtros['q'] ?? '';
        $consulta = $this->consultaFiltrada($filtros);

        // Contar todos los resultados filtrados antes de paginar.
        $porEstado = (clone $consulta)->selectRaw('estado, COUNT(*) AS cantidad')
            ->groupBy('estado')->pluck('cantidad', 'estado');
        $resumen = [
            'total' => (int) $porEstado->sum(),
            'presentes' => (int) $porEstado->get('presente', 0),
            'ausentes' => (int) $porEstado->get('ausente', 0),
            'permisos' => (int) $porEstado->get('permiso', 0),
        ];
        $resumen['porcentaje'] = $resumen['total'] > 0
            ? round($resumen['presentes'] * 100 / $resumen['total'], 1)
            : null;

        // Una fila SQL por alumno; nunca hidratar su historial en el listado.
        $agrupada = (clone $consulta)->select('alumno_id')
            ->selectRaw('COUNT(*) AS registros')
            ->selectRaw("SUM(CASE WHEN estado = 'presente' THEN 1 ELSE 0 END) AS presentes")
            ->selectRaw("SUM(CASE WHEN estado = 'ausente' THEN 1 ELSE 0 END) AS ausentes")
            ->selectRaw("SUM(CASE WHEN estado = 'permiso' THEN 1 ELSE 0 END) AS permisos")
            ->groupBy('alumno_id');
        $alumnos = Alumno::query()->joinSub($agrupada, 'resumen_asistencia', function ($join) {
            $join->on('alumnos.id', '=', 'resumen_asistencia.alumno_id');
        })->select(['alumnos.id', 'alumnos.nombre', 'alumnos.apellido', 'alumnos.codigo',
            'resumen_asistencia.registros', 'resumen_asistencia.presentes',
            'resumen_asistencia.ausentes', 'resumen_asistencia.permisos'])
            ->orderBy('alumnos.apellido')->orderBy('alumnos.nombre')->orderBy('alumnos.id')
            ->paginate(25)->appends($filtros);

        // Cursos del historial filtrado, no la inscripción vigente del alumno.
        // Dos consultas para toda la página, independientemente de sus alumnos.
        $cursosPorAlumno = (clone $consulta)->whereIn('alumno_id', $alumnos->pluck('id'))
            ->select(['alumno_id', 'curso_id'])->distinct()
            ->with('curso:id,nombre,nivel,seccion')->get()->groupBy('alumno_id');

        // Si se seleccionó un curso, cargar sus materias para el filtro
        $detalleCursos = collect();
        if ($cursoId) {
            $detalleCursos = DetalleCurso::where('curso_id', $cursoId)
                ->with('materia')
                ->get();
        }

        return view('admin.asistencias', compact(
            'cursos', 'maestros', 'detalleCursos', 'alumnos', 'cursosPorAlumno', 'filtros',
            'cursoId', 'maestroId', 'detalleCursoId', 'fechaDesde', 'fechaHasta', 'resumen', 'busqueda'
        ));
    }

    public function detalles(Request $request, Alumno $alumno)
    {
        $filtros = $this->filtros($request);
        $consulta = $this->consultaFiltrada($filtros)->where('alumno_id', $alumno->id);
        abort_unless((clone $consulta)->exists(), 404);
        $asistencias = $consulta->with(['curso', 'detalleCurso.materia', 'detalleCurso.maestro'])
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(20, ['*'], 'detalle_page')->appends($filtros);

        // Solo HTML escapado por Blade; no serializar el expediente del alumno.
        return response()->json(['html' => view('admin.partials.asistencias-detalle', compact('asistencias'))->render()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function pdf(Request $request, \App\Services\ControlAsistenciaPdf $informe)
    {
        $periodo = app(\App\Services\PeriodoAsistencia::class)->validar($request);
        $request->validate([
            'curso_id' => 'required|integer|exists:cursos,id',
            'detalle_curso_id' => 'required|integer|exists:detalle_cursos,id',
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', ...($request->filled('fecha_desde') ? ['after_or_equal:fecha_desde'] : [])],
        ]);
        $filtros = $this->filtros($request);
        $detalle = DetalleCurso::with(['curso', 'materia'])->findOrFail($filtros['detalle_curso_id']);
        abort_unless((int) $detalle->curso_id === (int) $filtros['curso_id'], 422, 'La materia no corresponde al curso seleccionado.');
        abort_if(isset($filtros['maestro_id']) && (int) $detalle->maestro_id !== (int) $filtros['maestro_id'], 422,
            'El docente seleccionado no corresponde a la materia.');
        $estado = $request->validate(['estado' => ['nullable', 'string', \Illuminate\Validation\Rule::in(array_keys(\App\Services\FiltrosHistoricosAsistencia::ESTADOS))]]);
        $filtros = array_merge($filtros, array_filter($estado, fn ($v) => $v !== null && $v !== ''));
        if ($periodo) $filtros = array_merge($filtros, $periodo);
        $filtrado = !empty($filtros['estado']);
        $servicio = $filtrado ? app(\App\Services\AsistenciaHistoricaPdf::class) : $informe;
        $datos = !empty($filtros['modalidad'])
            ? app(\App\Services\AsistenciasPeriodo::class)->datos($detalle, $filtros)
            : $servicio->datos($detalle, $filtros, $this->consultaFiltrada($filtros));
        $vista = !empty($filtros['modalidad']) ? 'admin.reportes.pdf.asistencia-periodo' : ($filtrado ? 'admin.reportes.pdf.asistencia-filtrada' : 'admin.reportes.pdf.asistencia');
        $prefijo = !empty($filtros['modalidad']) ? 'asistencia-'.$filtros['modalidad'].'-' : ($filtrado ? 'asistencias-historicas-'.$filtros['estado'].'-' : 'control-asistencia-');
        if (!empty($filtros['modalidad'])) $filtros = app(\App\Services\PeriodoAsistencia::class)->conFechas($filtros);
        $emitido = now()->timezone('America/El_Salvador');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($vista, $datos + compact('filtros', 'emitido'))
            ->setPaper('letter', 'landscape')->setOption('isRemoteEnabled', false);
        $pdf->render();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $pdf->getDomPDF()->getCanvas()->page_text(650, 591, 'Página {PAGE_NUM} / {PAGE_COUNT}', $font, 7);
        return $pdf->download($prefijo.$detalle->curso_id.'-'.$detalle->id.'-'.$emitido->format('Y-m-d-His').'.pdf')
            ->header('Cache-Control', 'private, no-store');
    }

    private function filtros(Request $request): array
    {
        $validated = $request->validate([
            'curso_id' => 'nullable|integer|min:1',
            'maestro_id' => 'nullable|integer|min:1',
            'detalle_curso_id' => 'nullable|integer|min:1',
            'fecha_desde' => 'nullable|date_format:Y-m-d',
            'fecha_hasta' => 'nullable|date_format:Y-m-d',
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'detalle_page' => 'nullable|integer|min:1',
        ]);
        unset($validated['page'], $validated['detalle_page']);
        if (isset($validated['q'])) $validated['q'] = trim($validated['q']);
        return $validated;
    }

    private function consultaFiltrada(array $filtros): Builder
    {
        $consulta = app(\App\Services\FiltrosHistoricosAsistencia::class)->aplicar(Asistencia::query(), $filtros)
            ->when($filtros['maestro_id'] ?? null, fn ($q, $id) => $q->whereHas('detalleCurso', fn ($dc) => $dc->where('maestro_id', $id)));

        foreach (preg_split('/\s+/u', $filtros['q'] ?? '', -1, PREG_SPLIT_NO_EMPTY) as $termino) {
            $patron = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termino).'%';
            $consulta->whereHas('alumno', fn ($alumno) => $alumno
                ->whereRaw("nombre LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("apellido LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$patron]));
        }
        return $consulta;
    }
}
