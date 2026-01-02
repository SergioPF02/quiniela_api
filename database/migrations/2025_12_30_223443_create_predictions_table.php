<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('predictions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('match_id')->constrained('matches'); // Relación con el partido
        $table->unsignedBigInteger('user_id'); // ID del usuario (luego haremos login real)
        $table->integer('predicted_home'); // Goles Local
        $table->integer('predicted_away'); // Goles Visitante
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
