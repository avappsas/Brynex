<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el campo `arl_nit_cotizante` (BIGINT NULL) a la tabla `contratos`.
 *
 * Propósito:
 *   Registrar bajo qué NIT/cédula se cotiza la ARL en la planilla PILA:
 *   - arl_modo = 'razon_social'  → se guarda el NIT de la Razón Social seleccionada
 *   - arl_modo = 'independiente' → se guarda la cédula del cliente (bigInteger)
 *
 * Tipo bigInteger: consistente con `clientes.cedula` y con el ID de `razones_sociales`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            // Después de arl_modo: guarda NIT (RS) o cédula (independiente)
            $table->bigInteger('arl_nit_cotizante')->nullable()->after('arl_modo');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('arl_nit_cotizante');
        });
    }
};
