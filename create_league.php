<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\League;

$league = League::updateOrCreate(
    ['api_id' => 39], // Premier League
    [
        'name' => 'Premier League',
        'current_season' => 2024,
        'active' => true,
        'type' => 'LEAGUE'
    ]
);

echo "✅ Liga creada: " . $league->name . "\n";

