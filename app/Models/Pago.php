<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $table = 'pagos';

    protected $fillable = [
        'mensualidad_id',
        'fecha_pago',
        'monto_pagado',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_pago' => 'date',
            'monto_pagado' => 'decimal:2',
        ];
    }

    public function mensualidad()
    {
        return $this->belongsTo(Mensualidad::class);
    }
}
