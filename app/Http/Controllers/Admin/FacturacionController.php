<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Factura, Abono, Plano, Contrato, Empresa, RazonSocial, User, BancoCuenta};
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class FacturacionController extends Controller
{
    // ─── Buscador de empresa ─────────────────────────────────────────
    public function index(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $empresas = Empresa::where('aliado_id', $aliadoId)
            ->where('id', '>', 1)               // excluir "Individual"
            ->orderBy('empresa')
            ->get(['id','empresa','nit','contacto','telefono','celular','iva']);

        return view('admin.facturacion.index', compact('empresas'));
    }

    // ─── Vista empresa (tabla de trabajadores) ───────────────────────
    public function empresa(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        $mes  = (int) $request->get('mes',  now()->month);
        $anio = (int) $request->get('anio', now()->year);

        // Traer todos los contratos vigentes cuyos clientes pertenecen a esta empresa
        // La relación es: contratos.cedula → clientes.cedula → clientes.cod_empresa
        $cedulasEmpresa = DB::table('clientes')
            ->where('aliado_id', $aliadoId)
            ->where('cod_empresa', $empresaId)
            ->pluck('cedula');

        $contratos = Contrato::where('aliado_id', $aliadoId)
            ->whereIn('cedula', $cedulasEmpresa)
            ->whereIn('estado', ['vigente', 'activo'])
            ->with(['cliente', 'tipoModalidad', 'razonSocial', 'eps', 'arl', 'pension', 'caja', 'asesor'])
            ->orderBy('cedula')
            ->get();

        // Facturas ya generadas para este periodo (solo planilla/afiliacion, no otro_ingreso)
        $facturasExistentes = Factura::where('aliado_id', $aliadoId)
            ->periodo($mes, $anio)
            ->whereIn('tipo', ['planilla', 'afiliacion'])
            ->whereIn('cedula', $contratos->pluck('cedula'))
            ->get()
            ->keyBy('cedula');

        // Calcular días cotizados para cada contrato según fecha_ingreso
        $hoy       = now();
        $contratos = $contratos->map(function ($c) use ($mes, $anio, $hoy, $facturasExistentes) {
            $diasCotizar = 30;
            if ($c->fecha_ingreso) {
                $fIng = $c->fecha_ingreso;
                $mesIngreso  = (int)$fIng->month;
                $anioIngreso = (int)$fIng->year;

                if ($mesIngreso === $mes && $anioIngreso === $anio) {
                    // Mes de ingreso → afiliación, días = 0
                    $diasCotizar = 0;
                } else {
                    // Calcular el mes anterior al período actual
                    $mesAnterior  = $mes === 1 ? 12 : $mes - 1;
                    $anioAnterior = $mes === 1 ? $anio - 1 : $anio;

                    if ($mesIngreso === $mesAnterior && $anioIngreso === $anioAnterior) {
                        // Primera planilla: cubrir los días activos del mes de ingreso
                        $diasCotizar = max(1, 30 - $fIng->day + 1);
                    }
                    // else: mes normal → 30 días
                }
            }
            $c->dias_cotizar  = $diasCotizar;
            $c->factura_exist = $facturasExistentes->get($c->cedula);
            return $c;
        });

        // Cuentas bancarias + asesores
        $bancos   = BancoCuenta::activas($aliadoId);
        $asesores = \App\Models\Asesor::where('aliado_id', $aliadoId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        // N_PLANO actual por razón social
        $planosActuales = DB::table('planos')
            ->where('aliado_id', $aliadoId)
            ->where('mes_plano', $mes)->where('anio_plano', $anio)
            ->select('razon_social', DB::raw('MAX(n_plano) as n_plano_max'))
            ->groupBy('razon_social')
            ->get()->keyBy('razon_social');

        return view('admin.facturacion.empresa', compact(
            'empresa', 'contratos', 'facturasExistentes',
            'mes', 'anio', 'bancos', 'planosActuales', 'asesores'
        ));
    }

    // ─── Facturar (crear factura) ────────────────────────────────────
    public function facturar(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'contratos'            => 'required|array|min:1',
            'contratos.*'          => 'exists:contratos,id',
            'tipo'                 => 'required|in:afiliacion,planilla',
            'mes'                  => 'required|integer|min:1|max:12',
            'anio'                 => 'required|integer|min:2000|max:2100',
            'forma_pago'           => 'required|in:efectivo,consignacion,mixto,prestamo',
            'estado'               => 'required|in:pre_factura,pagada,prestamo',
            'es_prestamo'          => 'boolean',
            // Array dinámico de consignaciones bancarias
            'consignaciones'             => 'nullable|array',
            'consignaciones.*.banco_cuenta_id' => 'required_with:consignaciones|integer',
            'consignaciones.*.valor'           => 'required_with:consignaciones|numeric|min:0',
            'consignaciones.*.fecha'           => 'nullable|date',
            'consignaciones.*.referencia'      => 'nullable|string|max:100',
            'valor_efectivo'       => 'nullable|numeric|min:0',
            'valor_prestamo'       => 'nullable|numeric|min:0',
            'otros'                => 'nullable|numeric|min:0',
            'otros_admon'          => 'nullable|numeric|min:0',
            'mensajeria'           => 'nullable|numeric|min:0',
            'np'                   => 'nullable|integer',
            'n_plano'              => 'nullable|integer',
            'empresa_id'           => 'nullable|integer',
            'aplicar_saldo'        => 'boolean',
            'observacion'          => 'nullable|string|max:500',
            // SS editables desde la UI (override manual)
            'v_eps_manual'         => 'nullable|integer|min:0',
            'v_arl_manual'         => 'nullable|integer|min:0',
            'v_afp_manual'         => 'nullable|integer|min:0',
            'v_caja_manual'        => 'nullable|integer|min:0',
            // Distribución de afiliación (override manual desde la UI)
            'dist_asesor'          => 'nullable|integer|min:0',
            'dist_retiro'          => 'nullable|integer|min:0',
            'dist_encargado'       => 'nullable|integer|min:0',
            'dist_admon'           => 'nullable|integer|min:0',
        ]);

        $np = $validated['np'] ?? null;
        // Si es pago masivo y no tiene NP, generar uno nuevo
        if (!$np && count($validated['contratos']) > 1) {
            $np = (DB::table('facturas')
                ->where('aliado_id', $aliadoId)
                ->whereNull('deleted_at')
                ->max('np') ?? 0) + 1;
        }

        $mes  = (int)$validated['mes'];
        $anio = (int)$validated['anio'];

        // ─── Pre-calcular n_plano compartido por razón social ──────────────
        // Todos los contratos de la misma RS en este lote deben tener el MISMO n_plano.
        // Se calcula UNA VEZ por RS antes de entrar al foreach.
        $nPlanosPorRS = [];

        // ─── Pre-calcular totales del pago ─────────────────────────────────
        // valor_consignado = suma de TODAS las consignaciones del array
        $consignacionesData = $validated['consignaciones'] ?? [];
        $totalPagoConsig    = array_sum(array_column($consignacionesData, 'valor'));
        $totalPagoEfectivo  = (int)($validated['valor_efectivo']  ?? 0);
        $totalPagoPrestamo  = (int)($validated['valor_prestamo']  ?? 0);

        // Calcular COSTO BRUTO ESTIMADO de cada contrato para proporcionar el pago.
        // Para planilla: SS(30 días) + admon + seguro — no basta con solo admon.
        // Para afiliación: costo_afiliacion + seguro.
        $totalesPorContrato = [];
        foreach ($validated['contratos'] as $cId) {
            $c = Contrato::where('aliado_id', $aliadoId)
                ->with(['eps', 'arl', 'pension', 'caja', 'tipoModalidad'])
                ->find($cId);
            if (!$c) { $totalesPorContrato[$cId] = 1; continue; }
            // Detectar si es mes de afiliación
            $esMesIng = $c->fecha_ingreso
                && (int)$c->fecha_ingreso->month === $mes
                && (int)$c->fecha_ingreso->year  === $anio;
            $esAfil = $esMesIng && (!($c->tipoModalidad?->esIndependiente() ?? false)
                        || !($c->cobra_planilla_primer_mes ?? false));
            if ($esAfil) {
                $totalesPorContrato[$cId] = (int)($c->costo_afiliacion ?? 0) + (int)($c->seguro ?? 0);
            } else {
                // Planilla: SS con días REALES del contrato (mismo cálculo que la factura real)
                $diasEst = $this->calcularDias($c, $mes, $anio);
                $ssEst   = $this->calcularSS($c, $diasEst);
                $totalSSEst = $ssEst['eps'] + $ssEst['arl'] + $ssEst['afp'] + $ssEst['caja'];
                $totalesPorContrato[$cId] = $totalSSEst
                    + (int)($c->administracion ?? 0)
                    + (int)($c->admon_asesor  ?? 0)
                    + (int)($c->seguro        ?? 0);
            }
            // Mínimo 1 para evitar peso cero en contratos sin costo
            if ($totalesPorContrato[$cId] <= 0) $totalesPorContrato[$cId] = 1;
        }
        $granTotal = array_sum($totalesPorContrato) ?: 1; // evitar división por 0

        // ─── numero_factura compartido para el lote masivo ─────────────────
        // En modo masivo todos los contratos comparten el MISMO número de recibo.
        // En modo individual se genera uno por llamada, pero aquí pre-calculamos uno solo.
        $esMasivo = count($validated['contratos']) > 1;
        $batchNumeroFactura = Factura::siguienteNumero($aliadoId); // único para el lote

        $facturasCreadas  = [];
        $omitidos         = [];  // contratos ya facturados para ese período
        DB::transaction(function () use (
            $validated, $aliadoId, $np, $mes, $anio,
            &$facturasCreadas, &$omitidos, &$nPlanosPorRS,
            $totalPagoConsig, $totalPagoEfectivo, $totalPagoPrestamo,
            $consignacionesData, $totalesPorContrato, $granTotal, $batchNumeroFactura
        ) {
            foreach ($validated['contratos'] as $contratoId) {
                $contrato = Contrato::where('aliado_id', $aliadoId)
                    ->with(['eps','arl','pension','caja','asesor','tipoModalidad'])
                    ->findOrFail($contratoId);


                // ─── Validación anti-duplicado ─────────────────────────────
                // No se permite facturar el mismo cedula+mes+año, sin importar
                // si fue por empresa o de forma individual.
                $yaExiste = Factura::where('aliado_id', $aliadoId)
                    ->where('cedula', $contrato->cedula)
                    ->where('mes', $mes)
                    ->where('anio', $anio)
                    ->whereNotIn('estado', ['anulada'])
                    ->exists();

                if ($yaExiste) {
                    $omitidos[] = [
                        'cedula'  => $contrato->cedula,
                        'nombre'  => $contrato->cliente?->primer_nombre . ' ' . $contrato->cliente?->primer_apellido,
                        'motivo'  => 'Ya existe una factura para ' . $mes . '/' . $anio,
                    ];
                    continue; // saltar este contrato
                }

                $esIndependiente = $contrato->tipoModalidad?->esIndependiente() ?? false;
                $tipoForzado     = $validated['tipo'];

                if ($contrato->fecha_ingreso) {
                    $mesIngreso  = (int)$contrato->fecha_ingreso->month;
                    $anioIngreso = (int)$contrato->fecha_ingreso->year;
                    $esMesIngreso = ($mes === $mesIngreso && $anio === $anioIngreso);

                    if ($esMesIngreso) {
                        if (!$esIndependiente) {
                            // Empresa: siempre afiliación en el mes de ingreso
                            $tipoForzado = 'afiliacion';
                        } elseif ($esIndependiente && !($contrato->cobra_planilla_primer_mes ?? false)) {
                            // Independiente normal: solo afiliación el primer mes
                            $tipoForzado = 'afiliacion';
                        }
                        // Independiente con cobra_planilla_primer_mes=true → puede ser planilla
                    }
                }

                // ─── Tipo, días y SS ───────────────────────────────────────
                $esAfiliacion = $tipoForzado === 'afiliacion';
                // dias_cotizados = 0 en afiliación, calculado en planilla
                $diasCotizar  = $esAfiliacion ? 0 : $this->calcularDias($contrato, $mes, $anio);

                // SS = 0 en afiliación (dinero va a distribución interna)
                $calcSS = $esAfiliacion
                    ? ['eps' => 0, 'arl' => 0, 'afp' => 0, 'caja' => 0]
                    : $this->calcularSS($contrato, $diasCotizar);

                // Override manual de SS desde la UI (solo en modo individual — 1 contrato).
                // En modo masivo el modal muestra TOTALES del lote, no valores individuales.
                // Si se aplicaran aquí, cada factura individual recibiría el total de TODOS,
                // multiplicando el SS por la cantidad de personas. ← bug original.
                $esModoIndividual = count($validated['contratos']) === 1;
                if (!$esAfiliacion && $esModoIndividual) {
                    if (isset($validated['v_eps_manual']))  $calcSS['eps']  = intval($validated['v_eps_manual']);
                    if (isset($validated['v_arl_manual']))  $calcSS['arl']  = intval($validated['v_arl_manual']);
                    if (isset($validated['v_afp_manual']))  $calcSS['afp']  = intval($validated['v_afp_manual']);
                    if (isset($validated['v_caja_manual'])) $calcSS['caja'] = intval($validated['v_caja_manual']);
                }

                // Saldo previo del cliente (auto desde BD)
                $saldo = Factura::saldoClienteMesPrevio($aliadoId, $contrato->cedula, $mes, $anio);

                $afiliacion  = $esAfiliacion ? (int)($contrato->costo_afiliacion ?? 0) : 0;
                $seguro      = (int)($contrato->seguro ?? 0);

                // Si es afiliación: no hay admon mensual ni SS (el total = costo_afiliacion + seguro)
                // intval() garantiza que no haya decimales que generen $43.003 en vez de $43.000
                $admon       = $esAfiliacion ? 0 : intval($contrato->administracion ?? 0);
                $adminAsesor = $esAfiliacion ? 0 : intval($contrato->admon_asesor   ?? 0);
                $otrosAdmon  = intval($validated['otros_admon'] ?? 0);

                $totalSS  = $calcSS['eps'] + $calcSS['arl'] + $calcSS['afp'] + $calcSS['caja'];
                $ivaBase  = $admon + $adminAsesor;
                $iva      = 0;

                // IVA solo aplica en planilla (sobre admon)
                if (!$esAfiliacion) {
                    $clienteIva = DB::table('clientes')->where('cedula', $contrato->cedula)->value('iva');
                    if (strtoupper(trim($clienteIva ?? '')) === 'SI') {
                        $cfgIva = \App\Models\ConfiguracionBrynex::porcentajeIva();
                        $iva    = (int) ceil($ivaBase * $cfgIva / 100 / 100) * 100;
                    }
                }

                // total = BRUTO (SS + admon + seguro + IVA + otros).
                // El anticipo (saldo_a_favor) y la deuda previa (saldo_pendiente) se guardan
                // en columnas separadas y el sistema acumulativo de saldo_proximo los maneja.
                $total = $totalSS + $admon + $adminAsesor + $otrosAdmon + $seguro + $afiliacion + $iva;

                // ─── Calcular distribución de afiliación ───────────────────
                $distAdmon = $distAsesor = $distRetiro = $distUtilidad = 0;
                if ($esAfiliacion && $afiliacion > 0) {
                    // Si el frontend envió valores manuales, usarlos
                    $hasManual = isset($validated['dist_asesor']) || isset($validated['dist_retiro'])
                              || isset($validated['dist_encargado']) || isset($validated['dist_admon']);

                    if ($hasManual) {
                        $distAsesor   = (int)($validated['dist_asesor']    ?? 0);
                        $distRetiro   = (int)($validated['dist_retiro']    ?? 0);
                        // dist_encargado se suma al dist_admon (internamente no hay campo separado en la tabla)
                        $distEncargado = (int)($validated['dist_encargado'] ?? 0);
                        $distAdmonRaw  = (int)($validated['dist_admon']     ?? 0);
                        // dist_admon en la tabla = empresa admon puro
                        $distAdmon    = $distAdmonRaw;
                        // Recalcular utilidad = total - todos los demás
                        $distUtilidad = max(0, $afiliacion - $distAsesor - $distRetiro - $distEncargado - $distAdmon);
                    } else {
                        $cfg = \App\Models\ConfiguracionAliado::paraAliado($aliadoId, $contrato->plan_id);
                        if ($cfg) {
                            $dist         = $cfg->calcularDistribucion($afiliacion, $contrato->asesor ?? null);
                            $distAdmon    = $dist['admon'];
                            $distAsesor   = $dist['asesor'];
                            $distRetiro   = $dist['retiro'];
                            $distUtilidad = $dist['utilidad'];
                        }
                    }
                }

                // ─── n_plano compartido por RS en este lote ────────────────
                // Todos los contratos de la misma RS deben tener el mismo n_plano.
                $rsId = $contrato->razon_social_id;
                if ($rsId && !isset($nPlanosPorRS[$rsId])) {
                    $nPlanosPorRS[$rsId] = static::_nPlanoParaRS($aliadoId, $rsId, $mes, $anio);
                }
                $nPlanoFactura = $rsId ? ($nPlanosPorRS[$rsId] ?? null) : null;

                // ─── Distribución proporcional del pago ────────────────────
                // El total ingresado (consig+efectivo+prestamo) se reparte
                // proporcionalmente entre todos los contratos del lote.
                $proporcion = $granTotal > 0
                    ? (($totalesPorContrato[$contratoId] ?? 0) / $granTotal)
                    : (1 / max(1, count($validated['contratos'])));

                $vConsig   = (int) round($totalPagoConsig   * $proporcion);
                $vEfectivo = (int) round($totalPagoEfectivo * $proporcion);
                $vPrestamo = (int) round($totalPagoPrestamo * $proporcion);

                $factura = Factura::create([
                    'aliado_id'        => $aliadoId,
                    'numero_factura'   => $batchNumeroFactura,
                    'tipo'             => $tipoForzado,
                    'cedula'           => $contrato->cedula,
                    'contrato_id'      => $contrato->id,
                    'mes'              => $mes,
                    'anio'             => $anio,
                    'fecha_pago'       => now()->toDateString(),
                    'estado'           => $validated['estado'],
                    'es_prestamo'      => $validated['estado'] === 'prestamo',
                    'forma_pago'       => $validated['forma_pago'],
                    'valor_consignado' => $vConsig,
                    'valor_efectivo'   => $vEfectivo,
                    'valor_prestamo'   => $vPrestamo,
                    'otros'            => (int)($validated['otros']      ?? 0),
                    'otros_admon'      => $otrosAdmon,
                    'mensajeria'       => (int)($validated['mensajeria'] ?? 0),
                    'dias_cotizados'   => $diasCotizar,
                    'v_eps'            => $calcSS['eps'],
                    'v_arl'            => $calcSS['arl'],
                    'v_afp'            => $calcSS['afp'],
                    'v_caja'           => $calcSS['caja'],
                    'total_ss'         => $totalSS,
                    'admon'            => $admon,
                    'admin_asesor'     => $adminAsesor,
                    'seguro'           => $seguro,
                    'afiliacion'       => $afiliacion,
                    'iva'              => $iva,
                    'total'            => max(0, $total),
                    'saldo_a_favor'    => $saldo['a_favor'],
                    'saldo_pendiente'  => $saldo['pendiente'],
                    'dist_admon'       => $distAdmon,
                    'dist_asesor'      => $distAsesor,
                    'dist_retiro'      => $distRetiro,
                    'dist_utilidad'    => $distUtilidad,
                    'np'               => $np,
                    'n_plano'          => $nPlanoFactura,
                    'empresa_id'       => $validated['empresa_id'] ?? null,
                    'razon_social_id'  => $contrato->razon_social_id,
                    'usuario_id'       => Auth::id(),
                    'observacion'      => $validated['observacion'] ?? null,
                ]);

                // ─── Guardar consignaciones bancarias ──────────────────────
                // Las consignaciones representan comprobantes bancarios reales.
                // Se guardan UNA SOLA VEZ (en la primera factura del lote) con
                // el valor real. Las demás facturas del NP solo registran
                // valor_consignado proporcional en su campo, sin fila en consignaciones.
                if (empty($facturasCreadas)) {
                    // Esta es la PRIMERA factura del lote → guardar consignaciones reales
                    foreach ($consignacionesData as $cs) {
                        $valorCs = (int)$cs['valor'];
                        if ($valorCs <= 0) continue;
                        \App\Models\Consignacion::create([
                            'aliado_id'       => $aliadoId,
                            'factura_id'      => $factura->id,
                            'banco_cuenta_id' => (int) $cs['banco_cuenta_id'],
                            'fecha'           => $cs['fecha'] ?? now()->toDateString(),
                            'valor'           => $valorCs,
                            'referencia'      => $cs['referencia'] ?? null,
                            'confirmado'      => false,
                            'usuario_id'      => Auth::id(),
                        ]);
                    }
                }
                // Las facturas 2..N del lote NO crean filas en consignaciones;
                // su valor_consignado proporcional ya quedó en el campo de la factura.

                // ─── Calcular saldo_proximo ────────────────────────────────
                // saldo_proximo = -(anticipo consumido) cuando había anticipo.
                // Con SUM acumulativo en saldoClienteMesPrevio:
                //   Abril +350k + Mayo -350k = 0 para Junio. ✓
                $anticipoAplicado = (int)$factura->saldo_a_favor;
                if ($factura->es_prestamo) {
                    // Préstamo: debe el bruto completo al mes siguiente
                    $saldoProximo = -(int)$factura->total;
                } elseif ($anticipoAplicado > 0) {
                    // Había anticipo: registra cuánto anticipo se consumió
                    $saldoProximo = -$anticipoAplicado;
                } else {
                    // Pago normal: registra superávit (+) o déficit (-) vs bruto
                    $pagadoReal   = (int)$factura->valor_consignado + (int)$factura->valor_efectivo;
                    $saldoProximo = $pagadoReal - (int)$factura->total;
                }
                $factura->update(['saldo_proximo' => $saldoProximo]);

                // Si está pagada o en préstamo, generar plano
                if (in_array($factura->estado, [Factura::ESTADO_PAGADA, Factura::ESTADO_PRESTAMO])) {
                    $factura->load('contrato.eps', 'contrato.arl', 'contrato.pension', 'contrato.caja', 'contrato.razonSocial');
                    Plano::generarDesdeContrato($contrato, $factura);
                }

                $facturasCreadas[] = $factura->id;
            }
        });

        $primera = count($facturasCreadas) === 1 ? $facturasCreadas[0] : null;

        // Si no se creó ninguna (todos eran duplicados) → error
        if (empty($facturasCreadas) && !empty($omitidos)) {
            $nombres = collect($omitidos)->pluck('nombre')->join(', ');
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Ya existe factura para este período: ' . $nombres . '. Anule la factura existente antes de refacturar.',
                'omitidos'=> $omitidos,
            ], 422);
        }

        // Éxito con posibles omitidos parciales
        $msgOmit = !empty($omitidos)
            ? ' | ' . count($omitidos) . ' omitido(s) por duplicado.'
            : '';

        return response()->json([
            'ok'              => true,
            'mensaje'         => count($facturasCreadas) . ' factura(s) generada(s) correctamente.' . $msgOmit,
            'facturas'        => $facturasCreadas,
            'omitidos'        => $omitidos,
            'recibo_url'      => $primera ? route('admin.facturacion.recibo', $primera) : null,
            // IDs de consignaciones creadas (solo las de la primera factura del batch)
            // El JS los usa para subir las imágenes de soporte después de crear la factura.
            'consignacion_ids' => \App\Models\Consignacion::whereIn('factura_id', $facturasCreadas)
                ->orderBy('id')
                ->pluck('id')
                ->values()
                ->all(),
        ]);
    }

    // ─── Registrar abono ─────────────────────────────────────────────
    public function abonar(Request $request, int $facturaId)
    {
        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::where('aliado_id', $aliadoId)->findOrFail($facturaId);

        $validated = $request->validate([
            'valor'            => 'required|numeric|min:1',
            'forma_pago'       => 'required|in:efectivo,consignacion,mixto',
            'valor_efectivo'   => 'nullable|numeric|min:0',
            'valor_consignado' => 'nullable|numeric|min:0',
            'banco_cuenta_id'  => 'nullable|integer',
            'observacion'      => 'nullable|string|max:300',
        ]);

        $abono = DB::transaction(function () use ($factura, $validated) {
            $ab = Abono::create([
                ...$validated,
                'factura_id' => $factura->id,
                'fecha'      => now()->toDateString(),
                'usuario_id' => Auth::id(),
            ]);

            // ¿El total abonado cubre el total?
            $factura->refresh();
            if ($factura->estaCompletamentePagada()) {
                $factura->update(['estado' => Factura::ESTADO_PAGADA]);
                // Generar plano si no existe
                if (!$factura->plano) {
                    $c = $factura->contrato()->with('eps','arl','pension','caja','tipoModalidad')->first();
                    if ($c) Plano::generarDesdeContrato($c, $factura);
                }
            } else {
                $factura->update(['estado' => Factura::ESTADO_ABONO]);
            }

            return $ab;
        });

        return response()->json([
            'ok'             => true,
            'abono_id'       => $abono->id,
            'total_abonado'  => $factura->total_abonado,
            'saldo_restante' => $factura->saldo_restante,
            'estado'         => $factura->estado,
            'recibo_url'     => route('admin.facturacion.recibo-abono', $abono->id),
        ]);
    }

    // ─── Recibo de factura ───────────────────────────────────────────
    public function recibo(int $facturaId)
    {
        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::where('aliado_id', $aliadoId)
            ->with(['contrato.cliente','contrato.eps','contrato.arl',
                    'contrato.pension','contrato.caja','contrato.razonSocial',
                    'razonSocial','usuario','abonos',
                    'consignaciones.bancoCuenta'])   // ← todas las cuentas consignadas
            ->findOrFail($facturaId);

        // Grupo NP: todas las facturas del mismo NP/mes/año
        $grupoNp = null;
        if ($factura->np) {
            $grupoNp = Factura::where('aliado_id', $aliadoId)
                ->where('np', $factura->np)
                ->where('mes',  $factura->mes)
                ->where('anio', $factura->anio)
                ->with(['contrato.cliente','contrato.eps','contrato.arl',
                        'contrato.pension','contrato.caja','contrato.razonSocial',
                        'abonos','consignaciones.bancoCuenta'])
                ->orderBy('id')
                ->get();
        }

        return view('admin.facturacion.recibo',
            compact('factura','grupoNp'));
    }

    // ─── Anular factura (solo admin) ─────────────────────────────────
    public function anular(Request $request, int $facturaId)
    {
        $user = Auth::user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('superadmin'))) {
            return response()->json(['ok' => false, 'message' => 'Sin permisos.'], 403);
        }

        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::where('aliado_id', $aliadoId)
            ->with(['contrato.cliente', 'abonos', 'plano'])
            ->findOrFail($facturaId);

        $motivo = trim($request->input('motivo', ''));
        if (!$motivo) {
            return response()->json(['ok' => false, 'message' => 'Debe indicar el motivo de anulación.'], 422);
        }

        // Si tiene NP, anula todo el grupo
        $facturasAnular = collect([$factura]);
        if ($factura->np && $request->boolean('todo_np', false)) {
            $facturasAnular = Factura::where('aliado_id', $aliadoId)
                ->where('np', $factura->np)
                ->where('mes',  $factura->mes)
                ->where('anio', $factura->anio)
                ->with(['abonos', 'plano'])
                ->get();
        }

        DB::transaction(function () use ($facturasAnular, $motivo, $aliadoId, $user) {
            foreach ($facturasAnular as $f) {
                // Registrar en bitácora ANTES de anular
                Bitacora::registrar(
                    accion: 'deleted',
                    modelo: 'Factura',
                    registroId: $f->id,
                    descripcion: "Factura #{$f->numero_factura} anulada. Motivo: {$motivo}",
                    detalle: [
                        'snapshot' => $f->toArray(),
                        'abonos'   => $f->abonos->toArray(),
                        'plano_id' => $f->plano?->id,
                        'motivo'   => $motivo,
                    ],
                    alidoId: $aliadoId
                );

                // Soft-delete de la factura (guarda motivo y quién anuló)
                $f->motivo_anulacion = $motivo;
                $f->anulado_por      = $user->id;
                $f->saldo_proximo    = 0; // limpiar para no influir en futuros cálculos
                $f->save();
                $f->delete(); // SoftDeletes → establece deleted_at

                // Soft-delete del plano (si existe)
                if ($f->plano) {
                    $f->plano->delete();
                }

                // Las consignaciones se eliminan físicamente (quedan en el snapshot de la bitácora)
                DB::table('consignaciones')->where('factura_id', $f->id)->delete();
                DB::table('abonos')->where('factura_id', $f->id)->delete();
            }
        });

        return response()->json([
            'ok'      => true,
            'mensaje' => $facturasAnular->count() > 1
                ? "{$facturasAnular->count()} facturas del NP {$factura->np} anuladas."
                : "Recibo #{$factura->numero_factura} anulado.",
        ]);
    }

    // ─── Listado de facturas anuladas ────────────────────────────────
    public function anuladas(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $q = Factura::onlyTrashed()
            ->where('aliado_id', $aliadoId)
            ->with(['contrato.cliente', 'razonSocial']);

        // Filtros opcionales
        if ($request->filled('cedula'))  $q->where('cedula', $request->cedula);
        if ($request->filled('mes'))     $q->where('mes',  $request->mes);
        if ($request->filled('anio'))    $q->where('anio', $request->anio);
        if ($request->filled('buscar')) {
            $b = $request->buscar;
            $q->where(fn($sq) => $sq->where('cedula','like',"%$b%")
                ->orWhere('numero_factura','like',"%$b%")
                ->orWhere('motivo_anulacion','like',"%$b%"));
        }

        $facturas = $q->orderByDesc('deleted_at')->paginate(25)->withQueryString();

        return view('admin.facturacion.anuladas', compact('facturas'));
    }

    // ─── Restaurar una factura anulada ───────────────────────────────
    public function restaurar(Request $request, int $facturaId)
    {
        $user = Auth::user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('superadmin'))) {
            return response()->json(['ok' => false, 'message' => 'Sin permisos.'], 403);
        }

        $aliadoId = session('aliado_id_activo');
        $factura  = Factura::onlyTrashed()
            ->where('aliado_id', $aliadoId)
            ->findOrFail($facturaId);

        DB::transaction(function () use ($factura, $aliadoId, $user) {
            $factura->restore();
            $factura->motivo_anulacion = null;
            $factura->anulado_por      = null;
            $factura->save();

            // Restaurar el plano asociado si existe
            Plano::onlyTrashed()->where('factura_id', $factura->id)->restore();

            Bitacora::registrar(
                accion: 'updated',
                modelo: 'Factura',
                registroId: $factura->id,
                descripcion: 'Recibo #'.$factura->numero_factura.' restaurado por '.($user->nombre ?? $user->name).'.',
                detalle: ['restaurado_por' => $user->id],
                alidoId: $aliadoId
            );
        });

        return response()->json(['ok' => true, 'mensaje' => "Recibo #{$factura->numero_factura} restaurado correctamente."]);
    }


    // ─── Recibo de abono ─────────────────────────────────────────────
    public function reciboAbono(int $abonoId)
    {
        $aliadoId = session('aliado_id_activo');
        $abono    = Abono::whereHas('factura', fn($q) => $q->where('aliado_id', $aliadoId))
            ->with(['factura.contrato.cliente','usuario'])
            ->findOrFail($abonoId);

        return view('admin.facturacion.recibo-abono', compact('abono'));
    }

    // ─── API: Saldo previo de cliente ────────────────────────────────
    public function saldoCliente(Request $request, int $cedula)
    {
        $aliadoId = session('aliado_id_activo');
        $mes  = (int) $request->mes;
        $anio = (int) $request->anio;
        return response()->json(
            Factura::saldoClienteMesPrevio($aliadoId, $cedula, $mes, $anio)
        );
    }

    // ─── API: Verificar si mes ya está facturado para un contrato ────
    public function mesPagado(Request $request, int $contratoId)
    {
        $aliadoId = session('aliado_id_activo');
        $contrato = Contrato::where('aliado_id', $aliadoId)->find($contratoId);
        if (!$contrato) {
            return response()->json(['pagado' => false, 'mes' => null, 'anio' => null]);
        }

        $mes  = (int) ($request->mes  ?? now()->month);
        $anio = (int) ($request->anio ?? now()->year);

        // Verificar si ya existe factura pagada o pre-factura para este periodo
        $existe = Factura::where('aliado_id', $aliadoId)
            ->where('contrato_id', $contratoId)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->whereIn('estado', ['pagada', 'pre_factura', 'abono', 'prestamo'])
            ->exists();

        // Si ya está pagado, calcular el siguiente mes disponible
        $mesSiguiente  = $mes;
        $anioSiguiente = $anio;
        if ($existe) {
            $mesSiguiente++;
            if ($mesSiguiente > 12) { $mesSiguiente = 1; $anioSiguiente++; }
        }

        // Calcular saldo del cliente para el nuevo mes
        $saldo = Factura::saldoClienteMesPrevio($aliadoId, $contrato->cedula, $mesSiguiente, $anioSiguiente);

        return response()->json([
            'pagado'        => $existe,
            'mes'           => $existe ? $mesSiguiente  : $mes,
            'anio'          => $existe ? $anioSiguiente : $anio,
            'saldo_a_favor' => $saldo['a_favor']   ?? 0,
            'saldo_pendiente'=> $saldo['pendiente'] ?? 0,
        ]);
    }


    // ─── API: N_PLANO actual de una razón social ─────────────────────
    public function planoActual(Request $request, int $razonSocialId)
    {
        $aliadoId = session('aliado_id_activo');
        $rs = DB::table('razones_sociales')->find($razonSocialId);
        $actual = DB::table('planos')
            ->where('aliado_id', $aliadoId)
            ->max('n_plano') ?? 0;

        return response()->json([
            'n_plano_actual'    => $actual,
            'n_plano_siguiente' => $actual + 1,
            'razon_social'      => $rs?->razon_social ?? '',
        ]);
    }

    // ─── Helpers privados ────────────────────────────────────────────
    /**
     * Calcula el siguiente n_plano para una razón social en un período dado.
     * Siempre retorna 1.
     */
    private static function _nPlanoParaRS(int $aliadoId, ?int $razonSocialId, int $mes, int $anio): int
    {
        // Siempre inicia en 1. El aliado actualiza manualmente a 2, 3...
        // cuando hace el pago ante el operador PILA, para separar lotes enviados.
        return 1;
    }

    private function calcularDias(Contrato $contrato, int $mes, int $anio): int
    {
        if (!$contrato->fecha_ingreso) return 30;
        $fIng        = $contrato->fecha_ingreso;
        $mesIngreso  = (int)$fIng->month;
        $anioIngreso = (int)$fIng->year;

        // ① Mes de ingreso → afiliación, no hay días de planilla
        if ($mesIngreso === $mes && $anioIngreso === $anio) {
            return 0;
        }

        // ② Mes siguiente al ingreso → primera planilla: días activos del mes de ingreso
        $mesAnterior  = $mes === 1 ? 12 : $mes - 1;
        $anioAnterior = $mes === 1 ? $anio - 1 : $anio;
        if ($mesIngreso === $mesAnterior && $anioIngreso === $anioAnterior) {
            return max(1, 30 - $fIng->day + 1);
        }

        // ③ Mes normal
        return 30;
    }

    private function calcularSS(Contrato $contrato, int $dias): array
    {
        $aliadoId = session('aliado_id_activo');
        $ibc      = (int)($contrato->salario ?? 0);
        $nArl     = (int)($contrato->n_arl ?? 1);
        $plan     = $contrato->plan;

        $pctEps = \App\Models\ConfiguracionBrynex::pctSaludDependiente();
        $pctPen = \App\Models\ConfiguracionBrynex::pctPensionDependiente();
        $pctArl = \App\Models\ArlTarifa::porcentajePara($nArl, $aliadoId);
        $pctCaj = \App\Models\ConfiguracionBrynex::pctCajaDependiente();

        $r = fn($v) => (int)(ceil($v / 100) * 100);

        $epsMes  = ($plan?->incluye_eps)     ? $r($ibc * $pctEps / 100) : 0;
        $arlMes  = ($plan?->incluye_arl)     ? $r($ibc * $pctArl / 100) : 0;
        $afpMes  = ($plan?->incluye_pension) ? $r($ibc * $pctPen / 100) : 0;
        $cajaMes = ($plan?->incluye_caja)    ? $r($ibc * $pctCaj / 100) : 0;

        if ($dias < 30) {
            return [
                'eps'  => $r($epsMes  * $dias / 30),
                'arl'  => $r($arlMes  * $dias / 30),
                'afp'  => $r($afpMes  * $dias / 30),
                'caja' => $r($cajaMes * $dias / 30),
            ];
        }

        return ['eps' => $epsMes, 'arl' => $arlMes, 'afp' => $afpMes, 'caja' => $cajaMes];
    }

    // ─── API: Saldos para N contratos (modo masivo empresa) ─────────
    // GET /admin/facturacion/api/saldos-contratos?contratos[]=1&contratos[]=2&mes=5&anio=2026
    public function saldosContratos(Request $request)
    {
        $aliadoId  = session('aliado_id_activo');
        $contratoIds = array_filter((array)$request->contratos);
        $mes  = (int)($request->mes  ?? now()->month);
        $anio = (int)($request->anio ?? now()->year);

        $totalAFavor   = 0;
        $totalPendiente = 0;
        $porContrato   = [];

        foreach ($contratoIds as $cId) {
            $contrato = Contrato::where('aliado_id', $aliadoId)
                ->with('cliente')
                ->find($cId);
            if (!$contrato) continue;

            $saldo = Factura::saldoClienteMesPrevio($aliadoId, $contrato->cedula, $mes, $anio);
            $aFavor    = (int)($saldo['a_favor']   ?? 0);
            $pendiente = (int)($saldo['pendiente'] ?? 0);

            $cli  = $contrato->cliente;
            $nom  = trim(($cli?->primer_nombre ?? '').' '.($cli?->primer_apellido ?? ''));

            $porContrato[$cId] = [
                'cedula'    => $contrato->cedula,
                'nombre'    => $nom ?: 'CC '.$contrato->cedula,
                'a_favor'   => $aFavor,
                'pendiente' => $pendiente,
            ];
            $totalAFavor    += $aFavor;
            $totalPendiente += $pendiente;
        }

        return response()->json([
            'total_a_favor'   => $totalAFavor,
            'total_pendiente' => $totalPendiente,
            'por_contrato'    => $porContrato,
        ]);
    }

    // ─── Historial de pagos del cliente ─────────────────────────────
    public function historial(Request $request, int $cedula)
    {
        $aliadoId = session('aliado_id_activo');

        // Buscar el cliente directamente por cédula (no depende de contratos)
        $cliente = \App\Models\Cliente::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->first();

        // Si no existe el cliente en este aliado, abortar
        abort_if(!$cliente, 404, 'Cliente no encontrado.');

        // Traemos el contrato de referencia (solo para contexto visual del header)
        $contrato = Contrato::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->whereIn('estado', ['vigente', 'activo'])
            ->with(['cliente', 'razonSocial'])
            ->orderByDesc('created_at')
            ->first();

        // Si no hay contrato vigente, buscar cualquiera
        if (!$contrato) {
            $contrato = Contrato::where('aliado_id', $aliadoId)
                ->where('cedula', $cedula)
                ->with(['cliente', 'razonSocial'])
                ->orderByDesc('created_at')
                ->first();
        }

        // Filtros opcionales
        $filtroAnio = $request->integer('anio', 0);
        $filtroRs   = $request->get('razon_social_id', '');

        // Query principal
        $query = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->with(['razonSocial', 'empresa', 'plano', 'usuario'])
            ->orderByDesc('anio')
            ->orderByDesc('mes');

        if ($filtroAnio > 0) {
            $query->where('anio', $filtroAnio);
        }
        if ($filtroRs !== '') {
            $query->where('razon_social_id', $filtroRs);
        }

        // Sin filtros activos: últimas 20
        $sinFiltros = !$filtroAnio && $filtroRs === '';
        if ($sinFiltros) {
            $facturas = $query->limit(20)->get();
        } else {
            $facturas = $query->get();
        }

        // Agrupar: [razon_social → [anio → [facturas]]]
        $agrupado = [];
        foreach ($facturas as $f) {
            $rs   = $f->razonSocial?->razon_social ?? 'Sin razón social';
            $anio = $f->anio;
            $agrupado[$rs][$anio][] = $f;
        }

        // Para filtros: años y razones sociales disponibles del cliente
        $aniosDisp = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->select('anio')->distinct()->orderByDesc('anio')
            ->pluck('anio');

        $rsSocDisp = Factura::where('aliado_id', $aliadoId)
            ->where('cedula', $cedula)
            ->with('razonSocial')
            ->select('razon_social_id')->distinct()
            ->get()
            ->map(fn($f) => [
                'id'    => $f->razon_social_id,
                'label' => $f->razonSocial?->razon_social ?? 'Sin razón social',
            ])->unique('id');

        $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                       'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        return view('admin.facturacion.historial', compact(
            'cliente', 'contrato', 'cedula', 'agrupado',
            'filtroAnio', 'filtroRs', 'sinFiltros',
            'aniosDisp', 'rsSocDisp', 'meses'
        ));
    }


    // ─── Imagen de consignación ────────────────────────────────────────
    /**
     * POST /admin/facturacion/consignacion/{id}/imagen
     * Sube la imagen/PDF de soporte de una consignación y guarda la ruta.
     */
    public function subirImagenConsignacion(Request $request, int $id)
    {
        $aliadoId = session('aliado_id_activo');

        $consig = \App\Models\Consignacion::where('id', $id)
            ->where('aliado_id', $aliadoId)
            ->firstOrFail();

        $request->validate([
            'imagen' => 'required|file|mimes:jpg,jpeg,png,pdf,webp|max:8192', // 8 MB
        ]);

        // Eliminar imagen anterior si existe
        if ($consig->imagen_path && \Storage::disk('public')->exists($consig->imagen_path)) {
            \Storage::disk('public')->delete($consig->imagen_path);
        }

        $file = $request->file('imagen');
        $ext  = $file->getClientOriginalExtension();
        $path = $file->storeAs(
            "consignaciones/{$aliadoId}/{$consig->factura_id}",
            "{$id}.{$ext}",
            'public'
        );

        $consig->update(['imagen_path' => $path]);

        return response()->json([
            'ok'  => true,
            'url' => \Storage::url($path),
        ]);
    }

    /**
     * GET /admin/facturacion/consignacion/{id}/imagen
     * Redirige a la URL pública de la imagen de soporte.
     */
    public function verImagenConsignacion(int $id)
    {
        $aliadoId = session('aliado_id_activo');

        $consig = \App\Models\Consignacion::where('id', $id)
            ->where('aliado_id', $aliadoId)
            ->firstOrFail();

        if (!$consig->imagen_path || !\Storage::disk('public')->exists($consig->imagen_path)) {
            abort(404, 'Imagen no encontrada.');
        }

        return redirect(\Storage::url($consig->imagen_path));
    }

    // ─── Facturar Otro Ingreso (Trámite) ─────────────────────────────
    /**
     * POST /admin/facturacion/otro-ingreso
     * Crea una factura de tipo 'otro_ingreso' (trámites, servicios adicionales).
     * NO genera plano PILA. IVA aplica si cliente OR empresa tiene iva='SI'.
     * Asesor se toma del campo asesor del cliente o de la empresa.
     */
    public function facturarOtroIngreso(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'cedula'              => 'required|integer',
            'descripcion_tramite' => 'required|string|max:300',
            'mes'                 => 'required|integer|min:1|max:12',
            'anio'                => 'required|integer|min:2000|max:2100',
            'valor_admon'         => 'required|numeric|min:0',
            'valor_asesor'        => 'nullable|numeric|min:0',
            'forma_pago'          => 'required|in:efectivo,consignacion,mixto,prestamo',
            'estado'              => 'required|in:pre_factura,pagada,prestamo',
            'valor_efectivo'      => 'nullable|numeric|min:0',
            'valor_prestamo'      => 'nullable|numeric|min:0',
            'consignaciones'                   => 'nullable|array',
            'consignaciones.*.banco_cuenta_id' => 'required_with:consignaciones|integer',
            'consignaciones.*.valor'           => 'required_with:consignaciones|numeric|min:0',
            'consignaciones.*.fecha'           => 'nullable|date',
            'consignaciones.*.referencia'      => 'nullable|string|max:100',
            'empresa_id'          => 'nullable|integer',
            'observacion'         => 'nullable|string|max:500',
        ]);

        $cedula  = (int)$validated['cedula'];
        $mes     = (int)$validated['mes'];
        $anio    = (int)$validated['anio'];

        // ── IVA: aplica si cliente OR empresa tienen iva='SI' ─────────
        $clienteIva  = DB::table('clientes')->where('cedula', $cedula)->value('iva');
        $empresaId   = $validated['empresa_id'] ?? null;
        $empresaIva  = $empresaId
            ? DB::table('empresas')->where('id', $empresaId)->value('iva')
            : null;

        $aplicaIva = strtoupper(trim($clienteIva ?? '')) === 'SI'
                  || strtoupper(trim($empresaIva ?? '')) === 'SI';

        $valorAdmon  = (int)($validated['valor_admon']  ?? 0);
        $valorAsesor = (int)($validated['valor_asesor'] ?? 0);
        $ivaBase     = $valorAdmon + $valorAsesor;

        $iva = 0;
        if ($aplicaIva && $ivaBase > 0) {
            $cfgIva = \App\Models\ConfiguracionBrynex::porcentajeIva();
            $iva    = (int) ceil($ivaBase * $cfgIva / 100 / 100) * 100;
        }

        $total = $valorAdmon + $valorAsesor + $iva;

        // ── Pagos ──────────────────────────────────────────────────────
        $consignacionesData = $validated['consignaciones'] ?? [];
        $totalConsig  = array_sum(array_column($consignacionesData, 'valor'));
        $totalEfectivo = (int)($validated['valor_efectivo'] ?? 0);
        $totalPrestamo = (int)($validated['valor_prestamo'] ?? 0);

        // ── Saldo previo del cliente ────────────────────────────────────
        $saldo = Factura::saldoClienteMesPrevio($aliadoId, $cedula, $mes, $anio);

        $factura = DB::transaction(function () use (
            $aliadoId, $cedula, $mes, $anio, $validated,
            $valorAdmon, $valorAsesor, $iva, $total,
            $totalConsig, $totalEfectivo, $totalPrestamo,
            $consignacionesData, $saldo, $empresaId
        ) {
            $numeroFactura = Factura::siguienteNumero($aliadoId);

            $factura = Factura::create([
                'aliado_id'           => $aliadoId,
                'numero_factura'      => $numeroFactura,
                'tipo'                => Factura::TIPO_OTRO_INGRESO,
                'cedula'              => $cedula,
                'contrato_id'         => null,
                'empresa_id'          => $empresaId,
                'mes'                 => $mes,
                'anio'                => $anio,
                'fecha_pago'          => now()->toDateString(),
                'estado'              => $validated['estado'],
                'es_prestamo'         => $validated['estado'] === 'prestamo',
                'forma_pago'          => $validated['forma_pago'],
                'valor_consignado'    => (int)$totalConsig,
                'valor_efectivo'      => $totalEfectivo,
                'valor_prestamo'      => $totalPrestamo,
                // SS = 0 (no es planilla)
                'dias_cotizados'      => 0,
                'v_eps'               => 0,
                'v_arl'               => 0,
                'v_afp'               => 0,
                'v_caja'              => 0,
                'total_ss'            => 0,
                // Valores administrativos
                'admon'               => $valorAdmon,
                'admin_asesor'        => 0,     // planilla: no aplica
                'admon_asesor_oi'     => $valorAsesor,
                'iva'                 => $iva,
                'total'               => max(0, $total),
                'seguro'              => 0,
                'afiliacion'          => 0,
                'mensajeria'          => 0,
                'otros'               => 0,
                // Saldos
                'saldo_a_favor'       => $saldo['a_favor'],
                'saldo_pendiente'     => $saldo['pendiente'],
                // Descripción del trámite
                'descripcion_tramite' => $validated['descripcion_tramite'],
                'observacion'         => $validated['observacion'] ?? null,
                'usuario_id'          => Auth::id(),
            ]);

            // ── saldo_proximo ──────────────────────────────────────────
            if ($factura->es_prestamo) {
                $saldoProximo = -(int)$factura->total;
            } else {
                $pagadoReal   = (int)$factura->valor_consignado + (int)$factura->valor_efectivo;
                $saldoProximo = $pagadoReal - (int)$factura->total;
            }
            $factura->update(['saldo_proximo' => $saldoProximo]);

            // ── Consignaciones ─────────────────────────────────────────
            foreach ($consignacionesData as $cs) {
                $valorCs = (int)$cs['valor'];
                if ($valorCs <= 0) continue;
                \App\Models\Consignacion::create([
                    'aliado_id'       => $aliadoId,
                    'factura_id'      => $factura->id,
                    'banco_cuenta_id' => (int)$cs['banco_cuenta_id'],
                    'fecha'           => $cs['fecha'] ?? now()->toDateString(),
                    'valor'           => $valorCs,
                    'referencia'      => $cs['referencia'] ?? null,
                    'confirmado'      => false,
                    'usuario_id'      => Auth::id(),
                ]);
            }

            // ── NO se genera plano PILA ────────────────────────────────
            return $factura;
        });

        return response()->json([
            'ok'              => true,
            'mensaje'         => 'Otro ingreso registrado correctamente. Recibo #' . $factura->numero_factura,
            'factura_id'      => $factura->id,
            'recibo_url'      => route('admin.facturacion.recibo', $factura->id),
            'consignacion_ids' => \App\Models\Consignacion::where('factura_id', $factura->id)
                ->orderBy('id')->pluck('id')->values()->all(),
        ]);
    }
    // ─── Historial de facturación de una empresa ────────────────────
    public function historialEmpresa(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        $facturas = Factura::where('aliado_id', $aliadoId)
            ->where('empresa_id', $empresaId)
            ->with(['usuario', 'consignaciones.bancoCuenta'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->get();

        // Agrupar por numero_factura (cada NP puede tener varios trabajadores con el mismo número)
        $grupos = $facturas->groupBy('numero_factura')->map(function ($grupo) {
            $primera = $grupo->first();
            return (object)[
                'id'                => $primera->id,
                'np'                => $primera->np,
                'tipo'              => $primera->tipo,
                'numero_factura'    => $primera->numero_factura,
                'fecha_pago'        => $primera->fecha_pago,
                'mes'               => $primera->mes,
                'anio'              => $primera->anio,
                'estado'            => $primera->estado,
                'descripcion_tramite' => $primera->descripcion_tramite,
                'total'             => $grupo->sum(fn($f) => (int)$f->total),
                'cantidad'          => $grupo->count(),
                'usuario'           => $primera->usuario,
            ];
        })->values();

        return view('admin.facturacion.historial_empresa', compact('empresa', 'grupos', 'facturas'));
    }

    // ─── Editar empresa ──────────────────────────────────────────────
    public function editEmpresa(int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);
        $asesores = \App\Models\Asesor::where('aliado_id', $aliadoId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('admin.facturacion.empresa_edit', compact('empresa', 'asesores'));
    }

    // ─── Actualizar empresa ──────────────────────────────────────────
    public function updateEmpresa(Request $request, int $empresaId)
    {
        $aliadoId = session('aliado_id_activo');
        $empresa  = Empresa::where('aliado_id', $aliadoId)->findOrFail($empresaId);

        $validated = $request->validate([
            'empresa'    => 'required|string|max:255',
            'nit'        => 'nullable|numeric',
            'contacto'   => 'nullable|string|max:255',
            'telefono'   => 'nullable|string|max:50',
            'celular'    => 'nullable|string|max:50',
            'correo'     => 'nullable|email|max:150',
            'direccion'  => 'nullable|string|max:255',
            'iva'        => 'nullable|string|max:20',
            'asesor_id'  => 'nullable|exists:asesores,id',
            'observacion'=> 'nullable|string|max:500',
        ]);

        $empresa->update($validated);

        return redirect()
            ->route('admin.facturacion.empresa', [
                'id'  => $empresaId,
                'mes' => now()->month,
                'anio'=> now()->year,
            ])
            ->with('success', 'Empresa actualizada correctamente.');
    }

    // ─── Cuenta de Cobro ─────────────────────────────────────────────
    public function cuentaCobroPreview(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $aliado   = \App\Models\Aliado::find($aliadoId);

        $contratoIds = $request->input('contratos', []);
        $mes         = (int) $request->input('mes',  now()->month);
        $anio        = (int) $request->input('anio', now()->year);
        $empresaId   = (int) $request->input('empresa_id');
        $tipo        = $request->input('tipo', 'simple'); // simple | detallada

        $empresa = Empresa::where('aliado_id', $aliadoId)->find($empresaId);

        // Cuentas bancarias marcadas para cobro
        $cuentasCobro = BancoCuenta::paraCobro($aliadoId);

        // Contratos seleccionados con sus relaciones
        $contratos = Contrato::where('aliado_id', $aliadoId)
            ->whereIn('id', $contratoIds)
            ->with(['cliente', 'tipoModalidad', 'razonSocial', 'eps', 'arl', 'pension', 'caja'])
            ->get();

        // Facturas existentes para el período
        $facturasExistentes = Factura::where('aliado_id', $aliadoId)
            ->periodo($mes, $anio)
            ->whereIn('tipo', ['planilla', 'afiliacion'])
            ->whereIn('cedula', $contratos->pluck('cedula'))
            ->get()
            ->keyBy('cedula');

        $r100 = fn($v) => (int)(ceil(($v ?? 0) / 100) * 100);

        $items = $contratos->map(function ($c) use ($mes, $anio, $facturasExistentes, $r100, $aliadoId) {
            $fact   = $facturasExistentes->get($c->cedula);
            $nombre = $c->cliente?->nombre_completo
                      ?? trim(($c->cliente?->primer_nombre ?? '') . ' ' .
                              ($c->cliente?->segundo_nombre ?? '') . ' ' .
                              ($c->cliente?->primer_apellido ?? '') . ' ' .
                              ($c->cliente?->segundo_apellido ?? ''))
                      ?: '—';

            $dias = $this->calcularDias($c, $mes, $anio);

            // ¿Es afiliación?
            $esAfil = $c->fecha_ingreso
                && (int)$c->fecha_ingreso->month === $mes
                && (int)$c->fecha_ingreso->year  === $anio;
            if ($fact) $esAfil = $fact->tipo === 'afiliacion';

            if ($fact) {
                $vEps  = $r100($fact->v_eps);
                $vArl  = $r100($fact->v_arl);
                $vAFP  = $r100($fact->v_afp);
                $vCaja = $r100($fact->v_caja);
                $vAdm  = (int)($fact->admon + $fact->admin_asesor);
                $vIva  = $r100($fact->iva);
                $vTot  = (int)$fact->total;
                $estado = $fact->estado;
            } elseif ($esAfil) {
                $vEps=$vArl=$vAFP=$vCaja=$vAdm=$vIva=0;
                $vTot = (int)(($c->costo_afiliacion ?? 0) + ($c->seguro ?? 0));
                $estado = 'sin_factura';
            } else {
                $cotiz = $c->calcularCotizacion($dias);
                $vEps  = $r100($cotiz['eps']  ?? 0);
                $vArl  = $r100($cotiz['arl']  ?? 0);
                $vAFP  = $r100($cotiz['pen']  ?? 0);
                $vCaja = $r100($cotiz['caja'] ?? 0);
                $vAdm  = (int)(($c->administracion ?? 0) + ($c->admon_asesor ?? 0));
                $vIva  = $r100($cotiz['iva']  ?? 0);
                $vTot  = $vEps + $vArl + $vAFP + $vCaja + $vAdm + $vIva;
                $estado = 'sin_factura';
            }

            $saldo = Factura::saldoClienteMesPrevio($aliadoId, $c->cedula, $mes, $anio);

            return (object)[
                'cedula'         => $c->cedula,
                'nombre'         => $nombre,
                'fecha_ingreso'  => $c->fecha_ingreso,
                'razon_social'   => $c->razonSocial?->razon_social ?? '—',
                'eps_nombre'     => $c->eps?->nombre ?? '—',
                'arl_nombre'     => $c->arl?->nombre ?? '—',
                'n_arl'          => $c->n_arl ?? 1,
                'afp_nombre'     => $c->pension?->nombre ?? '—',
                'caja_nombre'    => $c->caja?->nombre ?? '—',
                'es_afil'        => $esAfil,
                'dias'           => $dias,
                'v_eps'          => $vEps,
                'v_arl'          => $vArl,
                'v_afp'          => $vAFP,
                'v_caja'         => $vCaja,
                'v_admon'        => $vAdm,
                'v_iva'          => $vIva,
                'v_total'        => $vTot,
                'estado'         => $estado,
                'saldo_favor'    => $saldo['a_favor'],
                'saldo_pendiente'=> $saldo['pendiente'],
            ];
        });

        $totalGeneral   = $items->sum('v_total');
        $totalFavor     = $items->sum('saldo_favor');
        $totalPendiente = $items->sum('saldo_pendiente');

        $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        $vista = $tipo === 'detallada'
            ? 'admin.facturacion.cuenta_cobro_detallada'
            : 'admin.facturacion.cuenta_cobro_simple';

        return view($vista, compact(
            'aliado','empresa','items','cuentasCobro',
            'mes','anio','meses','totalGeneral','totalFavor','totalPendiente'
        ));
    }
}



