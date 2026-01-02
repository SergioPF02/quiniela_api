<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            // Índices simples para búsquedas rápidas (FKs ya suelen tener, pero aseguramos)
            $table->index(['user_id', 'match_id']); 
            $table->index('private_league_id');
        });

        // Índice ÚNICO parcial para PostgreSQL para evitar duplicados reales
        // 1. Para predicciones públicas (private_league_id IS NULL)
        DB::statement('CREATE UNIQUE INDEX predictions_user_match_public_unique ON predictions (user_id, match_id) WHERE private_league_id IS NULL');
        
        // 2. Para predicciones privadas (private_league_id IS NOT NULL)
        DB::statement('CREATE UNIQUE INDEX predictions_user_match_private_unique ON predictions (user_id, match_id, private_league_id) WHERE private_league_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'match_id']);
            $table->dropIndex(['private_league_id']);
        });

        DB::statement('DROP INDEX IF EXISTS predictions_user_match_public_unique');
        DB::statement('DROP INDEX IF EXISTS predictions_user_match_private_unique');
    }
};
