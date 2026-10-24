<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\Maestro;
use App\Models\User;

class AccesoInstitucional
{
    public function permitido(User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if (! in_array($user->role, ['alumno', 'docente'], true)) {
            return false;
        }
        if ($user->auth_version === null) {
            return false;
        }
        $alumnos = Alumno::where('user_id', $user->id)->get();
        $maestros = Maestro::where('user_id', $user->id)->get();
        if ($alumnos->count() + $maestros->count() !== 1) {
            return false;
        }
        $persona = $user->role === 'alumno' ? $alumnos->first() : $maestros->first();

        return $persona && (bool) $persona->activo;
    }
}
