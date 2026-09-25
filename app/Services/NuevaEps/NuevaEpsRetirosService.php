<?php

namespace App\Services\NuevaEps;

use App\Models\Tarea;
use App\Services\TareaAutomaticaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Compara los retiros que Nueva EPS tiene registrados con los de BryNex.
 *
 * Es el mismo hallazgo de la mora, pero visto antes de que cueste: un retiro
 * que no le llegó a la EPS no avisa, simplemente se sigue cobrando mes a mes
 * hasta que alguien mira el estado de cuenta. Aquí se ve el primer mes.
 *
 * Mira las dos direcciones, porque las dos duelen:
 *  - retirado en BryNex y activo en la EPS → se está pagando de más;
 *  - retirado en la EPS y vigente en BryNex → hay alguien cotizando sin
 *    servicio, que es peor: se entera el día que necesita al médico.
 */
class NuevaEpsRetirosService
{
    public const ENTIDAD = 'NUEVA EPS';

    private const PREFIJO = 'nuevaeps:retiro';

    public function __construct(private TareaAutomaticaService $tareas) {}

    /**
     * @return array{ok:bool, error?:string, nit?:string, cotizantes?:int, nuevas?:int, cerradas?:int, detalle?:array}
     */
    public function revisar(string $nit, bool $simular = false): array
    {
        $nit = preg_replace('/\D/', '', $nit);
        $lectura = NuevaEpsPortalService::cotizantes($nit);

        if (! ($lectura['ok'] ?? false)) {
            return ['ok' => false, 'error' => $lectura['error'] ?? 'El portal no respondió.', 'nit' => $nit];
        }

        $delPortal = collect($lectura['trabajadores'] ?? [])
            ->keyBy(fn ($t) => ltrim(preg_replace('/\D/', '', (string) $t['documento']), '0'));

        $detalle = [];
        $nuevas = 0;
        $vistas = [];

        foreach ($this->contratos($nit) as $contrato) {
            $documento = ltrim(preg_replace('/\D/', '', (string) $contrato->cedula), '0');
            $enEps = $delPortal->get($documento);

            // A quien la EPS no tiene en esta empresa no se le mira el retiro:
            // eso es otra conversación (afiliación), no esta.
            if (! $enEps) {
                continue;
            }

            $caso = $this->comparar($nit, $contrato, $enEps);

            if (! $caso) {
                continue;
            }

            $fin = count($detalle);
            $detalle[] = $caso;

            $llave = self::PREFIJO.":{$nit}:{$documento}";
            $vistas[] = $llave;

            if ($ya = $this->tareas->activaPorLlave((int) $contrato->aliado_id, $llave)) {
                $detalle[$fin]['accion'] = 'ya_existe';
                $detalle[$fin]['tarea_id'] = $ya->id;

                continue;
            }

            if ($simular) {
                $nuevas++;
                $detalle[$fin]['accion'] = 'abriria';

                continue;
            }

            $tarea = $this->tareas->abrir([
                'aliado_id' => (int) $contrato->aliado_id,
                'tipo' => 'mora_eps',
                'cedula' => $documento,
                'contrato_id' => $contrato->id,
                'razon_social_id' => $contrato->razon_social_id,
                'entidad' => self::ENTIDAD,
                'tarea' => $caso['tarea'],
                'observacion' => $caso['observacion'],
                'llave_auto' => $llave,
            ]);

            if ($tarea) {
                $nuevas++;
                $detalle[$fin]['accion'] = 'abierta';
                $detalle[$fin]['tarea_id'] = $tarea->id;
            }
        }

        return [
            'ok' => true,
            'nit' => $nit,
            'cotizantes' => $delPortal->count(),
            'nuevas' => $nuevas,
            'cerradas' => $this->cerrarResueltas($nit, $vistas, $simular, $detalle),
            'detalle' => $detalle,
        ];
    }

