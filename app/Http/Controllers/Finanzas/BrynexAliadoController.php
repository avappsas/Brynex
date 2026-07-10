<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Aliado;
use App\Models\Finanzas\BrynexPago;
use App\Models\Finanzas\BrynexRecibo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BrynexAliadoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Muestra la cuadrícula de cobros a los aliados de Brynex.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $anio = $request->input('anio', now()->year);

        // Obtener IDs de aliados con pagos registrados en este año (sin filtrar por user_id para visibilidad global)
        $aliadoIdsConPagos = BrynexPago::where('anio', $anio)
            ->pluck('aliado_id')
            ->toArray();

        $startDate = \Carbon\Carbon::create($anio, 1, 1)->startOfYear()->toDateString();
        $endDate = \Carbon\Carbon::create($anio, 12, 31)->endOfYear()->toDateString();

        // Obtener aliados activos desde la base de datos principal que cumplan con la vigencia o tengan pagos
        $aliados = Aliado::activos()
            ->where(function($query) use ($startDate, $endDate) {
                $query->where(function($q) use ($endDate) {
                    $q->whereNull('brynex_fecha_inicio')
                      ->orWhere('brynex_fecha_inicio', '<=', $endDate);
                })
                ->where(function($q) use ($startDate) {
                    $q->whereNull('brynex_fecha_fin')
                      ->orWhere('brynex_fecha_fin', '>=', $startDate);
                });
            })
            ->orWhereIn('id', $aliadoIdsConPagos)
            ->orderBy('nombre')
            ->get();

        // Obtener pagos registrados de este año con sus recibos y soportes (sin filtrar por user_id para visibilidad global)
        $pagos = BrynexPago::with('recibo')
            ->where('anio', $anio)
            ->get()
            ->groupBy(['aliado_id', 'mes']);

        return view('finanzas.brynex_aliados.index', compact('aliados', 'pagos', 'anio'));
    }

    /**
     * Permite agregar un nuevo aliado manualmente.
     */
    public function storeAliado(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:150|unique:aliados,nombre',
        ], [
            'nombre.unique' => 'El aliado ya existe en la base de datos.',
        ]);

        Aliado::create([
            'nombre' => $request->nombre,
            'razon_social' => $request->nombre,
            'nit' => 'TEMP-' . substr(md5($request->nombre), 0, 10),
            'activo' => true,
            'afiliaciones_brynex' => false,
        ]);

        return redirect()->back()->with('success', 'Aliado agregado con éxito.');
    }

    /**
     * Permite actualizar la vigencia e información de un aliado.
     */
    public function updateAliado(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:aliados,id',
            'nombre' => 'required|string|max:150|unique:aliados,nombre,' . $request->id,
            'nit' => 'required|string|max:50',
            'brynex_fecha_inicio' => 'nullable|date',
            'brynex_fecha_fin' => 'nullable|date',
        ]);

        $aliado = Aliado::findOrFail($request->id);
        $aliado->update([
            'nombre' => $request->nombre,
            'razon_social' => $request->nombre,
            'nit' => $request->nit,
            'brynex_fecha_inicio' => $request->brynex_fecha_inicio,
            'brynex_fecha_fin' => $request->brynex_fecha_fin,
        ]);

        return redirect()->back()->with('success', 'Aliado actualizado con éxito.');
    }

    /**
     * Guarda el cobro de un aliado para un mes/año en caliente (AJAX).
     */
    public function saveCell(Request $request)
    {
        $request->validate([
            'aliado_id' => 'required|integer',
            'anio' => 'required|integer',
            'mes' => 'required|integer|between:1,12',
            'monto' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();

        $pago = BrynexPago::updateOrCreate(
            [
                'aliado_id' => $request->aliado_id,
                'anio' => $request->anio,
                'mes' => $request->mes,
            ],
            [
                'user_id' => $user->id,
                'monto' => $request->monto,
                'observacion' => $request->input('observacion', 'Modificado manualmente desde grilla'),
            ]
        );

        return response()->json([
            'success' => true,
            'pago' => $pago
        ]);
    }

    /**
     * Registra un recibo de pago real y distribuye el monto entre los meses seleccionados.
     */
    public function registrarRecibo(Request $request)
    {
        $request->validate([
            'aliado_id' => 'required|integer|exists:aliados,id',
            'fecha_pago' => 'required|date',
            'banco' => 'required|string|max:100',
            'soporte' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'observacion' => 'nullable|string|max:500',
            'anio' => 'required|integer',
            'pago_items' => 'required|array|min:1',
            'pago_items.*.mes' => 'required|integer|between:1,12',
            'pago_items.*.monto' => 'required|string',
            'pago_items.*.estado' => 'required|string|in:completo,pendiente',
            'pago_items.*.saldo_pendiente' => 'nullable|string',
        ]);

        $user = Auth::user();
        $soportePath = null;

        if ($request->hasFile('soporte')) {
            $soportePath = $request->file('soporte')->store('finanzas/brynex_soportes', 'local');
        }

        // Calcular monto total neto sumando los montos de cada mes
        $montoTotal = 0;
        $items = [];
        foreach ($request->pago_items as $item) {
            $montoLimpio = (float) preg_replace('/\D/', '', $item['monto']);
            $saldoLimpio = isset($item['saldo_pendiente']) ? (float) preg_replace('/\D/', '', $item['saldo_pendiente']) : 0;
            
            $montoTotal += $montoLimpio;
            $items[] = [
                'mes' => (int) $item['mes'],
                'monto' => $montoLimpio,
                'estado' => $item['estado'],
                'saldo_pendiente' => $saldoLimpio,
            ];
        }

        // Crear recibo de pago
        $recibo = BrynexRecibo::create([
            'user_id' => $user->id,
            'monto_total' => $montoTotal,
            'fecha_pago' => $request->fecha_pago,
            'soporte_path' => $soportePath,
            'banco' => $request->banco,
            'observacion' => $request->observacion,
        ]);

        // Registrar los cobros individuales en finanzas_brynex_pagos
        foreach ($items as $item) {
            BrynexPago::create([
                'aliado_id' => $request->aliado_id,
                'anio' => $request->anio,
                'mes' => $item['mes'],
                'user_id' => $user->id,
                'recibo_id' => $recibo->id,
                'monto' => $item['monto'],
                'estado' => $item['estado'],
                'saldo_pendiente' => $item['saldo_pendiente'],
                'observacion' => "Pago registrado el {$request->fecha_pago} en {$request->banco}. Estado: " . ($item['estado'] === 'pendiente' ? 'Abono parcial' : 'Completo') . ".",
            ]);
        }

        return redirect()->back()->with('success', 'Recibo de pago registrado y distribuido con éxito.');
    }

    /**
     * Elimina un recibo de pago, borra su archivo de soporte y sus pagos asociados.
     */
    public function deleteRecibo($id)
    {
        $recibo = BrynexRecibo::where('user_id', Auth::id())->findOrFail($id);

        if ($recibo->soporte_path) {
            Storage::disk('local')->delete($recibo->soporte_path);
        }

        // Eliminar los pagos asociados
        BrynexPago::where('recibo_id', $recibo->id)->delete();

        $recibo->delete();

        return redirect()->back()->with('success', 'Recibo de pago y cobros mensuales asociados eliminados con éxito.');
    }

    /**
     * Descarga de forma segura el archivo de soporte de un recibo.
     */
    public function descargarSoporte(BrynexRecibo $recibo)
    {
        if ($recibo->user_id !== Auth::id()) {
            abort(403, 'No autorizado.');
        }

        if (!$recibo->soporte_path || !Storage::disk('local')->exists($recibo->soporte_path)) {
            abort(404, 'Archivo de soporte no encontrado.');
        }

        return Storage::disk('local')->download($recibo->soporte_path);
    }
}
