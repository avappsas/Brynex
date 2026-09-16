<?php

namespace App\Console\Commands;

use App\Services\ArlColmena\ColmenaConfirmacionService;
use Illuminate\Console\Command;

/**
 * Cruce nocturno contra el informe de vigentes de ARL Colmena: confirma los
 * radicados de ARL de quienes la empresa ya tiene afiliados.
 * Programado a las 21:30 en el Kernel. Ver ColmenaConfirmacionService.
 */
class ArlColmenaConfirmarAfiliados extends Command
{
    protected $signature = 'arl:confirmar-colmena
                            {--aliado= : Solo este aliado (por defecto, todos)}
                            {--nit= : Solo esta empresa}
                            {--simular : Baja los informes pero no toca ningún radicado}';

    protected $description = 'Confirma los radicados de ARL Colmena cruzando con el informe de vigentes de cada empresa';

    public function handle(ColmenaConfirmacionService $servicio): int
    {
        $simular = (bool) $this->option('simular');
        $inicio = now();

        $r = $servicio->confirmar(
            $this->option('aliado') ? (int) $this->option('aliado') : null,
            $this->option('nit') ?: null,
            $simular,
            fn (string $m) => $this->line('['.now()->format('H:i:s').'] '.$m),
        );

        // `no_aparece` también: un OK a mano que Colmena no tiene merece que
        // alguien lo mire, porque es alguien cobrando ARL sin cobertura.
        $cambios = collect($r['detalle'])->whereIn('accion', ['confirmado', 'confirmaria', 'revisar', 'no_aparece', 'error']);

        if ($cambios->isNotEmpty()) {
            $this->table(['Aliado', 'Radicado', 'Cédula', 'Nombre', 'Empresa', 'Antes', 'Acción', 'Detalle'],
                $cambios->map(fn ($f) => [$f['aliado_id'], $f['radicado_id'], $f['cedula'], $f['nombre'], $f['empresa'], $f['estado_antes'], $f['accion'], $f['mensaje']])->all());
        }

        $this->info(sprintf('%s%d confirmados · %d no aparecen · %d por revisar · %d errores · %d omitidos sin clave (de %d en %d empresas, %ss).',
            $simular ? '[SIMULACIÓN] ' : '', $r['confirmados'], $r['no_aparecen'], $r['revisar'], $r['errores'], $r['sin_clave'],
            $r['candidatos'], $r['empresas'], $inicio->diffInSeconds(now())));

        return self::SUCCESS;
    }
}
