<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiniela extends Model
{
    use HasFactory;

    // Autorizamos estos campos
    protected $fillable = ['league_id', 'name', 'start_date', 'end_date'];

    // Relación con la Liga
    public function league()
    {
        return $this->belongsTo(League::class);
    }

    // Relación con los Partidos (HasMany)
    // Una Quiniela (Jornada) tiene muchos partidos
    public function matches()
    {
        return $this->hasMany(MatchGame::class);
    }
}
