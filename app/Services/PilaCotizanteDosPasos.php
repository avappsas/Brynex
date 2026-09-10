<?php

namespace App\Services;

use App\Models\TipoModalidad;

/**
 * PilaCotizanteDosPasos — modalidades que venden UN solo subsistema y se pagan
 * en dos planillas encadenadas: "Solo Caja" y "Solo Pensión".
 *
 *   paso 1 — planilla E con un día de pensión y nada más. Se paga.
 *   paso 2 — planilla N que corrige la anterior y anexa lo que se vendió.
 *
 * El paso 1 es **el mismo archivo para las dos**; lo único que cambia es qué
 * sube la corrección. Por eso viven juntas aquí.
 *
 * ## Por qué hacen falta dos planillas, y por qué la salud no cabe
 *
 * La coherencia entre subsistemas no es la misma regla en una planilla nueva
 * que en una corrección, y ahí está todo el juego:
 *
 *   planilla nueva (E) → `eo.val.2.673`: pensión, riesgos y **caja** iguales
 *   corrección    (N) → `eo.val.2.198` / `2.244`: pensión, salud y riesgos iguales
 *
 * La salud queda fuera de las dos gracias a la marca de colombiano en el
 * exterior —que no la exime del cotejo, sino que le permite ir en CERO—. De ahí:
 *
 *   - la **caja** solo se puede vender sola en una corrección, porque en una
 *     planilla nueva el 2.673 obligaría a subir pensión y riesgos con ella;
 *   - la **pensión** arrastra los riesgos, que con la tarifa en cero no cuestan
 *     nada, y obliga a un día de caja porque la línea C no la admite en cero
 *     (`eo.val.2.050` / `2.046`);
 *   - la **salud** no se puede vender sola por ningún lado: en cuanto tiene
 *     días, arrastra la pensión completa.
 *
 * Probado contra ARUS Enlace y Simple el 9-sep-2026 sobre planillas de paso 1
 * ya pagadas: las dos correcciones validaron con cero errores y el operador
 * cobró exactamente lo vendido —y en la pensión, solo la diferencia contra el
 * día ya pagado—.
 *
 * ## Cómo se retira el día que deje de servir
 *
 * Es un rodeo a una validación del operador, no una regla PILA: se supone
 * temporal. Para apagarlo **hoy mismo, sin desplegar**, basta vaciar
 * `TipoModalidad::ALIADOS_POR_MODALIDAD` —dejan de ofrecerse en todos los
 * aliados— o poner `activo = 0` en las tres filas del catálogo, que además
 * deja intactos los contratos y planos ya hechos.
 *
 * Para borrarlo de raíz, esto es todo lo que existe:
 *
 *   1. Esta clase y PlanillaDosPasosService.
 *   2. Las constantes ID_CAJA_30 / ID_CAJA_14 / ID_PENSION_30, IDS_SOLO_CAJA,
 *      IDS_SOLO_PENSION, IDS_DOS_PASOS y ALIADOS_POR_MODALIDAD, más el bloque
 *      del `foreach` en TipoModalidad::scopeActivos.
 *   3. El `if (in_array(..., IDS_DOS_PASOS))` de PilaCotizanteCalculator.
 *   4. En PlanoPilaTxtService: el `continue` del valor total de nómina y el
 *      `forzarSinSalud` del código de EPS (el `forzarIng` sin fecha lo usa
 *      también la E-1).
 *   5. En PlanillaApiController: la guarda de tanda mezclada, la rama del paso
 *      2 y el reintento con la fecha del operador.
 *   6. En planos/index.blade.php: el aviso de tanda mezclada y la etiqueta del
 *      botón del paso 2.
 *   7. Las filas -5, -10 y -11 de `tipo_modalidad` y sus `modalidad_planes`.
 *
 * Ninguna planilla normal pasa por aquí: todo lo anterior está detrás de un
 * `in_array($modalidad, IDS_DOS_PASOS)`.
 *
 * ## Por qué vive en su propia clase
 *
 * Misma razón que PilaCotizanteE1: sus valores contradicen los de cualquier
 * otra modalidad —un día de pensión que nadie compró, salud en cero, riesgos
 * reportados con tarifa cero— y no comparte nada con la rama general salvo el
 * punto de partida. No calcula desde cero: recibe el resultado de la rama
 * general y solo pisa lo que cambia.
 */
class PilaCotizanteDosPasos
{
    /** COLPENSIONES: administradora del día simbólico de pensión. */
    private const AFP_POR_DEFECTO = '25-14';

