<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\MatchGame;
use App\Models\Prediction;
use Carbon\Carbon;

class FootballApiService
{
    protected $apiKey;
    protected $baseUrl = 'https://v3.football.api-sports.io';

    public function __construct()
    {
        $this->apiKey = config('services.football_api.key');
    }

    /**
     * Sincroniza los partidos de una liga y temporada específica.
     */
    public function syncMatches($leagueId, $season)
    {
        $response = Http::withHeaders([
            'x-apisports-key' => $this->apiKey
        ])->get("{$this->baseUrl}/fixtures", [
            'league' => $leagueId,
            'season' => $season
        ]);

        if ($response->failed()) return false;

        $fixtures = $response->json()['response'];

        foreach ($fixtures as $fix) {
            $match = MatchGame::updateOrCreate(
                ['api_id' => $fix['fixture']['id']],
                [
                    'home_team' => $fix['teams']['home']['name'],
                    'away_team' => $fix['teams']['away']['name'],
                    'home_flag' => $fix['teams']['home']['logo'],
                    'away_flag' => $fix['teams']['away']['logo'],
                    'home_score' => $fix['goals']['home'],
                    'away_score' => $fix['goals']['away'],
                    'start_time' => Carbon::parse($fix['fixture']['date']),
                    'status' => $this->mapStatus($fix['fixture']['status']['short']),
                    'round' => $fix['league']['round'],
                ]
            );

            // Si el partido acaba de terminar, podrías disparar el cálculo de puntos aquí
        }

        return true;
    }

    protected function mapStatus($shortStatus)
    {
        return match ($shortStatus) {
            'NS' => 'scheduled',
            '1H', '2H', 'HT', 'ET', 'P' => 'live',
            'FT', 'AET', 'PEN' => 'finished',
            default => 'scheduled',
        };
    }
}
