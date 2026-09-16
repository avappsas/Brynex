<?php

namespace App\Services\Pension;

use App\Models\Radicado;
use App\Models\RadicadoMovimiento;
use App\Services\RegistroOficialService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cierra los radicados de pensión contra el RUAF.
 *
 * A diferencia de la EPS, la ARL y la caja, en pensión el vínculo es
 * persona ↔ fondo, no persona ↔ empresa: el empleador solo cotiza. Por eso no
 * hay portal de empleador que consultar y la fuente oficial es el RUAF, que se
 * consulta por el API del operador de planilla (ARUS / Simple), el mismo que ya
 * usa el formulario de clientes.
 *
 * El RUAF devuelve `administradoraRUAF` (el fondo) y `fechaAfiliacionRUAF`. Ojo
 * con `estado` y `regimen` del mismo payload: son del BDUA, o sea de salud, y no
 * dicen nada del fondo. Lo que confirma la pensión es tener administradora.
 *
 * Solo lee. Lo único que escribe es el radicado de BryNex.
 */
class PensionConciliacionService
{
    private const ABIERTOS = [Radicado::ESTADO_PENDIENTE, Radicado::ESTADO_TRAMITE, Radicado::ESTADO_ERROR];

    /** Nombre del confirmador en `radicados.confirmado_por`. */
    public const CONFIRMADOR = 'ruaf';

    /**
     * Como dice el RUAF que no hay fondo. "NIN-AF" es el que devuelve de verdad
     * (ver `ArlDatosFaltantesService` y `ruaf:completar-clientes`); los demás
     * salen de la tabla de fondos, donde NINGUNA y PENSIONADO van con 'N/A'.
     */
    private const SIN_FONDO = ['NIN-AF', 'NIN-EP', 'NINGUNA', 'N/A'];

    /** Respuestas del RUAF ya consultadas en esta corrida: una persona puede tener varios contratos. */
    private array $memoria = [];

    /**
     * Milisegundos entre consultas nuevas al operador. Por lo mismo que
     * `clientes:completar-ruaf`: una ráfaga seguida contra ARUS o Simple puede
     * leerse como abuso y costar el bloqueo de la cuenta.
     */
    private int $pausaMs = 250;

    public function __construct(private RegistroOficialService $registro) {}

