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

    // 2. Traer solo las Jornadas que NO han empezado (Abiertas para apuestas)
    public function getQuinielas($id)
    {
        $now = now('UTC');

        $quinielas = Quiniela::where('league_id', $id)
                             ->where('start_date', '>', $now) // Filtro estricto por fecha de inicio UTC
                             ->orderBy('start_date', 'asc')
                             ->get();
                             
        return response()->json($quinielas);
    }
}
