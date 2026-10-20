<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetalleCurso extends Model
{
    use HasFactory;
    protected $fillable = ['curso_id', 'materia_id', 'maestro_id'];

    protected $casts = [
        'maestro_id' => 'integer',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }

    public function maestro(): BelongsTo
    {
        return $this->belongsTo(Maestro::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    public function asignacionesDocentes(): HasMany
    {
        return $this->hasMany(AsignacionDocente::class);
    }

    /**
     * ¿El maestro tiene una asignación VIGENTE de esta materia en este curso?
     *
     * Es la comprobación de autorización del lado del servidor: evita que un
     * maestro registre asistencia o notas de una materia que ya no imparte,
     * aunque manipule la petición.
     */
    public function esMateriaDelMaestro(int $maestroId): bool
    {
        return $this->asignacionesDocentes()
            ->where('maestro_id', $maestroId)
            ->activas()
            ->exists();
    }

    /**
     * Materias visibles en el detalle de un curso.
     *
     * Una materia del curso se muestra si:
     *  - está activa en el catálogo, o
     *  - está inactiva pero conserva actividad académica real (notas o asistencias).
     *
     * NO son evidencia de actividad académica:
     *  - la existencia del registro en detalle_cursos (se genera automáticamente
     *    al crear un curso, para todas las materias activas);
     *  - tener un maestro asignado;
     *  - tener asignaciones docentes registradas;
     *  - tener alumnos inscritos en la materia.
     *
     * Esa evidencia es administrativa, no académica: una materia pudo asignarse a
     * un maestro y nunca llegar a impartirse.
     */
    public function scopeVisibles($query)
    {
        return $query->where(function ($q) {
            $q->whereHas('materia', fn ($mq) => $mq->where('activa', true))
                ->orWhereHas('notas', fn ($nq) => $nq->conCalificacion())
                ->orWhereHas('asistencias');
        });
    }

    /**
     * Regla reutilizable: ¿esta materia tiene actividad académica real en el curso?
     *
     * Es la contraparte en memoria de scopeVisibles(). Requiere que las relaciones
     * 'notas' y 'asistencias' estén cargadas.
     */
    public function tieneActividadAcademica(): bool
    {
        return $this->asistencias->isNotEmpty()
            || $this->notas->contains(fn (Nota $nota) => $nota->tieneCalificacion());
    }
}
