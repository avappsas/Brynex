<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BancoCuenta;
use App\Models\DataicoConfiguracion;
use App\Models\DataicoEnvio;
use App\Models\RazonSocial;
use App\Services\Dataico\EmisionService;
use App\Services\Dataico\PayloadBuilder;
use App\Services\Dataico\SeleccionFacturasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pantalla de la integración con el API de Dataico.
 *
 * Convive con [[FacturacionElectronicaController]], que es el flujo viejo
 * (exportar Excel y subirlo a mano). Los dos comparten `facturas.fe_marcada`:
 * lo que emite el API queda marcado y deja de salir en el Excel, así que no
 * hay forma de facturar dos veces lo mismo por los dos caminos.
 */
class DataicoController extends Controller
{
    public function __construct(
        private readonly SeleccionFacturasService $seleccion,
        private readonly EmisionService $emision,
        private readonly PayloadBuilder $builder,
    ) {}

    // ─── Panel ───────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $aliadoId = (int) session('aliado_id_activo');
        $cfg = DataicoConfiguracion::where('aliado_id', $aliadoId)->first();

        $estado = $request->input('estado', 'todos');

        $envios = DataicoEnvio::aliado($aliadoId)
            ->when($estado !== 'todos', fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $resumen = DB::table('dataico_envios')
            ->where('aliado_id', $aliadoId)
            ->selectRaw('estado, COUNT(*) AS n, SUM(CAST(base_admon AS BIGINT)) AS valor')
            ->groupBy('estado')
            ->pluck('n', 'estado');

        // Cuánto está esperando salir ahora mismo, y qué quedó retenido.
        $clasificado = ($cfg && $cfg->banco_cuenta_id && $cfg->fecha_inicio)
            ? $this->seleccion->clasificar($cfg)
            : ['emitibles' => collect(), 'sin_documento' => collect()];

        $pendientes = $clasificado['emitibles'];

        return view('admin.facturacion.dataico', [
            'cfg' => $cfg,
            'envios' => $envios,
            'estado' => $estado,
            'resumen' => $resumen,
            'porEmitir' => $pendientes->count(),
            'porEmitirValor' => (int) $pendientes->sum('base_admon'),
            'sinCorreo' => $pendientes->filter(fn ($g) => empty($g->adquiriente['correo']))->count(),
            'sinDocumento' => $clasificado['sin_documento'],
            // Grupos que no se emiten porque mezclan adquirientes. Van a la
            // vista para que no desaparezcan en silencio.
            'ambiguos' => ($cfg && $cfg->fecha_inicio)
                ? $this->seleccion->gruposAmbiguos($cfg)
                : collect(),
            'razonesSociales' => RazonSocial::where('aliado_id', $aliadoId)
                ->where('estado', 'Activa')
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nit', 'dv']),
            'cuentas' => BancoCuenta::activas($aliadoId),
        ]);
    }

    // ─── Configuración ───────────────────────────────────────────────────

    public function guardarConfiguracion(Request $request)
    {
        $aliadoId = (int) session('aliado_id_activo');

        $datos = $request->validate([
            'razon_social_id' => 'required|integer',
            'banco_cuenta_id' => 'required|integer',
            'fecha_inicio' => 'required|date',
            'modo' => 'required|in:factura,diario',
            'hora_cierre' => 'required|date_format:H:i',
            'dataico_account_id' => 'nullable|string|max:100',
            'auth_token' => 'nullable|string|max:500',
            'numbering_range_id' => 'nullable|string|max:100',
            'prefijo' => 'nullable|string|max:20',
            'resolucion' => 'nullable|string|max:50',
            'correo_fallback' => 'nullable|email|max:150',
            'enviar_email' => 'nullable|boolean',
            'activo' => 'nullable|boolean',
            'observacion' => 'nullable|string|max:500',
        ]);

        // La razón social y la cuenta tienen que ser del aliado en sesión: sin
        // esto, cambiando un id en el formulario se emitiría con la resolución
        // DIAN de otro aliado.
        $rsValida = RazonSocial::where('aliado_id', $aliadoId)
            ->where('id', $datos['razon_social_id'])->exists();
        $cuentaValida = BancoCuenta::where('aliado_id', $aliadoId)
            ->where('id', $datos['banco_cuenta_id'])->exists();

        if (! $rsValida || ! $cuentaValida) {
            return back()->with('error', 'La razón social o la cuenta no pertenecen a este aliado.');
        }

        $cfg = DataicoConfiguracion::firstOrNew(['aliado_id' => $aliadoId]);

        // El token en blanco significa "no lo cambies", no "bórralo": el
        // formulario nunca lo muestra de vuelta.
        if (blank($datos['auth_token'] ?? null)) {
            unset($datos['auth_token']);
        }

        $cfg->fill($datos + [
            'aliado_id' => $aliadoId,
            'enviar_email' => (bool) $request->boolean('enviar_email'),
            'consumidor_final' => (bool) $request->boolean('consumidor_final'),
            'activo' => (bool) $request->boolean('activo'),
        ])->save();

        if ($cfg->activo && ! $cfg->estaCompleta()) {
            return back()->with('error',
                'Se guardó, pero queda inactiva de hecho: faltan el ID de cuenta o el token de Dataico.');
        }

        return back()->with('success', 'Configuración de Dataico guardada.');
    }

