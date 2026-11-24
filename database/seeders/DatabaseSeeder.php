<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Usuarios de prueba: las claves se leen de .env (DEMO_*), no están en el código.
        User::create([
            'name' => 'Jefe de Caravana',
            'usuario' => 'jefe.caravana',
            'password' => env('DEMO_JEFE_CLAVE', 'cambiar'),
            'rol' => 'JEFE_CARAVANA',
        ]);
        User::create([
            'name' => 'Paje',
            'usuario' => 'paje',
            'password' => env('DEMO_PAJE_CLAVE', 'cambiar'),
            'rol' => 'PAJE',
        ]);
    }
}
