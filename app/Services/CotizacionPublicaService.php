<?php

namespace App\Services;

use App\Models\ConfiguracionAliado;
use App\Models\ConfiguracionBrynex;
use App\Models\PlanContrato;
use App\Models\TipoModalidad;
use Carbon\Carbon;

/**
 * Lógica de cotización pública, extraída de App\Services\Ia\Tools\CotizarPlanTool para que la
 * página web pública del aliado y la tool de la IA (cotizar_plan) usen EXACTAMENTE el mismo
 * cálculo — mismos precios, mismo plan de pago inicial, sin duplicar la lógica en dos lugares
 * que puedan desincronizarse. CotizarPlanTool delega aquí y solo conserva lo específico de la
 * IA (parseo de modalidad en texto libre, mensajes de error conversacionales).
 */
class CotizacionPublicaService
{
    public const MODALIDAD_DEPENDIENTE_ID   = 0;
    public const MODALIDAD_INDEPENDIENTE_ID = 10; // "I Venc" — la misma que usa CotizarPlanTool para el plan de pago inicial

    /**
     * Combinaciones "destacadas" que se muestran como tarjetas en la sección de planes de la
     * página pública Y como presets de precio en vivo en el generador de publicidad. El
     * cotizador interactivo, en cambio, acepta CUALQUIER combinación (hay 11 activas en el
     * sistema) — estas 3 son solo los anclajes de marketing más comunes.
     */
    public const PLANES_DESTACADOS = [
        [
            'clave'         => 'dependiente_basico',
            'nombre'        => 'Dependiente Básico',
            'descripcion'   => 'Salud y riesgos laborales para empleados y trabajadores dependientes.',
            'componentes'   => ['incluye_eps' => true, 'incluye_arl' => true, 'incluye_pension' => false, 'incluye_caja' => false],
            'independiente' => false,
            'destacado'     => false,
        ],
        [
            'clave'         => 'dependiente_completo',
            'nombre'        => 'Dependiente Completo',
            'descripcion'   => 'Salud, riesgos laborales, pensión y caja de compensación — la cobertura más completa.',
            'componentes'   => ['incluye_eps' => true, 'incluye_arl' => true, 'incluye_pension' => true, 'incluye_caja' => true],
            'independiente' => false,
            'destacado'     => true,
        ],
        [
            'clave'         => 'independiente',
            'nombre'        => 'Independiente',
            'descripcion'   => 'Para quienes trabajan por cuenta propia y necesitan afiliación a salud.',
            'componentes'   => ['incluye_eps' => true, 'incluye_arl' => false, 'incluye_pension' => false, 'incluye_caja' => false],
            'independiente' => true,
            'destacado'     => false,
        ],
    ];

    /**
     * Calcula el valor mensual/afiliación en vivo de las 3 combinaciones destacadas para un
     * aliado. Usado por la página pública (planesDestacados) y por el generador de publicidad
     * (presets de precio real al armar una pieza de tipo "promo de precio").
     */
    public static function planesDestacadosConPrecio(int $aliadoId, bool $mostrarPrecios = true): \Illuminate\Support\Collection
    {
        $tarjetas = [];

        foreach (self::PLANES_DESTACADOS as $def) {
            [$plan, $exacto] = self::resolverPlan($def['componentes'], $def['independiente']);
            if (!$plan) {
                continue;
            }

            $modalidad = self::resolverModalidadPermitida($plan, $def['independiente']);
            if (!$modalidad) {
                continue;
            }

            $resultado = self::cotizar($plan, $modalidad, $aliadoId);

            $tarjetas[] = [
                'clave'            => $def['clave'],
                'nombre'           => $def['nombre'],
                'descripcion'      => $def['descripcion'],
                'destacado'        => $def['destacado'],
                'componentes'      => $def['componentes'],
                'independiente'    => $def['independiente'],
                'valor_mensual'           => $mostrarPrecios ? $resultado['total'] : null,
                'costo_afiliacion'        => $mostrarPrecios ? $resultado['costo_afiliacion_sugerido'] : null,
                'costo_afiliacion_normal' => $mostrarPrecios ? $resultado['costo_afiliacion_normal'] : null,
                'en_promocion'            => $resultado['en_promocion'],
                'promocion_vence'         => $resultado['promocion_vence'],
            ];
        }

        return collect($tarjetas);
    }

