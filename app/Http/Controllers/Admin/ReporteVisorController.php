<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReporteVisorController extends Controller
{
    public const REPORTES = [
        'alumnos' => ['Alumnos inscritos', 'admin.reportes.alumnos.pdf'],
        'cursos' => ['Reporte anual de Cursos', 'admin.reportes.cursos.pdf'],
        'asistencias' => ['Control de Asistencia', 'admin.asistencias.pdf'],
        'mensualidades' => ['Estado de Pagos y Mensualidades', 'admin.reportes.mensualidades.pdf'],
        'maestros' => ['Maestros y Asignaciones', 'admin.reportes.maestros.pdf'],
        'inscripciones' => ['Solicitudes de inscripción', 'admin.reportes.inscripciones.pdf'],
    ];

    public function __invoke(Request $request, string $tipo)
    {
        abort_unless(isset(self::REPORTES[$tipo]), 404);
        [$titulo, $ruta] = self::REPORTES[$tipo];
        // La ruta PDF conserva toda la validación y autorización existentes.
        // Nunca aceptar una URL, ruta de disco o documento proporcionado por el usuario.
        $parametros = $tipo === 'inscripciones' ? ['estado', 'q'] : [
            'curso_id', 'estado', 'anio', 'mes', 'modalidad', 'detalle_curso_id',
            'fecha_desde', 'fecha_hasta', 'maestro_id', 'q',
        ];
        $filtros = $request->only($parametros);
        $pdfUrl = route($ruta, $filtros);
        $volverUrl = $tipo === 'inscripciones'
            ? route('admin.reportes.solicitudes', $filtros)
            : route('admin.reportes.show', ['tipo' => $tipo] + $filtros);

        return response()->view('admin.reportes.visor', compact('titulo', 'pdfUrl', 'volverUrl'))
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; frame-src 'self' blob:; object-src 'none'; base-uri 'none'; frame-ancestors 'self'");
    }
}
