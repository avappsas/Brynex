<?php

namespace App\Services\Banco;

use Carbon\CarbonInterface;

/**
 * Un movimiento del extracto, ya normalizado.
 *
 * Cada adaptador (Bancolombia, el falso, el que venga) traduce lo que responde
 * su API a este objeto. De aquí en adelante el resto de BryNex no sabe con qué
 * banco está hablando.
 */
class MovimientoBanco
{
    public function __construct(
        public readonly CarbonInterface $fecha,
        public readonly string $tipo,               // credito | debito
        public readonly float $valor,               // siempre positivo
        public readonly ?string $descripcion = null,
        public readonly ?string $referencia = null,
        public readonly ?string $idExterno = null,
        public readonly ?CarbonInterface $fechaHora = null,
        public readonly ?float $saldoDespues = null,
        public readonly ?string $canal = null,
        public readonly ?string $contraparteNombre = null,
        public readonly ?string $contraparteDocumento = null,
        /**
         * Posición del movimiento dentro de su día, tal como la entregó el
         * banco. Solo se usa para la huella cuando el API no da id propio:
         * sin ella, dos consignaciones del mismo valor el mismo día se verían
         * como una sola y la segunda se perdería.
         */
        public readonly int $secuencia = 0,
        /** Respuesta cruda del banco para esta fila. */
        public readonly array $payload = [],
    ) {}

    /**
     * Llave de deduplicación.
     *
     * Con id del banco, se usa ese y punto. Sin él se arma con los campos que
     * no cambian de una corrida a otra. Ojo: esto asume que el banco devuelve
     * los movimientos de un día ya cerrado siempre en el mismo orden. Cuando
     * llegue el API real hay que confirmarlo — si el orden baila, el mismo
     * movimiento entra dos veces con huellas distintas.
     */
    public function huella(): string
    {
        if ($this->idExterno !== null && $this->idExterno !== '') {
            return hash('sha256', 'ext:'.$this->idExterno);
        }

        return hash('sha256', implode('|', [
            $this->fecha->toDateString(),
            $this->tipo,
            number_format($this->valor, 2, '.', ''),
            (string) $this->referencia,
            (string) $this->descripcion,
            (string) $this->secuencia,
        ]));
    }

    public function esCredito(): bool
    {
        return $this->tipo === 'credito';
    }
}
