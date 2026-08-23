<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{OperadorCredencial, OperadorPlanilla, OperadorPlanillaApi, RazonSocial};
use App\Services\CorreccionEnlaceService;
use App\Services\CorreccionPensionFaltanteService;
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
            'razon_social_id'   => 'required|integer',
            'mes'               => 'required|integer|min:1|max:12',
            'anio'              => 'required|integer|min:2000|max:2100',
            'n_plano'           => 'required|integer|min:1',
            'tipos_modalidad'   => 'array',
            'tipos_modalidad.*' => 'integer',
        ]);

        $filtro = $this->filtroModalidades($validated['tipos_modalidad'] ?? []);

        $operadores = [];

        foreach ($this->operadoresConApi($aliadoId) as $operador) {
            $credencial = $this->credencial($aliadoId, $operador->id, (int) $validated['razon_social_id']);

            // Solo se ofrecen los que ya tienen credencial cargada.
            if (!$credencial) {
                continue;
            }

            // El filtro de modalidades forma parte de la identidad: la planilla
            // de los K y la de los E son dos planillas distintas de la misma
            // tanda, y mostrar la de la otra confundía más que ayudar.
            $planilla = OperadorPlanillaApi::where('aliado_id', $aliadoId)
                ->where('razon_social_id', $validated['razon_social_id'])
                ->where('operador_planilla_id', $operador->id)
                ->where('anio', $validated['anio'])
                ->where('mes', $validated['mes'])
                ->where('n_plano', $validated['n_plano'])
                ->whereRaw("ISNULL(tipos_modalidad, '') = ?", [$filtro])
                ->latest('id')
                ->first();

            // Si no hay ninguna con ESTE filtro, puede haberla de la misma
            // tanda liquidada con otro. Antes el bloque simplemente se quedaba
            // callado y parecía que nunca se había liquidado: basta marcar una
            // modalidad de más en la pantalla —aunque no aporte a nadie— para
            // dejar de reconocer la planilla que ya existe. No se devuelve como
            // `planilla` porque cubre a otra gente y su valor no es el de este
            // filtro; se devuelve aparte, solo para avisarlo.
            $otrosFiltros = collect();
            if (! $planilla) {
                $otrosFiltros = OperadorPlanillaApi::where('aliado_id', $aliadoId)
                    ->where('razon_social_id', $validated['razon_social_id'])
                    ->where('operador_planilla_id', $operador->id)
                    ->where('anio', $validated['anio'])
                    ->where('mes', $validated['mes'])
                    ->where('n_plano', $validated['n_plano'])
                    ->where('estado', 'validada')
                    ->orderBy('id')
                    ->get();
            }

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
                'planillas_tanda' => $otrosFiltros->map(fn ($p) => [
                    'numero_planilla' => $p->numero_planilla,
                    'valor_total'     => $p->valor_total,
                    'url_pago'        => $p->url_pago,
                    'fecha'           => optional($p->updated_at)->format('Y-m-d H:i'),
                    'modalidades'     => $this->nombresModalidades($p->tipos_modalidad),
                ])->values(),
            ];
        }

        return response()->json([
            'disponible' => count($operadores) > 0,
            'motivo'     => $operadores ? null : 'Ninguna razón social tiene credenciales de operador configuradas.',
            'operadores' => $operadores,
            'pendientes' => $this->pendientesDelPeriodo(
                $aliadoId, (int) $validated['razon_social_id'],
                (int) $validated['mes'], (int) $validated['anio']
            ),
        ]);
    }

    /**
     * Cuántos contratos vigentes de la razón social todavía no entran a
     * ninguna planilla del período. Es lo que Enlace reclama con la
     * advertencia `eo.val.2.270`, y sirve para saber, al liquidar la última
     * tanda, si de verdad quedó todo cubierto. Ver CierrePeriodoService.
     *
     * Solo para BryNex, igual que el informe: una razón social agrupa varias
     * empresas cliente, así que el número suelto siembra dudas en el aliado.
     * Devuelve null cuando no aplica y la vista no pinta nada.
     */
    private function pendientesDelPeriodo(int $aliadoId, int $razonSocialId, int $mes, int $anio): ?array
    {
        if (!\Illuminate\Support\Facades\Auth::user()?->can('brynex_cierre.ver')) {
            return null;
        }

        $total = (new \App\Services\CierrePeriodoService())
            ->contarPendientes($aliadoId, $razonSocialId, $mes, $anio);

        return [
            'total' => $total,
            'url'   => route('admin.informes.validacion_cierre', [
                'mes' => $mes, 'anio' => $anio, 'razon_social_id' => $razonSocialId,
            ]),
        ];
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
            // El usuario ya vio que hay una planilla liquidada y aun así quiere
            // volver a liquidar (ver el 409 más abajo).
            'reliquidar'           => 'boolean',
        ]);

        $filtro = $this->filtroModalidades($validated['tipos_modalidad'] ?? []);

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

        // ── 1. ¿Esta tanda ya tiene planilla? ────────────────────────────
        // Se pregunta ANTES de tocar nada: si el usuario cancela en el 409, no
        // se corrigieron datos ni se generó archivo por una liquidación que
        // nunca ocurrió.
        //
        // La llave incluye el filtro de modalidades: sin él, liquidar los K
        // pisaba el registro de los E y se perdía su número de planilla.
        $llave = [
            'aliado_id'            => $aliadoId,
            'razon_social_id'      => $rs->id,
            'operador_planilla_id' => $operador->id,
            'anio'                 => $validated['anio'],
            'mes'                  => $validated['mes'],
            'n_plano'              => $validated['n_plano'],
            'tipos_modalidad'      => $filtro,
        ];

        // Re-liquidar borra el número anterior, así que no puede pasar de
        // largo: se devuelve 409 con el número que se va a perder y el front
        // reintenta con `reliquidar` si el usuario confirma.
        //
        // El aviso es a propósito más amplio que la llave: también entran los
        // registros anteriores a la columna `tipos_modalidad`, que están en
        // NULL y de los que no se sabe con qué filtro se liquidaron. Tratarlos
        // como "de cualquier filtro" hace que avisen de más una vez, en vez de
        // dejar liquidar dos veces la misma tanda y duplicar la planilla en el
        // operador —que cuesta dinero de verdad—.
        $previo = OperadorPlanillaApi::where('aliado_id', $aliadoId)
            ->where('razon_social_id', $rs->id)
            ->where('operador_planilla_id', $operador->id)
            ->where('anio', $validated['anio'])
            ->where('mes', $validated['mes'])
            ->where('n_plano', $validated['n_plano'])
            ->where('estado', 'validada')
            ->where(function ($q) use ($filtro) {
                $q->whereNull('tipos_modalidad')->orWhere('tipos_modalidad', $filtro);
            })
            ->latest('id')
            ->first();

        if ($previo && !($validated['reliquidar'] ?? false)) {
            // Solo se pisa el registro si el filtro coincide exactamente. Si el
            // anterior es de otro filtro —o es uno viejo, sin filtro guardado—
            // lo que se arriesga es duplicar la planilla en el operador, que es
            // un problema distinto y hay que decirlo distinto.
            $reemplaza = $previo->tipos_modalidad === $filtro;

            return response()->json([
                'success'               => false,
                'requiere_confirmacion' => true,
                'reemplaza'             => $reemplaza,
                'numero_planilla'       => $previo->numero_planilla,
                'valor_total'           => $previo->valor_total,
                'fecha'                 => optional($previo->updated_at)->format('Y-m-d H:i'),
                'message'               => $reemplaza
                    ? "Esta tanda ya tiene la planilla {$previo->numero_planilla} liquidada. "
                      ."Si vuelve a liquidar, ese número se reemplaza."
                    : "Esta tanda ya tiene la planilla {$previo->numero_planilla} liquidada con otro filtro. "
                      ."Si la gente que va en este archivo ya está en esa planilla, quedaría duplicada en el operador.",
            ], 409);
        }

        // ── 2. Fondo de pensión faltante ─────────────────────────────────
        // Quien va al archivo sin AFP no cotiza pensión, aunque su factura se
        // la haya cobrado. Se corrige aquí, antes de armar el TXT, para que la
        // planilla salga bien de una vez en lugar de liquidar de menos y tener
        // que anularla en el operador — ver CorreccionPensionFaltanteService.
        $pensionCorregida = (new CorreccionPensionFaltanteService())->corregir([
            'aliado_id'       => $aliadoId,
            'razon_social_id' => (int) $rs->id,
            'mes'             => (int) $validated['mes'],
            'anio'            => (int) $validated['anio'],
            'n_plano'         => (int) $validated['n_plano'],
        ], $validated['tipos_modalidad'] ?? []);

        if (!empty($pensionCorregida['aplicadas'])) {
            Log::info('Enlace API: fondo de pensión corregido antes de liquidar', [
                'razon_social_id' => $rs->id,
                'n_plano'         => $validated['n_plano'],
                'correcciones'    => $pensionCorregida['aplicadas'],
            ]);
        }

        // ── 3. Generar el archivo plano en memoria ───────────────────────
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

        // ── 4. Registro de trazabilidad ──────────────────────────────────
        $registro = OperadorPlanillaApi::updateOrCreate(
            $llave,
            ['estado' => 'procesando', 'mensaje_error' => null]
        );

        // ── 5. Liquidar contra el operador ───────────────────────────────
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

            $correcciones = (new CorreccionEnlaceService())
                ->interpretar($resultado['errores_cotizante'] ?? [], $aliadoId);

            return response()->json([
                'success'          => true,
                'liquidada'        => false,
                'codigo_planilla'  => $resultado['codigo_planilla'] ?? null,
                'total_errores'    => $resultado['total_errores'] ?? 0,
                'errores_cotizante'=> $resultado['errores_cotizante'] ?? [],
                'errores_empresa'  => $resultado['errores_empresa'] ?? [],
                'advertencias'     => $resultado['advertencias'] ?? [],
                'correcciones'     => $correcciones,
                'pension_corregida'=> $pensionCorregida['aplicadas'],
                'razon_social_id'  => $rs->id,
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
            'pension_corregida' => $pensionCorregida['aplicadas'],
            'pendientes'      => $this->pendientesDelPeriodo(
                $aliadoId, (int) $rs->id, (int) $validated['mes'], (int) $validated['anio']
            ),
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

        $operador = $this->operadoresApiIndependiente()
            ->firstWhere('id', (int) $validated['operador_planilla_id']);

        if (!$operador) {
            return response()->json([
                'success' => false,
                'message' => 'Ese operador no tiene integración por API.',
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
        // mes_plano/anio_plano ya sea como mes de pago (paga_mes_actual) o como
        // mes vencido (el resto) — mismo criterio que el resto del módulo.
        $tipoModId = (int) $plano->tipo_modalidad_id;
        if ($tipoModId === 11) {
            $mesPago = (int) $plano->mes_plano; $anioPago = (int) $plano->anio_plano;
        } else {
            $mesPago  = $plano->mes_plano == 12 ? 1 : (int) $plano->mes_plano + 1;
            $anioPago = $plano->mes_plano == 12 ? (int) $plano->anio_plano + 1 : (int) $plano->anio_plano;
        }

        // Mismo arreglo que en el lote de empresa: si la factura le cobró
        // pensión pero el registro va sin AFP, se le pone el fondo antes de
        // armar el TXT — ver CorreccionPensionFaltanteService.
        $pensionCorregida = (new CorreccionPensionFaltanteService())->corregir([
            'aliado_id'       => $aliadoId,
            'razon_social_id' => (int) $rs->id,
            'mes'             => $mesPago,
            'anio'            => $anioPago,
            'n_plano'         => (int) $plano->n_plano,
            'plano_id'        => $plano->id,
        ]);

        if (!empty($pensionCorregida['aplicadas'])) {
            Log::info('Enlace API: fondo de pensión corregido antes de liquidar (independiente)', [
                'plano_id'     => $plano->id,
                'correcciones' => $pensionCorregida['aplicadas'],
            ]);
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

        // PT (Permiso por Protección Temporal) es código PILA válido: no se traduce a CE
        $mapaDoc = ['C' => 'CC', 'NIT' => 'CC', 'NUIP' => 'CC'];
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

            $correcciones = (new CorreccionEnlaceService())
                ->interpretar($resultado['errores_cotizante'] ?? [], $aliadoId);

            return response()->json([
                'success'          => true,
                'liquidada'        => false,
                'codigo_planilla'  => $resultado['codigo_planilla'] ?? null,
                'total_errores'    => $resultado['total_errores'] ?? 0,
                'errores_cotizante'=> $resultado['errores_cotizante'] ?? [],
                'errores_empresa'  => $resultado['errores_empresa'] ?? [],
                'correcciones'     => $correcciones,
                'pension_corregida'=> $pensionCorregida['aplicadas'],
                'razon_social_id'  => $rs->id,
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
            'pension_corregida' => $pensionCorregida['aplicadas'],
            'message'         => "Planilla {$resultado['numero_planilla']} liquidada en {$operador->nombre} para {$nombreCotizante}.",
        ]);
    }

    /**
     * Le pide a Enlace que corrija los errores que su validación marcó como
     * autocorregibles y refleja el mismo cambio en Brynex.
     *
     * Es una acción aparte y explícita, no un paso automático de liquidar():
     * corregir solo del lado de Enlace dejaría el dato malo en el contrato y
     * el error volvería el mes siguiente, así que el usuario ve primero a
     * quién y qué se le va a cambiar (ver `correcciones` en liquidar()).
     */
    public function autocorregir(Request $request)
    {
        $aliadoId = session('aliado_id_activo');

        $validated = $request->validate([
            'codigo_planilla' => 'required|integer',
            'solo_novedades'  => 'boolean',
        ]);

        $registro = OperadorPlanillaApi::where('aliado_id', $aliadoId)
            ->where('api_planilla_id', $validated['codigo_planilla'])
            ->latest('id')
            ->first();

        if (!$registro) {
            return response()->json(['success' => false, 'message' => 'Planilla no encontrada.'], 404);
        }

        if ($registro->estado === 'validada') {
            return response()->json(['success' => false, 'message' => 'Esta planilla ya está liquidada.'], 422);
        }

        // Las correcciones se leen de la validación original: son las que el
        // usuario vio en pantalla antes de aceptar.
        $erroresPrevios = $registro->response_log['validacionPlanillas'][0]['erroresCotizantePlanilla'] ?? [];
        $servicio       = new CorreccionEnlaceService();
        $correcciones   = $servicio->interpretar($erroresPrevios, $aliadoId);

        if (!$correcciones) {
            return response()->json([
                'success' => false,
                'message' => 'Esta planilla no tiene errores que Enlace pueda autocorregir.',
            ], 422);
        }

        $sesion = $this->abrirSesion($registro, $aliadoId);

        if (!$sesion['success']) {
            return response()->json(['success' => false, 'message' => $sesion['message']], 422);
        }

        $resultado = $sesion['api']->corregirPlanilla((int) $validated['codigo_planilla'], [
            'planillaNSoloNovedades' => (bool) ($validated['solo_novedades'] ?? false),
            'tipoArchivo'            => 'I',
        ]);

        if (!($resultado['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $resultado['message'] ?? 'Enlace no pudo autocorregir la planilla.',
            ], 422);
        }

        // Enlace ya corrigió su lado: se replica en Brynex aunque queden otros
        // errores no autocorregibles, para no volver a arrastrar el dato malo.
        $aplicado = $servicio->aplicarEnBrynex($correcciones, [
            'aliado_id'       => $aliadoId,
            'razon_social_id' => $registro->razon_social_id,
            'n_plano'         => $registro->n_plano,
            'mes'             => $registro->mes,
            'anio'            => $registro->anio,
            'plano_id'        => $registro->plano_id,
        ]);

        Log::info('Enlace API: planilla autocorregida', [
            'aliado_id'       => $aliadoId,
            'codigo_planilla' => $validated['codigo_planilla'],
            'correcciones'    => count($aplicado['aplicadas']),
            'planos'          => $aplicado['planos'],
            'contratos'       => $aplicado['contratos'],
            'clientes'        => $aplicado['clientes'],
        ]);

        if (!($resultado['liquidada'] ?? false)) {
            $registro->update([
                'estado'        => 'con_errores',
                'mensaje_error' => "Quedan {$resultado['total_errores']} error(es) que Enlace no puede corregir.",
                'response_log'  => $resultado['response'] ?? null,
            ]);

            return response()->json([
                'success'          => true,
                'liquidada'        => false,
                'codigo_planilla'  => $resultado['codigo_planilla'] ?? null,
                'total_errores'    => $resultado['total_errores'] ?? 0,
                'errores_cotizante'=> $resultado['errores_cotizante'] ?? [],
                'errores_empresa'  => $resultado['errores_empresa'] ?? [],
                'advertencias'     => $resultado['advertencias'] ?? [],
                'correcciones'     => $servicio->interpretar($resultado['errores_cotizante'] ?? [], $aliadoId),
                'aplicado'         => $aplicado,
                'message'          => "Quedan {$resultado['total_errores']} error(es) que Enlace no puede corregir.",
            ]);
        }

        // Quedó limpia: faltan los totales y la URL de pago, que la corrección
        // no devuelve.
        $totales = $sesion['api']->consultarTotales($resultado['numero_planilla']);
        $pago    = $sesion['api']->obtenerUrlPago($resultado['numero_planilla']);

        $registro->update([
            'estado'          => 'validada',
            'numero_planilla' => $resultado['numero_planilla'],
            'valor_total'     => $totales['total_pagar'] ?? null,
            'url_pago'        => $pago['url_pago'] ?? null,
            'mensaje_error'   => null,
            'response_log'    => $resultado['response'] ?? null,
        ]);

        return response()->json([
            'success'         => true,
            'liquidada'       => true,
            'numero_planilla' => $resultado['numero_planilla'],
            'codigo_planilla' => $resultado['codigo_planilla'] ?? null,
            'valor_total'     => $totales['total_pagar'] ?? null,
            'valor_mora'      => $totales['valor_mora'] ?? null,
            'fecha_limite'    => $totales['fecha_limite'] ?? null,
            'url_pago'        => $pago['url_pago'] ?? null,
            'advertencias'    => $resultado['advertencias'] ?? [],
            'aplicado'        => $aplicado,
            'message'         => "Planilla {$resultado['numero_planilla']} liquidada tras la autocorrección.",
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

        // Las inconsistencias también exigen sesión + autorización.
        $sesion = $this->abrirSesion($registro, $aliadoId);

        if (!$sesion['success']) {
            return response()->json(['success' => false, 'message' => $sesion['message']], 422);
        }

        $api = $sesion['api'];

        $resultado = $api->consultarInconsistencias(
            $codigoPlanilla,
            (int) ($validated['registro_inicial'] ?? 0),
            (int) ($validated['limite'] ?? 100)
        );

        return response()->json($resultado, $resultado['success'] ? 200 : 422);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Deja lista una sesión de Enlace autorizada sobre el aportante dueño de
     * una liquidación ya registrada: login → aportante → autorización.
     *
     * El aportante es la razón social (NI) salvo en los independientes, donde
     * cada contratista liquida con su propia cédula (ver liquidarIndependiente).
     *
     * @return array{success: bool, api?: SuaporteApiService, message?: string}
     */
    private function abrirSesion(OperadorPlanillaApi $registro, int $aliadoId): array
    {
        $rs = RazonSocial::where('aliado_id', $aliadoId)->find($registro->razon_social_id);

        // En independientes el operador lo trae el contratista, no el pivot
        // del aliado — mismo criterio que liquidarIndependiente().
        $operador = ($rs?->es_independiente
                ? $this->operadoresApiIndependiente()
                : $this->operadoresConApi($aliadoId))
            ->firstWhere('id', (int) $registro->operador_planilla_id);

        if (!$rs || !$operador) {
            return ['success' => false, 'message' => 'Configuración incompleta para esta planilla.'];
        }

        $credencial = $this->credencial($aliadoId, $operador->id, (int) $rs->id);

        if (!$credencial) {
            return ['success' => false, 'message' => "Faltan credenciales de {$operador->nombre}."];
        }

        if ($credencial->claveSecretaVencida()) {
            return ['success' => false, 'message' => "La clave secreta de {$operador->nombre} venció."];
        }

        // Independiente: el aportante es el contratista, no la razón social.
        $tipoDocumento = 'NI';
        $documento     = preg_replace('/\D/', '', (string) $rs->nit);

        if ($registro->plano_id) {
            $plano = DB::table('planos')
                ->where('id', $registro->plano_id)
                ->where('aliado_id', $aliadoId)
                ->first(['no_identifi', 'tipo_doc']);

            if (!$plano) {
                return ['success' => false, 'message' => 'No se encontró el registro del contratista.'];
            }

            // PT (Permiso por Protección Temporal) es código PILA válido: no se traduce a CE
            $mapaDoc       = ['C' => 'CC', 'NIT' => 'CC', 'NUIP' => 'CC'];
            $doc           = strtoupper(trim($plano->tipo_doc ?? 'CC'));
            $tipoDocumento = $mapaDoc[$doc] ?? ($doc ?: 'CC');
            $documento     = preg_replace('/\D/', '', (string) $plano->no_identifi);
        }

        $api = new SuaporteApiService([
            'operador'      => $operador->codigo,
            'usuario'       => $credencial->usuario,
            'contrasena'    => $credencial->contrasena,
            'clave_secreta' => $credencial->clave_secreta,
        ]);

        $auth = $api->autenticar();
        if (!$auth['success']) {
            return ['success' => false, 'message' => $auth['message']];
        }

        $aportante = $api->consultarAportante($tipoDocumento, $documento);
        if (!$aportante['success']) {
            return ['success' => false, 'message' => $aportante['message']];
        }

        $autorizacion = $api->autorizar($aportante['id'], $tipoDocumento, $documento);
        if (!$autorizacion['success']) {
            return ['success' => false, 'message' => $autorizacion['message']];
        }

        return ['success' => true, 'api' => $api];
    }

    /**
     * Operadores del aliado que corren sobre la plataforma Enlace Operativo
     * (hoy ARUS Enlace y Simple, ver SuaporteApiService::HOSTS).
     *
     * Respeta el pivot `aliado_operadores_planilla`: qué operadores usa el
     * aliado para las planillas de sus empresas.
     */
    private function operadoresConApi(int $aliadoId)
    {
        return OperadorPlanilla::paraAliado($aliadoId)
            ->whereIn('codigo', array_keys(SuaporteApiService::HOSTS))
            ->get();
    }

    /**
     * Lo mismo, pero para independientes: ahí el operador lo trae cada
     * contratista (`clientes.operador_planilla_id`), no la configuración del
     * aliado, así que el pivot NO aplica. Es el mismo criterio con el que
     * PlanoPagoController arma `$operadoresApiIds` y habilita el botón PSE de
     * la fila; si aquí se filtrara por pivot, el botón se vería habilitado y
     * el POST respondería "operador no activo para este aliado".
     */
    private function operadoresApiIndependiente()
    {
        return OperadorPlanilla::whereNull('aliado_id')
            ->where('activo', true)
            ->whereIn('codigo', array_keys(SuaporteApiService::HOSTS))
            ->orderBy('orden')
            ->get();
    }

    /**
     * El filtro de modalidades, normalizado para poder compararlo: ids únicos,
     * ordenados y unidos por coma. Cadena vacía cuando no hay filtro.
     *
     * Se normaliza porque el mismo filtro puede llegar en cualquier orden
     * desde la interfaz, y `[12,0]` y `[0,12]` son la misma planilla.
     */
    /**
     * Los ids guardados en `tipos_modalidad` traducidos a nombres, para poder
     * decirle al usuario con qué modalidades se liquidó una planilla en vez de
     * mostrarle "-6,0,12". Cadena vacía = sin filtro, o sea todas.
     */
    private function nombresModalidades(?string $csv): string
    {
        if (trim((string) $csv) === '') {
            return 'todas las modalidades';
        }

        // La modalidad 0 es un id válido, así que no se puede descartar el cero
        // que devuelve intval(): el filtro se aplica sobre las cadenas.
        $ids = array_map('intval', array_filter(array_map('trim', explode(',', $csv)), 'strlen'));

        $nombres = DB::table('tipo_modalidad')->whereIn('id', $ids)
            ->orderByRaw('CHARINDEX(CAST(id AS VARCHAR(10)), ?)', [$csv])
            ->pluck('tipo_modalidad', 'id');

        // Un id que no esté en el catálogo se muestra crudo antes que perderse.
        return implode(', ', array_map(fn ($id) => $nombres[$id] ?? "#$id", $ids));
    }

    private function filtroModalidades(array $tipos): string
    {
        $tipos = array_values(array_unique(array_map('intval', $tipos)));
        sort($tipos);

        return implode(',', $tipos);
    }

    /** Credencial de la razón social, o la general del aliado. */
    private function credencial(int $aliadoId, int $operadorId, ?int $razonSocialId): ?OperadorCredencial
    {
        return OperadorCredencial::paraOperador($aliadoId, $operadorId, $razonSocialId)->first();
    }
}
