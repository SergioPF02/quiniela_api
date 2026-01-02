<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Eliminar el valor por defecto actual
        DB::statement('ALTER TABLE leagues ALTER COLUMN active DROP DEFAULT');
        
        // 2. Cambiar el tipo con conversión explícita
        DB::statement('ALTER TABLE leagues ALTER COLUMN active TYPE smallint USING (CASE WHEN active THEN 1 ELSE 0 END)');
        
        // 3. Poner el nuevo valor por defecto
        DB::statement('ALTER TABLE leagues ALTER COLUMN active SET DEFAULT 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE leagues ALTER COLUMN active DROP DEFAULT');
        DB::statement('ALTER TABLE leagues ALTER COLUMN active TYPE boolean USING (CASE WHEN active=1 THEN true ELSE false END)');
        DB::statement('ALTER TABLE leagues ALTER COLUMN active SET DEFAULT false');
    }
};