    /**
     * Qué descuadre hay entre las dos fechas, si es que hay alguno.
     *
     * @return array|null null cuando coinciden, que es lo normal
     */
    private function comparar(string $nit, object $contrato, array $enEps): ?array
    {
        $enBrynex = $contrato->fecha_retiro ? Carbon::parse($contrato->fecha_retiro) : null;
        $enPortal = self::fecha($enEps['fecha_retiro'] ?? null);

        $base = [
            'documento' => ltrim(preg_replace('/\D/', '', (string) $contrato->cedula), '0'),
            'nombre' => $enEps['nombre'] ?? null,
            'retiro_brynex' => $enBrynex?->format('d/m/Y'),
            'retiro_eps' => $enPortal?->format('d/m/Y'),
            'aliado_id' => (int) $contrato->aliado_id,
        ];

        // Retirado aquí y activo allá: cada mes que pase es plata de más.
        if ($enBrynex && ! $enPortal) {
            $meses = $enBrynex->diffInMonths(now());

            return $base + [
                'causa' => 'retiro_sin_reportar',
                'tarea' => 'Reportar a Nueva EPS el retiro del '.$enBrynex->format('d/m/Y').': la EPS lo tiene activo.',
                'observacion' => 'En BryNex el contrato está retirado desde el '.$enBrynex->format('d/m/Y')
                    .' y Nueva EPS no tiene fecha de retiro'
                    .($meses >= 1 ? " (van {$meses} mes(es))" : '')
                    .'. Reportarlo antes de que empiece a cobrar la cotización.',
            ];
        }

        // Retirado allá y vigente aquí: alguien cotizando sin servicio.
        if (! $enBrynex && $enPortal) {
            return $base + [
                'causa' => 'retirado_en_la_eps',
                'tarea' => 'Nueva EPS lo tiene retirado desde el '.$enPortal->format('d/m/Y').' y en BryNex sigue vigente.',
                'observacion' => 'Nueva EPS registró el retiro el '.$enPortal->format('d/m/Y')
                    .', pero el contrato sigue vigente en BryNex. Si está trabajando, no tiene EPS: revisar y reafiliar.',
            ];
        }

        // Las dos fechas, pero distintas: importa cuando cambian de mes, porque
        // el aporte se liquida por mes.
        if ($enBrynex && $enPortal && $enBrynex->format('Y-m') !== $enPortal->format('Y-m')) {
            return $base + [
                'causa' => 'fechas_distintas',
                'tarea' => 'El retiro no coincide: BryNex '.$enBrynex->format('d/m/Y').' y Nueva EPS '.$enPortal->format('d/m/Y').'.',
                'observacion' => 'El retiro está en meses distintos en cada lado (BryNex '.$enBrynex->format('d/m/Y')
                    .', Nueva EPS '.$enPortal->format('d/m/Y').'), así que el aporte de ese mes va a descuadrar. Corregir el que esté mal.',
            ];
        }

        return null;
    }

    /** Los contratos de esa empresa que la EPS podría tener afiliados. */
    private function contratos(string $nit)
    {
        return DB::table('contratos as c')
            ->join('razones_sociales as rs', 'rs.id', '=', 'c.razon_social_id')
            ->where('rs.nit', $nit)
            // Los muy viejos no dicen nada útil: la EPS ya los depuró.
            ->where(function ($q) {
                $q->whereNull('c.fecha_retiro')->orWhere('c.fecha_retiro', '>=', now()->subMonths(12)->toDateString());
            })
            ->orderByDesc('c.id')
            ->get(['c.id', 'c.aliado_id', 'c.cedula', 'c.razon_social_id', 'c.fecha_retiro', 'c.estado'])
            // Una persona puede tener varios contratos en la misma empresa; manda el último.
            ->unique(fn ($c) => ltrim(preg_replace('/\D/', '', (string) $c->cedula), '0'));
    }

    /** '30/09/2025' → Carbon, y cualquier otra cosa a null. */
    private static function fecha(?string $texto): ?Carbon
    {
        $texto = trim((string) $texto);

        return preg_match('#^\d{2}/\d{2}/\d{4}$#', $texto) ? Carbon::createFromFormat('d/m/Y', $texto)->startOfDay() : null;
    }

    private function cerrarResueltas(string $nit, array $vistas, bool $simular, array &$detalle): int
    {
        $abiertas = Tarea::whereNotNull('llave_auto')
            ->where('llave_auto', 'like', self::PREFIJO.":{$nit}:%")
            ->whereIn('estado', Tarea::ESTADOS_ACTIVOS)
            ->get();

        $cerradas = 0;

        foreach ($abiertas as $tarea) {
            if (in_array($tarea->llave_auto, $vistas, true)) {
                continue;
            }

            if ($simular) {
                $cerradas++;
                $detalle[] = ['documento' => (string) $tarea->cedula, 'accion' => 'cerraria', 'tarea_id' => $tarea->id];

                continue;
            }

            if ($this->tareas->cerrar($tarea, 'Los retiros ya coinciden entre BryNex y Nueva EPS el '.now()->format('d/m/Y').'.')) {
                $cerradas++;
                $detalle[] = ['documento' => (string) $tarea->cedula, 'accion' => 'cerrada', 'tarea_id' => $tarea->id];
            }
        }

        return $cerradas;
    }
}
