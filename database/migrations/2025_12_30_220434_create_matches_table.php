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
    Schema::create('matches', function (Blueprint $table) {
        $table->id();
        $table->foreignId('quiniela_id')->nullable()->constrained('quinielas')->onDelete('cascade');
        $table->string('home_team');      // Local
        $table->string('away_team');      // Visitante
        $table->string('home_flag')->nullable(); 
        $table->string('away_flag')->nullable(); 
        $table->integer('home_score')->nullable(); // Goles
        $table->integer('away_score')->nullable(); 
        $table->dateTime('start_time');   // Hora
        $table->string('status')->default('scheduled'); 
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
