<?php

/*
 * Correos de afiliación a las EPS.
 *
 * - buzones: cuenta de Gmail de cada aliado desde la que salen los correos. La
 *   contraseña de aplicación vive en el módulo de claves (misma cuenta en
 *   "usuario", entidad con "GMAIL").
 * - asesores: a quién va el correo si la razón social no tiene "correo de la
 *   entidad" en su clave del portal. El reemplazo se ofrece cuando el asesor
 *   está de vacaciones (Juan Torres las anunció del 19-ago al 8-sep-2026).
 */
return [
    'buzones' => [
        2 => 'seguridadsocial.brygar@gmail.com',
    ],

    'asesores' => [
        'sos' => [
            'nombre_entidad' => 'S.O.S.',
            'principal'      => ['nombre' => 'Juan Carlos Torres', 'correo' => 'jtorres.qta@sos.com.co'],
            'reemplazo'      => ['nombre' => 'Marien Ruiz Mina', 'correo' => 'mrmina@sos.com.co'],
        ],
    ],

    // Sin respuesta al vencer se avisa. En el buzón la mediana es ~19 h y casi
    // siempre responde el día hábil siguiente: se espera hasta ese día a esta hora.
    'hora_vencimiento' => 12,
];
