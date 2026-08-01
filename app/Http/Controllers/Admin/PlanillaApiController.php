<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{OperadorCredencial, OperadorPlanilla, OperadorPlanillaApi, RazonSocial};
use App\Services\PlanoPilaTxtService;
use App\Services\SuaporteApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Liquidación de planillas PILA contra las APIs de los operadores.
 * Cubre los que corren la plataforma Enlace Operativo — hoy ARUS Enlace y
 * Simple, que exponen exactamente los mismos endpoints en distinto dominio.
 *
 * El flujo reemplaza el paso manual de descargar el TXT y subirlo al portal
 * del operador: se genera el plano en memoria, se envía a validar y, si queda
 * limpio, se guarda el número de planilla y la URL de pago PSE.
 */
class PlanillaApiController extends Controller
{
    /**
     * Estado de la integración para una razón social: qué operadores tienen
     * credenciales configuradas y si ya hay planilla liquidada del periodo.
     */
    public function estado(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'razon_social_id' => 'required|integer',
            'mes'             => 'required|integer|min:1|max:12',
            'anio'            => 'required|integer|min:2000|max:2100',
            'n_plano'         => 'required|integer|min:1',
        ]);

        $operadores = [];

        foreach ($this->operadoresConApi($aliadoId) as $operador) {
            $credencial = $this->credencial($aliadoId, $operador->id, (int) $validated['razon_social_id']);

            // Solo se ofrecen los que ya tienen credencial cargada.
            if (!$credencial) {
                continue;
            }

            $planilla = OperadorPlanillaApi::where('aliado_id', $aliadoId)
                ->where('razon_social_id', $validated['razon_social_id'])
                ->where('operador_planilla_id', $operador->id)
                ->where('anio', $validated['anio'])
                ->where('mes', $validated['mes'])
                ->where('n_plano', $validated['n_plano'])
                ->latest('id')
                ->first();

            $operadores[] = [
                'id'            => $operador->id,
                'nombre'        => $operador->nombre,
                'codigo'        => $operador->codigo,
                'clave_vencida' => $credencial->claveSecretaVencida(),
                'sin_codigo_ni' => empty($operador->codigo_ni),
                'planilla'      => $planilla ? [
                    'estado'          => $planilla->estado,
                    'numero_planilla' => $planilla->numero_planilla,
                    'valor_total'     => $planilla->valor_total,
                    'url_pago'        => $planilla->url_pago,
                    'mensaje_error'   => $planilla->mensaje_error,
                    'fecha'           => optional($planilla->updated_at)->format('Y-m-d H:i'),
                ] : null,
            ];
        }

        return response()->json([
            'disponible' => count($operadores) > 0,
            'motivo'     => $operadores ? null : 'Ninguna razón social tiene credenciales de operador configuradas.',
            'operadores' => $operadores,
        ]);
    }

    /**
     * Genera el plano y lo liquida en Enlace: validación → totales → URL PSE.
     */
    public function liquidar(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'razon_social_id'      => 'required|integer',
            'operador_planilla_id' => 'required|integer',
            'mes'                  => 'required|integer|min:1|max:12',
            'anio'                 => 'required|integer|min:2000|max:2100',
            'n_plano'              => 'required|integer|min:1',
            'tipos_modalidad'      => 'array',
            'tipos_modalidad.*'    => 'integer',
            'solo_novedades'       => 'boolean',
        ]);

        // Multi-tenant: la razón social debe ser del aliado activo.
        $rs = RazonSocial::where('aliado_id', $aliadoId)
            ->find($validated['razon_social_id']);

        if (!$rs) {
            return response()->json(['success' => false, 'message' => 'Razón social no encontrada.'], 404);
        }

        if (empty($rs->nit)) {
            return response()->json([
                'success' => false,
                'message' => "La razón social {$rs->razon_social} no tiene NIT configurado.",
            ], 422);
        }

        $operador = $this->operadoresConApi($aliadoId)
            ->firstWhere('id', (int) $validated['operador_planilla_id']);

        if (!$operador) {
            return response()->json([
                'success' => false,
                'message' => 'Ese operador no está activo para este aliado o no tiene integración por API.',
            ], 422);
        }

        // El código del operador va en el registro tipo 1 del archivo plano
        // (pos. 358-359). Sin él, el operador rechaza la planilla.
        if (empty($operador->codigo_ni)) {
            return response()->json([
                'success' => false,
                'message' => "Falta el código PILA de {$operador->nombre}. Configúrelo en Configuración → Operadores de planilla antes de liquidar.",
            ], 422);
        }

        $credencial = $this->credencial($aliadoId, $operador->id, (int) $rs->id);

        if (!$credencial) {
            return response()->json([
                'success' => false,
                'message' => "No hay credenciales de {$operador->nombre} para esta razón social. Configúrelas antes de liquidar.",
            ], 422);
        }

        if ($credencial->claveSecretaVencida()) {
            return response()->json([
                'success' => false,
                'message' => "La clave secreta de {$operador->nombre} venció. Genere una nueva desde el tablero del operador.",
            ], 422);
        }

        // ── 1. Generar el archivo plano en memoria ───────────────────────
        try {
            $plano = (new PlanoPilaTxtService())->construir([
                'aliado_id'       => $aliadoId,
                'razon_social_id' => $rs->id,
                'mes'             => $validated['mes'],
                'anio'            => $validated['anio'],
                'n_plano'         => $validated['n_plano'],
                'tipos_modalidad' => $validated['tipos_modalidad'] ?? [],
                'codigo_operador' => (string) $operador->codigo_ni,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Enlace API: error al construir el plano', [
                'razon_social_id' => $rs->id,
                'message'         => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el archivo plano: ' . $e->getMessage(),
            ], 500);
        }

        // ── 2. Registro de trazabilidad ──────────────────────────────────
        $registro = OperadorPlanillaApi::updateOrCreate(
            [
                'aliado_id'            => $aliadoId,
                'razon_social_id'      => $rs->id,
                'operador_planilla_id' => $operador->id,
                'anio'                 => $validated['anio'],
                'mes'                  => $validated['mes'],
                'n_plano'              => $validated['n_plano'],
            ],
            ['estado' => 'procesando', 'mensaje_error' => null]
        );

        // ── 3. Liquidar contra el operador ───────────────────────────────
        $api = new SuaporteApiService([
            'operador'      => $operador->codigo, // define el host de la plataforma
            'usuario'       => $credencial->usuario,
            'contrasena'    => $credencial->contrasena,
            'clave_secreta' => $credencial->clave_secreta,
        ]);

        $resultado = $api->liquidarPlanilla($rs->nit, $plano['contenido'], $plano['filename'], [
            'planillaNSoloNovedades' => (bool) ($validated['solo_novedades'] ?? false),
            'tipoArchivo'            => 'I',
        ]);

        // ── 4. Persistir el resultado ────────────────────────────────────
        if (!($resultado['success'] ?? false)) {
            $registro->update([
                'estado'        => 'error',
                'mensaje_error' => $resultado['message'] ?? 'Error desconocido.',
                'response_log'  => $resultado['response'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'paso'    => $resultado['paso'] ?? null,
                'message' => $resultado['message'] ?? 'No fue posible liquidar la planilla.',
            ], 422);
        }

        // Planilla con errores: Enlace la crea pero sin número, para corregir.
        if (!($resultado['liquidada'] ?? false)) {
            $registro->update([
                'estado'          => 'con_errores',
                'api_planilla_id' => $resultado['codigo_planilla'] ?? null,
                'mensaje_error'   => "La planilla tiene {$resultado['total_errores']} error(es).",
                'response_log'    => $resultado['response'] ?? null,
            ]);

            return response()->json([
                'success'          => true,
                'liquidada'        => false,
                'codigo_planilla'  => $resultado['codigo_planilla'] ?? null,
                'total_errores'    => $resultado['total_errores'] ?? 0,
                'errores_cotizante'=> $resultado['errores_cotizante'] ?? [],
                'errores_empresa'  => $resultado['errores_empresa'] ?? [],
                'advertencias'     => $resultado['advertencias'] ?? [],
                'message'          => 'El archivo tiene errores. Corríjalos y vuelva a liquidar.',
            ]);
        }

        $registro->update([
            'estado'          => 'validada',
            'api_planilla_id' => $resultado['codigo_planilla'] ?? null,
            'numero_planilla' => $resultado['numero_planilla'] ?? null,
            'valor_total'     => $resultado['totales']['total_pagar'] ?? null,
            'url_pago'        => $resultado['url_pago'] ?? null,
            'mensaje_error'   => null,
            'response_log'    => $resultado['response'] ?? null,
        ]);

        return response()->json([
            'success'         => true,
            'liquidada'       => true,
            'numero_planilla' => $resultado['numero_planilla'],
            'codigo_planilla' => $resultado['codigo_planilla'] ?? null,
            'valor_total'     => $resultado['totales']['total_pagar'] ?? null,
            'valor_mora'      => $resultado['totales']['valor_mora'] ?? null,
            'fecha_limite'    => $resultado['totales']['fecha_limite'] ?? null,
            'url_pago'        => $resultado['url_pago'] ?? null,
            'advertencias'    => $resultado['advertencias'] ?? [],
            'message'         => "Planilla {$resultado['numero_planilla']} liquidada en Enlace Operativo.",
        ]);
    }

    /**
     * Liquidación puntual de UN contratista independiente (fila de `planos`),
     * no de un lote de empresa. Todos los independientes de un aliado
     * comparten la misma razón social genérica ("INDEPENDIENTE"), así que
     * cada uno se liquida por su cuenta con su propia cédula como aportante
     * — ver PlanoPilaTxtService::construir() con `plano_id`.
     */
    public function liquidarIndependiente(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'plano_id'             => 'required|integer',
            'operador_planilla_id' => 'required|integer',
        ]);

        $plano = DB::table('planos')
            ->where('id', $validated['plano_id'])
            ->where('aliado_id', $aliadoId)
            ->whereNull('deleted_at')
            ->first();

        if (!$plano) {
            return response()->json(['success' => false, 'message' => 'Registro no encontrado.'], 404);
        }

        if (!empty($plano->numero_planilla)) {
            return response()->json(['success' => false, 'message' => 'Este registro ya tiene una planilla liquidada.'], 422);
        }

        $rs = RazonSocial::where('aliado_id', $aliadoId)->find($plano->razon_social_id);
        if (!$rs || !$rs->es_independiente) {
            return response()->json(['success' => false, 'message' => 'Este registro no pertenece a la razón social de independientes.'], 422);
        }

        $operador = $this->operadoresConApi($aliadoId)
            ->firstWhere('id', (int) $validated['operador_planilla_id']);

        if (!$operador) {
            return response()->json([
                'success' => false,
                'message' => 'Ese operador no está activo para este aliado o no tiene integración por API.',
            ], 422);
        }

        if (empty($operador->codigo_ni)) {
            return response()->json([
                'success' => false,
                'message' => "Falta el código PILA de {$operador->nombre}. Configúrelo en Configuración → Operadores de planilla antes de liquidar.",
            ], 422);
        }

        $credencial = $this->credencial($aliadoId, $operador->id, (int) $rs->id);

        if (!$credencial) {
            return response()->json([
                'success' => false,
                'message' => "No hay credenciales de {$operador->nombre} configuradas para este aliado.",
            ], 422);
        }

        if ($credencial->claveSecretaVencida()) {
            return response()->json([
                'success' => false,
                'message' => "La clave secreta de {$operador->nombre} venció. Genere una nueva desde el tablero del operador.",
            ], 422);
        }

        // El período que espera construir() es "mes de pago"; el plano guarda
        // mes_plano/anio_plano ya sea como mes de pago (I Act, tm=11) o como
        // mes vencido (el resto) — mismo criterio que el resto del módulo.
        $tipoModId = (int) $plano->tipo_modalidad_id;
        if ($tipoModId === 11) {
            $mesPago = (int) $plano->mes_plano; $anioPago = (int) $plano->anio_plano;
        } else {
            $mesPago  = $plano->mes_plano == 12 ? 1 : (int) $plano->mes_plano + 1;
            $anioPago = $plano->mes_plano == 12 ? (int) $plano->anio_plano + 1 : (int) $plano->anio_plano;
        }

        try {
            $planoTxt = (new PlanoPilaTxtService())->construir([
                'aliado_id'       => $aliadoId,
                'razon_social_id' => $rs->id,
                'mes'             => $mesPago,
                'anio'            => $anioPago,
                'n_plano'         => (int) $plano->n_plano,
                'plano_id'        => $plano->id,
                'codigo_operador' => (string) $operador->codigo_ni,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Enlace API: error al construir el plano de independiente', [
                'plano_id' => $plano->id,
                'message'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el archivo plano: ' . $e->getMessage(),
            ], 500);
        }

        $registro = OperadorPlanillaApi::updateOrCreate(
            [
                'aliado_id'            => $aliadoId,
                'razon_social_id'      => $rs->id,
                'plano_id'             => $plano->id,
                'operador_planilla_id' => $operador->id,
            ],
            ['anio' => $anioPago, 'mes' => $mesPago, 'n_plano' => $plano->n_plano, 'estado' => 'procesando', 'mensaje_error' => null]
        );

        $nombreCotizante = trim($plano->primer_nombre . ' ' . $plano->primer_ape);

        $api = new SuaporteApiService([
            'operador'      => $operador->codigo,
            'usuario'       => $credencial->usuario,
            'contrasena'    => $credencial->contrasena,
            'clave_secreta' => $credencial->clave_secreta,
        ]);

        $mapaDoc = ['C' => 'CC', 'NIT' => 'CC', 'PT' => 'CE', 'NUIP' => 'CC'];
        $tipoDoc = $mapaDoc[strtoupper(trim($plano->tipo_doc ?? 'CC'))] ?? strtoupper(trim($plano->tipo_doc ?? 'CC'));

        // Datos de contacto para registrar al contratista como aportante
        // independiente en Enlace si todavía no existe ahí (ver
        // SuaporteApiService::crearAportanteIndependiente). codigoMunicipio
        // = DIVIPOLA depto(2) + municipio(3) + '000', igual que exige
        // el formulario web de Enlace.
        $cliente = DB::table('clientes')
            ->where('cedula', $plano->no_identifi)
            ->where('aliado_id', $aliadoId)
            ->first();

        $contactoAportante = [];
        if ($cliente) {
            $depCod  = $cliente->departamento_id ? str_pad((string) $cliente->departamento_id, 2, '0', STR_PAD_LEFT) : '';
            $munPila = $cliente->municipio_id
                ? DB::table('ciudades')->where('id_ciudad_t', $cliente->municipio_id)->value('Municipio')
                : null;
            $munCod  = ($depCod && $munPila !== null)
                ? $depCod . str_pad((string) $munPila, 3, '0', STR_PAD_LEFT) . '000'
                : '';

            $contactoAportante = [
                'correo'              => $cliente->correo ?? '',
                'telefono'            => $cliente->telefono ?? '',
                'celular'             => $cliente->celular ?? '',
                'codigo_departamento' => $depCod,
                'codigo_municipio'    => $munCod,
                'direccion'           => $cliente->direccion_vivienda ?? '',
            ];
        }

        $resultado = $api->liquidarPlanilla($plano->no_identifi, $planoTxt['contenido'], $planoTxt['filename'], [
            'tipo_documento'     => $tipoDoc,
            'crear_si_no_existe' => true,
            'nombre_aportante'   => $nombreCotizante,
            'contacto_aportante' => $contactoAportante,
            'tipoArchivo'        => 'I',
        ]);

        if (!($resultado['success'] ?? false)) {
            $registro->update([
                'estado'        => 'error',
                'mensaje_error' => $resultado['message'] ?? 'Error desconocido.',
                'response_log'  => $resultado['response'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'paso'    => $resultado['paso'] ?? null,
                'message' => $resultado['message'] ?? 'No fue posible liquidar la planilla.',
            ], 422);
        }

        if (!($resultado['liquidada'] ?? false)) {
            $registro->update([
                'estado'          => 'con_errores',
                'api_planilla_id' => $resultado['codigo_planilla'] ?? null,
                'mensaje_error'   => "La planilla tiene {$resultado['total_errores']} error(es).",
                'response_log'    => $resultado['response'] ?? null,
            ]);

            return response()->json([
                'success'          => true,
                'liquidada'        => false,
                'codigo_planilla'  => $resultado['codigo_planilla'] ?? null,
                'total_errores'    => $resultado['total_errores'] ?? 0,
                'errores_cotizante'=> $resultado['errores_cotizante'] ?? [],
                'errores_empresa'  => $resultado['errores_empresa'] ?? [],
                'message'          => 'El archivo tiene errores. Corríjalos y vuelva a liquidar.',
            ]);
        }

        $registro->update([
            'estado'          => 'validada',
            'api_planilla_id' => $resultado['codigo_planilla'] ?? null,
            'numero_planilla' => $resultado['numero_planilla'] ?? null,
            'valor_total'     => $resultado['totales']['total_pagar'] ?? null,
            'url_pago'        => $resultado['url_pago'] ?? null,
            'mensaje_error'   => null,
            'response_log'    => $resultado['response'] ?? null,
        ]);

        return response()->json([
            'success'         => true,
            'liquidada'       => true,
            'numero_planilla' => $resultado['numero_planilla'],
            'valor_total'     => $resultado['totales']['total_pagar'] ?? null,
            'valor_mora'      => $resultado['totales']['valor_mora'] ?? null,
            'fecha_limite'    => $resultado['totales']['fecha_limite'] ?? null,
            'url_pago'        => $resultado['url_pago'] ?? null,
            'message'         => "Planilla {$resultado['numero_planilla']} liquidada en {$operador->nombre} para {$nombreCotizante}.",
        ]);
    }

    /**
     * Detalle paginado de inconsistencias de una planilla con errores.
     * La validación solo devuelve las primeras 100 líneas.
     */
    public function inconsistencias(Request $request, int $codigoPlanilla)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'razon_social_id'  => 'required|integer',
            'registro_inicial' => 'integer|min:0',
            'limite'           => 'integer|min:1|max:500',
        ]);

        // El código de planilla debe pertenecer a una liquidación del aliado.
        $registro = OperadorPlanillaApi::where('aliado_id', $aliadoId)
            ->where('razon_social_id', $validated['razon_social_id'])
            ->where('api_planilla_id', $codigoPlanilla)
            ->first();

        if (!$registro) {
            return response()->json(['success' => false, 'message' => 'Planilla no encontrada.'], 404);
        }

        $rs = RazonSocial::where('aliado_id', $aliadoId)->find($validated['razon_social_id']);

        // El operador sale del propio registro de la liquidación.
        $operador = $this->operadoresConApi($aliadoId)
            ->firstWhere('id', (int) $registro->operador_planilla_id);

        if (!$rs || !$operador) {
            return response()->json(['success' => false, 'message' => 'Configuración incompleta.'], 422);
        }

        $credencial = $this->credencial($aliadoId, $operador->id, (int) $rs->id);

        if (!$credencial) {
            return response()->json(['success' => false, 'message' => "Faltan credenciales de {$operador->nombre}."], 422);
        }

        $api = new SuaporteApiService([
            'operador'      => $operador->codigo,
            'usuario'       => $credencial->usuario,
            'contrasena'    => $credencial->contrasena,
            'clave_secreta' => $credencial->clave_secreta,
        ]);

        // Las inconsistencias también exigen sesión + autorización.
        $auth = $api->autenticar();
        if (!$auth['success']) {
            return response()->json(['success' => false, 'message' => $auth['message']], 422);
        }

        $nit       = preg_replace('/\D/', '', (string) $rs->nit);
        $aportante = $api->consultarAportante('NI', $nit);
        if (!$aportante['success']) {
            return response()->json(['success' => false, 'message' => $aportante['message']], 422);
        }

        $autorizacion = $api->autorizar($aportante['id'], 'NI', $nit);
        if (!$autorizacion['success']) {
            return response()->json(['success' => false, 'message' => $autorizacion['message']], 422);
        }

        $resultado = $api->consultarInconsistencias(
            $codigoPlanilla,
            (int) ($validated['registro_inicial'] ?? 0),
            (int) ($validated['limite'] ?? 100)
        );

        return response()->json($resultado, $resultado['success'] ? 200 : 422);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Operadores del aliado que corren sobre la plataforma Enlace Operativo
     * (hoy ARUS Enlace y Simple, ver SuaporteApiService::HOSTS).
     */
    private function operadoresConApi(int $aliadoId)
    {
        return OperadorPlanilla::paraAliado($aliadoId)
            ->whereIn('codigo', array_keys(SuaporteApiService::HOSTS))
            ->get();
    }

    /** Credencial de la razón social, o la general del aliado. */
    private function credencial(int $aliadoId, int $operadorId, ?int $razonSocialId): ?OperadorCredencial
    {
        return OperadorCredencial::paraOperador($aliadoId, $operadorId, $razonSocialId)->first();
    }
}
