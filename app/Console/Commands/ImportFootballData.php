<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\League;
use App\Models\MatchGame;
use App\Models\Quiniela;
use Carbon\Carbon;

class ImportFootballData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:import-league {code : The league code (e.g. PL, PD, CL)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import matches and league info from Football-Data.org';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $code = strtoupper($this->argument('code'));
        $apiKey = env('FOOTBALL_API_KEY');

        if (!$apiKey) {
            $this->error('API Key not found in .env (FOOTBALL_API_KEY)');
            return 1;
        }

        // Fix SSL issue in Laragon Portable
        $certPath = 'C:\App_Quiniela\laragon\etc\ssl\cacert.pem';
        $httpOptions = file_exists($certPath) ? ['verify' => $certPath] : ['verify' => false];

        $this->info("Fetching data for league: $code...");

        // 1. Fetch Competition Info
        $response = Http::withOptions($httpOptions)
            ->withHeaders(['X-Auth-Token' => $apiKey])
            ->get("https://api.football-data.org/v4/competitions/$code");

        if ($response->failed()) {
            $this->error("Failed to fetch league info: " . $response->body());
            return 1;
        }

        $data = $response->json();
        
        $league = League::updateOrCreate(
            ['api_id' => $data['id']],
            [
                'name' => $data['name'],
                'code' => $data['code'],
                'image' => $data['emblem'],
                'type' => $data['type'] ?? 'LEAGUE', // LEAGUE | CUP
                'current_season' => $data['currentSeason']['startDate'] ? Carbon::parse($data['currentSeason']['startDate'])->year : 2025,
                'active' => true
            ]
        );

        $this->info("League updated: {$league->name} ({$league->type})");

        // 2. Fetch Matches
        $this->info("Fetching matches...");
        $matchesResponse = Http::withOptions($httpOptions)
            ->withHeaders(['X-Auth-Token' => $apiKey])
            ->get("https://api.football-data.org/v4/competitions/$code/matches");

        if ($matchesResponse->failed()) {
            $this->error("Failed to fetch matches.");
            return 1;
        }

        $matchesData = $matchesResponse->json()['matches'];
        $bar = $this->output->createProgressBar(count($matchesData));

        // Group by Stage and Matchday to create Quinielas correctly for Cups and Leagues
        $groupedMatches = collect($matchesData)->groupBy(function ($match) {
            $stage = $match['stage'];
            $matchday = $match['matchday'];
            
            if ($stage === 'LEAGUE_STAGE' || $stage === 'REGULAR_SEASON') {
                return "Jornada $matchday";
            }

            if ($stage === 'GROUP_STAGE') {
                return "Fase de Grupos - J$matchday";
            }
            
            // Para Copas/Eliminatorias: Mapear nombres profesionales
            $names = [
                'PRELIMINARY_ROUND' => 'Ronda Preliminar',
                'QUALIFICATION_ROUND_1' => 'Calificación 1',
                'QUALIFICATION_ROUND_2' => 'Calificación 2',
                'QUALIFICATION_ROUND_3' => 'Calificación 3',
                'PLAYOFF_ROUND' => 'Play-offs',
                'ROUND_OF_16' => 'Octavos de Final',
                'QUARTER_FINALS' => 'Cuartos de Final',
                'SEMI_FINALS' => 'Semifinales',
                'FINAL' => 'Gran Final',
            ];
            
            return $names[$stage] ?? ucwords(strtolower(str_replace('_', ' ', $stage)));
        });

        foreach ($groupedMatches as $quinielaName => $matches) {
            // Determine start and end date for the Quiniela (Jornada)
            $dates = collect($matches)->pluck('utcDate')->map(fn($d) => Carbon::parse($d));
            $startDate = $dates->min();
            $endDate = $dates->max();

            // Create or Update Quiniela
            $quiniela = Quiniela::updateOrCreate(
                [
                    'league_id' => $league->id,
                    'name' => $quinielaName
                ],
                [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            );

            foreach ($matches as $matchData) {
                // Lógica Pro para equipos no definidos (TBD)
                $homeName = $matchData['homeTeam']['shortName'] ?? $matchData['homeTeam']['name'] ?? 'Por definir';
                $awayName = $matchData['awayTeam']['shortName'] ?? $matchData['awayTeam']['name'] ?? 'Por definir';
                $homeFlag = $matchData['homeTeam']['crest'] ?? 'https://via.placeholder.com/150?text=TBD';
                $awayFlag = $matchData['awayTeam']['crest'] ?? 'https://via.placeholder.com/150?text=TBD';

                $status = strtolower($matchData['status']);
                if (in_array($matchData['status'], ['IN_PLAY', 'PAUSED'])) {
                    $status = 'live';
                } elseif ($matchData['status'] == 'FINISHED') {
                    $status = 'finished';
                } elseif (in_array($matchData['status'], ['SCHEDULED', 'TIMED'])) {
                    $status = 'scheduled';
                }

                MatchGame::updateOrCreate(
                    ['api_id' => (string) $matchData['id']],
                    [
                        'league_id' => $league->id,
                        'quiniela_id' => $quiniela->id, // Link to the Auto-Quiniela
                        'home_team' => $homeName,
                        'away_team' => $awayName,
                        'home_flag' => $matchData['homeTeam']['crest'],
                        'away_flag' => $matchData['awayTeam']['crest'],
                        'home_score' => $matchData['score']['fullTime']['home'],
                        'away_score' => $matchData['score']['fullTime']['away'],
                        'start_time' => Carbon::parse($matchData['utcDate'])->setTimezone('America/Mexico_City'),
                        'status' => $status,
                        'matchday' => $matchData['matchday'],
                        'round' => $matchData['stage']
                    ]
                );
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Import complete!");
    }
}
