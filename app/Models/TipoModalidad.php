<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoModalidad extends BaseModel
{
    public $timestamps    = false;
    protected $table      = 'tipo_modalidad';
    protected $primaryKey = 'id';
    public $incrementing  = false;  // El ID NO es auto-incremental

    protected $fillable = [
        'id', 'tipo_modalidad', 'observacion', 'descripcion', 'orden', 'modalidad', 'activo',
        'es_tiempo_parcial', 'dias_arl', 'dias_afp', 'dias_caja',
    ];

    protected $casts = [
        'activo'            => 'boolean',
        'es_tiempo_parcial' => 'boolean',
        'dias_arl'          => 'integer',
        'dias_afp'          => 'integer',
        'dias_caja'         => 'integer',
    ];

    /**
     * Scope: activos, ordenados, sin los registros que no son una modalidad
     * que se pueda contratar — "Todos" (-100), que es un filtro, y "Corrección"
     * (16), que solo existe para que el traslado de razón social marque sus
     * planos con tipo_p = 16 y el TXT salga como planilla tipo N.
     */
    public function scopeActivos($q)
    {
        $q->where('activo', true)->whereNotIn('id', self::IDS_NO_CONTRATABLES);

        // Modalidades reservadas a un aliado: fuera de él no se ofrecen. Sin
        // aliado en sesión —una cola, un comando— quedan ocultas, que es el
        // lado seguro: no se vende lo que no se puede sostener.
        $aliadoActivo = (int) session('aliado_id_activo');

        foreach (self::ALIADOS_POR_MODALIDAD as $modalidadId => $aliados) {
            if (! in_array($aliadoActivo, $aliados, true)) {
                $q->where('id', '!=', $modalidadId);
            }
        }

        return $q->orderBy('orden');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'tipo_modalidad_id');
    }

    /** Nombre para mostrar en la UI: usa observacion si existe, si no tipo_modalidad */
    public function getNombreAttribute(): string
    {
        return $this->observacion ?: $this->tipo_modalidad;
    }

    /** Corrección: planilla tipo N generada por un traslado de razón social, no se contrata */
    const ID_CORRECCION = 16;

    /** No se le ofrecen a un cliente: "Todos" es un filtro y "Corrección" es operativa */
    const IDS_NO_CONTRATABLES = [-100, self::ID_CORRECCION];

    /**
     * Solo Caja: el cliente compra únicamente la caja de compensación, por el
     * mes completo o por media jornada del mes. Se paga en dos planillas
     * encadenadas — ver PilaCotizanteDosPasos.
     *
     * Las dos se calculan igual y solo se diferencian en los días de caja, que
     * salen de la columna `dias_caja` del catálogo: cambiar 14 por otro número
     * es cambiar el dato, no el código.
     */
    const ID_CAJA_30 = -5;

    const ID_CAJA_14 = -10;

    const IDS_SOLO_CAJA = [self::ID_CAJA_30, self::ID_CAJA_14];

    /**
     * Solo Pensión: el cliente compra únicamente el aporte a pensión. Mismo
     * esquema de dos planillas que Solo Caja —el paso 1 es el mismo archivo—,
     * y los días salen de `dias_afp`. Ver PilaCotizanteDosPasos.
     */
    const ID_PENSION_30 = -11;

    const IDS_SOLO_PENSION = [self::ID_PENSION_30];

    /** Las que se pagan en dos liquidaciones encadenadas contra el operador. */
    const IDS_DOS_PASOS = [self::ID_CAJA_30, self::ID_CAJA_14, self::ID_PENSION_30];

    /**
     * Modalidades que solo se le ofrecen a ciertos aliados.
     *
     * No es un permiso ni una restricción de seguridad: es qué se puede vender
     * dónde. Las de dos pasos arrancaron como planes de BRYGAR (aliado 2) y se
     * pagan en dos liquidaciones encadenadas contra la API del operador, así
     * que ofrecerlas a un aliado que no las conoce es venderle algo que no va a
     * poder pagar. Para abrirlas a otro aliado basta agregar su id aquí.
     */
    const ALIADOS_POR_MODALIDAD = [
        self::ID_CAJA_30 => [2],
        self::ID_CAJA_14 => [2],
        self::ID_PENSION_30 => [2],
    ];

    /**
     * Tiempo Parcial Independiente: cotizante 76 de la Resolución 1529 de 2026.
     * Pensión por semanas, ARL sobre el mínimo completo, caja voluntaria y sin
     * salud. Los días NO están en el catálogo: son del contrato.
     */
    const ID_TP_INDEPENDIENTE = 18;

    /** IDs que corresponden a modalidades independientes (I Venc=10, UPC=13, En el Exterior=14, TP Ind=18) */
    const IDS_INDEPENDIENTE = [10, 13, 14, self::ID_TP_INDEPENDIENTE];

    /** UPC: afiliar a alguien fuera del núcleo familiar — no depende del salario */
    const ID_UPC = 13;

    /** Gestión ARL: no cotiza seguridad social, solo se le hace el trámite de la ARL */
    const ID_GESTION_ARL = 15;

    /** Seguros: solo se le vendió un seguro — sin EPS, ARL, pensión, caja ni planilla */
    const ID_SEGUROS = 17;

    /** Modalidades que no cotizan seguridad social: no liquidan planilla ni generan plano */
    const IDS_SIN_SEGURIDAD_SOCIAL = [self::ID_GESTION_ARL, self::ID_SEGUROS];

    /** IDs que requieren el campo "Modo ARL" */
    const IDS_MODO_ARL = [10, -1, self::ID_TP_INDEPENDIENTE];

    /** IDs en que la ARL es libre (no bloqueada a la razon social) */
    const IDS_ARL_LIBRE = [10, -1, 8, self::ID_TP_INDEPENDIENTE];

    public function esIndependiente(): bool
    {
        return in_array($this->id, self::IDS_INDEPENDIENTE);
    }

    /** ¿Solo seguro? Sin EPS, ARL, pensión ni caja: el mes vale lo que valga el seguro. */
    public function esSeguros(): bool
    {
        return (int) $this->id === self::ID_SEGUROS;
    }

    /** ¿Esta modalidad cotiza seguridad social? Gestión ARL y Seguros no. */
    public function cotizaSeguridadSocial(): bool
    {
        return ! in_array((int) $this->id, self::IDS_SIN_SEGURIDAD_SOCIAL, true);
    }

    /**
     * ¿Es modalidad de Tiempo Parcial?
     * Los planes TP tienen días fijos por entidad definidos en BD.
     */
    public function esTiempoParcial(): bool
    {
        return (bool) $this->es_tiempo_parcial;
    }

    /**
     * Retorna los días a cotizar por entidad para este plan de Tiempo Parcial.
     * Array: ['arl' => X, 'afp' => Y, 'caja' => Z]
     *
     * Regla de negocio:
     *   - ARL: siempre 30 días (cotización mensual completa, sin importar el plan)
     *   - AFP: días fijos del plan (7, 14 ó 21)
     *   - CAJA: días fijos del plan (7, 14 ó 21)
     *
     * Si no es TP, retorna 30 para todas (mes completo).
     */
    public function diasPorEntidad(): array
    {
        if ($this->esTiempoParcial()) {
            return [
                'arl'  => 30,                    // ARL siempre mensual completa
                'afp'  => $this->dias_afp  ?? 30,
                'caja' => $this->dias_caja ?? 30,
            ];
        }
        return ['arl' => 30, 'afp' => 30, 'caja' => 30];
    }

    /**
     * ¿Los días de esta modalidad los pone el contrato en vez del catálogo?
     *
     * El Tiempo Parcial de dependientes nació como una modalidad por cada
     * combinación de días — ocho en total — y por eso `dias_afp`/`dias_caja`
     * viven en esta tabla. El Tiempo Parcial Independiente no repite eso: sus
     * días son un dato del contrato y del mes, no del catálogo, así que aquí
     * quedan en NULL.
     */
    public function diasEnElContrato(): bool
    {
        return $this->esTiempoParcial() && $this->dias_afp === null;
    }

    /** Fracción del SMMLV según los días AFP del plan de Tiempo Parcial */
    const FACTOR_SALARIO_POR_DIAS = [7 => 0.25, 14 => 0.50, 21 => 0.75, 30 => 1.00];

    /**
     * Fracción del salario mínimo que corresponde a esta modalidad.
     * Tiempo completo → 1.0; Tiempo Parcial → 0.25 / 0.50 / 0.75 según días AFP.
     *
     * $diasAfp lo pasa el contrato cuando es él quien tiene los días
     * (Tiempo Parcial Independiente); sin él se usan los del catálogo.
     */
    public function factorSalario(?int $diasAfp = null): float
    {
        if (! $this->esTiempoParcial()) {
            return 1.0;
        }
        return self::FACTOR_SALARIO_POR_DIAS[$diasAfp ?? $this->dias_afp] ?? 1.0;
    }

    /**
     * Salario mínimo legal que puede tener un contrato en esta modalidad.
     * UPC (13) no depende del salario: no tiene piso.
     */
    public function salarioMinimoPermitido(?int $diasAfp = null): float
    {
        // (int) obligatorio: el id no es IDENTITY y llega como string
        // Seguros no cotiza nada, así que tampoco tiene piso de salario.
        if (in_array((int) $this->id, [self::ID_UPC, self::ID_SEGUROS], true)) {
            return 0.0;
        }
        return round(ConfiguracionBrynex::salarioMinimo() * $this->factorSalario($diasAfp));
    }
}
