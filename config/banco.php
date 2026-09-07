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

    // Débitos que el banco cobra y BryNex no registra en el libro. El cruce
    // los marca `ignorado` en vez de dejarlos como diferencia sin explicar.
    'costos_bancarios' => [
        'GMF',
        '4X1000',
        'CUOTA DE MANEJO',
        'C MANEJO TARJ',
        'COMISION',
        'IVA COMISION',
        'COBRO IVA',
        'SERVICIO TRANSFERENCIA',
        'RETENCION',
    ],

    // Entradas que pone el banco, no un cliente. En el extracto de julio de
    // Brygar eran 28 abonos de intereses; sin esta lista aparecen cada mes como
    // «entró y nadie registró».
    'creditos_ignorados' => [
        'ABONO INTERESES',
        'REVERSION',
        'REINTEGRO',
    ],

    // Días que puede correrse la fecha del banco frente a la del libro. Dos
    // cubre el caso normal (consignó tarde, el banco lo aplicó al otro día);
    // más allá empiezan a cruzarse pagos de clientes distintos por el mismo
    // valor.
    'dias_tolerancia' => (int) env('BANCO_DIAS_TOLERANCIA', 2),

    // Los gastos aguantan más holgura que las consignaciones: un pago de
    // planilla se registra con la fecha de la planilla, no con la del día en
    // que salió la plata. Diez días cubren ese desfase.
    //
    // Más allá empieza a cruzar planillas de meses distintos: con treinta días
    // emparejaba un pago del 3 de junio con un gasto del 3 de julio por el
    // mismo valor, que es la misma planilla mensual, no el mismo pago.
    'dias_tolerancia_gastos' => (int) env('BANCO_DIAS_TOLERANCIA_GASTOS', 10),

    'timeout' => (int) env('BANCO_API_TIMEOUT', 30),
    'connect_timeout' => (int) env('BANCO_API_CONNECT_TIMEOUT', 10),
];
