<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PrivateLeague extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'quiniela_id', 'name', 'code'];

    // Relación: Dueño de la liga
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relación: Miembros de la liga (Muchos a Muchos)
    public function members()
    {
        return $this->belongsToMany(User::class, 'private_league_user', 'private_league_id', 'user_id')
                    ->withTimestamps();
    }

    // Relación: Partidos seleccionados para esta liga (Muchos a Muchos)
    public function matches()
    {
        return $this->belongsToMany(MatchGame::class, 'private_league_matches', 'private_league_id', 'match_id')
                    ->withTimestamps();
    }

    // Evento para generar código único automáticamente al crear
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($league) {
            // Generar código de 6 caracteres sin ambigüedades (Sin 0, O, 1, I)
            // Caracteres permitidos: 2-9, A-H, J-N, P-Z
            $pool = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            
            do {
                $code = substr(str_shuffle(str_repeat($pool, 6)), 0, 6);
            } while (static::where('code', $code)->exists()); // Asegurar unicidad

            $league->code = $code;
        });
    }
}
