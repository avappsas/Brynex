<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Agregar columna id_dept a la tabla cajas.
 *
 * Propósito: Permite asociar cada caja de compensación a su departamento
 * para mostrarla primero en el select cuando el cliente pertenece a ese dpto.
 *
 * Se puebla automáticamente usando una inferencia desde el campo 'ciudad'.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columna id_dept (nullable para cajas sin departamento asignado)
        Schema::table('cajas', function (Blueprint $table) {
            $table->unsignedSmallInteger('id_dept')->nullable()->after('ciudad')
                  ->comment('FK a departamentos.id — departamento principal de la caja');
        });

        // 2. Poblar id_dept según el campo 'ciudad' existente
        //    Mapa ciudad (parcial) → id departamento (código DANE)
        $mapaCiudadDept = [
            // Valle del Cauca (76)
            'Cali'           => 76,
            'Santiago de Cal'=> 76,

            // Antioquia (5)
            'Medellín'       => 5,
            'Medell'         => 5,

            // Bogotá (11)
            'Bogotá'         => 11,
            'Bogota'         => 11,

            // Atlántico (8)
            'Barranquilla'   => 8,

            // Bolívar (13)
            'Cartagena'      => 13,

            // Santander (68)
            'Bucaramanga'    => 68,

            // N. de Santander (54)
            'Cúcuta'         => 54,
            'Cucuta'         => 54,
            'San José de Cúc'=> 54,

            // Tolima (73)
            'Ibagué'         => 73,
            'Ibague'         => 73,

            // Cundinamarca (25)
            'Zipaquirá'      => 25,
            'Zipaquira'      => 25,
            'Fusagasugá'     => 25,

            // Caldas (17)
            'Manizales'      => 17,

            // Risaralda (66)
            'Pereira'        => 66,

            // Quindío (63)
            'Armenia'        => 63,

            // Nariño (52)
            'San Juan de Pas'=> 52,
            'Pasto'          => 52,

            // Huila (41)
            'Neiva'          => 41,

            // Cesar (20)
            'Valledupar'     => 20,

            // Córdoba (23)
            'Montería'       => 23,
            'Monteria'       => 23,

            // Arauca (81)
            'Arauca'         => 81,

            // Putumayo (86)
            'Puerto Asís'    => 86,
            'Puerto As'      => 86,

            // Boyacá (15)
            'Tunja'          => 15,

            // Sucre (70)
            'Sincelejo'      => 70,

            // Magdalena (47)
            'Santa Marta'    => 47,

            // Meta (50)
            'Villavicencio'  => 50,

            // Cauca (19)
            'Popayán'        => 19,
            'Popayan'        => 19,
        ];

        $cajas = DB::table('cajas')->whereNotNull('ciudad')->where('ciudad', '!=', '')->get();

        foreach ($cajas as $caja) {
            $ciudad  = $caja->ciudad ?? '';
            $deptId  = null;

            foreach ($mapaCiudadDept as $patron => $idDept) {
                if (stripos($ciudad, $patron) !== false) {
                    $deptId = $idDept;
                    break;
                }
            }

            if ($deptId) {
                DB::table('cajas')->where('id', $caja->id)->update(['id_dept' => $deptId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->dropColumn('id_dept');
        });
    }
};
