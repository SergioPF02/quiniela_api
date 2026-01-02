<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->foreignId('private_league_id')->nullable()->constrained('private_leagues')->onDelete('cascade');
            
            // Opcional: Agregar índice único compuesto para evitar duplicados en el mismo contexto
            // Nota: En MySQL, múltiples NULLs son permitidos en unique index, lo cual es perfecto.
            // (User + Match + NULL) -> Solo una predicción pública
            // (User + Match + LeagueID) -> Solo una predicción para esa liga
            // Pero primero deberíamos eliminar constraints anteriores si existen. 
            // Como no estoy seguro si existe, lo dejaré a manejo por código (updateOrCreate).
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropForeign(['private_league_id']);
            $table->dropColumn('private_league_id');
        });
    }
};