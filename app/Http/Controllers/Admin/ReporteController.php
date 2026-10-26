<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Materia;
use App\Models\DetalleCurso;
use App\Services\ReportesAdministrativos;
use App\Services\ConsultaSolicitudesInscripcion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReporteController extends Controller
{
    public function index()
    {
        return response()->view('admin.reportes.index', ['tipos' => ReportesAdministrativos::TIPOS])
            ->header('Cache-Control', 'private, no-store');
    }

    public function solicitudes(Request $request)
    {
        $filtros = $request->validate(ConsultaSolicitudesInscripcion::REGLAS);
        return response()->view('admin.reportes.solicitudes', compact('filtros'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, ReportesAdministrativos $reportes, string $tipo)
    {
        abort_unless(array_key_exists($tipo, ReportesAdministrativos::TIPOS), 404);
        if ($tipo === 'asistencias') {
            if ($request->hasAny(['fecha_desde', 'fecha_hasta'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'modalidad' => 'En Reportes utiliza Mensual o Anual con mes y año; Desde/Hasta ya no se admite en esta pantalla.',
                ]);
            }
            if (!$request->filled('modalidad')) {
                $hoy = now('America/El_Salvador');
                $request->merge(['modalidad' => 'mensual', 'anio' => $request->input('anio', $hoy->year), 'mes' => $request->input('mes', $hoy->month)]);
            }
        }
        [$estados, $filtros] = $this->filtros($request, $tipo);
        if ($tipo === 'asistencias') $filtros = array_merge($filtros, app(\App\Services\PeriodoAsistencia::class)->validar($request));
        return response()->view('admin.reportes.'.$tipo, [
            'tipos' => ReportesAdministrativos::TIPOS, 'tipo' => $tipo, 'estados' => $estados,
            'filtros' => $filtros, 'cursos' => app(\App\Services\CursosConsulta::class)->opciones($filtros),
            'materias' => $tipo === 'calificaciones' ? Materia::select('id', 'nombre')->where('activa', true)
                ->whereHas('detalleCursos', fn ($q) => app(\App\Services\CursosConsulta::class)->aplicar($q, $filtros)
                    ->when($filtros['curso_id'] ?? null, fn ($q, $id) => $q->where('curso_id', $id)))->orderBy('nombre')->get() : collect(),
            'detalleCursos' => $tipo === 'asistencias' && !empty($filtros['curso_id'])
                ? DetalleCurso::where('curso_id', $filtros['curso_id'])->with('materia')->orderBy('id')->get() : collect(),
            'reporte' => $reportes->consultar($tipo, $filtros),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function alumnosPdf(Request $request, ReportesAdministrativos $reportes)
    {
        [, $filtros] = $this->filtros($request, 'alumnos');
        $datos = $reportes->alumnosPdf($filtros);
        $emitido = now()->timezone('America/El_Salvador');
        $responsable = $request->user()->name;
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reportes.pdf.alumnos', $datos + compact('filtros', 'emitido', 'responsable'))
            ->setPaper('letter', 'landscape')->setOption('isRemoteEnabled', false);
        $pdf->render();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $pdf->getDomPDF()->getCanvas()->page_text(655, 591, 'Página {PAGE_NUM} / {PAGE_COUNT}', $font, 7);
        return $pdf->download('alumnos-inscritos-'.$emitido->format('Y-m-d-His').'.pdf')
            ->header('Cache-Control', 'private, no-store');
    }

    public function cursosPdf(Request $request, ReportesAdministrativos $reportes)
    {
        [, $filtros] = $this->filtros($request, 'cursos');
        $datos = $reportes->cursosPdf($filtros);
        $emitido = now()->timezone('America/El_Salvador');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reportes.pdf.cursos', $datos + compact('filtros', 'emitido'))
            ->setPaper('letter', empty($filtros['modalidad']) ? 'portrait' : 'landscape')->setOption('isRemoteEnabled', false);
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_text(empty($filtros['modalidad']) ? 450 : 650, empty($filtros['modalidad']) ? 765 : 590, 'Página {PAGE_NUM} de {PAGE_COUNT}', null, 8);
        return $pdf->download('reporte-cursos-'.$emitido->format('Y-m-d-His').'.pdf')
            ->header('Cache-Control', 'private, no-store');
    }

    public function inscripcionesPdf(Request $request, ConsultaSolicitudesInscripcion $solicitudes)
    {
        $filtros = $request->validate(ConsultaSolicitudesInscripcion::REGLAS);
        $datos = $solicitudes->informe($filtros);
        $emitido = now()->timezone('America/El_Salvador');
        $responsable = $request->user()->name;
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reportes.pdf.inscripciones',
            $datos + compact('filtros', 'emitido', 'responsable'))
            ->setPaper('letter', 'portrait')->setOption('isRemoteEnabled', false);
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_text(450, 765, 'Página {PAGE_NUM} de {PAGE_COUNT}', null, 8);
        return $pdf->download('reporte-inscripciones-'.$emitido->format('Y-m-d-His').'.pdf')
            ->header('Cache-Control', 'private, no-store');
    }

    public function maestrosPdf(Request $request, ReportesAdministrativos $reportes)
    {
        [, $filtros] = $this->filtros($request, 'maestros');
        $datos = $reportes->maestrosPdf($filtros);
        $emitido = now()->timezone('America/El_Salvador');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reportes.pdf.maestros', $datos + compact('filtros', 'emitido'))
            ->setPaper('letter', 'portrait')->setOption('isRemoteEnabled', false);
        $pdf->render();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $pdf->getDomPDF()->getCanvas()->page_text(46.8, 756,
            'ITM Aguilares • Reporte de Maestros y Asignaciones • Gestión Académica | {PAGE_NUM} / {PAGE_COUNT}', $font, 7, [.42, .45, .50]);
        return $pdf->download('reporte-maestros-asignaciones-'.$emitido->format('Y-m-d-His').'.pdf')
            ->header('Cache-Control', 'private, no-store');
    }

    public function mensualidadesPdf(Request $request, ReportesAdministrativos $reportes)
    {
        [, $filtros] = $this->filtros($request, 'mensualidades');
        $datos = $reportes->mensualidadesPdf($filtros);
        $emitido = now()->timezone('America/El_Salvador');
        $responsable = $request->user()->name;
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reportes.pdf.mensualidades', $datos + compact('filtros', 'emitido', 'responsable'))
            ->setPaper('letter', 'portrait')->setOption('isRemoteEnabled', false);
        $pdf->render();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $pdf->getDomPDF()->getCanvas()->page_text(46.8, 756,
            'ITM Aguilares • Consulta de pagos • Gestión Académica | {PAGE_NUM} / {PAGE_COUNT}', $font, 7, [.42, .45, .50]);
        return $pdf->download('estado-pagos-mensualidades-'.$emitido->format('Y-m-d-His').'.pdf')
            ->header('Cache-Control', 'private, no-store');
    }

    private function filtros(Request $request, ?string $tipo): array
    {
        $estados = match ($tipo) {
            'alumnos', 'maestros' => ['activo' => 'Activo', 'inactivo' => 'Inactivo'],
            'cursos' => ['PROXIMO' => 'Próximo', 'ACTIVO' => 'Activo', 'FINALIZADO' => 'Finalizado', 'SIN_FECHAS' => 'Sin fechas'],
            'asistencias' => ['presente' => 'Presente', 'ausente' => 'Ausente', 'permiso' => 'Permiso'],
            'mensualidades' => ['Pendiente' => 'Pendiente', 'Pagado' => 'Pagado'],
            default => [],
        };
        $reglas = ['incluir_historicos' => 'nullable|boolean', 'curso_id' => 'nullable|integer|min:1|exists:cursos,id', 'page' => 'nullable|integer|min:1|max:100000'];
        if ($estados) $reglas['estado'] = ['nullable', 'string', Rule::in(array_keys($estados))];
        if ($tipo === 'calificaciones') $reglas['materia_id'] = 'nullable|integer|min:1|exists:materias,id';
        if ($tipo === 'asistencias') {
            $reglas['detalle_curso_id'] = ['nullable', 'integer', 'min:1', Rule::exists('detalle_cursos', 'id')->where('curso_id', $request->input('curso_id'))];
            $reglas['curso_id'] = 'required_with:detalle_curso_id|nullable|integer|min:1|exists:cursos,id';
            $reglas['fecha_desde'] = 'nullable|date_format:Y-m-d';
            $reglas['fecha_hasta'] = ['nullable', 'date_format:Y-m-d'];
            if ($request->filled('fecha_desde')) $reglas['fecha_hasta'][] = 'after_or_equal:fecha_desde';
        }
        if ($tipo === 'mensualidades') {
            if (!$request->has('anio')) $request->merge(['anio' => now()->year]);
            $reglas['anio'] = 'required|integer|min:1900|max:2100';
            $reglas['mes'] = 'nullable|integer|min:1|max:12';
        }
        if ($tipo === 'cursos') {
            if ($request->has('mes') || ($request->has('modalidad') && $request->input('modalidad') !== 'anual')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['anio'=>'Cursos ofrece solo el reporte anual. Retira mes y modalidad mensual del enlace y selecciona un año.']);
            }
            $request->merge(['modalidad'=>'anual']);
            if (!$request->has('anio')) $request->merge(['anio'=>now('America/El_Salvador')->year]);
            $reglas['modalidad'] = 'required|in:anual';
            $reglas['anio'] = 'required|integer|between:1900,2100';
        }
        if ($tipo === 'alumnos') {
            if (!$request->has('anio') && !$request->filled('mes')) {
                $request->merge(['anio' => now()->year]);
            }
            $reglas['mes'] = 'nullable|integer|min:1|max:12';
            $reglas['anio'] = 'required|integer|min:1900|max:2100';
        }
        $filtros = array_filter($request->validate($reglas), fn ($v) => $v !== null && $v !== '');
        unset($filtros['page']);
        return [$estados, app(\App\Services\CursosConsulta::class)->normalizar($filtros)];
    }
}
