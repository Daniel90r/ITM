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
        $consulta = Asistencia::query()
            ->when($filtros['curso_id'] ?? null, fn ($q, $id) => $q->where('curso_id', $id))
            ->when($filtros['detalle_curso_id'] ?? null, fn ($q, $id) => $q->where('detalle_curso_id', $id))
            ->when($filtros['maestro_id'] ?? null, fn ($q, $id) => $q->whereHas('detalleCurso', fn ($dc) => $dc->where('maestro_id', $id)))
            ->when($filtros['fecha_desde'] ?? null, fn ($q, $fecha) => $q->whereDate('fecha', '>=', $fecha))
            ->when($filtros['fecha_hasta'] ?? null, fn ($q, $fecha) => $q->whereDate('fecha', '<=', $fecha));

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
