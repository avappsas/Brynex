# Plan de marketing automático — BRYGAR

Estado a 12 de agosto de 2026. Objetivo: que el sistema consiga clientes solo,
midiendo lo que funciona y moviendo la plata hacia ahí.

---

## 1. Qué está automatizado hoy (verificado en producción)

| Pieza del engranaje | Estado real |
|---|---|
| Generar un Reel diario | ✅ `marketing:autopilot` cada 30 min entre 05:00 y 21:00, una pieza por día desde las 09:00 |
| Pegarle el cierre de marca | ✅ `cierre_activo = SI`, se pega solo al terminar Veo |
| Publicar en Facebook + Instagram | ⚠️ **Solo tras tu aprobación** — `modo = aprobar` |
| Poner a pautar automáticamente | ❌ **No existe.** 0 campañas creadas en toda la historia, $0 gastados |
| Analizar resultados y decidir | ⚠️ Se miden (21:30) pero **nadie los lee**: el piloto no consulta métricas |
| Exponer la página web | ❌ El aliado no tiene `sitio_web`; las piezas solo enlazan a WhatsApp |

### Los números que mandan

Alcance orgánico de las últimas 10 piezas: **entre 2 y 8 personas por publicación**
en Instagram. Facebook devuelve `null` (no se está leyendo bien la métrica).

Mensajes de WhatsApp llegados con referencia de una pieza (`ref: P##`): **cero.**

Es decir: **el canal orgánico no ha traído un solo cliente.** No es un problema
creativo — 8 personas de alcance no convierten aunque la pieza sea perfecta. Por eso
lo que sigue se apoya en pauta pagada, no en publicar más bonito.

De los 438 mensajes entrantes de los últimos 30 días, prácticamente todos son de
clientes que YA existen (pagos, mora, planillas). No hay entrada de prospectos nuevos.

---

## 2. Las tres trampas que hay que resolver antes de gastar un peso

### 2.1 Un anuncio nuevo cada día es la peor forma de gastar el presupuesto

Meta necesita **~50 conversiones por semana y por conjunto de anuncios** para salir de
la fase de aprendizaje. Con 5.000 COP/día un anuncio no llega ni cerca. Si además cada
día se crea un anuncio distinto, **todos los días se reinicia el aprendizaje** y Meta
nunca optimiza: se paga precio de novato de forma permanente.

**Corrección:** presupuesto en el *conjunto*, no en la pieza. Un conjunto permanente que
acumula historial, donde las piezas entran y salen como creatividades. La pieza del día
entra como retador; si a los 4-5 días no supera a las que ya están, sale.

### 2.2 Pautar un Reel hoy publicaría una imagen fija

`MetaAdsService::crearBorrador()` sube `imagen_path` y arma un anuncio de imagen. Para
una pieza de video eso significa **pautar el póster**, tirando a la basura justamente el
formato que te da 30x más alcance.

**Corrección:** soportar `video_path` en el creativo (subir a `/advideos`, usar
`video_data` con `image_url` de miniatura).

### 2.3 El tope mensual configurado no alcanza

`pauta_config.limite_mensual_cop = 100.000`. A 15.000/día son 450.000/mes. El guardián
horario pausaría toda la pauta el día 7.

**Corrección:** subir el tope a 500.000 (lo que definiste como techo).

---

## 3. Cómo debe quedar el gasto

Regla de oro: **el presupuesto sube solo cuando hay evidencia, y baja solo cuando no la hay.**

```
Arranque              1 conjunto  ×  5.000/día
Si CPC-conversación < 8.000 COP durante 3 días  → 10.000/día
Si se sostiene 3 días más                        → 15.000/día  (techo duro)
Si sube de 12.000 COP por conversación           → vuelve a 5.000/día
Si 5 días sin una sola conversación              → se pausa y avisa
```

El techo de 15.000/día y los 500.000/mes son barreras físicas en código, no criterio del
modelo: `marketing:pauta-sync` ya sabe apagar, hay que enseñarle a subir dentro de la reja.

**Nunca se sube presupuesto más de una vez cada 3 días.** Un salto brusco reinicia el
aprendizaje igual que un anuncio nuevo.

---

## 4. Horarios, público y ciudades

Hoy la segmentación está quemada en el código: **toda Colombia, 18 a 65 años.** Eso es
tirar plata en una empresa que opera desde Cali.

### Público propuesto

| Conjunto | Quién | Por qué |
|---|---|---|
| **A — Frío local** | Cali + Valle + Palmira/Yumbo/Jamundí, 25-55 | Donde ya hay operación y reconocimiento |
| **B — Reactivación** | Custom Audience de ex-clientes (retirados por mora o temporalidad) | Ya te conocen: 33,1% vuelve, 71,6% dentro de 3 meses |
| **C — Similares** | Lookalike 1% de los que sí abrieron conversación | Solo cuando B tenga 500+ personas |

Los intereses tipo "trabajador independiente" en Meta son ruido en Colombia. Mejor
audiencia amplia + creatividad que filtre sola (el que no es independiente no escribe).

### Horarios

