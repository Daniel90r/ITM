<?php

namespace App\Services;

use App\Models\{Alumno, Curso, Inscripcion, ReactivacionInscripcion, User};
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Validation\ValidationException;

class ReactivarInscripcion
{
    public function ejecutar(int $id, User $administrador, string $motivo): Inscripcion
    {
        abort_unless($administrador->role === 'admin', 403);
        $motivo = trim($motivo);
        Validator::make(['motivo_reactivacion' => $motivo], [
            'motivo_reactivacion' => 'required|string|min:10|max:500',
        ])->validate();
        if (!Schema::hasTable('reactivaciones_inscripcion')) {
            $this->rechazar('La reactivación administrativa todavía no está habilitada. Contacta al responsable del sistema.');
        }
        // Esta lectura solo identifica las claves para mantener el orden global
        // alumno -> curso -> inscripción. El estado se relee bajo bloqueo.
        $referencia = Inscripcion::findOrFail($id);

        return DB::transaction(function () use ($referencia, $administrador, $motivo) {
            $alumno = Alumno::lockForUpdate()->findOrFail($referencia->alumno_id);
            $curso = Curso::lockForUpdate()->findOrFail($referencia->curso_id);
            $inscripcion = Inscripcion::lockForUpdate()->findOrFail($referencia->id);
            if ($inscripcion->alumno_id !== $alumno->id || $inscripcion->curso_id !== $curso->id) {
                $this->rechazar('La inscripción cambió. Recarga el listado antes de continuar.');
            }
            if (ReactivacionInscripcion::where('inscripcion_id', $inscripcion->id)->exists()) {
                $this->rechazar('La inscripción ya fue reactivada anteriormente.');
            }
            if ($inscripcion->estado !== 'inactiva' || (int) $inscripcion->activa !== 0) {
                $this->rechazar('Solo se pueden reactivar inscripciones inactivas con activa=0.');
            }
            if (!$alumno->activo || !$alumno->fecha_nacimiento) {
                $this->rechazar('El expediente del alumno está incompleto o el alumno no está habilitado.');
            }
            app(EncargadoInscripcion::class)->exigir($alumno);
            if (Inscripcion::bloqueaNuevaSolicitud($alumno->id)) {
                $this->rechazar('El alumno ya tiene otra inscripción pendiente o aprobada.');
            }
            if (!$curso->activo) $this->rechazar('El curso está deshabilitado.');
            if ($curso->estado_curso === Curso::ESTADO_FINALIZADO) $this->rechazar('El curso está finalizado.');
            if (!in_array($curso->estado_curso, [Curso::ESTADO_PROXIMO, Curso::ESTADO_ACTIVO], true)) {
                $this->rechazar('El curso debe estar Próximo o Activo para recuperar la inscripción.');
            }
            if ($curso->inscripciones()->aprobada()->lockForUpdate()->get(['id'])->count() >= Curso::CUPOS_MAXIMO) {
                $this->rechazar('El curso no tiene cupos disponibles.');
            }

            // No tocar fecha, documentos, observaciones ni dependencias académicas.
            $inscripcion->update(['estado' => 'aprobada', 'activa' => 1]);
            ReactivacionInscripcion::create([
                'inscripcion_id' => $inscripcion->id,
                'administrador_id' => $administrador->id,
                'estado_anterior' => 'inactiva',
                'estado_nuevo' => 'aprobada',
                'reactivada_at' => now(),
                'motivo' => $motivo,
            ]);
            return $inscripcion;
        });
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['reactivacion' => $mensaje]);
    }
}
