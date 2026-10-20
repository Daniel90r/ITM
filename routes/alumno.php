<?php

use Illuminate\Support\Facades\Route;

// RUTAS: ALUMNO
//
// NOTA DE SEGURIDAD (Fase 0): `verified` era un no-op (User no implementa
// MustVerifyEmail). Se retira; se conservan `auth` y `alumno.portal`.
Route::middleware(['auth', 'alumno.portal'])->group(function () {
    Route::get('/alumno/inscripciones/{inscripcion}/documento', [\App\Http\Controllers\DocumentoInscripcionController::class, 'show'])
        ->middleware('role:alumno')->whereNumber('inscripcion')->name('alumno.inscripciones.documento');
    Route::get('/alumno/inscripciones/{inscripcion}/documentos', [\App\Http\Controllers\DocumentoInscripcionController::class, 'historial'])
        ->middleware('role:alumno')->whereNumber('inscripcion')->name('alumno.inscripciones.documentos');
    Route::get('/alumno/inscripciones/{inscripcion}/documentos/{documento}', [\App\Http\Controllers\DocumentoInscripcionController::class, 'version'])
        ->middleware('role:alumno')->whereNumber('inscripcion')->whereNumber('documento')->name('alumno.inscripciones.documentos.version');

    // ALUMNO: Dashboard / Inicio
    Route::get('/alumno/dashboard', [\App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'index'])
        ->name('alumno.dashboard');

    // ALUMNO: Inscripción
    Route::get('/alumno/inscripcion', [\App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'inscripcion'])
        ->name('alumno.inscripcion');

    Route::post('/alumno/inscribirse', [\App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'inscribirse'])
        ->middleware(\App\Http\Middleware\ProtegerEscriturasDocumentales::class)
        ->name('alumno.inscribirse');

    // ALUMNO: Mis Notas
    Route::get('/alumno/notas', [\App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'notas'])
        ->name('alumno.notas');

    // ALUMNO: Estado de Pagos
    Route::get('/alumno/pagos', [\App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'pagos'])
        ->name('alumno.pagos');

    Route::get('/alumno/inscripcion/pdf', [App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'descargarPdf'])->middleware(\App\Http\Middleware\ProtegerEscriturasDocumentales::class)->name('alumno.inscripcion.pdf');
    Route::post('/alumno/inscripcion/subir', [App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'subirDocumento'])->middleware(\App\Http\Middleware\ProtegerEscriturasDocumentales::class)->name('alumno.inscripcion.subir');

    Route::post('/alumno/inscripcion/enviar', [App\Http\Controllers\Alumno\AlumnoDashboardController::class, 'enviarSolicitud'])->middleware(\App\Http\Middleware\ProtegerEscriturasDocumentales::class)->name('alumno.inscripcion.enviar');

});
