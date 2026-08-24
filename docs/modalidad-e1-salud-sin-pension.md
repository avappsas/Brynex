# Modalidad E-1: pagar salud sin pensión

Estado: **suspendida** (ago-2026). El código existe y el paso 1 funciona, pero
el esquema no se puede completar. Este documento explica por qué, para no
volver a recorrer el camino.

## Qué se quería

Un dependiente que compra un plan **EPS + ARL** o **EPS + ARL + CCF** sin
pensión. El operador rechaza la planilla directa, así que un cliente de otro
aliado lo resolvía en dos planillas encadenadas y nos pasó su archivo para
replicarlo:

- **Paso 1** — planilla `E`, días `1/0/1/1` (afp/eps/arl/ccf): un día de
  pensión y uno de caja, salud en cero, ARL con su día pero tarifa cero.
  Marca `colombiano en el exterior = X`, novedad `VAC-LR = L` e `ING = X`.
- **Paso 2** — planilla `N` que corrige la anterior: sube **solo la salud** a
  30 días, cambiando el subtipo de cotizante de `0` a `4`.

## Qué funciona

El **paso 1 liquida y se paga** en Simple y en ARUS Enlace. Probado con pagos
reales el 24-ago-2026 (planillas `87700422` y `1084593675`).

## Qué lo bloquea

El paso 2. La corrección necesita el subtipo 4 para que la pensión quede
exenta de la regla de coherencia, y ese subtipo no se puede usar.

| Lo que se intentó | Regla del operador |
|---|---|
| Salud 30 con pensión 1, sin subtipo | `eo.val.2.244` / `2.198` — días e IBC de pensión, salud y riesgos deben coincidir |
| Invocar subtipo 3 o 4 | `eo.val.2.655` — el cotizante no puede usar ese subtipo (registro UGPP) |
| Invocar subtipo 5, 6 o 9 | `eo.val.2.506` — debe estar en el Reporte de Personas Pensionadas |
| Invocar subtipo 11 o 12 (taxista) | `eo.val.2.355` / `2.655` |
| Meter el subtipo en la corrección | `eo.val.2.746` — el cambio de subtipo no está permitido |
| Quitar la pensión en la corrección | `eo.val.2.333` / `2.488` — una corrección solo puede sumar, nunca restar |
| Marcarla como solo novedades | `eo.val.5.012` — no aplica a planillas con valores |
| Cobrar la ARL en la corrección | `eo.val.2.447` + `2.090.10` — la novedad de ausentismo obliga a tarifa cero y debe ir en las dos líneas |
| Otros tipos de planilla (`F`, `X`, `M`, `J`, `A`, `S`, `T`, `H`, `U`) | `F` y `X` derogadas (`1.009`), `M` solo pre-feb-2014 (`1.161`), `J` exige sentencia (`1.179`), el resto repite `2.066` |
| Los flags `planillaUGPP` y `tipoArchivo` | Sin efecto; `tipoArchivo` solo acepta `I` |

Probado por **API de Simple, API de ARUS y el portal web de Simple** — los tres
devuelven lo mismo.

## La conclusión

**Toda exención de pensión está detrás de un registro externo.** No hay forma
de afirmarla desde el archivo. El propio error lo dice: *"puede dirigirse a los
canales de atención dispuestos por la UGPP o al correo contactenos@ugpp.gov.co"*.

Se verificó con una de las 12 personas del archivo del cliente
(CC 1064437945): enviada bajo nuestro NIT, recibe el mismo `eo.val.2.655`. O
sea la habilitación va atada a la persona **y probablemente al aportante**, no
al archivo. El cliente no encontró un truco de formato: hizo un trámite.

## Lo que sí resuelve el caso

Para quien **está exento** —pensionado, hombre 55+, mujer 50+, requisitos
cumplidos ante la UGPP, o documento CE/PT/PP/PE/PA— la planilla **directa**
liquida de una, sin E-1 y sin corrección. Verificado el 24-ago-2026 con un
cotizante de 73 años: planillas `87718511` (ARUS) y `1084594988` (Simple), por
$150.900, con modalidad normal (Dependiente E) y plan EPS+ARL+CCF.

## Para retomarlo

1. Averiguar con el cliente **qué trámite** hizo ante la UGPP: por persona o
   por aportante, por cuál canal, cuánto tarda, si se renueva.
2. Si el trámite es replicable, el contrato necesita una marca de "habilitado
   ante UGPP" para saber a quién se le puede vender el plan.
3. Y ojo: **con la exención registrada ya no hacen falta los dos pasos** — la
   planilla directa basta.

El código vive en `app/Services/PilaCotizanteE1.php` con una sola línea de
delegación en `PilaCotizanteCalculator`. La constante
`OPERADORES_PERMITEN_CAMBIO_SUBTIPO` está vacía a propósito: si algún operador
llega a permitir el cambio de subtipo, se agrega su código PILA ahí.
