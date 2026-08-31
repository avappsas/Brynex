<?php

namespace App\Console\Commands;

use App\Models\RazonSocialCargo;
use Illuminate\Console\Command;

/**
 * Siembra el catálogo común de cargos, con su código de ocupación DANE.
 *
 * Los códigos salen del catálogo oficial de ARL Sura
 * (`sel-services/cargo/ocupacionesDane`, 401 ocupaciones), así que el cargo deja
 * de ser texto libre y queda atado a algo verificable.
 *
 * Van con `razon_social_id` en NULL: son genéricos y los ve cualquier razón
 * social. Cada empresa puede agregar los suyos aparte.
 *
 * El nivel de riesgo es el de uso habitual en las empresas del aliado
 * (callcenter, confección, construcción, mensajería y ventas). No es una verdad
 * normativa: la clase la define la actividad económica del empleador, y trabajo
 * en alturas la sube a V aunque el oficio sea el mismo.
 */
class ArlSembrarCargos extends Command
{
    protected $signature = 'arl:sembrar-cargos {--aliado=1} {--dry-run}';

    protected $description = 'Siembra el catálogo común de cargos con su código de ocupación DANE';

    /** [cargo, código DANE, nivel de riesgo, por defecto del nivel] */
    private const CARGOS = [
        // ── Oficina, ventas y callcenter ──
        ['ASESOR COMERCIAL',              '3414', 1, true],
        ['VENDEDOR DE ALMACEN',           '5320', 1, false],
        ['TELEOPERADOR CALLCENTER',       '4223', 1, false],
        ['SISTEMAS',                      '3121', 1, false],
        ['SECRETARIA',                    '4113', 1, false],
        ['RECEPCIONISTA',                 '4222', 1, false],
        ['AUXILIAR CONTABLE',             '4121', 1, false],
        ['SUPERVISOR DE VENTAS',          '1412', 1, false],

        // ── Confección ──
        ['OPERARIO DE MAQUINA DE COSER',  '8263', 2, true],
        ['CORTADOR / PATRONISTA',         '8267', 2, false],
        ['SASTRE / MODISTA',              '7723', 2, false],
        ['MESERO',                        '5122', 2, false],

        // ── Construcción y servicios (riesgo 3) ──
        ['OBRERO DE CONSTRUCCION',        '9313', 3, true],
        ['ALBAÑIL',                       '7211', 3, false],
        ['ELECTRICISTA DE OBRA',          '7226', 3, false],
        ['PINTOR DE OBRA',                '7232', 3, false],
        ['SOLDADOR',                      '7312', 3, false],
        ['COCINERO',                      '5121', 3, false],
        ['SERVICIOS GENERALES / ASEO',    '9221', 3, false],
        ['VIGILANTE',                     '9133', 3, false],

        // ── Transporte y mensajería ──
        ['MENSAJERO',                     '9131', 4, true],
        ['CONDUCTOR',                     '8321', 4, false],

        // ── Trabajo en alturas: mismo oficio, otra clase de riesgo ──
        ['OBRERO DE CONSTRUCCION EN ALTURAS', '9313', 5, true],
        ['ALBAÑIL EN ALTURAS',            '7211', 5, false],
        ['ELECTRICISTA EN ALTURAS',       '7226', 5, false],
        ['PINTOR EN ALTURAS',             '7232', 5, false],
        ['SOLDADOR EN ALTURAS',           '7312', 5, false],
    ];

    public function handle(): int
    {
        $aliadoId = (int) $this->option('aliado');
        $seco     = (bool) $this->option('dry-run');
        $nuevos   = 0;

        foreach (self::CARGOS as [$cargo, $codigo, $riesgo, $porDefecto]) {
            $existe = RazonSocialCargo::whereNull('razon_social_id')->where('cargo', $cargo)->exists();

            if (! $seco) {
                RazonSocialCargo::updateOrCreate(
                    ['razon_social_id' => null, 'cargo' => $cargo],
                    [
                        'aliado_id'        => $aliadoId,
                        'codigo_ocupacion' => $codigo,
                        'nivel_riesgo'     => $riesgo,
                        'por_defecto'      => $porDefecto,
                        'activo'           => true,
                    ]
                );
            }

            $nuevos += $existe ? 0 : 1;
            $this->line(sprintf('  riesgo %s  %-36s %s%s', $riesgo, $cargo, $codigo, $porDefecto ? '   ← por defecto' : ''));
        }

        $this->newLine();
        $this->info(($seco ? '[dry-run] ' : '').count(self::CARGOS).' cargos en el catálogo ('.$nuevos.' nuevos).');

        return self::SUCCESS;
    }
}