    /**
     * Días de caja de la corrección de Solo Pensión.
     *
     * No es una venta: la línea C rechaza la caja en cero (`eo.val.2.050` y
     * `2.046`, "los días cotizados a Cajas de Compensación Familiar no son
     * válidos (0), deben ser mayores a cero"). Un día es el mínimo que la deja
     * pasar, y son $2.400 sobre el salario mínimo.
     */
    private const DIAS_CAJA_MINIMOS = 1;

    /**
     * IBC de ese día de caja.
     *
     * El operador **deja modificar el IBC de caja**: reportado en 100, el 4%
     * redondeado a la centena son $100 en vez de los $2.400 que costaría el día
     * proporcional. Es la misma convención que BryNex ya usa para quien no tiene
     * caja (CCF68), y arrastra la misma advertencia benigna: `eo.val.2.261`,
     * "el IBC reportado a Caja es 100 y debería ser X". Advertencia, no error:
     * probado y aceptado el 9-sep-2026.
     */
    private const IBC_CAJA_MINIMO = 100;

    /**
     * @param  array  $res  Resultado de PilaCotizanteCalculator (rama general)
     * @param  object  $p  Fila del plano; `paso_e1` (1|2) decide qué planilla
     * @param  int  $ibcFull  Salario completo
     * @param  string  $codAfpPila  Código PILA de la AFP que trae el plano (puede venir vacío)
     */
    public static function ajustar(array $res, object $p, int $ibcFull, string $codAfpPila): array
    {
        // `paso_e1` es la columna que PlanoPilaTxtService usa para decir qué
        // línea de la corrección se está armando: 1 = la A (lo que ya quedó
        // pagado) y 2 = la C (lo corregido). En una planilla E normal siempre
        // vale 1. El nombre viene de la E-1, que fue la primera en necesitarlo.
        $paso = ((int) ($p->paso_e1 ?? 1) === 2) ? 2 : 1;
        $modalidad = (int) ($p->tipo_modalidad_id ?? 0);
        $ibcUnDia = self::ibcUnDia($ibcFull);

        // ── Pensión: un día, salvo que sea justo lo que se vendió ────────
        //
        // El día simbólico no es un aporte que el cliente compró: es el mínimo
        // que el operador exige para aceptar el registro. Sin pensión —o con la
        // pensión en cero días— la planilla se cae con eo.val.2.066 ("el tipo
        // de cotizante 01 está obligado a cotizar a Pensión").
        //
        // La AFP sale de la ficha del cliente y no del contrato: cuando el plan
        // no vende pensión, la del contrato no dice nada. El día simbólico
        // igual necesita una administradora o el registro se rechaza.
        $res['tienePension'] = true;
        $res['codAfpPila'] = self::afpDelCliente($p, $codAfpPila);
        $res['subtipoCotizante'] = 0;
        $res['diasPension'] = 1;
        $res['ibcAfp'] = $ibcUnDia;
        $res['tarifaAfpDecimal'] = 0.16;
        $res['vAfp'] = PilaCotizanteCalculator::roundPila($ibcUnDia * 0.16);

        // ── Salud: en cero, con la marca de colombiano en el exterior ────
        //
        // Es la marca la que hace válida la salud en cero: quien está fuera del
        // país no cotiza salud en Colombia. Sin ella salen cinco errores que
        // dicen lo mismo de cinco formas (2.066, 2.043, 2.046, 2.673 y 2.198).
        // Va en las dos planillas: en estas modalidades la salud no llega nunca.
        $res['colombianoExterior'] = true;
        $res['forzarSinSalud'] = true;
        $res['codEpsPila'] = '';
        $res['diasSalud'] = 0;
        $res['ibcEps'] = 0;
        $res['tarifaEpsStr'] = '0.00000';
        $res['vEps'] = 0;

        // ── Riesgos: se reportan, no se cobran ───────────────────────────
        //
        // Acompañan a la pensión en días e IBC porque el operador los amarra
        // (eo.val.2.244 y 2.198), y la tarifa en cero la obliga la novedad de
        // ausentismo (eo.val.2.447). Así el día —o el mes— de riesgos no cuesta
        // nada en ninguna de las dos modalidades.
        $res['diasArl'] = 1;
        $res['ibcArl'] = $ibcUnDia;
        $res['tarifaArlStr'] = '0.00000';
        $res['tarifaArlDecimal'] = 0.0;
        $res['vArl'] = 0;

        // ── Novedades que sostienen el archivo ───────────────────────────
        //
        // ING justifica ante el validador que se coticen menos de 30 días: sin
        // ING ni RET, Enlace rechaza con eo.val.2.151 y su equivalente en
        // riesgos, eo.val.2.484. VAC-LR (licencia remunerada) es la novedad de
        // ausentismo que explica el resto del mes; es también la que obliga a
        // que la tarifa de riesgos vaya en cero.
        $res['forzarIng'] = true;
        $res['novedades'] = array_merge($res['novedades'] ?? [], ['VACLR' => 'L']);
        $res['horasLaboradas'] = 0;

        // ── Caja: en cero en el paso 1 ───────────────────────────────────
        //
        // El aporte entra completo en la corrección, así el cliente lo paga una
        // sola vez y no un día ahora y el resto después. Enlace acepta la
        // planilla E con la caja en cero —probado y pagado— aunque en la línea
        // C de una corrección ya no la admita (eo.val.2.050).
        $res['diasCcf'] = 0;
        $res['ibcCcf'] = 0;
        $res['vCcf'] = 0;

        if ($paso === 1) {
            return $res;
        }

        // ── La corrección: aquí se anexa lo que se vendió ────────────────
        //
        // Sin caja propia (la convención CCF68) no hay nada que anexar y los
        // $100 de la rama general se quedan como están: estas modalidades no se
        // le deberían vender a quien no tiene caja.
        $sinCaja = (bool) ($res['sinCaja'] ?? false);

        if (in_array($modalidad, TipoModalidad::IDS_SOLO_PENSION, true)) {
            // Pensión al mes vendido, y los riesgos con ella porque el operador
            // los amarra. El operador cobra solo la diferencia contra el día
            // que ya se pagó en el paso 1.
            $dias = self::diasVendidos($p, 'dias_afp');

            $res['diasPension'] = $dias;
            $res['ibcAfp'] = self::ibcProporcional($ibcFull, $dias);
            $res['vAfp'] = PilaCotizanteCalculator::roundPila($res['ibcAfp'] * 0.16);

            $res['diasArl'] = $dias;
            $res['ibcArl'] = $res['ibcAfp'];

            // El día de caja que la línea C exige para no ir en cero, al IBC
            // mínimo: son $100, no los $2.400 del día proporcional.
            if (! $sinCaja) {
                $res['diasCcf'] = self::DIAS_CAJA_MINIMOS;
                $res['ibcCcf'] = self::IBC_CAJA_MINIMO;
                $res['vCcf'] = PilaCotizanteCalculator::roundPila(
                    self::IBC_CAJA_MINIMO * (float) $res['tarifaCcfStr']
                );
            }

            return $res;
        }

        // Solo Caja: los días los fija la modalidad y no el contrato —"Caja 30
        // días" y "Caja 14 días" son la misma cosa con distinto número en
        // `tipo_modalidad.dias_caja`—. El IBC va proporcional exacto, sin
        // redondear a la centena: el operador lo compara contra salario × días
        // y avisa con eo.val.2.261 si no coincide al peso.
        if (! $sinCaja) {
            $dias = self::diasVendidos($p, 'dias_caja');

            $res['diasCcf'] = $dias;
            $res['ibcCcf'] = self::ibcProporcional($ibcFull, $dias);
            $res['vCcf'] = PilaCotizanteCalculator::roundPila(
                $res['ibcCcf'] * (float) $res['tarifaCcfStr']
            );
        }

        return $res;
    }

