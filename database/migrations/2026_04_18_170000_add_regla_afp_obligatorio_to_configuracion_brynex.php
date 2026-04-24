<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega la clave de configuración 'regla_afp_obligatorio' en configuracion_brynex.
 *
 * Cuando está en '1', los contratos de modalidad Dependiente E (id=0),
 * I Venc (id=10) e I Act (id=11) no permiten seleccionar planes sin AFP,
 * a menos que el cliente esté exento (doc ≠ CC, mujer ≥50, hombre ≥55).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuracion_brynex')->updateOrInsert(
            ['clave' => 'regla_afp_obligatorio'],
            [
                'valor'       => '0',
                'descripcion' => 'Si es 1, los contratos de modalidad Dependiente E e Independientes (I Act, I Venc) solo pueden seleccionar planes que incluyan AFP, a menos que el cliente esté exento (tipo_doc ≠ CC, mujer ≥50 años o hombre ≥55 años).',
            ]
        );
    }

    public function down(): void
    {
        DB::table('configuracion_brynex')->where('clave', 'regla_afp_obligatorio')->delete();
    }
};
