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

    public function liberar(): void
    {
        $this->mapas = [];
        $this->contadores = [];
    }
}
