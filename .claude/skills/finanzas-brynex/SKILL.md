---
name: finanzas-brynex
description: >
  Módulo de finanzas personales de Brayan García (dueño de Brynex), separado
  del negocio de los aliados. Actívate cuando el usuario mencione: finanzas
  personales, cuenta/bolsillo, patrimonio, préstamo, interés mensual,
  inversión, proyecto, entrada, fuente de ingreso, app líderes, brynex
  aliados (como ingreso), FinanzasDashboardController, finanzas.access.
---

# Skill: Finanzas Personales — Brynex

**No confundir con el negocio de los aliados** (cobros, facturación,
comisiones de asesores). Este módulo es la contabilidad personal de un solo
usuario: Brayan García, dueño de Brynex. Vive en su propia base de datos y
solo él puede entrar.

## Acceso: exclusivo, hardcodeado

```php
// app/Http/Middleware/FinanzasAccess.php
if (!$user || $user->cedula !== '1143944458' || !$user->hasRole('superadmin') || !$user->es_brynex) {
    abort(403);
}
```

Todas las rutas bajo `Route::prefix('finanzas')` llevan `middleware(['finanzas.access'])`
(routes/web.php). No es un módulo multi-tenant: no hay `aliado_id` en ningún
modelo de finanzas. Si algún día se necesita abrir a más de un usuario, este
middleware es el primer lugar a tocar — hoy es literal a una cédula.

## Base de datos separada

```php
// app/Models/Finanzas/BaseFinanzasModel.php
protected $connection = 'finanzas';   // conexión BryNex_Finanzas, no BryNex
```

Todos los modelos de `app/Models/Finanzas/` extienden `BaseFinanzasModel`.
Al escribir queries crudas o migraciones de este módulo, usar la conexión
`finanzas`, no la default.

## Entidades

```
Cuenta            ← "bolsillo" (banco, efectivo, Nequi...). Saldo CALCULADO,
                     no columna: suma entradas/abonos/transferencias entrantes
                     menos gastos/desembolsos/transferencias salientes.
CuentaTransferencia ← movimiento entre dos Cuentas propias
Entrada / FuenteIngreso ← ingresos recurrentes y esporádicos
Gasto / CategoriaGasto  ← egresos clasificados
Prestamo / PrestamoMovimiento ← dinero prestado a terceros, con interés
Inversion / InversionMovimiento ← inversiones (incluye cripto)
Proyecto / ProyectoMovimiento   ← proyectos con entradas/salidas propias
Patrimonio / PatrimonioGasto    ← activos y su depreciación/gasto asociado
AppLiderAliado / AppLiderPago / AppLiderRecibo       ← ingresos de "App Líderes" (otro producto)
BrynexPago / BrynexRecibo                             ← ingresos que Brynex (el negocio) le paga a Brayan
```

`AppLiderAliado` y el de `BrynexAliado` (rutas `app-lideres.*` y
`brynex-aliados.*`) son básicamente lo mismo: una tabla tipo hoja de cálculo
(`saveCell`) para llevar cobros de aliados con recibos adjuntos, tratados
como fuente de ingreso personal. Si se toca uno, revisar si el otro necesita
el mismo cambio — son controladores gemelos, no relacionados por herencia.

## Préstamos: interés simple mensual sobre saldo

```php
// PrestamoLiquidacionService — se ejecuta con el comando programado
// app/Console/Commands/LiquidarInteresesPrestamos.php
interes_del_corte = saldo_actual * (tasa_interes_mensual / 100)
```

Estados: `activo`, `pagado`, `mora`, `castigado`. `ultimo_corte` marca hasta
cuándo ya se liquidó interés; no volver a liquidar el mismo periodo dos veces.

## Dashboard con carga progresiva

`FinanzasDashboardController::index` sirve la vista vacía; los datos llegan
por los endpoints AJAX bajo `/finanzas/api/*` (`resumen`, `evolucion`,
`consolidado`, `cuentas`, `alertas`) — evita cargar todo el patrimonio en el
request inicial. Si se agrega una sección nueva al dashboard, seguir este
patrón (endpoint propio) en vez de meterlo en `index()`.

## Soportes de gastos/préstamos

Se guardan en disco `local` (privado), con `mimes` validado — ver
`GastoController`, `PrestamoController`, `ProyectoController`. Correcto tal
como está; no cambiar a disco `public`.