    /**
     * Busca el plan cuyos componentes coincidan EXACTAMENTE con lo pedido. Si no existe,
     * busca el plan más cercano que incluya AL MENOS lo pedido (superset con menos extras).
     *
     * "Solo EPS" (sin ARL/pensión/caja) NO es una combinación real bajo Dependiente: BRYGAR
     * afilia como dependiente mediante un esquema que exige ARL junto con la EPS. Esa
     * combinación solo aplica como Independiente (EPS sola, más cara al 12,5%) — por eso se
     * excluye de la búsqueda cuando la modalidad no es independiente, forzando el fallback a
     * "EPS + ARL" en vez de ofrecer algo que no se puede afiliar en la práctica.
     *
     * @return array{0: ?PlanContrato, 1: bool} [plan, esCoincidenciaExacta]
     */
    public static function resolverPlan(array $componentes, bool $esIndependiente): array
    {
        $query = PlanContrato::where('activo', true);
        if (!$esIndependiente) {
            $query->where(function ($q) {
                $q->where('incluye_eps', false)
                  ->orWhere('incluye_arl', true)
                  ->orWhere('incluye_pension', true)
                  ->orWhere('incluye_caja', true);
            });
        }

        $exacto = (clone $query)
            ->where('incluye_eps', $componentes['incluye_eps'])
            ->where('incluye_arl', $componentes['incluye_arl'])
            ->where('incluye_pension', $componentes['incluye_pension'])
            ->where('incluye_caja', $componentes['incluye_caja'])
            ->first();

        if ($exacto) {
            return [$exacto, true];
        }

        // Buscar planes que incluyan AL MENOS lo pedido (superset), y quedarnos con
        // el que tenga menos componentes extra (el más ajustado a lo pedido).
        $candidatos = $query->get()->filter(function ($p) use ($componentes) {
            return (!$componentes['incluye_eps']     || $p->incluye_eps)
                && (!$componentes['incluye_arl']     || $p->incluye_arl)
                && (!$componentes['incluye_pension'] || $p->incluye_pension)
                && (!$componentes['incluye_caja']    || $p->incluye_caja);
        });

        if ($candidatos->isEmpty()) {
            return [null, false];
        }

        $extras = fn ($p) => (int) $p->incluye_eps + (int) $p->incluye_arl + (int) $p->incluye_pension + (int) $p->incluye_caja;
        $mejor = $candidatos->sortBy($extras)->first();

        return [$mejor, false];
    }

    /** Modalidad usada por defecto según el perfil, para flujos que no dejan elegir la modalidad exacta (web pública). */
    public static function modalidadPorDefecto(bool $esIndependiente): ?TipoModalidad
    {
        return TipoModalidad::find($esIndependiente ? self::MODALIDAD_INDEPENDIENTE_ID : self::MODALIDAD_DEPENDIENTE_ID);
    }

    /**
     * Orden de preferencia al resolver la modalidad automáticamente. La modalidad es un
     * detalle INTERNO — el cliente solo dice si es empleado/independiente o si está en el
     * exterior; nunca se le pregunta "¿qué modalidad?". Si un plan no existe en las
     * modalidades del perfil pedido (ej. "Solo AFP" solo existe como "En el Exterior"),
     * se cae en cascada a la primera modalidad donde el plan SÍ es válido.
     */
    private const MODALIDAD_EXTERIOR_ID       = 14; // "En el Exterior"
    private const PRIORIDAD_INDEPENDIENTE_IDS = [10, 11, 13, 14];
    private const PRIORIDAD_DEPENDIENTE_IDS   = [0, 7];

