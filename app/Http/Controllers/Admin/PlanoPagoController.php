<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Plano, RazonSocial, TipoModalidad, BancoCuenta, Gasto, OperadorPlanilla, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PlanoPagoController extends Controller
{
    // ── 1. Vista principal con filtros ─────────────────────────────────
    public function index(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        // ── Filtros ─────────────────────────────────────────────────────
        $anio             = (int) $request->input('anio',   now()->year);
        $mes              = (int) $request->input('mes',    now()->month);
        $razonSocialId    = $request->input('razon_social_id');
        $nPlanoFiltro     = $request->input('n_plano');
        $modalidadesIds   = $request->input('tipos_modalidad', []);

        // Selects de ayuda
        $razonesSociales = RazonSocial::where('aliado_id', $aliadoId)
            ->orderByRaw("CASE WHEN LOWER(estado) IN ('activo','activa','1','si','yes') THEN 0 ELSE 1 END")
            ->orderBy('razon_social')
            ->get(['id', 'razon_social', 'n_plano', 'mes_pagos', 'anio_pagos', 'estado']);

        $tiposModalidad = TipoModalidad::where('activo', true)
            ->where('id', '<>', -100)
            ->orderBy('orden')
            ->get();

        // N_PLANO actual de la RS seleccionada
        $nPlanoActual = null;
        $rsSeleccionada = null;
        if ($razonSocialId) {
            $rsSeleccionada = RazonSocial::find($razonSocialId);
            $nPlanoActual   = $rsSeleccionada?->n_plano;
            if (!$nPlanoFiltro) {
                $nPlanoFiltro = $nPlanoActual;
            }
        }

        // ── Consulta principal ──────────────────────────────────────────
        // Solo traemos planos de tipo 'planilla' del período seleccionado.
        // El "período visible" es el mes de pago (mes facturado),
        // los planos dependientes tienen mes_plano = mes-1 internamente,
        // pero el usuario filtra por el mes de PAGO (= mes_plano + 1 para dependientes).
        // Para simplificar: mostramos planos donde mes_plano = mes seleccionado.
        $query = DB::table('planos AS p')
            ->join('facturas AS f', 'f.id', '=', 'p.factura_id')
            ->join('contratos AS c', 'c.id', '=', 'p.contrato_id')
            ->leftJoin('clientes AS cl', 'cl.cedula', '=', 'p.no_identifi')
            ->leftJoin('empresas AS em', 'em.id', '=', 'cl.cod_empresa')
            ->leftJoin('razones_sociales AS rs', 'rs.id', '=', 'p.razon_social_id')
            ->leftJoin('tipo_modalidad AS tm', 'tm.id', '=', 'p.tipo_modalidad_id')
            ->where('p.aliado_id', $aliadoId)
            ->whereNull('p.deleted_at')
            ->where('p.tipo_reg', 'planilla')
            ->where('p.mes_plano', $mes)
            ->where('p.anio_plano', $anio)
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
                'c.envio_planilla',
                // Desde cliente
                'cl.fecha_nacimiento',
                // Empresa del cliente (via clientes.cod_empresa → empresas.id)
                'em.empresa AS nombre_empresa',
                // Tipo modalidad
                'tm.tipo_modalidad AS tipo_modal_nombre',
            ]);

        if ($razonSocialId) {
            $query->where('p.razon_social_id', $razonSocialId);
        }

        if ($nPlanoFiltro) {
            $query->where('p.n_plano', $nPlanoFiltro);
        }

        if (!empty($modalidadesIds)) {
            $query->whereIn('p.tipo_modalidad_id', $modalidadesIds);
        }

        $planos = $query->orderBy('rs.razon_social')->orderBy('p.primer_ape')->get();

        // ── Calcular edad y nombre completo ─────────────────────────────
        $hoy = Carbon::today();
        $planos = $planos->map(function ($row) use ($hoy) {
            $row->nombre_completo = trim(
                $row->primer_nombre . ' ' . $row->segundo_nombre . ' ' .
                $row->primer_ape   . ' ' . $row->segundo_ape
            );
            $row->edad = $row->fecha_nacimiento
                ? $hoy->diffInYears(Carbon::parse($row->fecha_nacimiento))
                : null;
            return $row;
        });

        // ── Totales ─────────────────────────────────────────────────────
        $totalSS     = $planos->sum('total_ss');
        $totalAdmon  = $planos->sum('admon');
        $totalPersonas = $planos->count();

        // Bancos (para modal confirmar pago)
        $bancos     = BancoCuenta::where('aliado_id', $aliadoId)->where('activo', true)->get();
        $operadores = OperadorPlanilla::where(function ($q) use ($aliadoId) {
                $q->whereNull('aliado_id')->orWhere('aliado_id', $aliadoId);
            })
            ->where('activo', true)
            ->orderBy('orden')
            ->get();

        return view('admin.planos.index', compact(
            'planos', 'razonesSociales', 'tiposModalidad',
            'anio', 'mes', 'razonSocialId', 'nPlanoFiltro', 'modalidadesIds',
            'rsSeleccionada', 'nPlanoActual',
            'totalSS', 'totalAdmon', 'totalPersonas',
            'bancos', 'operadores'
        ));
    }

    // ── 2. API: Razón Social → N_PLANO actual ──────────────────────────
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

    // ── 3. Actualizar N_PLANO en Razón Social ─────────────────────────
    public function actualizarNPlano(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $validated = $request->validate([
            'razon_social_id' => 'required|integer',
            'n_plano'         => 'required|integer|min:1',
        ]);

        $rs = RazonSocial::where('aliado_id', $aliadoId)
            ->findOrFail($validated['razon_social_id']);

        $rs->update(['n_plano' => $validated['n_plano']]);

        return response()->json([
            'ok'     => true,
            'n_plano'=> $rs->n_plano,
            'mensaje'=> "N_PLANO actualizado a {$rs->n_plano} para {$rs->razon_social}",
        ]);
    }

    // ── 4. Descargar archivo vacío (TXT o XLSX) ───────────────────────
    public function descargar(Request $request)
    {
        $aliadoId = session('aliado_id_activo');
        $formato  = $request->input('formato', 'txt'); // 'txt' | 'xlsx'

        // Nombre del archivo: RazonSocial_Mes_Año_NPlano
        $razonSocialId = $request->input('razon_social_id');
        $mes           = $request->input('mes', now()->month);
        $anio          = $request->input('anio', now()->year);
        $nPlano        = $request->input('n_plano', 1);
        $rsNombre      = 'SIN_RS';

        if ($razonSocialId) {
            $rs = RazonSocial::find($razonSocialId);
            if ($rs) {
                $rsNombre = preg_replace('/[^A-Za-z0-9_\-]/', '_', $rs->razon_social);
            }
        }

        $nombreBase = "{$rsNombre}_{$mes}_{$anio}_P{$nPlano}";

        if ($formato === 'xlsx') {
            // XLSX vacío minimal (archivo ZIP con estructura XLSX)
            $xlsxBase64 = 'UEsDBBQAAAAIAAAAAAAAAAAAAAAAAAAAAAAUAAAAeGwvc2hlZXRzL3NoZWV0MS54bWxQSwECFAAUAAAACAAA' .
                'AAAAAAAAAAAAAAAAAAAAFAAAAHhsL3NoZWV0cy9zaGVldDEueG1sUEsFBgAAAAABAAEAQgAAADYAAAAAAA==';
            $contenido = base64_decode($xlsxBase64);
            $mime      = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $extension = 'xlsx';
        } else {
            $contenido = '';
            $mime      = 'text/plain';
            $extension = 'txt';
        }

        return response($contenido, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => "attachment; filename=\"{$nombreBase}.{$extension}\"",
        ]);
    }

    // ── 5. Confirmar Pago ─────────────────────────────────────────────
    public function confirmarPago(Request $request)
    {
        $aliadoId  = session('aliado_id_activo');
        $usuarioId = Auth::id();

        $validated = $request->validate([
            'razon_social_id'  => 'required|integer',
            'mes_plano'        => 'required|integer|between:1,12',
            'anio_plano'       => 'required|integer',
            'n_plano'          => 'required|integer|min:1',
            'tipos_modalidad'  => 'nullable|array',
            'operador'         => 'required|string|max:100',
            'numero_planilla'  => 'required|string|max:80',
            'valor'            => 'required|integer|min:1',
            'forma_pago'       => 'required|in:consignacion,transferencia,pse,efectivo',
            'banco_id'         => 'required|integer',
            'observacion'      => 'nullable|string|max:1000',
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
                . "Período: {$mesNombre} {$validated['anio_plano']} | "
                . "Operador: {$validated['operador']} | "
                . "Planilla: {$validated['numero_planilla']}";

            $gasto = Gasto::create([
                'aliado_id'       => $aliadoId,
                'usuario_id'      => $usuarioId,
                'cuadre_id'       => null,       // no requiere cuadre para pagos planilla
                'fecha'           => today(),
                'tipo'            => 'pago_planilla',
                'descripcion'     => $descripcion,
                'pagado_a'        => $validated['operador'],
                'forma_pago'      => $validated['forma_pago'] === 'consignacion'
                    ? 'transferencia_bancaria'
                    : $validated['forma_pago'],
                'banco_origen_id' => $validated['banco_id'],
                'valor'           => $validated['valor'],
                'observacion'     => $validated['observacion'],
            ]);

            // ── b) Actualizar numero_planilla en todos los planos del filtro ─
            $queryUpdate = DB::table('planos')
                ->where('aliado_id', $aliadoId)
                ->whereNull('deleted_at')
                ->where('tipo_reg', 'planilla')
                ->where('razon_social_id', $validated['razon_social_id'])
                ->where('mes_plano', $validated['mes_plano'])
                ->where('anio_plano', $validated['anio_plano'])
                ->where('n_plano', $validated['n_plano']);

            if (!empty($validated['tipos_modalidad'])) {
                $queryUpdate->whereIn('tipo_modalidad_id', $validated['tipos_modalidad']);
            }

            $cantActualizados = $queryUpdate->update([
                'numero_planilla' => $validated['numero_planilla'],
                'updated_at'      => now(),
            ]);

            DB::commit();

            return response()->json([
                'ok'               => true,
                'mensaje'          => "Pago confirmado. Se actualizaron {$cantActualizados} registros con la planilla {$validated['numero_planilla']}.",
                'gasto_id'         => $gasto->id,
                'cant_actualizados'=> $cantActualizados,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ok'     => false,
                'mensaje'=> 'Error al confirmar el pago: ' . $e->getMessage(),
            ], 500);
        }
    }
}
