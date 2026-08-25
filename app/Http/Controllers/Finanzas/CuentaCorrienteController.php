<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\Cuenta;
use App\Models\Finanzas\CuentaCorrienteCliente;
use App\Models\Finanzas\CuentaCorrienteItem;
use App\Models\Finanzas\Gasto;
use App\Models\Finanzas\Prestamo;
use App\Services\Finanzas\FinanzasWhatsappService;
use App\Services\Finanzas\PrestamoLiquidacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cuenta corriente de servicios.
 *
 * Un CLIENTE (ej. "Oficina Arroyave") acumula TRABAJOS. Cada trabajo se guarda
 * como un `Prestamo` de cuenta corriente para reusar toda la maquinaria de
 * liquidación, movimientos y abonos que ya existe para préstamos, pero con dos
 * diferencias de negocio:
 *
 *   1. El interés solo nace cuando el trabajo cumple un mes calendario sin
 *      pagarse. Pagarlo antes no cuesta un peso de más.
 *   2. El desglose (4 cámaras, 1 DVR, mano de obra) vive en ítems propios y su
 *      suma es el valor del trabajo.
 */
class CuentaCorrienteController extends Controller
{
    use \App\Http\Controllers\Finanzas\Concerns\InvalidaFinanzasCache;
    use \App\Http\Controllers\Finanzas\Concerns\ResuelveCuenta;

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
     * Listado de clientes con su saldo consolidado.
     */
    public function index()
    {
        $clientes = CuentaCorrienteCliente::where('user_id', Auth::id())
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        // Un solo query para los saldos de todos los clientes, en vez de uno por tarjeta.
        $resumen = Prestamo::where('user_id', Auth::id())
            ->where('es_cuenta_corriente', true)
            ->whereNotNull('cc_cliente_id')
            ->selectRaw('cc_cliente_id,
                COUNT(*) as total_trabajos,
                SUM(CASE WHEN estado IN (\'activo\', \'mora\') THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado IN (\'activo\', \'mora\') THEN saldo_actual ELSE 0 END) as saldo,
                SUM(CASE WHEN estado IN (\'activo\', \'mora\') THEN saldo_actual - monto_original ELSE 0 END) as intereses')
            ->groupBy('cc_cliente_id')
            ->get()
            ->keyBy('cc_cliente_id');

        $saldoTotalPendiente = (float) $resumen->sum('saldo');

        return view('finanzas.cuenta-corriente.index', compact('clientes', 'resumen', 'saldoTotalPendiente'));
    }

    /**
     * Ficha del cliente: sus trabajos, el desglose de cada uno y su historial.
     */
    public function show($id)
    {
        $cliente = $this->clienteDelUsuario($id);

        $trabajos = $cliente->trabajos()
            ->with(['items', 'movimientos' => fn ($q) => $q->orderBy('fecha', 'desc')->orderBy('id', 'desc')])
            ->orderByRaw("CASE WHEN estado IN ('activo', 'mora') THEN 0 ELSE 1 END")
            ->orderBy('fecha_desembolso', 'desc')
            ->orderByDesc('id')
            ->get();

        $pendientes = $trabajos->whereIn('estado', ['activo', 'mora']);

        $totales = [
            'saldo' => (float) $pendientes->sum('saldo_actual'),
            'capital' => (float) $pendientes->sum('monto_original'),
            'intereses' => (float) $pendientes->sum(fn ($t) => $t->intereses_acumulados),
            'trabajos_pendientes' => $pendientes->count(),
            'trabajos_totales' => $trabajos->count(),
            'vencidos' => $pendientes->filter(fn ($t) => $t->esta_vencido)->count(),
            // La utilidad se mide sobre TODOS los trabajos, cobrados o no: es el
            // negocio del cliente, no la plata que falta por entrar.
            'facturado' => (float) $trabajos->sum(fn ($t) => $t->total_items),
            'costos' => (float) $trabajos->sum(fn ($t) => $t->costo_items),
            'utilidad' => (float) $trabajos->sum(fn ($t) => $t->utilidad),
        ];

        $cuentas = Cuenta::where('user_id', Auth::id())->activas()->orderBy('orden')->get();
        $categorias = CategoriaGasto::where('user_id', Auth::id())->orderBy('nombre')->get();

        return view('finanzas.cuenta-corriente.show', compact('cliente', 'trabajos', 'totales', 'cuentas', 'categorias'));
    }

    /* ---------------------------------------------------------------- Clientes */

    public function storeCliente(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'cedula' => 'nullable|string|max:20',
            'telefono' => 'nullable|string|max:20',
            'tasa_interes_mensual' => 'required|numeric|min:0|max:100',
            'dias_mora_alerta' => 'required|integer|min:1',
            'notas' => 'nullable|string',
        ]);

        $cliente = CuentaCorrienteCliente::create([
            'user_id' => Auth::id(),
            'nombre' => $request->nombre,
            'cedula' => $request->cedula,
            'telefono' => $request->telefono,
            'tasa_interes_mensual' => $request->tasa_interes_mensual,
            'dias_mora_alerta' => $request->dias_mora_alerta,
            'alertas_activas' => $request->boolean('alertas_activas', true),
            'notas' => $request->notas,
            'activo' => true,
        ]);

        return redirect()->route('finanzas.cuenta-corriente.show', $cliente->id)
            ->with('success', "Cliente «{$cliente->nombre}» creado. Ya puedes registrarle trabajos.");
    }

    public function updateCliente(Request $request, $id)
    {
        $cliente = $this->clienteDelUsuario($id);

        $request->validate([
            'nombre' => 'required|string|max:100',
            'cedula' => 'nullable|string|max:20',
            'telefono' => 'nullable|string|max:20',
            'tasa_interes_mensual' => 'required|numeric|min:0|max:100',
            'dias_mora_alerta' => 'required|integer|min:1',
            'notas' => 'nullable|string',
        ]);

        $cliente->update([
            'nombre' => $request->nombre,
            'cedula' => $request->cedula,
            'telefono' => $request->telefono,
            'tasa_interes_mensual' => $request->tasa_interes_mensual,
            'dias_mora_alerta' => $request->dias_mora_alerta,
            'alertas_activas' => $request->boolean('alertas_activas'),
            'notas' => $request->notas,
            'activo' => $request->boolean('activo', true),
        ]);

        // Los datos de contacto viven en el cliente; los trabajos los reflejan
        // para que los cobros y las búsquedas por deudor sigan funcionando.
        $cliente->trabajos()->update([
            'nombre_deudor' => $cliente->nombre,
            'cedula_deudor' => $cliente->cedula,
            'telefono_deudor' => $cliente->telefono,
        ]);

        return redirect()->route('finanzas.cuenta-corriente.show', $cliente->id)
            ->with('success', 'Datos del cliente actualizados.');
    }

    public function destroyCliente($id)
    {
        $cliente = $this->clienteDelUsuario($id);

        if ($cliente->trabajos()->exists()) {
            return redirect()->route('finanzas.cuenta-corriente.show', $cliente->id)
                ->with('error', 'No se puede eliminar un cliente que ya tiene trabajos registrados. Márcalo como inactivo si ya no lo usas.');
        }

        $nombre = $cliente->nombre;
        $cliente->delete();

        return redirect()->route('finanzas.cuenta-corriente.index')
            ->with('success', "Cliente «{$nombre}» eliminado.");
    }

    /* ---------------------------------------------------------------- Trabajos */

    /**
     * Registra un trabajo con su desglose de ítems.
     */
    public function storeTrabajo(Request $request, $id)
    {
        $cliente = $this->clienteDelUsuario($id);

        $request->validate([
            'descripcion' => 'required|string|max:255',
            'fecha' => 'required|date',
            'tasa_interes_mensual' => 'required|numeric|min:0|max:100',
            'items' => 'required|array|min:1',
            'items.*.descripcion' => 'required|string|max:150',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.valor_unitario' => 'required|numeric|min:0',
            'items.*.costo_unitario' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
            'cuenta_costo_id' => 'nullable|integer',
            'categoria_costo_id' => 'nullable|integer',
            'soporte' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $items = collect($request->input('items'))
            ->map(fn ($i) => [
                'descripcion' => trim($i['descripcion']),
                'cantidad' => (float) $i['cantidad'],
                'valor_unitario' => (float) $i['valor_unitario'],
                'costo_unitario' => (float) ($i['costo_unitario'] ?? 0),
            ])
            ->values();

        $total = round($items->sum(fn ($i) => $i['cantidad'] * $i['valor_unitario']), 2);

        if ($total <= 0) {
            return back()->withInput()->with('error', 'El total del trabajo debe ser mayor a cero. Revisa las cantidades y valores del desglose.');
        }

        $soportePath = $request->hasFile('soporte')
            ? $request->file('soporte')->store('finanzas/prestamos', 'local')
            : null;

        $trabajo = DB::connection('finanzas')->transaction(function () use ($cliente, $request, $items, $total, $soportePath) {
            $trabajo = Prestamo::create([
                'user_id' => $cliente->user_id,
                'nombre_deudor' => $cliente->nombre,
                'cedula_deudor' => $cliente->cedula,
                'telefono_deudor' => $cliente->telefono,
                'monto_original' => $total,
                'tasa_interes_mensual' => (float) $request->tasa_interes_mensual,
                'fecha_desembolso' => $request->fecha,
                'ultimo_corte' => $request->fecha,
                'saldo_actual' => $total,
                'estado' => 'activo',
                'dias_mora_alerta' => $cliente->dias_mora_alerta,
                'alertas_activas' => $cliente->alertas_activas,
                'soporte_path' => $soportePath,
                'descripcion' => $request->descripcion,
                'observaciones' => $request->observaciones,
                'es_cuenta_corriente' => true,
                'cc_cliente_id' => $cliente->id,
                'sin_interes' => $request->boolean('sin_interes'),
            ]);

            foreach ($items as $orden => $item) {
                CuentaCorrienteItem::create([
                    'prestamo_id' => $trabajo->id,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'valor_unitario' => $item['valor_unitario'],
                    'costo_unitario' => $item['costo_unitario'],
                    'orden' => $orden,
                ]);
            }

            $this->liquidacionService->registrarDesembolso($trabajo);

            return $trabajo;
        });

        // A diferencia de un préstamo, aquí no salió plata por el valor del trabajo:
        // el egreso real es la suma de los costos de las líneas del desglose.
        $this->sincronizarGastoCosto($trabajo, $request->cuenta_costo_id, $request->categoria_costo_id);

        $this->invalidarCacheFinanzas(
            (int) date('Y', strtotime($request->fecha)),
            (int) date('n', strtotime($request->fecha))
        );

        return redirect()->route('finanzas.cuenta-corriente.show', $cliente->id)
            ->with('success', "Trabajo «{$trabajo->descripcion}» registrado por \$".number_format($total, 0, ',', '.').' COP.');
    }

    /**
     * Edita la cabecera y el desglose de un trabajo, recalculando su valor.
     */
    public function updateTrabajo(Request $request, $trabajoId)
    {
        $trabajo = $this->trabajoDelUsuario($trabajoId);

        $request->validate([
            'descripcion' => 'required|string|max:255',
            'fecha' => 'required|date',
            'tasa_interes_mensual' => 'required|numeric|min:0|max:100',
            'items' => 'required|array|min:1',
            'items.*.descripcion' => 'required|string|max:150',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.valor_unitario' => 'required|numeric|min:0',
            'items.*.costo_unitario' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
            'cuenta_costo_id' => 'nullable|integer',
            'categoria_costo_id' => 'nullable|integer',
        ]);

        $items = collect($request->input('items'))->values();
        $total = round($items->sum(fn ($i) => (float) $i['cantidad'] * (float) $i['valor_unitario']), 2);

        if ($total <= 0) {
            return back()->withInput()->with('error', 'El total del trabajo debe ser mayor a cero.');
        }

        $abonadoACapital = (float) $trabajo->movimientos()->where('tipo', 'abono_capital')->sum('monto');

        if ($total < $abonadoACapital) {
            return back()->withInput()->with('error',
                'El nuevo total ($'.number_format($total, 0, ',', '.').') es menor a lo que ya se abonó a capital ($'
                .number_format($abonadoACapital, 0, ',', '.').'). Ajusta el desglose o revierte los abonos primero.');
        }

        DB::connection('finanzas')->transaction(function () use ($trabajo, $request, $items, $total, $abonadoACapital) {
            $trabajo->update([
                'descripcion' => $request->descripcion,
                'fecha_desembolso' => $request->fecha,
                'tasa_interes_mensual' => (float) $request->tasa_interes_mensual,
                'sin_interes' => $request->boolean('sin_interes'),
                'observaciones' => $request->observaciones,
            ]);

            $trabajo->items()->delete();

            foreach ($items as $orden => $item) {
                CuentaCorrienteItem::create([
                    'prestamo_id' => $trabajo->id,
                    'descripcion' => trim($item['descripcion']),
                    'cantidad' => (float) $item['cantidad'],
                    'valor_unitario' => (float) $item['valor_unitario'],
                    'costo_unitario' => (float) ($item['costo_unitario'] ?? 0),
                    'orden' => $orden,
                ]);
            }

            // El movimiento de apertura debe reflejar el nuevo valor para que el
            // recálculo cronológico de saldos cuadre con el desglose.
            $apertura = $trabajo->movimientos()->where('tipo', 'desembolso')->orderBy('id')->first();
            if ($apertura) {
                $apertura->update([
                    'monto' => $total,
                    'fecha' => $request->fecha,
                    'observacion' => $request->descripcion,
                ]);
            }

            $trabajo->update(['monto_original' => round($total - $abonadoACapital, 2)]);

            $this->recalcularSaldos($trabajo->fresh());
        });

        // El gasto sigue a los costos del desglose: si subieron, bajaron o
        // quedaron en cero, la cuenta tiene que reflejarlo.
        $this->sincronizarGastoCosto($trabajo->fresh(), $request->cuenta_costo_id, $request->categoria_costo_id);

        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.cuenta-corriente.show', $trabajo->cc_cliente_id)
            ->with('success', 'Trabajo actualizado y saldos recalculados.');
    }

    public function destroyTrabajo($trabajoId)
    {
        $trabajo = $this->trabajoDelUsuario($trabajoId);
        $clienteId = $trabajo->cc_cliente_id;
        $descripcion = $trabajo->descripcion;

        DB::connection('finanzas')->transaction(function () use ($trabajo) {
            foreach ($trabajo->movimientos as $mov) {
                if ($mov->soporte_path) {
                    Storage::disk('local')->delete($mov->soporte_path);
                }
            }

            if ($trabajo->soporte_path) {
                Storage::disk('local')->delete($trabajo->soporte_path);
            }

            // El gasto solo existe por este trabajo: si el trabajo se va, la plata
            // vuelve a la cuenta.
            Gasto::where('cc_trabajo_id', $trabajo->id)->delete();

            $trabajo->items()->delete();
            $trabajo->movimientos()->delete();
            $trabajo->delete();
        });

        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.cuenta-corriente.show', $clienteId)
            ->with('success', "Trabajo «{$descripcion}» eliminado junto con su historial.");
    }

    /* ------------------------------------------------------------------- Pagos */

    /**
     * Pago dirigido a un trabajo puntual ("Arroyave paga solo las cámaras").
     */
    public function pagarTrabajo(Request $request, $trabajoId)
    {
        $trabajo = $this->trabajoDelUsuario($trabajoId);

        $request->validate([
            'monto' => 'required|numeric|min:1',
            'fecha' => 'required|date',
            'observacion' => 'nullable|string|max:255',
            'cuenta_id' => 'nullable|integer',
            'soporte' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $soportePath = $request->hasFile('soporte')
            ? $request->file('soporte')->store('finanzas/prestamos', 'local')
            : null;

        $res = $this->liquidacionService->registrarPago(
            $trabajo,
            (float) $request->monto,
            $request->fecha,
            $request->observacion ?: "Pago del trabajo: {$trabajo->descripcion}",
            $soportePath,
            $this->resolverCuenta($request->cuenta_id)
        );

        if (! $res['success']) {
            return redirect()->route('finanzas.cuenta-corriente.show', $trabajo->cc_cliente_id)
                ->with('error', $res['message']);
        }

        $this->invalidarCacheFinanzas(
            (int) date('Y', strtotime($request->fecha)),
            (int) date('n', strtotime($request->fecha))
        );

        $trabajo->refresh();
        $detalle = "Pago aplicado al trabajo «{$trabajo->descripcion}»: \$"
            .number_format($res['abono_capital'], 0, ',', '.').' al trabajo';

        if ($res['abono_interes'] > 0) {
            $detalle .= ' y $'.number_format($res['abono_interes'], 0, ',', '.').' a intereses vencidos';
        }

        $detalle .= $trabajo->saldo_actual <= 0
            ? '. El trabajo queda saldado.'
            : '. Queda pendiente $'.number_format($trabajo->saldo_actual, 0, ',', '.').'.';

        return redirect()->route('finanzas.cuenta-corriente.show', $trabajo->cc_cliente_id)
            ->with('success', $detalle);
    }

    /**
     * Abono general al cliente: se reparte del trabajo más viejo al más nuevo.
     *
     * Lo que sobre después de dejar todos los trabajos en cero NO se guarda como
     * saldo a favor: se informa y se devuelve, porque un excedente inventado es
     * exactamente el error que produce cobros fantasma más adelante.
     */
    public function abonoGeneral(Request $request, $id)
    {
        $cliente = $this->clienteDelUsuario($id);

        $request->validate([
            'monto' => 'required|numeric|min:1',
            'fecha' => 'required|date',
            'observacion' => 'nullable|string|max:255',
            'cuenta_id' => 'nullable|integer',
            'soporte' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $trabajos = $cliente->trabajosPendientes()
            ->orderBy('fecha_desembolso')
            ->orderBy('id')
            ->get();

        if ($trabajos->isEmpty()) {
            return redirect()->route('finanzas.cuenta-corriente.show', $cliente->id)
                ->with('error', 'El cliente no tiene trabajos pendientes a los cuales aplicar el abono.');
        }

        $soportePath = $request->hasFile('soporte')
            ? $request->file('soporte')->store('finanzas/prestamos', 'local')
            : null;

        $cuentaId = $this->resolverCuenta($request->cuenta_id);
        $restante = round((float) $request->monto, 2);
        $aplicados = [];

        foreach ($trabajos as $trabajo) {
            if ($restante <= 0) {
                break;
            }

            // Causar primero los meses cumplidos: sin esto el abono taparía el
            // capital y dejaría vivo un interés que ya se había ganado.
            $this->liquidacionService->liquidarPeriodo($trabajo, null, $request->fecha, [], true);
            $trabajo->refresh();

            $necesario = round((float) $trabajo->saldo_actual, 2);
            if ($necesario <= 0) {
                continue;
            }

            $aplicar = min($restante, $necesario);

            $res = $this->liquidacionService->registrarPago(
                $trabajo,
                $aplicar,
                $request->fecha,
                $request->observacion ?: 'Abono general del cliente distribuido a este trabajo.',
                $soportePath,
                $cuentaId
            );

            if (! $res['success']) {
                return redirect()->route('finanzas.cuenta-corriente.show', $cliente->id)
                    ->with('error', "No se pudo aplicar el abono al trabajo «{$trabajo->descripcion}»: {$res['message']}");
            }

            $restante = round($restante - $aplicar, 2);
            $aplicados[] = $trabajo->descripcion.' ($'.number_format($aplicar, 0, ',', '.').')';
        }

        $this->invalidarCacheFinanzas(
            (int) date('Y', strtotime($request->fecha)),
            (int) date('n', strtotime($request->fecha))
        );

        $mensaje = 'Abono distribuido en '.count($aplicados).' trabajo(s): '.implode(', ', $aplicados).'.';

        if ($restante > 0) {
            $mensaje .= ' Sobraron $'.number_format($restante, 0, ',', '.')
                .' que NO se aplicaron porque el cliente quedó a paz y salvo. Regístralos donde corresponda.';

            return redirect()->route('finanzas.cuenta-corriente.show', $cliente->id)
                ->with('warning', $mensaje);
        }

        return redirect()->route('finanzas.cuenta-corriente.show', $cliente->id)
            ->with('success', $mensaje);
    }

    /**
     * Liquida manualmente los meses cumplidos de todos los trabajos del cliente.
     */
    public function liquidarCliente(Request $request, $id)
    {
        $cliente = $this->clienteDelUsuario($id);
        $hasta = $request->input('fecha_hasta', now()->toDateString());
        $total = 0.00;
        $afectados = 0;

        foreach ($cliente->trabajosPendientes()->get() as $trabajo) {
            $interes = $this->liquidacionService->liquidarPeriodo($trabajo, null, $hasta, [], true);

            if ($interes > 0) {
                $total += $interes;
                $afectados++;
            }
        }

        $this->invalidarCacheFinanzas();

        return redirect()->route('finanzas.cuenta-corriente.show', $cliente->id)
            ->with('success', $afectados > 0
                ? 'Liquidación ejecutada: $'.number_format($total, 0, ',', '.')." en intereses sobre {$afectados} trabajo(s)."
                : 'Ningún trabajo tenía meses cumplidos por liquidar.');
    }

    public function whatsapp(Request $request, $id)
    {
        $cliente = $this->clienteDelUsuario($id);
        $res = $this->whatsappService->enviarRecordatorioCuentaCorriente($cliente);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => $res['ok'],
                'message' => $res['message'].($res['ok'] ? '' : ' Detalles: '.($res['error'] ?? 'ninguno')),
            ], $res['ok'] ? 200 : 400);
        }

        return redirect()->route('finanzas.cuenta-corriente.show', $cliente->id)
            ->with($res['ok'] ? 'success' : 'error',
                $res['message'].($res['ok'] ? '' : ' Detalles: '.($res['error'] ?? 'ninguno')));
    }

    /* ----------------------------------------------------------------- Helpers */

    private function clienteDelUsuario($id): CuentaCorrienteCliente
    {
        return CuentaCorrienteCliente::where('user_id', Auth::id())->findOrFail($id);
    }

    private function trabajoDelUsuario($id): Prestamo
    {
        return Prestamo::where('user_id', Auth::id())
            ->where('es_cuenta_corriente', true)
            ->whereNotNull('cc_cliente_id')
            ->findOrFail($id);
    }

    /**
     * Mantiene el gasto del trabajo alineado con la suma de los costos de su
     * desglose. Es un gasto normal (`tipo_movimiento = 'gasto'`) enlazado por
     * `cc_trabajo_id`: sale de la cuenta y entra al gasto del mes por el camino
     * de siempre, sin depender de que un tipo nuevo esté dado de alta en todas
     * las listas blancas que suman caja.
     *
     * Un solo gasto por trabajo, no uno por línea: el detalle se ve en la ficha
     * y la lista de gastos del día no se llena de renglones sueltos.
     */
    private function sincronizarGastoCosto(Prestamo $trabajo, $cuentaId = null, $categoriaId = null): void
    {
        $trabajo->load('items');
        $costo = $trabajo->costo_items;
        $gasto = Gasto::where('cc_trabajo_id', $trabajo->id)->first();

        // Sin costos no hay egreso: si antes lo había, se retira de la cuenta.
        if ($costo <= 0) {
            $gasto?->delete();

            return;
        }

        $datos = [
            'monto' => $costo,
            'fecha' => $trabajo->fecha_desembolso,
            'descripcion' => "Costos del trabajo: {$trabajo->descripcion} ({$trabajo->nombre_deudor})",
        ];

        if ($gasto) {
            // La cuenta y la categoría solo se pisan si el formulario las mandó;
            // así una edición del desglose no mueve dónde quedó registrado el egreso.
            if ($cuentaId) {
                $datos['cuenta_id'] = $this->resolverCuenta($cuentaId);
            }
            if ($categoriaId) {
                $datos['categoria_id'] = $this->resolverCategoriaCosto($trabajo, $categoriaId);
            }

            $gasto->update($datos);

            return;
        }

        Gasto::create($datos + [
            'user_id' => $trabajo->user_id,
            'categoria_id' => $this->resolverCategoriaCosto($trabajo, $categoriaId),
            'cuenta_id' => $this->resolverCuenta($cuentaId),
            'tipo_movimiento' => 'gasto',
            'es_patrimonio' => false,
            'patrimonio_id' => null,
            'cc_trabajo_id' => $trabajo->id,
        ]);
    }

    /**
     * Categoría del gasto de costos: la que se eligió, o una propia para no
     * revolver los costos de trabajos con los gastos de la casa.
     */
    private function resolverCategoriaCosto(Prestamo $trabajo, $categoriaId): int
    {
        if ($categoriaId) {
            $elegida = CategoriaGasto::where('user_id', $trabajo->user_id)->find($categoriaId);
            if ($elegida) {
                return $elegida->id;
            }
        }

        return CategoriaGasto::firstOrCreate(
            ['user_id' => $trabajo->user_id, 'nombre' => 'TRABAJOS'],
            ['icono' => '🛠️', 'orden' => 98]
        )->id;
    }

    /**
     * Recalcula cronológicamente los saldos de un trabajo tras editarlo.
     */
    private function recalcularSaldos(Prestamo $trabajo): void
    {
        $movimientos = $trabajo->movimientos()->orderBy('fecha')->orderBy('id')->get();
        $saldo = 0.00;
        $ultimoCorte = null;

        foreach ($movimientos as $mov) {
            $mov->saldo_antes = $saldo;
            $mov->saldo_despues = in_array($mov->tipo, ['desembolso', 'interes_mensual', 'interes_proporcional', 'capitalizacion'])
                ? $saldo + $mov->monto
                : $saldo - $mov->monto;
            $mov->save();

            $saldo = $mov->saldo_despues;

            if ($mov->tipo === 'interes_mensual') {
                $ultimoCorte = $mov->fecha;
            }
        }

        $trabajo->update([
            'saldo_actual' => max(0.00, round($saldo, 2)),
            'ultimo_corte' => $ultimoCorte ?: $trabajo->fecha_desembolso,
            'estado' => $saldo <= 0 ? 'pagado' : ($trabajo->estado === 'castigado' ? 'castigado' : 'activo'),
        ]);
    }
}
