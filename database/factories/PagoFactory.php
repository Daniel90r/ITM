<?php

namespace Database\Factories;

use App\Models\Pago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    public function definition(): array
    {
        return [
            'mensualidad_id' => MensualidadFactory::new(),
            'fecha_pago' => now()->toDateString(),
            'monto_pagado' => fake()->randomFloat(2, 100, 500),
            'observacion' => fake()->sentence(),
        ];
    }
}
