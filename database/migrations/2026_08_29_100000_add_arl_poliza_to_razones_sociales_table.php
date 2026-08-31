<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La póliza ARL de la razón social. Es el dato que exige el portal de Sura tanto
 * en el header `x-auth-poliza` como en el cuerpo del afiliar; sin él ninguna
 * llamada al API responde. `arl_nit` ya existía, pero identifica a la ARL, no al
 * contrato de la razón social con ella.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('razones_sociales', function (Blueprint $table) {
            $table->string('arl_poliza', 20)->nullable()->after('arl_nit');
        });
    }

    public function down(): void
    {
        Schema::table('razones_sociales', function (Blueprint $table) {
            $table->dropColumn('arl_poliza');
        });
    }
};
