<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// --- TAREAS PROGRAMADAS ---
// Ejecutar el sincronizador de partidos cada 10 minutos
Schedule::command('sync:matches')->everyTenMinutes();
