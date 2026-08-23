<?php

namespace App\Services;

/**
 * PilaCotizanteE1 — modalidad E-1 (tipo_modalidad_id = -4).
 *
 * Pagar salud (y ARL/caja) SIN pensión, en dos planillas.
 *
 * El operador rechaza una planilla E ordinaria con 30 días de salud y cero de
 * pensión, así que el pago se parte:
 *
 *   paso 1 — planilla E: un día de pensión y nada más. Salud y caja van en
 *            cero y la ARL se reporta con tarifa cero.
 *   paso 2 — planilla N (corrección de la anterior, ya pagada): salud, ARL y
 *            caja suben a los días reales con IBC completo, y el subtipo pasa
 *            a 4 (requisitos cumplidos para pensión) para justificar que la
 *            pensión se haya quedado en un solo día.
 *
 * La línea A de la corrección es el paso 1 y la línea C es el paso 2, así que
 * las dos salen de aquí cambiando `paso_e1` sobre la misma fila del plano.
 *
 * ## Por qué vive en su propia clase
 *
 * Es un esquema temporal —un rodeo a una validación del operador, no una
 * regla PILA— y sus valores contradicen los de cualquier otra modalidad: un
 * día de pensión que nadie compró y un mes de salud que llega tarde. Metido
 * dentro de PilaCotizanteCalculator sería una excepción más que hay que
 * recordar al tocar la rama general. Aquí, el día que el operador acepte la
 * planilla directa, se borra este archivo y la línea que lo llama.
 *
 * No calcula desde cero: recibe el resultado de la rama general del
 * calculador y solo pisa lo que cambia.
 */
class PilaCotizanteE1
{
    /** COLPENSIONES: administradora del día simbólico de pensión. */
    private const AFP_POR_DEFECTO = '25-14';

    /**
     * Operadores que NO exigen alinear pensión con riesgos en el paso 1, y a
     * los que por tanto se les puede mandar la ARL en cero días.
     *
     * ARUS rechaza esa forma con eo.val.2.244.2 ("los días de pensión y
     * riesgos deben ser iguales") y eo.val.2.198.2, su equivalente en IBC;
     * Simple la acepta. Como el día de ARL va con tarifa cero, las dos formas
     * liquidan por el mismo valor, así que el default —para un operador que no
     * esté en esta lista— es el día puesto, que es el que pasa en todas partes.
     *
     * Códigos PILA de operador de información: 88 = Simple, 89 = ARUS Enlace.
     */
    private const OPERADORES_SIN_ALINEAR_RIESGOS = ['88'];

    /** Departamento del cotizante sin caja propia, con la marca de exterior. */
    private const DEP_SIN_CAJA = '95';

    private const MUN_SIN_CAJA = '1';

