<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\MatchGame;
use App\Models\Prediction;
use App\Models\Quiniela;
use App\Models\League;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class TestRankingIsolation extends Command
{
    protected $signature = 'test:ranking-isolation';
    protected $description = 'Verifica que los puntos de una jornada NO se sumen a otra.';

    public function handle()
    {
        $this->info("🧪 INICIANDO PRUEBA DE AISLAMIENTO DE RANKINGS");
        $this->info("---------------------------------------------");

        // 1. LIMPIEZA
        User::where('email', 'like', 'user_jornada_%')->delete();
        League::where('code', 'TEST-ISO')->delete(); // Esto borra quinielas y partidos en cascada si está configurado, sino lo haremos manual
        Quiniela::where('name', 'like', 'Quiniela Test %')->delete();

        // 2. SETUP
        // Crear Liga Dummy
        $league = League::create([
            'name' => 'Liga de Prueba Aislamiento',
            'code' => 'TEST-ISO',
            'api_id' => 777777,
            'active' => true
        ]);

        // Crear 2 Quinielas (Jornadas)
        $jornadaA = Quiniela::create(['league_id' => $league->id, 'name' => 'Quiniela Test A', 'start_date' => now(), 'end_date' => now()]);
        $jornadaB = Quiniela::create(['league_id' => $league->id, 'name' => 'Quiniela Test B', 'start_date' => now(), 'end_date' => now()]);

        // Crear 1 Partido por Jornada
        $matchA = MatchGame::create(['league_id' => $league->id, 'quiniela_id' => $jornadaA->id, 'api_id' => 77001, 'home_team' => 'A1', 'away_team' => 'A2', 'start_time' => now(), 'status' => 'finished', 'home_score' => 1, 'away_score' => 0, 'matchday' => 1]);
        $matchB = MatchGame::create(['league_id' => $league->id, 'quiniela_id' => $jornadaB->id, 'api_id' => 77002, 'home_team' => 'B1', 'away_team' => 'B2', 'start_time' => now(), 'status' => 'finished', 'home_score' => 1, 'away_score' => 0, 'matchday' => 2]);

        // Crear 2 Usuarios
        $userA = User::create(['name' => 'Rey Jornada A', 'email' => 'user_jornada_a@test.com', 'password' => Hash::make('123')]);
        $userB = User::create(['name' => 'Rey Jornada B', 'email' => 'user_jornada_b@test.com', 'password' => Hash::make('123')]);

        $this->info("✅ Entorno creado: 2 Jornadas, 2 Partidos, 2 Usuarios.");

        // 3. APUESTAS (CRÍTICO)
        
        // --- JORNADA A ---
        // User A acierta (Local), User B falla (Visitante)
        Prediction::create(['user_id' => $userA->id, 'match_id' => $matchA->id, 'predicted_home' => 1, 'predicted_away' => 0]);
        Prediction::create(['user_id' => $userB->id, 'match_id' => $matchA->id, 'predicted_home' => 0, 'predicted_away' => 1]);

        // --- JORNADA B ---
        // User A falla (Visitante), User B acierta (Local)
        Prediction::create(['user_id' => $userA->id, 'match_id' => $matchB->id, 'predicted_home' => 0, 'predicted_away' => 1]);
        Prediction::create(['user_id' => $userB->id, 'match_id' => $matchB->id, 'predicted_home' => 1, 'predicted_away' => 0]);

        $this->info("🎲 Apuestas realizadas.");

        // 4. CALCULAR PUNTOS
        Artisan::call('calculate:points');
        $this->info("🧮 Puntos calculados.");

        // 5. VERIFICACIÓN DEL RANKING
        
        // --- CHECK JORNADA A ---
        $this->info("\n📊 Revisando Ranking JORNADA A...");
        // Simulamos la consulta del RankingController
        $rankingA = $this->getRankingForQuiniela($jornadaA->id);
        
        $winnerA = $rankingA->firstWhere('name', 'Rey Jornada A');
        $loserA = $rankingA->firstWhere('name', 'Rey Jornada B');

        if ($winnerA['points'] == 1 && $loserA['points'] == 0) {
            $this->info("   ✅ OK: En Jornada A, User A tiene 1 pto y User B tiene 0.");
        } else {
            $this->error("   ❌ ERROR en JORNADA A: Datos incorrectos.");
        }

        // --- CHECK JORNADA B (LA PRUEBA DE FUEGO) ---
        $this->info("\n📊 Revisando Ranking JORNADA B...");
        $rankingB = $this->getRankingForQuiniela($jornadaB->id);
        
        $winnerB = $rankingB->firstWhere('name', 'Rey Jornada B');
        $loserB = $rankingB->firstWhere('name', 'Rey Jornada A'); // Este ganó en la anterior, AQUÍ debe tener 0

        if ($winnerB['points'] == 1 && $loserB['points'] == 0) {
            $this->info("   ✅ OK: En Jornada B, User B tiene 1 pto.");
            $this->info("   ✅ AISLAMIENTO CONFIRMADO: User A tiene 0 puntos en esta jornada (no se trajo el punto de la anterior).");
        } else {
            $this->error("   ❌ ERROR en JORNADA B: Los puntos se mezclaron.");
            $this->line("      User A tiene " . $loserB['points'] . " puntos (Debería tener 0).");
        }

        // Limpieza final
        // User::where('email', 'like', 'user_jornada_%')->delete();
        // League::where('code', 'TEST-ISO')->delete();
    }

    private function getRankingForQuiniela($quinielaId)
    {
        // Misma lógica exacta que RankingController
        $users = User::whereHas('predictions', function ($query) use ($quinielaId) {
            $query->whereNull('private_league_id')
                  ->whereHas('matchGame', function ($subQ) use ($quinielaId) {
                      $subQ->where('quiniela_id', $quinielaId);
                  });
        })->with(['predictions' => function($q) use ($quinielaId) {
            $q->whereNull('private_league_id')
              ->whereHas('matchGame', function($subQ) use ($quinielaId) {
                $subQ->where('quiniela_id', $quinielaId);
            });
        }])->get();

        return $users->map(function ($user) {
            return [
                'name' => $user->name,
                'points' => (int) $user->predictions->sum('points'),
            ];
        });
    }
}
