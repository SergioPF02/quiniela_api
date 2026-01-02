<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\MatchGame;
use App\Models\Prediction;
use Illuminate\Support\Facades\Artisan;

class PointsLogicTest extends TestCase
{
    // Usamos RefreshDatabase para que la prueba se haga en una DB limpia y no toque tus datos reales
    // NOTA: Como usas MySQL/Postgres local, esto limpiará las tablas temporalmente. 
    // Si tienes datos importantes, Laravel usa transacciones para revertirlo, pero asegúrate de estar en entorno local.
    // Para mayor seguridad en esta prueba en vivo, NO usaré RefreshDatabase sino que crearé y borraré manualmente.

    public function test_points_calculation_rules()
    {
        // 1. PREPARACIÓN: Crear un partido simulado terminado (GANA LOCAL 2-1)
        $match = MatchGame::create([
            'api_id' => 999999,
            'home_team' => 'Equipo Test Local',
            'away_team' => 'Equipo Test Visitante',
            'home_score' => 2, // GANO LOCAL
            'away_score' => 1,
            'status' => 'finished',
            'start_time' => now()->subHours(2),
            'matchday' => 1
        ]);

        // Crear Usuarios Dummy
        $userWinner = User::factory()->create();
        $userLoserAway = User::factory()->create();
        $userLoserDraw = User::factory()->create();

        // 2. CREAR PREDICCIONES
        
        // Usuario A: Predice GANA LOCAL (Acierta resultado)
        // Nota: Aunque predice 1-0 y fue 2-1, el RESULTADO es el mismo (Local). Debe ganar 1 punto.
        Prediction::create([
            'user_id' => $userWinner->id,
            'match_id' => $match->id,
            'predicted_home' => 1,
            'predicted_away' => 0,
            'points' => 0 // Inicialmente 0
        ]);

        // Usuario B: Predice GANA VISITANTE (Falla)
        Prediction::create([
            'user_id' => $userLoserAway->id,
            'match_id' => $match->id,
            'predicted_home' => 0,
            'predicted_away' => 1,
            'points' => 0
        ]);

        // Usuario C: Predice EMPATE (Falla)
        Prediction::create([
            'user_id' => $userLoserDraw->id,
            'match_id' => $match->id,
            'predicted_home' => 2,
            'predicted_away' => 2,
            'points' => 0
        ]);

        // 3. EJECUCIÓN: Correr el comando de cálculo
        Artisan::call('calculate:points', ['--match' => $match->id]);

        // 4. VERIFICACIÓN (ASSERTIONS)

        // Verificar Usuario A (Ganador)
        $pointsA = Prediction::where('user_id', $userWinner->id)->where('match_id', $match->id)->value('points');
        $this->assertEquals(1, $pointsA, "El Usuario A debió recibir 1 punto por acertar al Ganador Local.");

        // Verificar Usuario B (Perdedor - Visitante)
        $pointsB = Prediction::where('user_id', $userLoserAway->id)->where('match_id', $match->id)->value('points');
        $this->assertEquals(0, $pointsB, "El Usuario B debió recibir 0 puntos (Apostó Visitante, Ganó Local).");

        // Verificar Usuario C (Perdedor - Empate)
        $pointsC = Prediction::where('user_id', $userLoserDraw->id)->where('match_id', $match->id)->value('points');
        $this->assertEquals(0, $pointsC, "El Usuario C debió recibir 0 puntos (Apostó Empate, Ganó Local).");

        // 5. LIMPIEZA
        Prediction::where('match_id', $match->id)->delete();
        $match->delete();
        $userWinner->delete();
        $userLoserAway->delete();
        $userLoserDraw->delete();
        
        echo "\n\n✅ PRUEBA DE LÓGICA DE PUNTOS EXITOSA: El sistema calcula correctamente (Solo 1 punto por resultado).\n";
    }
}
