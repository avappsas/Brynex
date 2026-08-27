<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Plano, RazonSocial, TipoModalidad, BancoCuenta, Gasto, OperadorPlanilla, User};
use App\Services\ExcelPlanoNIService;
use App\Services\ExcelAsopagosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PlanoPagoController extends Controller
{
    // ── 1. Vista principal con filtros ─────────────────────────────────
    public function index(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        // ── Filtros ─────────────────────────────────────────────────────
        $anio           = (int) $request->input('anio',   now()->year);
        $mes            = (int) $request->input('mes',    now()->month);
        $razonSocialId  = $request->input('razon_social_id');
        $nPlanoFiltro   = $request->input('n_plano');
        $modalidadesIds = $request->input('tipos_modalidad', []);
        $estadoPago     = $request->input('estado_pago', 'todas'); // 'todas' | 'pendientes' | 'pagadas'

        // ── Logica de mes vencido ────────────────────────────────────────
        // El filtro MES muestra el mes de PAGO (mes seleccionado por el usuario).
        // Internamente:
        //   - Con `paga_mes_actual`                   → mes_plano = mes_pago (mes actual)
        //   - Todos los demas (dependientes, etc.)    → mes_plano = mes_pago - 1 (mes vencido)
        $mesVencido  = $mes > 1 ? $mes - 1 : 12;
        $anioVencido = $mes > 1 ? $anio    : $anio - 1;

        // Closure reutilizable para el WHERE de periodo mixto
        $wherePeriodo = function ($q) use ($mes, $anio, $mesVencido, $anioVencido, $nPlanoFiltro) {
            if ((int)$nPlanoFiltro === 100) {
                // Para el plano 100 (IR) permitimos tanto el mes de pago como el mes vencido
                $q->whereIn('p.mes_plano', [$mes, $mesVencido])
                  ->whereIn('p.anio_plano', [$anio, $anioVencido]);
            } else {
                Plano::filtrarPeriodoDePago($q, $mes, $anio);
            }
        };

        // Regla IR (n_plano=100): antes del día 26 no se muestran como pendientes en el select.
        $diaHoy = (int) now()->day;

        // ── Planos PENDIENTES por RS para el SELECT (sin numero_planilla) ──────────
        // Excluye n_plano=100 (Ingreso-Retiro) si hoy < día 26 del mes.
        $cantPorRs = DB::table('planos AS p')
            ->leftJoin('facturas AS f', function ($join) use ($aliadoId) {
                $join->on('f.id', '=', 'p.factura_id')
                     ->where('f.aliado_id', $aliadoId);  // scope al aliado
            })
            ->where('p.aliado_id', $aliadoId)
            ->whereNull('p.deleted_at')          // plano no soft-deleted
            ->whereNull('f.deleted_at')          // factura no anulada
            ->whereIn('p.tipo_reg', ['planilla', 'retiro'])
            ->where('p.num_dias', '>', 0)
            ->where('p.n_plano', '>', 0)         // excluye n_plano=0 (retiros IR legacy)
            ->where(function ($q) {
                $q->whereNull('p.numero_planilla')
                  ->orWhere('p.numero_planilla', '');
            })
            ->when($diaHoy < 26, fn($q) => $q->where('p.n_plano', '<>', 100))
            ->where($wherePeriodo)
            ->groupBy('p.razon_social_id')
            ->select('p.razon_social_id', DB::raw('COUNT(*) AS cant'))
            ->pluck('cant', 'razon_social_id');

        $razonesSociales = RazonSocial::where('aliado_id', $aliadoId)
            ->whereRaw("LOWER(ISNULL(estado,'')) IN ('activo','activa','1','si','yes')") // solo activas
            ->orderBy('razon_social')
            ->get(['id', 'razon_social', 'n_plano', 'mes_pagos', 'anio_pagos', 'estado']);

        // Todas las modalidades activas (se usan cuando no hay RS seleccionada)
        $tiposModalidad = TipoModalidad::where('activo', true)
            ->where('id', '<>', -100)
            ->orderBy('orden')
            ->get();

        // ── N_PLANO actual de la RS seleccionada ─────────────────────────
        $nPlanoActual   = null;
        $rsSeleccionada = null;
        if ($razonSocialId) {
            $rsSeleccionada = RazonSocial::find($razonSocialId);
            $nPlanoActual   = $rsSeleccionada?->n_plano;
            // $request->has() = true si el param vino en el form (aunque vacío = "Todos")
            // $request->has() = false si es primera carga (URL sin n_plano) → usar plano actual
            if (!$request->has('n_plano')) {
                $nPlanoFiltro = $nPlanoActual;
            }
        }

        // ── Consulta principal ───────────────────────────────────────────
        // No se muestran planos hasta que el usuario seleccione una Razon Social.
        $planos            = collect();
        $modalidadesDispon = collect();

        if ($razonSocialId) {
            $query = DB::table('planos AS p')
                ->leftJoin('facturas AS f', 'f.id', '=', 'p.factura_id')
                ->leftJoin('contratos AS c', 'c.id', '=', 'p.contrato_id')
                // Filtrar clientes por aliado_id para evitar filas duplicadas
                // cuando el mismo cliente existe en múltiples aliados
                ->leftJoin('clientes AS cl', function ($join) use ($aliadoId) {
                    $join->on('cl.cedula', '=', 'p.no_identifi')
                         ->where('cl.aliado_id', '=', $aliadoId);
                })
                ->leftJoin('empresas AS em', 'em.id', '=', 'cl.cod_empresa')
                ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 'p.razon_social_id')
                ->leftJoin('tipo_modalidad AS tm', 'tm.id', '=', 'p.tipo_modalidad_id')
                // Operador asignado al cliente (para RS independientes)
                ->leftJoin('operadores_planilla AS op_cl', 'op_cl.id', '=', 'cl.operador_planilla_id')
                // Última liquidación por API de ESTE registro puntual (independientes:
                // cada fila se liquida por su cuenta, ver PlanillaApiController::liquidarIndependiente)
                ->leftJoin(DB::raw('(SELECT plano_id, MAX(id) AS max_id FROM operador_planillas_api WHERE deleted_at IS NULL AND plano_id IS NOT NULL GROUP BY plano_id) AS opa_max'), 'opa_max.plano_id', '=', 'p.id')
                ->leftJoin('operador_planillas_api AS opa', 'opa.id', '=', 'opa_max.max_id')
                ->where('p.aliado_id', $aliadoId)
                ->whereNull('p.deleted_at')
                ->where(function ($q) use ($nPlanoFiltro) {
                    if ((int)$nPlanoFiltro === 100) {
                        // En el plano 100 (IR) se muestran registros con n_plano=100 O cualquier novedad (retiro/afiliacion)
                        $q->where(function ($sub) {
                            $sub->where('p.n_plano', 100)
                                ->orWhereIn('p.tipo_reg', ['retiro', 'afiliacion']);
                        });
                    } else {
                        $q->whereIn('p.tipo_reg', ['planilla', 'retiro'])
                          ->whereRaw('ISNULL(p.num_dias, 0) > 0');
                    }
                })
                ->where('p.razon_social_id', $razonSocialId)
                ->where($wherePeriodo)
                ->select([
                    'p.id',
                    'p.tipo_reg',
                    'p.tipo_doc',
                    'p.no_identifi',
                    'p.primer_nombre', 'p.segundo_nombre',
                    'p.primer_ape', 'p.segundo_ape',
                    'p.n_plano',
                    'p.numero_planilla',
                    'p.mes_plano', 'p.anio_plano',
                    'p.num_dias',
                    'p.fecha_ing', 'p.fecha_ret',
                    'p.cod_eps',
                    // Nombre EPS: preferir el guardado en plano, fallback subconsulta al catálogo
                    // (subconsulta TOP 1 evita multiplicar filas si hay códigos duplicados en eps)
                    DB::raw("COALESCE(NULLIF(p.nombre_eps, ''), (SELECT TOP 1 e.nombre FROM eps e WHERE CAST(e.nit AS VARCHAR(20)) = p.cod_eps)) AS nombre_eps"),
                    'p.cod_afp',
                    // Nombre AFP/Pensión
                    DB::raw("COALESCE(NULLIF(p.nombre_afp, ''), (SELECT TOP 1 pn.razon_social FROM pensiones pn WHERE CAST(pn.nit AS VARCHAR(20)) = p.cod_afp)) AS nombre_afp"),
                    'p.cod_arl',
                    // Nombre ARL
                    DB::raw("COALESCE(NULLIF(p.nombre_arl, ''), (SELECT TOP 1 a.nombre_arl FROM arls a WHERE CAST(a.nit AS VARCHAR(20)) = p.cod_arl)) AS nombre_arl"),
                    'p.cod_caja',
                    // Nombre Caja
                    DB::raw("COALESCE(NULLIF(p.nombre_caja, ''), (SELECT TOP 1 cj.nombre FROM cajas cj WHERE CAST(cj.nit AS VARCHAR(20)) = p.cod_caja)) AS nombre_caja"),
                    'p.nivel_riesgo',
                    'p.razon_social_id',
                    'p.razon_social',
                    'p.tipo_modalidad_id',
                    'p.tipo_p',
                    // La tabla lo necesita para marcar quién cotiza el mes en curso:
                    // en una misma planilla conviven los dos períodos.
                    'p.paga_mes_actual',
                    'p.updated_at',
                    // Desde factura (snapshot)
                    'f.id AS factura_id',
                    'f.numero_factura AS numero_envio',
                    'f.v_eps', 'f.v_afp', 'f.v_arl', 'f.v_caja',
                    'f.total_ss',
                    'f.admon',
                    'f.mes AS mes_factura',
                    // Desde contrato
                    'c.id AS contrato_id',
                    'c.envio_planilla',
                    // Desde cliente
                    'cl.id AS cliente_id',
                    'cl.fecha_nacimiento',
                    'cl.operador_planilla_id',
                    // Operador del cliente
                    'op_cl.id   AS operador_cliente_id',
                    'op_cl.nombre AS operador_cliente_nombre',
                    // Última liquidación por API de este registro puntual (independientes)
                    'opa.operador_planilla_id AS operador_liquidado_id',
                    'opa.numero_planilla      AS numero_planilla_api',
                    'opa.url_pago             AS url_pago_api',
                    'opa.estado               AS estado_api',
                    'opa.mensaje_error        AS mensaje_error_api',
                    // Empresa del cliente
                    'em.empresa AS nombre_empresa',
                    // Tipo modalidad
                    'tm.tipo_modalidad AS tipo_modal_nombre',
                ]);

            if ($nPlanoFiltro) {
                $query->where('p.n_plano', $nPlanoFiltro);
            }

            if (!empty($modalidadesIds)) {
                $query->whereIn('p.tipo_modalidad_id', $modalidadesIds);
            }

            // Filtro por estado de pago
            if ($estadoPago === 'pendientes') {
                $query->where(function ($q) {
                    $q->whereNull('p.numero_planilla')
                      ->orWhere('p.numero_planilla', '');
                });
            } elseif ($estadoPago === 'pagadas') {
                $query->whereNotNull('p.numero_planilla')
                      ->where('p.numero_planilla', '!=', '');
            }

            $planos = $query->orderBy('rs.razon_social')->orderBy('p.primer_ape')->get();

            // ── Modalidades disponibles en periodo+RS ──────────────────────
            $modalidadesDisponIds = DB::table('planos AS p')
                ->join('tipo_modalidad AS tm', 'tm.id', '=', 'p.tipo_modalidad_id')
                ->where('p.aliado_id', $aliadoId)
                ->whereNull('p.deleted_at')
                ->whereIn('p.tipo_reg', ['planilla', 'retiro'])
                ->where('p.num_dias', '>', 0)
                ->where('p.razon_social_id', $razonSocialId)
                ->where($wherePeriodo)
                ->distinct()
                ->pluck('p.tipo_modalidad_id')
                ->toArray();

            $modalidadesDispon = $tiposModalidad->whereIn('id', $modalidadesDisponIds)->values();
        }

        // ── Calcular edad y nombre completo ─────────────────────────────
        $hoy = Carbon::today();
        $planos = $planos->map(function ($row) use ($hoy) {
            $row->nombre_completo = trim(
                $row->primer_nombre . ' ' . $row->segundo_nombre . ' ' .
                $row->primer_ape   . ' ' . $row->segundo_ape
            );
            $row->edad = $row->fecha_nacimiento
                ? $hoy->diffInYears(sqldate($row->fecha_nacimiento))
                : null;
            return $row;
        });

        // ── Totales ─────────────────────────────────────────────────────
        $totalSS       = $planos->sum('total_ss');
        $totalAdmon    = $planos->sum('admon');
        $totalPersonas = $planos->count();

        // Detectar si el plano ya fue pagado:
        // Se considera pagado si TODOS los registros del filtro tienen numero_planilla.
        // Esto evita que otro usuario intente duplicar el pago.
        $planoPagado          = false;
        $numeroPlanillaPagado = null;
        $valorPagado          = null; // total real pagado (SS + mora) desde gastos
        if ($planos->count() > 0) {
            $conPlanilla  = $planos->whereNotNull('numero_planilla')->where('numero_planilla', '!=', '')->count();
            $planoPagado  = ($conPlanilla === $planos->count());
            if ($planoPagado) {
                $numeroPlanillaPagado = $planos->first()->numero_planilla;
                // Buscar el gasto de pago_planilla asociado para obtener el valor real pagado
                $gastosPago = DB::table('gastos')
                    ->where('aliado_id', $aliadoId)
                    ->where('tipo', 'pago_planilla')
                    ->where('numero_planilla', $numeroPlanillaPagado)
                    ->orderByDesc('id')
                    ->first(['valor', 'fecha', 'observacion', 'pagado_a']);
                $valorPagado = $gastosPago ? (int) $gastosPago->valor : null;
            }
        }

        // Bancos (para modal confirmar pago)
        $bancos = BancoCuenta::paraFacturacion($aliadoId);

        // Operadores:
        //   - RS Independiente (es_independiente=true) → mostrar TODOS los operadores
        //     globales activos (el afiliado puede usar cualquier operador).
        //   - RS Normal (dependientes, etc.)           → filtrar por el pivot del aliado
        //     (solo los operadores que el aliado tiene configurados).
        $esIndependienteRS = (bool) ($rsSeleccionada?->es_independiente);

        if ($esIndependienteRS) {
            // Todos los operadores globales activos, sin restricción de aliado
            $operadores = DB::table('operadores_planilla')
                ->whereNull('aliado_id')
                ->where('activo', true)
                ->orderBy('orden')
                ->get();
        } else {
            // Respetar configuración del aliado (pivot aliado_operadores_planilla)
            // Si el aliado tiene filas en el pivot → filtrar por activo=true
            // Si no tiene filas → mostrar todos los globales activos
            $tienePivot = DB::table('aliado_operadores_planilla')
                ->where('aliado_id', $aliadoId)->exists();

            if ($tienePivot) {
                $operadores = DB::table('operadores_planilla AS op')
                    ->join('aliado_operadores_planilla AS piv',
                        fn ($j) => $j->on('piv.operador_id', '=', 'op.id')
                                     ->where('piv.aliado_id', $aliadoId)
                                     ->where('piv.activo', true))
                    ->whereNull('op.aliado_id')
                    ->where('op.activo', true)
                    ->orderBy('op.orden')
                    ->select('op.*')
                    ->get();
            } else {
                $operadores = DB::table('operadores_planilla')
                    ->whereNull('aliado_id')
                    ->where('activo', true)
                    ->orderBy('orden')
                    ->get();
            }
        }

        // Operadores con integración de API (Enlace Operativo) para el ícono
        // de "liquidar en línea" por independiente — igual que el resto de
        // opciones de independientes, sin filtrar por el pivot del aliado.
        $operadoresApiIds = DB::table('operadores_planilla')
            ->whereNull('aliado_id')
            ->where('activo', true)
            ->whereIn('codigo', array_keys(\App\Services\SuaporteApiService::HOSTS))
            ->pluck('id')
            ->all();

        return view('admin.planos.index', compact(
            'planos', 'razonesSociales', 'tiposModalidad', 'modalidadesDispon',
            'cantPorRs', 'diaHoy',
            'anio', 'mes', 'mesVencido', 'anioVencido',
            'razonSocialId', 'nPlanoFiltro', 'modalidadesIds',
            'rsSeleccionada', 'nPlanoActual',
            'totalSS', 'totalAdmon', 'totalPersonas',
            'bancos', 'operadores', 'operadoresApiIds',
            'planoPagado', 'numeroPlanillaPagado', 'valorPagado',
            'estadoPago',
        ) + [
            // Indica si la RS seleccionada es de tipo independiente:
            // en ese caso el pago se confirma POR PERSONA, no por planilla completa.
            'esIndependiente' => (bool) ($rsSeleccionada?->es_independiente),
            // NIT real de la RS (columna nit; fallback al id cuando coinciden)
            'rsNit'           => $rsSeleccionada ? ($rsSeleccionada->nit ?? $rsSeleccionada->id) : null,
            // Día hábil de vencimiento guardado en la RS (null = calcular por tabla)
            'rsDiaHabil'      => $rsSeleccionada?->dia_habil,
        ]);


    }

    // ── 2. API: Razon Social → N_PLANO actual ──────────────────────────
    public function apiRazonSocial(int $id)
    {
        $aliadoId = session('aliado_id_activo');
        $rs = RazonSocial::where('aliado_id', $aliadoId)->find($id);
        if (!$rs) abort(404);

        return response()->json([
            'n_plano'      => $rs->n_plano,
            'razon_social' => $rs->razon_social,
            'nit'          => $rs->id,
        ]);
    }

    // ── 2b. API: Resumen de planos por RS y n_plano (modal resumen) ───────────
    public function apiResumenPlanos(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $anio     = (int) $request->input('anio', now()->year);
        $mes      = (int) $request->input('mes',  now()->month);

        $mesVencido  = $mes > 1 ? $mes - 1 : 12;
        $anioVencido = $mes > 1 ? $anio    : $anio - 1;

        $wherePeriodo = fn ($q) => Plano::filtrarPeriodoDePago($q, $mes, $anio);

        // Contar pagados y pendientes agrupados por RS y n_plano
        $rows = DB::table('planos AS p')
            ->join('razones_sociales AS rs', 'rs.id', '=', 'p.razon_social_id')
            ->leftJoin('facturas AS f', function ($join) use ($aliadoId) {
                $join->on('f.id', '=', 'p.factura_id')
                     ->where('f.aliado_id', $aliadoId);  // scope al aliado
            })
            ->where('p.aliado_id', $aliadoId)
            ->whereNull('p.deleted_at')          // plano no soft-deleted
            ->whereNull('f.deleted_at')          // factura no anulada
            ->whereIn('p.tipo_reg', ['planilla', 'retiro'])
            ->where('p.num_dias', '>', 0)
            ->where('p.n_plano', '>', 0)         // excluye n_plano=0 (retiros IR legacy)
            ->where($wherePeriodo)
            ->groupBy('p.razon_social_id', 'rs.razon_social', 'p.n_plano')
            ->select(
                'p.razon_social_id',
                'rs.razon_social',
                'p.n_plano',
                DB::raw("SUM(CASE WHEN ISNULL(p.numero_planilla,'') <> '' THEN 1 ELSE 0 END) AS pagados"),
                DB::raw("SUM(CASE WHEN ISNULL(p.numero_planilla,'') = ''  THEN 1 ELSE 0 END) AS pendientes")
            )
            ->orderBy('rs.razon_social')
            ->orderBy('p.n_plano')
            ->get();

        // Agrupar por RS
        $agrupado = [];
        foreach ($rows as $row) {
            $rsId = $row->razon_social_id;
            if (!isset($agrupado[$rsId])) {
                $agrupado[$rsId] = [
                    'id'           => $rsId,
                    'razon_social' => $row->razon_social,
                    'planos'       => [],
                    'total_pend'   => 0,
                    'total_pag'    => 0,
                ];
            }
            $agrupado[$rsId]['planos'][] = [
                'n_plano'    => (int)$row->n_plano,
                'pagados'    => (int)$row->pagados,
                'pendientes' => (int)$row->pendientes,
            ];
            $agrupado[$rsId]['total_pend'] += (int)$row->pendientes;
            $agrupado[$rsId]['total_pag']  += (int)$row->pagados;
        }

        return response()->json([
            'ok'  => true,
            'data'=> array_values($agrupado),
            'dia' => (int) now()->day,
        ]);
    }


    public function actualizarNPlano(Request $request)
    {
        // Forzar respuesta JSON siempre (petición AJAX)
        $aliadoId = session('aliado_id_activo');

        try {
            $validated = $request->validate([
                'razon_social_id' => 'required|integer',
                'n_plano'         => 'required|integer|min:1',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Datos inválidos: ' . implode(', ', collect($e->errors())->flatten()->toArray()),
            ], 422);
        }

        if (!$aliadoId) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Sesión expirada. Recargue la página.',
            ], 401);
        }

        $rs = RazonSocial::where('aliado_id', $aliadoId)
            ->find($validated['razon_social_id']);

        if (!$rs) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Razón Social no encontrada o no pertenece a este aliado.',
            ], 404);
        }

        $rs->update(['n_plano' => $validated['n_plano']]);

        return response()->json([
            'ok'      => true,
            'n_plano' => $rs->n_plano,
            'mensaje' => "N_PLANO actualizado a {$rs->n_plano} para {$rs->razon_social}",
        ]);
    }

    // ── 3a-bis. Asignar operador de planilla al contratista ────────────
    // Desde la tabla de planos: cuando un independiente no tiene operador,
    // el select de la columna "Operador" lo asigna sin salir de la pantalla.
    // Escribe en clientes.operador_planilla_id, el mismo campo que edita el
    // formulario de contrato (ver ContratoController::update).
    public function asignarOperadorCliente(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        try {
            $validated = $request->validate([
                'cliente_id'           => 'required|integer',
                'operador_planilla_id' => 'required|integer',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Datos inválidos: ' . implode(', ', collect($e->errors())->flatten()->toArray()),
            ], 422);
        }

        if (!$aliadoId) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Sesión expirada. Recargue la página.',
            ], 401);
        }

        // Multi-tenant: el contratista debe ser del aliado activo
        $cliente = \App\Models\Cliente::where('aliado_id', $aliadoId)
            ->find($validated['cliente_id']);

        if (!$cliente) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Contratista no encontrado o no pertenece a este aliado.',
            ], 404);
        }

        // Solo operadores globales activos (los mismos que ofrece el select)
        $operador = DB::table('operadores_planilla')
            ->whereNull('aliado_id')
            ->where('activo', true)
            ->find($validated['operador_planilla_id']);

        if (!$operador) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Operador no válido o inactivo.',
            ], 404);
        }

        $cliente->update(['operador_planilla_id' => $operador->id]);

        return response()->json([
            'ok'       => true,
            'operador' => [
                'id'     => (int) $operador->id,
                'nombre' => $operador->nombre,
                // Si el operador tiene integración por API, el botón PSE se habilita
                'api'    => in_array($operador->codigo, array_keys(\App\Services\SuaporteApiService::HOSTS), true),
            ],
            'mensaje'  => "Operador {$operador->nombre} asignado al contratista {$cliente->cedula}.",
        ]);
    }

    // ── 3b. Mover un registro de plano a otro n_plano ─────────────────
    public function moverPlano(Request $request, int $id)
    {
        $aliadoId = session('aliado_id_activo');

        if (!$aliadoId) {
            return response()->json(['ok' => false, 'mensaje' => 'Sesión expirada.'], 401);
        }

        $nPlano = (int) $request->input('n_plano');
        if ($nPlano < 1) {
            return response()->json(['ok' => false, 'mensaje' => 'N_PLANO debe ser ≥ 1.'], 422);
        }

        // Obtener el factura_id del plano antes de actualizar
        $plano = DB::table('planos')
            ->where('id', $id)
            ->where('aliado_id', $aliadoId)
            ->whereNull('deleted_at')
            ->select('id', 'factura_id')
            ->first();

        if (!$plano) {
            return response()->json(['ok' => false, 'mensaje' => 'Registro no encontrado o sin cambios.'], 404);
        }

        DB::transaction(function () use ($id, $aliadoId, $nPlano, $plano) {
            // 1) Actualizar el plano
            DB::table('planos')
                ->where('id', $id)
                ->where('aliado_id', $aliadoId)
                ->whereNull('deleted_at')
                ->update(['n_plano' => $nPlano]);

            // 2) Sincronizar facturas.n_plano para mantener consistencia con historial
            if ($plano->factura_id) {
                DB::table('facturas')
                    ->where('id', $plano->factura_id)
                    ->where('aliado_id', $aliadoId)
                    ->whereNull('deleted_at')
                    ->update(['n_plano' => $nPlano]);
            }
        });

        return response()->json([
            'ok'      => true,
            'mensaje' => "Registro movido al plano P{$nPlano}.",
        ]);
    }


    // ── 3c. Mover múltiples registros de plano a otro n_plano ─────────
    public function moverPlanoMasivo(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        if (!$aliadoId) {
            return response()->json(['ok' => false, 'mensaje' => 'Sesión expirada.'], 401);
        }

        $ids = $request->input('ids');
        if (!is_array($ids) || empty($ids)) {
            return response()->json(['ok' => false, 'mensaje' => 'Debe seleccionar al menos un registro.'], 422);
        }

        $nPlano = (int) $request->input('n_plano');
        if ($nPlano < 1) {
            return response()->json(['ok' => false, 'mensaje' => 'N_PLANO debe ser ≥ 1.'], 422);
        }

        // Obtener los factura_id de los planos antes de actualizar
        $facturaIds = DB::table('planos')
            ->whereIn('id', $ids)
            ->where('aliado_id', $aliadoId)
            ->whereNull('deleted_at')
            ->whereNotNull('factura_id')
            ->pluck('factura_id')
            ->toArray();

        $updated = DB::transaction(function () use ($ids, $aliadoId, $nPlano, $facturaIds) {
            // 1) Actualizar los planos
            $cnt = DB::table('planos')
                ->whereIn('id', $ids)
                ->where('aliado_id', $aliadoId)
                ->whereNull('deleted_at')
                ->update(['n_plano' => $nPlano]);

            // 2) Sincronizar facturas.n_plano para mantener consistencia con historial
            if (!empty($facturaIds)) {
                DB::table('facturas')
                    ->whereIn('id', $facturaIds)
                    ->where('aliado_id', $aliadoId)
                    ->whereNull('deleted_at')
                    ->update(['n_plano' => $nPlano]);
            }

            return $cnt;
        });

        if (!$updated) {
            return response()->json(['ok' => false, 'mensaje' => 'No se encontraron registros elegibles para mover.'], 404);
        }

        return response()->json([
            'ok'      => true,
            'mensaje' => "{$updated} registros movidos correctamente al plano P{$nPlano}.",
        ]);
    }


    // ── 4. Descargar XLSX planilla SS (formato ayuda NI) ──────────────
    public function descargar(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $formato  = $request->input('formato', 'txt'); // 'txt' | 'xlsx'

        $razonSocialId  = $request->input('razon_social_id');
        $mes            = (int) $request->input('mes',  now()->month);
        $anio           = (int) $request->input('anio', now()->year);
        $nPlano         = (int) $request->input('n_plano', 1);
        $tiposModalidad = array_map('intval', (array) $request->input('tipos_modalidad', []));
        $operadorId     = $request->input('operador_id'); // ID del operador seleccionado

        $rsNombre = 'SIN_RS';
        if ($razonSocialId) {
            $rs = RazonSocial::find($razonSocialId);
            if ($rs) {
                $rsNombre = preg_replace('/[^A-Za-z0-9_\-]/', '_', $rs->razon_social);
            }
        }

        $nombreBase = "{$rsNombre}_{$mes}_{$anio}_P{$nPlano}";

        if ($formato === 'xlsx') {
            if (!$razonSocialId) {
                abort(400, 'Debe seleccionar una Razon Social para descargar el Excel.');
            }

            try {
                $service     = new ExcelPlanoNIService();
                $spreadsheet = $service->generar([
                    'aliado_id'       => $aliadoId,
                    'razon_social_id' => $razonSocialId,
                    'mes'             => $mes,
                    'anio'            => $anio,
                    'n_plano'         => $nPlano,
                    'tipos_modalidad' => $tiposModalidad, // ya casteados a int
                    'operador_id'     => $operadorId,
                ]);

                return $service->respuesta($spreadsheet, "{$nombreBase}.xlsx");

            } catch (\Illuminate\Database\QueryException $e) {
                // QueryException va PRIMERO porque extiende RuntimeException.
                // Error de base de datos → 500 con log detallado.
                \Illuminate\Support\Facades\Log::error('ExcelPlano QueryException', [
                    'sql'    => $e->getSql(),
                    'msg'    => $e->getMessage(),
                    'params' => [$razonSocialId, $mes, $anio, $nPlano],
                ]);
                abort(500, 'Error de base de datos al generar el Excel. Revise los logs.');
            } catch (\RuntimeException $e) {
                // Error de validación de negocio (ej: RS no encontrada)
                \Illuminate\Support\Facades\Log::error('ExcelPlano RuntimeException', ['msg' => $e->getMessage()]);
                abort(422, $e->getMessage());
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('ExcelPlano Error', ['msg' => $e->getMessage()]);
                abort(500, 'Error al generar el Excel: ' . $e->getMessage());
            }
        }

        // TXT vacio (comportamiento anterior)
        return response('', 200, [
            'Content-Type'        => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$nombreBase}.txt\"",
        ]);
    }

    // ── 4b. Descargar XLSX formato Asopagos ────────────────────────────
    public function descargarAsopagos(Request $request)
    {
        $aliadoId      = session('aliado_id_activo');
        $razonSocialId = $request->input('razon_social_id');
        $mes           = (int) $request->input('mes',  now()->month);
        $anio          = (int) $request->input('anio', now()->year);
        $nPlano        = (int) $request->input('n_plano', 1);
        $tiposModalidad = (array) $request->input('tipos_modalidad', []);

        if (!$razonSocialId) {
            abort(400, 'Debe seleccionar una Razón Social para descargar el formato Asopagos.');
        }

        $rsNombre = 'SIN_RS';
        $rs = RazonSocial::find($razonSocialId);
        if ($rs) {
            $rsNombre = preg_replace('/[^A-Za-z0-9_\-]/', '_', $rs->razon_social);
        }

        $nombreBase = "ASOPAGOS_{$rsNombre}_{$mes}_{$anio}_P{$nPlano}";

        try {
            $service     = new ExcelAsopagosService();
            $spreadsheet = $service->generar([
                'aliado_id'       => $aliadoId,
                'razon_social_id' => $razonSocialId,
                'mes'             => $mes,
                'anio'            => $anio,
                'n_plano'         => $nPlano,
                'tipos_modalidad' => $tiposModalidad,
            ]);

            return $service->respuesta($spreadsheet, "{$nombreBase}.xlsx");

        } catch (\Illuminate\Database\QueryException $e) {
            \Illuminate\Support\Facades\Log::error('AsopagosExcel QueryException', [
                'sql' => $e->getSql(), 'msg' => $e->getMessage(),
            ]);
            abort(500, 'Error de base de datos al generar el archivo Asopagos.');
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        } catch (\Exception $e) {
            abort(500, 'Error al generar el archivo Asopagos: ' . $e->getMessage());
        }
    }

    // ── 4b. Descargar TXT MiPlanilla (PILA 693 chars) ────────────────────
    public function descargarMiPlanilla(Request $request)
    {
        $aliadoId      = session('aliado_id_activo');
        $razonSocialId = $request->input('razon_social_id');
        $mes           = (int) $request->input('mes',  now()->month);
        $anio          = (int) $request->input('anio', now()->year);
        $nPlano        = (int) $request->input('n_plano', 1);
        $tiposModalidad = (array) $request->input('tipos_modalidad', []);

        if (!$razonSocialId) {
            abort(400, 'Debe seleccionar una Razón Social.');
        }

        try {
            $service = new \App\Services\PlanoPilaTxtService();
            return $service->generar([
                'aliado_id'       => $aliadoId,
                'razon_social_id' => $razonSocialId,
                'mes'             => $mes,
                'anio'            => $anio,
                'n_plano'         => $nPlano,
                'tipos_modalidad' => $tiposModalidad,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            \Illuminate\Support\Facades\Log::error('MiPlanillaTXT QueryException', [
                'sql' => $e->getSql(), 'msg' => $e->getMessage(),
            ]);
            abort(500, 'Error de base de datos al generar el TXT MiPlanilla.');
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        } catch (\Exception $e) {
            abort(500, 'Error al generar TXT MiPlanilla: ' . $e->getMessage());
        }
    }

    // ── 4c. Descargar Excel Aportes en Línea (formato AEL / ASOPAGOS) ──────────
    public function descargarAportesEnLinea(Request $request)
    {
        return $this->descargarExcelAel($request, new \App\Services\ExcelAportesEnLineaService(), 'AEL');
    }

    // ── 4c-bis. Excel Aportes en Línea 2 (plantilla del portal) ──────────────
    /**
     * Mismos datos que descargarAportesEnLinea(), pero escritos sobre la
     * plantilla que entrega el propio portal al "Exportar plano": conserva la
     * hoja oculta de catálogos y las listas desplegables de validación.
     */
    public function descargarAportesEnLinea2(Request $request)
    {
        return $this->descargarExcelAel($request, new \App\Services\ExcelAportesEnLinea2Service(), 'AEL2');
    }

    /**
     * Tronco común de los dos Excel de Aportes en Línea: valida la razón
     * social, arma el nombre del archivo y traduce las excepciones del
     * servicio en códigos HTTP.
     */
    private function descargarExcelAel(Request $request, \App\Services\ExcelAportesEnLineaService $service, string $prefijo)
    {
        $aliadoId      = session('aliado_id_activo');
        $razonSocialId = $request->input('razon_social_id');
        $mes           = (int) $request->input('mes',  now()->month);
        $anio          = (int) $request->input('anio', now()->year);
        $nPlano        = (int) $request->input('n_plano', 1);
        $tiposModalidad = array_map('intval', (array) $request->input('tipos_modalidad', []));

        if (!$razonSocialId) {
            abort(400, 'Debe seleccionar una Razón Social.');
        }

        $rsNombre = 'SIN_RS';
        $rs = \App\Models\RazonSocial::find($razonSocialId);
        if ($rs) {
            $rsNombre = preg_replace('/[^A-Za-z0-9_\-]/', '_', $rs->razon_social);
        }
        $filename = "{$prefijo}_{$rsNombre}_{$mes}_{$anio}_P{$nPlano}.xlsx";

        try {
            $spreadsheet = $service->generar([
                'aliado_id'       => $aliadoId,
                'razon_social_id' => $razonSocialId,
                'mes'             => $mes,
                'anio'            => $anio,
                'n_plano'         => $nPlano,
                'tipos_modalidad' => $tiposModalidad, // ya casteados a int
            ]);
            return $service->respuesta($spreadsheet, $filename);
        } catch (\Illuminate\Database\QueryException $e) {
            \Illuminate\Support\Facades\Log::error("{$prefijo} QueryException", [
                'sql' => $e->getSql(), 'msg' => $e->getMessage(),
            ]);
            abort(500, "Error de base de datos al generar el Excel {$prefijo}.");
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        } catch (\Exception $e) {
            abort(500, "Error al generar Excel {$prefijo}: " . $e->getMessage());
        }
    }

    // ── 4d. Limpiar planos huérfanos (factura anulada pero plano activo) ──────
    /**
     * Soft-deletea planos cuyos factura_id apunta a facturas ya anuladas (deleted_at != null).
     * Solo ejecutable por superadmin. Resuelve duplicados causados por el bug del hasOne
     * en el método anular() que dejaba planos activos al eliminar solo el primero del lote.
     */
    public function limpiarHuerfanos(Request $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user || !$user->hasRole('superadmin')) {
            return response()->json(['ok' => false, 'mensaje' => 'Sin permisos.'], 403);
        }

        $aliadoId = session('aliado_id_activo');

        // Buscar planos activos cuya factura está soft-deleted (anulada)
        $huerfanos = DB::table('planos AS p')
            ->join('facturas AS f', 'f.id', '=', 'p.factura_id')
            ->whereNull('p.deleted_at')
            ->whereNotNull('f.deleted_at')   // factura anulada
            ->where('p.aliado_id', $aliadoId)
            ->select('p.id', 'p.no_identifi', 'p.primer_nombre', 'p.primer_ape',
                     'p.factura_id', 'f.numero_factura')
            ->get();

        $ids = $huerfanos->pluck('id')->toArray();

        if (empty($ids)) {
            return response()->json([
                'ok'      => true,
                'mensaje' => 'No se encontraron planos huérfanos.',
                'eliminados' => 0,
            ]);
        }

        // Soft-delete masivo
        Plano::whereIn('id', $ids)->each(fn($p) => $p->delete());

        return response()->json([
            'ok'         => true,
            'mensaje'    => count($ids) . ' plano(s) huérfano(s) eliminados correctamente.',
            'eliminados' => count($ids),
            'detalle'    => $huerfanos->map(fn($p) => [
                'plano_id'       => $p->id,
                'cedula'         => $p->no_identifi,
                'nombre'         => trim($p->primer_nombre . ' ' . $p->primer_ape),
                'factura_id'     => $p->factura_id,
                'numero_factura' => $p->numero_factura,
            ]),
        ]);
    }

    // ── 5. Confirmar Pago ─────────────────────────────────────────────
    public function confirmarPago(Request $request)
    {
        $aliadoId  = session('aliado_id_activo');
        $usuarioId = Auth::id();

        // mes_plano recibido = mes de PAGO (el que muestra el filtro UI).
        $validated = $request->validate([
            'razon_social_id'  => 'required|integer',
            'mes_plano'        => 'required|integer|between:1,12',
            'anio_plano'       => 'required|integer',
            'n_plano'          => 'required|integer|min:1',
            'tipos_modalidad'  => 'nullable|array',
            'operador'         => 'required|string|max:100',
            'numero_planilla'  => 'required|string|max:80',
            'valor'            => 'required|integer|min:1',
            'forma_pago'       => 'required|in:transferencia,efectivo',
            // banco_id solo requerido cuando la forma NO es efectivo
            'banco_id'         => 'required_unless:forma_pago,efectivo|nullable|integer',
            'observacion'      => 'nullable|string|max:1000',
            'soporte'          => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120', // 5MB
            // Confirmación individual (RS independiente): ID del plano específico
            'plano_id'         => 'nullable|integer',
            // Fecha real del pago. Por defecto hoy, como siempre; se puede
            // corregir cuando la confirmación se registra días después, que es
            // lo que la corrección de una E-1 lleva al campo 10 del registro
            // tipo 1 y el operador valida contra el pago real.
            'fecha_pago'       => 'nullable|date',
        ]);


        $rs = RazonSocial::where('aliado_id', $aliadoId)
            ->findOrFail($validated['razon_social_id']);

        // Validar si ya existe un pago de planilla confirmado con este número de planilla para el aliado
        $existeGasto = Gasto::where('aliado_id', $aliadoId)
            ->where('tipo', 'pago_planilla')
            ->where('numero_planilla', $validated['numero_planilla'])
            ->exists();

        if ($existeGasto) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'La planilla N° ' . $validated['numero_planilla'] . ' ya tiene un pago confirmado registrado.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // ── a) Crear gasto tipo pago_planilla ──────────────────────
            $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            $mesNombre = $meses[$validated['mes_plano']] ?? $validated['mes_plano'];

            $descripcion = "Pago planilla SS — {$rs->razon_social} | "
                . "Periodo pago: {$mesNombre} {$validated['anio_plano']} | "
                . "Operador: {$validated['operador']} | "
                . "Planilla: {$validated['numero_planilla']}";

            $gasto = Gasto::create([
                'aliado_id'         => $aliadoId,
                'usuario_id'        => $usuarioId,
                'cuadre_id'         => null,
                'fecha'             => $validated['fecha_pago'] ?? today(),
                'tipo'              => 'pago_planilla',
                'numero_planilla'   => $validated['numero_planilla'],   // ← campo dedicado
                'descripcion'       => $descripcion,
                'pagado_a'          => $validated['operador'],
                'forma_pago'        => $validated['forma_pago'],
                'banco_origen_id'   => $validated['forma_pago'] !== 'efectivo'
                    ? ($validated['banco_id'] ?? null)
                    : null,
                'valor'             => $validated['valor'],
                'observacion'       => $validated['observacion'],
            ]);

            // ── Guardar imagen de soporte si viene adjunta ─────────────
            $soporteUrl = null;
            if ($request->hasFile('soporte')) {
                $path = $request->file('soporte')->store(
                    "gastos/{$aliadoId}", 'public'
                );
                $gasto->update(['imagen_path' => $path]);
                $soporteUrl = Storage::url($path);
            }


            // ── b) Calcular periodos reales (logica mes vencido) ──────
            // Con `paga_mes_actual` → mes real = mes_pago
            // Los demas            → mes real = mes_pago - 1
            $mesPago     = $validated['mes_plano'];
            $anioPago    = $validated['anio_plano'];
            $mesVencido  = $mesPago > 1 ? $mesPago - 1 : 12;
            $anioVencido = $mesPago > 1 ? $anioPago    : $anioPago - 1;

            // ── c) Actualizar numero_planilla ────────────────────────────────────────────────────────────────────────
            // Modo A) Individual (RS independiente): solo actualizar el plano_id recibido.
            // Modo B) Masivo: actualizar todos los planos del filtro (comportamiento original).
            if (!empty($validated['plano_id'])) {
                // ── MODO INDIVIDUAL ──
                $cantActualizados = DB::table('planos')
                    ->where('id',        $validated['plano_id'])
                    ->where('aliado_id', $aliadoId)
                    ->whereNull('deleted_at')
                    ->update([
                        'numero_planilla' => $validated['numero_planilla'],
                        'updated_at'      => now(),
                    ]);
            } else {
                // ── MODO MASIVO (comportamiento original) ──
                $queryUpdate = DB::table('planos')
                    ->where('aliado_id', $aliadoId)
                    ->whereNull('deleted_at')
                    ->whereIn('tipo_reg', ['planilla', 'retiro'])
                    ->where('num_dias', '>', 0)
                    ->where('razon_social_id', $validated['razon_social_id'])
                    ->where('n_plano', $validated['n_plano'])
                    ->tap(fn ($q) => Plano::filtrarPeriodoDePago($q, $mesPago, $anioPago, null));

                if (!empty($validated['tipos_modalidad'])) {
                    // Castear a int para que SQL Server compare correctamente
                    // con la columna integer, especialmente con valores negativos (-1, -6, etc.)
                    $tiposIds = array_map('intval', (array) $validated['tipos_modalidad']);
                    $queryUpdate->whereIn('tipo_modalidad_id', $tiposIds);
                }

                $cantActualizados = $queryUpdate->update([
                    'numero_planilla' => $validated['numero_planilla'],
                    'updated_at'      => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'ok'                => true,
                'mensaje'           => "Pago confirmado. Se actualizaron {$cantActualizados} registros con la planilla {$validated['numero_planilla']}.",
                'gasto_id'          => $gasto->id,
                'cant_actualizados' => $cantActualizados,
                'soporte_url'       => $soporteUrl,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Error al confirmar el pago: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Genera y descarga el Reporte Individual de Aportes en PDF con formato Suaporte
     * buscando por cédula (no_identifi) y número de planilla.
     */
    public function descargarCertificadoPdf(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $cedula = $request->input('cedula');
        $numeroPlanilla = $request->input('numero_planilla');

        if (!$cedula || !$numeroPlanilla) {
            abort(400, 'Debe suministrar la cédula y el número de planilla.');
        }

        // Buscar el cotizante y sus aportes en la tabla de planos
        $plano = \App\Models\Plano::with('razonSocial')
            ->where('aliado_id', $aliadoId)
            ->where('no_identifi', $cedula)
            ->where('numero_planilla', $numeroPlanilla)
            ->whereNull('deleted_at')
            ->first();

        if (!$plano) {
            abort(404, 'No se encontró ningún registro de planilla de pago con esos datos.');
        }

        $forceOperadorId = $request->input('forzar_operador_id');
        if ($forceOperadorId) {
            $forceOperadorId = (int) $forceOperadorId;
        }

        // Generar el PDF de planilla rellenando la plantilla correspondiente al operador configurado de forma dinámica
        $pdfContent = (new \App\Services\PlanillaFormularioService())->generar($plano, $forceOperadorId);

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"" . \App\Services\PlanillaWhatsappService::generarNombreArchivoPdf(
                trim("{$plano->primer_nombre} {$plano->segundo_nombre} {$plano->primer_ape} {$plano->segundo_ape}"),
                now()->month,
                now()->year
            ) . "\"")
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
    }

    // ── Módulo de Envíos de Planillas por WhatsApp ───────────────────

    public function enviosPlanillaIndex(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $anio = (int) $request->input('anio', now()->year);
        $mes  = (int) $request->input('mes', now()->month);

        $config = \App\Models\WhatsappConfig::paraAliado($aliadoId);
        $plantilla = null;
        
        if ($config) {
            if ($config->planilla_envio_plantilla_id) {
                $plantilla = \App\Models\WhatsappPlantilla::find($config->planilla_envio_plantilla_id);
            } elseif ($config->usa_cuenta_brynex || $config->usa_brynex) {
                // Heredar la de Brynex global (aliado 1 o buscar por nombre BryNex)
                $brynexId = \App\Models\Aliado::where('nombre', 'BryNex')->first()?->id ?: 1;
                $configGlobal = \App\Models\WhatsappConfig::paraAliado($brynexId);
                if ($configGlobal && $configGlobal->planilla_envio_plantilla_id) {
                    $plantilla = \App\Models\WhatsappPlantilla::find($configGlobal->planilla_envio_plantilla_id);
                } else {
                    $plantilla = \App\Models\WhatsappPlantilla::where('aliado_id', $brynexId)
                        ->where('nombre', 'envio_planilla_seguridad_social')
                        ->first();
                }
            }
        }

        // Si no está configurada, buscamos si ya existe por nombre del aliado local
        if (!$plantilla) {
            $plantilla = \App\Models\WhatsappPlantilla::where('aliado_id', $aliadoId)
                ->where('nombre', 'envio_planilla_seguridad_social')
                ->first();
        }

        return view('admin.planos.envio_planillas', compact('anio', 'mes', 'config', 'plantilla'));
    }

    public function enviosPlanillaApi(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $anio       = (int) $request->input('anio', now()->year);
        $mes        = (int) $request->input('mes', now()->month);
        $tipoEnvio  = $request->input('tipo_envio', 'individual'); // 'individual' | 'empleado_empresa' | 'contacto_empresa'
        $filtroEst  = $request->input('estado', 'pendientes'); // 'todos' | 'pendientes' | 'enviados' | 'fallidos'

        $service = app(\App\Services\PlanillaWhatsappService::class);
        $planos = $service->obtenerPlanosPagados($aliadoId, $mes, $anio);

        $destinatarios = $service->obtenerDestinatarios($planos, $tipoEnvio);

        // Aplicar filtros de estado
        if ($filtroEst === 'pendientes') {
            // Mostrar pendientes y fallidos tal como pidió el usuario
            $destinatarios = $destinatarios->filter(fn($d) => in_array($d['envio_estado'], ['pendiente', 'fallido']));
        } elseif ($filtroEst === 'enviados') {
            $destinatarios = $destinatarios->filter(fn($d) => $d['envio_estado'] === 'enviado');
        } elseif ($filtroEst === 'fallidos') {
            $destinatarios = $destinatarios->filter(fn($d) => $d['envio_estado'] === 'fallido');
        }

        // Búsqueda de texto en las columnas
        $q = $request->input('q');
        if (!empty($q)) {
            $q = lowercase(trim($q));
            $destinatarios = $destinatarios->filter(function($d) use ($q) {
                return stripos(lowercase($d['nombre_destinatario']), $q) !== false ||
                       stripos(lowercase($d['cliente_cedula']), $q) !== false ||
                       stripos(lowercase($d['numero_planilla']), $q) !== false;
            });
        }

        return response()->json([
            'ok' => true,
            'data' => $destinatarios->values(),
        ]);
    }

    public function enviosPlanillaEnviar(Request $request)
    {
        abort_unless(Auth::user()->hasRole(['admin', 'superadmin']), 403, 'No autorizado.');

        // Evitar timeout por procesamiento síncrono (QUEUE_CONNECTION=sync)
        @set_time_limit(0);
        @ini_set('max_execution_time', 0);

        $aliadoId = session('aliado_id_activo');
        $usuarioId = Auth::id();

        $anio      = (int) $request->input('anio', now()->year);
        $mes       = (int) $request->input('mes', now()->month);
        $tipoEnvio = $request->input('tipo_envio', 'individual');

        $config = \App\Models\WhatsappConfig::paraAliado($aliadoId);
        if (!$config || !$config->credencialesCompletas()) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No se han configurado o completado las credenciales de WhatsApp para este aliado.'
            ], 422);
        }

        $plantillaId = $config->planilla_envio_plantilla_id;
        if (!$plantillaId) {
            if ($config->usa_cuenta_brynex || $config->usa_brynex) {
                $brynexId = \App\Models\Aliado::where('nombre', 'BryNex')->first()?->id ?: 1;
                $configGlobal = \App\Models\WhatsappConfig::paraAliado($brynexId);
                $plantillaId = $configGlobal?->planilla_envio_plantilla_id;
                if (!$plantillaId) {
                    $plantilla = \App\Models\WhatsappPlantilla::where('aliado_id', $brynexId)
                        ->where('nombre', 'envio_planilla_seguridad_social')
                        ->first();
                    $plantillaId = $plantilla?->id;
                }
            } else {
                $plantilla = \App\Models\WhatsappPlantilla::where('aliado_id', $aliadoId)
                    ->where('nombre', 'envio_planilla_seguridad_social')
                    ->first();
                $plantillaId = $plantilla?->id;
            }
        }

        if (!$plantillaId) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'La plantilla de envío de planillas no está creada o configurada para este aliado.'
            ], 422);
        }

        $service = app(\App\Services\PlanillaWhatsappService::class);
        $planos = $service->obtenerPlanosPagados($aliadoId, $mes, $anio);
        $destinatarios = $service->obtenerDestinatarios($planos, $tipoEnvio);

        // Obtener los IDs seleccionados desde el request
        $planoIdsSeleccionados = array_map('intval', (array) $request->input('plano_ids', []));

        // Filtrar destinatarios que estén seleccionados y además pendientes o fallidos
        $destinatariosAEnviar = $destinatarios->filter(function($d) use ($planoIdsSeleccionados) {
            return in_array((int)$d['plano_id'], $planoIdsSeleccionados)
                && in_array($d['envio_estado'], ['pendiente', 'fallido']);
        })->values();

        if ($destinatariosAEnviar->isEmpty()) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No hay planillas pendientes de envío (o fallidas) para los filtros seleccionados.'
            ], 422);
        }

        // Crear el lote
        $lote = $service->crearLoteEnvio($aliadoId, $usuarioId, $plantillaId, $mes, $anio, $tipoEnvio, $destinatariosAEnviar);

        // Despachar el Job
        \App\Jobs\PlanillaEnvioWhatsappJob::dispatch($lote->id);

        return response()->json([
            'ok' => true,
            'mensaje' => 'Lote de envío masivo creado e iniciado en segundo plano. Progreso visible en la vista.',
            'lote_id' => $lote->id
        ]);
    }

    public function enviosPlanillaReenviar(Request $request, $planoId)
    {
        abort_unless(Auth::user()->hasRole(['admin', 'superadmin']), 403, 'No autorizado.');

        $aliadoId = session('aliado_id_activo');

        $plano = Plano::where('aliado_id', $aliadoId)->findOrFail($planoId);

        $config = \App\Models\WhatsappConfig::paraAliado($aliadoId);
        if (!$config || !$config->credencialesCompletas()) {
            return response()->json(['ok' => false, 'mensaje' => 'WhatsApp no configurado.'], 422);
        }

        $plantillaId = $config->planilla_envio_plantilla_id;
        if (!$plantillaId) {
            if ($config->usa_cuenta_brynex || $config->usa_brynex) {
                $brynexId = \App\Models\Aliado::where('nombre', 'BryNex')->first()?->id ?: 1;
                $configGlobal = \App\Models\WhatsappConfig::paraAliado($brynexId);
                $plantillaId = $configGlobal?->planilla_envio_plantilla_id;
                if (!$plantillaId) {
                    $plantilla = \App\Models\WhatsappPlantilla::where('aliado_id', $brynexId)
                        ->where('nombre', 'envio_planilla_seguridad_social')
                        ->first();
                    $plantillaId = $plantilla?->id;
                }
            } else {
                $plantilla = \App\Models\WhatsappPlantilla::where('aliado_id', $aliadoId)
                    ->where('nombre', 'envio_planilla_seguridad_social')
                    ->first();
                $plantillaId = $plantilla?->id;
            }
        }

        $plantilla = \App\Models\WhatsappPlantilla::find($plantillaId);
        if (!$plantilla) {
            return response()->json(['ok' => false, 'mensaje' => 'Plantilla no configurada.'], 422);
        }

        // Buscar el número de celular del destinatario
        $cliente = \App\Models\Cliente::where('cedula', $plano->no_identifi)
            ->where('aliado_id', $aliadoId)
            ->first();

        // nombre_destinatario: siempre es el nombre del CLIENTE afiliado
        $nombreDestinatario = $cliente
            ? trim("{$cliente->primer_nombre} {$cliente->segundo_nombre} {$cliente->primer_apellido} {$cliente->segundo_apellido}")
            : 'Cliente';
        $numeroCelular = $cliente?->celular;

        // Si es tipo contacto_empresa, enviar al número del contacto de empresa
        $tipoEnvio = $request->input('tipo_envio', 'individual');
        if ($tipoEnvio === 'contacto_empresa' || !$numeroCelular) {
            $empresa = \App\Models\Empresa::find($cliente?->cod_empresa);
            // Primero el celular del encargado de la seguridad social; si la
            // empresa no tiene encargado, el número general.
            if ($empresa && $empresa->celularParaEnviar()) {
                $numeroCelular = $empresa->celularParaEnviar();
                // nombre sigue siendo el del CLIENTE (no el contacto de empresa)
            }
        }

        $celularPrueba = $request->input('celular_prueba');
        $esPrueba = !empty($celularPrueba);

        if ($esPrueba) {
            $numeroCelular = $celularPrueba;
            $nombreDestinatario = "Prueba - " . $nombreDestinatario;
        }

        if (!$numeroCelular) {
            return response()->json(['ok' => false, 'mensaje' => 'El destinatario no posee número de celular registrado.'], 422);
        }

        // Detección del operador
        $gasto = DB::table('gastos')
            ->where('aliado_id', $aliadoId)
            ->where('tipo', 'pago_planilla')
            ->where('numero_planilla', $plano->numero_planilla)
            ->first(['pagado_a']);

        $operadorId = null;
        $operadorNombre = 'Operador';
        if ($gasto) {
            $operador = DB::table('operadores_planilla')
                ->where('nombre', trim($gasto->pagado_a))
                ->first(['id', 'nombre', 'codigo']);

            if (!$operador) {
                // Intentar búsqueda por substring
                foreach (DB::table('operadores_planilla')->get(['id', 'nombre', 'codigo']) as $op) {
                    if (stripos($gasto->pagado_a, $op->nombre) !== false || stripos($op->nombre, $gasto->pagado_a) !== false) {
                        $operador = $op;
                        break;
                    }
                }
            }

            if ($operador) {
                $operadorId = $operador->id;
                $operadorNombre = $operador->nombre;

                // Validar que el operador esté autorizado (solo para envíos reales, no pruebas)
                if (!$esPrueba) {
                    $codigoOp = strtoupper($operador->codigo ?? '');
                    if (!in_array($codigoOp, \App\Services\PlanillaWhatsappService::OPERADORES_AUTORIZADOS)) {
                        return response()->json([
                            'ok' => false,
                            'mensaje' => "El operador '{$operador->nombre}' no tiene plantilla PDF autorizada para envío por WhatsApp. Solo ARUS Enlace, Enlace y Simple están habilitados."
                        ], 422);
                    }
                }
            }
        }

        try {
            $apiService = app(\App\Services\WhatsappApiService::class);
            $formularioService = app(\App\Services\PlanillaFormularioService::class);

            // Generar PDF
            $pdfContenido = $formularioService->generar($plano, $operadorId);

            // Nombre del archivo: usar período de servicio actual (mes del filtro UI)
            $nombreArchivo = \App\Services\PlanillaWhatsappService::generarNombreArchivoPdf(
                trim("{$plano->primer_nombre} {$plano->segundo_nombre} {$plano->primer_ape} {$plano->segundo_ape}"),
                (int) $request->input('mes', now()->month),
                (int) $request->input('anio', now()->year)
            );
            $pathTemporal = "temp_planillas/{$aliadoId}/" . uniqid() . '_' . $nombreArchivo;
            Storage::disk('local')->put($pathTemporal, $pdfContenido);

            // Subir a Meta
            $creds = $config->credencialesEfectivas();
            $mediaId = $apiService->subirMedia($pathTemporal, 'application/pdf', $creds);

            Storage::disk('local')->delete($pathTemporal);

            if (!$mediaId) {
                return response()->json(['ok' => false, 'mensaje' => 'Error al subir el PDF a Meta.'], 422);
            }

            // Parámetros
            $bodyParams = [
                $nombreDestinatario,
                $operadorNombre,
                $plano->numero_planilla ?: 'N/A'
            ];

            // Enviar
            $resultado = $apiService->enviarTemplateConDocumento(
                $numeroCelular,
                $plantilla,
                $bodyParams,
                $mediaId,
                $nombreArchivo,
                $config
            );

            if ($resultado['ok']) {
                // Registrar la conversación y mensaje
                $conversacion = \App\Models\WhatsappConversacion::where('aliado_id', $aliadoId)
                    ->where('wa_contact_id', $numeroCelular)
                    ->first();

                if (!$conversacion) {
                    $conversacion = \App\Models\WhatsappConversacion::create([
                        'aliado_id' => $aliadoId,
                        'wa_contact_id' => $numeroCelular,
                        'nombre_contacto' => $nombreDestinatario,
                        'estado' => 'abierta',
                        'ultimo_mensaje_at' => now()
                    ]);
                }

                \App\Models\WhatsappMensaje::create([
                    'conversacion_id' => $conversacion->id,
                    'aliado_id' => $aliadoId,
                    'wa_message_id' => $resultado['wa_message_id'],
                    'direccion' => 'saliente',
                    'tipo' => 'template',
                    'contenido' => "Reenvío: Certificado Planilla (PDF)",
                    'plantilla_id' => $plantilla->id,
                    'plantilla_parametros' => json_encode($bodyParams),
                    'estado' => 'enviado',
                    'estado_at' => now(),
                ]);

                // Registrar en planilla_envios_whatsapp_detalle si es posible (solo si no es prueba)
                if (!$esPrueba) {
                    DB::table('planilla_envios_whatsapp_detalle')->insert([
                        'envio_id' => 0, // 0 indica envío individual / reenvío directo
                        'plano_id' => $plano->id,
                        'contrato_id' => $plano->contrato_id,
                        'cliente_cedula' => $plano->no_identifi,
                        'wa_numero' => $numeroCelular,
                        'nombre_destinatario' => $nombreDestinatario,
                        'numero_planilla' => $plano->numero_planilla,
                        'operador_nombre' => $operadorNombre,
                        'periodo_mes' => $plano->mes_plano,
                        'periodo_anio' => $plano->anio_plano,
                        'estado' => 'enviado',
                        'wa_message_id' => $resultado['wa_message_id'],
                        'enviado_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                return response()->json(['ok' => true, 'mensaje' => $esPrueba ? 'Mensaje de prueba enviado con éxito.' : 'Planilla reenviada con éxito por WhatsApp.']);
            } else {
                return response()->json(['ok' => false, 'mensaje' => 'Meta API Error: ' . $resultado['error']], 422);
            }

        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'mensaje' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function enviosPlanillaHistorial(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $lotes = \App\Models\PlanillaEnvioWhatsapp::with(['usuario'])
            ->where('aliado_id', $aliadoId)
            ->where('id', '>', 0)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $lotes,
        ]);
    }

    public function enviosPlanillaLoteDetalle(Request $request, $loteId)
    {
        $aliadoId = session('aliado_id_activo');

        $lote = \App\Models\PlanillaEnvioWhatsapp::where('aliado_id', $aliadoId)->findOrFail($loteId);

        $detalles = \App\Models\PlanillaEnvioWhatsappDetalle::where('envio_id', $lote->id)->get();

        return response()->json([
            'ok' => true,
            'lote' => $lote,
            'detalles' => $detalles,
        ]);
    }

    public function enviosPlanillaCrearPlantilla(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        try {
            $service = app(\App\Services\PlanillaWhatsappService::class);
            $plantilla = $service->crearPlantillaEnMeta($aliadoId);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Plantilla "envio_planilla_seguridad_social" creada y asociada de forma exitosa.',
                'plantilla' => $plantilla
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Error al crear la plantilla: ' . $e->getMessage()
            ], 500);
        }
    }
}


