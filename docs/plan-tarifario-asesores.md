# Plan: Tarifario por riesgo ARL + Niveles de asesores

> Especificación cerrada con el usuario (ago-2026). Ejecutar por fases, en orden.
> Cada fase es desplegable por sí sola. Leer las **Reglas transversales** antes de empezar.

## Objetivo

1. Rediseñar **Parámetros** (`/admin/configuracion/parametros`) como tarifario visual:
   por plan, con las 5 filas de riesgo ARL, configurando afiliación, retiro, otros y admon.
2. Crear **niveles de asesores** (plantillas de comisión por clientes vigentes) y la
   **matriz por asesor**, con PDF imprimible del tarifario del asesor.
3. Al **crear un contrato** con asesor, precargar `admon_asesor` y el nuevo
   `afiliacion_asesor` desde la matriz. Al facturar la afiliación, mapear a `dist_*`.
4. En la **liquidación de comisiones**, separar IR y Gestión ARL como categorías propias.

## Decisiones tomadas (no reabrir)

| Tema | Decisión |
|---|---|
| Semántica | Se guarda el costo del asesor Y el precio público. Ganancia real = precio cobrado en el contrato − costo del asesor. |
| Granularidad | Plan × modalidad × riesgo ARL (igual que `afiliacion_arl_modalidades`). |
| Nivel | Plantilla: al asignarlo se copia al asesor y luego es libre (sin piso mínimo). Asignación **manual** con sugerencia automática visible ("X contratos vigentes → Nivel N"). Conteo = contratos vigentes (sin fecha_retiro) con ese `asesor_id`. |
| Fórmula afiliación | `asesor = público − retiro − otros − aliado`. **Público, retiro y otros vienen de Parámetros** (heredados, en gris). En la celda del nivel se escribe **solo la parte del ASESOR**; el aliado queda como el resto (si sube el público, la diferencia es del aliado). |
| Retiro | Valor fijo en pesos por plan×modalidad×riesgo en Parámetros; vacío = respaldo `dist_retiro_pct` actual. Manda el del plan, nunca el asesor. |
| Otros | Valor en pesos por plan×modalidad×riesgo en Parámetros, junto al retiro. Vacío = 0. No se persiste como rubro propio: es informativo del PDF y cae en `dist_admon`. |
| Admon mensual | Total al cliente por plan×modalidad×riesgo en Parámetros (respaldo: `administracion` del plan). **`admon_asesor` se pide UNA vez por nivel** (igual en todos los planes), ajustable después en el asesor individual (se copia a `comision_admon_valor` tipo `fijo`). |
| Mapeo factura afiliación | `asesor → dist_asesor`, `retiro → dist_retiro`, `otros → dist_admon`, `aliado → dist_utilidad`. ⚠️ Es un mapeo invertido respecto al significado histórico: rotular en informes (`dist_admon` = "Otros", `dist_utilidad` = "Aliado"). |
| Campo en contrato | Solo `contratos.afiliacion_asesor` (nuevo). El resto se resuelve al facturar. `admon_asesor` sigue como hoy. |
| Renovaciones IR (mod 12) y Gestión ARL (mod 15) | Se detectan **al facturar**: cualquier factura de afiliación pagada previa (`pagada/abono/prestamo`, no anulada) del mismo `cedula` + misma modalidad + aliado ⇒ renovación ⇒ `dist_asesor = contrato->admon_asesor` (no `afiliacion_asesor`). Detección al facturar (no al crear) porque Gestión ARL renueva sobre el MISMO contrato cada año. En rotación IR el retiro va con 1 día y sin admon ⇒ no hay doble pago. |
| Prioridad facturación planillas | Las planillas siguen tomando `contratos.admon_asesor` tal cual (congelado en el contrato). El nivel solo alimenta la **creación/edición** del contrato. |
| Contratos existentes | No se tocan. `afiliacion_asesor = NULL` ⇒ camino actual (`ConfiguracionAliado::calcularDistribucion`). Al **editar** un contrato y cambiar plan/modalidad/riesgo/asesor, sí se recalcula desde la matriz (como hoy hace la admon). |
| Cascada de respaldo | celda del asesor → celda del nivel → comisión plana actual (`comision_afil_*`) → config del plan. Los ~70 asesores actuales siguen igual hasta tener nivel. |
| Cotizador público / WhatsApp / marketing | **NO cambian.** Siguen con la admon genérica del aliado (`CotizacionPublicaService:677`). La columna admon por celda no los alimenta. |
| Recibos / cuentas de cobro / cuadre | **NO cambian** ni un peso. |
| Liquidación | 4 categorías **solo visuales** por `tipo_modalidad_id` del contrato: Afiliaciones, Mensualidades (planillas), IR (mod 12 completa, primeras incluidas), Gestión ARL (mod 15 completa). Totales e histórico intactos. |
| Descuadre | Si `retiro + otros + asesor > público`: avisar al guardar Parámetros (listar celdas de niveles descuadradas); al facturar, la parte del aliado se ajusta con mínimo 0 y se avisa. Nunca bloquear. |
| Ayuda de llenado | Replicar riesgo 1 → riesgos 2-5 + botón "ajustar por delta ARL" (suma la diferencia de prima ARL por nivel, tarifas del aliado, redondeo a miles). Admon se replica igual en los 5. Copiar plan→plan y duplicar nivel→nivel. |
| Nueva modalidad en un plan | Asistente al activarla (mini-form de tarifas de esa combinación) + badge de "pendiente por tarifar" en Parámetros y niveles. |
| Permisos | Reusar `asesores.gestionar` (editar) / `asesores.ver` (mirar). Niveles viven en tarjeta nueva del hub de Configuración. |
| PDF | Solo lo genera el admin desde el asesor. Desglose completo: público, retiro, otros, aliado, **gana asesor**, admon (aliado/asesor) y total mes estimado con SS a salario mínimo (marcado "estimado"). |
| Fuera de alcance | Que IR no cobre siempre afiliación (rediseño aparte, otro trabajo). |

## Trampas encontradas al ejecutar la Fase 0 (¡leer antes de la Fase 1!)

1. **La tabla se llama `afiliacion_arl_modalidad`, en singular.** No `..._modalidades`.
2. **La admon NO se cascadea por plan.** Las filas por plan de `configuracion_aliado` traen
   `administracion = 0` en los datos reales; el valor vive solo en la fila GLOBAL (46.000 en el
   aliado 2). Es lo que ya documenta `CotizacionPublicaService::cotizar()`: *"administracion /
   admon_asesor / seguro SIEMPRE de la config genérica, nunca de la fila por plan"*.
   `TarifaAsesorService::resolverAdmonTotal()` ya lo hace bien — **usarlo, no releer la columna**.
   El costo de afiliación es al revés: ahí la fila por plan sí manda.
