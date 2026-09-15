<?php

/*
 * EPS que usan el portal de empleadores Boxalud (ASP.NET + DevExpress, usuario
 * NIT+P por razón social, reCAPTCHA invisible en el login). La afiliación la opera
 * la extensión BryNex Portales en el Chrome de la persona (BoxaludService).
 *
 * - host: dominio del portal (la extensión solo actúa en esos dominios).
 * - clave_entidad: patrón LIKE contra clave_accesos.entidad para el usuario del portal.
 * - tipos_documento: tipo de BryNex => valor del combo del afiliado en el portal.
 * - documentos: id de tipo de documento del portal donde se adjunta cada archivo.
 *
 * Mapeado el 15-sep-2026 en Emssanar con la sesión de Construtech, sin guardar nada.
 * Asmet Salud usa la misma plataforma (boxaludrc.asmetsalud.org.co): falta mapearla
 * antes de activarla aquí.
 */
return [
    'emssanar' => [
        'nombre'        => 'Emssanar',
        'codigo_eps'    => 'ESSC18',
        'host'          => 'boxalud.emssanareps.co',
        'clave_entidad' => '%MSSANAR%',
        'correo'        => 'emssanar',   // clave en afiliaciones_correo.asesores para el plan B
        'tipos_documento' => [
            'CC' => 2, 'TI' => 3, 'RC' => 4, 'CE' => 5, 'PA' => 6, 'PP' => 6, 'MS' => 8, 'CD' => 9,
            'CN' => 10, 'PE' => 11, 'SC' => 12, 'PT' => 15, 'PPT' => 15,
        ],
        'documentos' => ['formulario' => 7, 'encuesta' => 46],
    ],
];
