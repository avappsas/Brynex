# Auditoría de calidad — Brynex

**Fecha:** 31 de julio de 2026
**Alcance:** estabilidad, cobertura de tests, consistencia visual y UX del código de producción. Continúa `docs/auditoria-seguridad.md` (Fase 1); esta es la Fase 2.
**Estado:** informe de hallazgos, verificados en el código actual. **No se ha modificado ningún archivo.**

Prioridad = impacto del módulo en el negocio (dinero, compliance) × qué tan seguido se usa.

---

## Resumen

| Sección | Hallazgos |
|---|---|
| Estabilidad | 6 |
| Tests | 1 (cobertura casi nula) + nota de diseño de los servicios de cálculo |
| Consistencia visual | 4 |
| UX / profesionalismo | 3 |

---

# ESTABILIDAD

## E-1 — Dos escrituras de dinero sin transacción en Incapacidades

**Archivo:** [IncapacidadController.php:832-884](app/Http/Controllers/Admin/IncapacidadController.php:832) — `storeAbono()`

```php
if ($request->tipo === 'entrada_incapacidad' && $request->banco_cuenta_id) {
    $cons = DB::table('consignaciones')->insertGetId([...]);   // (1) entrada bancaria
    $consignacionId = $cons;
}
...
\App\Models\AbonoIncapacidad::create([...'consignacion_id' => $consignacionId...]);  // (2) el abono
```

Si (2) falla después de que (1) tuvo éxito (validación, timeout, lo que sea), queda una fila
en `consignaciones` — dinero registrado como entrado al banco — sin ningún `AbonoIncapacidad`
que la respalde. Es un fantasma contable: aparece en el saldo del banco pero no en el
historial de la incapacidad.

**Impacto:** alto (dinero), pero de baja frecuencia (solo si falla la segunda escritura).
**Esfuerzo:** S — envolver ambas escrituras en `DB::transaction(function () { ... })`.
**Riesgo de aplicar el fix:** bajo, es aditivo.

## E-2 — N+1 real: configuración del aliado consultada dentro de un loop, con el mismo valor cada vez

**Archivo:** [CobrosController.php:1544-1553](app/Http/Controllers/Admin/CobrosController.php:1544) y su gemelo en
[CobrosController.php:2482-2491](app/Http/Controllers/Admin/CobrosController.php:2482)

```php
foreach ($contratosValidosPreview as $c) {         // hasta 30 iteraciones
    ...
    $cfgAliado = DB::table('configuracion_aliado')  // MISMO query cada vez: no depende de $c
        ->where('aliado_id', $aliadoId)
        ->whereNull('plan_id')
        ->first(['mora_dia_habil_inicio']);
    ...
}
```

El valor no cambia entre iteraciones — depende solo de `$aliadoId`, constante en todo el
loop. Hasta 30 queries idénticas por request (dos veces, una por cada método gemelo:
preview individual y preview por empresa).

**Impacto:** medio (lentitud en la vista previa de envío masivo de WhatsApp, no dinero).
**Esfuerzo:** S — mover la consulta antes del `foreach`, una sola vez.

## E-3 — N+1 real: validación de orden de facturación con `find()` por cada contrato seleccionado

**Archivo:** [FacturacionController.php:755-756](app/Http/Controllers/Admin/FacturacionController.php:755)

```php
foreach ($validated['contratos'] as $cId) {
    $cChk = Contrato::where('aliado_id', $aliadoId)->find($cId);   // 1 query por contrato
    ...
}
```

