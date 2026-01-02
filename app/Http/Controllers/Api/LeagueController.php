<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Models\Quiniela;
use Illuminate\Http\Request;

class LeagueController extends Controller
{
    // 1. Traer solo Ligas que tengan jornadas DISPONIBLES para apostar
    public function index()
    {
        $now = now('UTC');
        
        return League::whereHas('quinielas', function($query) use ($now) {
            $query->where('start_date', '>', $now);
        })->get();
    }

    // 2. Traer Jornadas (Por defecto solo futuras, con ?mode=all trae todas)
    public function getQuinielas(Request $request, $id)
    {
        $now = now('UTC');
        $query = Quiniela::where('league_id', $id);

        // Si NO pedimos 'all', aplicamos el filtro estricto (comportamiento original para públicas)
        if ($request->query('mode') !== 'all') {
            $query->where('start_date', '>', $now);
        }

        $quinielas = $query->orderBy('start_date', 'asc')->get();
                             
        return response()->json($quinielas);
    }
}
