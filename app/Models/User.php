<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // <--- Importante para la API

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // --- ESTA ES LA FUNCIÓN QUE SEGURAMENTE FALTABA ---
        public function predictions()
        {
            return $this->hasMany(Prediction::class);
        }
    
        public function privateLeagues()
        {
            return $this->belongsToMany(PrivateLeague::class, 'private_league_user', 'user_id', 'private_league_id');
        }
    }
    