    /**
     * IBC del día simbólico de pensión y riesgos. Lo necesita el registro tipo
     * 1 para sumar el valor total de la nómina sin volver a pasar por el
     * calculador.
     */
    public static function ibcUnDia(int $ibcFull): int
    {
        return (int) round($ibcFull / 30);
    }

    /**
     * Los días que vende la modalidad, del catálogo. Abrir una variante nueva
     * —caja de 20 días, pensión de 15— es una fila en `tipo_modalidad`, no una
     * línea de código.
     */
    private static function diasVendidos(object $p, string $columna): int
    {
        return max(1, min(30, (int) ($p->{$columna} ?? $p->num_dias ?? 30)));
    }

    /** IBC proporcional a los días, exacto: la misma fórmula de la rama general. */
    private static function ibcProporcional(int $ibcFull, int $dias): int
    {
        return $dias < 30 ? (int) round($ibcFull * $dias / 30) : $ibcFull;
    }

    /**
     * La AFP del día simbólico: la de la ficha del cliente, la del plano si la
     * ficha no la tiene, y COLPENSIONES como último recurso —el régimen al que
     * se pertenece por defecto, sin trámite de traslado—.
     */
    private static function afpDelCliente(object $p, string $codAfpDelPlano): string
    {
        foreach ([(string) ($p->cod_afp_cliente ?? ''), $codAfpDelPlano] as $codigo) {
            $codigo = trim($codigo);
            if ($codigo !== '' && $codigo !== '0' && strtoupper($codigo) !== 'N/A') {
                return $codigo;
            }
        }

        return self::AFP_POR_DEFECTO;
    }
}