3. **Vigente = `estado = 'vigente'`**, no `fecha_retiro IS NULL` (hay retirados con la fecha nula).
4. `costo_afiliacion` de la celda quedó **nullable**: una celda puede existir solo para fijar
   admon/retiro/otros. Al guardar en Parámetros, borrar la fila solo si los CUATRO valores
   vienen vacíos (`AfiliacionArlModalidad::estaVacia()`), no si viene vacío el precio.
5. Ya existen y no hay que reescribirlos: `Asesor::aplicarNivel()` (copia la plantilla + la admon)
   y `TarifaAsesorService::distribucionFactura()` (la Fase 5 solo tiene que enchufarlo).

## Reglas transversales (leer SIEMPRE)

- **La BD local ES producción.** Migraciones incrementales únicamente (skill `laravel-migracion`). Jamás `migrate:fresh/reset`. No correr `php artisan test` sin descomentar el override SQLite en `phpunit.xml`.
- **sqlsrv devuelve enteros como string** → castear `(int)` antes de comparar ids.
- **Multi-tenant sin scope automático** → toda query filtra por `session('aliado_id_activo')`.
- **Latencia ~250ms/consulta** → cargar matrices con pocas queries (`get()->keyBy(...)`), nunca N+1 por celda.
- Deploy manual: commit + push + `git pull` en el servidor. Desplegar fase por fase, en orden.
- Formato de dinero en vistas: patrón `input-miles` existente (puntos de miles, limpiar al enviar).

## Modelo de datos (Fase 0)

```text
afiliacion_arl_modalidades   (+3 columnas, nullable)
  administracion  DECIMAL(10,2) NULL   -- admon mensual total al cliente para la celda
  retiro          DECIMAL(10,2) NULL   -- respaldo: dist_retiro_pct × costo_afiliacion
  otros           DECIMAL(10,2) NULL   -- respaldo: 0

asesor_niveles               (nueva)
  id, aliado_id, nombre, orden, contratos_min, contratos_max,
  admon_asesor DECIMAL(10,2), activo, timestamps

asesor_nivel_tarifas         (nueva)
  id, asesor_nivel_id, plan_id, tipo_modalidad_id, nivel_arl,
  afil_asesor DECIMAL(10,2), timestamps
  UNIQUE (asesor_nivel_id, plan_id, tipo_modalidad_id, nivel_arl)

asesor_tarifas               (nueva — copia editable del asesor)
  id, aliado_id, asesor_id, plan_id, tipo_modalidad_id, nivel_arl,
  afil_asesor DECIMAL(10,2), timestamps
  UNIQUE (asesor_id, plan_id, tipo_modalidad_id, nivel_arl)

asesores    + nivel_id BIGINT NULL (FK asesor_niveles)
contratos   + afiliacion_asesor DECIMAL(10,2) NULL   -- NULL = contrato viejo, lógica actual
```

`tipo_modalidad.id` NO es IDENTITY y puede ser negativo (-1, -6…): columna entera con signo, sin FK forzada si da problemas con SQL Server.

## Servicio central: `App\Services\TarifaAsesorService`

- `resolverAfiliacionAsesor(aliadoId, asesorId, planId, modalidadId, nivelArl): ?float` — cascada completa.
- `resolverAdmonAsesor(asesor): float` — `comision_admon_valor` (fijo) del asesor; sin asesor ⇒ 0.
- `resolverAdmonTotal(aliadoId, planId, modalidadId, nivelArl): float` — celda → config plan → config global.
- `resolverRetiro(...)`, `resolverOtros(...)` — celda → respaldo (% / 0).
- `esRenovacion(aliadoId, cedula, modalidadId): bool` — solo para mod 12/15; factura afiliación pagada previa, excluye anuladas.
- `sugerirNivel(asesor): ?AsesorNivel` — count contratos vigentes vs rangos.
- `celdasDescuadradas(aliadoId): array` — para el aviso al guardar Parámetros.

## Fases

### Fase 0 — BD, modelos y servicio ✅ HECHA (ago-2026)
Migrada en producción y verificada. Entregado:
- 5 migraciones (`2026_08_07_0900*`), 165 filas de tarifas conservadas, 0 contratos afectados.
- Modelos `AsesorNivel`, `AsesorNivelTarifa`, `AsesorTarifa`; ampliados `AfiliacionArlModalidad`,
  `Asesor` (+`nivel()`, `tarifas()`, `contratosVigentes()`, `aplicarNivel()`), `Contrato`.
- `App\Services\TarifaAsesorService` completo: `resolverPrecioPublico/AdmonTotal/Retiro/Otros`,
  `resolverAfiliacionAsesor` (cascada con `origen`), `resolverAdmonAsesor`, `desglose`,
  `esRenovacion`, `sugerirNivel`, `celdasDescuadradas`, `distribucionFactura`, `combinaciones`
  (45 combinaciones plan×modalidad = 193 celdas).
- Verificado end-to-end en transacción con rollback: aplicar nivel copia matriz + admon;
  desglose cuadra (120.000 = 80.000 asesor + 40.000 aliado; 46.000 = 6.000 + 40.000);
  celda sin plantilla cae a comisión plana; cotización pública idéntica (total 554.300).

### Fase 1 — Parámetros: tarifario visual ✅ HECHA (ago-2026)
Entregado y verificado:
- `ConfiguracionAliadoController`: `construirTarifario()` (respaldos desde `$configs`, sin un
  `paraAliado()` por plan) y guardado de los 4 campos por celda; la fila solo se borra si los
  CUATRO quedan vacíos. Se bota el caché de SS al guardar.
- Vista: sección "Valores generales" + tarjeta plegable por plan → selector de modalidad →
  filas de riesgo con Afiliación · Retiro · Otros · Admon · Seg. social · **Total mes** (vivo en JS).
  Badge "N sin tarifar" por plan y aviso de celdas descuadradas.
- Ayudas de llenado en JS: `replicarRiesgos`, `ajustarPorArl` (usa el delta real de SS entre
  riesgos, redondeado a miles, con confirmación), `copiarDePlan`, `recalcTotal`.
- `TarifaAsesorService::gridSeguridadSocial()` cacheada 12h (llave incluye el salario mínimo,
  así en enero se recalcula sola) + `CotizadorService::precargarCatalogo()`.
- **Corregido de paso:** `abrirModalPromocion()` sacaba el nombre del plan con
  `btn.closest('tr')`; fuera de la tabla reventaba. Ahora viaja en `data-nombre`.

