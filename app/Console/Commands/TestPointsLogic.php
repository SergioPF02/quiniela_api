<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\MatchGame;
use App\Models\Prediction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class TestPointsLogic extends Command
{
    protected $signature = 'test:logic';
    protected $description = 'Verifica que la lógica de puntos sea 100% correcta (1 punto por resultado).';

    public function handle()
    {
        $this->info("🧪 INICIANDO PRUEBA DE INTEGRIDAD DE PUNTOS...");

        // 1. LIMPIEZA PREVIA (Por si acaso)
        // Borramos usuarios de prueba anteriores si quedaron
        User::where('email', 'like', 'test_user_%@test.com')->delete();

        // 2. PREPARACIÓN: Crear un partido simulado terminado (GANA LOCAL 2-1)
        $match = MatchGame::create([
            'api_id' => 888888,
            'home_team' => 'Equipo Test Local',
            'away_team' => 'Equipo Test Visitante',
            'home_score' => 2, // GANO LOCAL
            'away_score' => 1,
            'status' => 'finished',
            'start_time' => now()->subHours(2),
            'matchday' => 1
        ]);

        $this->info("Match Creado ID: {$match->id} (Resultado: 2-1 LOCAL)");

        // Crear Usuarios Dummy
        $userWinner = User::create(['name' => 'Winner', 'email' => 'test_user_w@test.com', 'password' => Hash::make('123')]);
        $userLoserAway = User::create(['name' => 'LoserAway', 'email' => 'test_user_la@test.com', 'password' => Hash::make('123')]);
        $userLoserDraw = User::create(['name' => 'LoserDraw', 'email' => 'test_user_ld@test.com', 'password' => Hash::make('123')]);

        // 3. CREAR PREDICCIONES
        
        // Usuario A: Predice GANA LOCAL (1-0) -> Acierta resultado
        Prediction::create([
            'user_id' => $userWinner->id,
            'match_id' => $match->id,
            'predicted_home' => 1,
            'predicted_away' => 0,
            'points' => 0
        ]);

        // Usuario B: Predice GANA VISITANTE (0-1) -> Falla
        Prediction::create([
            'user_id' => $userLoserAway->id,
            'match_id' => $match->id,
            'predicted_home' => 0,
            'predicted_away' => 1,
            'points' => 0
        ]);

        // Usuario C: Predice EMPATE (2-2) -> Falla
        Prediction::create([
            'user_id' => $userLoserDraw->id,
            'match_id' => $match->id,
            'predicted_home' => 2,
            'predicted_away' => 2,
            'points' => 0
        ]);

        $this->info("Predicciones Creadas. Ejecutando cálculo...");

        // 4. EJECUCIÓN
        Artisan::call('calculate:points', ['--match' => $match->id]);

        // 5. VERIFICACIÓN (ASSERTIONS)
        $errors = 0;

        // Verificar Usuario A (Ganador)
        $pointsA = Prediction::where('user_id', $userWinner->id)->where('match_id', $match->id)->value('points');
        if ($pointsA === 1) {
            $this->info("✅ PRUEBA 1 PASÓ: Usuario que acertó ganador recibió 1 punto.");
        } else {
            $this->error("❌ PRUEBA 1 FALLÓ: Usuario que acertó ganador recibió $pointsA puntos (Esperado: 1).");
            $errors++;
        }

        // Verificar Usuario B (Perdedor - Visitante)
        $pointsB = Prediction::where('user_id', $userLoserAway->id)->where('match_id', $match->id)->value('points');
        if ($pointsB === 0) {
            $this->info("✅ PRUEBA 2 PASÓ: Usuario que apostó al perdedor recibió 0 puntos.");
        } else {
            $this->error("❌ PRUEBA 2 FALLÓ: Usuario que apostó al perdedor recibió $pointsB puntos (Esperado: 0).");
            $errors++;
        }

        // Verificar Usuario C (Perdedor - Empate)
        $pointsC = Prediction::where('user_id', $userLoserDraw->id)->where('match_id', $match->id)->value('points');
        if ($pointsC === 0) {
            $this->info("✅ PRUEBA 3 PASÓ: Usuario que apostó empate recibió 0 puntos.");
        } else {
            $this->error("❌ PRUEBA 3 FALLÓ: Usuario que apostó empate recibió $pointsC puntos (Esperado: 0).");
            $errors++;
        }

        // 6. LIMPIEZA
        Prediction::where('match_id', $match->id)->delete();
        $match->delete();
        $userWinner->delete();
        $userLoserAway->delete();
        $userLoserDraw->delete();

        if ($errors === 0) {
            $this->info("\n🎉 CONCLUSIÓN: EL SISTEMA DE PUNTOS ES 100% CONFIABLE Y SEGURO.");
        } else {
            $this->error("\n⚠️ ATENCIÓN: SE DETECTARON ERRORES EN EL CÁLCULO.");
        }
    }
}
