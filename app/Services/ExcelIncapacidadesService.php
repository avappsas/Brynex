<?php

namespace App\Services;

use App\Http\Controllers\Admin\IncapacidadController;
use App\Models\AbonoIncapacidad;
use App\Models\Incapacidad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ExcelIncapacidadesService
 *
 * Descarga en Excel la lista de incapacidades del aliado, con una hoja por
 * naturaleza del registro:
 *
 *   "Originales" → las incapacidades raíz (incapacidad_padre_id IS NULL)
 *   "Prórrogas"  → las continuaciones, con el id del padre al lado
 *
 * Separadas a propósito. En una sola hoja la prórroga repite cédula, entidad y
 * razón social del padre, así que cualquier suma de días o de valor esperado
 * sale inflada y el conteo de filas ya no es el conteo de casos.
 *
 * La hoja de prórrogas trae TODAS las de los padres exportados, incluso las
 * que están en un estado que la tabla oculta: media familia no sirve para
 * reclamarle a la entidad.
 */
class ExcelIncapacidadesService
{
    /**
     * SQL Server no acepta más de 2100 parámetros en una consulta, y un
     * whereIn con las cédulas de la exportación completa los pasa. Todo IN
     * sobre datos de la exportación va troceado por este tamaño.
     */
    private const LOTE_IN = 1000;

    /** Columnas que comparten las dos hojas, de la cédula en adelante. */
    private const ENCABEZADOS_COMUNES = [
        'Cédula', 'Tipo doc', 'Afiliado', 'Celular', 'Correo', 'Empresa',
        'Razón social', 'NIT razón social', 'Contrato',
        'Tipo de incapacidad', 'Diagnóstico', 'Concepto de rehabilitación',
        'Días', 'Fecha inicio', 'Fecha terminación', 'Fecha recibido',
        'Tipo de entidad', 'Entidad responsable',
        'N° de radicado', 'Fecha de radicado',
        'Transcripción requerida', 'Transcripción completada',
        'Estado', 'Estado de pago',
        'Salario base', 'Valor esperado',
        'Recibido de la entidad', 'Pagado al cliente', 'Pago directo al afiliado',
        'Prestado al cliente', 'Saldo pendiente',
        'Valor pago (campo)', 'Fecha de pago', 'Pagado a', 'Pagado a (tipo)', 'Detalle del pago',
        'Quien remite', 'Quien recibe', 'Creado por', 'Creado el',
        'Gestiones', 'Última gestión', 'Días sin gestión',
        'Observación', 'Descripción del cliente',
        'Motivo de anulación', 'Observación de anulación', 'Anulada por', 'Anulada el',
    ];

    /** Columnas con plata: se escriben como número para poder sumarlas en Excel. */
    private const ENCABEZADOS_MONEDA = [
        'Salario base', 'Valor esperado', 'Recibido de la entidad', 'Pagado al cliente',
        'Pago directo al afiliado', 'Prestado al cliente', 'Saldo pendiente', 'Valor pago (campo)',
    ];

    /** @var array<string,\Illuminate\Support\Collection> */
    private $entidades;

    /** @var \Illuminate\Support\Collection */
    private $usuarios;

    /** @var \Illuminate\Support\Collection */
    private $razonesSociales;

    /** @var \Illuminate\Support\Collection */
    private $clientes;

    /** @var \Illuminate\Support\Collection */
    private $empresas;

    /** @var \Illuminate\Support\Collection */
    private $abonos;

    /** @var \Illuminate\Support\Collection */
    private $gestiones;

