<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CursoHorario extends Model
{
    protected $table = 'curso_horarios';
    protected $fillable = ['dia_semana', 'hora_inicio', 'hora_fin'];
    protected $casts = ['dia_semana' => 'integer'];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
