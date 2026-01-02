<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchGame;
use Illuminate\Http\Request;
use App\Models\Prediction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Import Log

class MatchController extends Controller
{
    public function index()
    {
        // Traer todos los partidos ordenados por fecha
        $matches = MatchGame::orderBy('start_time', 'asc')->get();

        return response()->json($matches);
    }

    // Guardar boleta completa
    public function storeBulkPredictions(Request $request)
    {
        $validated = $request->validate([
            'quiniela_id' => 'nullable|exists:quinielas,id',
            'predictions' => 'required|array',
            'predictions.*.match_id' => 'required|exists:matches,id',
            'predictions.*.home_score' => 'required|integer',
            'predictions.*.away_score' => 'required|integer',
            'private_league_id' => 'nullable|exists:private_leagues,id',
        ]);

        $quinielaId = $validated['quiniela_id'] ?? null;
        $privateLeagueId = $validated['private_league_id'] ?? null;
        $userId = auth()->id();
        $submittedMatchIds = collect($validated['predictions'])->pluck('match_id')->toArray();

        // 1. Validación de Tiempo (En bloque) - Usamos UTC para consistencia absoluta
        $nowUtc = now('UTC');
        
        $startedMatches = MatchGame::whereIn('id', $submittedMatchIds)
            ->where(function($query) use ($nowUtc) {
                $query->where('status', '!=', 'scheduled')
                      ->orWhere('start_time', '<=', $nowUtc);
            })->exists();
        
        if ($startedMatches) {
            return response()->json(['error' => 'Uno o más partidos seleccionados ya han comenzado.'], 403);
        }

        // 2. Transacción optimizada para PostgreSQL: Delete + Bulk Insert
        DB::transaction(function () use ($validated, $userId, $privateLeagueId, $quinielaId, $submittedMatchIds) {
            
            // A. BORRADO QUIRÚRGICO: Borramos TODAS las predicciones previas de estos partidos
            // Esto es mucho más rápido que chequear existencia uno por uno.
            $deleteQuery = Prediction::where('user_id', $userId)
                                     ->whereIn('match_id', $submittedMatchIds);

            if ($privateLeagueId) {
                $deleteQuery->where('private_league_id', $privateLeagueId);
            } else {
                $deleteQuery->whereNull('private_league_id');
            }
            
            $deleteQuery->delete();

            // B. INSERCIÓN MASIVA (BULK INSERT)
            // Preparamos el array gigante
            $now = now();
            $recordsToInsert = [];
            
            foreach ($validated['predictions'] as $pred) {
                $recordsToInsert[] = [
                    'user_id' => $userId,
                    'match_id' => $pred['match_id'],
                    'private_league_id' => $privateLeagueId,
                    'predicted_home' => $pred['home_score'],
                    'predicted_away' => $pred['away_score'],
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }

            // ¡BAM! Una sola consulta para insertar todo
            Prediction::insert($recordsToInsert);
        });

        return response()->json(['message' => '¡Boleta guardada con éxito!']);
    }

    // Función para guardar o actualizar un pronóstico (Mantener para compatibilidad si es necesario)
   public function storePrediction(Request $request)
    {
        $validated = $request->validate([
            'match_id' => 'required|exists:matches,id',
            'home_score' => 'required|integer|min:0',
            'away_score' => 'required|integer|min:0',
            'private_league_id' => 'nullable|exists:private_leagues,id',
        ]);

        $privateLeagueId = $validated['private_league_id'] ?? null;
        $userId = auth()->id();

        Log::info("Single Prediction - User: $userId, Match: {$validated['match_id']}, PrivateLeague: $privateLeagueId");

        // 1. Buscamos el partido
        $match = MatchGame::findOrFail($validated['match_id']);

        // 2. REGLA DE ORO: Validación Anti-Trampas
        // No se puede pronosticar si:
        // - El partido ya empezó (según su hora programada)
        // - El estatus no es 'scheduled' (programado)
        
        $nowUtc = now('UTC');
        
        // Convertimos start_time a Carbon si no lo es, asegurando UTC
        $matchStartTime = \Illuminate\Support\Carbon::parse($match->start_time)->utc();
        
        if ($match->status !== 'scheduled' || $nowUtc->isAfter($matchStartTime)) {
            return response()->json([
                'error' => '¡Bloqueado! El tiempo para enviar pronósticos ha terminado.',
                'server_time_utc' => $nowUtc->toDateTimeString(),
                'match_time_utc' => $matchStartTime->toDateTimeString()
            ], 403);
        }

        // 3. Si todo bien, guardamos
        $prediction = Prediction::updateOrCreate(
            [
                'match_id' => $validated['match_id'],
                'user_id' => $userId,
                'private_league_id' => $privateLeagueId,
            ],
            [
                'predicted_home' => $validated['home_score'],
                'predicted_away' => $validated['away_score'],
            ]
        );

        return response()->json([
            'message' => '¡Pronóstico guardado exitosamente!',
            'data' => $prediction
        ]);
    }
    // Obtener partidos de una quiniela específica
    public function getByQuiniela($id)
    {
        $userId = auth()->id(); // Obtenemos el ID del usuario conectado

        $matches = MatchGame::where('quiniela_id', $id)
                        ->with(['predictions' => function($query) use ($userId) {
                            // Aquí está la magia: Trae solo MI predicción para la LIGA PÚBLICA (null)
                            $query->where('user_id', $userId)
                                  ->whereNull('private_league_id');
                        }])
                        ->orderBy('start_time', 'asc')
                        ->take(50) 
                        ->get();

        return response()->json([
            'server_time' => now('UTC')->toDateTimeString(),
            'matches' => $matches
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
    // Obtener partidos destacados (Próximos o En Vivo de TODAS las ligas)
    public function getUpcoming()
    {
        $userId = auth()->id();

        // SIMPLIFICADO: Trae los próximos 20 partidos programados, sin importar la fecha exacta
        $matches = MatchGame::where('status', 'scheduled')
                        ->with(['predictions' => function($query) use ($userId) {
                            $query->where('user_id', $userId)
                                  ->whereNull('private_league_id');
                        }])
                        ->orderBy('start_time', 'asc') // Los más cercanos primero
                        ->take(20)
                        ->get();

        return response()->json($matches, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
