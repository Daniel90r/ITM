<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Portal público: oferta académica visible sin iniciar sesión.
Route::get('/', \App\Http\Controllers\OfertaAcademicaController::class)->name('inicio');

// Dashboard general: despacha a cada rol hacia su propio panel.
//
// NOTA DE SEGURIDAD (Fase 0): el middleware `verified` se retiró temporalmente
// de todos los portales. User no implementa MustVerifyEmail y no hay flujo
// operativo de verificación de correo (se usan correos personales sin
// verificación), por lo que `verified` era un no-op: mostraba una protección
// inexistente. Se conserva `auth` (garantizado por EnsureUser) y `role`.
// (Ver informe al final de este cambio: opción B elegida < A.)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function (Request $request) {

        /** @var \App\Models\User $usuario */
        $usuario = $request->user();

        return match ($usuario->role) {
            'admin'   => redirect()->route('admin.dashboard'),
            'docente' => redirect()->route('docente.dashboard'),
            'alumno'  => redirect()->route('alumno.dashboard'),
            default   => abort(403, 'Rol no reconocido.'),
        };
    })->name('dashboard');
});

// El perfil compartido está reservado a administradores y docentes.
Route::middleware(['auth', 'role:admin,docente'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/docente.php';
require __DIR__.'/alumno.php';
require __DIR__.'/admin.php';
