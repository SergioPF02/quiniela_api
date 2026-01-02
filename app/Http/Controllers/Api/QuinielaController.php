<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiniela;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuinielaController extends Controller
{
    public function getDashboard(Request $request)
    {
        $userId = auth()->id();

        $quinielas = Quiniela::with('league')
            ->whereHas('league')
            ->get()
            ->map(function ($quiniela) use ($userId) {
                $totalMatches = DB::table('matches')->where('quiniela_id', $quiniela->id)->count();
                
                $userPredictionsCount = DB::table('predictions')
                    ->join('matches', 'predictions.match_id', '=', 'matches.id')
                    ->where('matches.quiniela_id', $quiniela->id)
                    ->where('predictions.user_id', $userId)
                    ->count();

                $hasStarted = DB::table('matches')
                    ->where('quiniela_id', $quiniela->id)
                    ->where('status', '!=', 'scheduled')
                    ->exists();

                return [
                    'id' => $quiniela->id,
                    'name' => $quiniela->name,
                    'league_name' => $quiniela->league->name,
                    'league_image' => $quiniela->league->image ?? '', 
                    'total_matches' => $totalMatches,
                    'user_predictions' => $userPredictionsCount,
                    'is_completed' => ($totalMatches > 0 && $totalMatches == $userPredictionsCount),
                    'has_started' => $hasStarted,
                ];
            })
            ->filter(function($q) { return $q['total_matches'] > 0; })
            ->values();

        return response()->json([
            // POR JUGAR: No han empezado. 
            // (Incluso si ya las completé, las muestro para que puedan ser editadas antes del inicio)
            'activas' => $quinielas->filter(function($q) { 
                return !$q['has_started']; 
            })->values(),
            
            // RESULTADOS: Ya empezaron O terminaron, PERO solo si el usuario participó
            'historial' => $quinielas->filter(function($q) { 
                return $q['has_started'] && $q['user_predictions'] > 0; 
            })->sortByDesc('id')->values(),
        ]);
    }
}