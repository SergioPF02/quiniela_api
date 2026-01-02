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
        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('league_id')->nullable()->constrained()->onDelete('cascade')->after('id');
            $table->integer('matchday')->nullable()->after('status');
        });

        Schema::table('leagues', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name'); // Ej: PL, PD, CL
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['league_id']);
            $table->dropColumn(['league_id', 'matchday']);
        });

        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};