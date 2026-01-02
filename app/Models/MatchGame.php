<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchGame extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'quiniela_id',
        'league_id', // <--- Nuevo
        'api_id',
        'home_team', 'away_team',
        'home_flag', 'away_flag',
        'home_score', 'away_score',
        'start_time',
        'status',
        'round',
        'matchday' // <--- Nuevo
    ];

    protected $casts = [
        'start_time' => 'datetime',
    ];


    // Relación: Un partido pertenece a una Quiniela
    public function quiniela()
    {
        return $this->belongsTo(Quiniela::class);
    }

    // --- ESTA ES LA QUE FALTABA ---
    // Relación: Un partido tiene muchas Predicciones (de diferentes usuarios)
    public function predictions()
    {
        return $this->hasMany(Prediction::class, 'match_id');
    }
}