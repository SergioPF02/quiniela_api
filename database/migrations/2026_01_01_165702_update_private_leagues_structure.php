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
        // 1. Hacer quiniela_id opcional (nullable) ya que ahora podemos tener mezclas
        Schema::table('private_leagues', function (Blueprint $table) {
            $table->unsignedBigInteger('quiniela_id')->nullable()->change();
        });

        // 2. Nueva tabla para asociar partidos específicos a una liga privada
        Schema::create('private_league_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_league_id')->constrained('private_leagues')->onDelete('cascade');
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('private_league_matches');
        
        Schema::table('private_leagues', function (Blueprint $table) {
            $table->unsignedBigInteger('quiniela_id')->nullable(false)->change();
        });
    }
};
