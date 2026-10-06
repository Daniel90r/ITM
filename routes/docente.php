<?php

use Illuminate\Support\Facades\Route;

// RUTAS: DOCENTE
//
// NOTA DE SEGURIDAD (Fase 0): `verified` era un no-op (User no implementa
// MustVerifyEmail). Se retira; se conserva `auth` y `role:docente`.
Route::middleware(['auth', 'role:docente'])->group(function () {

    // DOCENTE: Dashboard
    Route::get('/docente/dashboard', [\App\Http\Controllers\Docente\DocenteDashboardController::class, 'index'])
        ->name('docente.dashboard');

    // DOCENTE: Registro de Notas
    Route::get('/docente/notas', [\App\Http\Controllers\Docente\NotasController::class, 'index'])
        ->name('docente.notas');
    Route::post('/docente/notas/guardar', [\App\Http\Controllers\Docente\NotasController::class, 'guardar'])
        ->name('docente.notas.guardar');
    Route::get('/docente/asistencia', [\App\Http\Controllers\Docente\AsistenciaController::class, 'index'])->name('docente.asistencia');
    Route::post('/docente/asistencia/guardar', [\App\Http\Controllers\Docente\AsistenciaController::class, 'guardar'])->name('docente.asistencia.guardar');
    Route::get('/docente/asistencia/reporte', [\App\Http\Controllers\Docente\AsistenciaController::class, 'reporte'])
        ->name('docente.asistencia.reporte');

});