<?php

namespace App\Services\Publicidad;

use App\Models\ConsentimientoDato;
use App\Models\MarketingBloqueado;
use Illuminate\Support\Facades\DB;

/**
 * Arma las listas de celulares que se suben a Meta como Custom Audience para pautarles.
 *
 * El planteamiento es deliberado: a esta gente NO se le escribe por WhatsApp, se le muestra
 * publicidad. Escribirle a una base sin autorización expresa es lo que sanciona la SIC (caso
 * Movistar, Res. 78138 de 2025) y lo que degrada el quality rating del número. Mostrarle un
 * anuncio es publicidad segmentada, y cuando la persona hace clic es ELLA quien abre la
 * conversación — ahí no hay problema de opt-in y además Meta abre la ventana gratuita de 72h.
 *
 * Los segmentos salen de la medición del propio negocio: el 33% de los clientes vuelve, y el
 * 71,6% de los que vuelven lo hace dentro de los primeros 3 meses. Por eso la ventana corta
 * es un segmento aparte y no un detalle.
 */
class SegmentosAudiencia
{
    /** Definición de los segmentos disponibles, para poder listarlos en el panel. */
    public const SEGMENTOS = [
        'ventana_dorada' => [
            'nombre'      => 'Ventana dorada (retirados hace 0-3 meses)',
            'descripcion' => 'Los que más probablemente vuelven: el 71,6% de los reingresos ocurre en este lapso.',
            'meses'       => 3,
        ],
        'ventana_media' => [
            'nombre'      => 'Retirados hace 0-6 meses',
            'descripcion' => 'Segunda ola: todavía con intención de retorno medible.',
            'meses'       => 6,
        ],
        'ex_clientes' => [
            'nombre'      => 'Todos los ex-clientes',
            'descripcion' => 'Base completa de quienes estuvieron afiliados y hoy no tienen contrato vigente.',
            'meses'       => null,
        ],
        'vigentes' => [
            'nombre'      => 'Clientes vigentes',
            'descripcion' => 'Para EXCLUIR de las campañas de captación — no tiene sentido pagar por alguien que ya es cliente.',
            'meses'       => null,
            'vigentes'    => true,
        ],
    ];

    /**
     * Celulares del segmento, ya normalizados y sin quienes pidieron no ser contactados.
     *
     * Excluir a los de la lista de bajas no lo exige Meta —es publicidad, no mensajería—
     * pero si alguien pidió que no lo contactáramos, perseguirlo con anuncios es la misma
     * falta de respeto por otra vía, y es justo lo que hace que la gente reporte la marca.
     *
     * @return array<string> Celulares en formato 57XXXXXXXXXX.
     */
    public static function telefonos(string $clave, int $aliadoId): array
    {
        $def = self::SEGMENTOS[$clave] ?? null;
        if (!$def) {
            return [];
        }

        $crudos = !empty($def['vigentes'])
            ? self::celularesVigentes($aliadoId)
            : self::celularesExClientes($aliadoId, $def['meses']);

        $normalizados = array_values(array_unique(array_filter(
            array_map([ConsentimientoDato::class, 'normalizarTelefono'], $crudos)
        )));

        if (!$normalizados) {
            return [];
        }

        return ConsentimientoDato::filtrarContactables($aliadoId, $normalizados)['contactables'];
    }

    /** Resumen para mostrar en el panel sin llegar a subir nada. */
    public static function resumen(int $aliadoId): array
    {
        $filas = [];
        foreach (self::SEGMENTOS as $clave => $def) {
            $filas[$clave] = [
                'nombre'      => $def['nombre'],
                'descripcion' => $def['descripcion'],
                'total'       => count(self::telefonos($clave, $aliadoId)),
            ];
        }

        return $filas;
    }

    /**
     * Ex-clientes: tuvieron contrato retirado y HOY no tienen ninguno vigente. El vínculo
     * contrato→cliente es por `cedula`, no por un id — la tabla `contratos` no tiene
     * `cliente_id` (ver la estructura legacy).
     *
     * @return array<string>
     */
    private static function celularesExClientes(int $aliadoId, ?int $meses): array
    {
        $q = DB::table('clientes as cl')
            ->join('contratos as c', function ($j) use ($aliadoId) {
                $j->on('c.cedula', '=', 'cl.cedula')
                  ->where('c.aliado_id', '=', $aliadoId)
                  ->where('c.estado', '=', 'retirado');
            })
            ->where('cl.aliado_id', $aliadoId)
            ->whereNotNull('cl.celular')
            ->whereRaw("LEN(REPLACE(cl.celular,' ','')) >= 10")
            ->whereNotExists(function ($sub) use ($aliadoId) {
                $sub->select(DB::raw(1))
                    ->from('contratos as c2')
                    ->whereColumn('c2.cedula', 'cl.cedula')
                    ->where('c2.aliado_id', $aliadoId)
                    ->where('c2.estado', 'vigente');
            });

        if ($meses !== null) {
            $q->where('c.fecha_retiro', '>=', now()->subMonths($meses));
        }

        return $q->distinct()->pluck('cl.celular')->all();
    }

    /** @return array<string> */
    private static function celularesVigentes(int $aliadoId): array
    {
        return DB::table('clientes as cl')
            ->join('contratos as c', function ($j) use ($aliadoId) {
                $j->on('c.cedula', '=', 'cl.cedula')
                  ->where('c.aliado_id', '=', $aliadoId)
                  ->where('c.estado', '=', 'vigente');
            })
            ->where('cl.aliado_id', $aliadoId)
            ->whereNotNull('cl.celular')
            ->whereRaw("LEN(REPLACE(cl.celular,' ','')) >= 10")
            ->distinct()
            ->pluck('cl.celular')
            ->all();
    }
}
