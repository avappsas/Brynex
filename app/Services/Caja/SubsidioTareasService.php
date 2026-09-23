<?php

namespace App\Services\Caja;

use App\Models\Contrato;
use App\Models\Tarea;
use App\Services\TareaAutomaticaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Convierte los bloqueos de subsidio que reporta la caja en tareas de BryNex.
 *
 * La caja retiene la cuota monetaria y dice por qué: casi siempre porque espera
 * los aportes, y si no, porque falta el certificado escolar o algún documento
 * del beneficiario. Cada motivo se trabaja distinto, así que cada uno abre su
 * tipo de tarea y se cierra por su cuenta.
 *
 * La entrada son los movimientos tal como los lista el portal —una fila por
 * bloqueo, con período, fecha, valor y motivo—, y lo que decide la tarea es la
 * pareja **trabajador + motivo + período**: si el mismo bloqueo sigue ahí
 * mañana, la tarea de ayer es la misma y no se duplica; si desapareció, se
 * cierra sola en positivo.
 */
class SubsidioTareasService
{
    /** Los motivos que se resuelven pagando o conciliando aportes. */
    private const MOTIVO_APORTES = '/aporte|mora|pago/i';

    /** Los que se resuelven mandándole un papel a la caja. */
    private const MOTIVO_DOCUMENTOS = '/certificad|escolar|estudio|documento|superviv/i';

    /** Con qué caja se trabaja si no dicen otra. */
    public const CAJA_POR_DEFECTO = 'COMFANDI';

    public function __construct(private TareaAutomaticaService $tareas) {}

    /**
     * @param  array  $movimientos  filas del portal: documento, tipo, periodo, fecha, valor, motivo
     * @param  array<string>  $revisados  documentos que sí se consultaron, para poder cerrar lo que ya no aparece
     * @return array{nuevas:int, cerradas:int, sin_contrato:int, detalle:array}
     */
    public function procesar(int $aliadoId, array $movimientos, array $revisados, bool $simular = false, ?string $nit = null, string $caja = self::CAJA_POR_DEFECTO): array
    {
        $bloqueos = $this->leer($movimientos);
        $contratos = $this->contratosDe($aliadoId, $revisados, $nit, $caja);

        $detalle = [];
        $nuevas = 0;
        $sinContrato = 0;
        $vistas = [];

        foreach ($bloqueos as $bloqueo) {
            $contrato = $contratos->get($bloqueo['documento']);

            if (! $contrato) {
                $sinContrato++;
                $detalle[] = $bloqueo + ['accion' => 'sin_contrato'];

                continue;
            }

            $llave = $this->llave($bloqueo, $caja);

            // El portal repite la fila del bloqueo una vez por beneficiario, y
            // las tres de Yesenia son el mismo hallazgo: una sola tarea. Sin
            // esto, la simulación contaba una por fila.
            if (in_array($llave, $vistas, true)) {
                $detalle[] = $bloqueo + ['accion' => 'repetido'];

                continue;
            }

            $vistas[] = $llave;

            if ($ya = $this->tareas->activaPorLlave($aliadoId, $llave)) {
                $detalle[] = $bloqueo + ['accion' => 'ya_existe', 'tarea_id' => $ya->id];

                continue;
            }

            if ($simular) {
                $nuevas++;
                $detalle[] = $bloqueo + ['accion' => 'abriria', 'tipo_tarea' => $this->tipoTarea($bloqueo['motivo'])];

                continue;
            }

            $tarea = $this->tareas->abrir([
                'aliado_id' => $aliadoId,
                'tipo' => $this->tipoTarea($bloqueo['motivo']),
                'cedula' => $bloqueo['documento'],
                'contrato_id' => $contrato->id,
                'razon_social_id' => $contrato->razon_social_id,
                'entidad' => mb_strtoupper($caja),
                'tarea' => $this->texto($bloqueo, $caja),
                'observacion' => $this->observacion($bloqueo, $caja),
                'llave_auto' => $llave,
            ]);

            if ($tarea) {
                $nuevas++;
                $detalle[] = $bloqueo + ['accion' => 'abierta', 'tarea_id' => $tarea->id];
            }
        }

        $cerradas = $this->cerrarResueltas($aliadoId, $revisados, $vistas, $simular, $detalle, $caja);

        return [
            'nuevas' => $nuevas,
            'cerradas' => $cerradas,
            'sin_contrato' => $sinContrato,
            'bloqueos' => $bloqueos->count(),
            'detalle' => $detalle,
        ];
    }

    /**
     * Cierra las tareas de quienes sí se revisaron y ya no tienen ese bloqueo.
     *
     * Solo se miran los documentos revisados en esta corrida: si a alguien no se
     * le consultó, su tarea se queda como está. Cerrar por no haber mirado sería
     * dar por resuelto lo que nadie comprobó.
     */
    private function cerrarResueltas(int $aliadoId, array $revisados, array $vistas, bool $simular, array &$detalle, string $caja = self::CAJA_POR_DEFECTO): int
    {
        if (! $revisados) {
            return 0;
        }

        $documentos = array_map(fn ($d) => $this->documento($d), $revisados);

        $abiertas = Tarea::where('aliado_id', $aliadoId)
            ->whereNotNull('llave_auto')
            ->where('llave_auto', 'like', self::prefijo($caja).':%')
            ->whereIn('estado', Tarea::ESTADOS_ACTIVOS)
            ->whereIn('cedula', $documentos)
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

            if ($this->tareas->cerrar($tarea, mb_convert_case($caja, MB_CASE_TITLE).' ya no reporta este bloqueo el '.now()->format('d/m/Y').': el subsidio quedó liberado.')) {
                $cerradas++;
                $detalle[] = ['documento' => (string) $tarea->cedula, 'accion' => 'cerrada', 'tarea_id' => $tarea->id];
            }
        }

