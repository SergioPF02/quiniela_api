<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class League extends Model
{
        use HasFactory;
    
            // Autorizamos estos campos para guardado automático
            protected $fillable = ['api_id', 'name', 'code', 'image', 'type', 'current_season', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function quinielas()
        {
            return $this->hasMany(Quiniela::class);
        }
    }
    