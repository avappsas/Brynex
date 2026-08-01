<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Valores oficiales de UPC adicional (lo que cuesta afiliar a alguien que NO
 * pertenece al núcleo familiar del cotizante — modalidad "UPC" id 13 en
 * tipo_modalidad) por rango de edad, sexo y zona.
 *
 * Fuente: "Valor UPC Adicional Régimen Contributivo Vigencia 2026",
 * Resolución 2764 del 30/12/2025 (Ministerio de Salud y Protección Social),
 * Art. 2.1.4.5 Decreto 780 de 2016. Tabla obtenida el 31/07/2026 de
 * empresas.miplanilla.com/PublicoEmpresas/Content/Documentos/valores-upc.pdf
 * (con membrete de ADRES). Los 14 rangos de edad/sexo coinciden con la
 * "estructura de costo por grupo etario" que la propia resolución publica
 * para el cálculo de la UPC-C, pero no se re-derivaron los pesos aquí: se
 * tomó la tabla ya calculada del operador. Vale la pena que Brayan la
 * contraste contra su propio portal de EPS antes de usarla con un cliente
 * real, y hay que actualizarla cada enero con la resolución del año.
 *
 * Esta es información de referencia nacional, no de negocio por aliado —
 * sigue el mismo patrón que `arls`, `eps`, `cajas`, `pensiones` (sin
 * aliado_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('upc_adicional_tarifas')) {
            return;
        }

        Schema::create('upc_adicional_tarifas', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('edad_desde');
            $table->unsignedTinyInteger('edad_hasta')->nullable(); // null = sin tope (75 y más)
            $table->char('sexo', 1)->nullable(); // 'H'/'M', null = aplica a ambos
            $table->string('zona', 20); // normal | especial | ciudades | san_andres
            $table->decimal('valor', 12, 2);
            $table->unsignedSmallInteger('vigencia_anio');
            $table->timestamps();

            $table->index(['vigencia_anio', 'zona', 'sexo']);
        });

        $vigencia = 2026;
        $filas = [
            ['edad_desde' => 0, 'edad_hasta' => 0, 'sexo' => null, 'zona' => 'normal', 'valor' => 458000],
            ['edad_desde' => 0, 'edad_hasta' => 0, 'sexo' => null, 'zona' => 'especial', 'valor' => 503600],
            ['edad_desde' => 0, 'edad_hasta' => 0, 'sexo' => null, 'zona' => 'ciudades', 'valor' => 502900],
            ['edad_desde' => 0, 'edad_hasta' => 0, 'sexo' => null, 'zona' => 'san_andres', 'valor' => 630700],
            ['edad_desde' => 1, 'edad_hasta' => 4, 'sexo' => null, 'zona' => 'normal', 'valor' => 133400],
            ['edad_desde' => 1, 'edad_hasta' => 4, 'sexo' => null, 'zona' => 'especial', 'valor' => 146500],
            ['edad_desde' => 1, 'edad_hasta' => 4, 'sexo' => null, 'zona' => 'ciudades', 'valor' => 146300],
            ['edad_desde' => 1, 'edad_hasta' => 4, 'sexo' => null, 'zona' => 'san_andres', 'valor' => 183100],
            ['edad_desde' => 5, 'edad_hasta' => 14, 'sexo' => null, 'zona' => 'normal', 'valor' => 55200],
            ['edad_desde' => 5, 'edad_hasta' => 14, 'sexo' => null, 'zona' => 'especial', 'valor' => 60500],
            ['edad_desde' => 5, 'edad_hasta' => 14, 'sexo' => null, 'zona' => 'ciudades', 'valor' => 60400],
            ['edad_desde' => 5, 'edad_hasta' => 14, 'sexo' => null, 'zona' => 'san_andres', 'valor' => 75200],
            ['edad_desde' => 15, 'edad_hasta' => 18, 'sexo' => 'H', 'zona' => 'normal', 'valor' => 53300],
            ['edad_desde' => 15, 'edad_hasta' => 18, 'sexo' => 'H', 'zona' => 'especial', 'valor' => 58400],
            ['edad_desde' => 15, 'edad_hasta' => 18, 'sexo' => 'H', 'zona' => 'ciudades', 'valor' => 58400],
            ['edad_desde' => 15, 'edad_hasta' => 18, 'sexo' => 'H', 'zona' => 'san_andres', 'valor' => 72700],
            ['edad_desde' => 15, 'edad_hasta' => 18, 'sexo' => 'M', 'zona' => 'normal', 'valor' => 82400],
            ['edad_desde' => 15, 'edad_hasta' => 18, 'sexo' => 'M', 'zona' => 'especial', 'valor' => 90400],
            ['edad_desde' => 15, 'edad_hasta' => 18, 'sexo' => 'M', 'zona' => 'ciudades', 'valor' => 90200],
            ['edad_desde' => 15, 'edad_hasta' => 18, 'sexo' => 'M', 'zona' => 'san_andres', 'valor' => 112700],
            ['edad_desde' => 19, 'edad_hasta' => 44, 'sexo' => 'H', 'zona' => 'normal', 'valor' => 90300],
            ['edad_desde' => 19, 'edad_hasta' => 44, 'sexo' => 'H', 'zona' => 'especial', 'valor' => 99100],
            ['edad_desde' => 19, 'edad_hasta' => 44, 'sexo' => 'H', 'zona' => 'ciudades', 'valor' => 99000],
            ['edad_desde' => 19, 'edad_hasta' => 44, 'sexo' => 'H', 'zona' => 'san_andres', 'valor' => 123600],
            ['edad_desde' => 19, 'edad_hasta' => 44, 'sexo' => 'M', 'zona' => 'normal', 'valor' => 164800],
            ['edad_desde' => 19, 'edad_hasta' => 44, 'sexo' => 'M', 'zona' => 'especial', 'valor' => 181000],
            ['edad_desde' => 19, 'edad_hasta' => 44, 'sexo' => 'M', 'zona' => 'ciudades', 'valor' => 180800],
            ['edad_desde' => 19, 'edad_hasta' => 44, 'sexo' => 'M', 'zona' => 'san_andres', 'valor' => 226400],
            ['edad_desde' => 45, 'edad_hasta' => 49, 'sexo' => null, 'zona' => 'normal', 'valor' => 168100],
            ['edad_desde' => 45, 'edad_hasta' => 49, 'sexo' => null, 'zona' => 'especial', 'valor' => 184700],
            ['edad_desde' => 45, 'edad_hasta' => 49, 'sexo' => null, 'zona' => 'ciudades', 'valor' => 184500],
            ['edad_desde' => 45, 'edad_hasta' => 49, 'sexo' => null, 'zona' => 'san_andres', 'valor' => 231000],
            ['edad_desde' => 50, 'edad_hasta' => 54, 'sexo' => null, 'zona' => 'normal', 'valor' => 212100],
            ['edad_desde' => 50, 'edad_hasta' => 54, 'sexo' => null, 'zona' => 'especial', 'valor' => 233100],
            ['edad_desde' => 50, 'edad_hasta' => 54, 'sexo' => null, 'zona' => 'ciudades', 'valor' => 232800],
            ['edad_desde' => 50, 'edad_hasta' => 54, 'sexo' => null, 'zona' => 'san_andres', 'valor' => 291600],
            ['edad_desde' => 55, 'edad_hasta' => 59, 'sexo' => null, 'zona' => 'normal', 'valor' => 250700],
            ['edad_desde' => 55, 'edad_hasta' => 59, 'sexo' => null, 'zona' => 'especial', 'valor' => 275500],
            ['edad_desde' => 55, 'edad_hasta' => 59, 'sexo' => null, 'zona' => 'ciudades', 'valor' => 275200],
            ['edad_desde' => 55, 'edad_hasta' => 59, 'sexo' => null, 'zona' => 'san_andres', 'valor' => 344800],
            ['edad_desde' => 60, 'edad_hasta' => 64, 'sexo' => null, 'zona' => 'normal', 'valor' => 321200],
            ['edad_desde' => 60, 'edad_hasta' => 64, 'sexo' => null, 'zona' => 'especial', 'valor' => 353100],
            ['edad_desde' => 60, 'edad_hasta' => 64, 'sexo' => null, 'zona' => 'ciudades', 'valor' => 352700],
            ['edad_desde' => 60, 'edad_hasta' => 64, 'sexo' => null, 'zona' => 'san_andres', 'valor' => 442100],
            ['edad_desde' => 65, 'edad_hasta' => 69, 'sexo' => null, 'zona' => 'normal', 'valor' => 397400],
            ['edad_desde' => 65, 'edad_hasta' => 69, 'sexo' => null, 'zona' => 'especial', 'valor' => 436900],
            ['edad_desde' => 65, 'edad_hasta' => 69, 'sexo' => null, 'zona' => 'ciudades', 'valor' => 436300],
            ['edad_desde' => 65, 'edad_hasta' => 69, 'sexo' => null, 'zona' => 'san_andres', 'valor' => 547100],
            ['edad_desde' => 70, 'edad_hasta' => 74, 'sexo' => null, 'zona' => 'normal', 'valor' => 480000],
            ['edad_desde' => 70, 'edad_hasta' => 74, 'sexo' => null, 'zona' => 'especial', 'valor' => 527800],
            ['edad_desde' => 70, 'edad_hasta' => 74, 'sexo' => null, 'zona' => 'ciudades', 'valor' => 527100],
            ['edad_desde' => 70, 'edad_hasta' => 74, 'sexo' => null, 'zona' => 'san_andres', 'valor' => 661000],
            ['edad_desde' => 75, 'edad_hasta' => null, 'sexo' => null, 'zona' => 'normal', 'valor' => 598300],
            ['edad_desde' => 75, 'edad_hasta' => null, 'sexo' => null, 'zona' => 'especial', 'valor' => 657800],
            ['edad_desde' => 75, 'edad_hasta' => null, 'sexo' => null, 'zona' => 'ciudades', 'valor' => 657000],
            ['edad_desde' => 75, 'edad_hasta' => null, 'sexo' => null, 'zona' => 'san_andres', 'valor' => 824100],
        ];

        $ahora = now();
        foreach ($filas as &$fila) {
            $fila['vigencia_anio'] = $vigencia;
            $fila['created_at'] = $ahora;
            $fila['updated_at'] = $ahora;
        }
        unset($fila);

        DB::table('upc_adicional_tarifas')->insert($filas);
    }

    public function down(): void
    {
        Schema::dropIfExists('upc_adicional_tarifas');
    }
};
