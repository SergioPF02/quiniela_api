<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class RankingController extends Controller
{
    // Mostrar Ranking de una Quiniela Específica (Jornada)
    public function show($quinielaId)
    {
        // Traemos usuarios que tengan predicciones PÚBLICAS en esta quiniela
        $users = User::whereHas('predictions', function ($query) use ($quinielaId) {
            $query->whereNull('private_league_id') // Solo públicas
                  ->whereHas('matchGame', function ($subQ) use ($quinielaId) {
                      $subQ->where('quiniela_id', $quinielaId);
                  });
        })->with(['predictions' => function($q) use ($quinielaId) {
            // Cargamos SOLO las predicciones de esta quiniela y que sean PÚBLICAS
            $q->whereNull('private_league_id')
              ->whereHas('matchGame', function($subQ) use ($quinielaId) {
                $subQ->where('quiniela_id', $quinielaId);
            });
        }])->get();

        $ranking = $users->map(function ($user) {
            // Nota: Ya filtramos en el 'with', así que $user->predictions solo tiene las correctas
            $totalPredictions = $user->predictions->count();
            $correctWinners = $user->predictions->where('points', '>', 0)->count();
            
            // Calculamos efectividad: (Aciertos / Total Pronosticados) * 100
            $effectiveness = $totalPredictions > 0 
                ? round(($correctWinners / $totalPredictions) * 100, 1) 
                : 0;

            return [
                'name' => $user->name,
                'points' => (int) $user->predictions->sum('points'),
                'total_played' => $totalPredictions,
                'correct_winners' => $correctWinners,
                'effectiveness' => $effectiveness,
                'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=random&color=fff'
            ];
        });

        // Ordenamiento Pro: 
        // 1. Más Puntos
        // 2. Mayor Efectividad (quien falló menos de lo que jugó)
        // 3. Orden Alfabético
        $rankingOrdenado = $ranking->sort(function ($a, $b) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            if ($a['effectiveness'] !== $b['effectiveness']) {
                return $b['effectiveness'] <=> $a['effectiveness'];
            }
            return $a['name'] <=> $b['name'];
        })->values();

        return response()->json($rankingOrdenado);
    }
}