El propio comentario del código reconoce el costo ("en masivo se omite la validación por
desempeño"), pero la ruta individual (selección múltiple manual) sigue pagando 1 query por
contrato marcado para facturar.

**Impacto:** medio (facturación es de los flujos más usados; con 50+ contratos seleccionados
se nota la demora).
**Esfuerzo:** S — `Contrato::where('aliado_id', $aliadoId)->whereIn('id', $validated['contratos'])->get()->keyBy('id')` antes del loop.

## E-4 — Módulo de Finanzas personal: casts `float` sobre columnas `decimal(18,2)`

**Archivos:** 20 campos monetarios en `app/Models/Finanzas/*` — `Prestamo` (`monto_original`,
`tasa_interes_mensual`, `saldo_actual`), `Cuenta` (`saldo_inicial`), `Gasto`, `Inversion`,
`Patrimonio`, `PrestamoMovimiento`, `BrynexPago`, `AppLiderPago`, etc. También
[CobrosAdicionalEmpresa.php:21](app/Models/CobrosAdicionalEmpresa.php:21) en el negocio principal.

Las columnas en BD son correctas (`$table->decimal('monto_original', 18, 2)`), pero el cast
de Eloquent las convierte a `float` de PHP en cada lectura/escritura. Los modelos del
negocio principal (`Contrato`, `Factura`) sí usan `'decimal:2'` — el patrón correcto ya
existe en el proyecto, solo no se aplicó en Finanzas.

**Por qué importa:** `Prestamo::saldo_actual` se actualiza en cada liquidación de interés
(`PrestamoLiquidacionService`, corrida periódica). Con `float`, errores de redondeo de
punto flotante se acumulan operación tras operación a lo largo de meses de intereses
compuestos — el tipo de bug que no se nota hasta que el saldo final no cuadra con lo
calculado a mano.

**Impacto:** medio-alto (dinero real, del dueño; se acumula con el tiempo).
**Esfuerzo:** M — cambiar los 20 casts a `'decimal:2'` y verificar que el código que
consume esos campos no dependa de que sean `float` nativo (Eloquent con cast `decimal`
devuelve string, no float — revisar comparaciones y operaciones aritméticas en
`PrestamoLiquidacionService`, `FinanzasDashboardController` y las vistas de Finanzas).

## E-5 — Controladores de más de 1000 líneas

| Controlador | Líneas | Métodos |
|---|---|---|
| `FacturacionController` | 4220 | 39 |
| `InformeController` | 3414 | 33 |
| `CobrosController` | 2726 | 18 |
| `ContratoController` | 1509 | — |
| `IncapacidadController` | 1415 | — |
| `PlanoPagoController` | 1468 | — |
| `CuadreDiarioController` | 1014 | — |

No es un bug, es deuda técnica: cualquier cambio en `FacturacionController` obliga a
navegar un archivo de 210 KB. Los candidatos más claros a extraer a `Services` (por tener
métodos privados de cálculo puro mezclados con métodos de request/response):
`_crearParAfilPlanilla`, `_liquidarCartera`, `_nPlanoParaRS` en `FacturacionController`
— toda la lógica de generación de planilla dentro de la facturación podría vivir en un
servicio propio, dejando el controlador con solo las acciones HTTP.

**Impacto:** bajo directo, alto acumulado (cada feature nueva en estos módulos es más
lenta y más riesgosa de lo que debería).
**Esfuerzo:** L — refactor, no se debe hacer de una sola vez ni sin tests de por medio
(ver sección TESTS).

## E-6 — `_liquidarCartera`: fallo silencioso documentado, sin aviso al usuario

**Archivo:** [FacturacionController.php:1924-1932](app/Http/Controllers/Admin/FacturacionController.php:1924)

```php
} catch (\Throwable $e) {
    // Si falla el cierre del préstamo, loguear pero NO revertir la factura ya creada.
    \Log::warning("[_liquidarCartera] No se pudo liquidar la cartera: " . $e->getMessage(), [...]);
}
```

Es una decisión de diseño explícita y razonable (no revertir una factura ya facturada
porque falló un paso secundario), pero el usuario que generó la factura no se entera de
que el préstamo asociado quedó sin liquidar — solo queda en el log. Sugerencia: que la
respuesta de la acción incluya un aviso ("factura creada; no se pudo liquidar la cartera
asociada, revisar manualmente") en vez de que el único rastro sea `storage/logs`.

**Impacto:** bajo-medio. **Esfuerzo:** S.

---

# TESTS

## T-1 — Cobertura real: dos tests de ejemplo, cero tests de negocio

`tests/Unit/ExampleTest.php` y `tests/Feature/ExampleTest.php` son el scaffold por
defecto de Laravel, sin modificar. Ningún cálculo de dinero, mora, PILA o comisión tiene
un test.

**Antes de escribir cualquier test:** descomentar en `phpunit.xml`:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```
Están comentados hoy, así que `php artisan test` corre contra la base de datos real de
producción (ver `CLAUDE.md`). Es la razón más probable de que nadie haya escrito tests
de integración: hacerlo hoy escribiría en la BD real.

### Nota de diseño encontrada al evaluar qué es testeable

`MoraClienteService` cachea configuración por aliado en propiedades **estáticas**
(`self::$cacheConfigAliado`, `self::$cacheFestivos`, `self::$cacheDiaHabil` —
[MoraClienteService.php:151-153](app/Services/MoraClienteService.php:151)). Dentro de un
mismo proceso PHP (el mismo run de PHPUnit), el caché de un test se filtra al siguiente si
ambos usan el mismo `aliado_id`. No hay método para resetearlo desde fuera. Cualquier test
que use este servicio debe tenerlo en cuenta (usar `aliado_id` distintos por test, o
exponer un `reset()` para tests).

### Los 10 tests de mayor valor, verificados contra el código real

1. **`MoraClienteService::aplicarTramos`** — pura, sin BD. Los tramos de mora determinan
   cuánto se le cobra de más a un cliente atrasado; un error aquí es dinero mal cobrado
   en cada factura con mora.
2. **`MoraClienteService::festivosColombia` + `getNthDiaHabil`** — puras. De esto depende
   `diaHabilVencimiento`, que decide cuándo empieza a correr la mora. Un festivo mal
   calculado corre la fecha de vencimiento de TODOS los clientes de un aliado ese mes.
3. **`PilaCotizanteCalculator::roundPila`** — pura. Regla de redondeo PILA (múltiplo de
   100 hacia arriba); si esto falla, el archivo PILA generado es rechazado por el operador
   de planilla.
4. **`PilaCotizanteCalculator::calcularSemanasTp`** — pura. Semanas cotizadas en tiempo
   parcial; afecta directamente el IBC reportado a la EPS/AFP/ARL.
5. **`PilaCotizanteCalculator::calcular`** con los 3 escenarios de `tipo_modalidad_id`
   verificados en el código: K-matriz (solo ARL, tipo cotizante 23), independiente (tipo
   cotizante 59/2), tiempo parcial (tipo cotizante 51). Necesita datos mínimos de catálogo
   (`Caja`) — Feature test, no puro.
6. **`CotizadorService::calcular`** — el valor que ve un lead en el cotizador público antes
   de convertirse en cliente. Si el cálculo está mal, se le cotiza mal a un prospecto real.
7. **IBC en tiempo parcial** (documentado en el skill `contratos-brynex`):
   `$ibcTiempoParcial = ($salario/30) * $dias; max con SMMLV proporcional` — verificar el
   piso del SMMLV, que es la parte que más se olvida al modificar esta fórmula.
8. **Cálculo de tarifa ARL** (`ArlTarifa` + `NivelesRiesgoArl` por actividad económica) —
   determina cuánto paga el aliado a la ARL; un error de clase de riesgo cambia el costo
   real, no solo el mostrado.
9. **Distribución de un pago entre EPS/ARL/AFP/CCF** al confirmar una planilla
   (`PlanoPagoController::confirmarPago`) — Feature test con BD, valida que la suma de las
   partes cuadre exactamente con el total pagado (sin perder ni sobrar centavos por
   redondeo).
10. **`IncapacidadController::storeAbono`** — test de regresión para E-1: forzar el fallo
    de la segunda escritura y confirmar que, tras el fix, no queda una `consignacion`
    huérfana (todo o nada).

**Impacto:** alto — son los cálculos donde un error cuesta dinero real o rechazo de un
archivo de compliance. **Esfuerzo:** M por test (los puros son S, los que tocan BD son M).

---

# CONSISTENCIA VISUAL

El skill `blade-alpine-brynex` documenta un sistema de diseño (variables CSS, clases
`.btn-accion`/`.btn-secundario`/`.btn-danger`, escala tipográfica) con la regla explícita
"NUNCA inventar colores, clases o estilos". La medición contra las 116 vistas de
`resources/views/admin/` dice que ese sistema casi no se usa:

## V-1 — Solo 13 de 116 vistas usan las clases de botón documentadas

Las otras 114 usan color hexadecimal inline (`style="color:#2563eb"` etc.) en vez de
`.btn-accion` / `var(--azul-btn)`. El dato interesante: **los colores sí son consistentes
entre sí** — la paleta real (`#64748b`, `#94a3b8`, `#475569`, `#0f172a`, `#334155`,
`#2563eb`, `#1d4ed8`, `#1e40af`) coincide con la escala slate/blue de Tailwind, repetida a
mano cientos de veces. No es caos visual — es un sistema de diseño informal, por
copy-paste, que nunca se centralizó en las variables CSS que el skill documenta.

**Consecuencia práctica:** cambiar un tono de azul hoy significa buscar y reemplazar en
más de 100 archivos, no editar una variable. Y un agente (o dev nuevo) que siga la regla
"usar los tokens" al pie de la letra introduce una tercera convención en vez de alinear
las que ya existen.

**Impacto:** medio (mantenibilidad, no funcionalidad). **Esfuerzo:** L si se quiere
migrar todo; S si solo se actualiza el skill para reflejar la paleta hex real como
"tokens de facto" y se exige usarla tal cual en vistas nuevas, sin migrar las existentes.

## V-2 — 81 de 116 vistas tienen su propio bloque `<style>`

CSS duplicado por vista en vez de un stylesheet compartido de componentes (tablas,
badges, modales se redefinen archivo por archivo). Coincide con las vistas más pesadas:
`contratos/form.blade.php` (3332 líneas), `planos/index.blade.php` (3105),
`informes/financiero.blade.php` (2982), `incapacidades/index.blade.php` (2781).

**Impacto:** medio (mantenibilidad). **Esfuerzo:** L.

## V-3 — Solo 20 de 116 vistas tienen algún `@media` query

El panel admin es, en la práctica, de escritorio únicamente. Las vistas más grandes y más
usadas (facturación, cobros, contratos, incapacidades) no tienen manejo responsive —
probablemente se ven mal o son inusables en tablet/móvil.

**Impacto:** depende de si el equipo usa el panel desde el celular alguna vez (verificar
con el usuario antes de priorizar). **Esfuerzo:** L si se aborda de fondo.

## V-4 — Vistas de más de 2000 líneas mezclando HTML + CSS + JS

`contratos/form.blade.php` (3332), `planos/index.blade.php` (3105),
`informes/financiero.blade.php` (2982), `incapacidades/index.blade.php` (2781),
`facturacion/empresa.blade.php` (2182), `cobros/index.blade.php` (2055),
`afiliaciones/index.blade.php` (2048). Mismo problema que E-5 pero en vistas: son
difíciles de navegar y cualquier cambio pequeño obliga a cargar mentalmente miles de
líneas de contexto.

**Impacto:** bajo directo, alto acumulado. **Esfuerzo:** L.

---

# UX / PROFESIONALISMO

## U-1 — Sin política consistente de paginación

24 de 45 controladores admin usan `->get()` en `index()` sin `paginate()`. Revisados con
cuidado — la mayoría está bien así: la consulta ya viene acotada por otro filtro
(mes/año en `ComisionesController`; y **corrección tras revisión más profunda**:
`AfiliacionController::index` también filtra por `whereMonth/whereYear('fecha_ingreso', ...)`
— solo trae las afiliaciones de un mes puntual, no "todos los contratos vigentes" como
decía una versión anterior de este hallazgo).

El único caso genuinamente sin cota de crecimiento verificado es:

- **`GestionArlController::index`** — filtra solo por `aliado_id + estado='vigente' +
  tipo_modalidad_id=15`, sin acotar por fecha. Pero **no es una corrección aislada**: el
  semáforo (criterio de orden por defecto) se calcula y se ordena **en PHP después**
  de `$query->get()` (comentario en el propio código: `// semaforo se ordena en PHP
  después`). Agregar `->paginate()` antes de ese punto paginaría la colección sin
  ordenar todavía, rompiendo el orden por defecto de la vista. El fix real requiere
  ordenar primero y paginar manualmente sobre la colección ya ordenada (o mover el
  cálculo del semáforo a SQL) — no se aplicó en esta tanda por ese motivo.

**Impacto:** bajo-medio hoy (en la práctica, los contratos ARL independiente vencen cada
28 días — `GestionArlController::DIAS_VIGENCIA` — así que la tabla probablemente no crece
sin límite en la práctica, aunque la consulta no lo garantice). **Esfuerzo:** M, no S —
requiere tocar el ordenamiento, no solo agregar `paginate()`.

## U-2 — Validación de formularios: HTML5 básica en un tercio de las vistas

Solo 34 de 116 vistas usan siquiera el atributo `required`. La validación real vive en el
backend (`$request->validate()`, verificado como consistente en la auditoría de
seguridad), así que no es un hueco de integridad de datos — es UX: el usuario se entera
del campo faltante después de enviar el formulario, no antes.

**Impacto:** bajo (cosmético/fricción). **Esfuerzo:** M si se quiere sistematizar.

## U-3 — Feedback en acciones largas: presente donde más importa, no en todos lados

Solo 26 de 116 vistas muestran spinner/estado "Procesando..."/deshabilitan el botón
durante una acción async. Se verificó el caso de mayor riesgo — el modal de facturar
(`_modal_facturar.blade.php`) — y **sí** deshabilita el botón mientras procesa
(`btn.disabled = true` / `false` alrededor del fetch). Las exportaciones a Excel/PDF
(6 vistas revisadas) en su mayoría no muestran ningún indicador mientras se genera el
archivo — para archivos grandes (plano PILA, informe financiero) el usuario no tiene forma
de saber si el clic se registró.

**Impacto:** bajo-medio (frustración/clics duplicados en generación de reportes largos).
**Esfuerzo:** S por vista.

---

# Verificado y correcto — para no re-auditar

- **`->first()` sin null-check:** revisados los controladores más grandes
  (Facturación, Cobros, Planos) — el código usa `?->` de forma consistente donde hace
  falta. Sin hallazgos.
- **Confirmación de acciones destructivas:** las funciones JS de eliminar (`eliminarClaveGlobal`,
  `ECA.eliminar`, etc.) sí llaman a `confirm()` internamente, aunque no aparezca en la
  misma línea que el `onclick`. Verificado en 2 casos representativos.
- **`PlanoPagoController::confirmarPago`** usa transacción manual (`DB::beginTransaction`/
  `commit`/`rollback`), no `DB::transaction()` — por eso una búsqueda ingenua por
  `DB::transaction` no lo encuentra. Está correctamente protegido.
- **Catch silenciosos en Jobs y webhooks** (WhatsApp, IA, backups): son 28 de los 32 casos
  encontrados. Es el patrón correcto para procesos en background — no se quiere que un
  webhook de Meta o un Job en cola tumbe el proceso completo por un fallo secundario.
  No se reportan como hallazgos individuales.
- **`asDateTime` en `BaseModel`/`HasSqlServerDates`**: el `catch` que devuelve `null` en
  fechas no parseables es intencional y razonable, no un error silenciado real.

---

# Orden sugerido

**Rápidas y aisladas (Sonnet, un commit por hallazgo):** E-1, E-2, E-3, E-6, U-1
(los 2 controladores identificados).

**Requiere más cuidado (revisar antes de tocar Finanzas real):** E-4 — confirmar con el
usuario antes de cambiar los casts, dado que toca el saldo de préstamos reales.

**Proyecto aparte, con tests de por medio primero:** E-5 y V-1/V-2/V-4 — no
refactorizar `FacturacionController` ni consolidar CSS sin antes tener los tests de la
sección TESTS corriendo, para poder confirmar que nada se rompió.

**Depende de decisión de negocio:** V-3 (¿se usa el panel desde móvil alguna vez?) y U-2
(¿vale la pena la inversión en validación de cliente dado que el backend ya valida?).