    /**
     * @param  bool  $incluirOk  también revisa los que ya están en OK, para
     *                           detectar traslados de fondo que nadie registró.
     * @return Collection<int, Radicado>
     */
    public function pendientes(int $aliadoId, ?string $nit = null, bool $incluirOk = false): Collection
    {
        $nit = $nit ? preg_replace('/\D/', '', $nit) : null;
        $estados = $incluirOk ? [...self::ABIERTOS, Radicado::ESTADO_OK] : self::ABIERTOS;

        return Radicado::query()
            ->where('aliado_id', $aliadoId)
            ->where('tipo', Radicado::TIPO_PENSION)
            ->whereIn('estado', $estados)
            ->whereHas('contrato', fn ($c) => $c
                ->where('aliado_id', $aliadoId)
                ->where('estado', 'vigente')
                ->when($nit, fn ($q) => $q->whereHas('razonSocial', fn ($rs) => $rs->where('nit', $nit))))
            ->with(['contrato.cliente', 'contrato.razonSocial', 'contrato.pension'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  callable|null  $progreso  fn(string $mensaje, array $parcial)
     */
    public function conciliar(
        int $aliadoId,
        ?string $nit = null,
        bool $simular = false,
        ?int $usuarioId = null,
        ?callable $progreso = null,
        bool $incluirOk = false,
        int $pausaMs = 250,
    ): array {
        $this->pausaMs = max(0, $pausaMs);
        $avisar = $progreso ?? fn () => null;
        $detalle = [];
        $fondos = DB::table('pensiones')->get()->keyBy('id')->all();

        $radicados = $this->pendientes($aliadoId, $nit, $incluirOk);
        $total = $radicados->count();
        $personas = $radicados->pluck('contrato.cedula')->unique()->count();

        $avisar("{$total} radicados de pensión por revisar ({$personas} personas) en el RUAF.", $detalle);

        foreach ($radicados as $i => $r) {
            try {
                $detalle[] = $this->conciliarUno($aliadoId, $r, $fondos, $simular, $usuarioId);
            } catch (Throwable $e) {
                $detalle[] = $this->fila($r, 'error', $e->getMessage());
            }

            // Cada consulta al operador tarda unos 2 s: sin avisos el botón se
            // queda mudo varios minutos.
            if (($i + 1) % 5 === 0 || $i + 1 === $total) {
                $avisar('Consultando el RUAF… '.($i + 1)." de {$total}.", $detalle);
            }
        }

        $cuenta = collect($detalle)->countBy('accion');

        return [
            'total' => $total,
            'personas' => $personas,
            'cerrados' => $cuenta->get('cerrado', 0) + $cuenta->get('cerraria', 0),
            'sin_cambio' => $cuenta->get('confirmado', 0),
            'faltan' => $cuenta->get('falta', 0),
            'revisar' => $cuenta->get('revisar', 0),
            'errores' => $cuenta->get('error', 0),
            'simulado' => $simular,
            'incluye_ok' => $incluirOk,
            'detalle' => $detalle,
        ];
    }

    private function conciliarUno(int $aliadoId, Radicado $r, array $fondos, bool $simular, ?int $usuarioId): array
    {
        $c = $r->contrato;
        $cedula = preg_replace('/\D/', '', (string) $c->cedula);
        $tipoDoc = strtoupper((string) ($c->cliente?->tipo_doc ?: 'CC'));

        $ruaf = $this->consultar($aliadoId, $cedula, $tipoDoc);

        if ($ruaf === null) {
            return $this->fila($r, 'error', 'El operador de planilla no respondió la consulta al RUAF.');
        }

        $delContrato = $c->pension_id ? ($fondos[$c->pension_id] ?? null) : null;
        $enBrynex = $delContrato->razon_social ?? ($c->pension_id ? "fondo {$c->pension_id}" : null);
        $enRuafId = $ruaf['pension_id'] ?? null;
        $enRuaf = $ruaf['pension_nombre'] ?? null;
        $codigo = strtoupper(trim((string) ($ruaf['pension_codigo'] ?? '')));
        $desde = $this->fecha($ruaf['ruaf_desde'] ?? null);
        $porOperador = $ruaf['operador'] ?? 'el operador';

        // El contrato puede no cotizar a pensión: NINGUNA y PENSIONADO van con
        // código 'N/A' en la tabla de fondos.
        $noCotiza = ! $c->pension_id
            || in_array(strtoupper(trim((string) ($delContrato->codigo ?? ''))), self::SIN_FONDO, true);

        // Sin administradora en el RUAF no hay afiliación a pensión.
        if ($codigo === '' || in_array($codigo, self::SIN_FONDO, true)) {
            return $noCotiza
                ? $this->fila($r, 'revisar', 'Ni el contrato ni el RUAF reportan fondo de pensión'.($enBrynex ? " (el contrato está como {$enBrynex})" : '').': el radicado de pensión no aplica.')
                : $this->fila($r, 'falta', "No figura afiliado a ningún fondo de pensión en el RUAF y el contrato cotiza a {$enBrynex}: falta tramitar la vinculación.");
        }

        if (! $enRuafId) {
            return $this->fila($r, 'revisar', "El RUAF devuelve la administradora {$codigo}, que no está en la tabla de fondos de BryNex.");
        }

        if ($noCotiza) {
            return $this->fila($r, 'revisar', "En el RUAF figura {$enRuaf} desde {$desde}, pero el contrato ".($enBrynex ? "está como {$enBrynex}" : 'no tiene fondo').': revisar el contrato.');
        }

        if ((int) $enRuafId !== (int) $c->pension_id) {
            return $this->fila($r, 'revisar', "En el RUAF figura {$enRuaf} desde {$desde}, pero el contrato cotiza a {$enBrynex}: revisar traslado.");
        }

        $mensaje = "RUAF ({$porOperador}): afiliado a {$enRuaf} desde {$desde}.";

        // Ya cerrado y ya confirmado: se revisó solo para detectar traslados.
        if ($r->estado === Radicado::ESTADO_OK && $r->confirmado_por) {
            return $this->fila($r, 'confirmado', $mensaje);
        }

        if ($simular) {
            return $this->fila($r, 'cerraria', $mensaje);
        }

        $this->marcar($r, $mensaje, $usuarioId);

        return $this->fila($r, 'cerrado', $mensaje);
    }

    /** Una consulta por persona, no por radicado. */
    private function consultar(int $aliadoId, string $cedula, string $tipoDoc): ?array
    {
        $llave = $tipoDoc.'-'.$cedula;

        if (! array_key_exists($llave, $this->memoria)) {
            $this->memoria[$llave] = $this->registro->consultar($aliadoId, $cedula, $tipoDoc);

            if ($this->pausaMs > 0) {
                usleep($this->pausaMs * 1000);
            }
        }

        return $this->memoria[$llave];
    }

    private function marcar(Radicado $r, string $mensaje, ?int $usuarioId): void
    {
        DB::transaction(function () use ($r, $mensaje, $usuarioId) {
            $radicado = Radicado::whereKey($r->id)->lockForUpdate()->first();
            $anterior = $radicado->estado;

            $radicado->update([
                'estado' => Radicado::ESTADO_OK,
                'user_id' => $usuarioId,
                'fecha_inicio_tramite' => $radicado->fecha_inicio_tramite ?? now(),
                'fecha_confirmacion' => $radicado->fecha_confirmacion ?? now(),
                'observacion' => trim(($radicado->observacion ? $radicado->observacion.' | ' : '').'Conciliación con RUAF: '.$mensaje),
            ] + $radicado->datosConfirmacion(self::CONFIRMADOR));

            RadicadoMovimiento::create([
                'radicado_id' => $radicado->id,
                'contrato_id' => $radicado->contrato_id,
                'tipo_proceso' => 'afiliacion',
                'entidad' => Radicado::TIPO_PENSION,
                'user_id' => $usuarioId,
                'estado_anterior' => $anterior,
                'estado_nuevo' => Radicado::ESTADO_OK,
                'observacion' => 'Conciliación con RUAF: '.$mensaje,
            ]);
        });

        $r->refresh();
    }

    /** El RUAF entrega las fechas como AAAAMMDD. */
    private function fecha(?string $aaaammdd): string
    {
        return preg_match('/^\d{8}$/', (string) $aaaammdd)
            ? substr($aaaammdd, 6, 2).'/'.substr($aaaammdd, 4, 2).'/'.substr($aaaammdd, 0, 4)
            : '—';
    }

    private function fila(Radicado $r, string $accion, string $mensaje): array
    {
        $c = $r->contrato;

        return [
            'radicado_id' => $r->id,
            'contrato_id' => $c->id,
            'cedula' => (string) $c->cedula,
            'nombre' => trim(($c->cliente?->primer_nombre ?? '').' '.($c->cliente?->primer_apellido ?? '')),
            'empresa' => $c->razonSocial?->razon_social,
            'estado_antes' => $r->estado,
            'accion' => $accion,
            'mensaje' => $mensaje,
        ];
    }
}
