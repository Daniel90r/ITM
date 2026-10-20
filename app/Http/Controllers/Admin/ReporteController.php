<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Materia;
use App\Services\ReportesAdministrativos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReporteController extends Controller
{
    public function index(Request $request, ReportesAdministrativos $reportes, ?string $tipo = null)
    {
        abort_if($tipo !== null && !array_key_exists($tipo, ReportesAdministrativos::TIPOS), 404);
        $estados = match ($tipo) {
            'alumnos', 'maestros' => ['activo' => 'Activo', 'inactivo' => 'Inactivo'],
            'cursos' => ['PROXIMO' => 'Próximo', 'ACTIVO' => 'Activo', 'FINALIZADO' => 'Finalizado', 'SIN_FECHAS' => 'Sin fechas'],
            'asistencias' => ['presente' => 'Presente', 'ausente' => 'Ausente', 'permiso' => 'Permiso'],
            'mensualidades' => ['Pendiente' => 'Pendiente', 'Pagado' => 'Pagado'],
            default => [],
        };
        $reglas = ['curso_id' => 'nullable|integer|min:1|exists:cursos,id', 'page' => 'nullable|integer|min:1|max:100000'];
        if ($estados) $reglas['estado'] = ['nullable', 'string', Rule::in(array_keys($estados))];
        if ($tipo === 'calificaciones') $reglas['materia_id'] = 'nullable|integer|min:1|exists:materias,id';
        if ($tipo === 'asistencias') {
            $reglas['fecha_desde'] = 'nullable|date_format:Y-m-d';
            $reglas['fecha_hasta'] = ['nullable', 'date_format:Y-m-d'];
            if ($request->filled('fecha_desde')) $reglas['fecha_hasta'][] = 'after_or_equal:fecha_desde';
        }
        if ($tipo === 'mensualidades') {
            $reglas['anio'] = 'nullable|integer|min:1900|max:2100';
            $reglas['mes'] = 'nullable|integer|min:1|max:12';
        }
        $filtros = array_filter($request->validate($reglas), fn ($v) => $v !== null && $v !== '');
        unset($filtros['page']);
        return response()->view('admin.reportes.index', [
            'tipos' => ReportesAdministrativos::TIPOS, 'tipo' => $tipo, 'estados' => $estados,
            'filtros' => $filtros, 'cursos' => $tipo ? $reportes->catalogoCursos() : collect(),
            'materias' => $tipo === 'calificaciones' ? Materia::select('id', 'nombre')->where('activa', true)->orderBy('nombre')->get() : collect(),
            'reporte' => $tipo ? $reportes->consultar($tipo, $filtros) : null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
