<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReactivacionInscripcion extends Model
{
    protected $table = 'reactivaciones_inscripcion';
    public $timestamps = false;
    protected $fillable = ['inscripcion_id', 'administrador_id', 'estado_anterior', 'estado_nuevo', 'reactivada_at', 'motivo'];
    protected $casts = ['reactivada_at' => 'datetime'];

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }

    public function administrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrador_id');
    }
}
