<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuentas / bolsillos (Banco, Efectivo, Nequi...) para saber dónde está el dinero.
     * Migración aditiva: crea tablas y columnas nuevas, siembra las 3 cuentas base
     * por usuario y asigna el histórico existente a la cuenta "Banco".
     */
    public function up(): void
    {
        Schema::connection('finanzas')->create('finanzas_cuentas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nombre', 50);
            $table->string('tipo', 20)->default('banco'); // banco | efectivo | billetera | otro
            $table->string('icono', 10)->nullable();
            $table->string('color', 10)->nullable();
            $table->decimal('saldo_inicial', 18, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'activo'], 'ix_cuenta_user_activo');
        });

        Schema::connection('finanzas')->create('finanzas_cuenta_transferencias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cuenta_origen_id');
            $table->unsignedBigInteger('cuenta_destino_id');
            $table->date('fecha');
            $table->decimal('monto', 18, 2);
            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            $table->foreign('cuenta_origen_id')->references('id')->on('finanzas_cuentas');
            $table->foreign('cuenta_destino_id')->references('id')->on('finanzas_cuentas');
            $table->index(['user_id', 'fecha'], 'ix_transf_user_fecha');
        });

        // Columna cuenta_id en las tablas de movimientos de dinero
        Schema::connection('finanzas')->table('finanzas_gastos', function (Blueprint $table) {
            $table->unsignedBigInteger('cuenta_id')->nullable()->after('categoria_id');
            $table->index('cuenta_id', 'ix_gasto_cuenta');
        });

        Schema::connection('finanzas')->table('finanzas_entradas', function (Blueprint $table) {
            $table->unsignedBigInteger('cuenta_id')->nullable();
            $table->index('cuenta_id', 'ix_entrada_cuenta');
        });

        Schema::connection('finanzas')->table('finanzas_prestamo_movimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('cuenta_id')->nullable();
            $table->index('cuenta_id', 'ix_prestmov_cuenta');
        });

        Schema::connection('finanzas')->table('finanzas_proyecto_movimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('cuenta_id')->nullable();
            $table->index('cuenta_id', 'ix_proymov_cuenta');
        });

        // ── Seed: 3 cuentas base por cada usuario con datos en finanzas ──
        $conn = DB::connection('finanzas');

        $userIds = collect()
            ->merge($conn->table('finanzas_gastos')->distinct()->pluck('user_id'))
            ->merge($conn->table('finanzas_entradas')->distinct()->pluck('user_id'))
            ->merge($conn->table('finanzas_prestamos')->distinct()->pluck('user_id'))
            ->merge($conn->table('finanzas_proyectos')->distinct()->pluck('user_id'))
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            $bancoId = $conn->table('finanzas_cuentas')->insertGetId([
                'user_id' => $userId,
                'nombre' => 'Banco',
                'tipo' => 'banco',
                'icono' => '🏦',
                'color' => '#4f46e5',
                'saldo_inicial' => 0,
                'activo' => true,
                'orden' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $conn->table('finanzas_cuentas')->insert([
                [
                    'user_id' => $userId,
                    'nombre' => 'Efectivo',
                    'tipo' => 'efectivo',
                    'icono' => '💵',
                    'color' => '#10b981',
                    'saldo_inicial' => 0,
                    'activo' => true,
                    'orden' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'user_id' => $userId,
                    'nombre' => 'Nequi',
                    'tipo' => 'billetera',
                    'icono' => '📱',
                    'color' => '#a21caf',
                    'saldo_inicial' => 0,
                    'activo' => true,
                    'orden' => 3,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // ── Backfill: todo el histórico sin cuenta queda en "Banco" ──
            $conn->table('finanzas_gastos')
                ->where('user_id', $userId)
                ->whereNull('cuenta_id')
                ->update(['cuenta_id' => $bancoId]);

            $conn->table('finanzas_entradas')
                ->where('user_id', $userId)
                ->whereNull('cuenta_id')
                ->update(['cuenta_id' => $bancoId]);

            $prestamoIds = $conn->table('finanzas_prestamos')->where('user_id', $userId)->pluck('id');
            $conn->table('finanzas_prestamo_movimientos')
                ->whereIn('prestamo_id', $prestamoIds)
                ->whereNull('cuenta_id')
                ->update(['cuenta_id' => $bancoId]);

            $proyectoIds = $conn->table('finanzas_proyectos')->where('user_id', $userId)->pluck('id');
            $conn->table('finanzas_proyecto_movimientos')
                ->whereIn('proyecto_id', $proyectoIds)
                ->whereNull('cuenta_id')
                ->update(['cuenta_id' => $bancoId]);
        }
    }

    public function down(): void
    {
        Schema::connection('finanzas')->table('finanzas_proyecto_movimientos', function (Blueprint $table) {
            $table->dropIndex('ix_proymov_cuenta');
            $table->dropColumn('cuenta_id');
        });
        Schema::connection('finanzas')->table('finanzas_prestamo_movimientos', function (Blueprint $table) {
            $table->dropIndex('ix_prestmov_cuenta');
            $table->dropColumn('cuenta_id');
        });
        Schema::connection('finanzas')->table('finanzas_entradas', function (Blueprint $table) {
            $table->dropIndex('ix_entrada_cuenta');
            $table->dropColumn('cuenta_id');
        });
        Schema::connection('finanzas')->table('finanzas_gastos', function (Blueprint $table) {
            $table->dropIndex('ix_gasto_cuenta');
            $table->dropColumn('cuenta_id');
        });
        Schema::connection('finanzas')->dropIfExists('finanzas_cuenta_transferencias');
        Schema::connection('finanzas')->dropIfExists('finanzas_cuentas');
    }
};
