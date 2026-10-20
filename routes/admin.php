<?php

use Illuminate\Support\Facades\Route;

// RUTAS: ADMINISTRADOR
//
// NOTA DE SEGURIDAD (Fase 0): se retiró el middleware `verified` del grupo.
// `verified` era un no-op porque User no implementa MustVerifyEmail y no hay
// flujo de verificación operativo. Se conserva `auth` y `role:admin`.
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/reportes', [\App\Http\Controllers\Admin\ReporteController::class, 'index'])->name('admin.reportes');
    Route::get('/admin/reportes/{tipo}', [\App\Http\Controllers\Admin\ReporteController::class, 'index'])
        ->whereIn('tipo', array_keys(\App\Services\ReportesAdministrativos::TIPOS))->name('admin.reportes.show');
    Route::get('/admin/solicitudes/{inscripcion}/documento', [\App\Http\Controllers\DocumentoInscripcionController::class, 'show'])
        ->whereNumber('inscripcion')->name('admin.solicitudes.documento');
    Route::get('/admin/solicitudes/{inscripcion}/documentos', [\App\Http\Controllers\DocumentoInscripcionController::class, 'historial'])
        ->whereNumber('inscripcion')->name('admin.solicitudes.documentos');
    Route::get('/admin/solicitudes/{inscripcion}/documentos/{documento}', [\App\Http\Controllers\DocumentoInscripcionController::class, 'version'])
        ->whereNumber('inscripcion')->whereNumber('documento')->name('admin.solicitudes.documentos.version');

    // ADMINISTRADOR: Dashboard
    Route::get('/admin/dashboard', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])
        ->name('admin.dashboard');

    // ADMINISTRADOR: Alumnos
    Route::get('/admin/alumnos', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'alumnos'])
        ->name('admin.alumnos');
    Route::get('/admin/alumnos/encargados', [\App\Http\Controllers\Admin\EncargadoController::class, 'index'])
        ->name('admin.encargados.index');
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
    //
    // Fase 0: se retira POST /admin/notas (admin.notas.store).
    // Causa: la columna `notas.registrado_por` es FK de `maestros.id`, pero el
    // controlador asignaba `auth()->id()` (users.id). Un admin no tiene fila en
    // `maestros`, por lo que el POST producía un usuario inválido o error de FK,
    // y la vista admin/notas.blade.php es SOLO lectura (no hay consumidor).
    // El registro real de notas lo realiza el docente en docente.notas.guardar,
    // que valida asignación y usa maestro.id como `registrado_por`.
    Route::get('/admin/notas', [\App\Http\Controllers\Admin\NotaController::class, 'index'])
        ->name('admin.notas');

    // ADMINISTRADOR: Boletas
    Route::get('/admin/boletas', [\App\Http\Controllers\Admin\BoletaController::class, 'index'])
        ->name('admin.boletas');
    Route::post('/admin/boletas/emitir', [\App\Http\Controllers\Admin\BoletaController::class, 'emitir'])
        ->name('admin.boletas.emitir');
    Route::get('/admin/boletas/preview/{alumno}/{curso}', [\App\Http\Controllers\Admin\BoletaController::class, 'preview'])
        ->name('admin.boletas.preview');
    Route::get('/admin/boletas/{alumno_id}', [\App\Http\Controllers\Admin\BoletaController::class, 'show'])
        ->name('admin.boletas.show');
    Route::get('/boletas/{id}/pdf', [\App\Http\Controllers\Admin\BoletaController::class, 'generarPdf'])
        ->name('admin.boletas.pdf');

    // ADMINISTRADOR: Mensualidades
    //
    // `/admin/mensualidades` es la ruta OFICIAL: es la que resuelven todos los
    // `route('admin.mensualidades')` del sidebar, del dashboard, de la propia
    // vista y de las redirecciones del controlador.
    Route::get('/admin/mensualidades', [\App\Http\Controllers\Admin\MensualidadController::class, 'index'])
        ->name('admin.mensualidades');
    Route::get('/admin/mensualidades/cursos/{curso}/alumnos/{alumno}', [\App\Http\Controllers\Admin\MensualidadController::class, 'detalles'])
        ->whereNumber('curso')->whereNumber('alumno')->name('admin.mensualidades.detalles');
    // `/mensualidades` es el mismo listado en una URL corta. Se conserva para
    // no romper marcadores ni enlaces ya compartidos, pero NO puede llamarse
    // igual que la ruta oficial: dos rutas con el mismo nombre hacen fallar
    // `php artisan route:cache` con
    // "Another route has already been assigned name [admin.mensualidades]".
    Route::get('/mensualidades', [\App\Http\Controllers\Admin\MensualidadController::class, 'index'])
        ->name('admin.mensualidades.corta');
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
    Route::post('/admin/solicitudes/{id}/reactivar', [App\Http\Controllers\Admin\InscripcionController::class, 'reactivar'])->whereNumber('id')->name('admin.solicitudes.reactivar');

    Route::put('/admin/alumnos/{id}', [App\Http\Controllers\Admin\AdminDashboardController::class, 'updateAlumno'])->name('admin.alumnos.update');

    // ADMINISTRADOR: Asistencias
    Route::get('/admin/asistencias', [App\Http\Controllers\Admin\AsistenciaController::class, 'index'])
        ->name('admin.asistencias');
    Route::get('/admin/asistencias/alumnos/{alumno}/detalle', [App\Http\Controllers\Admin\AsistenciaController::class, 'detalles'])
        ->whereNumber('alumno')->name('admin.asistencias.detalles');

    // ADMINISTRADOR: Directores
    Route::get('/admin/directores', [\App\Http\Controllers\Admin\DirectorController::class, 'index'])
        ->name('admin.directores.index');
    Route::post('/admin/directores', [\App\Http\Controllers\Admin\DirectorController::class, 'store'])
        ->name('admin.directores.store');
    Route::patch('/admin/directores/{director}/firma', [\App\Http\Controllers\Admin\DirectorController::class, 'updateFirma'])
        ->name('admin.directores.firma.update');
    Route::get('/admin/directores/{director}/firma', [\App\Http\Controllers\Admin\DirectorController::class, 'showFirma'])
        ->name('admin.directores.firma.show');
});
