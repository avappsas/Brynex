<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración: Corregir valores de ciudades.departamento_id.
 *
 * Contexto de la tabla original (antes de renombrarse):
 *   - IdCiudad    → id             (código DANE del municipio, ej: 5001, 76001)
 *   - Departamento (nvarchar)      ← contiene el código DANE del departamento (5, 8, 11...)
 *   - Municipio_No → departamento_id  ← RENOMBRADO INCORRECTO: era numeración secuencial
 *   - Ciudad       → nombre
 *
 * El error: se renombró Municipio_No a departamento_id, pero Municipio_No tenía
 * una numeración secuencial (1, 2, 3...) que NO corresponde a departamentos.id.
 *
 * La columna correcta es [Departamento] que contiene el código DANE real del
 * departamento (5=Antioquia, 8=Atlántico, 11=Bogotá, 76=Valle del Cauca...).
 *
 * Solución: Actualizar departamento_id con el valor CAST de la columna Departamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Copiar el código de departamento correcto (columna Departamento, nvarchar)
        // al campo departamento_id (int), que actualmente tiene valores incorrectos.
        DB::statement("UPDATE ciudades SET departamento_id = CAST(Departamento AS int)");
    }

    public function down(): void
    {
        // Restaurar a 0 (sin reversión exacta posible de los valores originales de Municipio_No)
        DB::statement("UPDATE ciudades SET departamento_id = 0");
    }
};
