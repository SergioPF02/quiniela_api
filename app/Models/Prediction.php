<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = ['match_id', 'user_id', 'predicted_home', 'predicted_away', 'private_league_id'];

    // --- ESTA ES LA FUNCIÓN QUE PROBABLEMENTE TE FALTA ---
    public function matchGame()
    {
        // "Una predicción pertenece a un Partido (MatchGame)"
        // Nota: Especificamos 'match_id' y 'id' para evitar confusiones
        return $this->belongsTo(MatchGame::class, 'match_id', 'id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}