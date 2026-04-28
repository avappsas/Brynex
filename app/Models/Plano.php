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

    public function factura()  { return $this->belongsTo(Factura::class); }
    public function contrato() { return $this->belongsTo(Contrato::class); }

    /**
     * Genera el registro de plano a partir de un contrato y factura.
     * Snapshot de los datos al momento de facturar.
     */
    public static function generarDesdeContrato(Contrato $contrato, Factura $factura, ?string $fechaRetiro = null): static
    {
        $cliente = $contrato->cliente;
        $eps     = $contrato->eps;
        $afp     = $contrato->pension;
        $arl     = $contrato->arl;
        $caja    = $contrato->caja;
        $rs      = $contrato->razonSocial;
        $modal   = $contrato->tipoModalidad;

        // cod_arl y nombre_arl:
        // Prioridad 1: la RS tiene arl_nit → buscar nombre en tabla arls por ese NIT
        // Prioridad 2: fallback al ARL del contrato individual (independientes o RS sin ARL)
        $codArl    = $rs?->arl_nit ?? $arl?->nit ?? $arl?->codigo_arl ?? null;
        $nombreArl = null;
        if ($rs?->arl_nit) {
            // Buscar el nombre real de la ARL de la RS en la tabla arls
            $nombreArl = DB::table('arls')->where('nit', $rs->arl_nit)->value('nombre_arl');
        }
        // Si no se encontró por la RS, usar el ARL del contrato individual
        if (!$nombreArl) {
            $nombreArl = $arl?->nombre_arl ?? null;
        }

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
            'tipo_doc'          => 'CC',
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
            'salario_basico'    => $contrato->salario ?? 0,
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

    private static function splitNombre(string $nombre): array
    {
        $partes = preg_split('/\s+/', trim($nombre), 2);
        return [$partes[0] ?? '', $partes[1] ?? ''];
    }
}