    /**
     * @param  callable():Builder  $consultaPadres  Fábrica de la consulta ya filtrada
     *                                              de incapacidades raíz. Es una fábrica
     *                                              y no una consulta suelta porque se
     *                                              usa dos veces (filas y subconsulta).
     */
    public function descargar(callable $consultaPadres, int $aliadoId, string $nombreAliado = ''): StreamedResponse
    {
        // El aliado más grande arma unas 120.000 celdas y PhpSpreadsheet las
        // sostiene todas en memoria hasta que cierra el archivo: con los 128 MB
        // de fábrica la descarga muere a medio escribir y el navegador recibe
        // un ZIP roto. Solo sube el techo, nunca lo baja: si el servidor ya da
        // más (o no tiene límite), se respeta lo que haya.
        $limite = trim((string) ini_get('memory_limit'));
        if ($limite !== '' && $limite !== '-1') {
            $bytes = (int) $limite * match (strtoupper(substr($limite, -1))) {
                'G' => 1073741824, 'M' => 1048576, 'K' => 1024, default => 1,
            };
            if ($bytes < 512 * 1048576) {
                ini_set('memory_limit', '512M');
            }
        }

        // Sin eager loading de quienRecibe/creadoPor/anuladaPor/razonSocial: son
        // cuatro consultas por colección (ocho en total, dos de ellas idénticas)
        // y a 250 ms de red cada una pesan más que el resto de la descarga
        // junta. Se resuelven con dos mapas en precargarAuxiliares().
        // Sin withCount('prorrogas'): esa subconsulta correlacionada se cobra
        // una cuenta por fila y en el aliado más grande se llevó 106 de los
        // 133 segundos de la descarga. El número sale de las prórrogas que ya
        // se traen enteras unas líneas más abajo.
        $padres = $consultaPadres()
            ->orderByDesc('fecha_recibido')
            ->orderByDesc('id')
            ->get();

        // Subconsulta y no un whereIn con los ids: con 2.000 padres el whereIn
        // se pasa del tope de parámetros de SQL Server.
        $prorrogas = Incapacidad::where('aliado_id', $aliadoId)
            ->whereIn(
                'incapacidad_padre_id',
                $consultaPadres()->select('incapacidades.id')->getQuery()
            )
            ->orderBy('incapacidad_padre_id')
            ->orderBy('numero_proroga')
            ->get();

        $this->precargarAuxiliares($padres->concat($prorrogas), $aliadoId);

        // Cuántas prórrogas y cuántos días suman: el padre solo conoce los suyos.
        $porPadre = $prorrogas->groupBy('incapacidad_padre_id');
        $diasProrrogas = $porPadre->map(fn ($g) => (int) $g->sum('dias_incapacidad'));

        $libro = new Spreadsheet;
        $libro->getProperties()
            ->setCreator('BryNex')
            ->setTitle('Incapacidades'.($nombreAliado ? ' — '.$nombreAliado : ''));

        $hojaOriginales = $libro->getActiveSheet();
        $hojaOriginales->setTitle('Originales');
        $this->escribirHoja(
            $hojaOriginales,
            array_merge(['ID', 'Prórrogas', 'Días totales (familia)'], self::ENCABEZADOS_COMUNES),
            $padres->map(fn ($inc) => array_merge([
                $inc->id,
                $porPadre->has($inc->id) ? $porPadre[$inc->id]->count() : 0,
                (int) $inc->dias_incapacidad + (int) ($diasProrrogas[$inc->id] ?? 0),
            ], $this->columnasComunes($inc)))->all()
        );

        $hojaProrrogas = $libro->createSheet();
        $hojaProrrogas->setTitle('Prórrogas');
        $this->escribirHoja(
            $hojaProrrogas,
            array_merge(['ID', 'ID original', 'N° de prórroga'], self::ENCABEZADOS_COMUNES),
            $prorrogas->map(fn ($inc) => array_merge([
                $inc->id,
                $inc->incapacidad_padre_id,
                (int) $inc->numero_proroga,
            ], $this->columnasComunes($inc)))->all()
        );

        $libro->setActiveSheetIndex(0);

        $archivo = 'incapacidades_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, $archivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Carga de una sola vez lo que cuelga de las incapacidades exportadas.
     *
     * Cada consulta contra el SQL Server cuesta ~250 ms de red haga lo que
     * haga, así que aquí manda el número de consultas y no su tamaño: son
     * cuatro para toda la exportación, en vez de cuatro por fila.
     */
    private function precargarAuxiliares($incapacidades, int $aliadoId): void
    {
        // Catálogos de entidades: las incapacidades migradas del legacy guardan
        // el id de la EPS/ARL/AFP pero dejaron `entidad_nombre` vacío, y sin
        // esto la columna sale muda en miles de filas. Se leen con las mismas
        // llaves de caché que usa la lista, así que normalmente no van a la BD.
        $this->entidades = [
            'eps' => cache()->remember('eps_list', 3600, fn () => DB::table('eps')->orderBy('nombre')->get(['id', 'nombre']))
                ->pluck('nombre', 'id'),
            'arl' => cache()->remember('arl_list', 3600, fn () => DB::table('arls')->orderBy('nombre_arl')->get(['id', 'nombre_arl']))
                ->pluck('nombre_arl', 'id'),
            'afp' => cache()->remember('pension_list', 3600, fn () => DB::table('pensiones')->orderBy('razon_social')->get(['id', 'razon_social']))
                ->pluck('razon_social', 'id'),
        ];

        // Todos los usuarios y no solo los del aliado: `created_by` de una
        // incapacidad migrada puede apuntar a un usuario de BryNex.
        $this->usuarios = DB::table('users')->pluck('nombre', 'id');

        $this->razonesSociales = $this->porLotes(
            $incapacidades->pluck('razon_social_id')->filter()->unique()->values(),
            fn ($lote) => DB::table('razones_sociales')->whereIn('id', $lote)->get(['id', 'razon_social', 'nit'])
        )->keyBy('id');

        $cedulas = $incapacidades->pluck('cedula_usuario')->filter()->unique()->values();

        $this->clientes = $this->porLotes($cedulas, fn ($lote) => DB::table('clientes')
            ->where('aliado_id', $aliadoId)
            ->whereIn('cedula', $lote)
            ->get(['cedula', 'tipo_doc', 'primer_nombre', 'segundo_nombre', 'primer_apellido',
                'segundo_apellido', 'celular', 'correo', 'cod_empresa']))
            ->keyBy('cedula');

        $empresaIds = $this->clientes->pluck('cod_empresa')->filter()->unique()->values();

        $this->empresas = $this->porLotes($empresaIds, fn ($lote) => DB::table('empresas')
            ->where('aliado_id', $aliadoId)
            ->whereIn('id', $lote)
            ->get(['id', 'empresa']))
            ->keyBy('id');

        // Abonos y gestiones se agregan para TODO el aliado en una sola
        // consulta: filtrar por los ids exportados obligaría a trocear el IN y
        // a pagar varias idas a la base para ahorrar unas filas en memoria.
        $this->abonos = DB::table('abonos_incapacidades')
            ->where('aliado_id', $aliadoId)
            ->groupBy('incapacidad_id', 'tipo')
            ->select('incapacidad_id', 'tipo', DB::raw('SUM(valor) as total'))
            ->get()
            ->groupBy('incapacidad_id')
            ->map(fn ($g) => $g->pluck('total', 'tipo'));

        $this->gestiones = DB::table('gestiones_incapacidad as g')
            ->join('incapacidades as i', 'i.id', '=', 'g.incapacidad_id')
            ->where('i.aliado_id', $aliadoId)
            ->whereNull('i.deleted_at')
            ->groupBy('g.incapacidad_id')
            ->select(
                'g.incapacidad_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('MAX(g.created_at) as ultima'),
                // La gestión del padre marcada "aplica a familia" también cuenta
                // como gestión de sus prórrogas: es la regla del semáforo.
                DB::raw('MAX(CASE WHEN g.aplica_a_familia = 1 THEN g.created_at END) as ultima_familia')
            )
            ->get()
            ->keyBy('incapacidad_id');
    }

    /** Ejecuta un whereIn troceado y devuelve todas las filas juntas. */
    private function porLotes($valores, callable $consulta)
    {
        if ($valores->isEmpty()) {
            return collect();
        }

        return $valores->chunk(self::LOTE_IN)
            ->reduce(fn ($acc, $lote) => $acc->concat($consulta($lote->values()->all())), collect());
    }

    /** Las columnas que comparten las dos hojas, en el orden de ENCABEZADOS_COMUNES. */
    private function columnasComunes(Incapacidad $inc): array
    {
        $cliente = $this->clientes->get($inc->cedula_usuario);
        $rs = $inc->razon_social_id ? $this->razonesSociales->get($inc->razon_social_id) : null;
        $empresa = $cliente && $cliente->cod_empresa ? $this->empresas->get($cliente->cod_empresa) : null;
        $abonos = $this->abonos->get($inc->id) ?? collect();
        $gestion = $this->gestiones->get($inc->id);

        // Días sin gestión con la misma regla del semáforo de la tabla, pero
        // resuelta contra los mapas precargados: llamar a
        // diasDesdeUltimaGestion() aquí costaría dos consultas por fila.
        $ultimaGestion = $gestion->ultima ?? null;
        if ($inc->incapacidad_padre_id) {
            $delPadre = $this->gestiones->get($inc->incapacidad_padre_id)->ultima_familia ?? null;
            if ($delPadre && (! $ultimaGestion || $delPadre > $ultimaGestion)) {
                $ultimaGestion = $delPadre;
            }
        }

        $recibido = (float) ($abonos['entrada_incapacidad'] ?? 0);
        $alCliente = (float) ($abonos['pago_cliente'] ?? 0);
        $directo = (float) ($abonos['pago_directo_entidad'] ?? 0);
        $prestado = (float) ($abonos['abono'] ?? 0);

        // Mismo criterio que Incapacidad::getSaldoPendienteAttribute(): solo
        // descuentan los tipos marcados, y nunca baja de cero. Los estados que
        // ya no tienen nada por cobrar quedan en cero aunque no tengan abonos:
        // las pagadas del legacy se cerraron sin registrar el movimiento, y sin
        // esta regla la columna cobra de nuevo lo que ya se pagó — es la misma
        // lista que usa la columna "Valor Esperado" de la tabla.
        $descuentan = collect(AbonoIncapacidad::TIPOS_DESCUENTAN)
            ->sum(fn ($tipo) => (float) ($abonos[$tipo] ?? 0));
        $saldo = in_array($inc->estado, IncapacidadController::ESTADOS_SIN_PENDIENTE, true)
            ? 0.0
            : max(0, (float) ($inc->valor_esperado ?? 0) - $descuentan);

        $nombre = $cliente
            ? trim(preg_replace('/\s+/', ' ', ($cliente->primer_nombre ?? '').' '.($cliente->segundo_nombre ?? '')
                .' '.($cliente->primer_apellido ?? '').' '.($cliente->segundo_apellido ?? '')))
            : '';

        return [
            $inc->cedula_usuario,
            $cliente->tipo_doc ?? null,
            // Sin ficha de cliente el nombre queda vacío: se marca en vez de
            // dejar la celda muda, que se lee como un dato faltante cualquiera.
            $nombre !== '' ? $nombre : '(sin ficha de cliente)',
            $cliente->celular ?? null,
            $cliente->correo ?? null,
            $empresa->empresa ?? null,
            $rs->razon_social ?? $inc->razon_social_nombre,
            $rs->nit ?? null,
            $inc->contrato_id,
            $this->sinIcono(Incapacidad::TIPOS_INCAPACIDAD[$inc->tipo_incapacidad] ?? $inc->tipo_incapacidad),
            $inc->diagnostico,
            $inc->concepto_rehabilitacion,
            (int) $inc->dias_incapacidad,
            $this->fecha($inc->fecha_inicio),
            $this->fecha($inc->fecha_terminacion),
            $this->fecha($inc->fecha_recibido),
            Incapacidad::TIPOS_ENTIDAD[$inc->tipo_entidad] ?? $inc->tipo_entidad,
            $inc->entidad_nombre ?: ($this->entidades[$inc->tipo_entidad][$inc->entidad_responsable_id] ?? null),
            $inc->numero_radicado,
            $this->fecha($inc->fecha_radicado),
            $inc->transcripcion_requerida ? 'Sí' : 'No',
            $inc->transcripcion_completada ? 'Sí' : 'No',
            $this->sinIcono(Incapacidad::ESTADOS[$inc->estado]['label'] ?? $inc->estado),
            $this->sinIcono(Incapacidad::ESTADOS_PAGO[$inc->estado_pago]['label'] ?? $inc->estado_pago),
            $inc->salario_base !== null ? (float) $inc->salario_base : null,
            $inc->valor_esperado !== null ? (float) $inc->valor_esperado : null,
            $recibido,
            $alCliente,
            $directo,
            $prestado,
            $saldo,
            $inc->valor_pago !== null ? (float) $inc->valor_pago : null,
            $this->fecha($inc->fecha_pago),
            $inc->pagado_a,
            $inc->pagado_a_tipo,
            $inc->detalle_pago,
            $inc->quien_remite,
            $this->usuarios->get($inc->quien_recibe_id),
            $this->usuarios->get($inc->created_by),
            $this->fecha($inc->created_at, true),
            (int) ($gestion->total ?? 0),
            $this->fecha($ultimaGestion, true),
            max(0, (int) now()->diffInDays(sqldate($ultimaGestion) ?? $inc->created_at)),
            $inc->observacion,
            $inc->descripcion_cliente,
            $this->sinIcono(Incapacidad::MOTIVOS_ANULACION[$inc->motivo_anulacion] ?? $inc->motivo_anulacion),
            $inc->anulacion_observacion,
            $this->usuarios->get($inc->anulada_por),
            $this->fecha($inc->anulada_en, true),
        ];
    }

    /**
     * Vuelca encabezados y filas en la hoja y la deja usable: fila fija,
     * autofiltro, anchos y las columnas de plata como número.
     */
    private function escribirHoja($hoja, array $encabezados, array $filas): void
    {
        $hoja->fromArray($encabezados, null, 'A1');
        if (! empty($filas)) {
            $hoja->fromArray($filas, null, 'A2', true);
        }

        $ultimaColumna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($encabezados));
        $ultimaFila = count($filas) + 1;

        $hoja->getStyle('A1:'.$ultimaColumna.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F766E']],
        ]);
        $hoja->freezePane('A2');
        $hoja->setAutoFilter('A1:'.$ultimaColumna.'1');