Verificación: render OK en aliado con y sin celdas (773 casillas, 11 tarjetas, ~3.5s);
**reenviar el formulario sin cambios deja la BD byte por byte igual** (1.021 valores de
tarifario); guardar solo la admon de una celda ya no borra la fila; cotizador idéntico en las
193 combinaciones con y sin memoización (total 554.300).

#### Ajustes pedidos tras revisar la pantalla (ago-2026)
1. **«% Admon afil» fuera de la pantalla** (global y por plan). ⚠️ El valor NO se borra: viaja en
   hidden, porque `dist_admon_pct` lo sigue usando `ConfiguracionAliado::calcularDistribucion()`
   para los contratos sin tarifario. Quitar el input sin el hidden lo pondría en 0 y cambiaría
   la distribución de todo lo que se facture.
2. **Encargado solo en Valores generales.** El de cada plan también quedó en hidden, por lo
   mismo. Verificado con el aliado 7 (encargado 64) que sobrevive al guardado.
3. **Tarifario invertido: modalidad → plan → riesgo.** Antes era plan → modalidad. Se agrupa por
   modalidad porque es como se vende. Lo que era por plan (afiliación base + promoción) se
   movió a una sección compacta propia, «Afiliación base por plan».

#### Segunda ronda de ajustes (ago-2026)
4. **Ingreso-Retiro solo con EPS+ARL+AFP.** Migración `2026_08_07_150000_limitar_ingreso_retiro_a_eps_arl_afp`:
   borra las relaciones de la modalidad 12 con los planes 3, 4 y 6 en `modalidad_planes`. Afecta
   al formulario de contrato y al cotizador, no solo al tarifario. Los 28 contratos que ya
   existen en esos planes **no se tocan**.
5. **Independientes Mes Actual (11) hereda de Independientes (10).** `TarifaAsesorService::MODALIDADES_ALIAS`
   + `modalidadTarifa()`: la 11 se excluye de `combinaciones()` (no se tarifa aparte) y sus
   lecturas se redirigen a las celdas de la 10. **Solo afecta al precio**: la facturación sigue
   distinguiéndolas (la 11 cobra el primer mes proporcional, ver `CobroContratoService`).
6. **Card «Afiliación base por plan» eliminada.** No hacen falta hidden: `store()` itera sobre
   `configs` recibidos, así que un plan ausente simplemente no se actualiza. ⚠️ Efecto lateral:
   el `costo_afiliacion` por plan ya no es editable en ninguna pantalla (sigue vivo como
   respaldo y se muestra en cada plan como «Afiliación general»). La promoción por plan también
   dejó de ser editable — hoy ningún aliado tiene ninguna configurada.
7. **Tiempo Parcial en tarjeta propia** (8 variantes) y **selector de plan rediseñado**:
   tarjetas `.btn-plan` con nombre, nº de riesgos y avance de llenado (`3/5`, `✓ 5/5`).
8. **Modalidades inactivas fuera del tarifario** (`activo = true` en `combinaciones()` y en
   `construirTarifario()`): «TipoE- (1 dia pension)» tenía relaciones pero no se vende.

Tras estos ajustes el tarifario pasó de **45 combinaciones / 193 celdas** a
**33 combinaciones / 145 celdas**.

Estructura final de la pantalla: Globales BryNex · Tarifas ARL · Mora · Parámetros especiales ·
**Valores generales** · **Tarifario por modalidad** · **Tiempo Parcial**.

#### Tercera ronda: la matriz de nivel/asesor también por modalidad (ago-2026)
9. `TarifaAsesorService::armarMatriz()` pasó a devolver **modalidad → planes → riesgos** (antes
   plan → modalidades). Nuevo partial `niveles/_modalidad.blade.php`, tarjeta aparte de Tiempo
   Parcial y los mismos botones `.btn-plan`. El resumen de la cabecera es ahora por modalidad
   (`data-resumen-mod`) y cada botón de plan lleva su contador (`data-avance-plan`).
   Lo heredan las dos pantallas: matriz del nivel y matriz del asesor.

⚠️ **Bug encontrado al invertir la matriz:** el PDF del tarifario y el `array_filter` de
`AsesorController::tarifarioPdf()` seguían leyendo `$datos['plan']` y `$datos['modalidades']`.
PHP 8 lo degrada a *warning* en vez de error, así que **el PDF se generaba «bien» pero vacío**
(«Este asesor todavía no tiene tarifas configuradas»). Corregido y verificado con contenido
real: 3 modalidades, 55 filas, 47.000 = 22.000 entrega + 25.000 asesor.
Lección: al cambiar la forma de `armarMatriz()`, revisar SIEMPRE los tres consumidores —
matriz de nivel, matriz de asesor y PDF.

#### Cuarta ronda: tarjeta «Solo ARL» y tarjetas con opciones (ago-2026)
10. Las tres modalidades del plan Solo ARL (**Estudiante K** −1, **ARL Tipo Y** 8 y
    **Gestión ARL** 15) se agrupan en una sola tarjeta llamada «Solo ARL», y cada una aparece
    dentro **como si fuera un plan**. Cada una conserva sus propias casillas: no comparten
    precio, porque Gestión ARL es otro producto (sin planilla mensual y con descuento).
    Es solo presentación — no cambia ids, ni nombres en BD, ni la facturación.

Para lograrlo, la estructura de la matriz y del tarifario pasó de `modalidad → planes` a
**`tarjeta → opciones`**, donde una opción es un par (modalidad, plan):
- tarjeta normal → el título es la modalidad y cada opción es uno de sus **planes**;
- tarjeta de grupo → el título es el del grupo y cada opción es una **modalidad**.

Los grupos se declaran en `TarifaAsesorService::GRUPOS_TARIFARIO` (+ `grupoDeModalidad()`).
Las claves de tarjeta son strings (`m_10`, `g_solo_arl`), y el estado Alpine pasó de
`plan: {}` a `opcion: {}` con claves `"{modalidad}_{plan}"`. Los `name=` de los inputs
**no cambiaron** (`tarifario[plan][modalidad][nivel][campo]` y `matriz[plan][modalidad][nivel]`),
así que el guardado siguió intacto.

Resultado: de 16 tarjetas a 14, con las mismas 33 combinaciones plan/modalidad.

⚠️ **Segunda vez que el PDF se rompe por esto.** Al pasar de `planes` a `opciones` volvieron a
quedar desactualizados `tarifarioPdf()` y `pdf/tarifario_asesor.blade.php`. Confirmado el
recordatorio de la ronda anterior: **cambiar la forma de `armarMatriz()` obliga a revisar los
tres consumidores** — matriz de nivel, matriz de asesor y PDF. Verificado con contenido real
tras el arreglo (3 tarjetas, 55 filas, sin el mensaje de "no tiene tarifas").

