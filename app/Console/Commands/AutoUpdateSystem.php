<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\League;

class AutoUpdateSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:auto-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Orquestador maestro: Descarga ligas nuevas, actualiza resultados y calcula puntos automáticamente.';

    /**
     * Lista COMPLETA de ligas soportadas por la API (Free Tier)
     * Incluye las Big 5, Ligas Europeas secundarias, Brasil y Torneos Internacionales.
     */
    protected $targetLeagues = [
        'PL',   // Premier League
        'PD',   // Primera Division
        'CL',   // Champions League
        'SA',   // Serie A
        'BL1',  // Bundesliga
        'FL1',  // Ligue 1
        'DED',  // Eredivisie
        'PPL',  // Primeira Liga
        'BSA',  // Brasileirão
        'ELC',  // Championship
        'WC',   // World Cup
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🤖 INICIANDO PROTOCOLO DE AUTOMATIZACIÓN " . now());

        // 1. ITERAR SOBRE LAS LIGAS OBJETIVO
        foreach ($this->targetLeagues as $code) {
            $this->info("⚽ Analizando Liga: $code");

            // Ejecutamos el importador individual
            // Laravel permite llamar a otros comandos Artisan desde aquí
            try {
                $this->call('data:import-league', ['code' => $code]);
                
                $this->info("✅ $code actualizada correctamente.");
            } catch (\Exception $e) {
                $this->error("❌ Error actualizando $code: " . $e->getMessage());
            }

            // PAUSA ESTRATÉGICA (Rate Limiting)
            // La API gratuita permite 10 llamadas por minuto.
            // data:import-league hace 2 llamadas (Info + Matches).
            // Esperamos 15 segundos entre ligas para estar seguros y no ser baneados.
            $this->info("⏳ Esperando 15 segundos para respetar límites de API...");
            sleep(15);
        }

        // 2. RECALCULAR PUNTOS
        // Una vez que tenemos los resultados frescos, calculamos el ranking
        $this->info("🧮 Recalculando Tabla de Puntos Global...");
        $this->call('calculate:points');

        $this->info("🚀 SISTEMA ACTUALIZADO EXITOSAMENTE " . now());
        return 0;
    }
}