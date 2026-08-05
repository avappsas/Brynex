<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `asignable` = ¿este permiso aparece en la pantalla de permisos por usuario?
 *
 * La primera versión mostraba los 107 permisos y era ilegible: la mayoría los
 * tiene cualquier trabajador del aliado (ver clientes, crear contratos, subir
 * documentos), así que marcarlos o no daba exactamente igual.
 *
 * Regla, que el seeder aplica solo: **si el rol `usuario` ya lo trae, no es
 * asignable** — no hay nada que decidir, porque el sistema solo otorga
 * permisos, nunca los quita. Quedan visibles únicamente los que de verdad
 * distinguen a una persona de otra: lo que solo tiene admin, lo que no tiene
 * nadie por defecto, y los restringidos.
 *
 * Los NO asignables siguen existiendo y siendo exigidos por el middleware
 * igual que antes; simplemente no se pintan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->boolean('asignable')->default(true)->after('restringido');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('asignable');
        });
    }
};
