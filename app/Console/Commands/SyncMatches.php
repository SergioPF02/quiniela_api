<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\MatchGame;
use App\Models\Quiniela;
use App\Models\League;
use Carbon\Carbon;

class SyncMatches extends Command
{
    protected $signature = 'sync:matches';
    protected $description = 'Descarga partidos reales de la API-Football y los organiza por jornadas/fases';

    public function handle()
    {
        $apiKey = config('services.football_api.key');

        if (!$apiKey) {
            $this->error("❌ No se encontró la API Key en el archivo .env (FOOTBALL_API_KEY)");
            return;
        }

        $activeLeagues = League::where('active', true)->whereNotNull('api_id')->get();

        if ($activeLeagues->isEmpty()) {
            $this->warn("⚠️ No hay ligas activas configuradas.");
            return;
        }

        $this->info("🔄 Iniciando sincronización de " . $activeLeagues->count() . " ligas...");

        foreach ($activeLeagues as $league) {
            $this->syncLeague($league, $apiKey);
        }
        
        $this->info("🚀 Sincronización Global Finalizada.");
    }

    private function syncLeague(League $league, $apiKey)
    {
        $this->line("📡 Descargando: {$league->name}...");

        $response = Http::withoutVerifying()->withHeaders([
            'x-rapidapi-host' => 'v3.football.api-sports.io',
            'x-rapidapi-key' => $apiKey
        ])->get('https://v3.football.api-sports.io/fixtures', [
            'league' => $league->api_id,
            'season' => $league->current_season
        ]);

        $json = $response->json();

        if (isset($json['errors']) && count($json['errors']) > 0) {
            $this->error("❌ Error en {$league->name}: " . json_encode($json['errors']));
            return;
        }

        $matches = $json['response'] ?? [];
        
        if (count($matches) === 0) {
            $this->warn("   ⚠️ Sin partidos encontrados.");
            return;
        }

        $this->info("   ✅ Procesando " . count($matches) . " partidos...");

        foreach ($matches as $data) {
            $fixture = $data['fixture'];
            $teams = $data['teams'];
            $goals = $data['goals'];
            $leagueData = $data['league'];
            $statusRaw = $fixture['status']['short']; 

            // DETERMINAR LA QUINIELA (JORNADA/FASE)
            // Usamos el 'round' de la API para separar (ej: Group Stage - 1, Round of 16, etc.)
            $roundName = $leagueData['round'];
            
            $quiniela = Quiniela::firstOrCreate(
                [
                    'league_id' => $league->id,
                    'name' => $roundName // Se llamará como la fase (ej: "Round of 16")
                ],
                [
                    'start_date' => Carbon::parse($fixture['date'])->startOfDay(),
                    'end_date' => Carbon::parse($fixture['date'])->endOfDay()->addDays(7)
                ]
            );

            // Mapeo de estatus
            $myStatus = 'scheduled';
            if (in_array($statusRaw, ['FT', 'AET', 'PEN'])) {
                $myStatus = 'finished';
            } elseif (in_array($statusRaw, ['1H', '2H', 'HT', 'ET', 'P'])) {
                $myStatus = 'live';
            }

            MatchGame::updateOrCreate(
                ['api_id' => $fixture['id']], 
                [
                    'quiniela_id' => $quiniela->id,
                    'league_id' => $league->id,
                    'round' => $roundName,
                    'home_team' => $teams['home']['name'],
                    'away_team' => $teams['away']['name'],
                    'home_flag' => $teams['home']['logo'],
                    'away_flag' => $teams['away']['logo'],
                    'home_score' => $goals['home'], 
                    'away_score' => $goals['away'], 
                    'start_time' => Carbon::parse($fixture['date']),
                    'status' => $myStatus
                ]
            );
        }

        $this->info("⚙️ Recalculando puntos...");
        $this->call('calculate:points');
    }
}