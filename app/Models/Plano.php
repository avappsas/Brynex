<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Plano extends BaseModel
{
    use SoftDeletes;

    protected $table    = 'planos';
    protected $fillable = [
        'factura_id', 'contrato_id', 'aliado_id',
        // Tipo de registro
        'tipo_reg',               // 'afiliacion' | 'planilla'
        'tipo_doc',               // 'CC', 'CE', etc.
        'no_identifi',            // cédula
        // Número de factura (snapshot)
        'numero_factura',
        // Nombre (snapshot del cliente)
        'primer_ape', 'segundo_ape', 'primer_nombre', 'segundo_nombre',
        // Novedades
        'fecha_ing', 'fecha_ret',
        // Período y días
        'num_dias',               // días cotizados unificados
        'n_plano', 'mes_plano', 'anio_plano',
        // Salario
        'salario_basico',
        // Entidades (NIT snapshot al momento de facturar)
        'cod_eps',    // NIT de la EPS
        'nombre_eps', // Nombre snapshot EPS
        'cod_afp',    // NIT de la pensión
        'nombre_afp', // Nombre snapshot AFP
        'cod_arl',    // NIT de la ARL
        'nombre_arl', // Nombre snapshot ARL
        'cod_caja',   // NIT de la Caja
        'nombre_caja',// Nombre snapshot CAJA
        'nivel_riesgo',
        // Clasificación
        'razon_social',           // nombre RS (legacy, se mantiene por compatibilidad)
        'razon_social_id',        // ID de razones_sociales
        'tipo_p',                 // ID tipo modalidad (= tipo_modalidad_id, legacy smallint)
        'tipo_modalidad_id',      // ID de tipo_modalidad
        'usuario_id',
    ];

    protected $casts = [
        'fecha_ing' => 'date',
        'fecha_ret' => 'date',
    ];

    public function factura()      { return $this->belongsTo(Factura::class); }
    public function contrato()     { return $this->belongsTo(Contrato::class); }
    public function razonSocial()  { return $this->belongsTo(RazonSocial::class, 'razon_social_id'); }
    public function enviosWhatsappDetalles() { return $this->hasMany(PlanillaEnvioWhatsappDetalle::class, 'plano_id'); }

    /**
     * Resuelve el NIT y el nombre de la ARL a utilizar para un contrato y su razón social.
     * Prioriza la ARL del contrato si es independiente, si tiene modalidad libre o si la RS es independiente.
     * De lo contrario, prioriza el ARL de la razón social.
     */
    public static function resolverArlSnapshot(Contrato $contrato, ?RazonSocial $rs): array
    {
        $arl = $contrato->arl;
        $esIndependiente = $contrato->esIndependiente()
            || ($rs?->es_independiente)
            || ($contrato->tipoModalidad && in_array($contrato->tipo_modalidad_id, \App\Models\TipoModalidad::IDS_ARL_LIBRE));

        $codArl = null;
        $nombreArl = null;

        if ($esIndependiente && $arl) {
            $codArl = $arl->nit ?? $arl->codigo_arl ?? null;
            $nombreArl = $arl->nombre_arl ?? null;
        }

        if (!$codArl) {
            $codArl = $rs?->arl_nit ?? $arl?->nit ?? $arl?->codigo_arl ?? null;
            if ($rs?->arl_nit) {
                $nombreArl = DB::table('arls')->where('nit', $rs->arl_nit)->value('nombre_arl');
            }
            if (!$nombreArl) {
                $nombreArl = $arl?->nombre_arl ?? null;
            }
        }

        return [
            'cod_arl' => $codArl,
            'nombre_arl' => $nombreArl,
        ];
    }

    /**
     * Genera el registro de plano a partir de un contrato y factura.
     * Snapshot de los datos al momento de facturar.
     */
    public static function generarDesdeContrato(Contrato $contrato, Factura $factura, ?string $fechaRetiro = null): static
    {
        // ── Protección idempotente: no generar dos planos para la misma factura ──
        // Si ya existe un plano activo (no soft-deleted) para esta factura_id, retornarlo
        // sin crear un duplicado (ej: flujo abonar() que completa una factura ya planificada).
        $existente = static::where('factura_id', $factura->id)->whereNull('deleted_at')->first();
        if ($existente) {
            return $existente;
        }
    
        $cliente = $contrato->cliente;
        $eps     = $contrato->eps;
        $afp     = $contrato->pension;
        $arl     = $contrato->arl;
        $caja    = $contrato->caja;
        $rs      = $contrato->razonSocial;
        $modal   = $contrato->tipoModalidad;

        // ── Fallback AFP: si el contrato no tiene pension_id pero el plan incluye AFP
        //    y el cliente sí tiene pensión, usar la del cliente para el snapshot del plano.
        //    Esto evita que cod_afp quede NULL cuando se facturó con valor de pensión.
        if (!$afp && $cliente?->pension_id) {
            $plan = $contrato->plan;
            if ($plan?->incluye_pension && (int)($factura->v_afp ?? 0) > 0) {
                $afp = $cliente->pension; // relación pension en el modelo Cliente
            }
        }

        // cod_arl y nombre_arl utilizando el método centralizado
        $arlSnapshot = static::resolverArlSnapshot($contrato, $rs);
        $codArl = $arlSnapshot['cod_arl'];
        $nombreArl = $arlSnapshot['nombre_arl'];

        [$primerApe, $segundoApe] = static::splitNombre($cliente?->apellidos ?? ($cliente?->primer_apellido . ' ' . $cliente?->segundo_apellido ?? ''));
        [$primerNom, $segundoNom] = static::splitNombre($cliente?->nombres    ?? ($cliente?->primer_nombre   . ' ' . $cliente?->segundo_nombre   ?? ''));

        $esAfiliacion = $factura->tipo === 'afiliacion';
        $esPlanilla   = !$esAfiliacion;

        // Independientes "Mes Actual" (tipo_modalidad_id = 11): el plano cubre el mes facturado.
        // Dependientes de empresa: el plano cubre el MES ANTERIOR (billing mayo → plano abril).
        $esIndepMesActual = $modal && (int)$modal->id === 11;

        if ($esPlanilla && !$esIndepMesActual) {
            // Dependiente empresa: mes anterior
            $mesPlan  = $factura->mes > 1 ? $factura->mes - 1 : 12;
            $anioPlan = $factura->mes > 1 ? $factura->anio    : $factura->anio - 1;
        } else {
            // Afiliación o independiente mes actual
            $mesPlan  = $factura->mes;
            $anioPlan = $factura->anio;
        }

        // fecha_ing: en afiliación siempre; en planilla solo el primer mes de cotización.
        // • Independiente mes actual (tipo 11): fecha_ingreso = mes facturado (paga el mismo mes)
        // • Dependiente / I Vencido:            fecha_ingreso = mes ANTERIOR al facturado
        $fechaIng = null;
        if ($esAfiliacion) {
            $fechaIng = $contrato->fecha_ingreso;
        } elseif ($contrato->fecha_ingreso) {
            $fIng = $contrato->fecha_ingreso;
            if ($esIndepMesActual) {
                // Independiente mes actual: primer plano cuando fecha_ingreso = mes facturado
                if ((int)$fIng->month === (int)$factura->mes && (int)$fIng->year === (int)$factura->anio) {
                    $fechaIng = $fIng;
                }
            } else {
                // Dependiente / I Vencido: primer plano cuando fecha_ingreso = mes anterior
                $mesPrev  = $factura->mes > 1 ? $factura->mes - 1 : 12;
                $anioPrev = $factura->mes > 1 ? $factura->anio    : $factura->anio - 1;
                if ((int)$fIng->month === $mesPrev && (int)$fIng->year === $anioPrev) {
                    $fechaIng = $fIng;
                }
            }
        }

        return static::create([
            'factura_id'        => $factura->id,
            'contrato_id'       => $contrato->id,
            'aliado_id'         => $contrato->aliado_id,
            'numero_factura'    => $factura->numero_factura,
            // Tipo de registro
            'tipo_reg'          => $esAfiliacion ? 'afiliacion' : 'planilla',
            'tipo_doc'          => strtoupper(trim($cliente?->tipo_doc ?? 'CC')) ?: 'CC',
            'no_identifi'       => $contrato->cedula,
            // Nombre snapshot
            'primer_ape'        => strtoupper($primerApe),
            'segundo_ape'       => strtoupper($segundoApe),
            'primer_nombre'     => strtoupper($primerNom),
            'segundo_nombre'    => strtoupper($segundoNom),
            // Novedades
            'fecha_ing'         => $fechaIng,
            'fecha_ret'         => $fechaRetiro ? \Carbon\Carbon::parse($fechaRetiro)->toDateString() : null,
            // Días
            'num_dias'          => $esAfiliacion ? 0 : ($factura->dias_cotizados ?? 30),
            // Entidades (NIT snapshot)
            'cod_eps'           => $eps?->nit  ?? $eps?->cod_eps  ?? null,
            'nombre_eps'        => $eps?->nombre ?? null,
            'cod_afp'           => $afp?->nit  ?? $afp?->cod_afp  ?? null,
            'nombre_afp'        => $afp?->razon_social ?? null,
            'cod_arl'           => $codArl,
            'nombre_arl'        => $nombreArl,
            'cod_caja'          => $caja?->nit ?? $caja?->cod_caja ?? null,
            'nombre_caja'       => $caja?->nombre ?? null,
            'nivel_riesgo'      => $contrato->n_arl ?? 1,
            // salario_basico: para Tiempo Parcial (planes 1-4) usamos ceil(SM × factor_afp)
            // para garantizar el redondeo PILA (Res. 2388). Para otros planes, salario del contrato.
            'salario_basico'    => static::calcularSalarioBasicoPlano($contrato, $modal),
            // Período — mes ANTERIOR para dependientes (billing mayo → plano abril)
            'n_plano'           => $factura->n_plano,
            'mes_plano'         => $mesPlan,
            'anio_plano'        => $anioPlan,
            // Clasificación
            'razon_social'      => $rs?->razon_social ?? null,
            'razon_social_id'   => $contrato->razon_social_id,
            'tipo_p'            => $contrato->tipo_modalidad_id,  // ID (evita texto largo)
            'tipo_modalidad_id' => $contrato->tipo_modalidad_id,
            'usuario_id'        => $factura->usuario_id,
        ]);
    }

    /**
     * Calcula el salario_basico que se almacena en el plano.
     *
     * Para planes de Tiempo Parcial (es_tiempo_parcial = true, planes 1-4):
     *   salario_basico = ceil(SM × factor_afp)
     *   donde factor_afp = dias_afp / 30
     *   Esto garantiza el redondeo PILA hacia arriba (Res. 2388) sin importar
     *   el salario del año (SM se lee de ConfiguracionBrynex).
     *
     * Para todos los demás planes: usa contrato->salario sin modificar.
     */
    private static function calcularSalarioBasicoPlano($contrato, $modal): int
    {
        if ($modal && $modal->esTiempoParcial()) {
            $sm      = (float) ConfiguracionBrynex::obtener('salario_minimo', 1423500);
            $diasP   = $modal->diasPorEntidad();                     // ['afp'=>7|14|21|30, ...]
            $diasAfp = (int)($diasP['afp'] ?? 30);
            // ceil(SM × dias_afp / 30) garantiza redondeo hacia arriba requerido por PILA
            return (int)ceil($sm * $diasAfp / 30);
        }

        return (int)($contrato->salario ?? 0);
    }

    private static function splitNombre(string $nombre): array
    {
        $partes = preg_split('/\s+/', trim($nombre), 2);
        return [$partes[0] ?? '', $partes[1] ?? ''];
    }
}
