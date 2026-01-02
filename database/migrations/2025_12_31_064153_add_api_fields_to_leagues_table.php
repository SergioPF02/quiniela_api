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
        Schema::table('leagues', function (Blueprint $table) {
            $table->unsignedBigInteger('api_id')->nullable()->after('id')->unique(); // ID en API-Football (ej: 39)
            $table->integer('current_season')->default(2024)->after('api_id'); // Año a descargar
            $table->boolean('active')->default(false)->after('image'); // ¿Sincronizar esta liga?
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn(['api_id', 'current_season', 'active']);
        });
    }
};
