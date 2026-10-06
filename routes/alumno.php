<?php

use Illuminate\Support\Facades\Route;

// RUTAS: ALUMNO
//
// NOTA DE SEGURIDAD (Fase 0): `verified` era un no-op (User no implementa
// MustVerifyEmail). Se retira; se conservan `auth` y `alumno.portal`.
Route::middleware(['auth', 'alumno.portal'])->group(function () {

    // ALUMNO: Dashboard / Inicio
    Route::get('/alumno/dashboard', [\App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'index'])
        ->name('alumno.dashboard');

    // ALUMNO: Inscripción
    Route::get('/alumno/inscripcion', [\App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'inscripcion'])
        ->name('alumno.inscripcion');

    Route::post('/alumno/inscribirse', [\App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'inscribirse'])
        ->name('alumno.inscribirse');

    // ALUMNO: Mis Notas
    Route::get('/alumno/notas', [\App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'notas'])
        ->name('alumno.notas');

    // ALUMNO: Estado de Pagos
    Route::get('/alumno/pagos', [\App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'pagos'])
        ->name('alumno.pagos');

    Route::get('/alumno/inscripcion/pdf', [App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'descargarPdf'])->name('alumno.inscripcion.pdf');
    Route::post('/alumno/inscripcion/subir', [App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'subirDocumento'])->name('alumno.inscripcion.subir');

    Route::post('/alumno/inscripcion/enviar', [App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'enviarSolicitud'])->name('alumno.inscripcion.enviar');

});
