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
├── recibo.blade.php         ← Recibo: estilos + layout de la hoja
├── _recibo_cuerpo.blade.php ← Cuerpo del recibo (grupo NP e individual)
├── _recibo_desglose_empresa.blade.php ← Desglose de la copia empresa
└── partials/                ← Componentes reutilizables
```

## Recibo: doble copia por hoja

`aliados.recibo_doble_copia` (switch en Configuración → Parámetros
Especiales) hace que el recibo salga **dos veces en la misma hoja carta**:
copia CLIENTE arriba, copia EMPRESA abajo, para partirla por la mitad.

- El cuerpo del recibo se incluye dos veces desde `recibo.blade.php`, con
  `['copia' => 'cliente'|'empresa']`. Dentro del partial eso es `$esCopiaEmp`.
- La copia cliente respeta el botón "Vista detallada"; la copia empresa
  lleva `.det` fijo (siempre detallada).
- En la copia empresa se omiten la **nota legal**, el **Resumen Financiero**
  y la franja de **Forma de Pago**: todo eso lo absorbe el desglose del
  final, que tiene 4 columnas (SS · Servicios · Saldos · Forma de pago).
  Tampoco repite "Total factura", que ya sale en el bloque TOTAL A PAGAR.
- El escalado lo hace `ajustarCopias()` en JS. No usa un ancho fijo: prueba
  varios anchos de diseño (`ANCHOS_DISENO`) y elige el que da mayor escala,
  **descartando** los que hacen crecer el alto — señal de que la tabla ya
  está partiendo palabras ("INDEPENDIENT/E"). A igualdad de escala toma el
  más ancho. Con eso un recibo de pocas filas sube de ~0.66 a ~0.93.
- La geometría en pantalla y en `@media print` es la misma, así que lo que
  se ve es lo que sale. Para que eso se cumpla:
  - Las reglas de `@media print` que reacomodan el recibo (quitar márgenes,
    `font-size: .58rem` en la tabla) están acotadas a `.hoja-fondo`, o sea
    solo al modo simple. En doble copia el recibo se imprime tal cual se ve.
  - `ajustarCopias()` mide con la clase `.midiendo`, que oculta los
    `.no-print` (el link 👤 de cada fila) igual que hará la impresora.
  - `@page margin` es 6mm en doble copia y 8mm en simple (condicional en
    Blade). El modo simple depende de 199.9mm para su `scale(0.721)`.
- **No poner `id=` dentro de `_recibo_cuerpo.blade.php`**: se renderiza dos
  veces en la misma página y los ids quedarían duplicados.

### ¿A nombre de quién sale el recibo?

Lo decide **`facturas.empresa_id`**, nunca `clientes.cod_empresa` — ese
último es el canal/referido comercial del cliente y no dice a quién se le
factura. Usarlo hacía que un recibo de una sola persona saliera encabezado
con una empresa ajena.

- Con `empresa_id` → encabezado "Empresa": nombre, NIT y datos de entrega.
- Sin `empresa_id` y 1 fila (`$unaPersona`) → "Afiliado": nombre, C.C. y
  badge Dependiente/Independiente. Además:
  - En la **copia cliente** la tabla se reemplaza por `.liq-grid`: las
    entidades como etiqueta/valor en dos columnas (EPS/ARL, Pensión/Caja,
    Días) más una barra `.liq-total`. Una tabla de 6 columnas y una sola
    fila es muy ancha y baja, y obliga a escalar el recibo hacia abajo
    dejando media hoja vacía; el grid es más angosto y sube la escala.
  - En la **copia empresa** se mantiene la tabla (hacen falta los valores
    por entidad), pero **sin las columnas No / Nombre / Razón Social**.
    Ojo con el `colspan` del `tfoot`: cambia de 8 a 5.
- Sin `empresa_id` y varias filas → la razón social si todas comparten una,
  si no "N trabajadores".

El 99,6% de los lotes sin `empresa_id` son de una sola fila.

### Saldos: solo existe `saldo_proximo`

`saldo_a_favor` y `saldo_pendiente` se eliminaron de `facturas` en la
migración `2026_04_20_220000`. Queda una sola columna:

```
saldo_proximo = (valor_efectivo + valor_consignado + anticipo_aplicado) − total
    negativo → quedó PENDIENTE      positivo → quedó A FAVOR
```

El *saldo anterior acumulado* no está guardado: se calcula sumando los
`saldo_proximo` de las facturas previas (por `empresa_id` si la factura es
de empresa, si no por `cedula` con `empresa_id IS NULL`). Lo hace
`FacturacionController@recibo` y lo pasa como `$saldoAnterior`.

### Cuadre del desglose

`SS + servicios` debe dar `total`. A nivel de **lote** (`numero_factura`)
siempre cuadra; en filas sueltas puede no cuadrar porque hay registros con
`total = 0` cuyo valor quedó consolidado en otra fila del lote. Por eso el
desglose calcula la diferencia y la muestra como fila **"Ajuste"** en vez de
dejar el recibo descuadrado.

## Rutas Principales

```
GET  /admin/facturacion                           → index
GET  /admin/facturacion/empresa/{empresa}         → empresa (con ?mes=&anio=)
POST /admin/facturacion/generar                   → generar facturas del mes
POST /admin/facturacion/{factura}/marcar-pagada   → cambiar estado
```
