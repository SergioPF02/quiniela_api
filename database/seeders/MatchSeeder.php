<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MatchSeeder extends Seeder
{
    public function run()
    {
        // 1. Borramos datos viejos
        DB::table('matches')->truncate();

        // 2. Insertamos partidos de prueba
        DB::table('matches')->insert([
            [
                'home_team' => 'México',
                'away_team' => 'Polonia',
                'home_flag' => '🇲🇽',
                'away_flag' => '🇵🇱',
                'start_time' => Carbon::now()->addDays(1), // Mañana
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ],
            [
                'home_team' => 'Argentina',
                'away_team' => 'Arabia Saudita',
                'home_flag' => '🇦🇷',
                'away_flag' => '🇸🇦',
                'start_time' => Carbon::now()->addDays(2),
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ],
            [
                'home_team' => 'Brasil',
                'away_team' => 'Serbia',
                'home_flag' => '🇧🇷',
                'away_flag' => '🇷🇸',
                'start_time' => Carbon::now()->subHours(2), // Hace 2 horas
                'status' => 'finished',
                'home_score' => 2,
                'away_score' => 0,
            ],
        ]);
    }
}
