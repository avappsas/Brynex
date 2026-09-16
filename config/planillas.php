<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Soportes del operador al confirmar el pago
    |--------------------------------------------------------------------------
    |
    | Al confirmar el pago de una planilla de ARUS o Simple se bajan del
    | operador los informes individuales de todas las personas y quedan en
    | disco, listos para el envío por WhatsApp (ver DescargarSoportesPlanillaJob).
    |
    | El operador tarda en mostrar el pago: PSE pasa por un estado intermedio y
    | un pago en horario extendido aparece al día siguiente. Por eso se espera
    | antes del primer intento y se reintenta; si al final la planilla sigue sin
    | aparecer, casi siempre es el número mal digitado al confirmar.
    |
    */

    // Minutos antes de cada intento: el primero, y los reintentos. Suman ~15 h.
    'soportes_esperas_minutos' => [10, 30, 120, 720],

    // A qué WhatsApp se avisa, por aliado, cuando no se pudieron bajar.
    // Aliado sin número: el aviso queda solo en el log.
    'soportes_aviso_whatsapp' => [
        2 => ['3117762689'], // Brygar
    ],
];
