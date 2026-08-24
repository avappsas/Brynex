<?php

namespace App\Services\Dataico;

use App\Models\DataicoConfiguracion;
use Carbon\Carbon;

/**
 * Arma el JSON que se le manda a `POST /direct/dataico_api/v2/invoices`.
 *
 * La estructura NO es una reconstrucción: se calcó de facturas reales de
 * BRYGAR ya aceptadas por la DIAN, traídas con
 * `GET /direct/dataico_api/v2/invoices?number=FE1184` el 24-ago-2026. Por eso
 * los enums son los que Dataico devuelve de verdad (`PERSONA_NATURAL`,
 * `DEBITO`, `BANK_TRANSFER`, `SIMPLIFICADO`) y no los que uno supondría.
 *
 * Lo que se aprendió de esas facturas:
 *   · La dirección va PLANA en `customer`, no anidada en un `address`.
 *   · `PERSONA_NATURAL` usa `first_name` + `family_name`;
 *     `PERSONA_JURIDICA` usa `company_name`. Nunca los dos.
 *   · Los ítems no llevan impuestos ni descuento: BRYGAR no cobra IVA.
 *   · `notes` es un ARRAY de strings. BRYGAR pone ahí el período.
 *   · La numeración se identifica con `{prefix, resolution_number}`.
 *
 * Único campo sin confirmar: el formato de `issue_date`. La respuesta lo
 * devuelve como `15/08/2026 13:31:57`, pero el QR de la DIAN lo lleva como
 * `2026-08-15`, así que se manda ISO. Si la primera emisión rebota por fecha,
 * es lo primero que hay que cambiar — y se cambia solo aquí.
 *
 * Contenido de la factura, por decisión del dueño (24-ago-2026):
 * un solo ítem con `admon + afiliacion`. La seguridad social (EPS, ARL, AFP,
 * caja), la mora, el seguro y la mensajería NO se facturan — son plata que
 * BRYGAR recauda y traslada, no ingreso propio.
 */
class PayloadBuilder
{
    /** Servicio de administración de la afiliación (facturas de planilla). */
    private const SKU_ADMON = 'SKU 0001';

    private const DESC_ADMON = '0001-Servicio de administración y gestión de afiliaciones a seguridad social';

    /** Afiliación nueva al sistema. */
    private const SKU_AFILIACION = 'SKU 0006';

    private const DESC_AFILIACION = '0006-Afiliación al sistema general de seguridad social';

    /**
     * Relleno de correo para adquirientes sin uno.
     *
     * No es invento: es exactamente lo que traen las 1.184 facturas que BRYGAR
     * ya emitió por el Excel, y la DIAN las aceptó todas. Va siempre con
     * `send_email: false`, así que a esa dirección no le llega nada.
     */
    private const CORREO_RELLENO = 'notiene@gmail.com';

    public function construir(DataicoConfiguracion $cfg, object $grupo): array
    {
        $adq = $grupo->adquiriente;
        $fecha = $grupo->fecha_pago
            ? Carbon::parse($grupo->fecha_pago)->format('Y-m-d')
            : now()->format('Y-m-d');
        $esAfiliacion = strtolower($grupo->tipo ?? '') === 'afiliacion';

        $invoice = [
            // Va DENTRO de invoice. En la raíz del cuerpo el API responde 500
            // ("los campos deben ser sólo 'invoice' y opcionalmente
            // 'actions'"), y como header o parámetro de URL responde 500
            // diciendo que falta un account id válido — aunque el mismo valor
            // sí funciona como parámetro en el GET de consulta.
            'dataico_account_id' => $cfg->dataico_account_id,
            'issue_date' => $fecha,
            'payment_date' => $fecha,
            'invoice_type_code' => 'FACTURA_VENTA',

            // Efectivo no llega hasta aquí: el criterio de selección exige una
            // consignación en la cuenta de la razón social emisora.
            'payment_means_type' => 'DEBITO',
            'payment_means' => 'BANK_TRANSFER',

            'customer' => $this->customer($cfg, $adq),
            'items' => [$this->item($grupo, $esAfiliacion)],
            'notes' => $this->notas($grupo),
        ];

        // El número lo asigna Dataico del rango autorizado. Mandarlo vacío es a
        // propósito: BRYGAR también emite por fuera de Brynex y el consecutivo
        // interno no está sincronizado con la resolución.
        if ($numeracion = $this->numeracion($cfg)) {
            $invoice['numbering'] = $numeracion;
        }

        // El cuerpo admite EXACTAMENTE dos llaves: `invoice` y, opcional,
        // `actions` — y `actions` va en la raíz, no dentro de `invoice`.
        return [
            'invoice' => $invoice,
            'actions' => $this->actions($cfg, $adq),
        ];
    }

