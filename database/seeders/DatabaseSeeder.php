<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Catálogo de Ligas Reales (Fuente: API-Football)
        $leagues = [
            [
                'name' => 'Liga MX',
                'api_id' => 262,
                'active' => true, // Activa por defecto
                'current_season' => 2023, // Temporada permitida en plan Free
                'image' => 'https://media.api-sports.io/football/leagues/262.png'
            ],
            [
                'name' => 'Premier League',
                'api_id' => 39,
                'active' => false,
                'current_season' => 2023,
                'image' => 'https://media.api-sports.io/football/leagues/39.png'
            ],
            [
                'name' => 'La Liga',
                'api_id' => 140,
                'active' => false,
                'current_season' => 2023,
                'image' => 'https://media.api-sports.io/football/leagues/140.png'
            ],
            [
                'name' => 'UEFA Champions League',
                'api_id' => 2,
                'active' => false,
                'current_season' => 2023,
                'image' => 'https://media.api-sports.io/football/leagues/2.png'
            ],
        ];

        foreach ($leagues as $data) {
            \App\Models\League::updateOrCreate(['api_id' => $data['api_id']], $data);
        }

        // Llama al Seeder de Quinielas
        $this->call([
            QuinielaSeeder::class,
        ]);

        // Usuario Admin
        \App\Models\User::firstOrCreate(
            ['email' => 'admin@quinielapro.com'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('password123')
            ]
        );
    }
}
