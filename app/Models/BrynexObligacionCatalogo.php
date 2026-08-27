<?php

namespace App\Models;

/**
 * Catálogo de obligaciones: qué existe y a quién le aplica.
 *
 * Es parametría pura, sembrada por `BrynexObligacionesSeeder`. Lo que decide
 * si una obligación se le genera a una razón social es el cruce de tres cosas:
 * su régimen, si es responsable de IVA y con qué periodicidad.
 */
class BrynexObligacionCatalogo extends BaseModel
{
    protected $table = 'brynex_obligaciones_catalogo';

    protected $fillable = [
        'codigo', 'nombre', 'entidad', 'formulario', 'regimen',
        'periodicidad', 'requiere_iva', 'periodicidad_iva_requerida',
        'descripcion', 'orden', 'activo',
    ];

    protected $casts = [
        'requiere_iva' => 'boolean',
        'activo' => 'boolean',
    ];

    public const ENTIDADES = [
        'DIAN' => '🏛️ DIAN',
        'CAMARA' => '📜 Cámara de Comercio',
        'MUNICIPIO' => '🏙️ Municipio',
    ];

    /** Cuántos renglones genera al año. */
    public function periodosPorAnio(): int
    {
        return match ($this->periodicidad) {
            'mensual' => 12,
            'bimestral' => 6,
            'cuatrimestral' => 3,
            default => 1,
        };
    }

    /** ¿Esta obligación le aplica a esta razón social? */
    public function aplicaA(BrynexRazonSocial $ficha): bool
    {
        if (! $this->activo) {
            return false;
        }

        // null = aplica a los dos regímenes
        if ($this->regimen !== null && $this->regimen !== $ficha->regimen) {
            return false;
        }

        if ($this->requiere_iva && ! $ficha->esResponsableIva()) {
            return false;
        }

        // El IVA ordinario es bimestral o cuatrimestral según los ingresos del
        // año anterior: solo se genera el que coincida con la ficha.
        if ($this->periodicidad_iva_requerida !== null
            && $this->periodicidad_iva_requerida !== $ficha->periodicidad_iva) {
            return false;
        }

        // El ICA no lo fija la DIAN sino cada municipio, y su periodicidad
        // cambia de uno a otro. Por eso hay una entrada de catálogo por
        // periodicidad y se elige la que coincida con la ficha.
        if ($this->entidad === 'MUNICIPIO') {
            return $this->periodicidad === $ficha->periodicidad_ica;
        }

        return true;
    }

    private const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun',
        'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    private const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    /**
     * Cómo se nombra el período. Se guarda en el renglón para no rearmarlo.
     *
     * Va en el orden en que se dice en la práctica contable: «1 bimestre», no
     * «Bimestre 1». Los meses del período van aparte, en `mesesDelPeriodo()`,
     * para que la vista los pinte como apoyo y no dentro del nombre.
     */
    public function etiquetaPeriodo(int $periodo): string
    {
        return match ($this->periodicidad) {
            'mensual' => self::MESES[$periodo - 1] ?? ('Mes '.$periodo),
            'bimestral' => $periodo.' bimestre',
            'cuatrimestral' => $periodo.' cuatrimestre',
            default => 'Anual',
        };
    }

    /** 'ene-feb' — el rango que cubre el período. Vacío en las anuales. */
    public function mesesDelPeriodo(int $periodo): string
    {
        return match ($this->periodicidad) {
            'bimestral' => self::MESES_CORTOS[($periodo - 1) * 2]
                .'-'.self::MESES_CORTOS[($periodo - 1) * 2 + 1],
            'cuatrimestral' => self::MESES_CORTOS[($periodo - 1) * 4]
                .'-'.self::MESES_CORTOS[($periodo - 1) * 4 + 3],
            default => '',
        };
    }
}
