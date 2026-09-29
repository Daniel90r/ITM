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

    const ESTADO_PROXIMO = 'PROXIMO';

    const ESTADO_ACTIVO = 'ACTIVO';

    const ESTADO_FINALIZADO = 'FINALIZADO';

    const ESTADO_SIN_FECHAS = 'SIN_FECHAS';

    const ESTADOS_EDITABLES = [self::ESTADO_PROXIMO, self::ESTADO_ACTIVO];

    protected $fillable = ['nombre', 'nivel', 'seccion', 'anio_lectivo', 'fecha_inicio', 'fecha_fin', 'activo', 'finalizado_manualmente', 'estado'];

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
            return self::ESTADO_FINALIZADO;
        }

        $hoy = now()->startOfDay();

        if ($this->fecha_fin && $hoy->gt($this->fecha_fin->copy()->endOfDay())) {
            return self::ESTADO_FINALIZADO;
        }

        if (in_array($this->estado, self::ESTADOS_EDITABLES, true)) {
            return $this->estado;
        }

        if (!$this->fecha_inicio || !$this->fecha_fin) {
            return self::ESTADO_SIN_FECHAS;
        }

        if ($hoy->lt($this->fecha_inicio->copy()->startOfDay())) {
            return self::ESTADO_PROXIMO;
        }

        return self::ESTADO_ACTIVO;
    }

    public function getEstadoEditableAttribute(): ?string
    {
        $estado = $this->estado_curso;

        return in_array($estado, self::ESTADOS_EDITABLES, true) ? $estado : null;
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

    /**
     * Cursos válidos para una NUEVA inscripción: solo los que están PRÓXIMOS.
     *
     * Es la contraparte en SQL de getEstadoCursoAttribute() === ESTADO_PROXIMO.
     * No basta con noFinalizado(): ese scope también deja pasar los cursos ACTIVOS.
     *
     * Un curso es PRÓXIMO si no está finalizado (ni por botón ni por fecha_fin
     * vencida) y además:
     *  - tiene estado = PROXIMO (seleccionado explícitamente desde Editar Curso), o
     *  - no tiene estado manual y su fecha_inicio todavía no ha llegado.
     */
    public function scopeProximo($query)
    {
        $hoy = now()->startOfDay();

        return $query->where('activo', true)
            ->where('finalizado_manualmente', false)
            ->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', $hoy);
            })
            ->where(function ($q) use ($hoy) {
                $q->where('estado', self::ESTADO_PROXIMO)
                    ->orWhere(function ($q) use ($hoy) {
                        $q->where(function ($q) {
                            $q->whereNull('estado')
                                ->orWhereNotIn('estado', self::ESTADOS_EDITABLES);
                        })
                            ->whereNotNull('fecha_inicio')
                            ->whereNotNull('fecha_fin')
                            ->where('fecha_inicio', '>', $hoy);
                    });
            });
    }

    /**
     * Regla única de elegibilidad para una NUEVA inscripción.
     *
     * Un curso solo admite inscripciones si está VISIBLE (activo) y en estado
     * PRÓXIMO. Es la contraparte en PHP de scopeProximo(): ese scope exige
     * `activo = true`, así que el guardinline que solo miraba el estado
     * dejaba pasar cursos inactivos manipulados.
     */
    public function admiteInscripcionPorEstado(): bool
    {
        return (bool) $this->activo
            && $this->estado_curso === self::ESTADO_PROXIMO;
    }

    /**
     * Un curso admite nuevas inscripciones solo si está PRÓXIMO, está activo
     * y además tiene cupo disponible.
     */
    public function admiteNuevaInscripcion(): bool
    {
        return $this->admiteInscripcionPorEstado()
            && $this->inscripciones()->aprobada()->count() < self::CUPOS_MAXIMO;
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
