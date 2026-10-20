<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Inscripcion extends Model
{
    use HasFactory;
    protected $table = 'inscripciones';
    // Las tareas CLI que usan Eloquent comparten la barrera de las peticiones HTTP.
    public function save(array $options = [])
    {
        return app(\App\Services\ControlTransicionDocumental::class)->operacion(fn () => parent::save($options));
    }

    public function delete()
    {
        return app(\App\Services\ControlTransicionDocumental::class)->operacion(fn () => parent::delete());
    }

    protected $fillable = [
        'alumno_id', 'curso_id', 'fecha_inscripcion',
        'activa', 'estado', 'documento_path', 'observacion', 'motivo_rechazo', 'tomara_ingles'
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function reactivacion(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ReactivacionInscripcion::class);
    }

    public function documentos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentoInscripcion::class)->orderBy('version');
    }

    public function documentoVigente(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DocumentoInscripcion::class)->where('vigente', '1');
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════════
    // ESTADOS: PENDIENTE · APROBADA · RECHAZADA · INACTIVA
    // La inscripción EFECTIVA (alumno contado como inscrito) es únicamente estado = APROBADA.
    // ═══════════════════════════════════════════════════════════════════════════════════════════

    public function scopeAprobada($query)
    {
        return $query->where('estado', 'aprobada');
    }

    public function scopePendiente($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeRechazada($query)
    {
        return $query->where('estado', 'rechazada');
    }

    public function getEstaAprobadaAttribute(): bool
    {
        return $this->estado === 'aprobada';
    }

    /**
     * Un alumno solo puede tener UNA solicitud pendiente o UNA inscripción aprobada a la vez.
     * PENDIENTE o APROBADA → bloquea nueva solicitud. RECHAZADA → permite solicitar otra.
     */
    public static function bloqueaNuevaSolicitud(int $alumnoId): bool
    {
        return self::where('alumno_id', $alumnoId)
            ->whereIn('estado', ['pendiente', 'aprobada'])
            ->exists();
    }

    public static function tieneAprobada(int $alumnoId): bool
    {
        return self::where('alumno_id', $alumnoId)
            ->where('estado', 'aprobada')
            ->exists();
    }

    public static function tienePendiente(int $alumnoId, ?int $ignorarId = null): bool
    {
        return self::where('alumno_id', $alumnoId)
            ->where('estado', 'pendiente')
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->exists();
    }
}
