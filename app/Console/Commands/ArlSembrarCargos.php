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

        // ── Administrativos ──
        ['AUXILIAR ADMINISTRATIVO',       '4123', 1, false],
        ['ASISTENTE DE GERENCIA',         '4113', 1, false],
        ['DIGITADOR',                     '4112', 1, false],
        ['ARCHIVISTA',                    '4141', 1, false],
        ['CAJERO',                        '4211', 1, false],
        ['AUXILIAR DE INVENTARIOS',       '4131', 1, false],
        ['AUXILIAR DE NOMINA',            '4122', 1, false],
        ['CONTADOR',                      '2411', 1, false],
        ['ANALISTA FINANCIERO',           '2413', 1, false],
        ['ADMINISTRADOR DE EMPRESAS',     '2419', 1, false],
        ['COORDINADOR ADMINISTRATIVO',    '1411', 1, false],
        ['JEFE DE TALENTO HUMANO',        '1322', 1, false],
        ['JEFE ADMINISTRATIVO Y FINANCIERO', '1321', 1, false],
        ['GERENTE',                       '1211', 1, false],
        ['DIRECTOR DE SUCURSAL',          '1212', 1, false],
        ['JEFE DE SISTEMAS',              '1326', 1, false],

        // ── Comerciales ──
        ['JEFE COMERCIAL',                '1323', 1, false],
        ['JEFE DE MERCADEO Y PUBLICIDAD', '1324', 1, false],
        ['COORDINADOR DE MERCADEO',       '1413', 1, false],
        ['AGENTE DE SEGUROS',             '3411', 1, false],
        ['ASESOR INMOBILIARIO',           '3412', 1, false],
        ['ASESOR DE VIAJES',              '3413', 1, false],
        ['COMPRADOR',                     '3415', 1, false],
        ['AUXILIAR DE COMPRAS',           '3421', 1, false],
        ['AUXILIAR DE COMERCIO EXTERIOR', '3422', 1, false],
        ['CORREDOR COMERCIAL',            '3429', 1, false],
        ['ASESOR DE TELEVENTAS',          '5342', 1, false],
        // Van a la calle a diario, así que rara vez son clase I.
        ['VENDEDOR EN PUESTO DE MERCADO', '5330', 2, false],
        ['VENDEDOR AMBULANTE',            '5341', 2, false],
        ['IMPULSADOR / MERCADERISTA',     '5320', 2, false],

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