        return $cerradas;
    }

    /**
     * Normaliza las filas del portal y se queda solo con los bloqueos.
     *
     * @return Collection<int, array{documento:string, periodo:string, fecha:?string, valor:int, motivo:string}>
     */
    public function leer(array $movimientos): Collection
    {
        return collect($movimientos)
            ->map(fn ($m) => [
                'documento' => $this->documento((string) ($m['documento'] ?? '')),
                'tipo' => Str::upper(trim((string) ($m['tipo'] ?? 'BLOQUEO'))),
                'periodo' => trim((string) ($m['periodo'] ?? '')),
                'fecha' => trim((string) ($m['fecha'] ?? '')) ?: null,
                'valor' => (int) abs((int) preg_replace('/\D/', '', (string) ($m['valor'] ?? 0))),
                'motivo' => trim((string) ($m['motivo'] ?? '')) ?: 'Sin motivo reportado',
            ])
            ->filter(fn ($m) => $m['documento'] !== '' && $m['tipo'] === 'BLOQUEO')
            ->values();
    }

    /** Trabajador + motivo + período: lo que identifica al hallazgo. */
    public function llave(array $bloqueo, string $caja = self::CAJA_POR_DEFECTO): string
    {
        return self::prefijo($caja).':'.$this->claseMotivo($bloqueo['motivo'])
            .':'.$bloqueo['documento']
            .':'.Str::slug($bloqueo['periodo'] ?: 'sin-periodo');
    }

    /**
     * Con qué empieza la llave de las tareas de esa caja.
     *
     * Un slug y no el nombre tal cual: "COMFENALCO VALLE" dejaría un espacio en
     * medio de la llave, que se busca con LIKE y se lee a diario.
     */
    public static function prefijo(string $caja): string
    {
        return Str::slug($caja);
    }

    /** aportes | documentos | otro */
    public function claseMotivo(string $motivo): string
    {
        if (preg_match(self::MOTIVO_DOCUMENTOS, $motivo)) {
            return 'documentos';
        }

        return preg_match(self::MOTIVO_APORTES, $motivo) ? 'aportes' : 'otro';
    }

    /**
     * El bloqueo por papeles se pide como documentos; el resto se trabaja con la
     * caja como un asunto de subsidio.
     */
    public function tipoTarea(string $motivo): string
    {
        return $this->claseMotivo($motivo) === 'documentos' ? 'solicitud_documentos' : 'subsidios';
    }

    private function texto(array $bloqueo, string $caja = self::CAJA_POR_DEFECTO): string
    {
        $valor = $bloqueo['valor'] ? ' por $'.number_format($bloqueo['valor'], 0, ',', '.') : '';
        $nombre = mb_convert_case($caja, MB_CASE_TITLE);

        return $this->claseMotivo($bloqueo['motivo']) === 'documentos'
            ? "{$nombre} bloqueó el subsidio de {$bloqueo['periodo']}{$valor}: {$bloqueo['motivo']}. Conseguir el documento y radicarlo en la caja."
            : "{$nombre} bloqueó el subsidio de {$bloqueo['periodo']}{$valor}: {$bloqueo['motivo']}. Verificar que el aporte esté pagado y reclamar la liberación.";
    }

    private function observacion(array $bloqueo, string $caja = self::CAJA_POR_DEFECTO): string
    {
        return sprintf('Bloqueo reportado por %s el %s. Período %s, valor retenido $%s. Motivo: %s.',
            mb_convert_case($caja, MB_CASE_TITLE),
            $bloqueo['fecha'] ?? 'sin fecha',
            $bloqueo['periodo'] ?: '—',
            number_format($bloqueo['valor'], 0, ',', '.'),
            $bloqueo['motivo']);
    }

    /**
     * El contrato al que pertenece cada bloqueo.
     *
     * La misma persona puede tener dos contratos vigentes en el aliado —Yesenia
     * tiene el de Construtech con Comfandi y otro de solo salud—, así que no
     * sirve el último por id: manda el de la empresa que se está revisando y,
     * en su defecto, cualquiera que cotice a esta caja.
     *
     * @return Collection<string, Contrato>
     */
    private function contratosDe(int $aliadoId, array $documentos, ?string $nit = null, string $caja = self::CAJA_POR_DEFECTO): Collection
    {
        if (! $documentos) {
            return collect();
        }

        $nit = $nit ? preg_replace('/\D/', '', $nit) : null;

        return Contrato::with('razonSocial')
            ->where('aliado_id', $aliadoId)
            ->where('estado', 'vigente')
            ->whereIn('cedula', array_map(fn ($d) => $this->documento($d), $documentos))
            ->whereHas('caja', fn ($k) => $k->where('nombre', 'like', '%'.$caja.'%'))
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn ($c) => $this->documento((string) $c->cedula))
            ->map(function ($suyos) use ($nit) {
                if ($nit) {
                    $deLaEmpresa = $suyos->first(fn ($c) => preg_replace('/\D/', '', (string) $c->razonSocial?->nit) === $nit);
                    if ($deLaEmpresa) {
                        return $deLaEmpresa;
                    }
                }

                return $suyos->first();
            });
    }

    private function documento(string $valor): string
    {
        return ltrim(preg_replace('/\D/', '', $valor), '0');
    }
}
