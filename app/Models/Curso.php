<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Curso extends Model
{
    use HasFactory;

    const CUPOS_MAXIMO = 20;

    protected $fillable = ['nombre', 'nivel', 'seccion', 'anio_lectivo', 'fecha_inicio', 'fecha_fin', 'activo', 'finalizado_manualmente'];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'activo' => 'boolean',
            'finalizado_manualmente' => 'boolean',
        ];
    }

    public function getEstadoCursoAttribute(): string
    {
        if ($this->finalizado_manualmente) {
            return 'FINALIZADO';
        }

        if (!$this->fecha_inicio || !$this->fecha_fin) {
            return 'SIN_FECHAS';
        }

        $hoy = now()->startOfDay();

        if ($hoy->lt($this->fecha_inicio->copy()->startOfDay())) {
            return 'PROXIMO';
        }

        if ($hoy->gt($this->fecha_fin->copy()->endOfDay())) {
            return 'FINALIZADO';
        }

        return 'ACTIVO';
    }

    public function getEstaPorFinalizarAttribute(): bool
    {
        if (
            $this->estado_curso !== 'ACTIVO' ||
            !$this->fecha_fin
        ) {
            return false;
        }

        $hoy = now()->startOfDay();
        $fechaFin = $this->fecha_fin->startOfDay();

        $diasRestantes = $hoy->diffInDays($fechaFin, false);

        return $diasRestantes >= 0 && $diasRestantes <= 7;
    }

    public function getDiasParaFinalizarAttribute(): ?int
    {
        if (!$this->fecha_fin) {
            return null;
        }

        return max(
            0,
            now()->startOfDay()->diffInDays(
                $this->fecha_fin->startOfDay(),
                false
            )
        );
    }

    public function scopeNoFinalizado($query)
    {
        $hoy = now()->startOfDay();

        return $query->where('finalizado_manualmente', false)
            ->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', $hoy);
            });
    }

    public function getInscritosCountAttribute(): int
    {
        return $this->inscripciones()->aprobada()->count();
    }

    public function getCuposDisponiblesAttribute(): int
    {
        return max(0, self::CUPOS_MAXIMO - $this->inscritos_count);
    }

    public function getEstaLlenoAttribute(): bool
    {
        return $this->inscritos_count >= self::CUPOS_MAXIMO;
    }

    public function detalleCursos(): HasMany
    {
        return $this->hasMany(DetalleCurso::class);
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'inscripciones');
    }
}