    // ─── Acciones sobre envíos ───────────────────────────────────────────

    /** Reintenta los envíos que quedaron en error. */
    public function reintentar(Request $request)
    {
        $aliadoId = (int) session('aliado_id_activo');
        $cfg = DataicoConfiguracion::activaDe($aliadoId);

        if (! $cfg || ! $cfg->estaCompleta()) {
            return back()->with('error', 'La configuración de Dataico no está completa.');
        }

        $numeros = array_filter(
            array_map('intval', (array) $request->input('numeros_factura', [])),
            fn ($v) => $v > 0
        );

        if (empty($numeros)) {
            return back()->with('error', 'No seleccionaste ninguna factura.');
        }

        // Devolver a `pendiente` con el contador en cero es lo que las vuelve
        // elegibles otra vez: la selección excluye `enviado`, `enviando`,
        // `omitido` y los `error` que ya agotaron los intentos automáticos.
        // Un reintento a mano es una persona diciendo "ya corregí el dato".
        DataicoEnvio::aliado($aliadoId)
            ->whereIn('numero_factura', $numeros)
            ->where('estado', DataicoEnvio::ESTADO_ERROR)
            ->update(['estado' => DataicoEnvio::ESTADO_PENDIENTE, 'intentos' => 0]);

        $ok = 0;
        $mal = 0;
        foreach ($numeros as $n) {
            $r = $this->emision->emitirNumeroFactura($cfg, $n);
            if ($r === null) {
                continue;
            }
            $r['resultado'] === 'enviadas' ? $ok++ : $mal++;
        }

        return back()->with('success', "Reintento: {$ok} emitidas, {$mal} con error.");
    }

    /** Excluye una factura del envío automático, dejando por qué. */
    public function omitir(Request $request)
    {
        $aliadoId = (int) session('aliado_id_activo');

        $datos = $request->validate([
            'numero_factura' => 'required|integer|min:1',
            'motivo' => 'required|string|max:500',
        ]);

        DataicoEnvio::updateOrCreate(
            ['aliado_id' => $aliadoId, 'numero_factura' => $datos['numero_factura']],
            [
                'estado' => DataicoEnvio::ESTADO_OMITIDO,
                'error_mensaje' => $datos['motivo'],
            ]
        );

        return back()->with('success', "Factura {$datos['numero_factura']} excluida de Dataico.");
    }

    /** Muestra el JSON que se enviaría, sin enviar nada. */
    public function simular(Request $request)
    {
        $aliadoId = (int) session('aliado_id_activo');
        $cfg = DataicoConfiguracion::where('aliado_id', $aliadoId)->first();

        if (! $cfg || ! $cfg->banco_cuenta_id || ! $cfg->fecha_inicio) {
            return response()->json(['error' => 'Configura primero la cuenta y la fecha de inicio.'], 422);
        }

        $numero = (int) $request->input('numero_factura', 0);
        $grupo = $this->seleccion->pendientes($cfg, $numero ?: null, 1)->first();

        if (! $grupo) {
            return response()->json(['error' => 'No hay ninguna factura pendiente que simular.'], 404);
        }

        return response()->json([
            'numero_factura' => $grupo->numero_factura,
            'payload' => $this->builder->construir($cfg, $grupo),
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
