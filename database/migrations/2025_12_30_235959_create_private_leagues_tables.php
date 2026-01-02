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
        // 1. Tabla de Ligas Privadas
        Schema::create('private_leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // El Dueño/Admin
            $table->foreignId('quiniela_id')->constrained('quinielas')->onDelete('cascade'); // A qué torneo pertenece
            $table->string('name');
            $table->string('code')->unique(); // Código para compartir (ej: "AMIGOS-123")
            $table->timestamps();
        });

        // 2. Tabla Pivote (Relación Muchos a Muchos: Usuarios <-> Ligas Privadas)
        Schema::create('private_league_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_league_id')->constrained('private_leagues')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps(); // Fecha de unión
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('private_league_user');
        Schema::dropIfExists('private_leagues');
    }
};
