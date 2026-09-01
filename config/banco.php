<?php

/**
 * Conexión con el API del banco (extracto y saldos).
 *
 * Hoy solo existe el adaptador falso: Bancolombia todavía no activa el
 * producto para Brygar (ver docs/plan-api-bancolombia.md). Cuando lleguen las
 * credenciales se agrega la clase real al mapa `proveedores` y se cambia
 * `proveedor` — el resto del sistema no se entera.
 *
 * Las credenciales NO van aquí: cuando existan, van cifradas por cuenta
 * bancaria, como en `razon_social_credenciales`. Este archivo solo tiene lo
 * que es igual para todas las cuentas.
 */
return [

    // fake | (bancolombia, cuando exista)
    'proveedor' => env('BANCO_API_PROVEEDOR', 'fake'),

    'proveedores' => [
        'fake' => \App\Services\Banco\Providers\FakeBancoProvider::class,
    ],

    // Cuántos días hacia atrás sincroniza una corrida sin fechas explícitas.
    // Cinco cubre un puente festivo: si el worker se cae un viernes, el martes
    // todavía alcanza a recoger lo que quedó pendiente.
    'dias_atras' => (int) env('BANCO_API_DIAS_ATRAS', 5),

    // Tope de días por corrida. Los APIs de extracto suelen limitar el rango,
    // y pedir un año de una sentada es la forma más rápida de que corten.
    'max_dias_rango' => (int) env('BANCO_API_MAX_DIAS_RANGO', 90),

    // Filas por INSERT. Con ~250 ms de latencia contra el SQL Server, insertar
    // uno por uno convierte 300 movimientos en más de un minuto de red.
    'lote_insert' => (int) env('BANCO_API_LOTE_INSERT', 100),

    'timeout' => (int) env('BANCO_API_TIMEOUT', 30),
    'connect_timeout' => (int) env('BANCO_API_CONNECT_TIMEOUT', 10),
];
