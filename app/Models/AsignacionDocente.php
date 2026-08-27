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
}