11. **Cada modalidad tarifa solo sus niveles de riesgo.** `NIVELES_ARL_POR_MODALIDAD` +
    `nivelesArlPara($plan, $modalidadId)`, usado por `combinaciones()` y por
    `construirTarifario()`:
    - **Estudiante K (−1)** → riesgos **1, 2, 3** (planilla K, riesgo bajo)
    - **ARL Tipo Y (8)** → riesgos **4, 5** (planilla Y, alto riesgo)
    - **Gestión ARL (15)** → los 5, como dice su descripción

    Los datos lo confirmaban: K solo tiene contratos en 1-3, y Y tiene 345 y 380 en 4 y 5.
    El tarifario bajó de 145 a **140 celdas**.

    ⚠️ Las celdas ya configuradas fuera de rango **no se borran ni se ocultan al resolver**:
    BRYGAR tiene 5 (K en riesgos 4-5, Y en 1-3) y `resolverPrecioPublico()` las sigue
    devolviendo. Solo dejan de pedirse en pantalla. Hay además **1 contrato anómalo**
    (id 27031, aliado 7, ARL Tipo Y en riesgo 1) — ya retirado, no afecta.

12. **La restricción también rige el formulario de contrato.** La constante viaja a la vista
    (`nivelesArlPorModalidad` → `NIVELES_ARL_POR_MODALIDAD` en JS), así que el selector y el
    tarifario no se pueden contradecir:
    - `actualizarNivelesArl()` reconstruye el `<select name=n_arl>` con los niveles de la
      modalidad y marca el borde en ámbar. **Antes solo restringía la modalidad 8 y únicamente
      si la razón social no era independiente**; ahora aplica siempre y también a la K.
    - `onActividadChange()` recorta el nivel que propone la actividad económica: sin eso, una
      actividad de riesgo 5 en Estudiante K dejaba el select con un valor inexistente.
    - Validación de servidor `ContratoController::validarNivelArl()`, porque la UI se puede
      saltar. **Solo se exige cuando el nivel cambia**: así el contrato heredado 27031 sigue
      siendo editable. Mensaje: «ARL Tipo Y» solo admite nivel de riesgo 4 o 5.

#### Valores de retiro cargados con el costo real (ago-2026)
Se verificó cuánto cuesta de verdad un retiro (1 día de planilla) contrastando el cotizador
interno con **las facturas ya liquidadas y pagadas**: coincide en 19 de 21 combinaciones con
datos. Las 2 que no (EPS+ARL+CCF N1 y N2, 3 facturas de may-jun 2026) vienen del redondeo
viejo con `round()` en vez de `ceil()` — el cotizador actual es el correcto, y así lo dice el
comentario de `CotizadorService`: *"con round() la cotización queda $100 por debajo de lo que
liquida Enlace"*.

Ejemplo confirmado con 76 facturas: **EPS + ARL(1) + AFP con 1 día = $12.300**
(EPS 2.400 + ARL 400 + AFP 9.400 + $100 de cargo sin-CCF).

Cargado en la columna `retiro` de `afiliacion_arl_modalidad`: **12 aliados activos × 25 celdas
= 300 escrituras** (50 actualizadas, 250 creadas), solo en **Dependiente E (0) e
Ingreso-Retiro (12)** — las dos modalidades donde realmente se paga un día al retirar y las
únicas con `CARGO_SIN_CCF`. Se incluyen los $100 (decisión del usuario: que cuadre con la
línea de seguridad social de la factura).

Solo se tocó la columna `retiro`: `costo_afiliacion`, `administracion` y `otros` quedaron
intactos. Ningún aliado tiene tarifas ARL propias, así que el valor es idéntico en los 12.

⚠️ **Estos valores dependen del SMMLV: hay que recalcularlos cada enero.** El script está en
el scratchpad de la sesión; conviene convertirlo en un comando Artisan
(`retiros:recalcular`) antes del próximo cambio de salario mínimo.

