<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Finanzas\AppLiderAliado;
use App\Models\Finanzas\AppLiderPago;
use App\Models\Finanzas\AppLiderRecibo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AppLiderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Muestra la cuadrícula de cobros a los aliados de Otras App (App Líderes).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $anio = $request->input('anio', now()->year);

        // Obtener IDs de aliados con pagos registrados en este año
        $aliadoIdsConPagos = AppLiderPago::where('user_id', $user->id)
            ->where('anio', $anio)
            ->pluck('app_lider_aliado_id')
            ->toArray();

        $startDate = \Carbon\Carbon::create($anio, 1, 1)->startOfYear()->toDateString();
        $endDate = \Carbon\Carbon::create($anio, 12, 31)->endOfYear()->toDateString();

        // Obtener aliados activos que cumplan con la vigencia o tengan pagos
        $aliados = AppLiderAliado::where('user_id', $user->id)
            ->where(function($query) use ($startDate, $endDate) {
                $query->where(function($q) use ($endDate) {
                    $q->whereNull('fecha_inicio')
                      ->orWhere('fecha_inicio', '<=', $endDate);
                })
                ->where(function($q) use ($startDate) {
                    $q->whereNull('fecha_fin')
                      ->orWhere('fecha_fin', '>=', $startDate);
                });
            })
            ->orWhereIn('id', $aliadoIdsConPagos)
            ->orderBy('nombre')
            ->get();

        // Obtener pagos registrados de este año con sus recibos y soportes
        $pagos = AppLiderPago::with('recibo')
            ->where('user_id', $user->id)
            ->where('anio', $anio)
            ->get()
            ->groupBy(['app_lider_aliado_id', 'mes']);

        return view('finanzas.app_lideres.index', compact('aliados', 'pagos', 'anio'));
    }

    /**
     * Permite agregar un nuevo aliado manualmente.
     */
    public function storeAliado(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:150',
        ]);

        AppLiderAliado::create([
            'user_id' => Auth::id(),
            'nombre' => $request->nombre,
            'valor_mensual' => 0, // se establece en 0 o el valor que configure
            'fecha_inicio' => now()->toDateString(),
            'activo' => true,
        ]);

        return redirect()->back()->with('success', 'Aliado de Otras App agregado con éxito.');
    }

    /**
     * Permite actualizar la vigencia e información de un aliado.
     */
    public function updateAliado(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:finanzas_app_lideres_aliados,id',
            'nombre' => 'required|string|max:150',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
        ]);

        $aliado = AppLiderAliado::where('user_id', Auth::id())->findOrFail($request->id);
        $aliado->update([
            'nombre' => $request->nombre,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
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

        $pago = AppLiderPago::updateOrCreate(
            [
                'app_lider_aliado_id' => $request->aliado_id,
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
            'aliado_id' => 'required|integer|exists:finanzas_app_lideres_aliados,id',
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
            $soportePath = $request->file('soporte')->store('finanzas/app_lideres_soportes', 'local');
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
        $recibo = AppLiderRecibo::create([
            'user_id' => $user->id,
            'monto_total' => $montoTotal,
            'fecha_pago' => $request->fecha_pago,
            'soporte_path' => $soportePath,
            'banco' => $request->banco,
            'observacion' => $request->observacion,
        ]);

        // Registrar los cobros individuales en finanzas_app_lideres_pagos
        foreach ($items as $item) {
            AppLiderPago::updateOrCreate(
                [
                    'app_lider_aliado_id' => $request->aliado_id,
                    'anio' => $request->anio,
                    'mes' => $item['mes'],
                ],
                [
                    'user_id' => $user->id,
                    'recibo_id' => $recibo->id,
                    'monto' => $item['monto'],
                    'estado' => $item['estado'],
                    'saldo_pendiente' => $item['saldo_pendiente'],
                    'observacion' => "Pago registrado el {$request->fecha_pago} en {$request->banco}. Estado: " . ($item['estado'] === 'pendiente' ? 'Abono parcial' : 'Completo') . ".",
                ]
            );
        }

        return redirect()->back()->with('success', 'Recibo de pago registrado y distribuido con éxito.');
    }

    /**
     * Elimina un recibo de pago, borra su archivo de soporte y sus pagos asociados.
     */
    public function deleteRecibo($id)
    {
        $recibo = AppLiderRecibo::where('user_id', Auth::id())->findOrFail($id);

        if ($recibo->soporte_path) {
            Storage::disk('local')->delete($recibo->soporte_path);
        }

        // Eliminar los pagos asociados
        AppLiderPago::where('recibo_id', $recibo->id)->delete();

        $recibo->delete();

        return redirect()->back()->with('success', 'Recibo de pago y cobros mensuales asociados eliminados con éxito.');
    }

    /**
     * Descarga de forma segura el archivo de soporte de un recibo.
     */
    public function descargarSoporte($id)
    {
        $recibo = AppLiderRecibo::where('user_id', Auth::id())->findOrFail($id);

        if (!$recibo->soporte_path || !Storage::disk('local')->exists($recibo->soporte_path)) {
            abort(404, 'Archivo de soporte no encontrado.');
        }

        return Storage::disk('local')->download($recibo->soporte_path);
    }
}
