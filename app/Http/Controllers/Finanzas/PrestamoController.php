<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Finanzas\Prestamo;
use App\Models\Finanzas\Gasto;
use App\Models\Finanzas\CategoriaGasto;
use App\Services\Finanzas\PrestamoLiquidacionService;
use App\Services\Finanzas\FinanzasWhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PrestamoController extends Controller
{
    protected PrestamoLiquidacionService $liquidacionService;
    protected FinanzasWhatsappService $whatsappService;

    public function __construct(
        PrestamoLiquidacionService $liquidacionService,
        FinanzasWhatsappService $whatsappService
    ) {
        $this->middleware('auth');
        $this->liquidacionService = $liquidacionService;
        $this->whatsappService = $whatsappService;
    }

    /**
     * Lista los préstamos normales (excluye cuenta corriente).
     */
    public function index(Request $request)
    {
        $estado = $request->input('estado', 'activo');

        $query = Prestamo::where('user_id', Auth::id())
            ->where('es_cuenta_corriente', false);

        if ($estado === 'activo') {
            $query->whereIn('estado', ['activo', 'mora']);
        } elseif ($estado === 'pagado') {
            $query->where('estado', 'pagado');
        }

        $prestamos = $query->orderBy('fecha_desembolso', 'desc')->get();

        return view('finanzas.prestamos.index', compact('prestamos', 'estado'));
    }

    /**
     * Formulario de creación de préstamos.
     */
    public function create()
    {
        return view('finanzas.prestamos.create');
    }

    /**
     * Registra un nuevo préstamo.
     * Crea el préstamo en finanzas_prestamos y registra el egreso correspondiente en finanzas_gastos.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre_deudor' => 'required|string|max:100',
            'cedula_deudor' => 'nullable|string|max:20',
            'telefono_deudor' => 'nullable|string|max:20',
            'monto_original' => 'required|numeric|min:1',
            'tasa_interes_mensual' => 'required|numeric|min:0|max:100',
            'fecha_desembolso' => 'required|date',
            'dias_mora_alerta' => 'required|integer|min:1',
            'alertas_activas' => 'nullable|boolean',
            'es_cuenta_corriente' => 'nullable|boolean',
            'cuenta_corriente_grupo' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string',
            'soporte' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240', // 10MB max
        ]);

        $user = Auth::user();
        $soportePath = null;

        if ($request->hasFile('soporte')) {
            $soportePath = $request->file('soporte')->store('finanzas/prestamos', 'local');
        }

        $esCC = $request->has('es_cuenta_corriente') ? (bool) $request->es_cuenta_corriente : false;

        $prestamo = Prestamo::create([
            'user_id' => $user->id,
            'nombre_deudor' => $request->nombre_deudor,
            'cedula_deudor' => $request->cedula_deudor,
            'telefono_deudor' => $request->telefono_deudor,
            'monto_original' => $request->monto_original,
            'tasa_interes_mensual' => $request->tasa_interes_mensual,
            'fecha_desembolso' => $request->fecha_desembolso,
            'ultimo_corte' => $request->fecha_desembolso,
            'saldo_actual' => $request->monto_original,
            'estado' => 'activo',
            'dias_mora_alerta' => $request->dias_mora_alerta,
            'alertas_activas' => $request->has('alertas_activas') ? (bool) $request->alertas_activas : true,
            'soporte_path' => $soportePath,
            'descripcion' => $request->descripcion,
            'observaciones' => $request->observaciones,
            'es_cuenta_corriente' => $esCC,
            'cuenta_corriente_grupo' => $request->cuenta_corriente_grupo,
        ]);

        // Registrar el movimiento de desembolso inicial en el historial
        $this->liquidacionService->registrarDesembolso($prestamo);

        // Crear una categoría temporal de Gasto "Otros" o similar si no existe,
        // o mapear como tipo_movimiento = 'prestamo' en la tabla de gastos
        $categoriaOtros = CategoriaGasto::where('user_id', $user->id)->where('nombre', 'Otros')->first();
        $catId = $categoriaOtros ? $categoriaOtros->id : 1; // Fallback al id 1 de categoría

        Gasto::create([
            'user_id' => $user->id,
            'categoria_id' => $catId,
            'fecha' => $request->fecha_desembolso,
            'monto' => $request->monto_original,
            'descripcion' => "Préstamo otorgado a: {$request->nombre_deudor}",
            'tipo_movimiento' => 'prestamo',
            'es_patrimonio' => false,
            'patrimonio_id' => null,
        ]);

        if ($esCC) {
            return redirect()->route('finanzas.prestamos.cuenta-corriente')->with('success', 'Préstamo registrado en Cuenta Corriente.');
        }

        return redirect()->route('finanzas.prestamos.index')->with('success', 'Préstamo registrado y salida creada con éxito.');
    }

    /**
     * Detalle completo de un préstamo (tabla de liquidación, historial de pagos, soporte).
     */
    public function show($id)
    {
        $prestamo = Prestamo::where('user_id', Auth::id())
            ->with(['movimientos' => function ($q) {
                $q->orderBy('fecha', 'desc')->orderBy('id', 'desc');
            }])
            ->findOrFail($id);

        return view('finanzas.prestamos.show', compact('prestamo'));
    }

    /**
     * Muestra formulario de edición.
     */
    public function edit($id)
    {
        $prestamo = Prestamo::where('user_id', Auth::id())->findOrFail($id);
        return view('finanzas.prestamos.edit', compact('prestamo'));
    }

    /**
     * Actualiza los datos del préstamo.
     */
    public function update(Request $request, $id)
    {
        $prestamo = Prestamo::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'nombre_deudor' => 'required|string|max:100',
            'cedula_deudor' => 'nullable|string|max:20',
            'telefono_deudor' => 'nullable|string|max:20',
            'tasa_interes_mensual' => 'required|numeric|min:0|max:100',
            'dias_mora_alerta' => 'required|integer|min:1',
            'alertas_activas' => 'nullable|boolean',
            'estado' => 'required|string|in:activo,pagado,mora,castigado',
            'observaciones' => 'nullable|string',
            'soporte' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $data = $request->only('nombre_deudor', 'cedula_deudor', 'telefono_deudor', 'tasa_interes_mensual', 'dias_mora_alerta', 'estado', 'observaciones');
        $data['alertas_activas'] = $request->has('alertas_activas') ? (bool) $request->alertas_activas : true;

        if ($request->hasFile('soporte')) {
            // Eliminar soporte anterior si existía
            if ($prestamo->soporte_path) {
                Storage::delete($prestamo->soporte_path);
            }
            $data['soporte_path'] = $request->file('soporte')->store('finanzas/prestamos', 'local');
        }

        $prestamo->update($data);

        return redirect()->route('finanzas.prestamos.show', $prestamo->id)->with('success', 'Datos del préstamo actualizados.');
    }

    /**
     * Registra un abono/pago de intereses o capital.
     */
    public function registrarPago(Request $request, $id)
    {
        $prestamo = Prestamo::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'monto' => 'required|numeric|min:1',
            'fecha' => 'required|date',
            'observacion' => 'nullable|string|max:255',
        ]);

        $res = $this->liquidacionService->registrarPago($prestamo, $request->monto, $request->fecha, $request->observacion);

        if ($res['success']) {
            return redirect()->route('finanzas.prestamos.show', $prestamo->id)
                ->with('success', "Pago registrado con éxito. Se abonaron \${$res['abono_interes']} a intereses y \${$res['abono_capital']} a capital.");
        }

        return redirect()->route('finanzas.prestamos.show', $prestamo->id)->with('error', $res['message']);
    }

    /**
     * Realiza el corte y liquidación mensual de intereses manualmente a la fecha seleccionada.
     */
    public function liquidarMes(Request $request, $id)
    {
        $prestamo = Prestamo::where('user_id', Auth::id())->findOrFail($id);
        $fecha = $request->input('fecha', now()->toDateString());

        $interes = $this->liquidacionService->liquidarPeriodo($prestamo, $fecha);

        return redirect()->route('finanzas.prestamos.show', $prestamo->id)
            ->with('success', "Liquidación ejecutada. Intereses liquidados e incorporados al saldo: \${$interes}");
    }

    /**
     * Envía una notificación manual de cobro al deudor mediante WhatsApp.
     */
    public function enviarWhatsapp($id)
    {
        $prestamo = Prestamo::where('user_id', Auth::id())->findOrFail($id);

        $res = $this->whatsappService->enviarRecordatorioPrestamo($prestamo);

        if ($res['ok']) {
            return redirect()->route('finanzas.prestamos.show', $prestamo->id)->with('success', $res['message']);
        }

        return redirect()->route('finanzas.prestamos.show', $prestamo->id)->with('error', $res['message'] . ' Detalles: ' . ($res['error'] ?? 'ninguno'));
    }

    /**
     * Habilita o deshabilita los recordatorios del préstamo.
     */
    public function toggleAlertas($id)
    {
        $prestamo = Prestamo::where('user_id', Auth::id())->findOrFail($id);
        $prestamo->update(['alertas_activas' => !$prestamo->alertas_activas]);

        return redirect()->route('finanzas.prestamos.show', $prestamo->id)
            ->with('success', $prestamo->alertas_activas ? 'Recordatorios activados.' : 'Recordatorios desactivados.');
    }

    /**
     * Muestra la vista de "Cuenta Corriente" (servicios prestados al cliente habitual).
     */
    public function cuentaCorriente()
    {
        $prestamos = Prestamo::where('user_id', Auth::id())
            ->where('es_cuenta_corriente', true)
            ->orderBy('estado', 'asc')
            ->orderBy('fecha_desembolso', 'desc')
            ->get();

        // Agruparlos por su campo de grupo (usualmente el nombre del mes de trabajo o proyecto)
        $grupos = $prestamos->groupBy('cuenta_corriente_grupo');
        $saldoTotalPendiente = $prestamos->whereIn('estado', ['activo', 'mora'])->sum('saldo_actual');

        return view('finanzas.prestamos.cuenta-corriente', compact('grupos', 'saldoTotalPendiente'));
    }
}
