<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionDocente extends Model
{
    protected $table = 'asignaciones_docentes';

    protected $fillable = [
        'detalle_curso_id',
        'maestro_id',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'motivo_cambio',
        'asignado_por',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function detalleCurso(): BelongsTo
    {
        return $this->belongsTo(DetalleCurso::class);
    }

    public function maestro(): BelongsTo
    {
        return $this->belongsTo(Maestro::class);
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    /**
     * Asignación vigente: es la que autoriza trabajar con esa materia.
     *
     * Una asignación 'finalizada' o 'programada' se conserva como historial,
     * pero NO habilita registros nuevos.
     */
    public function scopeActivas($query)
    {
        return $query->where('estado', 'activa');
    }

    /**
     * Materias (detalle_curso) que el maestro tiene asignadas AHORA en cursos
     * y materias activas.
     *
     * Es la fuente de verdad del registro de asistencia y de notas: un curso
     * no pertenece a un maestro, la relación es Curso + Materia + Maestro.
     */
    public function scopeDetallesDelMaestro($query, int $maestroId)
    {
        return $query
            ->where('maestro_id', $maestroId)
            ->activas()
            ->whereHas('detalleCurso', function ($q) {
                $q->whereHas('curso', fn ($cq) => $cq->where('activo', true))
                    ->whereHas('materia', fn ($mq) => $mq->where('activa', true));
            });
    }
}
