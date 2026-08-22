<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dueño del módulo de finanzas personales
    |--------------------------------------------------------------------------
    |
    | Finanzas es un módulo aparte, de acceso exclusivo del dueño. Su cédula
    | estaba escrita a mano en el middleware de acceso, en tres puntos del
    | controlador de chat de WhatsApp, en el servicio del webhook, en un
    | comando de importación y en una vista.
    |
    | El riesgo no era el rendimiento sino el silencio: si ese registro cambia
    | de cédula o se borra, los filtros de privacidad que ocultan a los deudores
    | del dueño dejan de aplicarse sin lanzar ningún error, y esas
    | conversaciones aparecen en el panel de los demás usuarios.
    |
    | Ojo: `SuaportePdfService` y `PlanillaFormularioService` también tienen ese
    | número, pero ahí significa otra cosa — es la cédula por defecto del
    | representante en un documento PDF. Esas NO deben leer de aquí.
    |
    */

    'cedula_dueno' => env('FINANZAS_CEDULA_DUENO', '1143944458'),

    /*
    |--------------------------------------------------------------------------
    | Caché de los teléfonos de deudores
    |--------------------------------------------------------------------------
    |
    | La lista se consultaba en cada sondeo del badge de WhatsApp —cada 30 s por
    | pestaña abierta— y cada consulta cruzaba a la conexión `finanzas`, que es
    | otra base de datos.
    |
    | El costo de cachear es que un préstamo nuevo tarda hasta este tiempo en
    | ocultarse del panel de los demás. Por eso son minutos y no horas, y por
    | eso existe `TelefonosDeudores::olvidar()` para invalidarla al guardar un
    | préstamo.
    |
    */

    'cache_deudores_segundos' => env('FINANZAS_CACHE_DEUDORES', 300),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp personal del dueño
    |--------------------------------------------------------------------------
    |
    | Adonde se reenvía el mensaje cuando escribe un deudor. Estaba escrito a
    | mano dentro de `WhatsappWebhookService`. Si queda vacío, el reenvío
    | simplemente no ocurre — no se rompe la recepción del mensaje.
    |
    */

    'whatsapp_personal_dueno' => env('FINANZAS_WHATSAPP_DUENO', '573117762689'),

];
