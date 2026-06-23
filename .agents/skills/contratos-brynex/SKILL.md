---
name: contratos-brynex
description: >
  Lógica de negocio de contratos, planes, modalidades, ARL y seguridad social en Brynex.
  Actívate cuando el usuario mencione: contrato, plan, modalidad, tiempo parcial, ARL,
  EPS, pensión, caja, IBC, salario, afiliación, retiro, cotizante, seguridad social,
  ContratoController, PilaCotizanteCalculator, TipoModalidad.
---

# Skill: Contratos y Seguridad Social Brynex

## Arquitectura del Módulo

```
ContratoController.php (~77KB)   ← Controlador
PilaCotizanteCalculator.php      ← Servicio de cálculo de aportes PILA

Modelos:
├── Contrato.php          ← Entidad central
├── Cliente.php           ← Persona natural afiliada
├── TipoModalidad.php     ← Modalidad: tiempo completo, parcial, independiente, etc.
├── PlanContrato.php      ← Plan de servicios del aliado
├── Eps.php / Pension.php / Arl.php / Caja.php  ← Entidades de SS
└── ArlTarifa.php         ← Tarifas de ARL por actividad económica
```

## Campos Clave del Contrato

| Campo | Descripción |
|---|---|
| `cedula` | Identificación del cotizante (FK a `clientes.cedula`) |
| `aliado_id` | Aliado propietario del contrato |
| `plan_id` | Plan de servicios contratado |
| `tipo_modalidad_id` | FK a `tipo_modalidades` |
| `eps_id` / `pension_id` / `arl_id` / `caja_id` | Entidades de seguridad social |
| `salario` | Salario mensual del cotizante |
| `ibc` | Ingreso Base de Cotización (puede diferir del salario) |
| `administracion` | Cobro mensual del aliado al cotizante |
| `arl_modo` | Modo de ARL: `brynex` o `cliente` |
| `arl_nit_cotizante` | NIT cuando la ARL la paga directamente el cliente |
| `estado` | `activo`, `retirado`, `suspendido` |
| `fecha_ingreso` / `fecha_retiro` | Fechas de vigencia |
| `cobra_planilla_primer_mes` | Boolean para primer mes |

## Lógica de Tiempo Parcial

```php
// TipoModalidad tiene:
// - dias_tiempo_parcial: int (días trabajados en el mes)
// - es_tiempo_parcial: boolean

// El IBC en tiempo parcial se calcula:
$ibcTiempoParcial = ($salario / 30) * $dias_tiempo_parcial;
$ibcFinal = max($ibcTiempoParcial, $smmlvProporcional);

// Planes de tiempo parcial tienen modalidades específicas
// en la tabla tipo_modalidad_planes (pivote)
```

## Cálculo de ARL

```php
// ArlTarifa se obtiene por:
// - actividad_economica_id del contrato
// - clase de riesgo de la actividad económica

// Tarifa ARL = IBC * (porcentaje_arl / 100)
// Quien paga depende de arl_modo:
// - 'brynex': lo procesa el aliado en la planilla
// - 'cliente': el cotizante paga directamente con su NIT
```

## Flujo de Afiliación

1. Crear/buscar `Cliente` por cédula
2. Crear `Contrato` con plan, modalidad, entidades SS y fechas
3. Registrar `Bitacora` del evento de afiliación
4. Si el aliado lo requiere, generar formularios EPS

## Vistas del Módulo

```
resources/views/admin/contratos/
├── index.blade.php        ← Lista con filtros
├── show.blade.php         ← Detalle con histórico
├── create.blade.php       ← Formulario nuevo contrato
└── edit.blade.php         ← Edición de contrato

resources/views/admin/afiliaciones/
└── index.blade.php        ← Vista de afiliaciones activas
```

## Queries Frecuentes

```php
// Contratos activos de un aliado con relaciones
Contrato::where('aliado_id', $alidoId)
    ->where('estado', 'activo')
    ->with(['cliente', 'eps', 'pension', 'arl', 'caja', 'tipoModalidad', 'planContrato'])
    ->get();

// Contratos que vencen este mes
Contrato::where('aliado_id', $alidoId)
    ->whereMonth('fecha_retiro', now()->month)
    ->whereYear('fecha_retiro', now()->year)
    ->get();
```

## Nomenclatura de Planes

Los planes tienen nombre descriptivo tipo:
- `EPS + AFP + CCF (Tiempo Completo)`
- `Solo EPS (Independiente)`
- `EPS + AFP + ARL + CCF (Tiempo Parcial)`

Están en la tabla `tipo_modalidad_planes` con columnas de configuración de qué entidades incluye.
