<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⚠️  MIGRACIÓN REEMPLAZADA
 * Esta migración crea una versión inicial de `contratos` que luego es
 * descartada y reconstruida completamente por:
 * 2026_04_04_075000_rebuild_contratos_table.php
 *
 * Se mantiene como NO-OP para que Laravel no rompa el orden de
 * migraciones si esta ya fue ejecutada antes del rebuild.
 */
return new class extends Migration
{
    public function up(): void
    {
        // NO-OP: La tabla será creada y normalizada por 075000_rebuild_contratos_table.php
        // Si la tabla ya existe (migración previa), el rebuild la dropeará y recreará.
    }

    public function down(): void
    {
        // NO-OP: El down del rebuild se encarga de eliminar la tabla.
    }
};

