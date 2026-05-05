<?php

namespace App\Console\Commands;

use App\Models\Consignacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Registra un "saldo inicial" en saldos_banco para dejar el saldo calculado
 * desde esa fecha en $0, SIN inventar gastos ficticios.
 *
 * La tabla saldos_banco actúa como libro contable: el último saldo_acumulado
 * es el saldo real. Al insertar un saldo_inicial negativo igual al saldo
 * histórico, los movimientos futuros parten desde $0.
 *
 * Uso:
 *   php artisan banco:ajuste-apertura
 *   php artisan banco:ajuste-apertura --banco=137 --aliado=2
 *   php artisan banco:ajuste-apertura --banco=137 --aliado=2 --confirmar
 */
class AjusteAperturaBanco extends Command
{
    protected $signature = 'banco:ajuste-apertura
                            {--banco=  : ID de la cuenta bancaria (banco_cuentas.id)}
                            {--aliado= : ID del aliado (por defecto el primero activo)}
                            {--fecha=  : Fecha del ajuste (Y-m-d, por defecto hoy)}
                            {--confirmar : Ejecutar sin confirmación interactiva}';

    protected $description = 'Registra un saldo inicial en saldos_banco para que el banco arranque en $0 desde hoy';

    public function handle(): int
    {
        // ── 1. Resolver aliado ───────────────────────────────────────────
        $aliadoId = $this->option('aliado')
            ? (int) $this->option('aliado')
            : DB::table('aliados')->orderBy('id')->value('id');

        if (! $aliadoId) {
            $this->error('No se encontró ningún aliado.');
            return 1;
        }

        // ── 2. Listar bancos del aliado ──────────────────────────────────
        $bancos = DB::table('banco_cuentas')
            ->where('aliado_id', $aliadoId)
            ->where('activo', true)
            ->get(['id', 'banco', 'nombre', 'numero_cuenta']);

        if ($bancos->isEmpty()) {
            $this->error("El aliado #{$aliadoId} no tiene cuentas bancarias activas.");
            return 1;
        }

        // ── 3. Seleccionar banco ─────────────────────────────────────────
        $bancoId = $this->option('banco') ? (int) $this->option('banco') : null;

        if (! $bancoId) {
            $this->info('Cuentas bancarias disponibles para aliado #' . $aliadoId . ':');
            $this->table(
                ['ID', 'Banco', 'Nombre', 'Número'],
                $bancos->map(fn($b) => [$b->id, $b->banco, $b->nombre, $b->numero_cuenta])
            );
            $bancoId = (int) $this->ask('¿ID de la cuenta a ajustar?');
        }

        $banco = $bancos->firstWhere('id', $bancoId);
        if (! $banco) {
            $this->error("No se encontró la cuenta bancaria ID={$bancoId} para aliado={$aliadoId}.");
            return 1;
        }

        // ── 4. Calcular saldo acumulado histórico (consignaciones - gastos) ──
        $saldoHistorico = Consignacion::saldoBanco($aliadoId, $bancoId);

        // Verificar si ya existe un saldo_inicial en saldos_banco
        $yaExiste = DB::table('saldos_banco')
            ->where('aliado_id', $aliadoId)
            ->where('banco_cuenta_id', $bancoId)
            ->where('tipo', 'saldo_inicial')
            ->exists();

        $this->line('');
        $this->info("Cuenta: [{$banco->id}] {$banco->banco} — {$banco->nombre} | {$banco->numero_cuenta}");
        $this->info('Aliado ID        : ' . $aliadoId);
        $this->info('Saldo histórico  : $' . number_format($saldoHistorico, 0, ',', '.'));

        if ($yaExiste) {
            $this->warn('⚠️  Ya existe un saldo_inicial registrado para esta cuenta.');
            $this->warn('   Si continúas se insertará un segundo ajuste. ¿Estás seguro?');
        }

        if ($saldoHistorico === 0) {
            $this->warn('El saldo ya está en $0. No es necesario ningún ajuste.');
            return 0;
        }

        // ── 5. Fecha del ajuste ──────────────────────────────────────────
        $fecha = $this->option('fecha') ?? now()->toDateString();

        // ── 6. Usuario administrador ─────────────────────────────────────
        $usuarioId = DB::table('users')
            ->where('aliado_id', $aliadoId)
            ->orderBy('id')
            ->value('id');

        // ── 7. Mostrar plan ──────────────────────────────────────────────
        $this->line('');
        $this->warn('Se insertará en saldos_banco (NO en gastos):');
        $this->line("  tipo             = saldo_inicial");
        $this->line("  descripcion      = Saldo de apertura BryNex — ajuste histórico migrado");
        $this->line("  banco_cuenta_id  = {$bancoId}  ({$banco->banco} {$banco->nombre})");
        $this->line("  valor            = \$" . number_format($saldoHistorico, 0, ',', '.') . "  (positivo, es el punto de partida)");
        $this->line("  saldo_acumulado  = \$0  (el banco arranca en 0 desde esta fecha)");
        $this->line("  fecha            = {$fecha}");
        $this->line('');
        $this->line('✅ Este método NO crea gastos ficticios. El saldo_banco.saldo_acumulado');
        $this->line('   será el que use el informe en lugar de recalcular desde consignaciones.');
        $this->line('   Los movimientos futuros se acumularán a partir de $0.');

        if (! $this->option('confirmar')) {
            if (! $this->confirm('¿Confirmar el ajuste?', false)) {
                $this->line('Ajuste cancelado.');
                return 0;
            }
        }

        // ── 8. Limpiar gastos ficticios del intento anterior (si los hay) ──
        $gastosAnteriores = DB::table('gastos')
            ->where('aliado_id', $aliadoId)
            ->where('banco_origen_id', $bancoId)
            ->where('tipo', 'ajuste_apertura')
            ->count();

        if ($gastosAnteriores > 0) {
            DB::table('gastos')
                ->where('aliado_id', $aliadoId)
                ->where('banco_origen_id', $bancoId)
                ->where('tipo', 'ajuste_apertura')
                ->delete();
            $this->warn("🗑  Se eliminaron {$gastosAnteriores} gasto(s) tipo ajuste_apertura del intento anterior.");
        }

        // ── 9. Insertar en saldos_banco ──────────────────────────────────
        // valor = 0: el campo solo necesita ser un marcador válido (INT).
        // El dato importante es saldo_acumulado = 0, que indica el punto de partida.
        // La descripción guarda el valor histórico neutralizado como referencia textual.
        DB::table('saldos_banco')->insert([
            'aliado_id'       => $aliadoId,
            'banco_cuenta_id' => $bancoId,
            'fecha'           => $fecha,
            'tipo'            => 'saldo_inicial',
            'descripcion'     => 'Saldo de apertura BryNex — saldo historico neutralizado: $'
                                 . number_format(abs($saldoHistorico), 0, ',', '.'),
            'cuadre_id'       => null,
            'gasto_id'        => null,
            'factura_id'      => null,
            'usuario_id'      => $usuarioId,
            'valor'           => 0,   // marcador: valor=0, lo que importa es saldo_acumulado
            'saldo_acumulado' => 0,   // desde esta fecha el banco arranca en $0
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // ── 10. Verificar ────────────────────────────────────────────────
        $nuevoSaldo = \App\Models\Consignacion::saldoBanco($aliadoId, $bancoId);
        $this->line('');
        $this->info('✅ Saldo inicial registrado en saldos_banco correctamente.');
        $this->info("   Saldo antes : \$" . number_format($saldoHistorico, 0, ',', '.'));
        $this->info("   Saldo ahora : \$" . number_format($nuevoSaldo, 0, ',', '.'));
        $this->line('');
        $this->line('El banco Bancolombia Brayan Garcia ahora arranca en $0 desde ' . $fecha . '.');
        $this->line('Solo se contabilizarán consignaciones y gastos posteriores a esa fecha.');

        return 0;
    }
}
