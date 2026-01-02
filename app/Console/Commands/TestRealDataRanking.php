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

class TestRealDataRanking extends Command
{
    protected $signature = 'test:real-data-ranking';
    protected $description = 'Prueba de aislamiento usando Ligas y Jornadas REALES (Champions/Mundial).';

    public function handle()
    {
        $this->info("🏟️ INICIANDO PRUEBA CON DATOS REALES (PREDEFINIDOS)");
        $this->info("---------------------------------------------------- ");

        // 1. BUSCAR DATOS REALES
        // Buscamos la Champions (CL) o el Mundial (WC)
        $league = League::whereIn('code', ['CL', 'WC'])->whereHas('quinielas')->first();

        if (!$league) {
            $this->error("❌ No encontré la Champions ni el Mundial con quinielas cargadas.");
            return;
        }

        $this->info("✅ Liga Real Seleccionada: " . $league->name);

        // Obtener 2 Quinielas (Jornadas) Reales que tengan partidos
        $quinielas = $league->quinielas()->whereHas('matches')->take(2)->get();

        if ($quinielas->count() < 2) {
            $this->error("❌ Necesito al menos 2 jornadas reales con partidos para esta prueba.");
            return;
        }

        $realJornadaA = $quinielas[0];
        $realJornadaB = $quinielas[1];

        $this->info("📅 Jornada A Real: " . $realJornadaA->name . " (ID: {$realJornadaA->id})");
        $this->info("📅 Jornada B Real: " . $realJornadaB->name . " (ID: {$realJornadaB->id})");

        // Obtener 1 Partido Real de cada una
        $matchA = $realJornadaA->matches()->first();
        $matchB = $realJornadaB->matches()->first();

        $this->info("⚽ Partido Real A: {$matchA->home_team} vs {$matchA->away_team} (ID: {$matchA->id})");
        $this->info("⚽ Partido Real B: {$matchB->home_team} vs {$matchB->away_team} (ID: {$matchB->id})");

        // 2. GUARDAR ESTADO ORIGINAL (SNAPSHOT)
        // Para restaurarlos después de la prueba
        $originalStatusA = $matchA->status;
        $originalScoreHomeA = $matchA->home_score;
        $originalScoreAwayA = $matchA->away_score;
        
        $originalStatusB = $matchB->status;
        $originalScoreHomeB = $matchB->home_score;
        $originalScoreAwayB = $matchB->away_score;

        // 3. CREAR USUARIOS DE PRUEBA
        $userA = User::create(['name' => 'Experto Jornada 1', 'email' => 'real_test_1@test.com', 'password' => Hash::make('123')]);
        $userB = User::create(['name' => 'Experto Jornada 2', 'email' => 'real_test_2@test.com', 'password' => Hash::make('123')]);

        try {
            // 4. SIMULAR RESULTADOS (FORZAR PARTIDOS REALES A "finished")
            $this->info("\n🔧 Simulando que los partidos reales ya se jugaron...");
            
            // Partido A: Gana Local (1-0)
            $matchA->update(['status' => 'finished', 'home_score' => 1, 'away_score' => 0]);
            
            // Partido B: Gana Local (1-0)
            $matchB->update(['status' => 'finished', 'home_score' => 1, 'away_score' => 0]);

            // 5. HACER APUESTAS
            $this->info("🎲 Usuarios apostando en las jornadas oficiales...");

            // -- JORNADA A --
            // User A acierta (Local), User B falla (Visitante)
            Prediction::create(['user_id' => $userA->id, 'match_id' => $matchA->id, 'predicted_home' => 1, 'predicted_away' => 0]);
            Prediction::create(['user_id' => $userB->id, 'match_id' => $matchA->id, 'predicted_home' => 0, 'predicted_away' => 1]);

            // -- JORNADA B --
            // User A falla (Visitante), User B acierta (Local)
            Prediction::create(['user_id' => $userA->id, 'match_id' => $matchB->id, 'predicted_home' => 0, 'predicted_away' => 1]);
            Prediction::create(['user_id' => $userB->id, 'match_id' => $matchB->id, 'predicted_home' => 1, 'predicted_away' => 0]);

            // 6. CALCULAR PUNTOS
            $this->info("🧮 Ejecutando cálculo de puntos en el sistema...");
            Artisan::call('calculate:points');

            // 7. VERIFICAR RANKING EN LOS DATOS REALES
            
            // --- CHECK JORNADA A ---
            $this->info("\n📊 Ranking de '{$realJornadaA->name}' (ID: {$realJornadaA->id})");
            $rankingA = $this->getRankingForQuiniela($realJornadaA->id);
            
            $winnerA = $rankingA->firstWhere('name', 'Experto Jornada 1');
            $loserA = $rankingA->firstWhere('name', 'Experto Jornada 2');

            if ($winnerA['points'] == 1 && $loserA['points'] == 0) {
                $this->info("   ✅ CORRECTO: Experto 1 tiene 1 pto, Experto 2 tiene 0.");
            } else {
                $this->error("   ❌ FALLO: Los puntos no coinciden.");
            }

            // --- CHECK JORNADA B ---
            $this->info("\n📊 Ranking de '{$realJornadaB->name}' (ID: {$realJornadaB->id})");
            $rankingB = $this->getRankingForQuiniela($realJornadaB->id);
            
            $winnerB = $rankingB->firstWhere('name', 'Experto Jornada 2');
            $loserB = $rankingB->firstWhere('name', 'Experto Jornada 1'); // Debería tener 0 aunque ganó en la anterior

            if ($winnerB['points'] == 1 && $loserB['points'] == 0) {
                $this->info("   ✅ CORRECTO: Experto 2 tiene 1 pto.");
                $this->info("   ✅ AISLAMIENTO VERIFICADO: Experto 1 tiene 0 puntos aquí (sus puntos de la Jornada A no se mezclaron).");
            } else {
                $this->error("   ❌ FALLO: Se detectó mezcla de puntos.");
                $this->line("      Experto 1 tiene " . $loserB['points'] . " puntos.");
            }

        } catch (
Exception $e) {
            $this->error("🔥 Error durante la prueba: " . $e->getMessage());
        } finally {
            // 8. RESTAURACIÓN (ROLLBACK MANUAL)
            $this->info("\n🧹 Restaurando datos originales...");
            
            // Restaurar Partidos
            $matchA->update(['status' => $originalStatusA, 'home_score' => $originalScoreHomeA, 'away_score' => $originalScoreAwayA]);
            $matchB->update(['status' => $originalStatusB, 'home_score' => $originalScoreHomeB, 'away_score' => $originalScoreAwayB]);
            
            // Borrar Predicciones de prueba
            Prediction::whereIn('user_id', [$userA->id, $userB->id])->delete();
            
            // Borrar Usuarios de prueba
            $userA->delete();
            $userB->delete();

            $this->info("✅ Base de datos restaurada. Todo limpio.");
        }
    }

    private function getRankingForQuiniela($quinielaId)
    {
        // Lógica de RankingController replicada para verificación
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
