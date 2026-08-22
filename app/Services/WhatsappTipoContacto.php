<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Clasifica los contactos del chat de WhatsApp en cliente / excliente / nuevo.
 *
 * La conversación ya guarda `contrato_id`, pero solo se llena **al crearla** y con
 * un contrato vigente de ese momento: un cliente que se retiró el mes pasado sigue
 * con su `contrato_id` puesto, y uno que se afilió después de escribir por primera
 * vez sigue en null. Por eso la clasificación se resuelve en caliente cruzando el
 * celular contra `clientes` + `contratos`, y no leyendo la columna.
 *
 * El cruce es UNA sola consulta para todo el sidebar (~400 conversaciones), no una
 * por fila: con ~250 ms de latencia de red al SQL Server, una consulta por
 * conversación haría inusable el inbox.
 */
class WhatsappTipoContacto
{
    public const CLIENTE   = 'cliente';
    public const EXCLIENTE = 'excliente';
    public const NUEVO     = 'nuevo';

    public const ETIQUETAS = [
        self::CLIENTE   => 'Cliente',
        self::EXCLIENTE => 'Excliente',
        self::NUEVO     => 'Nuevo',
    ];

    /** Tope de parámetros por consulta en SQL Server (2100); se deja margen. */
    private const LOTE = 1500;

    /**
     * Deja en cada conversación `tipo_contacto`, `desde_marketing` y, para los
     * exclientes, `fecha_retiro_contrato`. Modifica la colección en sitio.
     */
    public function clasificar(Collection $conversaciones, int $alidoId): void
    {
        if ($conversaciones->isEmpty()) {
            return;
        }

        $telefonos = $conversaciones
            ->map(fn ($c) => $this->telefono10($c->wa_contact_id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $estados = $this->contratosPorTelefono($telefonos, $alidoId);

        foreach ($conversaciones as $c) {
            $tel    = $this->telefono10($c->wa_contact_id);
            $estado = $tel ? ($estados[$tel] ?? null) : null;

            $c->tipo_contacto = match (true) {
                // Un contrato vigente manda sobre cualquier otro dato.
                $estado && $estado->vigentes  > 0  => self::CLIENTE,
                $estado && $estado->retirados > 0  => self::EXCLIENTE,
                // Está en la base de clientes pero nunca tuvo contrato: nunca llegó
                // a ser cliente, así que sigue siendo un prospecto.
                $estado                            => self::NUEVO,
                // Contacto de una empresa vinculada al aliado (la razón social es el
                // cliente, aunque quien escribe no tenga contrato a su nombre).
                (bool) ($c->empresa_id ?? null)    => self::CLIENTE,
                default                            => self::NUEVO,
            };

            $c->desde_marketing = ($c->origen_campana_categoria ?? null) === 'MARKETING'
                || (bool) ($c->origen_publicacion_id ?? null);

            // Solo tiene sentido en un excliente: en un cliente vigente sería la
            // fecha de un retiro viejo que ya no describe su situación actual.
            $c->fecha_retiro_contrato = $c->tipo_contacto === self::EXCLIENTE
                ? ($estado->ultimo_retiro ?? null)
                : null;
        }
    }


    /**
     * Contratos vigentes y retirados del aliado, agrupados por el celular del cliente.
     *
     * Solo se cruza por `clientes.celular` (bigint de 10 dígitos, el mismo formato al
     * que se recorta `wa_contact_id`). `clientes.telefono` no se consulta: sobre los
     * 60 números del aliado 2 que no cruzan por celular, ninguno cruzaba por teléfono
     * — son fijos viejos, no líneas de WhatsApp.
     *
     * @param  string[]  $telefonos  Celulares de 10 dígitos.
     * @return array<string, object{vigentes:int, retirados:int, ultimo_retiro:?string}>
     */
    private function contratosPorTelefono(array $telefonos, int $alidoId): array
    {
        $resultado = [];

        foreach (array_chunk($telefonos, self::LOTE) as $lote) {
            $filas = DB::table('clientes as cl')
                ->leftJoin('contratos as co', fn ($j) => $j
                    ->on('co.cedula', '=', 'cl.cedula')
                    ->where('co.aliado_id', '=', $alidoId))
                ->where('cl.aliado_id', $alidoId)
                ->whereIn('cl.celular', array_map('intval', $lote))
                ->groupBy('cl.celular')
                ->select(
                    'cl.celular as telefono',
                    DB::raw("SUM(CASE WHEN co.estado = 'vigente'  THEN 1 ELSE 0 END) as vigentes"),
                    DB::raw("SUM(CASE WHEN co.estado = 'retirado' THEN 1 ELSE 0 END) as retirados"),
                    DB::raw("MAX(CASE WHEN co.estado = 'retirado' THEN co.fecha_retiro END) as ultimo_retiro"),
                )
                ->get();

            foreach ($filas as $fila) {
                $resultado[(string) $fila->telefono] = $fila;
            }
        }

        return $resultado;
    }

    /** Últimos 10 dígitos de `wa_contact_id` (llega como 573001234567). */
    private function telefono10(?string $waContactId): ?string
    {
        $digitos = preg_replace('/[^0-9]/', '', (string) $waContactId);

        return strlen($digitos) >= 10 ? substr($digitos, -10) : null;
    }
}
