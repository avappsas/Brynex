<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tareas que crea sola la revisión de cajas, y el registro de esas corridas.
 *
 * `tareas.llave_auto` identifica de qué hallazgo nació una tarea —por ejemplo
 * "comfandi:bloqueo_aportes:1144132276:2026-07"—, para que el barrido de cada
 * noche reconozca la que ya creó en vez de abrir una nueva. La unicidad se
 * comprueba en código (TareaAutomaticaService) y no con un índice único: la
 * misma llave puede repetirse legítimamente cuando la anterior ya se cerró y el
 * bloqueo vuelve meses después, y un único filtrado obligaría a QUOTED_IDENTIFIER
 * en toda sesión que escriba en `tareas` (ver la memoria de índices creados a mano).
 *
 * `caja_revisiones` es la marca de "esto ya se hizo hoy": el comando nocturno y
 * el disparo desde el portal miran esta tabla antes de arrancar, así entrar diez
 * veces al módulo no dispara diez barridos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->string('llave_auto', 140)->nullable()->after('numero_radicado');
            $table->index(['aliado_id', 'llave_auto', 'estado'], 'IX_tareas_llave_auto');
        });

        Schema::create('caja_revisiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            // Qué se revisó: por ahora 'comfandi', mañana otra caja.
            $table->string('entidad', 30);
            $table->date('fecha');
            // corriendo | ok | error
            $table->string('estado', 20)->default('corriendo');
            // Alcance de la corrida: 'candidatos' (los sospechosos del día) o
            // 'completa' (todos los vigentes, una vez al mes).
            $table->string('alcance', 20)->default('candidatos');
            $table->unsignedInteger('revisados')->default(0);
            $table->unsignedInteger('bloqueados')->default(0);
            $table->unsignedInteger('tareas_nuevas')->default(0);
            $table->unsignedInteger('tareas_cerradas')->default(0);
            $table->string('mensaje', 500)->nullable();
            $table->timestamps();

            // Una corrida por aliado, entidad y día: es la llave del candado.
            $table->unique(['aliado_id', 'entidad', 'fecha'], 'UQ_caja_revisiones_dia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_revisiones');

        Schema::table('tareas', function (Blueprint $table) {
            $table->dropIndex('IX_tareas_llave_auto');
            $table->dropColumn('llave_auto');
        });
    }
};