Publicación orgánica: **11:00 y 19:00** (almuerzo y después del trabajo). Hoy está en
09:00, que es la peor hora para este público — a las 9 están trabajando.

Pauta: sin restricción horaria los primeros 15 días. Cortar horas al principio le quita
a Meta el volumen que necesita para aprender. Después de 15 días, apagar la franja
00:00-06:00 si los datos lo confirman.

> **Ojo Ley 2300:** el límite horario aplica a *mensajes que nosotros iniciamos*
> (`VentanaContactoLey2300`). Un anuncio no es contacto iniciado: es la persona la que
> escribe. La pauta puede correr fuera de esa ventana; lo que no puede es que el sistema
> escriba primero.

---

## 5. El análisis diario (lo que falta de verdad)

Antes de generar la pieza del día, el piloto debe leer qué pasó. Hoy no lo hace.

Comando nuevo: `marketing:analisis` — corre 08:30, antes del autopilot de las 09:00.

1. Lee métricas orgánicas + resultados de pauta de los últimos 14 días.
2. Cruza contra los WhatsApp entrantes con `ref: P##` → **conversaciones reales por pieza**.
3. Ordena los ángulos (miedo al accidente, costo, trámite, familia) por conversaciones,
   no por likes. *Un like no paga una afiliación.*
4. Escribe un resumen que el generador recibe como contexto: qué repetir y qué no.
5. Mueve el presupuesto según la regla de la sección 3.
6. Te manda el resumen por WhatsApp.

**Requisito previo:** hoy llegarían 0 conversaciones atribuidas porque nadie ha escrito
desde una pieza. El `ref: P##` ya viaja en el link orgánico y en el anuncio; hay que
guardarlo en la conversación al primer mensaje, no solo dejarlo en el texto.

---

## 6. La página web

`aliados.sitio_web` está vacío y ninguna pieza la menciona.

Decisión importante: **no mandar la pauta a la web.** El anuncio Click-to-WhatsApp
convierte mucho mejor y además evita la penalización de Meta a los enlaces externos
(`wa.me` está exento; un dominio propio no).

La web sirve para otra cosa: **credibilidad**. Quien duda busca la empresa antes de
escribir. Entonces la web va en el perfil, en la firma y en las publicaciones orgánicas
— no en el anuncio.

---

## 7. La IA de WhatsApp

Estado: activa (`activo_whatsapp = 1`), Gemini 3.6 Flash. Tiene herramientas buenas
—cotizar, tabla de planes, pasar a asesor, no-contactar— pero:

**La base de conocimiento está vacía: 0 entradas.** La IA no sabe nada de BRYGAR más allá
de lo que las herramientas consultan. No conoce requisitos, tiempos de afiliación, qué
pasa si el cliente se retira, ni por qué elegir BRYGAR sobre otro.

Cómo responde hoy, textual de una conversación de esta mañana:

> `🤖 Brygar: ¡Hola, Juan! Buenas tardes. 😊`

Para un cliente que ya te conoce, pasa. **Para un lead que acabas de pagar, eso es plata
perdida:** saludó y no preguntó nada. Un lead de pauta llega tibio y se enfría en minutos.

### Lo que hay que cambiar

1. **Guion específico para leads de pauta.** Si el mensaje trae `ref: P##`, la IA sabe que
   viene de un anuncio y arranca calificando: *¿qué necesitas — EPS, ARL o las dos? ¿es
   para ti o para varias personas? ¿desde cuándo?* — y cotiza con `CotizarPlanPublicoTool`
   en el mismo turno.
2. **Llenar la base de conocimiento** con lo que preguntan de verdad: requisitos, tiempos,
   qué pasa con la mora, cómo es el retiro, diferencia entre modalidades.
3. **Cerrar, no informar.** Que termine cada respuesta pidiendo el dato que falta para
   afiliar, no con "cualquier cosa me avisas".
4. **Que la frase `te mejoramos cualquier cotización que tengas` sea del guion** cuando el
   prospecto menciona a otra empresa o dice que está caro.
5. **Pasar a humano rápido** cuando hay intención real de pagar — ahí Daniela cierra mejor
   que la IA.

---

## 8. Orden de ejecución

| # | Qué | Por qué primero |
|---|---|---|
| 1 | Atribución `ref: P##` en la conversación | Sin esto no se puede medir nada de lo demás |
| 2 | Anuncios de video + tope mensual a 500.000 | Sin esto la pauta arranca rota |
| 3 | Conjunto permanente + creatividades rotativas | Evita reiniciar el aprendizaje a diario |
| 4 | Segmentación Cali/Valle + audiencia de reactivación | Deja de gastar en todo el país |
| 5 | `marketing:analisis` a las 08:30 + ajuste de presupuesto | El circuito de aprendizaje |
| 6 | Guion de la IA para leads + base de conocimiento | Que el lead pagado no se pierda |
| 7 | Publicación automática y horario 11:00/19:00 | Cuando ya haya confianza en la calidad |

Los pasos 1 y 6 son los que más plata salvan: sin ellos, todo lo que se pague en pauta
llega a un embudo que no mide y a un bot que no cierra.
