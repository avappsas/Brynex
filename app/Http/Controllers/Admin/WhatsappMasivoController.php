<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\WhatsappEnvioMasivoJob;
use App\Models\{
    Contrato, Empresa, Factura,
    WhatsappConfig, WhatsappEnvioMasivo,
    WhatsappEnvioMasivoDetalle, WhatsappPlantilla
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Controlador de envíos masivos de WhatsApp desde el módulo de cobros.
 * Solo el admin del aliado puede lanzar envíos masivos.
 */
class WhatsappMasivoController extends Controller
{
    /**
     * Lanza un envío masivo a clientes individuales con pagos pendientes.
     * Se usa desde la vista de cobros individuales.
     */
    public function lanzarIndividual(Request $request)
    {
        $alidoId = session('aliado_id_activo');
        $this->authorizarAdmin();

        $validated = $request->validate([
            'plantilla_id' => 'required|integer|exists:whatsapp_plantillas,id',
            'mes'          => 'required|integer|min:1|max:12',
            'anio'         => 'required|integer|min:2020|max:2099',
            'parametros'   => 'nullable|array',
        ]);

        // Verificar credenciales
        $config = WhatsappConfig::paraAliado($alidoId);
        if (!$config->credencialesCompletas()) {
            return response()->json(['ok' => false, 'error' => 'No hay credenciales de WhatsApp configuradas.'], 422);
        }

        $effectiveAliadoId = $alidoId;
        if ($config->usa_cuenta_brynex) {
            $aliadoBrynex = \App\Models\Aliado::where('nombre', 'BryNex')->first();
            $effectiveAliadoId = $aliadoBrynex ? $aliadoBrynex->id : 1;
        }

        // Verificar que la plantilla es del aliado y está aprobada
        $plantilla = WhatsappPlantilla::delAliado($effectiveAliadoId)
            ->aprobadas()
            ->findOrFail($validated['plantilla_id']);

        $mes  = $validated['mes'];
        $anio = $validated['anio'];

        // Obtener contratos individuales pendientes del mes
        $contratosPendientes = $this->obtenerContratosPendientesIndividuales($alidoId, $mes, $anio);

        // Filtrar los que no han recibido esta plantilla hoy (anti-spam)
        $hoy = now()->toDateString();
        $yaEnviados = DB::table('whatsapp_envios_masivos_detalle as d')
            ->join('whatsapp_envios_masivos as e', 'e.id', '=', 'd.envio_id')
            ->where('e.aliado_id', $alidoId)
            ->where('e.plantilla_id', $plantilla->id)
            ->whereDate('d.created_at', $hoy)
            ->where('d.estado', 'enviado')
            ->pluck('d.wa_numero')
            ->flip()
            ->all();

        $destinatarios = [];
        foreach ($contratosPendientes as $contrato) {
            $numero = $this->normalizarNumero($contrato->celular ?? '');
            if (!$numero) continue;
            if (isset($yaEnviados[$numero])) continue; // Ya recibió hoy esta plantilla

            $destinatarios[] = [
                'contrato_id'        => $contrato->id,
                'empresa_id'         => null,
                'wa_numero'          => $numero,
                'nombre_destinatario'=> $contrato->nombre_cliente ?? $contrato->cedula,
            ];
        }

        if (empty($destinatarios)) {
            return response()->json([
                'ok'      => false,
                'error'   => 'No hay destinatarios disponibles (todos ya recibieron esta plantilla hoy o no tienen número).',
            ], 422);
        }

        // Crear el registro del envío masivo
        $envio = WhatsappEnvioMasivo::create([
            'aliado_id'           => $alidoId,
            'plantilla_id'        => $plantilla->id,
            'usuario_id'          => Auth::id(),
            'mes'                 => $mes,
            'anio'                => $anio,
            'tipo_envio'          => 'individual',
            'total_destinatarios' => count($destinatarios),
            'estado'              => 'pendiente',
            'parametros_json'     => $validated['parametros'] ?? [],
        ]);

        // Crear detalle por destinatario
        foreach ($destinatarios as $dest) {
            WhatsappEnvioMasivoDetalle::create(array_merge($dest, [
                'envio_id' => $envio->id,
                'estado'   => 'pendiente',
            ]));
        }

        // Obtener la URL de la imagen del header desde la petición HTTP actual
        $headerImageUrl = null;
        if ($config->cobro_header_imagen) {
            $headerImageUrl = url('storage/' . $config->cobro_header_imagen);
        }

        // Despachar job en background
        dispatch(new WhatsappEnvioMasivoJob($envio->id, $validated['parametros'] ?? [], $headerImageUrl));

        return response()->json([
            'ok'      => true,
            'mensaje' => "Envío en proceso: {$envio->total_destinatarios} mensajes siendo enviados.",
            'envio_id'=> $envio->id,
        ]);
    }

    /**
     * Lanza un envío masivo a empresas (solo al contacto de la empresa).
     */
    public function lanzarEmpresa(Request $request)
    {
        $alidoId = session('aliado_id_activo');
        $this->authorizarAdmin();

        $validated = $request->validate([
            'plantilla_id' => 'required|integer|exists:whatsapp_plantillas,id',
            'empresa_ids'  => 'required|array|min:1',
            'empresa_ids.*'=> 'integer',
            'mes'          => 'required|integer|min:1|max:12',
            'anio'         => 'required|integer|min:2020|max:2099',
            'parametros'   => 'nullable|array',
        ]);

        $config = WhatsappConfig::paraAliado($alidoId);
        if (!$config->credencialesCompletas()) {
            return response()->json(['ok' => false, 'error' => 'No hay credenciales de WhatsApp configuradas.'], 422);
        }

        $effectiveAliadoId = $alidoId;
        if ($config->usa_cuenta_brynex) {
            $aliadoBrynex = \App\Models\Aliado::where('nombre', 'BryNex')->first();
            $effectiveAliadoId = $aliadoBrynex ? $aliadoBrynex->id : 1;
        }

        $plantilla = WhatsappPlantilla::delAliado($effectiveAliadoId)
            ->aprobadas()
            ->findOrFail($validated['plantilla_id']);

        // Obtener empresas seleccionadas con sus contactos
        $empresas = Empresa::whereIn('id', $validated['empresa_ids'])
            ->whereIn('id', function ($sub) use ($alidoId) {
                $sub->from('clientes')->select('cod_empresa')
                    ->where('aliado_id', $alidoId)->where('cod_empresa', '>', 1);
            })
            ->get();

        // Anti-spam: números ya enviados hoy con esta plantilla
        $hoy = now()->toDateString();
        $yaEnviados = DB::table('whatsapp_envios_masivos_detalle as d')
            ->join('whatsapp_envios_masivos as e', 'e.id', '=', 'd.envio_id')
            ->where('e.aliado_id', $alidoId)
            ->where('e.plantilla_id', $plantilla->id)
            ->whereDate('d.created_at', $hoy)
            ->where('d.estado', 'enviado')
            ->pluck('d.wa_numero')
            ->flip()
            ->all();

        $destinatarios = [];
        foreach ($empresas as $empresa) {
            // El contacto de la empresa es quien recibe el mensaje
            $numero = $this->normalizarNumero($empresa->celular ?? $empresa->telefono ?? '');
            if (!$numero) continue;
            if (isset($yaEnviados[$numero])) continue;

            $destinatarios[] = [
                'contrato_id'        => null,
                'empresa_id'         => $empresa->id,
                'wa_numero'          => $numero,
                'nombre_destinatario'=> $empresa->empresa,
            ];
        }

        if (empty($destinatarios)) {
            return response()->json([
                'ok'    => false,
                'error' => 'No hay destinatarios disponibles.',
            ], 422);
        }

        $envio = WhatsappEnvioMasivo::create([
            'aliado_id'           => $alidoId,
            'plantilla_id'        => $plantilla->id,
            'usuario_id'          => Auth::id(),
            'mes'                 => $validated['mes'],
            'anio'                => $validated['anio'],
            'tipo_envio'          => 'empresa',
            'total_destinatarios' => count($destinatarios),
            'estado'              => 'pendiente',
            'parametros_json'     => $validated['parametros'] ?? [],
        ]);

        foreach ($destinatarios as $dest) {
            WhatsappEnvioMasivoDetalle::create(array_merge($dest, [
                'envio_id' => $envio->id,
                'estado'   => 'pendiente',
            ]));
        }

        // Obtener la URL de la imagen del header desde la petición HTTP actual
        $headerImageUrl = null;
        if ($config->cobro_header_imagen) {
            $headerImageUrl = url('storage/' . $config->cobro_header_imagen);
        }

        dispatch(new WhatsappEnvioMasivoJob($envio->id, $validated['parametros'] ?? [], $headerImageUrl));

        return response()->json([
            'ok'      => true,
            'mensaje' => "Envío en proceso: {$envio->total_destinatarios} mensajes siendo enviados.",
            'envio_id'=> $envio->id,
        ]);
    }

    /**
     * Historial de envíos masivos del aliado.
     */
    public function historial()
    {
        $alidoId = session('aliado_id_activo');

        $envios = WhatsappEnvioMasivo::where('aliado_id', $alidoId)
            ->with(['plantilla:id,nombre_display', 'usuario:id,nombre'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.whatsapp.masivo.historial', compact('envios'));
    }

    /**
     * Detalle de un envío masivo específico.
     */
    public function detalle(int $id)
    {
        $alidoId = session('aliado_id_activo');
        $envio   = WhatsappEnvioMasivo::where('aliado_id', $alidoId)
            ->with(['plantilla', 'usuario', 'detalles'])
            ->findOrFail($id);

        return view('admin.whatsapp.masivo.detalle', compact('envio'));
    }

    // ── Helpers privados ─────────────────────────────────────────────

    private function obtenerContratosPendientesIndividuales(int $alidoId, int $mes, int $anio): \Illuminate\Support\Collection
    {
        // Obtener cédulas que ya pagaron este mes
        $cedulasPagadas = Factura::where('aliado_id', $alidoId)
            ->periodo($mes, $anio)
            ->whereIn('tipo', ['planilla', 'afiliacion'])
            ->whereIn('estado', ['pagada', 'prestamo'])
            ->whereNull('deleted_at')
            ->pluck('cedula')
            ->flip()
            ->all();

        // Contratos individuales vigentes sin pago
        return DB::table('contratos as c')
            ->join('clientes as cl', function ($j) use ($alidoId) {
                $j->on('cl.cedula', '=', 'c.cedula')
                  ->where('cl.aliado_id', $alidoId)
                  ->where(function ($q) {
                      $q->where('cl.cod_empresa', 1)->orWhereNull('cl.cod_empresa');
                  });
            })
            ->where('c.aliado_id', $alidoId)
            ->whereIn('c.estado', ['vigente', 'activo'])
            ->whereNotIn('c.cedula', array_keys($cedulasPagadas))
            ->select(
                'c.id', 'c.cedula',
                'cl.celular',
                DB::raw("TRIM(ISNULL(cl.primer_nombre,'') + ' ' + ISNULL(cl.primer_apellido,'')) AS nombre_cliente")
            )
            ->get();
    }

    private function normalizarNumero(string $numero): ?string
    {
        $numero = preg_replace('/[^0-9]/', '', $numero);
        if (strlen($numero) < 7) return null;
        if (!str_starts_with($numero, '57') && strlen($numero) === 10) {
            $numero = '57' . $numero;
        }
        return '+' . $numero;
    }

    private function authorizarAdmin(): void
    {
        abort_unless(Auth::user()->hasRole(['admin', 'superadmin']), 403);
    }
}