#### La API de ARUS no se usó
`SuaporteApiService` existe y apunta a `suaporte.com.co`, pero **hasta el método de validación
crea una planilla real** en el operador con las credenciales del aliado ("si el archivo no
tiene errores, Enlace crea la planilla y devuelve su número"). No se llamó. Las 76 facturas de
$12.300 ya son planillas liquidadas por ese mismo operador, así que la fuente es equivalente.

#### Limpieza de datos de prueba
Al reducirse las combinaciones válidas (145), los niveles y el tarifario sembrados en **BryNex**
quedaron con celdas huérfanas de modalidad 11 e Ingreso-Retiro en planes 3/4/6: se borraron
(135 de niveles + 45 de tarifario). Las **25 huérfanas de BRYGAR no se tocaron**: son
configuración real del aliado y las siguen leyendo sus contratos de IR en planes antiguos.

#### Notas para quien siga
- La pantalla pesa ~1 MB de HTML (773 casillas). Si molesta, lo siguiente sería renderizar las
  tarjetas bajo demanda, no meter más columnas.
- La admon por plan y `admon_asesor` por plan ya NO se editan en pantalla: viajan en hidden
  para no perderse. La admon real se edita por celda o en "Valores generales".
- **Regla al quitar campos de esta pantalla:** `store()` hace `$data['campo'] ?? 0` / `?: null`,
  así que un campo ausente se guarda como 0/null. Siempre dejar el hidden.

### Fase 1 (original) — Parámetros: tarifario visual
- `ConfiguracionAliadoController@index/store`: cargar/guardar `administracion`, `retiro`, `otros` por celda (mismo patrón `arl_afiliacion[plan][modalidad][nivel]`; vacío = DELETE del valor).
- `resources/views/admin/configuracion/index.blade.php`: reemplazar la tabla ancha por **tarjeta plegable por plan** → selector de modalidad → filas riesgo 1-5 con columnas Afiliación · Retiro · Otros · Admon · **Total mes estimado** (CotizadorService a salario mínimo, rótulo "estimado"). Planes sin ARL: una sola fila.
- Botones: "replicar a riesgos 2-5", "ajustar por delta ARL", "copiar plan → plan". Badge de combinaciones sin tarifar.
- Aviso de descuadre contra niveles al guardar (usa `celdasDescuadradas`; en esta fase aún no hay niveles — dejar el hook listo).
- **No tocar** secciones de mora, especiales, globales BryNex, ni el cotizador.
**Verificar:** guardar y releer celdas; cotización pública antes/después idéntica (`CotizacionPublicaService::cotizar` en tinker).

### Fase 2 — Niveles de asesores ✅ HECHA (ago-2026)
Entregado:
- `AsesorNivelController` en `admin/configuracion/niveles-asesor` (ruta aparte de `asesores/...`
  para no chocar con `Route::resource`). Autorización fina en el controlador: `asesores.ver`
  para mirar, `asesores.gestionar` para escribir. Todo resuelto con `nivelDelAliado()`, nunca
  por id suelto.
- Vistas `niveles/index` (listado + alta/edición en línea + tabla de asesores con su nivel
  sugerido), `niveles/_campos`, `niveles/matriz`.
- Matriz: **1 casilla por celda** (lo del asesor); público/retiro/otros en gris; "queda aliado"
  calculado en vivo y en rojo si el reparto se pasa del precio. Ayudas: replicar riesgo 1,
  "poner un valor en todo", "aplicar porcentaje" (redondeo a miles) y duplicar nivel.
- `TarifaAsesorService::baseTarifario()` — los heredados por celda, compartido con la Fase 3.
- Tarjeta nueva en el hub de Configuración.
- Un nivel con asesores asignados NO se puede borrar (mensaje explicativo).
- **Corregido:** `Asesor::aplicarNivel()` ahora hace `fresh(['tarifas'])`. Con una instancia
  cargada antes de editar el nivel copiaba la admon vieja al asesor, y eso solo se habría
  notado en la liquidación.

Verificación end-to-end en transacción con rollback sobre el aliado BryNex: index vacío,
crear, matriz (193 casillas, 11 planes), guardar (la celda vacía no se persiste, la llena sí),
detección de descuadre, duplicar con sus celdas, aplicar a un asesor (copia matriz + admon,
desglose con `origen=asesor`) y bloqueo de borrado con asesores asignados.

### Fase 2 (original) — Niveles de asesores (hub Configuración)
- Rutas bajo `permiso:asesores.gestionar` (ver: `asesores.ver`) + `AsesorNivelController`.
- CRUD de niveles (nombre, rango de contratos, `admon_asesor` única, orden) — no fijo a 3 niveles.
- Pantalla matriz del nivel: público/retiro/otros heredados en gris; **1 casilla por celda** (`afil_asesor`); "aliado = resto" calculado en vivo; aviso si resto < 0. Duplicar nivel.
- Tarjeta nueva en `hub.blade.php`.
**Verificar:** crear 2 niveles, duplicar, editar celdas, descuadre avisa.

### Fase 3 — Asesor: nivel, matriz propia y PDF ✅ HECHA (ago-2026)
Entregado:
- `AsesorController`: `aplicarNivel()` (acción aparte del update general, porque copiar la
  matriz pisa datos), `tarifas()` / `guardarTarifas()` (matriz propia, reusa la vista de nivel)
  y `tarifarioPdf()`.
- Ficha del asesor: bloque de nivel con estado actual, contratos vigentes, tarifas propias,
  banner de sugerencia y botones «Ver / editar tarifas» e «Imprimir tarifario». El formulario
  de aplicar nivel va FUERA del form principal (no se pueden anidar `<form>`), con opción
  «No pisar las que ya ajusté».
- `resources/views/pdf/tarifario_asesor.blade.php`: por plan y modalidad — cobra al cliente,
  entrega a la empresa, **usted gana**, admon mes y total mes estimado. Solo salen los planes
  con tarifa configurada.
- La vista `niveles/matriz` se parametrizó (`$volverUrl`, `$volverTexto`, `$admonValor`,
  `$nivelDelAsesor`, `$contexto`) y `armarMatriz()` se movió al servicio: nivel y asesor usan
  exactamente la misma pantalla, así no se desincronizan.

**Optimización obligatoria descubierta aquí (aplica a Fases 1-3):** guardar una matriz con
`updateOrCreate` celda por celda son 2 consultas × 193 celdas ≈ **96 s** contra este servidor y
moría por `max_execution_time`. Se creó `TarifaAsesorService::sincronizarCeldas()`, que hace
leer-diff-borrar-insertar en bloque, y se usa en los tres guardados (Parámetros, nivel, asesor).
`aplicarNivel()` y `duplicar()` también insertan en bloque. Medido:

| Acción | Antes | Ahora |
|---|---|---|
| Guardar matriz de 193 celdas | ~386 consultas | **18 consultas / 5,1 s** |
| Re-guardar sin cambios | igual de caro | **5 consultas / 1,4 s** |
| Aplicar nivel a un asesor | ~90 inserts | **9 consultas / 3,0 s** |
| Duplicar nivel | ~90 inserts | **5 consultas / 2,0 s** |
| Guardar tarifas del asesor | — | **2 consultas / 0,6 s** |

⚠️ **Nunca volver a escribir estas matrices con `updateOrCreate` en bucle.** Usar
`sincronizarCeldas()`.

Verificación: aplicar nivel copia 90 celdas + admon; el desglose cuadra
(55.000 = 15.000 retiro + 25.000 asesor + 15.000 aliado; admon 27.000 = 8.000 + 19.000);
ajustar una celda del asesor **no toca el nivel**; vaciarla la devuelve a la cascada
(`origen=nivel`); asesor sin nivel sigue en su comisión plana (`origen=plano`); PDF válido.
Reverificadas Fases 1 y 2 tras el refactor: idempotencia de Parámetros intacta y la regla de
celda parcial (guardar solo la admon no borra la fila) sigue funcionando.

#### Datos de prueba cargados en BryNex (aliado 1)
90 celdas de tarifario con el tarifario de referencia (planes 3, 4, 5 y 6; admon 27.000) y 3
niveles con 90 celdas cada uno: Nivel 1 (1-10, gana 20.000, admon 6.000), Nivel 2 (11-50,
25.000 / 8.000) y Nivel 3 (51+, 30.000 / 10.000). Riesgo 1 → 55.000 = 15.000 retiro + 40.000
empresa; riesgo 5 → 59.000 = 19.000 + 40.000. 0 celdas descuadradas.

### Fase 3 (original) — Asesor: nivel, matriz propia y PDF
- `AsesorController` + `form.blade.php`: selector de nivel; banner sugerencia ("N contratos vigentes → correspondería Nivel X", nunca automático). Al asignar nivel: copiar `asesor_nivel_tarifas → asesor_tarifas` y `admon_asesor → comision_admon_tipo='fijo'/comision_admon_valor`. Matriz del asesor editable (libre, sin piso).
- PDF (DomPDF, patrón de PDFs existentes del repo): tarifario completo por plan×modalidad×riesgo. Ruta GET `asesores/{asesor}/tarifario-pdf` bajo `asesores.gestionar`, con `autorizarAliado`.
- Botón "📄 Imprimir tarifario" en form/show.
**Verificar:** asignar nivel a un asesor de prueba, editar una celda, generar PDF.

### Fase 4 — Contrato: precarga ✅ HECHA (ago-2026)
Entregado:
- `ContratoController@tarifasPorPlan` ahora acepta `tipo_modalidad_id`, `n_arl`, `asesor_id` y
  `cedula`. Devuelve `administracion` (parte EMPRESA), `admon_total`, `admon_asesor`,
  `costo_afiliacion`, `afiliacion_asesor` (null sin asesor), `desglose` (retiro/otros/aliado/
  origen/descuadrada) y `es_renovacion`. **Sin `tipo_modalidad_id` responde el formato viejo**,
  así nada del formulario depende de que el tarifario esté configurado.
- Validación: `afiliacion_asesor` nullable; `''` → `null`; sin asesor → `null`.
- Formulario: hidden `afiliacion_asesor`, método Alpine `refrescarTarifas()` + `aplicarTarifas()`
  y llamadas desde **plan, modalidad, riesgo ARL y asesor** (antes solo plan). `onAsesorChange`
  delega en el servidor cuando ya hay plan; si no, conserva el reparto local de siempre.
- Aviso bajo la fila de admon con el reparto («Afiliación $55.000 = retiro $15.000 + otros $0 +
  empresa $15.000 + asesor $25.000»), el origen del valor, aviso rojo si descuadra y aviso
  ámbar de renovación en IR / Gestión ARL.

**Bug propio detectado y corregido antes de desplegar:** la primera versión forzaba
`admon_asesor = 0` cuando el contrato no tenía asesor. Hay **251 contratos (111 vigentes) con
`admon_asesor > 0` y `asesor_id` nulo** (herencia de la migración); como esa columna es una
línea de COBRO, editarlos habría bajado en silencio la factura del cliente. Ahora solo se
limpia `afiliacion_asesor`, que hoy es null en todos lados.

Verificación: sin modalidad → respuesta idéntica a la anterior; sin asesor →
`afiliacion_asesor` null; con asesor de nivel → 55.000 = 15.000 retiro + 0 otros + 15.000
empresa + 25.000 asesor y admon 27.000 = 19.000 + 8.000; riesgo 5 sube a 59.000/retiro 19.000;
asesor sin nivel cae a su comisión plana (`origen=plano`); quitar el asesor limpia el valor
huérfano; contrato guardado y releído conserva el dato y `distribucionFactura()` suma exacto.
Formulario de crear y de editar renderiza en BryNex y BRYGAR, y en contratos viejos el campo
va vacío. `es_renovacion` da true en IR y Gestión ARL con historial y false en Dependiente E.

### Fase 4 (original) — Contrato: precarga
- Ampliar `ContratoController@tarifasPorPlan` (recibe `plan_id, tipo_modalidad_id, nivel_arl, asesor_id`) → devuelve `administracion` (total − admon_asesor), `admon_asesor`, `costo_afiliacion`, `afiliacion_asesor` y flag informativo de renovación proyectada.
- `contratos/form.blade.php`: recalcular al cambiar plan/modalidad/riesgo/asesor (extender `onAsesorChange` y el fetch de tarifas actual). Guardar `afiliacion_asesor` en store/update (validación `nullable|numeric|min:0`).
- Sin asesor o asesor sin datos ⇒ comportamiento idéntico al actual (`afiliacion_asesor` NULL).
**Verificar:** crear contrato con asesor con nivel (valores de matriz), con asesor sin nivel (comisión plana), sin asesor (igual que hoy). Editar contrato viejo sin tocar tarifas ⇒ no cambia nada.

### Fase 5 — Facturación: mapeo dist_* ✅ HECHA (ago-2026)
Enchufado `TarifaAsesorService::distribucionFactura()` en los **dos** puntos de reparto de
`FacturacionController`: el de `facturar()` (~1800) y el de `_crearParAfilPlanilla()` (~2370).
El diff son **47 líneas**, sin tocar nada más del motor.

Orden de prioridad en ambos sitios, sin alterar el existente:
1. **Override manual** de la UI (`dist_asesor` y compañía) — manda como siempre.
2. **Contrato con tarifario** (`afiliacion_asesor` no nulo) → reparto congelado del contrato.
3. **Contrato anterior** (`afiliacion_asesor` NULL) → `ConfiguracionAliado::calcularDistribucion()`,
   idéntico a antes. Ese null es lo que protege a todo lo ya existente.

Verificado end-to-end facturando de verdad (en transacción con rollback), plan EPS+ARL+AFP+CCF:

| Escenario | Resultado |
|---|---|
| Contrato sin tarifario | asesor 1.111 (comisión plana) — camino viejo intacto |
| Contrato con tarifario | 55.000 = 25.000 asesor + 14.600 retiro + 0 otros + 15.400 aliado ✔ cuadra |
| Cobrando 30.000 de más | asesor igual, el aliado absorbe (utilidad 45.400) |
| Override manual | 7 / 8 / 9 / 10 respetados |
| **Primera** afiliación IR | asesor 25.000 (comisión de afiliación) |
| **Renovación** IR | asesor 8.000 (su admon) ✔ cuadra en 52.000 |

⚠️ **No pasar Pint por `FacturacionController.php`.** El archivo nunca se formateó, así que
`pint` reescribe 2.679 líneas y sepulta cualquier cambio real. Se revirtió y se reaplicaron
los dos bloques a mano respetando el estilo del archivo.

### Fase 5 (original) — Facturación: mapeo dist_*
- En los 3 puntos de distribución de `FacturacionController` (~1803, ~2360, `_crearParAfilPlanilla`): **solo si** `contrato->afiliacion_asesor !== null` **y** no hay distribución manual (`hasManual` sigue mandando):
  - renovación (mod 12/15 + `TarifaAsesorService::esRenovacion`) ⇒ `dist_asesor = (int) contrato->admon_asesor`
  - si no ⇒ `dist_asesor = (int) contrato->afiliacion_asesor`
  - `dist_retiro` = celda retiro (respaldo % actual); mod 15 mantiene la regla vigente `dist_retiro = 0`.
  - `dist_admon` = celda otros; `dist_encargado` = 0 (o manual); `dist_utilidad` = resto, `max(0, …)`.
  - `contrato->afiliacion_asesor === null` ⇒ camino actual intacto (`calcularDistribucion`).
**Verificar:** facturar en local un contrato nuevo de prueba y revisar las columnas `dist_*`; facturar un contrato viejo ⇒ distribución idéntica a antes. Recibo y cuenta de cobro sin cambios.

### Fase 6 — Liquidación: 4 categorías ✅ HECHA (ago-2026)
`ComisionesController`: constante `SQL_CATEGORIA` (por `c.tipo_modalidad_id`) + `CATEGORIAS`
con etiqueta, icono y color. Se agrega `categoria` a cada fila del detalle y `categorias` al
consolidado. **Diff: 44 líneas, solo inserciones.**

- mod **12** → *Ingreso-Retiro* · mod **15** → *Gestión ARL* · resto: `f.tipo='afiliacion'` →
  *Afiliaciones*, `planilla` → *Planillas*. Toda la modalidad junta, incluidas las primeras
  afiliaciones (decisión del usuario).
- **`total`, `pagado`, `saldo` y el corte histórico no se tocaron**: `categorias` es un campo
  aparte que solo pinta. Como se decide por la modalidad del contrato, el histórico también
  se ve separado sin recalcular nada.
- Vista `comisiones/index`: fila de 4 KPIs con las categorías + fila con Pagado / Total / Saldo.
  Badge de categoría en cada factura del detalle. La rama de **encargado** no manda
  `categorias`, y la vista cae a los 2 KPIs de siempre.
- Rótulos de `afiliaciones.blade.php`: se mantuvieron **🏢 Gastos** (= el rubro "otros") y
  **📊 Utilidad** (= lo que le queda al aliado) porque ya calzaban con el nuevo mapeo; solo se
  les añadió un `title` que explica de dónde sale cada uno.

Verificación: con 4 comisiones construidas (25.000 afiliación + 8.000 planilla + 15.000 IR +
3.000 Gestión ARL) cada una cae en su renglón y la suma da 51.000 = total. Antes, IR y Gestión
ARL se sumaban a "Afiliaciones" (43.000). Sobre datos reales de Formalizate, 6 periodos:
suma de categorías = total en los 6, saldo sin cambios.

Hallazgo: en el histórico **las afiliaciones de IR y Gestión ARL tienen `dist_asesor = 0`** —
al asesor nunca se le pagó por ellas. Es exactamente el hueco que cierra la Fase 5.

⚠️ Igual que en `FacturacionController`, **no pasar Pint por `ComisionesController.php`**:
reescribe 216 líneas y el problema de estilo que reporta es preexistente.

### Fase 6 (original) — Liquidación: 4 categorías + rótulos
- `ComisionesController` + `resources/views/admin/informes/comisiones/*`: separar por `tipo_modalidad_id` del contrato — mod 12 ⇒ "Ingreso-Retiro", mod 15 ⇒ "Gestión ARL", resto: afiliaciones (`f.tipo='afiliacion'` × `dist_asesor`) y mensualidades (`f.tipo='planilla'` × `admin_asesor`). **Solo visual**: consolidado, saldo y corte histórico intactos.
- Rotular donde aparezcan: `dist_admon` → "Otros", `dist_utilidad` → "Aliado" (informe de comisiones y pantalla de distribuir).
**Verificar:** totales por asesor idénticos antes/después para un mes cerrado (misma suma, más columnas).

### Fase 7 — Asistente de modalidad nueva + pulido ✅ HECHA (ago-2026)
`ModalidadConfigController`:
- `combinacionesSinTarifar()` → mapa `[modalidad][plan]` de combinaciones vendibles sin ninguna
  celda de tarifario en el aliado activo. La grilla las marca con 🏷️ y un tooltip.
- `guardar()` compara el estado anterior con el nuevo y, si se habilitó alguna combinación que
  no tiene precio, redirige con un aviso ámbar que las lista y enlaza a Parámetros.
  El asistente va **después de guardar** y no al hacer clic, porque esta pantalla guarda todas
  las relaciones de golpe con checkboxes, no una por una.

⚠️ **Corregido de paso: `guardar()` hacía `truncate()` sobre `modalidad_planes`** — la operación
que CLAUDE.md prohíbe — para reconstruir la tabla. Cambiado a `delete()`: mismo efecto, pero
respeta el log de transacción y el rollback en cualquier motor. Es la tabla que sostiene la
restricción de Ingreso-Retiro y el selector de planes de todo el sistema.

Verificación: guardar sin cambios deja las 42 relaciones idénticas; habilitar Estudiante K en
EPS+ARL dispara el aviso correcto; **Ingreso-Retiro sigue restringido a EPS+ARL+AFP** tras
guardar; BRYGAR marca 0 pendientes (está todo tarifado) y BryNex 24, con 8 marcas visibles.

#### Pasada final
`php -l` sobre todos los archivos modificados y `view:cache` completo sin errores. Regresiones
re-corridas al cierre: Parámetros sigue siendo idempotente, la Fase 5 reparte igual en los 4
escenarios y la Fase 6 da 0 fallos sobre datos reales.

⚠️ **Regla de estilo del proyecto:** no pasar Pint por `FacturacionController.php` (2.679 líneas
de ruido) ni `ComisionesController.php` (216). Ambos tienen problemas de estilo preexistentes;
al tocarlos, respetar el formato del archivo a mano.

### Fase 7 (original) — Asistente de modalidad nueva + pulido
- `ModalidadConfigController`: al activar una modalidad en un plan, abrir mini-form de tarifas de esa combinación (público, retiro, otros, admon). Badges "pendiente por tarifar" en Parámetros, niveles y matriz del asesor.
- Pasada final: `pint`, `php -l` de todo lo tocado, checklist manual completo.

## Qué queda congelado en el contrato (verificado ago-2026)

El tarifario **solo manda al crear el contrato**. Después, cada contrato lleva sus
propios valores y cambiar un nivel, la celda de un asesor o su admon **no toca ni un
contrato existente** — no hay ninguna escritura a `contratos` desde niveles ni desde
`Asesor::aplicarNivel()`.

| Valor | Dónde vive | Quién lo lee al facturar |
|---|---|---|
| Comisión de afiliación | `contratos.afiliacion_asesor` | `distribucionFactura()` → `dist_asesor` |
| Admon mensual del asesor | `contratos.admon_asesor` | `facturar()` y `_crearParAfilPlanilla()` → `facturas.admin_asesor` |
| Precio cobrado | `contratos.costo_afiliacion` | `facturar()` → `facturas.afiliacion` |
| Admon del aliado | `contratos.administracion` | `facturar()` → `facturas.admon` |
| Retiro y "otros" | **Parámetros, en vivo** | `distribucionFactura()` → `dist_retiro` / `dist_admon` |

La última fila es la única excepción, y es a propósito: el retiro y el "otros" se
resuelven contra Parámetros en el momento de facturar, no contra el contrato. No mueve
lo del asesor (eso ya salió congelado y se descuenta primero), solo reparte distinto
entre `dist_retiro` y `dist_utilidad`, que son los dos bolsillos del aliado. El retiro
es un costo real que se paga el día del retiro, así que corresponde el de hoy.

**Retiro en 0 ⇒ se calcula** (ago-2026). En planilla el retiro siempre se calcula; en
afiliación manda Parámetros y el cálculo queda de respaldo: si la celda no tiene retiro
y el `dist_retiro_pct` del plan es 0, `distribucionFactura()` llama a
`TarifaAsesorService::retiroCalculado()`, que es la seguridad social de UN día del propio
contrato por `calcularCotizacion(1)` — mismas entidades del plan, mismo prorrateo con
`ceil`. Así una celda sin llenar no le regala el retiro a la utilidad.

Ojo con la pantalla: la matriz de niveles y la de un asesor muestran el retiro desde
`baseTarifario()`, que no tiene contrato y por lo tanto no puede calcularlo. En una celda
sin retiro configurado la columna «QUEDA ALIADO» sale más alta de lo que será en la
factura. Los 12 aliados activos ya tienen sus 300 celdas de retiro cargadas, así que esto
solo se ve en modalidades o planes nuevos: al crearlos, llenar el retiro en Parámetros.

El informe de comisiones lee `facturas.dist_asesor` / `facturas.admin_asesor`, así que
una factura ya emitida no cambia nunca.

Para pasar un contrato viejo al tarifario nuevo hay que abrirlo y cambiarle plan,
modalidad, riesgo o asesor: solo esos cuatro disparan `refrescarTarifas()`. Abrir y
guardar sin tocar nada conserva los valores.

### El formulario del contrato (ago-2026)

La fila de tarifas quedó en cinco casillas: **Admon Mensual · Admon Asesor ·
Afiliación Empresa · Afiliación Asesor · Seguro**. Ya no se escribe el costo de
afiliación: se arma solo, con **empresa + asesor = `costo_afiliacion`**, y viaja en un
input oculto (`#inp_costo`, mismo id/name/clase de antes para no romper el cotizador ni
las vistas que lo leen). Editar cualquiera de las dos partes recompone el total.

El vacío en «Afiliación Asesor» **no es lo mismo que 0** y hay que respetarlo en toda la
cadena:

- **sin asesor** → la casilla muestra `0` y el servidor guarda `NULL` (`validar()`);
- **con asesor y con tarifario** → el número que le toca;
- **con asesor pero sin tarifario** (contrato viejo) → vacío, y así se queda hasta que
  alguien lo escriba. Poner 0 ahí lo metería al reparto nuevo con comisión cero y el
  asesor perdería su plata sin que nadie lo note.

Por eso la casilla **no** lleva la clase `.campo-money`: esa clase convierte el vacío en 0.

## Cómo se calculan los precios y los retiros (ago-2026)

Dos botones en el tarifario, **solo superadmin**, cada uno con vista previa antes de escribir.
La lógica vive en `TarifaAsesorService`; los controladores solo la escriben en bloque.

### 🧮 Calcular precios de afiliación — `proponerPrecios()`

Todo sale del **costo mensual del plan como dependiente a salario mínimo** (seguridad social
+ administración del aliado, que es lo que hace que cada aliado tenga su propia lista).

| | Regla |
|---|---|
| Plan **sin** pensión | el **85%** de su propio mes (la rebaja del 15%) |
| Plan **con** pensión | el mes de ESE MISMO plan **sin la AFP**, completo y riesgo por riesgo |

La pensión es más de la mitad de la cotización: sin esa excepción, afiliar sin pensión salía
más caro que afiliar con pensión. `EPS+ARL+AFP` cuesta 405.600 al mes pero su afiliación se
cobra como la de `EPS+ARL`: 125.400.

Después, tres guardas en este orden:

1. **piso 45.000** y **tope el mes real del plan**;
2. riesgos 2-5 de los planes sin pensión = la base por la **escalera que el aliado ya usa**
   (calcular cada riesgo contra su mes dispararía el precio: la ARL del 5 vale trece veces
   la del 1). Los planes con pensión no usan escalera — su precio ya trae la ARL de su riesgo;
3. al replicar el precio a las modalidades del plan, **ninguna celda queda por debajo de su
   propio retiro + otros**, y **ninguna celda baja** si ya vale más de lo propuesto. Lo
   segundo protege los ajustes comerciales del aliado; el efecto es que el botón nunca
   abarata nada, y para bajar un precio hay que editar la casilla a mano.

### ♻️ Recalcular retiros — `proponerRetiros()`

El retiro es lo que cuesta sacar a la persona: **un día** de seguridad social, salvo en
**tiempo parcial, donde es el bloque mínimo de 7 días** — la planilla de tiempo parcial no
admite fracciones menores, y la ARL va completa porque ahí siempre cotiza el mes entero.

> Ojo con `calcularCotizacion()`: **ignora `$dias` en la rama de tiempo parcial**. Pedirle 1
> día devuelve lo mismo que pedirle 30, y por eso el retiro de un TP14 llegó a cobrarse como
> un mes entero. `retiroCalculado()` lo resuelve clonando la modalidad con `dias_afp` y
> `dias_caja` en 7.

Solo **reemplaza si el cálculo es MAYOR** que lo guardado. El salario mínimo siempre sube, así
que cada enero actualiza los retiros del año anterior sin pisar el ajuste de un aliado que
puso un valor más alto a propósito. Un cálculo en 0 no se escribe (pasa en UPC): un 0 en la
celda significa «este plan no paga retiro» y apagaría el respaldo que lo calcula al facturar.

### Rendimiento

Las dos rutinas cotizan ~140 celdas. Hay que pasarle a cada contrato en memoria **las
relaciones `plan` y `tipoModalidad` ya cargadas**: sin eso cada cotización las pide por su
cuenta y el cálculo se va de 6 a 23 segundos (79 consultas solo a `tipo_modalidad`). Al
escribir, agrupar por valor y hacer un `UPDATE` por valor distinto — celda por celda son ~280
consultas de 250ms y la petición se pasa del `max_execution_time`.

## Checklist de regresión (correr al final de cada fase relevante)

- [ ] Cotización pública (web/WhatsApp) da el mismo valor que antes.
- [ ] Facturar contrato viejo (sin `afiliacion_asesor`) ⇒ `dist_*` idéntico a antes.
- [ ] Facturar planilla ⇒ `admin_asesor` = `contratos.admon_asesor`, sin cambios.
- [ ] Recibo, copia empresa y cuentas de cobro: mismos totales.
- [ ] Informe de comisiones de un mes pasado: mismo total por asesor.
- [ ] Contrato sin asesor: flujo intacto.
