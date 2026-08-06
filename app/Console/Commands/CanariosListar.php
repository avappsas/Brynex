<?php

namespace App\Console\Commands;

use App\Models\Canario;
use App\Models\Cliente;
use Illuminate\Console\Command;

/**
 * Inventario de los registros trampa, y si siguen intactos.
 *
 * Se comprueba también que ninguno haya adquirido un contrato: un canario con
 * contrato dejaría de ser inofensivo y entraría en facturación y en los planos
 * PILA. Si eso pasa, hay que retirarlo ya.
 */
class CanariosListar extends Command
{
    protected $signature = 'canarios:listar';

    protected $description = 'Lista los registros trampa sembrados y verifica que sigan siendo inofensivos';

    public function handle(): int
    {
        $canarios = Canario::with('aliado')->orderBy('aliado_id')->get();

        if ($canarios->isEmpty()) {
            $this->warn('No hay canarios sembrados. Siémbralos con: php artisan canarios:sembrar --ejecutar');

            return self::SUCCESS;
        }

        $filas = [];
        $alertas = [];

        foreach ($canarios as $c) {
            $cliente = $c->referencia_id ? Cliente::find($c->referencia_id) : null;
            $contratos = $cliente ? $cliente->contratos()->count() : 0;

            $estado = match (true) {
                ! $c->activo => 'retirado',
                ! $cliente => 'BORRADO en clientes',
                $contratos > 0 => "⚠ CON {$contratos} CONTRATO(S)",
                default => 'ok',
            };

            if ($contratos > 0) {
                $alertas[] = sprintf(
                    'El canario CC %s de %s tiene %d contrato(s): ya no es inofensivo, retíralo.',
                    $c->cedula,
                    $c->aliado->nombre ?? $c->aliado_id,
                    $contratos
                );
            }

            $filas[] = [
                $c->aliado->nombre ?? ('aliado '.$c->aliado_id),
                $c->cedula,
                $c->nombre,
                $c->created_at?->format('Y-m-d') ?? '?',
                $estado,
            ];
        }

        $this->table(['Aliado', 'Cédula', 'Nombre', 'Sembrado', 'Estado'], $filas);

        foreach ($alertas as $alerta) {
            $this->error('  '.$alerta);
        }

        return self::SUCCESS;
    }
}
