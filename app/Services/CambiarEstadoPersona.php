<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\CambioEstadoPersona;
use App\Models\DetalleCurso;
use App\Models\Maestro;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CambiarEstadoPersona
{
    public function revision(Alumno|Maestro $persona, bool $bloquear = false): int
    {
        $query = CambioEstadoPersona::where($persona instanceof Alumno ? 'alumno_id' : 'maestro_id', $persona->id)->orderByDesc('id');
        if ($bloquear) {
            $query->lockForUpdate();
        }

        return (int) ($query->first(['id'])?->id ?? 0);
    }

    public function ejecutar(string $tipo, int $id, bool $activo, bool $esperado, string $motivo, User $admin, int $revision): bool
    {
        abort_unless($admin->role === 'admin', 403);
        abort_unless(in_array($tipo, ['alumnos', 'maestros'], true), 404);
        Validator::make(['motivo' => trim($motivo)], ['motivo' => 'required|string|min:10|max:500'])->validate();
        $class = $tipo === 'alumnos' ? Alumno::class : Maestro::class;
        $ref = $class::findOrFail($id);

        return DB::transaction(function () use ($class, $ref, $activo, $esperado, $motivo, $admin, $tipo, $revision) {
            $user = $ref->user_id ? User::whereKey($ref->user_id)->lockForUpdate()->first() : null;
            $persona = $class::whereKey($ref->id)->lockForUpdate()->firstOrFail();
            if ($persona->user_id !== $ref->user_id || ($persona->user_id && ! $user)) {
                $this->rechazar('La asociación de cuenta cambió o no es válida.');
            }
            if ($user) {
                $rol = $tipo === 'alumnos' ? 'alumno' : 'docente';
                if ($user->role !== $rol || Alumno::where('user_id', $user->id)->count() + Maestro::where('user_id', $user->id)->count() !== 1) {
                    $this->rechazar('Asociación de cuenta ambigua o incorrecta. Revisión administrativa requerida.');
                }
            }
            // The locked profile serializes transitions, including profiles without accounts.
            // A locking read sees the latest committed audit event under InnoDB.
            if ($revision < 0 || $this->revision($persona, true) !== $revision) {
                throw ValidationException::withMessages(['revision' => 'Esta confirmación está desactualizada. Actualiza la página.']);
            }
            if ((bool) $persona->activo === $activo) {
                return false;
            }
            if ((bool) $persona->activo !== $esperado) {
                $this->rechazar('El estado cambió. Actualiza la página antes de continuar.');
            }
            if (! $activo && $persona instanceof Maestro) {
                $asignaciones = AsignacionDocente::where('maestro_id', $persona->id)->whereIn('estado', ['activa', 'programada'])->lockForUpdate()->get();
                if ($asignaciones->isNotEmpty()) {
                    $this->rechazar('Resuelve antes las asignaciones activas o programadas: '.$asignaciones->pluck('id')->implode(', ').'.');
                }
                if (DetalleCurso::where('maestro_id', $persona->id)->exists()) {
                    $this->rechazar('Existe una referencia docente sin resolver en cursos. Revisa sus asignaciones.');
                }
            }
            $persona->update(['activo' => $activo]);
            if ($user) {
                $version = (int) $user->auth_version + 1;
                $user->forceFill(['auth_version' => $version, 'remember_token' => 'itm-access-v1:'.$version.':'.Str::random(40)])->save();
            }
            CambioEstadoPersona::create([
                'alumno_id' => $tipo === 'alumnos' ? $persona->id : null,
                'maestro_id' => $tipo === 'maestros' ? $persona->id : null,
                'administrador_id' => $admin->id, 'estado_anterior' => $esperado, 'estado_nuevo' => $activo,
                'motivo' => trim($motivo), 'auth_version' => $user?->auth_version, 'registrado_at' => now(),
            ]);

            return true;
        }, 3);
    }

    private function rechazar(string $message): never
    {
        throw ValidationException::withMessages(['estado' => $message]);
    }
}
