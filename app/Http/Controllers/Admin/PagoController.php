<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ConsultaPagos;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    public function alumnos(Request $request, ConsultaPagos $consulta)
    {
        $filtros = $request->validate(ConsultaPagos::REGLAS);
        $filtros['anio'] = array_key_exists('anio', $filtros) ? ($filtros['anio'] ?? '') : now()->year;
        unset($filtros['alumno_id']);
        $texto = trim($filtros['q'] ?? '');
        $alumnos = mb_strlen($texto) < 2 ? collect() : $consulta->consulta($filtros)
            ->select(['alumno.id', 'alumno.nombre', 'alumno.apellido', 'alumno.codigo'])
            ->distinct()->orderBy('alumno.apellido')->orderBy('alumno.nombre')->orderBy('alumno.id')->limit(8)->get()
            ->map(fn ($a) => ['id' => $a->id, 'nombre' => trim($a->nombre.' '.$a->apellido), 'codigo' => $a->codigo]);
        return response()->json(['alumnos' => $alumnos])->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request, ConsultaPagos $consulta)
    {
        $filtros = $request->validate(ConsultaPagos::REGLAS);
        // Un año vacío es la selección explícita de todos los años.
        $filtros['anio'] = array_key_exists('anio', $filtros) ? ($filtros['anio'] ?? '') : now()->year;
        $query = $consulta->consulta($filtros);
        $totalRecaudado = (clone $query)->sum('pagos.monto_pagado');
        $mesActual = (clone $query)->whereYear('pagos.fecha_pago', now()->year)->whereMonth('pagos.fecha_pago', now()->month);
        $recaudadoMes = (clone $mesActual)->sum('pagos.monto_pagado');
        $pagosMesActual = $mesActual->count();
        $pagos = $query->orderByDesc('pagos.fecha_pago')->orderByDesc('pagos.id')->paginate(25)->appends($filtros);
        return response()->view('admin.pagos', compact('pagos', 'filtros', 'totalRecaudado', 'recaudadoMes', 'pagosMesActual'))
            ->header('Cache-Control', 'private, no-store');
    }
}
