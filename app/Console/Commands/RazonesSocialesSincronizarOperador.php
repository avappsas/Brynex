<?php

namespace App\Console\Commands;

use App\Models\RazonSocial;
use App\Services\RazonSocialOperadorService;
use Illuminate\Console\Command;

/**
 * Actualiza las razones sociales con la ficha que tiene el operador de planilla
 * (ARUS o Simple): representante legal, municipio, exoneración y, donde BryNex
 * no tenga nada, dirección y teléfono. Ver RazonSocialOperadorService.
 */
class RazonesSocialesSincronizarOperador extends Command
{
    protected $signature = 'razones-sociales:sincronizar-operador
                            {--aliado= : Solo las razones sociales de este aliado}
                            {--rs= : Solo esta razón social (id)}
                            {--dry-run : Mostrar qué cambiaría, sin escribir}';

    protected $description = 'Trae del operador de planilla la ficha de cada empresa (representante, municipio, exoneración)';

    public function handle(RazonSocialOperadorService $servicio): int
    {
        $razones = RazonSocial::query()
            ->where('es_independiente', 0)
            ->where('estado', 'Activa')
            ->when($this->option('aliado'), fn ($q, $a) => $q->where('aliado_id', (int) $a))
            ->when($this->option('rs'), fn ($q, $id) => $q->where('id', (int) $id))
            ->orderBy('aliado_id')->orderBy('id')
            ->get();

        $aplicar = ! $this->option('dry-run');
        $ok = $sinDatos = 0;

        foreach ($razones as $rs) {
            $etiqueta = sprintf('[%d] %s · %s', $rs->aliado_id, $rs->nit, mb_substr((string) $rs->razon_social, 0, 32));
            $r = $servicio->sincronizar($rs, $aplicar);

            if (! $r['success']) {
                $this->line("  – {$etiqueta}: {$r['message']}");
                $sinDatos++;
                continue;
            }

            $ok++;
            $visibles = array_diff_key($r['cambios'], array_flip(['datos_operador', 'datos_operador_at']));
            $this->info("  ✔ {$etiqueta} ({$r['operador']})".($visibles ? '' : ': sin cambios visibles'));

            foreach ($visibles as $columna => [$antes, $despues]) {
                $fmt = fn ($v) => is_bool($v) ? ($v ? 'sí' : 'no') : (($v === null || $v === '') ? '∅' : (string) $v);
                $this->line("      {$columna}: {$fmt($antes)} → {$fmt($despues)}");
            }
        }

        $this->newLine();
        $this->info(($aplicar ? 'Actualizadas' : 'Se actualizarían').": {$ok}   ·   Sin datos del operador: {$sinDatos}");

        return self::SUCCESS;
    }
}
