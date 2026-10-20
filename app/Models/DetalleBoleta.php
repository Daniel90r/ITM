<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleBoleta extends Model
{
    protected $table = 'detalles_boletas';

    protected $fillable = [
        'boleta_id',
        'detalle_curso_id',
        'materia_id',
        'materia_nombre',
        'materia_codigo',
        'laboratorio',
        'examen_teorico',
        'practica',
        'conducta',
        'promedio',
    ];

    protected $casts = [
        'laboratorio' => 'decimal:2',
        'examen_teorico' => 'decimal:2',
        'practica' => 'decimal:2',
        'promedio' => 'integer',
    ];

    public function boleta(): BelongsTo
    {
        return $this->belongsTo(Boleta::class);
    }

    public function detalleCurso(): BelongsTo
    {
        return $this->belongsTo(DetalleCurso::class);
    }

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }
}