        foreach ($encabezados as $i => $titulo) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);

            // Ancho calculado a ojo y no con setAutoSize(): el auto-ancho mide
            // cada celda con la métrica de la fuente y se llevaba 16 de los 20
            // segundos de la descarga. Se topa en 38 para que una observación
            // larga no deje una columna de pantalla y media.
            $ancho = mb_strlen($titulo);
            foreach ($filas as $fila) {
                $ancho = max($ancho, mb_strlen((string) ($fila[$i] ?? '')));
            }
            $hoja->getColumnDimension($col)->setWidth(min($ancho + 2, 38));

            if ($ultimaFila > 1 && in_array($titulo, self::ENCABEZADOS_MONEDA, true)) {
                $hoja->getStyle($col.'2:'.$col.$ultimaFila)
                    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            }
        }
    }

    /** '📬 Recibido' → 'Recibido'. Los emoji del panel no aportan en una celda. */
    private function sinIcono(?string $etiqueta): ?string
    {
        if ($etiqueta === null) {
            return null;
        }

        return trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', $etiqueta));
    }

    /** Fecha en el formato que espera quien abre el archivo aquí. */
    private function fecha($valor, bool $conHora = false): ?string
    {
        return sqldate($valor)?->format($conHora ? 'd/m/Y H:i' : 'd/m/Y');
    }
}
