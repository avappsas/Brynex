<?php

/*
 * EPS que usan el portal de empleadores Boxalud (ASP.NET + DevExpress, usuario
 * NIT+P por razón social, reCAPTCHA invisible en el login). La afiliación la opera
 * la extensión BryNex Portales en el Chrome de la persona (BoxaludService).
 *
 * - host: dominio del portal (la extensión solo actúa en esos dominios).
 * - clave_entidad: patrón LIKE contra clave_accesos.entidad para el usuario del portal.
 * - codigos_eps: códigos de la tabla `eps` que corresponden a esa EPS (contributivo
 *   y movilidad), para saber a qué contratos mirarles la afiliación.
 * - tipos_documento: tipo de BryNex => valor del combo del afiliado en el portal.
 * - documentos: id de tipo de documento del portal donde se adjunta cada archivo.
 *
 * Mapeado el 15-sep-2026 en Emssanar con la sesión de Construtech, sin guardar nada.
 * Asmet Salud usa la misma plataforma (boxaludrc.asmetsalud.org.co): falta mapearla
 * antes de activarla aquí.
 */
return [
    'emssanar' => [
        'nombre' => 'Emssanar',
        'codigo_eps' => 'ESSC18',
        'host' => 'boxalud.emssanareps.co',
        'clave_entidad' => '%MSSANAR%',
        'correo' => 'emssanar',   // clave en afiliaciones_correo.asesores para el plan B
        'tipos_documento' => [
            'CC' => 2, 'TI' => 3, 'RC' => 4, 'CE' => 5, 'PA' => 6, 'PP' => 6, 'MS' => 8, 'CD' => 9,
            'CN' => 10, 'PE' => 11, 'SC' => 12, 'PT' => 15, 'PPT' => 15,
        ],
        'documentos' => ['formulario' => 7, 'encuesta' => 46],
        'codigos_eps' => ['ESSC18'],
    ],

    /*
     * De Coosalud por ahora solo se LEE: "Afiliaciones → Consulta afiliaciones"
     * da el listado de la empresa y con eso se cruzan los radicados y los
     * retiros (BoxaludCruceService). La afiliación por portal no está mapeada
     * aquí —por eso no tiene tipos_documento ni documentos—, y la pantalla de
     * afiliaciones tampoco aparece en el módulo de radicación.
     */
    'coosalud' => [
        'nombre' => 'Coosalud',
        'codigo_eps' => 'EPS042',
        'host' => 'sinergia.coosalud.com',
        'clave_entidad' => '%COOSALUD%',
        'codigos_eps' => ['EPS042', 'ESSC24'],  // contributivo y movilidad
        'solo_lectura' => true,
    ],

    /*
     * Asmet Salud es el mismo Boxalud con otro dominio y los mismos usuarios
     * NIT+P, así que el cruce le sirve tal cual. Nunca se ha mirado por dentro:
     * si su menú no tiene "Consulta afiliaciones", la corrida lo dice y no pasa
     * nada más. Tampoco está en el módulo de radicación, como Coosalud.
     */
    'asmet' => [
        'nombre' => 'Asmet Salud',
        'codigo_eps' => 'ESSC62',
        'host' => 'boxaludrc.asmetsalud.org.co',
        'clave_entidad' => '%ASMET%',
        'codigos_eps' => ['ESSC62'],
        'solo_lectura' => true,
    ],
];
