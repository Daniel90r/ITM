<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Maestro extends Model
{
    protected $fillable = [
        'user_id',
        'nombre',
        'apellido',
        'telefono',
        'dui',
        'codigo',
        'activo',
    ];

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalleCursos(): HasMany
    {
        return $this->hasMany(DetalleCurso::class);
    }

    public function notasRegistradas(): HasMany
    {
        return $this->hasMany(Nota::class, 'registrado_por');
    }
}
