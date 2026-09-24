<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permisos que se le niegan a un usuario aunque su rol los traiga.
 *
 * El sistema solo sabía otorgar: un superadmin lo tiene todo menos lo
 * restringido, y no había forma de decir "superadmin, pero sin el estado
 * financiero de los aliados". Es una lista JSON de nombres de permiso
 * (`["informes.financiero"]`) que el Gate::before y `User::hasPermissionTo`
 * revisan antes que cualquier otra regla. Se maneja con `permisos:negar`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('permisos_negados')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permisos_negados');
        });
    }
};
