<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    /**
     * Crea el administrador inicial SOLO en local/testing y solo si la
     * contraseña viene del entorno. Nunca se usa una contraseña por defecto:
     * una credencial conocida en el repositorio es una credencial conocida en
     * cualquier despliegue donde se corra este seeder.
     *
     * No modifica una cuenta existente (firstOrCreate por email).
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('AdminSeeder omitido: solo corre en local/testing.');

            return;
        }

        $password = env('ADMIN_SEED_PASSWORD');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException(
                'ADMIN_SEED_PASSWORD no está definida. Defina la contraseña del administrador en .env '
                . '(local/testing) antes de ejecutar este seeder. No se crea ninguna cuenta con contraseña por defecto.'
            );
        }

        User::firstOrCreate(['email' => 'admin@itm.com'], [
            'name'     => 'Administrador ITM',
            'password' => Hash::make($password),
            'role'     => 'admin',
        ]);
    }
}
