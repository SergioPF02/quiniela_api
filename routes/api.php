<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\AuthController;

// --- RUTAS PÚBLICAS (Cualquiera entra) ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/partidos', [MatchController::class, 'index']); // Ver partidos es público
use App\Http\Controllers\Api\LeagueController;
use App\Http\Controllers\Api\PrivateLeagueController;
use App\Http\Controllers\Api\QuinielaController;

// --- RUTAS PROTEGIDAS (Solo con Token) ---
Route::middleware('auth:sanctum')->group(function () {
    
    // Dashboard de Jornadas Activas
    Route::get('/dashboard/quinielas', [QuinielaController::class, 'getDashboard']);
    
    // Guardado masivo (Boleta Completa)
    Route::post('/pronosticar/bulk', [MatchController::class, 'storeBulkPredictions']);
    
    // Aquí ponemos el pronóstico para saber QUIÉN lo mandó
    Route::post('/pronosticar', [MatchController::class, 'storePrediction']);
    
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/ligas', [LeagueController::class, 'index']);
    Route::get('/ligas/{id}/quinielas', [LeagueController::class, 'getQuinielas']);
    Route::get('/quinielas/{id}/partidos', [MatchController::class, 'getByQuiniela']);
    // Ruta para ver el Ranking de una Quiniela específica
    Route::get('/quinielas/{id}/ranking', [App\Http\Controllers\Api\RankingController::class, 'show']);

    // --- NUEVO: Partidos Destacados (Dashboard) ---
    Route::get('/partidos/destacados', [MatchController::class, 'getUpcoming']);

    // --- LIGAS PRIVADAS ---
    Route::post('/private-leagues', [PrivateLeagueController::class, 'store']); // Crear
    Route::post('/private-leagues/join', [PrivateLeagueController::class, 'join']); // Unirse
    Route::get('/private-leagues', [PrivateLeagueController::class, 'index']); // Mis ligas
    Route::get('/private-leagues/{id}/ranking', [PrivateLeagueController::class, 'ranking']); // Ranking Privado
    Route::get('/private-leagues/{id}/matches', [PrivateLeagueController::class, 'getLeagueMatches']); // Ver partidos de mi liga

   // Ruta de prueba para ver mis datos
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

Route::get('/ping', function () {
    return response()->json(['status' => 'ok']);
});