    // ─── Piezas ──────────────────────────────────────────────────────────

    /**
     * Cuál de las resoluciones DIAN de la cuenta se usa.
     *
     * Con una sola resolución vigente se puede omitir y Dataico toma la que
     * tenga por defecto; el par explícito evita sorpresas cuando entra una
     * resolución nueva y las dos quedan activas un tiempo.
     */
    private function numeracion(DataicoConfiguracion $cfg): ?array
    {
        if (filled($cfg->prefijo) && filled($cfg->resolucion)) {
            return [
                'prefix' => $cfg->prefijo,
                'resolution_number' => $cfg->resolucion,
            ];
        }

        return null;
    }

    private function customer(DataicoConfiguracion $cfg, array $adq): array
    {
        $esJuridica = $adq['tipo_persona'] === 'PERSONA_JURIDICA';

        $customer = [
            'party_type' => $adq['tipo_persona'],
            'party_identification_type' => $adq['tipo_documento'],
            'party_identification' => $adq['identificacion'],
            'tax_level_code' => 'SIMPLIFICADO',
            'country_code' => 'CO',
            'email' => $this->correo($cfg, $adq),
        ];

        if ($esJuridica) {
            $customer['company_name'] = $adq['nombre_completo'];
        } else {
            $customer['first_name'] = $adq['primer_nombre'];
            $customer['family_name'] = $adq['apellido'];
        }

        // La dirección va plana. Ciudad y departamento viajan juntos o no
        // viajan: Dataico rechaza la combinación si va incompleta.
        if (filled($adq['ciudad']) && filled($adq['departamento'])) {
            $customer['city'] = $adq['ciudad'];
            $customer['department'] = $adq['departamento'];
        }
        if (filled($adq['direccion'])) {
            $customer['address_line'] = $adq['direccion'];
        }

        return $customer;
    }

    private function item(object $grupo, bool $esAfiliacion): array
    {
        return [
            'sku' => $esAfiliacion ? self::SKU_AFILIACION : self::SKU_ADMON,
            'description' => $esAfiliacion ? self::DESC_AFILIACION : self::DESC_ADMON,
            'quantity' => 1,
            'price' => (float) $grupo->base_admon,
        ];
    }

    private function actions(DataicoConfiguracion $cfg, array $adq): array
    {
        $tieneCorreoReal = filled($adq['correo']) || filled($cfg->correo_fallback);

        // Sin correo real no se manda representación gráfica. La factura igual
        // queda emitida ante la DIAN: es la salida acordada para el 44% de
        // clientes de BRYGAR que no tienen correo registrado.
        return [
            'send_dian' => true,
            'send_email' => $cfg->enviar_email && $tieneCorreoReal,
        ] + ($cfg->enviar_email && $tieneCorreoReal
                ? ['email' => $this->correo($cfg, $adq)]
                : []);
    }

    private function correo(DataicoConfiguracion $cfg, array $adq): string
    {
        return filled($adq['correo'])
            ? $adq['correo']
            : ($cfg->correo_fallback ?: self::CORREO_RELLENO);
    }

    /**
     * `notes` es un array. BRYGAR viene poniendo ahí el mes del período
     * ("JUNIO"); se conserva esa costumbre y se agrega el número interno de
     * Brynex, que es lo que permite rastrear la factura de vuelta.
     */
    private function notas(object $grupo): array
    {
        $mes = Carbon::create((int) $grupo->anio, (int) $grupo->mes, 1)
            ->locale('es')
            ->isoFormat('MMMM');

        $notas = [mb_strtoupper($mes)];
        $notas[] = "Brynex {$grupo->numero_factura}";

        if ((int) $grupo->num_clientes > 1) {
            $notas[] = "{$grupo->num_clientes} afiliados";
        }

        return $notas;
    }
}