    /**
     * Resuelve la modalidad correcta para un plan consultando modalidad_planes — la MISMA
     * tabla de permitidos que usa el cotizador del admin (admin/cotizaciones/create) — en vez
     * de asumir una modalidad fija. Evita cotizar combinaciones plan+modalidad que el negocio
     * no ofrece (ej. "Solo AFP" con Independiente Vencido, que no existe).
     */
    public static function resolverModalidadPermitida(PlanContrato $plan, bool $esIndependiente, bool $desdeExterior = false): ?TipoModalidad
    {
        $permitidas = \Illuminate\Support\Facades\DB::table('modalidad_planes')
            ->where('plan_id', $plan->id)
            ->pluck('tipo_modalidad_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        // Plan sin filas en modalidad_planes: conservar el comportamiento histórico.
        if (empty($permitidas)) {
            return self::modalidadPorDefecto($esIndependiente);
        }

        if ($desdeExterior && in_array(self::MODALIDAD_EXTERIOR_ID, $permitidas, true)) {
            return TipoModalidad::find(self::MODALIDAD_EXTERIOR_ID);
        }

        $prioridad = $esIndependiente
            ? self::PRIORIDAD_INDEPENDIENTE_IDS
            : array_merge(self::PRIORIDAD_DEPENDIENTE_IDS, self::PRIORIDAD_INDEPENDIENTE_IDS);

        foreach ($prioridad as $id) {
            if (in_array($id, $permitidas, true)) {
                $modalidad = TipoModalidad::find($id);
                if ($modalidad) {
                    return $modalidad;
                }
            }
        }

        // El plan solo existe en modalidades especiales (SimpleP, K, Y, ...) que no se
        // ofrecen por los canales públicos — mejor no cotizar que cotizar mal.
        return null;
    }

    /**
     * Cotiza un plan ya resuelto: valor mensual (CotizadorService, con administracion/admon_asesor
     * /seguro SIEMPRE de la config genérica del aliado — nunca de la fila por plan, ver nota en
     * ConfiguracionAliado::paraAliado) + costo de afiliación sugerido (config por plan, con
     * fallback a la genérica) + plan de pago inicial (mes 1 solo afiliación, mes 2 proporcional +
     * administración completa, mes 3 en adelante el valor mensual completo).
     *
     * Opciones: salario, nivel_arl, dias, fecha_afiliacion (string 'AAAA-MM-DD' o null = hoy).
     */
    public static function cotizar(PlanContrato $plan, TipoModalidad $tipoModalidad, ?int $aliadoId, array $opciones = []): array
    {
        $salario  = (float) ($opciones['salario'] ?? ConfiguracionBrynex::salarioMinimo());
        $nivelArl = (int) ($opciones['nivel_arl'] ?? 1);
        $dias     = (int) ($opciones['dias'] ?? 30);

        $cfgGeneral = ConfiguracionAliado::paraAliado($aliadoId);
        $cfgPlan    = ConfiguracionAliado::paraAliado($aliadoId, $plan->id);

        $resultado = CotizadorService::calcular([
            'tipo_modalidad_id' => $tipoModalidad->id,
            'plan_id'           => $plan->id,
            'n_arl'             => $nivelArl,
            'salario'           => $salario,
            'administracion'    => (float) ($cfgGeneral->administracion ?? 0),
            'admon_asesor'      => (float) ($cfgGeneral->admon_asesor ?? 0),
            'seguro'            => (float) ($cfgGeneral->seguro_valor ?? 0),
            'dias'              => $dias,
        ], $aliadoId);

        // costo_afiliacion SÍ varía por plan, pero además varía contrato a contrato dentro del
        // mismo plan en la práctica (el asesor lo ajusta al afiliar) — por eso se marca como
        // "sugerido", no como un cobro garantizado.
        //
        // Si hay una promoción vigente (plan-específica primero, luego general) reemplaza el
        // costo de afiliación normal — misma cascada plan→general que el precio normal, así
        // que web, WhatsApp y marketing ven SIEMPRE el mismo número, y al vencer vuelve solo.
        $cfgConPromo = collect([$cfgPlan, $cfgGeneral])->first(fn ($c) => $c?->promocionVigente());

        $resultado['costo_afiliacion_normal']    = (float) ($cfgPlan->costo_afiliacion ?? $cfgGeneral->costo_afiliacion ?? 0);
        $resultado['en_promocion']               = (bool) $cfgConPromo;
        $resultado['promocion_vence']            = $cfgConPromo?->promocion_vencimiento?->toDateString();
        $resultado['costo_afiliacion_sugerido']  = $cfgConPromo
            ? (float) $cfgConPromo->promocion_costo_afiliacion
            : $resultado['costo_afiliacion_normal'];

        try {
            $fechaAfiliacion = !empty($opciones['fecha_afiliacion']) ? Carbon::parse($opciones['fecha_afiliacion']) : now();
        } catch (\Throwable $e) {
            $fechaAfiliacion = now();
        }
        $resultado['fecha_afiliacion'] = $fechaAfiliacion->toDateString();

        // Plan de pago inicial (gancho de venta): replica EXACTO el esquema real de facturación
        // (CobroContratoService::calcular/calcularDias) para quien se afilia hoy —
        // mes 1 = SOLO el cobro de afiliación (sin SS ni administración); mes 2 = proporcional de
        // los días restantes del mes vencido + administración COMPLETA (nunca se prorratea); mes
        // 3 en adelante = mes completo. Solo aplica al esquema de "afiliación pura" (Dependiente e
        // Independiente Vencido); Independiente Activo (id 11) cobra proporcional + administración
        // + afiliación TODO junto desde el mes de ingreso, sin este diferimiento, así que se omite
        // para no sugerirle al cliente un plan de pago que no le aplica.
        if ((int) $tipoModalidad->id !== 11) {
            $diaIngreso         = (int) $fechaAfiliacion->day;
            $diasProporcionales = max(1, 30 - $diaIngreso + 1);

            $resultadoMes2 = CotizadorService::calcular([
                'tipo_modalidad_id' => $tipoModalidad->id,
                'plan_id'           => $plan->id,
                'n_arl'             => $nivelArl,
                'salario'           => $salario,
                'administracion'    => (float) ($cfgGeneral->administracion ?? 0),
                'admon_asesor'      => (float) ($cfgGeneral->admon_asesor ?? 0),
                'seguro'            => (float) ($cfgGeneral->seguro_valor ?? 0),
                'dias'              => $diasProporcionales,
            ], $aliadoId);

            $resultado['plan_pago_inicial'] = [
                'mes_1_nombre'              => ucfirst($fechaAfiliacion->translatedFormat('F')),
                'mes_1_afiliacion'          => $resultado['costo_afiliacion_sugerido'],
                'mes_2_nombre'              => ucfirst($fechaAfiliacion->copy()->addMonthNoOverflow()->translatedFormat('F')),
                'mes_2_dias_proporcionales' => $diasProporcionales,
                'mes_2_valor'               => $resultadoMes2['total'],
                'mes_3_nombre'              => ucfirst($fechaAfiliacion->copy()->addMonthsNoOverflow(2)->translatedFormat('F')),
                'mes_3_en_adelante'         => $resultado['total'],
            ];
        }

        return $resultado;
    }

    /**
     * Costo mensual de afiliarse directamente como independiente (sin intermediario), sobre el
     * IBC sugerido (mismo % que usa CotizadorService::ibcSugerido) — usado por la calculadora de
     * ahorro de la página pública. Redondeo idéntico al de CotizadorService (hacia arriba al 100).
     */
    public static function costoDirectoIndependiente(float $salario): array
    {
        $r   = fn ($v) => (float) (ceil($v / 100) * 100);
        $ibc = $r($salario * ConfiguracionBrynex::pctIbcIndependienteSugerido() / 100);

        $salud   = $r($ibc * ConfiguracionBrynex::pctSaludIndependiente() / 100);
        $pension = $r($ibc * ConfiguracionBrynex::pctPensionIndependiente() / 100);

        return [
            'ibc'     => $ibc,
            'salud'   => $salud,
            'pension' => $pension,
            'total'   => $salud + $pension,
        ];
    }
}
