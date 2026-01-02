<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MatchGame;
use App\Models\Prediction;
use App\Models\User;

class SimulationSeeder extends Seeder
{
    public function run()
    {
        // 1. Identify User (Admin)
        $user = User::first();
        if (!$user) {
            $this->command->error("No users found!");
            return;
        }

        // 2. Identify Match (ID 525 from previous check - Villarreal vs Alavés)
        $match = MatchGame::find(525);
        
        if (!$match) {
            $this->command->error("Match 525 not found!");
            return;
        }

        $this->command->info("Simulating for Match: {$match->home_team} vs {$match->away_team}");

        // 3. Create Prediction (Correct Score: 2-1)
        Prediction::updateOrCreate(
            ['user_id' => $user->id, 'match_id' => $match->id],
            ['predicted_home' => 2, 'predicted_away' => 1]
        );
        $this->command->info("✅ Prediction Created: 2-1");

        // 4. Update Match Result (2-1) and Status to Finished
        $match->update([
            'home_score' => 2,
            'away_score' => 1,
            'status' => 'finished'
        ]);
        $this->command->info("✅ Match Finished: 2-1");
    }
}
