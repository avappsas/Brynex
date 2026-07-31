---
name: afiliaciones-brynex
description: >
  Cotizador público, gestión de vigencia ARL y seguimiento de radicados de
  afiliación en Brynex. Actívate cuando el usuario mencione: cotizador,
  cotización, prospecto, lead, arma tu plan, gestión ARL, semáforo ARL,
  vigencia ARL, renovar ARL, radicado, estado del radicado, motivo de
  afiliación, motivo de retiro, AfiliacionController, GestionArlController,
  CotizacionController, AfiliacionArlModalidad.
---

# Skill: Afiliaciones, Cotizador y Gestión ARL — Brynex

No confundir con [[contratos-brynex]]: ese skill cubre la creación y edición
del contrato/cotizante. Este cubre lo que pasa ANTES (cotizador público, lead)
y DESPUÉS (seguimiento de radicados, vigencia de ARL) de que el contrato existe.

## Los tres flujos del módulo

```
1. COTIZADOR PÚBLICO (sin login)          → CotizacionController + servicios
2. AFILIACIONES (seguimiento admin)        → AfiliacionController
3. GESTIÓN ARL (vigencia de tipo_modalidad_id=15) → GestionArlController
```

## 1. Cotizador público → lead → prospecto → cliente

```
Servicios:
├── CotizadorService.php           ← Calcula el valor del plan (EPS/AFP/ARL/CCF)
└── CotizacionPublicaService.php   ← Lógica del formulario público "Arma tu plan"

Modelos:
├── CotizacionProspecto.php  ← Lead capturado en /aliado/{slug}/cotizar o /lead
└── CotizacionGestion.php    ← Seguimiento comercial del prospecto (llamadas, notas)
```

Ruta pública (sin auth, con `throttle`, ver [routes/web.php](routes/web.php)):
`POST /aliado/{slug}/cotizar` → crea/actualiza un `CotizacionProspecto`.

`CotizacionController` (admin) gestiona esos prospectos:
- `index/show/update` — CRUD del prospecto.
- `registrarGestion` — nota de seguimiento comercial → `CotizacionGestion`.
- `cotizar` — recalcula el valor con `CotizadorService`.
- `convertirACliente` — el prospecto se convierte en `Cliente` + `Contrato` real
  (aquí es donde entra [[contratos-brynex]]).
- `descargarPdf` — cotización en PDF para enviar al lead.

## 2. Afiliaciones (admin/afiliaciones) — seguimiento de radicados

`AfiliacionController::index` lista **contratos vigentes** (no prospectos) y
sus `radicados` por entidad (EPS, ARL, caja, pensión), cada uno con estado:

```php
$estadosPermitidos = ['pendiente', 'tramite', 'traslado', 'error', 'ok'];
```

Filtra por razón social, tipo de modalidad, EPS/ARL/caja/pensión y estado de
radicado. `exportar()` es el mismo query en Excel.

`FormularioEpsController` genera y firma el formulario de afiliación EPS
(`/{contrato}/formulario/eps`), con firma capturada del cliente.

**Motivos** (catálogos simples, sin lógica): `MotivoAfiliacion` (por qué se
afilió) y `MotivoRetiro` (por qué se retiró, con flag `es_reingreso` para
distinguir de una baja definitiva).

## 3. Gestión ARL — semáforo de vigencia

Solo aplica a contratos con `tipo_modalidad_id = GestionArlController::TIPO_MODALIDAD_ARL` (= 15,
"ARL independiente"). La ARL independiente se paga por periodos cortos y vence:

```php
const DIAS_VIGENCIA = 28; // máximo días que dura una afiliación ARL activa

// Semáforo por días restantes hasta el vencimiento:
const VERDE    = 10; // >= 10 días restantes
const AMARILLO = 4;  // 4-9 días restantes
const ROJO     = 3;  // 0-3 días o ya vencido
```

`index()` calcula el semáforo por contrato en tiempo real (no se guarda en
BD) y permite filtrar/ordenar por él. `renovar()` extiende la vigencia.

**Tarifas de ARL** (cuánto cuesta según actividad económica y clase de
riesgo): `ArlTarifa` + servicio `App\Services\NivelesRiesgoArl` — usado tanto
aquí como en el cálculo de administración de [[contratos-brynex]].

**Costo de afiliación por plan** (lo que cobra el aliado al afiliar, distinto
de la tarifa de ARL): `AfiliacionArlModalidad` — combinación de
`aliado_id` + `plan_id` + `tipo_modalidad_id` → `costo_afiliacion`.

## Cuidado

- Hay una ruta de **debug temporal** en `admin/gestion-arl/debug/{cedula}`
  (routes/web.php, con el comentario `DEBUG TEMPORAL — borrar después`) que
  expone `aliado_id`, `es_brynex` y datos de contratos por cédula sin más
  control que `auth`. No usarla como referencia de patrón; hay que eliminarla.
- El semáforo de Gestión ARL es calculado, no columna de BD — no se puede
  filtrar por SQL directo sin replicar la lógica de fechas.
