<?php

namespace Database\Factories;

use App\Models\Maestro;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaestroFactory extends Factory
{
    protected $model = Maestro::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'user_id' => $user->id,
            'nombre' => $this->faker->firstName(),
            'apellido' => $this->faker->lastName(),
            'telefono' => $this->faker->numerify('####-####'),
            'dui' => $this->faker->unique()->numerify('########-#'),
            'codigo' => $this->faker->unique()->numerify('M-#####'),
            'activo' => true,
        ];
    }
}