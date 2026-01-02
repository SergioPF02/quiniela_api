<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\MatchGame;
use App\Models\Prediction;
use App\Models\Quiniela;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class TestTournamentLogic extends Command
{
    protected $signature = 'test:tournament';
    protected $description = 'Simula un torneo completo (Mundial) con partidos indefinidos y verifica el ranking.';

    public function handle()
    {
        $this->info("🏆 INICIANDO SIMULACIÓN DE TORNEO: MUNDIAL 2026 🏆");
        $this->info("-----------------------------------------------------");

        // 1. LIMPIEZA INICIAL
        $this->info("🧹 Limpiando datos de prueba anteriores...");
        User::where('email', 'like', 'bot_mundial_%')->delete();
        Quiniela::where('name', 'Simulación Mundial 2026')->delete();
        // Borramos partidos huérfanos de pruebas anteriores (opcional, por seguridad)
        MatchGame::where('api_id', '>', 9000000)->delete();

        // 2. CREAR EL TORNEO (QUINIELA)
        $quiniela = Quiniela::create([
            'name' => 'Simulación Mundial 2026',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'league_id' => 9, // ID del Mundial real
        ]);
        $this->info("✅ Torneo Creado: {$quiniela->name} (ID: {$quiniela->id})");

        // 3. CREAR JORNADAS Y PARTIDOS
        // A. Fase de Grupos (Partidos Definidos)
        $matchGroup1 = MatchGame::create([
            'quiniela_id' => $quiniela->id,
            'api_id' => 9000001,
            'home_team' => 'Brasil',
            'away_team' => 'Serbia',
            'start_time' => now()->addDays(1),
            'status' => 'scheduled',
            'matchday' => 1
        ]);
        
        $matchGroup2 = MatchGame::create([
            'quiniela_id' => $quiniela->id,
            'api_id' => 9000002,
            'home_team' => 'Francia',
            'away_team' => 'Dinamarca',
            'start_time' => now()->addDays(1),
            'status' => 'scheduled',
            'matchday' => 1
        ]);

        // B. Octavos de Final (Partidos INDEFINIDOS / TBD)
        // Aquí simulamos que aún no sabemos quién juega
        $matchKnockout = MatchGame::create([
            'quiniela_id' => $quiniela->id,
            'api_id' => 9000003,
            'home_team' => '1ro Grupo G', // TBD
            'away_team' => '2do Grupo H', // TBD
            'start_time' => now()->addDays(15),
            'status' => 'scheduled',
            'matchday' => 4 // Jornada futura
        ]);

        $this->info("📅 Partidos generados:");
        $this->line("   - ID {$matchGroup1->id}: Brasil vs Serbia (Definido)");
        $this->line("   - ID {$matchKnockout->id}: 1ro Grupo G vs 2do Grupo H (Indefinido - TBD)");

        // 4. CREAR 5 USUARIOS (BOTS)
        $bots = [];
        for ($i = 1; $i <= 5; $i++) {
            $bots[$i] = User::create([
                'name' => "Bot Mundial $i",
                'email' => "bot_mundial_$i@test.com",
                'password' => Hash::make('password')
            ]);
        }
        $this->info("🤖 5 Bots registrados exitosamente.");

        // 5. SIMULAR APUESTAS (Validación de 'Todos los partidos seleccionados')
        // Simularemos que el Bot 1 es el Experto (Ganará todo)
        // El Bot 5 fallará todo.
        
        $this->info("\n🎲 Bots enviando sus pronósticos (Boleta Completa)...");
        
        // Array de partidos para la boleta (El usuario selecciona TODOS los disponibles en la app)
        // Nota: En la app real, podrían filtrar por jornada, pero aquí simulamos que envían todo lo disponible.
        $allMatches = [$matchGroup1->id, $matchGroup2->id, $matchKnockout->id];

        foreach ($bots as $index => $user) {
            $predictions = [];
            
            // Lógica de predicción según el bot
            if ($index == 1) { 
                // EL EXPERTO: Predice los resultados que NOSOTROS sabemos que van a ocurrir
                // Brasil gana (1-0), Francia empata (1-1), Y el TBD (que será Brasil vs Corea) gana Local (3-0)
                $predictions = [
                    ['match_id' => $matchGroup1->id, 'home_score' => 2, 'away_score' => 0], // Acierta Gana Local
                    ['match_id' => $matchGroup2->id, 'home_score' => 1, 'away_score' => 1], // Acierta Empate
                    ['match_id' => $matchKnockout->id, 'home_score' => 3, 'away_score' => 1] // Acierta Gana Local (Aunque sea TBD)
                ];
                $desc = "Experto (Acierta Todo)";
            } elseif ($index == 5) {
                // EL PERDEDOR: Falla todo
                $predictions = [
                    ['match_id' => $matchGroup1->id, 'home_score' => 0, 'away_score' => 1], // Falla
                    ['match_id' => $matchGroup2->id, 'home_score' => 2, 'away_score' => 0], // Falla
                    ['match_id' => $matchKnockout->id, 'home_score' => 0, 'away_score' => 1] // Falla
                ];
                $desc = "Perdedor (Falla Todo)";
            } else {
                // PROMEDIOS: Resultados mixtos (todos apuestan local 1-0 por simplicidad)
                $predictions = [
                    ['match_id' => $matchGroup1->id, 'home_score' => 1, 'away_score' => 0], // Acierta
                    ['match_id' => $matchGroup2->id, 'home_score' => 1, 'away_score' => 0], // Falla
                    ['match_id' => $matchKnockout->id, 'home_score' => 1, 'away_score' => 0] // Acierta
                ];
                $desc = "Promedio";
            }

            // Simulamos el envío al API (Usando la misma lógica del controlador pero directa)
            // Validamos que estén todos los partidos (Simulación de restricción Frontend)
            if (count($predictions) != count($allMatches)) {
                $this->error("❌ Bot $index intentó enviar boleta incompleta.");
                continue;
            }

            // Insertar Predicciones (Bulk Insert Optimizado)
            foreach ($predictions as $p) {
                Prediction::create([
                    'user_id' => $user->id,
                    'match_id' => $p['match_id'],
                    'predicted_home' => $p['home_score'],
                    'predicted_away' => $p['away_score'],
                ]);
            }
            $this->line("   - Bot $index ($desc): Boleta enviada ✅");
        }

        // 6. EVOLUCIÓN DEL TIEMPO: ACTUALIZAR PARTIDOS TBD
        // Aquí viene la magia: El partido ID 9000003 ya tiene apuestas, pero ahora se define quién juega.
        $this->info("\n⏳ TIEMPO PASA... Fase de Grupos termina.");
        $this->info("🔄 Actualizando partido TBD: '1ro Grupo G' ahora es 'Brasil' y '2do Grupo H' es 'Corea del Sur'.");
        
        $matchKnockout->home_team = 'Brasil';
        $matchKnockout->away_team = 'Corea del Sur';
        $matchKnockout->save();

        $this->line("   -> Las apuestas previas se mantienen vinculadas al ID {$matchKnockout->id}.");

        // 7. FINALIZAR PARTIDOS (SET SCORES)
        $this->info("\n⚽ JUGANDO PARTIDOS...");
        
        // Brasil vs Serbia: Gana Brasil 2-0
        $matchGroup1->home_score = 2;
        $matchGroup1->away_score = 0;
        $matchGroup1->status = 'finished';
        $matchGroup1->save();

        // Francia vs Dinamarca: Empate 1-1
        $matchGroup2->home_score = 1;
        $matchGroup2->away_score = 1;
        $matchGroup2->status = 'finished';
        $matchGroup2->save();

        // Brasil vs Corea (Ex-TBD): Gana Brasil 4-1
        $matchKnockout->home_score = 4;
        $matchKnockout->away_score = 1;
        $matchKnockout->status = 'finished';
        $matchKnockout->save();

        // 8. CALCULAR PUNTOS
        $this->info("🧮 Calculando puntos finales...");
        Artisan::call('calculate:points');

        // 9. VERIFICAR RANKING
        $this->info("\n🏆 --- RANKING FINAL DEL TORNEO --- 🏆");
        
        // Simular lógica del RankingController
        $ranking = User::where('email', 'like', 'bot_mundial_%')
            ->with('predictions')
            ->get()
            ->map(function ($user) {
                return [
                    'name' => $user->name,
                    'points' => $user->predictions->sum('points')
                ];
            })
            ->sortByDesc('points')
            ->values();

        $tableMask = "| %-15s | %-6s |";
        $this->line(sprintf($tableMask, "Usuario", "Puntos"));
        $this->line(str_repeat("-", 26));

        foreach ($ranking as $r) {
            $this->line(sprintf($tableMask, $r['name'], $r['points']));
        }

        // VALIDACIÓN AUTOMÁTICA
        $winner = $ranking->first();
        if ($winner['name'] == 'Bot Mundial 1' && $winner['points'] == 3) {
            $this->info("\n✅ PRUEBA EXITOSA: El 'Experto' (Bot 1) ganó con puntuación perfecta (3 puntos de 3 partidos).");
            $this->info("   -> Nota: Ganó 3 puntos porque acertó el resultado (Gana/Empate/Gana) en los 3 partidos.");
            $this->info("   -> El sistema manejó correctamente el cambio de nombre de equipos en el partido TBD.");
        } else {
            $this->error("\n❌ PRUEBA FALLIDA: El resultado no fue el esperado.");
            $this->line("Esperábamos 3 puntos para Bot 1, obtuvo: " . $winner['points']);
        }

        // 10. LIMPIEZA FINAL
        // MatchGame::where('api_id', '>', 9000000)->delete();
        // User::where('email', 'like', 'bot_mundial_%')->delete();
        // Quiniela::where('name', 'Simulación Mundial 2026')->delete();
    }
}