    /**
     * @param  array  $res  Resultado de PilaCotizanteCalculator (rama general)
     * @param  object  $p  Fila del plano; `paso_e1` (1|2) decide qué planilla
     * @param  int  $ibcFull  Salario completo
     * @param  string  $codAfpPila  Código PILA de la AFP que trae el plano (puede venir vacío)
     * @param  bool  $sinCaja  El cotizante no tiene caja propia (convención CCF68)
     */
    public static function ajustar(
        array $res,
        object $p,
        int $ibcFull,
        string $codAfpPila,
        bool $sinCaja
    ): array {
        $paso = ((int) ($p->paso_e1 ?? 1) === 2) ? 2 : 1;
        $ibcUnDia = (int) round($ibcFull / 30);

        // `variante_e1` permite probar otras formas del archivo contra el
        // validador del operador sin tocar esta clase entre intento e intento.
        // 'dos_pasos' es la que está en producción; las 'directo_*' buscan
        // resolverlo en una sola planilla, sin corrección ni segundo pago.
        $variante = (string) ($p->variante_e1 ?? 'dos_pasos');

        if ($variante !== 'dos_pasos') {
            return self::variante($variante, $res, $ibcFull, $ibcUnDia, $codAfpPila, $p, $sinCaja);
        }

        // Manda la AFP de la ficha del cliente, no la del contrato.
        //
        // El plan de esta modalidad no incluye pensión, así que la AFP que
        // traiga el contrato —y con ella el plano— no dice nada: lo que importa
        // es el fondo al que la persona está realmente afiliada, que es el que
        // vive en su ficha. El día simbólico igual necesita una administradora
        // o el operador rechaza el registro, así que si la ficha no la tiene se
        // cae al contrato, y en último caso a COLPENSIONES —el régimen al que
        // se pertenece por defecto, sin trámite de traslado—.
        $codAfp = self::afpDelCliente($p, $codAfpPila);

        // Dentro de una corrección la administradora de pensión se calla en las
        // dos líneas: ver el comentario de PlanoPilaTxtService al armarlas.
        if (! empty($p->en_correccion)) {
            $codAfp = '';
        }

        // La pensión se queda en un día en las DOS planillas: es el mínimo que
        // el operador exige para aceptar el registro, no un aporte comprado.
        $res['colombianoExterior'] = false;
        $res['tienePension'] = true;
        $res['codAfpPila'] = $codAfp;
        $res['subtipoCotizante'] = ($paso === 2) ? 4 : 0;
        $res['diasPension'] = 1;
        $res['ibcAfp'] = $ibcUnDia;
        $res['tarifaAfpDecimal'] = 0.16;
        $res['vAfp'] = PilaCotizanteCalculator::roundPila($ibcUnDia * 0.16);

        if ($paso === 2) {
            // El subtipo 4 y una administradora de pensión no pueden convivir:
            // "El código de la administradora de Pensión debe estar vacío"
            // (eo.val.2.116). Los días, el IBC y la cotización del día de
            // pensión sí se conservan —así viene el archivo que el operador
            // aceptó—, pero la administradora se calla.
            $res['codAfpPila'] = '';

            // El operador exige IBC de otros parafiscales cuando el cotizante
            // va marcado como exonerado (eo.val.2.439); el archivo del cliente
            // lo trae con el IBC del día de pensión.
            if ($res['exonerado'] === 'S') {
                $res['ibcOtros'] = $ibcUnDia;
            }

            // Salud, ARL y caja se quedan con los días reales y el IBC
            // completo que ya calculó la rama general: eso es exactamente lo
            // que la corrección viene a agregar.
            return $res;
        }

        // Salud en cero, con el cotizante marcado como colombiano en el
        // exterior (campo 8).
        //
        // Sin esa marca la salud en cero devuelve cinco errores que dicen lo
        // mismo de cinco formas —el tipo de cotizante 01 está obligado a
        // cotizar salud (eo.val.2.066), su administradora no puede ir vacía
        // (2.043), los días deben ser mayores a cero (2.046), y los días e IBC
        // de los cuatro subsistemas tienen que coincidir (2.673 y 2.198)—. Con
        // la marca no aplican: quien está fuera del país no cotiza salud en
        // Colombia. La salud completa entra en la corrección.
        $res['colombianoExterior'] = true;
        $res['codEpsPila'] = '';
        $res['diasSalud'] = 0;
        $res['ibcEps'] = 0;
        $res['tarifaEpsStr'] = '0.00000';
        $res['vEps'] = 0;

        // El departamento tiene que concordar con la caja: una caja regional en
        // un departamento que no cubre se rechaza con eo.val.2.162 ("no presta
        // cubrimiento en el departamento ingresado"). Con caja propia se manda
        // el departamento real del cotizante; sin ella, la convención CCF68 va
        // con el departamento 95.
        if ($sinCaja) {
            $res['depCod'] = self::DEP_SIN_CAJA;
            $res['munCod'] = self::MUN_SIN_CAJA;
        }

        // La ARL nunca cobra en el paso 1 —el aporte de riesgos completo entra
        // en la corrección—, pero si va con días o en cero depende del
        // operador: ver OPERADORES_SIN_ALINEAR_RIESGOS.
        //
        // La tarifa en cero, en cambio, la obliga la novedad de ausentismo en
        // todos: "cuando se presenta novedad de ausentismo la tarifa de ARL
        // debe de ser cero" (eo.val.2.447).
        $alineaRiesgos = ! in_array(
            trim((string) ($p->codigo_operador ?? '')),
            self::OPERADORES_SIN_ALINEAR_RIESGOS,
            true
        );
        $res['diasArl'] = $alineaRiesgos ? 1 : 0;
        $res['ibcArl'] = $alineaRiesgos ? $ibcUnDia : 0;
        $res['tarifaArlStr'] = '0.00000';
        $res['tarifaArlDecimal'] = 0.0;
        $res['vArl'] = 0;

        // La caja va en cero, igual que la salud: el mes completo de caja entra
        // en la corrección. Sin esto el paso 1 cobraría un día de caja que
        // después habría que volver a cobrar completo.
        $res['diasCcf'] = 0;
        $res['ibcCcf'] = 0;
        $res['vCcf'] = 0;

        $res['horasLaboradas'] = 0;

        return $res;
    }

