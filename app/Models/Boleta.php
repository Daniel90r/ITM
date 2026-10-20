<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Boleta extends Model
{
    protected $table = 'boletas';

    protected $fillable = [
        'alumno_id',
        'curso_id',
        'director_id',
        'inscripcion_id',
        'fecha_emision',
        'alumno_nombre',
        'alumno_apellido',
        'alumno_codigo',
        'curso_nombre',
        'curso_turno',
        'curso_seccion',
        'curso_anio_lectivo',
        'director_nombre',
        'director_firma_snapshot_path',
        'promedio_general',
        'concepto_general',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'curso_anio_lectivo' => 'integer',
        'promedio_general' => 'integer',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(Director::class);
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleBoleta::class);
    }
}