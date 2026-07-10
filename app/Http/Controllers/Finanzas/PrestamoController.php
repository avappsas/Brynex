<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Finanzas\Prestamo;
use App\Models\Finanzas\PrestamoMovimiento;
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
        if ($this->isMobileDevice($request)) {
            return redirect()->route('finanzas.dashboard', ['tab' => 'deudas']);
        }

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

        // Asegurar la existencia de la categoría "Otros" para asociar el egreso del préstamo
        $categoriaOtros = CategoriaGasto::where('user_id', $user->id)->where('nombre', 'Otros')->first();
        if (!$categoriaOtros) {
            $categoriaOtros = CategoriaGasto::create([
                'user_id' => $user->id,
                'nombre' => 'Otros',
                'icono' => '📁',
                'orden' => 99,
            ]);
        }
        $catId = $categoriaOtros->id;

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
        $prestamo = Prestamo::where('user_id', Auth::id())->findOrFail($id);

        // Forzar recálculo para sincronizar saldos e inconsistencias de fechas de corte
        $this->recalcularSaldos($prestamo);

        // Cargar las relaciones ordenadas para visualización
        $prestamo->load(['movimientos' => function ($q) {
            $q->orderBy('fecha', 'desc')->orderBy('id', 'desc');
        }]);

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
            'soporte' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $soportePath = null;
        if ($request->hasFile('soporte')) {
            $soportePath = $request->file('soporte')->store('finanzas/prestamos', 'local');
        }

        $res = $this->liquidacionService->registrarPago($prestamo, $request->monto, $request->fecha, $request->observacion, $soportePath);

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

    /**
     * Actualiza un movimiento específico de un préstamo y recalcula los saldos.
     */
    public function updateMovimiento(Request $request, $id)
    {
        $movimiento = PrestamoMovimiento::findOrFail($id);
        $prestamo = Prestamo::where('user_id', Auth::id())->findOrFail($movimiento->prestamo_id);

        $request->validate([
            'fecha' => 'required|date',
            'monto' => 'required|numeric|min:0',
            'observacion' => 'nullable|string|max:255',
            'soporte' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240'
        ]);

        $movimiento->fecha = $request->input('fecha');
        $movimiento->monto = (float) $request->input('monto');
        $movimiento->observacion = $request->input('observacion');

        // Manejar archivo de soporte
        if ($request->hasFile('soporte')) {
            // Eliminar soporte previo si existe
            if ($movimiento->soporte_path) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($movimiento->soporte_path);
            }
            $path = $request->file('soporte')->store('finanzas/prestamos/soportes', 'local');
            $movimiento->soporte_path = $path;
        }

        if ($request->boolean('eliminar_soporte') && $movimiento->soporte_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($movimiento->soporte_path);
            $movimiento->soporte_path = null;
        }

        $movimiento->save();

        // Recalcular toda la cadena de saldos de este préstamo
        $this->recalcularSaldos($prestamo);

        return redirect()->route('finanzas.prestamos.show', $prestamo->id)
            ->with('success', 'Movimiento actualizado correctamente y saldos recalculados.');
    }

    /**
     * Elimina un movimiento específico de un préstamo y recalcula los saldos.
     */
    public function destroyMovimiento($id)
    {
        $movimiento = PrestamoMovimiento::findOrFail($id);
        $prestamo = Prestamo::where('user_id', Auth::id())->findOrFail($movimiento->prestamo_id);

        // Eliminar soporte físico si existe
        if ($movimiento->soporte_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($movimiento->soporte_path);
        }

        $movimiento->delete();

        // Recalcular saldos tras la eliminación
        $this->recalcularSaldos($prestamo);

        return redirect()->route('finanzas.prestamos.show', $prestamo->id)
            ->with('success', 'Movimiento eliminado correctamente y saldos recalculados.');
    }

    /**
     * Recalcula cronológicamente los saldos de los movimientos del préstamo.
     */
    private function recalcularSaldos(Prestamo $prestamo)
    {
        $movimientos = $prestamo->movimientos()->orderBy('fecha', 'asc')->orderBy('id', 'asc')->get();
        $saldo = 0.00;
        $ultimoCorteFecha = null;

        foreach ($movimientos as $mov) {
            $mov->saldo_antes = $saldo;
            
            if (in_array($mov->tipo, ['desembolso', 'interes_mensual', 'capitalizacion'])) {
                $mov->saldo_despues = $saldo + $mov->monto;
            } else {
                // abono_capital, abono_interes, pago_total
                $mov->saldo_despues = $saldo - $mov->monto;
            }
            
            $mov->save();
            $saldo = $mov->saldo_despues;

            if ($mov->tipo === 'interes_mensual') {
                $ultimoCorteFecha = $mov->fecha;
            }
        }

        $prestamo->update([
            'saldo_actual' => $saldo,
            'ultimo_corte' => $ultimoCorteFecha,
            'estado' => $saldo <= 0 ? 'pagado' : ($prestamo->dias_mora > 35 ? 'mora' : 'activo')
        ]);
    }

    private function isMobileDevice(Request $request): bool
    {
        $userAgent = $request->header('User-Agent', '');
        return (bool) preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent);
    }

    /**
     * Visualiza/Descarga el soporte principal de un préstamo.
     */
    public function descargarSoporte($id)
    {
        $prestamo = Prestamo::where('user_id', Auth::id())->findOrFail($id);

        if (!$prestamo->soporte_path || !Storage::disk('local')->exists($prestamo->soporte_path)) {
            // Si el archivo no existe localmente, pero estamos en desarrollo local, redirigimos a producción
            if (config('app.env') === 'local' || request()->getHost() === '127.0.0.1' || request()->getHost() === 'localhost') {
                return redirect('https://brynex.co/finanzas/prestamos/' . $prestamo->id . '/soporte');
            }
            abort(404, 'Archivo de soporte no encontrado.');
        }

        return Storage::disk('local')->response($prestamo->soporte_path);
    }

    /**
     * Visualiza/Descarga el soporte de un movimiento individual de préstamo.
     */
    public function descargarSoporteMovimiento($id)
    {
        $movimiento = PrestamoMovimiento::findOrFail($id);
        
        // Validar que el movimiento pertenezca a un préstamo del usuario actual
        $prestamo = Prestamo::where('user_id', Auth::id())->findOrFail($movimiento->prestamo_id);

        if (!$movimiento->soporte_path || !Storage::disk('local')->exists($movimiento->soporte_path)) {
            // Si el archivo no existe localmente, pero estamos en desarrollo local, redirigimos a producción
            if (config('app.env') === 'local' || request()->getHost() === '127.0.0.1' || request()->getHost() === 'localhost') {
                return redirect('https://brynex.co/finanzas/prestamos-movimiento/' . $movimiento->id . '/soporte');
            }
            abort(404, 'Archivo de soporte no encontrado.');
        }

        return Storage::disk('local')->response($movimiento->soporte_path);
    }
}
