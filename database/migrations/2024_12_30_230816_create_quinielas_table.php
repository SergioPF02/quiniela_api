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
    Schema::create('quinielas', function (Blueprint $table) {
        $table->id();
        $table->foreignId('league_id')->constrained()->onDelete('cascade'); // Pertenece a un Bombo
        $table->string('name'); // Ej: "Jornada 1", "Gran Final"
        $table->dateTime('start_date'); // Cuándo empieza
        $table->dateTime('end_date'); // Cuándo cierra
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quinielas');
    }
};
