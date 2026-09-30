<?php

use Illuminate\Support\Facades\Route;

// RUTAS: ADMINISTRADOR

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {

    // ADMINISTRADOR: Dashboard
    Route::get('/admin/dashboard', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])
        ->name('admin.dashboard');

    // ADMINISTRADOR: Alumnos
    Route::get('/admin/alumnos', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'alumnos'])
        ->name('admin.alumnos');
    Route::post('/admin/alumnos', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'storeAlumno'])
        ->name('admin.alumnos.store');
    Route::post('/admin/alumnos/siguiente-codigo', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'siguienteCodigo'])
        ->name('admin.alumnos.siguienteCodigo');
    // ADMINISTRADOR: Maestros
    Route::get('/admin/maestros', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'maestros'])
        ->name('admin.maestros');

    Route::post('/admin/maestros', [\App\Http\Controllers\Admin\MaestroController::class, 'store'])
        ->name('admin.maestros.store');

    Route::post('/admin/maestros/siguiente-codigo', [\App\Http\Controllers\Admin\MaestroController::class, 'siguienteCodigoMaestro'])
        ->name('admin.maestros.siguienteCodigo');

    Route::put('/admin/maestros/{id}', [\App\Http\Controllers\Admin\MaestroController::class, 'update'])
        ->name('admin.maestros.update');

    // ADMINISTRADOR: Materias
    Route::get('/admin/materias', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'materias'])
        ->name('admin.materias');
    Route::post('/admin/materias', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'storeMateria'])
        ->name('admin.materias.store');
    Route::post('/admin/materias/siguiente-codigo', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'siguienteCodigoMateria'])
        ->name('admin.materias.siguienteCodigo');
    Route::patch('/admin/materias/{materia}/toggle', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'toggleMateria'])
        ->name('admin.materias.toggle');

    // ADMINISTRADOR: Cursos
    Route::get('/admin/cursos', [\App\Http\Controllers\Admin\SeccionController::class, 'index'])
        ->name('admin.cursos');

    Route::post('/admin/cursos', [\App\Http\Controllers\Admin\SeccionController::class, 'store'])
        ->name('admin.cursos.store');

    Route::get('/admin/cursos/{id}', [\App\Http\Controllers\Admin\SeccionController::class, 'show'])
        ->name('admin.cursos.show');

    Route::get('/admin/cursos/{id}/editar', [\App\Http\Controllers\Admin\SeccionController::class, 'edit'])
        ->name('admin.cursos.edit');

    Route::put('/admin/cursos/{id}', [\App\Http\Controllers\Admin\SeccionController::class, 'update'])
        ->name('admin.cursos.update');

    Route::patch('/admin/cursos/{curso}/finalizar', [\App\Http\Controllers\Admin\SeccionController::class, 'finalizar'])
        ->name('admin.cursos.finalizar');

    // ADMINISTRADOR: Inscripciones
    Route::get('/admin/inscripciones', [\App\Http\Controllers\Admin\InscripcionController::class, 'index'])
        ->name('admin.inscripciones');
    Route::post('/admin/inscripciones', [\App\Http\Controllers\Admin\InscripcionController::class, 'store'])
        ->name('admin.inscripciones.store');

    // ADMINISTRADOR: Notas
    Route::get('/admin/notas', [\App\Http\Controllers\Admin\NotaController::class, 'index'])
        ->name('admin.notas');
    Route::post('/admin/notas', [\App\Http\Controllers\Admin\NotaController::class, 'store'])
        ->name('admin.notas.store');

    // ADMINISTRADOR: Boletas
    Route::get('/admin/boletas', [\App\Http\Controllers\Admin\BoletaController::class, 'index'])
        ->name('admin.boletas');
    Route::get('/admin/boletas/{alumno_id}', [\App\Http\Controllers\Admin\BoletaController::class, 'show'])
        ->name('admin.boletas.show');
    Route::get('/boletas/{id}/pdf', [\App\Http\Controllers\Admin\BoletaController::class, 'generarPdf'])
        ->name('admin.boletas.pdf');

    // ADMINISTRADOR: Mensualidades
    Route::get('/admin/mensualidades', [\App\Http\Controllers\Admin\MensualidadController::class, 'index'])
        ->name('admin.mensualidades');
    Route::get('/mensualidades', [\App\Http\Controllers\Admin\MensualidadController::class, 'index'])->name('admin.mensualidades');
    Route::post('/mensualidades/generar', [\App\Http\Controllers\Admin\MensualidadController::class, 'generar'])->name('admin.mensualidades.generar');
    Route::post('/mensualidades/{id}/pagar', [\App\Http\Controllers\Admin\MensualidadController::class, 'pagar'])->name('admin.mensualidades.pagar');
    Route::post('/mensualidades/pagar-lote', [\App\Http\Controllers\Admin\MensualidadController::class, 'pagarLote'])->name('admin.mensualidades.pagarLote');
    Route::post('/mensualidades/{id}/revertir', [\App\Http\Controllers\Admin\MensualidadController::class, 'revertir'])->name('admin.mensualidades.revertir');

    // ADMINISTRADOR: Pagos
    Route::get('/admin/pagos', [\App\Http\Controllers\Admin\PagoController::class, 'index'])
        ->name('admin.pagos');

    Route::get('/admin/maestros/{id}', [\App\Http\Controllers\Admin\MaestroController::class, 'show'])
        ->name('admin.maestros.show');

    Route::post('/admin/maestros/{id}/asignar', [\App\Http\Controllers\Admin\MaestroController::class, 'asignar'])
        ->name('admin.maestros.asignar');

    Route::delete('/admin/maestros/{maestro_id}/desasignar/{detalle_id}', [\App\Http\Controllers\Admin\MaestroController::class, 'desasignar'])
        ->name('admin.maestros.desasignar');

    Route::put('/admin/maestros/{maestro_id}/programas/{detalle_id}/cambiar-maestro', [\App\Http\Controllers\Admin\MaestroController::class, 'cambiarMaestro'])
        ->name('admin.maestros.asignaciones.cambiar');

    Route::get('/admin/maestros/materias-por-curso/{curso_id}', [\App\Http\Controllers\Admin\MaestroController::class, 'materiasPorCurso'])
        ->name('admin.maestros.materiasPorCurso');

    Route::delete('/inscripciones/{id}', [\App\Http\Controllers\Admin\InscripcionController::class, 'desactivar'])->name('admin.inscripciones.desactivar');

    Route::get('/admin/solicitudes', [App\Http\Controllers\Admin\InscripcionController::class, 'solicitudes'])->name('admin.solicitudes');
    Route::get('/admin/solicitudes/pendientes/count', [App\Http\Controllers\Admin\InscripcionController::class, 'pendientesCount'])->name('admin.solicitudes.pendientesCount');
    Route::post('/admin/solicitudes/{id}/aprobar', [App\Http\Controllers\Admin\InscripcionController::class, 'aprobar'])->name('admin.solicitudes.aprobar');
    Route::post('/admin/solicitudes/{id}/rechazar', [App\Http\Controllers\Admin\InscripcionController::class, 'rechazar'])->name('admin.solicitudes.rechazar');
    Route::post('/admin/solicitudes/{id}/reabrir', [App\Http\Controllers\Admin\InscripcionController::class, 'reabrir'])->name('admin.solicitudes.reabrir');

    Route::put('/admin/alumnos/{id}', [App\Http\Controllers\Admin\AdminDashboardController::class, 'updateAlumno'])->name('admin.alumnos.update');

    // ADMINISTRADOR: Asistencias
    Route::get('/admin/asistencias', [App\Http\Controllers\Admin\AsistenciaController::class, 'index'])
        ->name('admin.asistencias');

});
