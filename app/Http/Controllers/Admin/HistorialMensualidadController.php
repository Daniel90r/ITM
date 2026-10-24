<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Mensualidad;
use App\Services\HistorialMensualidades;
use App\Services\ConsultaPagos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class HistorialMensualidadController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1', 'anio' => 'nullable|integer|min:1900|max:2100']);
        $q = trim($filtros['q'] ?? '');
        $consulta = $this->alumnosConsultables()->select('id', 'nombre', 'apellido', 'codigo', 'activo');
        foreach (preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) as $termino) {
            $patron = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termino).'%';
            $consulta->where(fn ($a) => $a->whereRaw("nombre LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("apellido LIKE ? ESCAPE '!'", [$patron])->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$patron]));
        }
        if ($request->routeIs('admin.sugerencias.estados-cuenta')) return \App\Services\SugerenciasFiltros::personas($consulta);
        $alumnos = $consulta->orderBy('apellido')->orderBy('nombre')->orderBy('id')->paginate(25)->appends($filtros);
        return response()->view('admin.mensualidades-historial-alumnos', compact('alumnos', 'q', 'filtros'))->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Alumno $alumno, HistorialMensualidades $historial)
    {
        $filtros = $request->validate(['anio' => 'nullable|integer|min:1900|max:2100', 'pagos_page' => 'nullable|integer|min:1']);
        abort_unless($this->alumnosConsultables()->whereKey($alumno->id)->exists(), 404);
        $anio = (int) ($filtros['anio'] ?? now()->timezone('America/El_Salvador')->year);
        // Parametros antiguos de curso no deben ocultar obligaciones del alumno.
        $datos = $historial->consultar($alumno, $anio);
        $movimientos = app(ConsultaPagos::class)->consulta(['alumno_id' => $alumno->id, 'anio' => $anio]);
        $datos['totalPagos'] = (clone $movimientos)->sum('pagos.monto_pagado');
        $datos['pagos'] = $movimientos->orderByDesc('pagos.fecha_pago')->orderByDesc('pagos.id')
            ->paginate(25, ['*'], 'pagos_page')->appends(['anio' => $anio]);
        return response()->view('admin.mensualidades-historial', $datos)->header('Cache-Control', 'private, no-store');
    }

    public function legacyIndex(Request $request)
    {
        return redirect()->route('admin.pagos.historial.index', $request->query());
    }

    public function legacyShow(Request $request, Alumno $alumno)
    {
        return redirect()->route('admin.pagos.estado-cuenta', ['alumno' => $alumno->id] + $request->query());
    }

    private function alumnosConsultables(): Builder
    {
        // Una solicitud no habilita la consulta económica. Las obligaciones previas
        // sí deben seguir visibles, independientemente del estado del expediente.
        return Alumno::query()->where(fn (Builder $query) => $query
            ->whereHas('inscripciones', fn (Builder $inscripciones) => $inscripciones->aprobada())
            ->orWhereIn('alumnos.id', Mensualidad::query()->select('alumno_id')));
    }
}
