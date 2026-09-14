<?php

namespace App\Console\Commands;

use App\Services\EpsSura\EpsSuraConfirmacionService;
use Illuminate\Console\Command;

/**
 * Cruce nocturno contra el informe de afiliados de EPS SURA: confirma los
 * radicados de EPS de quienes la empresa ya tiene como cotizantes con derecho.
 * Programado a las 21:00 en el Kernel. Ver EpsSuraConfirmacionService.
 */
class EpsSuraConfirmarAfiliados extends Command
{
    protected $signature = 'eps:confirmar-sura
                            {--aliado= : Solo este aliado (por defecto, todos)}
                            {--nit= : Solo esta empresa}
                            {--simular : Baja los informes pero no toca ningún radicado}';

    protected $description = 'Confirma los radicados de EPS SURA cruzando con el informe de afiliados de cada empresa';

    public function handle(EpsSuraConfirmacionService $servicio): int
    {
        $simular = (bool) $this->option('simular');
        $inicio  = now();

        $r = $servicio->confirmar(
            $this->option('aliado') ? (int) $this->option('aliado') : null,
            $this->option('nit') ?: null,
            $simular,
            fn (string $m) => $this->line('['.now()->format('H:i:s').'] '.$m),
        );

        $cambios = collect($r['detalle'])->whereIn('accion', ['confirmado', 'confirmaria', 'revisar', 'error']);
        if ($cambios->isNotEmpty()) {
            $this->table(['Aliado', 'Radicado', 'Cédula', 'Nombre', 'Empresa', 'Antes', 'Acción', 'Detalle'],
                $cambios->map(fn ($f) => [$f['aliado_id'], $f['radicado_id'], $f['cedula'], $f['nombre'], $f['empresa'], $f['estado_antes'], $f['accion'], $f['mensaje']])->all());
        }

        $this->info(sprintf('%s%d confirmados · %d no aparecen · %d por revisar · %d errores (de %d en %d empresas, %ss).',
            $simular ? '[SIMULACIÓN] ' : '', $r['confirmados'], $r['no_aparecen'], $r['revisar'], $r['errores'],
            $r['candidatos'], $r['empresas'], $inicio->diffInSeconds(now())));

        return self::SUCCESS;
    }
}
