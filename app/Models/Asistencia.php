<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    protected $fillable = [
        'curso_id', 'detalle_curso_id', 'alumno_id', 'fecha', 'estado', 'observacion', 'registrado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function detalleCurso(): BelongsTo
    {
        return $this->belongsTo(DetalleCurso::class);
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function maestro(): BelongsTo
    {
        return $this->belongsTo(Maestro::class, 'registrado_por');
    }
}
