<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MatchGame;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CalculatePoints extends Command
{
    protected $signature = 'calculate:points {--match= : ID de un partido específico}';
    protected $description = 'Calcula y actualiza los puntos de los usuarios basados en sus pronósticos';

    public function handle()
    {
        $matchId = $this->option('match');

        if ($matchId) {
            $matches = MatchGame::where('id', $matchId)->where('status', 'finished')->get();
        } else {
            $matches = MatchGame::where('status', 'finished')->get();
        }

        if ($matches->isEmpty()) {
            $this->info("No hay partidos terminados para procesar.");
            return;
        }

        $this->info("Procesando puntos para " . $matches->count() . " partidos...");

        foreach ($matches as $match) {
            $this->calculateForMatch($match);
        }

        $this->info("✅ Proceso completado.");
    }

    private function calculateForMatch($match)
    {
        $predictions = Prediction::where('match_id', $match->id)->get();

        foreach ($predictions as $prediction) {
            $points = 0;

            // El resultado real se basa en los goles finales (home_score / away_score)
            // que la API ya nos da procesados como el resultado final del encuentro.
            $realResult = $this->getWinner($match->home_score, $match->away_score);
            $predResult = $this->getWinner($prediction->predicted_home, $prediction->predicted_away);

            if ($realResult === $predResult) {
                $points = 1;
            }

            $prediction->points = $points;
            $prediction->save();
        }

        $this->line("   - Partido ID {$match->id} ({$match->home_team} vs {$match->away_team}): " . $predictions->count() . " pronósticos procesados.");
    }

    private function getWinner($home, $away)
    {
        if ($home > $away) return 'home';
        if ($away > $home) return 'away';
        return 'draw';
    }
}