---
name: facturacion-brynex
description: >
  Lógica de negocio del módulo de Facturación de Brynex. Actívate cuando el usuario
  mencione: factura, facturación, cobro mensual, mora, anticipo, distribución de factura,
  FacturacionController, cálculo de administración, generar factura, estado de factura.
---

# Skill: Facturación Brynex

## Arquitectura del Módulo

```
FacturacionController.php (~167KB)   ← Controlador principal
├── Generación de facturas por mes/aliado
├── Cálculo de administración por contrato
├── Aplicación de anticipos
├── Cálculo de mora
├── Distribución de cobros (encargado / asesor)
└── Exportación Excel / PDF

Modelos relacionados:
├── Factura.php          ← Modelo central
├── Contrato.php         ← Fuente de datos del cotizante  
├── Anticipo.php         ← Anticipos aplicables a facturas
├── Abono.php            ← Pagos parciales
├── Consignacion.php     ← Comprobantes bancarios
└── MoraClienteService   ← Servicio de cálculo de mora
```

## Estructura de la Tabla `facturas`

Columnas clave:
| Columna | Tipo | Descripción |
|---|---|---|
| `aliado_id` | bigint | FK al aliado propietario |
| `contrato_id` | bigint | FK al contrato del cotizante |
| `empresa_id` | bigint | FK a la empresa |
| `mes` | int | Mes de facturación (1-12) |
| `anio` | int | Año de facturación |
| `administracion` | decimal | Valor de administración |
| `admon_asesor` | decimal | Parte del asesor |
| `valor_planilla` | decimal | Valor planilla seguridad social |
| `mora` | decimal | Mora acumulada |
| `anticipo_aplicado` | decimal | Anticipo descontado |
| `otros_ingresos` | decimal | Otros cobros adicionales |
| `estado` | string | `pendiente`, `pagada`, `vencida` |
| `dist_encargado` | decimal | Distribución al encargado |
| `fe_marcada` | boolean | Marcada para facturación electrónica |
| `deleted_at` | timestamp | Soft delete |

## Reglas de Negocio

1. **Generación**: Una factura por contrato por mes. No duplicar.
2. **Administración**: Calculada desde `contrato.administracion` (puede variar por plan y modalidad)
3. **Anticipo**: Se aplica automáticamente si `anticipo.saldo > 0` al generar la factura
4. **Mora**: Calculada por `MoraClienteService` — depende de días de atraso y config del aliado
5. **Distribución**: `dist_encargado` + `admon_asesor` debe ser ≤ `administracion`
6. **Tiempo Parcial**: El IBC se calcula proporcional a `dias_tiempo_parcial / 30`

## Patrones de Query Frecuentes

```php
// Facturas de un aliado por mes
Factura::where('aliado_id', $alidoId)
    ->where('mes', $mes)
    ->where('anio', $anio)
    ->with(['contrato.cliente', 'contrato.razonSocial'])
    ->get();

// Factura única por contrato/mes
Factura::where('contrato_id', $contratoId)
    ->where('mes', $mes)
    ->where('anio', $anio)
    ->first();

// Suma de deuda pendiente
Factura::where('aliado_id', $alidoId)
    ->where('estado', 'pendiente')
    ->sum(DB::raw('administracion + mora - anticipo_aplicado'));
```

## Vistas del Módulo

```
resources/views/admin/facturacion/
├── index.blade.php          ← Lista de facturas por empresa/mes
├── empresa.blade.php        ← Detalle de empresa con sus facturas
├── show.blade.php           ← Detalle individual de factura
└── partials/                ← Componentes reutilizables
```

## Rutas Principales

```
GET  /admin/facturacion                           → index
GET  /admin/facturacion/empresa/{empresa}         → empresa (con ?mes=&anio=)
POST /admin/facturacion/generar                   → generar facturas del mes
POST /admin/facturacion/{factura}/marcar-pagada   → cambiar estado
```
