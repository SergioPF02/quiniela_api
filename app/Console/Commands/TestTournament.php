<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\MatchGame;
use App\Models\Prediction;
use App\Models\Quiniela;

class TestTournament extends Command
{
    protected $signature = 'test:tournament';
    protected $description = 'Simula un torneo con 10 usuarios para probar desempates';

    public function handle()
    {
        $this->info("🏟️  Iniciando Simulación del Torneo...");

        // 1. Limpiar datos de prueba anteriores
        Prediction::truncate();
        User::where('email', 'like', '%@simulacro.com')->delete();

        // 2. Obtener partidos reales terminados (de la sincro anterior)
        $matches = MatchGame::where('status', 'finished')->take(5)->get();
        
        if ($matches->count() < 3) {
            $this->error("❌ Necesito al menos 3 partidos terminados en la BD para la prueba.");
            return;
        }

        $quinielaId = $matches->first()->quiniela_id;
        $this->info("   Usando Quiniela ID: $quinielaId con " . $matches->count() . " partidos terminados.");

        // --- CREAR USUARIOS ---

        // 🤴 EL CRACK (Poca cantidad, mucha calidad)
        // Estrategia: 2 Aciertos Exactos (6 pts)
        $crack = $this->createUser("El Crack", "crack@simulacro.com");
        $this->predictExact($crack, $matches[0]); // 3 pts
        $this->predictExact($crack, $matches[1]); // 3 pts
        // Total: 6 Pts (2 Plenos)

        // 🍀 EL CONSTANTE (Mucha cantidad, poca calidad)
        // Estrategia: 6 Aciertos de Ganador (6 pts)
        // Necesitamos más partidos para esto, o simular que atinó a ganadores
        // Como solo tomé 5 partidos, ajustemos:
        // Crack: 1 Exacto (3 pts)
        // Constante: 3 Ganadores (3 pts)
        
        // REINICIO ESTRATEGIA PARA 5 PARTIDOS
        Prediction::where('user_id', $crack->id)->delete();
        
        // MATCH 1: 2-1
        $m1 = $matches[0]; 
        
        // Crack: Dice 2-1 (Exacto) -> 3 Pts
        Prediction::create(['user_id' => $crack->id, 'match_id' => $m1->id, 'predicted_home' => $m1->home_score, 'predicted_away' => $m1->away_score]);

        // Constante: Dice 1-0 (Ganador Correcto, asumiendo que ganó local) -> 1 Pt
        // Necesitamos que el Constante sume 3 puntos en total.
        $constante = $this->createUser("El Constante", "constante@simulacro.com");
        
        // Simulamos predicciones para el constante en los primeros 3 partidos para que sume 3 puntos
        foreach($matches->take(3) as $m) {
             // Logic para adivinar ganador pero no resultado
             // Si ganó local (2-0), él dice (1-0).
             $home = $m->home_score > $m->away_score ? $m->home_score - 1 : 0;
             $away = $m->away_score > $m->home_score ? $m->away_score - 1 : 0;
             // Ajuste rápido para asegurar 1 punto
             if ($m->home_score > $m->away_score) { $pH = 10; $pA = 0; } // Gana Local
             elseif ($m->away_score > $m->home_score) { $pH = 0; $pA = 10; } // Gana Visitante
             else { $pH = 0; $pA = 0; } // Empate

             Prediction::create(['user_id' => $constante->id, 'match_id' => $m->id, 'predicted_home' => $pH, 'predicted_away' => $pA]);
        }
        // Total Constante: 3 Pts (0 Plenos)

        // 🤖 RELLENO (8 Bots malos)
        for ($i=1; $i<=8; $i++) {
            $bot = $this->createUser("Bot $i", "bot$i@simulacro.com");
            // Predicciones random que seguro fallan (100-100)
            foreach($matches as $m) {
                Prediction::create(['user_id' => $bot->id, 'match_id' => $m->id, 'predicted_home' => 100, 'predicted_away' => 100]);
            }
        }

        $this->info("✅ Usuarios creados y pronósticos guardados.");
        $this->info("   User 'El Crack': Debería tener 3 puntos (1 Pleno).");
        $this->info("   User 'El Constante': Debería tener 3 puntos (0 Plenos).");
        $this->info("👉 Ejecutando Ranking para ver quién gana...");

        // Llamada interna al controlador (simulada)
        $controller = new \App\Http\Controllers\Api\RankingController();
        $response = $controller->show($quinielaId);
        $ranking = $response->getData(true);

        $this->table(
            ['Pos', 'Usuario', 'Puntos', '🎯 Plenos', '✅ Aciertos'],
            collect($ranking)->map(function($r, $idx) {
                return [
                    $idx + 1,
                    $r['name'],
                    $r['points'],
                    $r['exact_matches'],
                    $r['correct_winners']
                ];
            })
        );
    }

    private function createUser($name, $email) {
        return User::create(['name' => $name, 'email' => $email, 'password' => bcrypt('123')]);
    }

    private function predictExact($user, $match) {
        Prediction::create([
            'user_id' => $user->id,
            'match_id' => $match->id,
            'predicted_home' => $match->home_score,
            'predicted_away' => $match->away_score
        ]);
    }
}
