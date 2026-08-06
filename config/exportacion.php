<?php

/**
 * Entrega de datos a un aliado que se va de la plataforma.
 *
 * La lista blanca vive aquí y no en un permiso de Spatie a propósito: un
 * permiso lo puede otorgar cualquiera que administre permisos, y esta acción
 * saca de la plataforma los datos personales de miles de personas. Para sumar
 * a alguien hay que cambiar código y desplegar, que es exactamente la fricción
 * que se busca.
 */
return [

    /*
     * Correos autorizados. Además de estar en esta lista, el usuario tiene que
     * ser `es_brynex` y tener el rol `superadmin`, y confirmar por WhatsApp.
     */
    'correos_autorizados' => array_filter(array_map(
        'trim',
        explode(',', (string) env('EXPORTACION_CORREOS', 'brayan30030@gmail.com'))
    )),

    /* Minutos de vida del código que llega por WhatsApp. */
    'codigo_minutos' => (int) env('EXPORTACION_CODIGO_MINUTOS', 10),

    /* Intentos de código antes de invalidar la solicitud. */
    'codigo_intentos' => (int) env('EXPORTACION_CODIGO_INTENTOS', 3),

    /* Días que sobrevive el ZIP en el servidor antes de borrarse solo. */
    'dias_retencion' => (int) env('EXPORTACION_DIAS_RETENCION', 7),

    /*
     * Carpeta dentro del disco `local` (storage/app). Nunca en `public`: el
     * repo se sirve desde brynex.co y ya hubo tres scripts filtrados por URL
     * (C-1/C-2/C-3 de la auditoría).
     */
    'carpeta' => 'exportaciones',

    /* Filas por consulta al trocear. GiMave son ~420.000 filas en total. */
    'chunk' => (int) env('EXPORTACION_CHUNK', 2000),

];
