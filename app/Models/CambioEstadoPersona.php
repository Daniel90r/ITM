<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CambioEstadoPersona extends Model
{
    protected $table = 'cambios_estado_personas';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['estado_anterior' => 'boolean', 'estado_nuevo' => 'boolean', 'registrado_at' => 'datetime'];
    }

    public function administrador()
    {
        return $this->belongsTo(User::class, 'administrador_id');
    }
}
