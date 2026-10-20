<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nota extends Model
{
    use HasFactory;
    protected $fillable = [
        'alumno_id', 'detalle_curso_id',
        'laboratorio', 'examen_teorico', 'practica', 'conducta',
        'promedio', 'registrado_por'
    ];

    protected $casts = [
        'laboratorio'    => 'float',
        'examen_teorico' => 'float',
        'practica'       => 'float',
    ];

    public function calcularPromedio(): ?int
    {
        $notas = array_filter([
            $this->laboratorio,
            $this->examen_teorico,
            $this->practica,
        ], fn($n) => !is_null($n));

        if (empty($notas)) return null;

        return (int) round(array_sum($notas) / count($notas));
    }

    protected static function booted(): void
    {
        static::saving(function (Nota $nota) {
            $nota->promedio = $nota->calcularPromedio();
        });
    }

    /**
     * Columnas que representan una calificación realmente capturada.
     * 'promedio' se excluye porque lo calcula el modelo a partir de las otras.
     */
    public const COLUMNAS_CALIFICACION = [
        'laboratorio', 'examen_teorico', 'practica', 'conducta',
    ];

    /**
     * Una fila de notas cuenta como actividad académica solo si tiene al menos
     * una calificación capturada. El flujo de captura usa updateOrCreate por alumno,
     * por lo que pueden existir filas con todas las notas en NULL.
     */
    public function scopeConCalificacion($query)
    {
        return $query->where(function ($q) {
            foreach (self::COLUMNAS_CALIFICACION as $columna) {
                $q->orWhereNotNull($columna);
            }
        });
    }

    public function tieneCalificacion(): bool
    {
        foreach (self::COLUMNAS_CALIFICACION as $columna) {
            if ($this->{$columna} !== null && $this->{$columna} !== '') {
                return true;
            }
        }

        return false;
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function detalleCurso(): BelongsTo
    {
        return $this->belongsTo(DetalleCurso::class);
    }

    public function maestro(): BelongsTo
    {
        return $this->belongsTo(Maestro::class, 'registrado_por');
    }
}