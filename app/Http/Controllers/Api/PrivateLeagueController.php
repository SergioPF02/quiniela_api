<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivateLeague;
use App\Models\MatchGame;
use Illuminate\Http\Request;

class PrivateLeagueController extends Controller
{
    // 1. Crear una Liga Privada (Soporta lista de partidos personalizada)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'match_ids' => 'required|array|min:1', // Lista de IDs de partidos
            'match_ids.*' => 'exists:matches,id',
        ]);

        $league = PrivateLeague::create([
            'user_id' => auth()->id(), // El creador
            'name' => $validated['name'],
            'quiniela_id' => null, // Ya no dependemos de un solo torneo
        ]);

        // Asociar los partidos seleccionados
        $league->matches()->sync($validated['match_ids']);

        // El creador se une automáticamente
        $league->members()->attach(auth()->id());

        return response()->json([
            'message' => 'Liga privada creada con éxito',
            'data' => $league
        ], 201);
    }

    // 2. Unirse a una Liga con Código
    public function join(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|exists:private_leagues,code',
        ], [
            'code.exists' => 'El código de invitación no existe o es incorrecto.',
        ]);

        $league = PrivateLeague::where('code', $validated['code'])->first();

        // Evitar duplicados
        if ($league->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'Ya eres miembro de esta liga'], 409);
        }

        $league->members()->attach(auth()->id());

        return response()->json([
            'message' => '¡Te has unido a ' . $league->name . '!',
            'data' => $league
        ]);
    }

    // 3. Ver mis Ligas Privadas
    public function index()
    {
        $leagues = auth()->user()->privateLeagues()->withCount('members')->get();
        return response()->json($leagues);
    }

    // 4. Ver los partidos de una liga privada específica
    public function getLeagueMatches($id)
    {
        $league = PrivateLeague::findOrFail($id);
        
        // Verificamos si el usuario es miembro
        if (!$league->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['error' => 'No perteneces a esta liga'], 403);
        }

        // Traemos los partidos asociados, incluyendo la predicción del usuario
        $userId = auth()->id();
        $matches = $league->matches()
                          ->with(['predictions' => function($query) use ($userId, $id) {
                              $query->where('user_id', $userId)
                                    ->where('private_league_id', $id);
                          }])
                          ->orderBy('start_time', 'asc')
                          ->get();

        return response()->json($matches);
    }

    // 5. Ranking de la Liga Privada (Optimizado para selección personalizada)
    public function ranking($id)
    {
        $league = PrivateLeague::findOrFail($id);
        
        // Obtenemos los IDs de los partidos que pertenecen a esta liga
        $matchIds = $league->matches()->pluck('matches.id');

        // Obtenemos SOLO los miembros de esta liga privada
        $members = $league->members()->with(['predictions' => function($q) use ($matchIds, $id) {
            // Cargar predicciones SOLO de los partidos seleccionados Y para esta liga
            $q->whereIn('match_id', $matchIds)
              ->where('private_league_id', $id);
        }])->get();

        // Calculamos puntos (Usando los puntos ya calculados por el comando)
        $ranking = $members->map(function ($user) {
            return [
                'name' => $user->name,
                'points' => (int) $user->predictions->sum('points'),
                'exact_matches' => $user->predictions->where('points', 3)->count(),
                'correct_winners' => $user->predictions->where('points', 1)->count(),
                'effectiveness' => $user->predictions->count() > 0 
                                    ? round(($user->predictions->where('points', '>', 0)->count() / $user->predictions->count()) * 100) 
                                    : 0,
                'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=random&color=fff'
            ];
        });

        // Ordenamiento: 1. Puntos, 2. Plenos (3pts), 3. Aciertos (1pt)
        $rankingOrdenado = $ranking->sort(function ($a, $b) {
            if ($a['points'] !== $b['points']) return $b['points'] <=> $a['points'];
            if ($a['exact_matches'] !== $b['exact_matches']) return $b['exact_matches'] <=> $a['exact_matches'];
            return $b['correct_winners'] <=> $a['correct_winners'];
        })->values();

        return response()->json($rankingOrdenado);
    }
}
