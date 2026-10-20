<?php

namespace Database\Factories;

use App\Models\Director;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DirectorFactory extends Factory
{
    protected $model = Director::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'user_id' => $user->id,
            'nombre' => $this->faker->name(),
            'dui' => $this->faker->numerify('########-#'),
            'telefono' => $this->faker->numerify('####-####'),
            'email' => $this->faker->unique()->safeEmail(),
            'firma_path' => null,
            'fecha_inicio' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'fecha_fin' => null,
            'activo' => true,
        ];
    }
}