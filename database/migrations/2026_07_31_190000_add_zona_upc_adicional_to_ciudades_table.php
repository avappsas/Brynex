<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clasifica cada ciudad en la zona geográfica que usa la Resolución 2764 de
 * 2025 (MinSalud) para el valor de UPC adicional: normal (por defecto),
 * grandes_ciudades, especial (dispersión geográfica) o san_andres.
 *
 * Fuente de los códigos DIVIPOLA: Anexo 1 (zona especial) y Anexo 2 (grandes
 * ciudades) de la Resolución 2764 del 30/12/2025, extraídos y cruzados contra
 * la tabla `ciudades` de Brynex el 31/07/2026. 351 de 352 códigos de zona
 * especial y los 32 de grandes ciudades coincidieron con `ciudades.id`; el
 * único código sin coincidencia (27493, Chocó) no existe en el catálogo de
 * Brynex y por lo tanto no se puede marcar — no afecta el resultado porque
 * nunca se seleccionará como ciudad de un cliente.
 *
 * Nota: Buenaventura (76109) y Santa Marta (47001) aparecen en AMBOS anexos
 * de la resolución (son grandes ciudades cuyo municipio también figura en la
 * lista de dispersión geográfica). Ante esa ambigüedad se les da prioridad
 * como "grandes_ciudades" — es la clasificación con más verificación cruzada
 * (nombre + departamento). Revisar si Brayan tiene el criterio oficial.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ciudades', 'zona_upc_adicional')) {
            Schema::table('ciudades', function (Blueprint $table) {
                // NULL = zona normal (el caso por defecto, la mayoría del país).
                $table->string('zona_upc_adicional', 20)->nullable()->after('nombre');
            });
        }

        // ── Grandes ciudades y conurbados (prima adicional del 15%) ──────────
        // Verificado por nombre + departamento contra `ciudades`, no por el
        // código DIVIPOLA de la resolución (la extracción de texto del PDF
        // fue poco confiable en los nombres, no en los códigos que Brynex ya
        // tiene catalogados).
        $grandesCiudades = [
            '63001', // Armenia (Quindío)
            '68081', // Barrancabermeja
            '8001',  // Barranquilla
            '5088',  // Bello
            '11001', // Bogotá D.E.
            '68001', // Bucaramanga
            '76109', // Buenaventura
            '13001', // Cartagena
            '76147', // Cartago
            '54001', // Cúcuta
            '66170', // Dosquebradas
            '68276', // Floridablanca
            '76111', // Guadalajara de Buga
            '73001', // Ibagué
            '5360',  // Itagüí
            '17001', // Manizales
            '5001',  // Medellín
            '23001', // Montería
            '41001', // Neiva
            '76520', // Palmira
            '52001', // Pasto
            '66001', // Pereira
            '19001', // Popayán
            '44001', // Riohacha
            '47001', // Santa Marta
            '76001', // Cali
            '70001', // Sincelejo
            '25754', // Soacha
            '8758',  // Soledad
            '76834', // Tuluá
            '20001', // Valledupar
            '50001', // Villavicencio
        ];

        DB::table('ciudades')->whereIn('id', $grandesCiudades)
            ->update(['zona_upc_adicional' => 'grandes_ciudades']);

        // ── San Andrés, Providencia y Santa Catalina (departamento completo) ──
        DB::table('ciudades')->where('departamento_id', 88)
            ->update(['zona_upc_adicional' => 'san_andres']);

        // ── Zona especial por dispersión geográfica (Anexo 1) ────────────────
        // 352 códigos DIVIPOLA, ya sin los 2 que se solapan con grandes ciudades.
        $zonaEspecial = [
            '5004','5040','5045','5051','5125','5147','5172','5234','5250','5361',
            '5475','5480','5490','5495','5543','5591','5604','5659','5665','5790',
            '5819','5837','5854','5873','13006','13042','13074','13160','13212','13300',
            '13440','13458','13473','13490','13549','13580','13600','13650','13655','13667',
            '13810','15047','15097','15135','15180','15183','15212','15218','15223','15236',
            '15248','15317','15332','15377','15403','15425','15507','15514','15522','15533',
            '15550','15580','15660','15667','15673','15681','15690','15810','15822','18029',
            '18094','18205','18247','18256','18410','18460','18479','18592','18610','18753',
            '18756','18785','18860','19050','19290','19318','19418','19533','19693','19701',
            '19785','19809','20310','20787','23068','23580','25086','25148','25168','25293',
            '25324','25368','25372','25438','25530','25580','25662','25839','25885','27001',
            '27006','27025','27050','27073','27075','27077','27099','27135','27150','27160',
            '27205','27245','27250','27361','27372','27413','27425','27430','27450','27491',
            '27493','27495','27580','27600','27615','27660','27745','27787','27800','27810',
            '41244','41359','41483','41503','41530','41660','41668','41807','44035','44078',
            '44090','44098','44110','44279','44378','44420','44430','44560','44650','44847',
            '44855','44874','47258','47541','47545','47660','47692','47703','47960','50006',
            '50110','50124','50223','50226','50245','50251','50270','50287','50313','50318',
            '50325','50330','50350','50370','50400','50450','50568','50573','50577','50590',
            '50606','50680','50683','50686','50689','50711','52079','52227','52233','52250',
            '52256','52385','52390','52405','52427','52473','52490','52520','52540','52621',
            '52678','52696','52699','54128','54174','54206','54245','54344','54385','54398',
            '54670','54800','54820','54871','66456','66572','68013','68020','68101','68152',
            '68179','68245','68250','68264','68266','68271','68298','68320','68324','68368',
            '68377','68385','68397','68425','68502','68673','68686','68720','68770','68773',
            '70110','70124','70204','70215','70221','70230','70233','70235','70265','70400',
            '70418','70429','70473','70508','70523','70670','70678','70702','70708','70713',
            '70717','70742','70771','70820','70823','73024','73067','73152','73236','73347',
            '73483','73555','73616','73873','76243','76246','76250','76616','76828','76863',
            '81065','81220','81300','81591','81736','81794','85010','85015','85125','85136',
            '85139','85162','85225','85230','85250','85263','85279','85300','85315','85325',
            '85400','85410','85430','85440','86001','86219','86320','86568','86569','86571',
            '86573','86749','86755','86757','86760','86865','86885','91001','91263','91405',
            '91407','91430','91536','91540','91669','94001','94343','94883','94888','95001',
            '95015','95025','95200','97001','97161','97511','97666','97777','99001','99524',
            '99624','99773',
        ];

        DB::table('ciudades')->whereIn('id', $zonaEspecial)
            ->whereNull('zona_upc_adicional') // no pisar lo ya marcado como grandes_ciudades
            ->update(['zona_upc_adicional' => 'especial']);
    }

    public function down(): void
    {
        Schema::table('ciudades', function (Blueprint $table) {
            $table->dropColumn('zona_upc_adicional');
        });
    }
};
