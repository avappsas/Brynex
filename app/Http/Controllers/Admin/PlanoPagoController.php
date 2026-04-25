<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Plano, RazonSocial, TipoModalidad, BancoCuenta, Gasto, OperadorPlanilla, User};
use App\Services\ExcelPlanoNIService;
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

        // ── Logica de mes vencido ────────────────────────────────────────
        // El filtro MES muestra el mes de PAGO (mes seleccionado por el usuario).
        // Internamente:
        //   - Independientes (tipo_modalidad_id = 11) → mes_plano = mes_pago (mes actual)
        //   - Todos los demas (dependientes, etc.)    → mes_plano = mes_pago - 1 (mes vencido)
        $mesVencido  = $mes > 1 ? $mes - 1 : 12;
        $anioVencido = $mes > 1 ? $anio    : $anio - 1;

        // Closure reutilizable para el WHERE de periodo mixto
        $wherePeriodo = function ($q) use ($mes, $anio, $mesVencido, $anioVencido) {
            $q->where(function ($inner) use ($mes, $anio) {
                // Independientes → mes actual
                $inner->where('p.tipo_modalidad_id', 11)
                      ->where('p.mes_plano',  $mes)
                      ->where('p.anio_plano', $anio);
            })->orWhere(function ($inner) use ($mesVencido, $anioVencido) {
                // Todos los demas → mes vencido (mes_pago - 1)
                $inner->where('p.tipo_modalidad_id', '<>', 11)
                      ->where('p.mes_plano',  $mesVencido)
                      ->where('p.anio_plano', $anioVencido);
            });
        };

        // ── Conteo de planos por RS para el periodo mixto ────────────────
        // Cuenta TODOS los planos del periodo sin importar el n_plano actual de la RS.
        // (Si el n_plano se avanzó, los planos del periodo anterior siguen visibles en el select.)
        $cantPorRs = DB::table('planos AS p')
            ->where('p.aliado_id', $aliadoId)
            ->whereNull('p.deleted_at')
            ->where('p.tipo_reg', 'planilla')
            ->where($wherePeriodo)
            ->groupBy('p.razon_social_id')
            ->select('p.razon_social_id', DB::raw('COUNT(*) AS cant'))
            ->pluck('cant', 'razon_social_id');

        $razonesSociales = RazonSocial::where('aliado_id', $aliadoId)
            ->orderByRaw("CASE WHEN LOWER(estado) IN ('activo','activa','1','si','yes') THEN 0 ELSE 1 END")
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
                ->join('facturas AS f', 'f.id', '=', 'p.factura_id')
                ->join('contratos AS c', 'c.id', '=', 'p.contrato_id')
                ->leftJoin('clientes AS cl', 'cl.cedula', '=', 'p.no_identifi')
                ->leftJoin('empresas AS em', 'em.id', '=', 'cl.cod_empresa')
                ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 'p.razon_social_id')
                ->leftJoin('tipo_modalidad AS tm', 'tm.id', '=', 'p.tipo_modalidad_id')
                // Operador asignado al cliente (para RS independientes)
                ->leftJoin('operadores_planilla AS op_cl', 'op_cl.id', '=', 'cl.operador_planilla_id')
                ->where('p.aliado_id', $aliadoId)
                ->whereNull('p.deleted_at')
                ->where('p.tipo_reg', 'planilla')
                ->where('p.razon_social_id', $razonSocialId)
                ->where($wherePeriodo)
                ->select([
                    'p.id',
                    'p.tipo_reg',
                    'p.no_identifi',
                    'p.primer_nombre', 'p.segundo_nombre',
                    'p.primer_ape', 'p.segundo_ape',
                    'p.n_plano',
                    'p.numero_planilla',
                    'p.mes_plano', 'p.anio_plano',
                    'p.num_dias',
                    'p.fecha_ing', 'p.fecha_ret',
                    'p.cod_eps', 'p.nombre_eps',
                    'p.cod_afp', 'p.nombre_afp',
                    'p.cod_arl', 'p.nombre_arl',
                    'p.cod_caja', 'p.nombre_caja',
                    'p.nivel_riesgo',
                    'p.razon_social_id',
                    'p.razon_social',
                    'p.tipo_modalidad_id',
                    'p.tipo_p',
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

            $planos = $query->orderBy('rs.razon_social')->orderBy('p.primer_ape')->get();

            // ── Modalidades disponibles en periodo+RS ──────────────────────
            $modalidadesDisponIds = DB::table('planos AS p')
                ->join('tipo_modalidad AS tm', 'tm.id', '=', 'p.tipo_modalidad_id')
                ->where('p.aliado_id', $aliadoId)
                ->whereNull('p.deleted_at')
                ->where('p.tipo_reg', 'planilla')
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
        $planoPagado     = false;
        $numeroPlanillaPagado = null;
        if ($planos->count() > 0) {
            $conPlanilla  = $planos->whereNotNull('numero_planilla')->where('numero_planilla', '!=', '')->count();
            $planoPagado  = ($conPlanilla === $planos->count());
            if ($planoPagado) {
                $numeroPlanillaPagado = $planos->first()->numero_planilla;
            }
        }

        // Bancos (para modal confirmar pago)
        $bancos = BancoCuenta::where('aliado_id', $aliadoId)->where('activo', true)->get();

        // Operadores: respetar configuración del aliado (pivot aliado_operadores_planilla)
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

        return view('admin.planos.index', compact(
            'planos', 'razonesSociales', 'tiposModalidad', 'modalidadesDispon',
            'cantPorRs',
            'anio', 'mes', 'mesVencido', 'anioVencido',
            'razonSocialId', 'nPlanoFiltro', 'modalidadesIds',
            'rsSeleccionada', 'nPlanoActual',
            'totalSS', 'totalAdmon', 'totalPersonas',
            'bancos', 'operadores',
            'planoPagado', 'numeroPlanillaPagado',
        ) + [
            // Indica si la RS seleccionada es de tipo independiente:
            // en ese caso el pago se confirma POR PERSONA, no por planilla completa.
            'esIndependiente' => (bool) ($rsSeleccionada?->es_independiente),
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

    // ── 3. Actualizar N_PLANO en Razon Social ─────────────────────────
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

        $updated = DB::table('planos')
            ->where('id', $id)
            ->where('aliado_id', $aliadoId)
            ->whereNull('deleted_at')
            ->update(['n_plano' => $nPlano]);

        if (!$updated) {
            return response()->json(['ok' => false, 'mensaje' => 'Registro no encontrado o sin cambios.'], 404);
        }

        return response()->json([
            'ok'      => true,
            'mensaje' => "Registro movido al plano P{$nPlano}.",
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
        $tiposModalidad = (array) $request->input('tipos_modalidad', []);
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
                    'tipos_modalidad' => $tiposModalidad,
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
        ]);


        $rs = RazonSocial::where('aliado_id', $aliadoId)
            ->findOrFail($validated['razon_social_id']);

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
                'aliado_id'       => $aliadoId,
                'usuario_id'      => $usuarioId,
                'cuadre_id'       => null,
                'fecha'           => today(),
                'tipo'            => 'pago_planilla',
                'descripcion'     => $descripcion,
                'pagado_a'        => $validated['operador'],
                'forma_pago'      => $validated['forma_pago'],
                'banco_origen_id' => $validated['forma_pago'] !== 'efectivo'
                    ? ($validated['banco_id'] ?? null)
                    : null,
                'valor'           => $validated['valor'],
                'observacion'     => $validated['observacion'],
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
            // Independientes (tipo_modalidad_id=11) → mes real = mes_pago
            // Dependientes y demas               → mes real = mes_pago - 1
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
                    ->where('tipo_reg', 'planilla')
                    ->where('razon_social_id', $validated['razon_social_id'])
                    ->where('n_plano', $validated['n_plano'])
                    ->where(function ($q) use ($mesPago, $anioPago, $mesVencido, $anioVencido) {
                        $q->where(function ($i) use ($mesPago, $anioPago) {
                            $i->where('tipo_modalidad_id', 11)
                              ->where('mes_plano',  $mesPago)
                              ->where('anio_plano', $anioPago);
                        })->orWhere(function ($i) use ($mesVencido, $anioVencido) {
                            $i->where('tipo_modalidad_id', '<>', 11)
                              ->where('mes_plano',  $mesVencido)
                              ->where('anio_plano', $anioVencido);
                        });
                    });

                if (!empty($validated['tipos_modalidad'])) {
                    $queryUpdate->whereIn('tipo_modalidad_id', $validated['tipos_modalidad']);
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
}
