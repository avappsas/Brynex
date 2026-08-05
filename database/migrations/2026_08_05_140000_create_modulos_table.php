<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de módulos (funciones) de Brynex.
 *
 * Es la capa legible encima de los permisos de Spatie: un módulo agrupa varios
 * permisos con nombre `codigo.accion` (facturacion.ver, facturacion.anular…).
 * Sirve para tres cosas:
 *
 *  1. **Pintar la pantalla de permisos** agrupada por módulo, con nombre e
 *     ícono en español, sin hardcodear la lista en un Blade.
 *  2. **Marcar módulos restringidos** (`restringido = true`): esos NO los trae
 *     ningún rol, ni siquiera superadmin por Gate::before. Se otorgan usuario
 *     por usuario. Es el mecanismo para casos como "las claves de bancos solo
 *     las ven algunos admin".
 *  3. **Cruzar con lo que el aliado contrató.** `modulo_brynex_codigo` apunta
 *     al código de `brynex_modulos` (la tabla de FACTURACIÓN de Brynex al
 *     aliado). Si el aliado no tiene ese módulo activo en
 *     `brynex_modulos_aliado`, ningún usuario suyo debería verlo por más
 *     permisos que tenga.
 *
 * Las columnas nuevas en `permissions` son para lo mismo a nivel de acción:
 * un módulo puede ser abierto y una de sus acciones restringida (por ejemplo
 * claves_acceso.ver está para todos, claves_acceso.ver_contrasena no).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modulos', function (Blueprint $table) {
            $table->id();

            // Prefijo de los permisos del módulo: `clientes` → clientes.ver
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();

            // operacion | financiero | reportes | comunicacion | administracion | brynex
            $table->string('grupo', 30)->index();
            $table->string('icono', 10)->nullable();

            // Ruta principal del módulo (para el sidebar y los enlaces del hub)
            $table->string('ruta_nombre', 120)->nullable();

            // true = ningún rol lo trae por defecto, se otorga a dedo
            $table->boolean('restringido')->default(false)->index();

            // true = solo usuarios es_brynex (Hub BryNex, cobros a aliados…)
            $table->boolean('solo_brynex')->default(false);

            // Código en brynex_modulos: si el aliado no lo contrató, se oculta
            $table->string('modulo_brynex_codigo', 60)->nullable();

            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->index(['grupo', 'orden']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('modulo_id')->nullable()->after('guard_name');
            // Texto legible para la pantalla de asignación ("Anular facturas")
            $table->string('etiqueta', 120)->nullable()->after('modulo_id');
            $table->string('accion', 60)->nullable()->after('etiqueta');
            // Restringido a nivel de acción (independiente del módulo)
            $table->boolean('restringido')->default(false)->after('accion');
            $table->unsignedSmallInteger('orden')->default(0)->after('restringido');

            $table->index('modulo_id');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['modulo_id']);
            $table->dropColumn(['modulo_id', 'etiqueta', 'accion', 'restringido', 'orden']);
        });

        Schema::dropIfExists('modulos');
    }
};