    /**
     * IBC del día simbólico de pensión y caja del paso 1. Lo necesita el
     * registro tipo 1 para sumar el valor total de la nómina sin volver a
     * pasar por el calculador.
     */
    public static function ibcUnDia(int $ibcFull): int
    {
        return (int) round($ibcFull / 30);
    }

    /**
     * Variantes de una sola planilla, para medirlas contra el validador.
     *
     * Todas dejan salud, riesgos y caja en el mes completo —que es el destino—
     * y solo cambian cómo justifican que la pensión no se cotice.
     */
    private static function variante(
        string $variante,
        array $res,
        int $ibcFull,
        int $ibcUnDia,
        string $codAfpPila,
        object $p,
        bool $sinCaja
    ): array {
        // Punto de partida común: subtipo 4 (requisitos cumplidos para
        // pensión), que es la excepción legal a cotizar pensión, y salud,
        // riesgos y caja con los días reales que ya trae la rama general.
        $res['subtipoCotizante'] = 4;

        switch ($variante) {
            // Sin pensión del todo. Es la ruta "limpia" que el módulo ya sabe
            // producir cuando el contrato va sin AFP.
            case 'directo_sin_pension':
                $res['tienePension'] = false;
                $res['codAfpPila'] = '';
                $res['diasPension'] = 0;
                $res['ibcAfp'] = 0;
                $res['tarifaAfpDecimal'] = 0.0;
                $res['vAfp'] = 0;
                break;

                // Un día de pensión, el resto del mes completo. Sin novedades.
            case 'directo_un_dia':
                $res['tienePension'] = true;
                $res['codAfpPila'] = self::afpDelCliente($p, $codAfpPila);
                $res['diasPension'] = 1;
                $res['ibcAfp'] = $ibcUnDia;
                $res['tarifaAfpDecimal'] = 0.16;
                $res['vAfp'] = PilaCotizanteCalculator::roundPila($ibcUnDia * 0.16);
                break;

                // Un día de pensión, subtipo 0: sin invocar ninguna excepción.
            case 'directo_un_dia_sin_subtipo':
                $res['subtipoCotizante'] = 0;
                $res['tienePension'] = true;
                $res['codAfpPila'] = self::afpDelCliente($p, $codAfpPila);
                $res['diasPension'] = 1;
                $res['ibcAfp'] = $ibcUnDia;
                $res['tarifaAfpDecimal'] = 0.16;
                $res['vAfp'] = PilaCotizanteCalculator::roundPila($ibcUnDia * 0.16);
                break;

                // Un día de pensión, subtipo 0 y novedad de ingreso marcada. La ING
                // es lo que autoriza cotizar menos de 30 días (eo.val.2.151); la
                // pregunta que resuelve esta variante es si además relaja las
                // reglas de "los días e IBC deben ser iguales" (2.198 y 2.244).
            case 'directo_un_dia_ing':
                $res['subtipoCotizante'] = 0;
                $res['forzarIng'] = true;
                $res['tienePension'] = true;
                $res['codAfpPila'] = self::afpDelCliente($p, $codAfpPila);
                $res['diasPension'] = 1;
                $res['ibcAfp'] = $ibcUnDia;
                $res['tarifaAfpDecimal'] = 0.16;
                $res['vAfp'] = PilaCotizanteCalculator::roundPila($ibcUnDia * 0.16);
                break;

                // Sin pensión invocando el subtipo 3 (no obligado por edad).
            case 'directo_subtipo3':
                $res['subtipoCotizante'] = 3;
                $res['tienePension'] = false;
                $res['codAfpPila'] = '';
                $res['diasPension'] = 0;
                $res['ibcAfp'] = 0;
                $res['tarifaAfpDecimal'] = 0.0;
                $res['vAfp'] = 0;
                break;

                // ── Sin pensión, justificada con novedades en vez de subtipo ──
                //
                // El subtipo 3 y el 4 los valida la UGPP contra los datos reales de
                // la persona (eo.val.2.655), así que quien no califica no puede
                // invocarlos. Estas variantes buscan la misma exención por el otro
                // lado: una novedad de ausentismo que explique por qué no hay
                // pensión ese mes.
            case 'nov_vaclr_sin_pension':
            case 'nov_sln_sin_pension':
            case 'nov_vaclr_ing_sin_pension':
            case 'nov_sln_ing_sin_pension':
                $res['subtipoCotizante'] = 0;
                $res['tienePension'] = false;
                $res['codAfpPila'] = '';
                $res['diasPension'] = 0;
                $res['ibcAfp'] = 0;
                $res['tarifaAfpDecimal'] = 0.0;
                $res['vAfp'] = 0;

                $res['novedades'] = str_contains($variante, 'vaclr')
                    ? ['VACLR' => 'L']
                    : ['SLN' => 'X'];

                if (str_contains($variante, '_ing_')) {
                    $res['novedades']['ING'] = 'X';
                }

                // El ausentismo obliga a tarifa de riesgos en cero
                // (eo.val.2.447), así que aquí la ARL no se cobra.
                $res['tarifaArlStr'] = '0.00000';
                $res['tarifaArlDecimal'] = 0.0;
                $res['vArl'] = 0;
                break;

                // ── Cambiar el TIPO de cotizante, no el subtipo ────────────────
                //
                // La obligación de cotizar pensión la impone el tipo 01
                // (eo.val.2.066), así que la otra salida es no ser un 01: hay tipos
                // que por definición no cotizan pensión —el aprendiz del SENA en
                // etapa lectiva solo aporta salud, y en etapa productiva salud y
                // riesgos—. Igual que la marca de exterior, es una afirmación sobre
                // la persona que el operador puede contrastar.
            case 'tipo12_aprendiz_lectiva':   // solo salud
            case 'tipo19_aprendiz_productiva': // salud + riesgos
                $res['tipoCotizante'] = $variante === 'tipo12_aprendiz_lectiva' ? 12 : 19;
                $res['subtipoCotizante'] = 0;
                $res['tienePension'] = false;
                $res['codAfpPila'] = '';
                $res['diasPension'] = 0;
                $res['ibcAfp'] = 0;
                $res['tarifaAfpDecimal'] = 0.0;
                $res['vAfp'] = 0;

                if ($variante === 'tipo12_aprendiz_lectiva') {
                    $res['diasArl'] = 0;
                    $res['ibcArl'] = 0;
                    $res['tarifaArlStr'] = '0.00000';
                    $res['tarifaArlDecimal'] = 0.0;
                    $res['vArl'] = 0;
                }

                // El aprendiz no genera aportes de caja ni parafiscales.
                $res['codCcfPila'] = '';
                $res['diasCcf'] = 0;
                $res['ibcCcf'] = 0;
                $res['vCcf'] = 0;
                $res['ibcOtros'] = 0;
                $res['vSena'] = 0;
                $res['vIcbf'] = 0;
                $res['tarifaSenaStr'] = '0.00000';
                $res['tarifaIcbfStr'] = '0.00000';
                break;

                // Sin pensión y marcado como colombiano en el exterior.
            case 'directo_exterior':
                $res['colombianoExterior'] = true;
                $res['tienePension'] = false;
                $res['codAfpPila'] = '';
                $res['diasPension'] = 0;
                $res['ibcAfp'] = 0;
                $res['tarifaAfpDecimal'] = 0.0;
                $res['vAfp'] = 0;
                if ($sinCaja) {
                    $res['depCod'] = self::DEP_SIN_CAJA;
                    $res['munCod'] = self::MUN_SIN_CAJA;
                }
                break;
        }

        return $res;
    }

    /**
     * Administradora de pensión del día simbólico: la de la ficha del cliente
     * primero, la del contrato después, y COLPENSIONES como último recurso.
     */
    private static function afpDelCliente(object $p, string $codAfpDelPlano): string
    {
        return self::primerCodigoReal([
            (string) ($p->cod_afp_cliente ?? ''),
            $codAfpDelPlano,
            self::AFP_POR_DEFECTO,
        ]);
    }

    /** El primer código de administradora utilizable de la lista. */
    private static function primerCodigoReal(array $candidatos): string
    {
        foreach ($candidatos as $codigo) {
            $codigo = trim((string) $codigo);
            if ($codigo !== '' && $codigo !== '0' && strtoupper($codigo) !== 'N/A') {
                return $codigo;
            }
        }

        return self::AFP_POR_DEFECTO;
    }
}
