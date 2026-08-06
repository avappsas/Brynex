<?php

namespace App\Services\Exportacion;

/**
 * Los consecutivos que ligan un archivo con otro dentro de una misma entrega.
 *
 * El problema: la entrega no lleva ids nuestros, y las llaves naturales no
 * alcanzan. `numero_factura` parecía la obvia, pero no es única — en BRYGAR
 * hay 2.931 números repetidos porque el número es el de la planilla, que
 * agrupa a varias personas. Ni `numero + tipo + cédula + mes + año` desempata
 * (quedan 98 casos). Sin algo que desempate, un pago no se puede amarrar a su
 * factura y la entrega queda inservible.
 *
 * La solución: un consecutivo que nace y muere en la exportación. Se asigna
 * 1..N en el orden en que se escribe el archivo padre, y el archivo hijo lo
 * repite. No es nuestro id, no revela nada del esquema y no sirve para nada
 * fuera de este ZIP — pero deja los archivos cruzables en Excel.
 */
class ContextoExportacion
{
    /** @var array<string, array<int,int>> mapa[tipo][id interno] = consecutivo */
    private array $mapas = [];

    /** @var array<string, array<string,int>> llave alterna → consecutivo */
    private array $alias = [];

    /** @var array<string,int> */
    private array $contadores = [];

    /** Asigna (o reusa) el consecutivo del registro padre. */
    public function asignar(string $tipo, int|string|null $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $id = (int) $id;

        if (isset($this->mapas[$tipo][$id])) {
            return $this->mapas[$tipo][$id];
        }

        $this->contadores[$tipo] = ($this->contadores[$tipo] ?? 0) + 1;

        return $this->mapas[$tipo][$id] = $this->contadores[$tipo];
    }

    /** Consecutivo ya asignado, o null si el padre no salió en la entrega. */
    public function consecutivo(string $tipo, int|string|null $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        return $this->mapas[$tipo][(int) $id] ?? null;
    }

    /**
     * Segunda llave para llegar al mismo consecutivo.
     *
     * Existe por la migración del sistema viejo: el 99% de las consignaciones
     * quedó sin `factura_id` y el único rastro del vínculo es el id legacy
     * escrito en la observación. Registrando el `id_legacy` de cada factura como
     * alias, un pago migrado sí puede apuntar a su factura.
     */
    public function alias(string $tipo, int|string|null $clave, int $consecutivo): void
    {
        if ($clave === null || $clave === '') {
            return;
        }

        $this->alias[$tipo][(string) $clave] = $consecutivo;
    }

    public function porAlias(string $tipo, int|string|null $clave): ?int
    {
        if ($clave === null || $clave === '') {
            return null;
        }

        return $this->alias[$tipo][(string) $clave] ?? null;
    }

    public function liberar(): void
    {
        $this->mapas = [];
        $this->alias = [];
        $this->contadores = [];
    }
}
