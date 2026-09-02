<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\RazonSocial;
use App\Services\ArlSura\ArlConciliacionService;
use App\Services\ArlSura\ArlSuraSesionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Conciliación de afiliados entre ARL Sura y BryNex.
 *
 * Las dos listas se desfasan solas —alguien cambia de empresa, o se retira en
 * un lado y no en el otro— y el error solo aparece cuando hay un accidente sin
 * cobertura o cuando se paga por gente que ya no está.
 *
 * Va por NIT y no por razón social: la misma empresa está registrada en varios
 * aliados, mientras que en Sura hay una sola póliza para todos.
 *
 * Cada empresa se consulta bajo demanda. Consultarlas todas al abrir la
 * pantalla abriría una sesión por póliza —cada una con su navegador— y la
 * pantalla se quedaría minutos en blanco.
 */
class ArlConciliacionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** Las empresas con póliza, agrupadas por NIT, para elegir cuál conciliar. */
    public function index(Request $request)
    {
        $empresas = RazonSocial::whereNotNull('arl_poliza')
            ->where('arl_poliza', '<>', '')
            ->get(['id', 'nit', 'razon_social', 'arl_poliza', 'aliado_id'])
            ->groupBy(fn ($r) => preg_replace('/\D/', '', (string) $r->nit))
            ->map(function ($grupo) {
                $primera = $grupo->first();
                $ids     = $grupo->pluck('id');

                return (object) [
                    'nit'          => preg_replace('/\D/', '', (string) $primera->nit),
                    'razon_social' => $primera->razon_social,
                    'poliza'       => $primera->arl_poliza,
                    'razon_id'     => $primera->id,
                    'aliados'      => $grupo->count(),
                    // Lo que BryNex cree tener: el otro lado se pide al portal.
                    'vigentes'     => Contrato::whereIn('razon_social_id', $ids)
                        ->where('estado', 'vigente')->count(),
                    'tiene_clave'  => (bool) ArlSuraSesionService::credencialPara(
                        (int) $primera->aliado_id,
                        (string) $primera->arl_poliza,
                        $primera->nit
                    ),
                ];
            })
            ->sortBy('razon_social')
            ->values();

        return view('brynex.conciliacion_arl', compact('empresas'));
    }

    /**
     * El cruce de una empresa. Lo pide la pantalla por fila, no todo junto.
     */
    public function conciliar(Request $request, string $nit)
    {
        // Abre sesión en el portal y pagina sus afiliados: no cabe en 30 s.
        @set_time_limit(300);

        $nit     = preg_replace('/\D/', '', $nit);
        $empresa = RazonSocial::where('nit', $nit)->whereNotNull('arl_poliza')->first();

        if (! $empresa) {
            return response()->json(['ok' => false, 'mensaje' => 'Esa empresa no tiene póliza ARL registrada.'], 422);
        }

        try {
            $servicio = ArlConciliacionService::paraPoliza((int) $empresa->aliado_id, $empresa->arl_poliza);
            $r        = $servicio->conciliar($nit, $empresa->arl_poliza);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $r);
    }

    /**
     * Los que están en ambos lados pero con distinto nivel de riesgo.
     *
     * Aparte del cruce porque cuesta una consulta al portal por trabajador: en
     * una empresa de cincuenta personas son cincuenta viajes.
     */
    public function riesgos(Request $request, string $nit)
    {
        @set_time_limit(600);

        $nit     = preg_replace('/\D/', '', $nit);
        $empresa = RazonSocial::where('nit', $nit)->whereNotNull('arl_poliza')->first();

        if (! $empresa) {
            return response()->json(['ok' => false, 'mensaje' => 'Esa empresa no tiene póliza ARL registrada.'], 422);
        }

        try {
            $servicio    = ArlConciliacionService::paraPoliza((int) $empresa->aliado_id, $empresa->arl_poliza);
            $diferencias = $servicio->diferenciasDeRiesgo($nit, $empresa->arl_poliza);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'diferencias' => $diferencias]);
    }
}
