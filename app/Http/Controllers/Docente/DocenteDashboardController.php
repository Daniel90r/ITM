<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DocenteDashboardController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // DOCENTE: DASHBOARD
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    public function index()
    {
        $docente = Auth::user()->maestro()->firstOrFail();

        $cursos = $docente->asignacionesDocentes()
            ->with([
                'detalleCurso.curso',
                'detalleCurso.materia',
            ])
            ->where('estado', 'activa')
            ->whereHas('detalleCurso.curso', fn ($q) => $q->where('activo', true))
            ->whereHas('detalleCurso.materia', fn ($q) => $q->where('activa', true))
            ->get()
            ->map(fn ($asignacion) => $asignacion->detalleCurso)
            ->keyBy('id')
            ->groupBy('curso_id');

        return view('docente.dashboard', compact('docente', 'cursos'));
    }
}
