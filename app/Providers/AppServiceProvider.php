<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use App\Models\Inscripcion;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // La auto-registro pública está deshabilitada: los alumnos sólo se crean
        // desde Gestión de Alumnos. Por ello el listener que generaba un perfil
        // de Alumno a partir del evento `Registered` ya no se registra.
        // (Mantiene la clase App\Listeners\CreateAlumnoProfile por compat.)

        $this->componerConteoDeSolicitudes();
    }

    /**
     * El badge del sidebar necesita el conteo de solicitudes pendientes, pero
     * una vista no debe consultar la base de datos. Se resuelve aquí, una sola
     * vez por request, para todas las páginas de administración.
     *
     * Si la vista ya recibió el dato (admin.solicitudes lo calcula su
     * controlador), se respeta ese valor y NO se vuelve a consultar.
     */
    private function componerConteoDeSolicitudes(): void
    {
        View::composer('components.admin-sidebar', function ($view): void {
            if (array_key_exists('solicitudesPendientes', $view->getData())) {
                return;
            }

            $view->with('solicitudesPendientes', Inscripcion::pendiente()->count());
        });
    }
}
