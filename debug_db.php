<?php
// Script temporal para inspeccionar la DB
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Quiniela;
use App\Models\PrivateLeague;

echo "--- QUINIELAS DISPONIBLES ---
";
$quinielas = Quiniela::all();
if ($quinielas->isEmpty()) {
    echo "¡NO HAY QUINIELAS! La tabla está vacía.
";
} else {
    foreach ($quinielas as $q) {
        echo "ID: " . $q->id . " | Nombre: " . $q->name . "\n";
    }
}

echo "\n--- LIGAS PRIVADAS EXISTENTES ---
";
$ligas = PrivateLeague::all();
foreach ($ligas as $l) {
    echo "ID: " . $l->id . " | Nombre: " . $l->name . " | Code: " . $l->code . "\n";
}

