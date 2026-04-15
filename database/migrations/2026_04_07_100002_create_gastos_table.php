<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aliado_id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('cuadre_id')->nullable();
            $table->date('fecha');
            $table->string('tipo', 50)->default('otro_oficina');
            $table->string('descripcion', 500);
            $table->string('pagado_a', 255)->nullable();
            $table->string('cc_pagado_a', 50)->nullable();
            $table->string('forma_pago', 30)->default('efectivo');
            $table->unsignedBigInteger('banco_origen_id')->nullable();
            $table->unsignedBigInteger('banco_destino_id')->nullable();
            $table->integer('valor');
            $table->string('recibo_caja', 100)->nullable();
            $table->string('lugar', 255)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['aliado_id', 'usuario_id', 'fecha']);
            $table->index(['aliado_id', 'cuadre_id']);
        });

        $this->importarLegacy();
    }

    private function importarLegacy(): void
    {
        try {
            $aliadoId = DB::table('aliados')->orderBy('id')->value('id');
            if (!$aliadoId) return;

            $primerDia  = now()->startOfMonth()->toDateString();
            $ultimoDia  = now()->endOfMonth()->toDateString();
            $usuarioId  = DB::table('users')->orderBy('id')->value('id');
            if (!$usuarioId) return;

            $legacy = DB::connection('sqlsrv_legacy')
                ->table('Gastos')
                ->whereBetween('Fecha', [$primerDia, $ultimoDia])
                ->get();

            foreach ($legacy as $g) {
                try {
                    $fecha = \Carbon\Carbon::parse($g->Fecha)->toDateString();
                } catch (\Throwable $e) {
                    $fecha = now()->toDateString();
                }

                $gasto = strtolower(trim($g->Gasto ?? ''));
                $tipo  = str_contains($gasto, 'nomina') ? 'nomina' : 'otro_oficina';

                $banco = trim($g->Banco ?? '');
                $formaPago = ($banco && strtolower($banco) !== 'efectivo')
                    ? 'transferencia_bancaria' : 'efectivo';

                DB::table('gastos')->insert([
                    'aliado_id'   => $aliadoId,
                    'usuario_id'  => $usuarioId,
                    'cuadre_id'   => null,
                    'fecha'       => $fecha,
                    'tipo'        => $tipo,
                    'descripcion' => trim($g->Concepto ?? 'Gasto importado'),
                    'pagado_a'    => trim($g->{'Pagado a:'} ?? '') ?: null,
                    'cc_pagado_a' => trim($g->cc_Pagadoa ?? '') ?: null,
                    'forma_pago'  => $formaPago,
                    'valor'       => (int) abs($g->VALOR ?? 0),
                    'recibo_caja' => trim($g->Recibo_Caja ?? '') ?: null,
                    'lugar'       => trim($g->lugar ?? '') ?: null,
                    'observacion' => trim($g->observacion ?? '') ?: null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Import legacy Gastos: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
