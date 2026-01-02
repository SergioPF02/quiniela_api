<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Quiniela;
use App\Models\League;
use Carbon\Carbon;

class QuinielaSeeder extends Seeder
{
    public function run()
    {
        // Buscamos la Liga MX creada en DatabaseSeeder
        $ligaMx = League::where('api_id', 262)->first();

        if ($ligaMx) {
            Quiniela::firstOrCreate(
                ['name' => 'Clausura 2025'],
                [
                    'league_id' => $ligaMx->id,
                    'start_date' => Carbon::now(),
                    'end_date' => Carbon::now()->addMonths(6),
                ]
            );
            
            Quiniela::firstOrCreate(
                ['name' => 'Jornada 1 - Clausura 2025'],
                [
                    'league_id' => $ligaMx->id,
                    'start_date' => Carbon::now(),
                    'end_date' => Carbon::now()->addDays(7),
                ]
            );
        }
    }
}
