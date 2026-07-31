---
name: incapacidades-brynex
description: >
  Gestión de incapacidades médicas en Brynex: registro, gestiones EPS/ARL, abonos,
  radicados y estados. Actívate cuando el usuario mencione: incapacidad, incapacidades,
  gestión médica, abono incapacidad, radicado EPS, radicado ARL, IncapacidadController,
  GestionIncapacidad, AbonoIncapacidad, días de incapacidad, reconocimiento EPS.
---

# Skill: Incapacidades Brynex

## Arquitectura del Módulo

```
IncapacidadController.php (~61KB)   ← Controlador principal

Modelos:
├── Incapacidad.php          ← Entidad central (tabla: incapacidades)
├── GestionIncapacidad.php   ← Gestiones/seguimientos de la incapacidad
├── AbonoIncapacidad.php     ← Pagos recibidos de EPS/ARL
└── Radicado.php             ← Radicados relacionados (documentos)
```

## Estructura de `Incapacidad`

Columnas principales:
| Columna | Descripción |
|---|---|
| `aliado_id` | Aliado propietario |
| `contrato_id` | FK al contrato del cotizante |
| `fecha_inicio` / `fecha_fin` | Rango de la incapacidad |
| `dias_incapacidad` | Total de días |
| `tipo` | `eps`, `arl`, `laboral` |
| `diagnostico` | Código CIE-10 o descripción |
| `estado` | `activa`, `cerrada`, `gestionada` |
| `valor_reconocido_eps` | Valor que paga la EPS |
| `valor_pagado` | Valor efectivamente recibido |
| `deleted_at` | Soft delete |

## Tipos de Abono (AbonoIncapacidad)

```php
// Tipos de abono:
// - 'entrada_incapacidad': pago recibido de EPS/ARL
// - 'pago_cotizante': anticipo/pago del cotizante
// - 'ajuste': ajuste manual

// IMPORTANTE: el tipo 'pago_eps' fue renombrado a 'entrada_incapacidad'
// en la migración: 2026_06_06_144626_update_abonos_incapacidades_tipo_pago_eps
```

## Flujo de Gestión de Incapacidad

1. **Registro**: Crear incapacidad con fechas y tipo
2. **Radicado**: Generar radicado de documentos ante EPS/ARL
3. **Gestión**: Registrar seguimientos (`GestionIncapacidad`) con fechas y observaciones
4. **Abono**: Registrar pagos recibidos (`AbonoIncapacidad`)
5. **Cierre**: Marcar como cerrada cuando se recibe el pago completo

## Vistas del Módulo

```
resources/views/admin/incapacidades/
├── index.blade.php           ← Lista con filtros de estado
├── show.blade.php            ← Detalle con gestiones y abonos
└── partials/
    ├── form-gestion.blade.php
    └── form-abono.blade.php

resources/views/incapacidades/    ← Vista para cotizantes (auto-gestión)
```

## Queries Frecuentes

```php
// Incapacidades activas con saldo pendiente
Incapacidad::where('aliado_id', $alidoId)
    ->where('estado', 'activa')
    ->withSum('abonos', 'valor')
    ->get();

// Con índices de performance (migración: add_performance_indexes_to_incapacidades)
// Índices en: aliado_id, contrato_id, estado, fecha_inicio
```

## Integración con Radicados

Las incapacidades pueden generar radicados de documentos:
- `radicados.incapacidad_id` → FK hacia la incapacidad
- Estados del radicado: `pendiente`, `radicado`, `en_proceso`, `resuelto`
