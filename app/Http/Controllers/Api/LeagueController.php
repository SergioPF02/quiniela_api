<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Models\Quiniela;
use Illuminate\Http\Request;

class LeagueController extends Controller
{
    // 1. Traer todas las Ligas (Bombos)
    public function index()
    {
        return League::all();
    }

    // 2. Traer las Quinielas de una Liga específica (Solo las que no han empezado)
    public function getQuinielas($id)
    {
        $quinielas = Quiniela::where('league_id', $id)
                             ->orderBy('start_date', 'asc')
                             ->get()
                             ->filter(function($quiniela) {
                                 // Verificar si la jornada tiene algún partido que ya NO esté programado
                                 $hasStarted = \DB::table('matches')
                                     ->where('quiniela_id', $quiniela->id)
                                     ->where('status', '!=', 'scheduled')
                                     ->exists();
                                     
                                 // También verificamos que tenga partidos
                                 $hasMatches = \DB::table('matches')
                                     ->where('quiniela_id', $quiniela->id)
                                     ->exists();

                                 return $hasMatches && !$hasStarted;
                             })
                             ->values();
                             
        return response()->json($quinielas);
    }
}
