<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business API (Meta Cloud API) — Cuenta Global Brynex
    |--------------------------------------------------------------------------
    | Los aliados que no tengan cuenta propia usarán estas credenciales.
    | Los aliados con cuenta propia las configuran desde el panel (WhatsappConfig).
    |
    | waba_id:         WhatsApp Business Account ID de Meta
    | phone_number_id: ID del número de teléfono registrado en Meta
    | token:           Token de acceso permanente de Meta
    | numero:          Número visible del WhatsApp Business Brynex
    | app_secret:      App Secret de la Meta App (para validar firma HMAC del webhook)
    | webhook_verify_token: Token que Meta usa para verificar el webhook (GET)
    */
    'whatsapp' => [
        'waba_id'              => env('WHATSAPP_BRYNEX_WABA_ID'),
        'phone_number_id'      => env('WHATSAPP_BRYNEX_PHONE_NUMBER_ID'),
        'token'                => env('WHATSAPP_BRYNEX_TOKEN'),
        'numero'               => env('WHATSAPP_BRYNEX_NUMERO'),
        'app_secret'           => env('WHATSAPP_APP_SECRET'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'brynex_wh_secret_2026'),
    ],

    'suaporte' => [
        'base_url' => env('SUAPORTE_API_URL', 'https://www.suaporte.com.co/api'),
        'auth_url' => env('SUAPORTE_AUTH_URL', 'https://www.suaporte.com.co/auth'),
        'usuario' => env('SUAPORTE_USUARIO'),
        'clave_secreta' => env('SUAPORTE_CLAVE_SECRETA'),
    ],

];
