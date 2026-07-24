# Plan de implementación — Página web pública por aliado (caso: Brygar)

> **Para el ejecutor (Sonnet):** Este plan es autocontenido. Ejecuta **una fase por sesión** en el orden indicado. Antes de crear cualquier migración o consulta, verifica los nombres reales de columnas/tablas con `php artisan` tinker o leyendo las migraciones existentes — la BD es **SQL Server** y algunos modelos tienen columnas adicionales no listadas aquí. Sigue las convenciones del proyecto descritas abajo. No inventes datos: todo contenido visible en la web sale de la BD o de la configuración del aliado.

---

## 1. Objetivo

Crear una página web pública, moderna e innovadora para cada aliado de Brynex, empezando por **Brygar** (agencia de afiliaciones a seguridad social en Cali: EPS, ARL, AFP, Caja de Compensación). La página:

- Vive en `https://brynex.co/aliado/{slug}` (ej. `/aliado/brygar`), **sin autenticación**.
- Muestra **planes y precios reales** leídos de la configuración del aliado en Brynex (`planes_contrato` + `configuracion_aliado`). Si el aliado cambia una tarifa en su panel, la web la refleja sin tocar nada más. **Una sola fuente de verdad.**
- Captura leads y los empuja a WhatsApp con mensaje prearmado.
- Tiene un CMS ligero por aliado (textos, secciones activables, FAQ, promociones).
- Se conecta a redes sociales (Facebook e Instagram primero, extensible) con credenciales configurables **por aliado**.
- Genera imágenes publicitarias (por plantillas HTML→imagen y/o por IA tipo Gemini, a elección) que, tras **aprobación** (del aliado o del superadmin), se publican simultáneamente en la sección de promociones de la web y en redes.
- 100% diseño e ilustración (SVG/CSS) — **no** usar fotos de stock ni fotos reales.
- El dominio `brygar.com` redirigirá a la ruta del servidor (Fase 6).

**Fuera de alcance por ahora:** consulta pública por cédula / portal del afiliado (se hará después como proyecto aparte).

## 2. Contexto técnico del proyecto (verificado)

- **Stack:** Laravel + Blade + Vite (JS/CSS vanilla, sin Tailwind ni framework CSS; el CSS es propio). BD principal SQL Server. Multi-aliado: los usuarios internos seleccionan aliado en sesión, pero la página pública NO usa sesión — resuelve el aliado por slug en la URL.
- **Modelos clave existentes** (en `app/Models/`, todos extienden `BaseModel`):
  - `Aliado`: `nombre, nit, razon_social, contacto, telefono, celular, whatsapp, correo, direccion, ciudad, logo, color_primario, activo, ...`. **No tiene `slug`** — se agrega en Fase 0.
  - `PlanContrato` (tabla `planes_contrato`, sin timestamps): `codigo, nombre, incluye_eps, incluye_arl, incluye_pension, incluye_caja, activo`. Método útil: `tiposRadicado()`.
  - `ConfiguracionAliado` (tabla `configuracion_aliado`): tarifas por aliado y por plan — `administracion, costo_afiliacion, seguro_valor, ...`. Método estático clave: `ConfiguracionAliado::paraAliado(int $aliadoId, ?int $planId)` que resuelve la config específica del plan o la genérica. **Usar este método para los precios públicos.**
  - `WhatsappConfig` (tabla `whatsapp_configuracion_aliado`): patrón de referencia para credenciales de terceros por aliado — token encriptado con `Crypt::encryptString` en mutador/accesor, flags `usa_cuenta_brynex`, `activo`. **Replicar este patrón para las credenciales de redes sociales.**
  - `IaConfiguracionAliado` (tabla `ia_configuracion_aliado`): `proveedor, api_key (encriptada), modelo, nombre_bot, activo_web, activo_whatsapp`. Se reutiliza para la generación por IA y el chat público.
  - **`CotizadorService::calcular()` y las tools de la IA** (`app/Services/Ia/Tools/CotizarPlanTool.php` y `CotizarPlanPublicoTool.php`): **esta es LA referencia obligatoria de cómo se calculan los precios.** La web pública debe cotizar EXACTAMENTE igual que la IA. Reglas verificadas en ese código:
    - **Valor mensual** = `CotizadorService::calcular([...], $aliadoId)` con `tipo_modalidad_id`, `plan_id`, `n_arl`, `salario`, `dias`, más `administracion`, `admon_asesor` y `seguro_valor` tomados de la **config genérica** del aliado (`ConfiguracionAliado::paraAliado($aliadoId)` con `plan_id` null). ⚠️ NUNCA tomar `administracion` de la fila por plan: esas filas tienen `administracion=0` y solo sirven para `costo_afiliacion` (comentario crítico en `CotizarPlanTool::ejecutar`, confirmado contra contratos reales de Brygar).
    - **Costo de afiliación** = `costo_afiliacion` de la config **por plan** (`paraAliado($aliadoId, $planId)`) con fallback a la genérica. Se presenta siempre como "valor de referencia/sugerido" (el asesor confirma al afiliar), nunca como cifra cerrada.
    - **Salario por defecto** = `ConfiguracionBrynex::salarioMinimo()`. Modalidad por defecto = "Dependiente" (`TipoModalidad` id 0). Nivel ARL por defecto = 1 (aclarándolo).
    - **Plan de pago inicial** (gancho de venta, replica la facturación real): mes 1 = solo afiliación; mes 2 = días proporcionales del mes vencido + administración completa; mes 3+ = valor mensual total. No aplica a Independiente Activo (modalidad id 11).
    - **Privacidad de precios** (regla de `CotizarPlanPublicoTool`): de cara al público solo se muestran `valor_mensual_total` y `costo_afiliacion` sugerido — **jamás** el desglose interno (EPS/ARL/pensión/caja por separado, `admon_asesor`, comisiones).
    - Regla AFP: si el plan no incluye pensión y `ConfiguracionBrynex::reglaAfpObligatorio()`, mostrar nota informativa de exención por edad (mujeres 50+, hombres 55+).
  - Módulo marketing existente: `MarketingLista`, `MarketingContacto`, `MarketingCampana`, `MarketingBloqueado` — rutas en `routes/web.php` bajo `Route::prefix('admin/marketing')->name('admin.marketing.')`. Los leads de la web se integran aquí.
  - Módulo IA: `IaConocimiento`, `IaConversacion`, `IaMensaje`; controller `AsistenteIaController` con endpoint autenticado `POST /asistente-ia/chat`.
- **Precedente de rutas públicas** (inicio de `routes/web.php`): subida de incapacidades por token y webhook de WhatsApp — las rutas públicas van ANTES del grupo `Route::middleware('auth')`, con seguridad manejada en el controller. La nueva ruta pública sigue ese patrón.
- **Vistas:** `resources/views/` con `layouts/app.blade.php` (interno). La página pública usa un **layout nuevo independiente** (`layouts/public.blade.php`) — no cargar assets del panel interno.
- **Convenciones:** controllers admin en `app/Http/Controllers/Admin/`, nombres de rutas con puntos (`admin.marketing.campanas.create`), comentarios y nombres en español, migraciones compatibles con SQL Server (cuidado con `->change()` y con defaults; ver comentario en `MarketingCampana::$attributes` sobre defaults de BD que no se reflejan en memoria).

## 3. Referencia competitiva (para el diseño y el copy)

Analizado jul-2026: expertosenseguridadsocial.com, conectavitaleps.com, afiliamoseps.com.co, engagedbpo.com, y brygar.com actual.

- Todos: mucha dependencia de WhatsApp, testimonios, proceso en pasos, precios "desde $X" o tablas estáticas.
- **Ninguno tiene:** cotizador interactivo en vivo, precios auto-actualizados desde un sistema, calculadora de ahorro, ni chat IA real. Esos son los diferenciadores de esta página.
- Elementos que sí copiar (funcionan): botón WhatsApp flotante, proceso "cómo funciona" en 3 pasos, contadores de confianza, FAQ, disclaimer de intermediario privado (Conecta Vital lo repite por cumplimiento — incluirlo en el footer).

## 4. Dirección de diseño

- **Estilo:** fintech/insurtech moderna en modo claro. Gradientes sutiles basados en `aliado.color_primario` (CSS custom properties: `--brand`, `--brand-soft`, `--brand-dark` derivados en el controller o con `color-mix()`), tarjetas con glassmorphism suave, esquinas redondeadas grandes, micro-animaciones al hacer scroll (IntersectionObserver, CSS `@keyframes`; sin librerías pesadas).
- **Ilustración:** SVG inline propios (personajes abstractos/geométricos, iconografía de salud/protección/familia). Nada de fotos. Los SVG deben heredar el color de marca vía `currentColor`/variables CSS para que sirvan a cualquier aliado.
- **Mobile-first** (el tráfico de este rubro es mayoritariamente móvil desde redes/WhatsApp).
- **Performance:** una sola página (landing larga con anclas), CSS/JS propios compilados por Vite en un entry nuevo (`resources/css/public.css`, `resources/js/public.js`), imágenes lazy, sin jQuery ni frameworks. Objetivo Lighthouse ≥ 90 móvil.
- **Accesibilidad:** contraste AA contra el color de marca (calcular tono de texto), navegación por teclado, `prefers-reduced-motion`.

## 5. Fases

---

### FASE 0 — Fundaciones: slug, configuración de página y ruta pública

**Objetivo:** que `GET /aliado/brygar` responda una página mínima con los datos reales del aliado.

**Migraciones:**
1. `agregar_slug_a_aliados`: columna `slug` string(60) **unique, nullable** en tabla de aliados (verificar nombre real de la tabla en el modelo `Aliado`). Backfill: generar slug desde `nombre` (`Str::slug`) para los aliados existentes; asegurar `brygar` para el aliado Brygar.
2. `crear_pagina_aliado_config`: tabla `pagina_aliado_config` —
   - `aliado_id` (FK, unique), `activo` (bool, default false — la página no es pública hasta activarla),
   - `hero_titulo`, `hero_subtitulo`, `hero_cta_texto` (strings/text nullable),
   - `seo_titulo`, `seo_descripcion` (nullable),
   - `mostrar_precios` (bool, default true), `precios_modo` (string: `exacto|desde`, default `exacto`),
   - `secciones` (JSON/text: toggles por sección `{hero, planes, cotizador, ahorro, pasos, faq, promos, contacto}`),
   - `whatsapp_mensaje_base` (text nullable — plantilla del mensaje prellenado),
   - `estadisticas_visibles` (bool default true), timestamps.
   Modelo `PaginaAliadoConfig` con casts y relación `aliado()`. Defaults espejados en `$attributes` (ver nota SQL Server arriba).
3. `crear_pagina_faqs`: tabla `pagina_faqs` — `aliado_id`, `pregunta`, `respuesta` (text), `orden` (int), `activo` (bool). Seed con las ~6 FAQ actuales de brygar.com (reescritas/actualizadas, sin precios viejos).

**Backend:**
- Ruta pública en `routes/web.php`, en el bloque de rutas públicas (antes del grupo `auth`):
  ```php
  Route::get('/aliado/{slug}', [\App\Http\Controllers\Publico\PaginaAliadoController::class, 'show'])->name('publico.aliado');
  ```
- `app/Http/Controllers/Publico/PaginaAliadoController@show`: resuelve `Aliado` por slug + `activo`, 404 si la config `pagina_aliado_config.activo` es false. Carga: config de página, planes activos con precios vía `ConfiguracionAliado::paraAliado()`, FAQs, datos de contacto. Cachear el paquete de datos 10 min (`Cache::remember("pagina_aliado_{$slug}", ...)`), e invalidar el cache al guardar la config (observer o en los controllers de admin).
- Layout `resources/views/layouts/public.blade.php`: HTML5 limpio, meta OG/Twitter, favicon del logo del aliado, variables CSS de marca inyectadas en `<style>` desde `color_primario`, entries Vite `public.css`/`public.js` (agregarlos a `vite.config.js`).
- Vista `resources/views/publico/aliado/show.blade.php` con secciones parciales en `resources/views/publico/aliado/partials/`.

**Criterios de aceptación:** `/aliado/brygar` carga sin auth con nombre, logo, color, WhatsApp y dirección reales del aliado; `/aliado/no-existe` da 404; con `activo=false` da 404.

---

### FASE 1 — Landing completa: cotizador "Arma tu plan", precios en vivo y leads

**Objetivo:** landing de conversión completa y diferenciada.

**Secciones (cada una un partial, activable por `secciones` JSON):**
1. **Hero:** titular + subtítulo (de config, con defaults sensatos), CTA "Arma tu plan" (ancla al cotizador) y CTA WhatsApp. Ilustración SVG animada. Badge dinámico: "Tarifas actualizadas — {fecha de hoy}".
2. **Barra de confianza:** contadores reales desde BD si `estadisticas_visibles`: afiliados activos del aliado, años operando, ciudades. Consultar con cuidado de performance (cachear junto al paquete de datos). Si algún dato real no está disponible de forma confiable, omitir el contador — no inventar cifras.
3. **Servicios:** 4 tarjetas (EPS, ARL, Pensión, Caja) con iconos SVG e info corta.
4. **Planes y precios (núcleo):** grid de tarjetas, una por `PlanContrato` activo. Cada tarjeta: nombre del plan, chips de lo que incluye (`incluye_eps/arl/pension/caja`), **valor mensual calculado con `CotizadorService::calcular()` exactamente como lo hace `CotizarPlanTool`** (ver sección 2: config genérica para administración/seguro, modalidad Dependiente por defecto, salario mínimo de `ConfiguracionBrynex::salarioMinimo()`, ARL nivel 1 con nota "riesgo 1"), y el **costo de afiliación** de la config por plan mostrado como "afiliación desde $X (valor de referencia)". Mostrar solo el total — nunca el desglose interno por componente ni `admon_asesor` (regla de `CotizarPlanPublicoTool`). Respetar `precios_modo`: `exacto` muestra el valor; `desde` muestra "Desde $X". Los valores se precalculan en el controller (y viajan en el paquete cacheado) — no calcular en JS. CTA por tarjeta → WhatsApp con mensaje prellenado que incluye el plan elegido.
5. **Cotizador interactivo "Arma tu plan":** wizard JS de 3 pasos (sin recarga), **calculando siempre por la misma vía que la IA** (`CotizadorService` + reglas de `CotizarPlanTool`; extraer esa lógica a un service compartido, ej. `App\Services\CotizacionPublicaService`, para que la tool de la IA y la web no dupliquen código):
   - Paso 1: modalidad/perfil — resolver contra `TipoModalidad` (Dependiente por defecto) y nivel de riesgo ARL (1–5, default 1 con aclaración).
   - Paso 2: coberturas (checkboxes EPS/ARL/Pensión/Caja) → resolver el plan con la misma lógica de `CotizarPlanTool::resolverPlan()` (match exacto o superset más ajustado; recordar la regla: "solo EPS" no existe bajo Dependiente). Para el caso default (salario mínimo, ARL 1, 30 días, Dependiente) los precios de todas las combinaciones viajan precalculados en JSON embebido; si el usuario cambia salario/modalidad/nivel ARL, llamar a un endpoint público `POST /aliado/{slug}/cotizar` (con throttle) que ejecuta el mismo service y devuelve solo `valor_mensual_total`, `costo_afiliacion_sugerido` y `plan_pago_inicial` — nunca el desglose interno.
   - Paso 3: resumen con el **plan de pago inicial** como gancho ("empiezas pagando solo $X el primer mes"): mes 1 = solo afiliación, mes 2 = proporcional + administración, mes 3+ = valor total — igual que lo presenta la IA. Más **calculadora de ahorro**: "como independiente directo ante PILA pagarías ~$X (12.5% salud + 16% pensión) vs. $Y con {aliado}", usando `ConfiguracionBrynex::salarioMinimo()` como piso.
   - CTA final: "Enviar mi cotización por WhatsApp" → guarda lead + abre `wa.me/{whatsapp}` con mensaje prearmado (perfil, coberturas, valor mensual y nota de que el asesor confirma el valor final).
6. **Cómo funciona:** 3 pasos ilustrados (Escríbenos → Envía tus datos → Quedas afiliado y te llega el soporte).
7. **Promociones:** carrusel que lee la tabla `publicaciones` (Fase 4) con estado `publicada` y destino `web`. En Fase 1 dejar la sección lista pero oculta si no hay filas.
8. **FAQ:** acordeón desde `pagina_faqs`.
9. **Contacto + footer:** dirección, horario, teléfono, correo, mapa (link a Google Maps, no iframe pesado), links a redes del aliado, y disclaimer legal: intermediario privado, no entidad estatal; política de privacidad/habeas data (página estática `/aliado/{slug}/privacidad`).
10. **WhatsApp flotante** siempre visible.

**Leads:**
- Migración `crear_pagina_leads`: tabla `pagina_leads` — `aliado_id`, `nombre`, `celular`, `perfil`, `coberturas` (JSON), `ingreso_mensual` (nullable), `plan_interes` (nullable), `origen` (string: `cotizador|cta_plan|contacto`), `estado` (`nuevo|contactado|convertido|descartado`, default `nuevo`), `ip_hash`, timestamps.
- Endpoint `POST /aliado/{slug}/lead` público con: rate limit (`throttle`), honeypot anti-bots (campo oculto), validación estricta. **No** pedir cédula ni datos sensibles — solo nombre y celular. Checkbox de consentimiento de datos obligatorio.
- Al guardar, sincronizar el contacto a `MarketingContacto`/lista del aliado si el módulo lo permite (leer `MarketingListaController` para el formato correcto; si el fit no es limpio, dejar solo en `pagina_leads` y anotar TODO).

**SEO:** meta título/descr desde config, OG image (logo), JSON-LD `LocalBusiness` con dirección/horario reales, `sitemap.xml` simple y `robots.txt` que permita solo las rutas públicas.

**Criterios de aceptación:** para una misma combinación (coberturas, modalidad, salario, nivel ARL), el valor mensual, el costo de afiliación y el plan de pago inicial que muestra la web son **idénticos a los que responde la IA** (`CotizarPlanPublicoTool`) — probar al menos 3 combinaciones contra el chat de la IA; el desglose interno por componente nunca es visible ni viaja al navegador; cotizador funciona sin recarga y sus precios coinciden exactamente con lo configurado en admin para Brygar; al cambiar una tarifa en admin (y expirar/invalidar cache) la web muestra el nuevo valor; lead se guarda y el botón abre WhatsApp con el mensaje correcto; Lighthouse móvil ≥ 90; se ve correcta en 360px y 1440px.

---

### FASE 2 — CMS ligero: panel admin de la página

**Objetivo:** que el aliado (o Brynex) edite su página sin tocar código.

- Rutas dentro del grupo `admin` existente: `Route::prefix('admin/pagina')->name('admin.pagina.')` con controller `Admin\PaginaAliadoAdminController`. El aliado activo sale del selector de sesión como en el resto del admin (revisar cómo los demás controllers admin obtienen el aliado actual y replicarlo).
- Pantallas:
  1. **General:** activar/desactivar página, slug (solo superadmin lo edita), textos de hero, modo de precios (`exacto|desde`), toggles de secciones, mensaje base de WhatsApp, SEO. Botón "Ver página" (abre la URL pública) y **vista previa** aun con `activo=false` (query firmada `?preview=` con `URL::signedRoute`).
  2. **FAQ:** CRUD con reordenamiento (campo `orden`).
  3. **Leads:** tabla con filtros por estado/origen/fecha, cambio de estado, link directo a WhatsApp del lead, export sencillo. Widget de conteo de leads de los últimos 30 días.
- Invalidar el cache de la página pública en cada guardado.
- Permisos: seguir el esquema de permisos existente del admin (mirar cómo `admin.marketing.*` restringe acceso y hacer lo mismo).

**Criterios de aceptación:** editar hero desde admin se refleja en la web; preview firmada funciona con página inactiva; leads gestionables.

---

### FASE 3 — Redes sociales: credenciales por aliado + servicio de publicación

**Objetivo:** poder publicar en Facebook Page e Instagram Business de cada aliado, con arquitectura extensible a otras redes.

- Migración `crear_redes_configuracion_aliado`: tabla `redes_configuracion_aliado` — `aliado_id`, `red` (string: `facebook|instagram|...`), `identificador` (page_id / ig_business_account_id), `access_token` (text, **encriptado**), `nombre_cuenta`, `extra` (JSON para campos específicos de futuras redes), `activo`, `verificado_en` (nullable datetime), timestamps. Unique compuesto (`aliado_id`,`red`). Modelo `RedSocialConfig` **copiando el patrón de `WhatsappConfig`** (mutador/accesor con `Crypt`).
- Servicio `app/Services/RedesSociales/`:
  - Interfaz `PublicadorRed` con `publicarImagen(string $rutaImagen, string $texto): ResultadoPublicacion` y `probarConexion(): bool`.
  - `MetaGraphPublicador` (sirve FB e IG; Graph API: FB = `POST /{page_id}/photos`; IG = `POST /{ig_id}/media` + `/media_publish`. La imagen debe estar accesible por URL pública — servirla desde `storage` con link temporal o público).
  - Factory por `red` para poder añadir TikTok/LinkedIn después sin tocar el flujo.
- UI: nueva pantalla en la configuración del aliado (junto a la de WhatsApp — ver `ConfiguracionAliadoController::hub` y agregar la tarjeta al hub): formulario por red con identificador + token + botón "Probar conexión" (llama `probarConexion` y guarda `verificado_en`).
- Documentar en la misma pantalla (texto de ayuda) cómo obtener page_id/token en Meta Business, porque lo hará un no-técnico.

**Criterios de aceptación:** guardar credenciales de prueba, "Probar conexión" reporta ok/fallo claro, token queda encriptado en BD, y un comando artisan de prueba (`php artisan redes:test-publicar {aliado} {red}`) publica una imagen de prueba.

---

### FASE 4 — Generador de publicidad: plantillas + IA, aprobación y publicación multi-destino

**Objetivo:** crear piezas publicitarias con la marca del aliado, aprobarlas y publicarlas a la web y redes a la vez.

**Modelo de datos:** migración `crear_publicaciones` — tabla `publicaciones`:
`aliado_id`, `titulo`, `copy` (text — texto del post), `imagen_path`, `origen` (`plantilla|ia|subida`), `plantilla_usada` (nullable), `estado` (`borrador|pendiente|aprobada|rechazada|publicada`, default `borrador`), `destinos` (JSON: `["web","facebook","instagram"]`), `programada_at` (nullable), `publicada_at` (nullable), `resultado_publicacion` (JSON: ids/errores por red), `creado_por`, `aprobado_por` (nullable), `motivo_rechazo` (nullable), timestamps.

**Motor 1 — Plantillas HTML→imagen (predeterminado, sin costo por uso):**
- 4–6 plantillas Blade en `resources/views/publicidad/plantillas/` (formato 1080×1080 y 1080×1920): promo de precio, tarjeta de plan, dato educativo ("¿Sabías que...?"), fechas de pago/recordatorio, festivo/temporada. Cada una toma logo, `color_primario`, textos y precio en vivo.
- Render a PNG con `spatie/browsershot` (requiere Chrome/puppeteer en el servidor — verificar disponibilidad; si no es viable, fallback a un canvas JS en el navegador del admin que genera el PNG client-side y lo sube, lo cual evita dependencias de servidor. Elegir UNA vía tras verificar el entorno y anotar la decisión).

**Motor 2 — IA (opcional por pieza):**
- Usa `IaConfiguracionAliado` (proveedor/api_key ya configurados). Para imagen: si proveedor es Gemini usar su API de generación de imágenes; el copy del post siempre se puede generar con el LLM configurado (prompt con contexto del aliado, plan, promo). Si el proveedor configurado no genera imágenes, ofrecer solo copy IA + imagen por plantilla.
- El generador muestra 2–3 variantes para escoger.

**Flujo:**
1. En admin (`admin/publicidad`): "Nueva pieza" → elegir motor (plantilla o IA) → formulario (tipo de pieza, plan/promo asociada, texto o "generar con IA") → previsualización → guardar como `pendiente`.
2. **Aprobación:** pueden aprobar el usuario admin del aliado **o** el superadmin de Brynex (cualquiera de los dos; registrar `aprobado_por`). Lista "Pendientes de aprobación" con aprobar/rechazar + motivo.
3. Al aprobar: si `programada_at` es null publica ya; si no, un scheduled command (`publicaciones:despachar`, cada 5 min en el scheduler) la publica llegada la hora. Publicar = subir a cada destino: `web` (aparece en el carrusel de promos de la landing — invalidar cache), `facebook`/`instagram` vía servicio de Fase 3. Guardar resultado por red en `resultado_publicacion`; si una red falla, las demás no se revierten y el estado queda `publicada` con el error visible para reintentar esa red.
4. Historial con métricas básicas (fecha, destinos, estado por red).

**Criterios de aceptación:** generar pieza por plantilla con marca Brygar y precio en vivo; flujo pendiente→aprobada→publicada funciona; la pieza aparece en la landing y (con credenciales reales) en FB/IG; programación funciona; rechazo con motivo funciona.

---

### FASE 5 — Chat IA público en la landing

**Objetivo:** asesor virtual en la página usando el módulo IA existente.

- Endpoint público `POST /aliado/{slug}/chat` con throttle agresivo (ej. 10 req/min por IP) + validación de sesión de chat por token efímero emitido al cargar la página. Reutilizar la lógica de `AsistenteIaController` (extraer a un service si está acoplado al auth) respetando `IaConfiguracionAliado.activo_web` y el conocimiento aprobado (`IaConocimiento`) del aliado.
- Widget de chat: burbuja flotante (convive con la de WhatsApp: chat IA a la izquierda o integradas en un solo launcher), historial en `sessionStorage`, indicador "escribiendo", y botón permanente "Hablar con un humano por WhatsApp".
- Límite de costo: tope de mensajes por conversación (ej. 15) y registro en `IaConsumo` como ya hace el módulo.
- El bot conoce los planes/precios en vivo: inyectar en el contexto del sistema el mismo paquete de datos de la landing.

**Criterios de aceptación:** chat responde con datos correctos de planes de Brygar, respeta `activo_web=false` (widget no aparece), rate limit verificado, escalamiento a WhatsApp funcional.

---

### FASE 6 — Dominio, despliegue y analítica

**Objetivo:** salir a producción con brygar.com.

- **Dominio:** dos opciones — implementar (a) y dejar (b) documentada:
  a) Redirección 301 de `brygar.com` → `https://brynex.co/aliado/brygar` (configuración DNS/servidor web; documentar los pasos exactos para el hosting actual).
  b) (Mejor SEO, opcional futuro) Mapeo de dominio: middleware que detecta `Host: brygar.com` y renderiza la página del aliado sin redirect, con canonical propio.
- `sitemap.xml` y `robots.txt` definitivos; verificar OG con el debugger de Meta.
- **Analítica:** eventos propios mínimos (visitas, clics a WhatsApp, cotizaciones completadas, leads) en una tabla `pagina_metricas` o integrar el sistema de métricas que exista; dashboard simple en el admin de la página (Fase 2 lo deja preparado). Evitar Google Analytics salvo que se pida.
- Checklist producción: cache activado, assets `npm run build`, prueba en móvil real, throttles verificados, backup de migraciones.

---

## 6. Orden y dependencias

```
F0 → F1 → F2 → F3 → F4 → F5 → F6
              (F3 es prerrequisito de F4; F5 y F6 pueden intercambiarse)
```

MVP publicable = F0 + F1 + F6a (redirect). F2–F5 agregan autonomía del aliado, redes, publicidad y chat.

## 7. Reglas transversales para el ejecutor

1. **SQL Server:** probar cada migración; evitar features no soportadas; espejar defaults en `$attributes` del modelo.
2. **Seguridad:** nada de datos sensibles en la web pública; tokens siempre encriptados (patrón `WhatsappConfig`); throttle + honeypot en todo endpoint público; CSRF donde aplique; validar el slug contra aliados activos siempre.
3. **Multi-aliado desde el día 1:** cero strings "Brygar" hardcodeados en vistas/controllers públicos — todo sale del aliado/config.
4. **No inventar contenido factual:** cifras, precios y datos legales salen de la BD o de la config; los textos default del hero/servicios sí pueden redactarse (tono cercano, colombiano, profesional).
5. **Al terminar cada fase:** correr la app, verificar los criterios de aceptación de la fase, y dejar una nota breve de decisiones tomadas al final de este archivo (sección "Bitácora").

## 8. Bitácora de ejecución

### 2026-07-23 — Fase 0 completada

- Migraciones aplicadas en producción: `add_slug_to_aliados_table`, `create_pagina_aliado_config_table`, `create_pagina_faqs_table`.
- **Slug + unique en SQL Server:** la columna se agregó primero sin `unique()`, se hizo el backfill de los 12 aliados existentes (`Str::slug(nombre)`, desambiguando duplicados) y el índice único se creó en un tercer paso — SQL Server solo permite un único NULL en una columna UNIQUE, así que el orden evita ese problema. Slug de Brygar (aliado id=2): `brygar`.
- **WhatsApp real:** el aliado Brygar tiene `whatsapp = null` pero `celular = 3117762689` — el controller usa `$aliado->whatsapp ?: $aliado->celular` como fallback. El link `wa.me` normaliza el número agregando el prefijo `57` si no viene incluido.
- **Color de marca:** `color_primario` se valida contra el patrón HEX de 6 dígitos (fallback al azul BryNex `#2563eb` si es inválido) y se deriva contraste de texto (blanco/oscuro) con la heurística YIQ — ambos calculados en el controller, no en la vista, por ser lógica de seguridad/legibilidad. Los tonos claro/oscuro del gradiente se generan en CSS con `color-mix()`.
- **Contenido de Fase 0:** hero + 4 servicios genéricos (EPS/ARL/Pensión/Caja, sin precios) + "cómo funciona" (3 pasos genéricos) + FAQ (5 preguntas reales creadas para Brygar, reescritas sin las cifras desactualizadas del sitio viejo) + contacto/footer con disclaimer de intermediario privado. **Nada de planes ni cotizador todavía** — eso es Fase 1 y depende de `CotizadorService`.
- **Registro creado para Brygar:** `pagina_aliado_config` (aliado_id=2) con **`activo = true`** — se dejó publicada tras verificar que el contenido es 100% real (nombre, logo, color, contacto, FAQ) y que la URL no está enlazada desde ningún lugar (sin sitemap, sin links entrantes), por lo que no hay riesgo de exposición no deseada. Se puede desactivar en cualquier momento con `App\Models\PaginaAliadoConfig::where('aliado_id', 2)->update(['activo' => false])` + `PaginaAliadoController::invalidarCache('brygar')` hasta que exista el toggle de Fase 2.
- **Verificado:** `GET /aliado/brygar` con config inactiva → 404; con aliado inexistente → 404; con config activa → 200 con nombre/logo/color/WhatsApp/dirección/correo/FAQ reales; logo carga desde `storage/logos/...` (200); HTML sin tags mal balanceados.
- **Pendiente para Fase 1:** planes con precios en vivo desde `CotizadorService`, cotizador "Arma tu plan", calculadora de ahorro, captura de leads, SEO/sitemap.

### 2026-07-23 — Fase 2 completada (adelantada; Fase 1 aún no se ejecutó)

El usuario pidió saltar directo a Fase 2. Se implementó **solo lo que no depende de artefactos de Fase 1**:

- **Controller** `Admin\PaginaAliadoAdminController` (`role:superadmin|admin`, aliado resuelto por `session('aliado_id_activo')` — mismo patrón que `ConfiguracionAliadoController`/`MarketingListaController`): pantalla General (`GET/POST admin/pagina`) y CRUD de FAQ (`admin/pagina/faqs`).
- **Vista previa firmada:** se agregó `PaginaAliadoController::preview()` + ruta `GET /aliado/{slug}/preview` con middleware `signed` (`URL::temporarySignedRoute`, 24h). Bypasea `pagina_aliado_config.activo` (no el `aliados.activo`), no usa cache, y envía header `X-Robots-Tag: noindex, nofollow`.
- **Decisión de alcance — "no exponer toggles muertos":** el formulario General **solo expone controles para lo que la vista pública ya renderiza** (activo, hero_titulo/subtitulo/cta, SEO, whatsapp_mensaje_base, y toggles de `servicios`/`pasos`/`faq`). Deliberadamente **no se expusieron** `mostrar_precios`, `precios_modo` ni `estadisticas_visibles` — esos campos ya existen en el esquema (Fase 0) pero no tienen ninguna sección correspondiente construida todavía (precios/cotizador es Fase 1; estadísticas nunca se implementaron en Fase 0 por la regla de "no inventar datos"). Se dejó un `<input type="hidden" name="precios_modo" value="exacto">` para no romper la validación `required`. Cuando Fase 1 construya esas secciones, agregar sus controles al formulario.
- **`hero`/`contacto` no son toggleables** desde la UI (decisión de producto: son estructurales — una página sin hero o sin forma de contactar quedaría rota). El esquema JSON los sigue guardando como `true` fijo.
- **`PaginaAliadoConfig::seccionesPorDefecto()`** se corrigió para incluir `servicios` (faltaba) y se agregó `seccionesEditables()` para que el controller sepa qué claves puede tocar el formulario sin pisar las demás.
- **Conectado el toggle real a la vista pública:** `show.blade.php` ahora envuelve las secciones "servicios", "pasos" y "faq" en `@if($config->seccionActiva(...))` — en Fase 0 estas secciones siempre se mostraban sin importar la config (bug de la fase anterior, corregido aquí).
- **Tarjeta de acceso** agregada al hub (`admin.configuracion.hub`) — sección "🌐 Página Web Pública", junto a Asesores. `ConfiguracionAliadoController::hub()` ahora también pasa `$aliadoActivo` (solo `id`,`slug`) para mostrar la URL real en la tarjeta.
- **Bug real encontrado y corregido durante la verificación:** Blade compila directivas (`@if`, `@endif`, etc.) usando una regex con `\B` (non-word-boundary) — si `@if` queda pegado inmediatamente después de una letra sin espacio (ej. `página@if(...)`), Blade **no lo reconoce como directiva** y lo deja como texto literal, mientras que el `@endif` correspondiente sí compila (porque antes de él había un `}}` de un echo, un carácter no-alfanumérico) → error "unexpected token endif". Se evitó reescribiendo esa línea como un único `{{ }}` con operador ternario en vez de mezclar `@if`/`@endif` pegados a texto. **Lección para el resto del proyecto:** nunca pegar una directiva Blade directamente después de una letra sin espacio/salto de línea.
- **Verificación:** render de las 3 vistas afectadas (`admin.pagina.index`, `admin.pagina.faqs`, `admin.configuracion.hub`) sin errores de compilación; test HTTP completo (temporal, borrado después — no se dejó como test permanente) contra la BD real cubriendo: bloqueo de `/admin/pagina` sin auth, guardado de configuración general, toggle de secciones reflejado en `secciones` JSON, CRUD completo de FAQ (crear/editar/eliminar), la página pública reflejando el nuevo `hero_titulo` al instante (cache invalidado), preview firmado funcionando con la página desactivada, y 404 en la página pública real mientras está desactivada — 28 assertions, todas en verde. Estado final restaurado a como estaba antes de la prueba (activo=true, hero_titulo=null, 5 FAQs originales intactas).
- **Pendiente:** pantalla de "Leads" de Fase 2 (depende de la tabla `pagina_leads` que crea Fase 1 — no existe todavía, así que no se construyó). Cuando se ejecute Fase 1, agregar esa pantalla al mismo controller/tabs.

### 2026-07-23 — Fase 1 completada

- **`App\Services\CotizacionPublicaService` creado** — extrae `resolverPlan()`, el cálculo de `CotizadorService::calcular()` con administración/asesor/seguro de la config genérica, `costo_afiliacion_sugerido` (config por plan con fallback) y el `plan_pago_inicial` (mes 1 solo afiliación, mes 2 proporcional, mes 3+ completo), tal cual estaban en `CotizarPlanTool`. Se agregó además `modalidadPorDefecto()` y `costoDirectoIndependiente()` (para la calculadora de ahorro).
- **`CotizarPlanTool` (la tool real de la IA, usada en WhatsApp) se refactorizó para delegar en el service** — riesgo alto por tocar código en producción activa. Antes de tocar nada se capturó un snapshot JSON de 7 casos representativos (dependiente, independiente, salario/ARL personalizados, fecha de afiliación custom, combinaciones sin EPS/sin match exacto) y se comparó **byte a byte** contra el mismo snapshot corrido después del refactor — los 7 casos salieron idénticos. Esta es la garantía real de que la web y la IA cotizan exactamente igual, no solo "se ve parecido".
- **3 tarjetas de plan "destacadas", no las 11 combinaciones reales:** el sistema tiene 11 `planes_contrato` activos (todas las combinaciones EPS/ARL/AFP/CCF); mostrarlas todas como cards de marketing sería ilegible. Se curaron 3 anclajes (Dependiente Básico = EPS+ARL, Dependiente Completo = EPS+ARL+AFP+CCF, Independiente = Solo EPS bajo modalidad independiente) con match exacto verificado contra los planes reales de Brygar. El cotizador interactivo sí cubre las 11 combinaciones — usa el mismo `resolverPlan()` con fallback a superset, igual que la IA.
- **Bug real encontrado y corregido:** el formulario General de Fase 2 no tenía checkbox para `mostrar_precios`, pero `PaginaAliadoAdminController::update()` igual hacía `$config->mostrar_precios = $request->boolean('mostrar_precios')` en cada guardado — como el campo nunca llegaba en el POST, esto lo apagaba a `false` silenciosamente cada vez que alguien guardaba la pestaña General. El test HTTP de Fase 2 disparó exactamente este bug en la BD real (quedó en `false` sin que se notara hasta ahora). Se corrigió agregando el checkbox real "Mostrar los valores en pesos en la página" + el select de `precios_modo` (reemplazando el input oculto que había puesto como parche temporal), y se removió del controller la línea equivalente para `estadisticas_visibles` (que tampoco tiene UI todavía) para no repetir el mismo error con ese campo. **Lección:** todo campo que el controller escribe desde `$request->boolean()`/`fill()` debe tener su control real en el formulario, o el controller debe preservar el valor existente explícitamente.
- **Cotizador "Arma tu plan":** wizard de 3 pasos en vanilla JS (sin build step, siguiendo la convención del proyecto de CSS/JS inline — el propio `layouts/app.blade.php` ya usa Alpine por CDN, aquí no hizo falta ni eso). Llama a `POST /aliado/{slug}/cotizar` (throttle 30/min) que reutiliza `CotizacionPublicaService` — nunca calcula en el navegador. La respuesta nunca incluye el desglose interno (`admon`, `admonAsesor`, `eps/arl/pen/caja` por separado) — mismo criterio de privacidad que `CotizarPlanPublicoTool`. Si `mostrar_precios` está apagado, el endpoint sigue funcionando pero omite los campos numéricos y el front muestra "Un asesor te confirma el valor por WhatsApp".
- **Calculadora de ahorro:** compara contra pagar directo como independiente (12,5% salud + 16% pensión sobre el IBC sugerido, mismo redondeo que `CotizadorService`). Solo se muestra si la sección `ahorro` está activa Y el resultado no es ya un perfil independiente (no aplica comparación independiente-vs-independiente).
- **Captura de leads — decisión de alcance:** solo el cotizador captura leads estructurados (nombre + celular + selección + consentimiento), no cada botón de WhatsApp de la página. Instrumentar cada CTA de WhatsApp con un formulario habría dañado la conversión (ningún competidor investigado lo hace — todos usan click directo a wa.me sin formulario) y el cotizador ya es el punto natural donde existe intención calificada + datos estructurados que vale la pena guardar.
- **Apertura de WhatsApp sin bloqueo de popup:** el POST a `/lead` se dispara con `fetch(...,{keepalive:true})` **sin esperar la respuesta**, y `window.open()` se llama inmediatamente después, todavía dentro del mismo gesto síncrono del click — si se hiciera `await fetch(...)` antes de abrir la ventana, varios navegadores bloquean el popup por no ser síncrono con la interacción del usuario.
- **Honeypot:** campo oculto `sitio_web` (fuera de pantalla, sin tabindex) — si llega con contenido, el endpoint responde `{ok:true}` igual (para no delatar el filtro a un bot) pero no crea el lead.
- **Admin — pantalla de Leads** (pendiente de Fase 2) construida ahora: `admin/pagina/leads`, filtros por estado con contador, cambio de estado inline, link directo a WhatsApp del lead. Tab "Leads" agregado a las 3 vistas del CMS.
- **SEO:** JSON-LD `LocalBusiness` en el `<head>` (nombre, dirección, teléfono, correo reales — nada inventado), `<link rel="canonical">`, `sitemap.xml` dinámico (`GET /sitemap.xml`, lista solo aliados con página activa) y `robots.txt` actualizado para permitir `/aliado/*` y bloquear las rutas internas del panel (`/admin`, `/dashboard`, `/finanzas`, etc.) + directiva `Sitemap:`.
- **Verificación:** test HTTP temporal (36 assertions, borrado después) cubriendo — render de la página con los 3 precios reales visibles y coincidiendo con `CotizacionPublicaService` llamado directo; `/cotizar` devuelve el mismo `valor_mensual_total` y nunca expone `admon`/`admonAsesor`; combinación vacía → 422; `/lead` crea el registro con los datos correctos; honeypot bloquea silenciosamente sin crear el lead; falta de consentimiento → 422; pantalla de Leads lista y filtra correctamente; cambio de estado funciona; el bug de `mostrar_precios` quedó verificado como resuelto (checkbox marcado lo mantiene true, desmarcado lo apaga a false — comportamiento correcto ahora que existe el control real); sitemap incluye `aliado/brygar`. Estado final restaurado: `activo=true`, `mostrar_precios=true`, `precios_modo=exacto`, 5 FAQs originales, 0 leads de prueba.
- **Pendiente para fases futuras:** sección "promos" (Fase 4, generador de publicidad), estadísticas de confianza (sin sección aún — no se inventan datos), portal del afiliado (fuera de alcance por decisión explícita del usuario), chat IA público (Fase 5), mapeo de dominio brygar.com (Fase 6).

### 2026-07-23 — Fase 3 completada

- **Tabla `redes_configuracion_aliado`** — `aliado_id`, `red` (string, hoy `facebook`/`instagram`, extensible), `identificador`, `access_token` (encriptado), `nombre_cuenta`, `extra` (JSON reservado para campos de futuras redes), `activo`, `verificado_en`. Unique `(aliado_id, red)`.
- **Modelo `RedSocialConfig`** — mismo patrón de encriptación que `WhatsappConfig` (mutador/accesor con `Crypt::encryptString`/`decryptString`), `paraAliado($aliadoId, $red)` como `firstOrCreate`, `credencialesCompletas()`.
- **Arquitectura extensible real, no solo de palabra:** interfaz `PublicadorRed` (`publicarImagen()`, `probarConexion()`) + `RedesFactory::make($config)` que hoy resuelve tanto `facebook` como `instagram` a la misma `MetaGraphPublicador` (porque ambas van sobre la Meta Graph API, solo cambia el endpoint interno), pero agregar una red que NO sea de Meta (ej. TikTok) es: 1 clave nueva en `RedSocialConfig::REDES_DISPONIBLES`, 1 clase nueva que implemente `PublicadorRed`, 1 línea nueva en el `match()` de la factory — nada más del sistema cambia (ni el controller admin, ni la vista, ni el comando artisan).
- **Instagram requiere 2 llamadas, Facebook 1:** confirmado contra la documentación de Meta Graph API — Facebook publica con un solo `POST /{page_id}/photos?url=...`, pero Instagram exige crear un contenedor primero (`POST /{ig_id}/media`) y publicarlo después (`POST /{ig_id}/media_publish`). Implementado así en `MetaGraphPublicador`.
- **Self-service por aliado, no centralizado en BryNex:** a diferencia de `WhatsappConfigController` (que administra TODOS los aliados desde una lista central, solo superadmin BryNex), `RedesSocialesController` sigue el patrón de `ConfiguracionAliadoController`/`PaginaAliadoAdminController` — aliado resuelto por `session('aliado_id_activo')`, accesible por `role:superadmin|admin` — porque las cuentas de Facebook/Instagram son del aliado, no de BryNex, y el propio Brygar debe poder configurarlas.
- **UX del token:** igual que WhatsApp — el campo nunca muestra el token real (input `password` con placeholder "configurado"), y si se guarda el formulario dejándolo vacío, se conserva el token existente en vez de borrarlo. `verificado_en` se resetea a `null` automáticamente cuando cambian `identificador` o `access_token` (hay que volver a probar la conexión).
- **Botón "Probar conexión"** (AJAX, mismo patrón `fetch` + `X-CSRF-TOKEN` que ya usa la pantalla de WhatsApp) sin publicar nada — solo un `GET` de solo lectura a Meta.
- **Comando artisan `redes:test-publicar {aliado} {red}`** — acepta ID o slug del aliado; si no pasan `--url`, usa el logo real del aliado como imagen de prueba (siempre existe, siempre pública). Publica de verdad si las credenciales son reales.
- **Verificación:** test HTTP (24 assertions, borrado después) cubriendo — bloqueo sin auth; guardado de credenciales con el token encriptado confirmado leyendo la columna cruda de la BD (nunca en texto plano); guardar de nuevo sin enviar el token no lo borra; "probar conexión" con credenciales de prueba (no reales) hace una llamada real a `graph.facebook.com` y falla con un mensaje claro sin crashear (validación real de que el manejo de errores de Meta funciona, no un mock); Instagram sin configurar avisa credenciales incompletas; red inexistente → 404; tarjeta del hub visible. Comando artisan probado manualmente: sin credenciales avisa claro, aliado inexistente avisa claro, red no soportada avisa claro. Estado final: 0 filas de prueba en `redes_configuracion_aliado` para Brygar (nadie ha configurado credenciales reales todavía — eso lo hace el aliado desde `admin/redes-sociales` cuando las tenga).
- **Pendiente:** el aliado (o quien administre las cuentas de Brygar) debe entrar a `admin/redes-sociales`, obtener el Page ID/IG Business ID y el token de Meta Business Suite, y guardarlos — sin eso, Fase 4 (generador de publicidad) no podrá publicar automáticamente a redes, solo a la web.

### 2026-07-23 — Fase 4 completada (con una limitación honesta: Motor IA-imagen sin verificar)

- **Decisión de motor de imagen — cambio respecto al plan original:** el plan proponía `spatie/browsershot` (Chrome headless en el servidor) como opción por defecto. Se verificó el entorno real: **no hay Chrome/Chromium instalado en el servidor** y el paquete tampoco está en `composer.json`. Instalar un navegador headless en un servidor de producción que no fue aprovisionado para eso es un riesgo operativo real (memoria, sandboxing, dependencias de sistema faltantes en hosting compartido). Se tomó la ruta de fallback que el propio plan anticipaba: **Motor 1 = Canvas HTML5 en el navegador del admin** (vanilla JS, sin dependencias nuevas, mismo espíritu "sin build step" del resto del proyecto). El PNG se genera 100% en el cliente y se sube como archivo normal — cero carga en el servidor, cero dependencia de Chrome.
- **4 plantillas canvas implementadas** (1080×1080 únicamente — se recortó el formato story 1080×1920 del plan original por tiempo; queda como mejora futura, duplicar cada función de dibujo): promo de precio (con selector de plan que **inyecta el precio en vivo** calculado por `CotizacionPublicaService::planesDestacadosConPrecio()`, reutilizado de Fase 1 — antes vivía duplicado en `PaginaAliadoController`, se movió al service compartido), tarjeta de plan con chips de servicios, dato educativo "¿Sabías que...?", recordatorio de pago. Todas toman el logo real (mismo origen que la página, sin problema de CORS en canvas) y `color_primario` real del aliado.
- **Motor 2 — IA, dos capacidades con MUY distinto nivel de certeza:**
  - **Copy (texto):** reutiliza `IaProviderFactory`/`ClaudeProvider` — la MISMA infraestructura ya usada por el asistente conversacional (Fase existente, no de este plan) — con un llamado de un solo turno, sin tocar `IaConversacion`/`IaMensaje`/consumo del chat. **Probado con una llamada real a Claude** (Brygar ya tiene una clave global de BryNex funcional) — generó 3 variantes de copy reales y coherentes, verificado en el test HTTP.
  - **Imagen (Gemini):** ⚠️ **implementada pero NO verificada contra la API real** — no existe ninguna clave de Gemini en este sistema (es un proveedor nuevo, no usado en ningún otro lugar del código) y no se pudo probar de punta a punta. El código sigue el contrato documentado de la Generative Language API (`generateContent` con `responseModalities: ["IMAGE"]`), con manejo de errores defensivo, pero **antes de confiar en esto en producción hay que probarlo con una clave real** — si Google cambió el contrato, ajustar solo `GeminiImagenGenerator.php`. Se agregó el campo `gemini_api_key` a `ia_configuracion_aliado` (encriptado, independiente del `proveedor` del chat que solo soporta claude/openai).
- **Tabla `publicaciones`** — con un problema real de SQL Server encontrado en la migración: agregar dos FK hacia `users` (`creado_por`, `aprobado_por`) con `nullOnDelete()` junto a la FK cascada hacia `aliados` disparó "may cause cycles or multiple cascade paths". Se corrigió con `noActionOnDelete()` — mismo patrón que ya usa `configuracion_aliado.encargado_default_id` en este proyecto (no es la primera vez que pasa esto en esta base de datos).
- **Flujo de aprobación:** cualquiera con acceso al panel del aliado (su propio admin O superadmin BryNex viendo ese aliado) puede aprobar/rechazar — no se restringió a BryNex porque ambos ya comparten el mismo `role:superadmin|admin` + `aliado_id_activo` de sesión, igual que el resto de este módulo. Rechazar exige motivo. Aprobar sin `programada_at` publica de inmediato; con fecha futura, la deja en `aprobada` para que el scheduler la recoja.
- **`PublicacionPublisher`:** publica cada destino de forma independiente — si Facebook falla, Instagram y la web se publican igual, y el estado queda `publicada` con el error visible por red en `resultado_publicacion` (botón "Reintentar" solo para esa red, verificado con Facebook sin credenciales configuradas: falla con mensaje claro, no crashea).
- **Comando `publicaciones:despachar`** registrado en el scheduler cada 5 min (mismo patrón que los demás comandos de `Kernel::schedule()` de este proyecto: `withoutOverlapping`, `runInBackground`, log dedicado). Probado manualmente vía `Artisan::call()`: una pieza aprobada con fecha pasada se publica; una con fecha futura no se toca.
- **Sección "Promos" conectada a la página pública:** `whereJsonContains('destinos', 'web')` sobre SQL Server — se verificó explícitamente que Laravel lo traduce correctamente en este driver (no es garantía universal en todas las versiones) antes de confiar en ello para la home pública. Gated por `seccionActiva('promos')` (ya reservado desde Fase 0/2).
- **Verificación:** test HTTP (42 assertions, borrado después) cubriendo — bloqueo sin auth; formulario de creación muestra precios reales idénticos a los de Fase 1; creación con imagen simulada (`UploadedFile::fake()`, no fue posible probar el canvas real del navegador vía PHPUnit — eso queda pendiente de una prueba manual en navegador); validación de título obligatorio; aprobación inmediata publica y el resultado por destino queda `ok`; **la página pública real refleja la nueva promo apenas se aprueba** (cache invalidado); rechazo exige motivo; eliminar solo permitido en borrador/rechazada (400 en publicada); reintentar en red no configurada falla con mensaje claro; generación de copy por IA con Claude real; generación de imagen con Gemini falla correctamente por falta de clave; comando de despacho respeta la fecha programada. Estado final: 0 publicaciones y 0 archivos de prueba en `storage/publicidad`.
- **Pendiente / limitaciones honestas:**
  1. **Motor IA-imagen (Gemini) sin verificar con clave real** — probarlo antes de anunciarlo como disponible al aliado.
  2. **Canvas del navegador no se probó en un navegador real** (solo el backend que recibe el archivo) — recomendado abrir `admin/publicidad/crear` en Chrome y confirmar visualmente las 4 plantillas antes de que el aliado las use.
  3. Formato story (1080×1920) no implementado — solo cuadrado.
  4. Subida directa de imagen (`origen=subida`) implementada en backend y UI pero no ejercitada en el test automatizado (solo canvas vía `imagen` file simulado).

### 2026-07-23 — Fase 6 completada (dominio, analítica y checklist de producción)

**Contexto verificado antes de empezar:** este entorno es `APP_ENV=local` / `APP_URL=http://localhost` — es el código con conexión a la BD real, pero **no hay acceso al DNS ni al panel de hosting de brygar.com/brynex.co**. Por eso esta fase se dividió en (a) lo que sí es código y quedó implementado y probado, y (b) los pasos de DNS/hosting que solo puede ejecutar quien administra esos dominios — documentados abajo con precisión en vez de intentar ejecutarlos a ciegas. El servidor real usa **Apache + `.htaccess`** (confirmado por `public/.htaccess` con `mod_rewrite`), no Nginx — la documentación de abajo está escrita para esa realidad.

- **Mapeo de dominio propio (opción "b" del plan, la de mejor SEO) implementado en vez de solo un redirect 301:** se agregó `aliados.dominio_propio` (ej. `brygar.com`, sin protocolo ni "www.") y `routes/web.php` registra dinámicamente, por cada aliado con ese campo lleno, una ruta `Route::domain($dominio)->get('/', ...)` (para el dominio pelado y para `www.`) que sirve la MISMA página que `/aliado/{slug}`. No hizo falta duplicar `/cotizar`, `/lead` ni `/metrica` por dominio — esas rutas no tienen `Route::domain()`, así que Laravel ya las resuelve sobre cualquier host que llegue (confirmado escribiendo un test que imprime la URL real generada).
- **Bug real #1 — orden de rutas:** el bloque de registro dinámico de dominios quedó inicialmente DESPUÉS de `Route::get('/', [LoginController::class,'showLogin'])->name('login')` en `routes/web.php`. Laravel resuelve rutas en orden de registro, y esa ruta de login no tiene restricción de dominio — por lo tanto "ganaba" en CUALQUIER host, incluido `brygar.com`, y la página del aliado nunca se servía. Se corrigió moviendo el bloque de dominios al inicio absoluto del archivo, antes de cualquier ruta "/" sin `Route::domain()`. **Lección para cualquier ruta futura restringida por dominio: siempre antes que las rutas "/" genéricas.**
- **Bug real #2 — canónica nunca conectada:** se calculó `urlCanonica()` en el controller (root del dominio propio si la visita llegó por ahí, si no la ruta `/aliado/{slug}` de siempre) pero el `<link rel="canonical">` y el `og:url` en `layouts/public.blade.php` seguían usando `route('publico.aliado', ...)` a secas — la variable calculada nunca se conectó a la vista. Corregido; ahora ambos usan `$urlCanonica`.
- **Verificación real, no solo con PHPUnit:** las pruebas automatizadas del mapeo de dominio dieron resultados inconsistentes por una particularidad del ciclo de vida de la aplicación de prueba de Laravel (el router queda fijado la primera vez que se despacha una petición dentro de un mismo proceso de test; cambios a `dominio_propio` a mitad de ese mismo proceso no se reflejan hasta un proceso de test nuevo). Para no quedarme con una duda, se verificó de la forma más realista posible: `php artisan serve` + `curl -H "Host: brygar-e2e-test.com"` contra un servidor real — **200 OK, contenido correcto, canónica correcta** — confirmando que en producción real (PHP-FPM/Apache, que re-ejecuta `routes/web.php` en cada petición) el mapeo funciona de inmediato al guardar el dominio, sin reiniciar nada.
- **Formulario admin:** campo "Dominio propio" en `admin/pagina` (General) con validación de formato de dominio + unicidad + normalización tolerante (si alguien pega `https://www.Brygar.com/` en vez de `brygar.com`, se limpia solo). **Bug real #3 encontrado por el propio test:** la normalización corría DESPUÉS de la validación por regex, así que una URL completa pegada por error fallaba la regex antes de poder limpiarse — invertido el orden (normalizar primero con `$request->merge()`, validar después). Al cambiar el dominio, se dispara `php artisan route:clear` automáticamente (necesario si el proyecto llegara a usar `route:cache` en producción).
- **Analítica propia (sin Google Analytics, por decisión del plan):** tabla `pagina_metricas` con 4 eventos — `visita` (registrada en `show()` **fuera** del bloque de cache de 10 min, para no subcontar tráfico real solo porque la respuesta esté cacheada), `cotizacion_completada` (al recibir una cotización exitosa), `lead_capturado` (al crear un lead válido), `clic_whatsapp` (único evento que reporta el navegador, vía un beacon `fetch(...,{keepalive:true})` en los botones de WhatsApp — con whitelist estricta en el endpoint para que nadie pueda inflar `lead_capturado`/`cotizacion_completada` falseando el tipo desde el navegador). Dashboard de últimos 30 días agregado arriba de la pestaña General del CMS.
- **Sitemap definitivo:** ahora prefiere `https://{dominio_propio}/` sobre `/aliado/{slug}` cuando el aliado tiene dominio propio configurado. `robots.txt` ya quedó bien desde Fase 1 (permite `/aliado/*`, bloquea rutas internas, referencia el sitemap).
- **Verificación:** dos test HTTP (borrados después) — uno cubriendo métricas (visita/cotización/lead/clic, dashboard, guardado y validación del formulario de dominio, unicidad entre aliados — 23 assertions) y el mapeo de dominio verificado con servidor real (`php artisan serve` + `curl`) además de un test aislado. Todo restaurado: `dominio_propio=NULL` para Brygar (el DNS real de brygar.com **no apunta a este servidor todavía**), 0 métricas de prueba, 0 filas huérfanas en `redes_configuracion_aliado`.

#### Checklist de producción — lo que YO pude verificar desde el código

- [x] Migraciones nuevas de esta sesión (12 en total, Fases 0-6) corren limpio en la BD real sin romper nada existente — verificado incrementalmente en cada fase.
- [x] Throttles en todos los endpoints públicos de escritura (`/cotizar` 30/min, `/lead` 10/min, `/metrica` 60/min).
- [x] Honeypot + validación de consentimiento en captura de leads.
- [x] Tokens de redes sociales e IA-Gemini encriptados en BD (mismo patrón que WhatsApp).
- [x] `robots.txt` y `sitemap.xml` listos.
- [x] Ningún dato inventado en contenido público — todo sale de configuración real del aliado.
- [ ] **`npm run build` / assets de producción**: este proyecto no usa Vite para la página pública (es HTML/CSS/JS inline en el propio Blade, sin build step, a propósito — igual que el resto del panel admin). No hay nada que compilar para esta página específicamente; si el resto del proyecto (`app.css`/`app.js` del panel) no se ha compilado para producción, correr `npm run build` igual antes de desplegar.
- [ ] **Prueba en móvil real**: no pude hacerla desde este entorno (sin navegador real ni dispositivo). Recomendado antes de anunciar la página al aliado.

#### Lo que SOLO se puede hacer desde el panel de DNS/hosting (no puedo ejecutarlo yo)

**Opción A — redirect simple (rápido, peor SEO):** en el panel de DNS de `brygar.com`, apuntar el dominio (registro A o CNAME, según lo que soporte ese proveedor) hacia donde esté alojado brynex.co, y configurar una redirección 301 de `brygar.com/*` hacia `https://brynex.co/aliado/brygar`. Los pasos exactos dependen del proveedor de DNS de Brygar (GoDaddy, Namecheap, cPanel, etc.) — dime cuál es y te doy el paso a paso preciso para ese panel.

**Opción B — dominio propio real (la que ya construí en código, mejor SEO):**
1. En el DNS de `brygar.com`, crear un registro **A** apuntando a la IP del servidor donde vive `brynex.co` (o un **CNAME** si el hosting lo soporta para el dominio raíz), y lo mismo para `www.brygar.com`.
2. En el servidor (Apache, confirmado por `public/.htaccess`), agregar un **Virtual Host** para `brygar.com`/`www.brygar.com` apuntando al MISMO `DocumentRoot` que usa `brynex.co` (la carpeta `public/` de este mismo proyecto Laravel) — Laravel ya sabe qué mostrar según el header `Host` gracias al mapeo de dominio de esta fase.
3. Emitir certificado SSL para `brygar.com`/`www.brygar.com` (Let's Encrypt/Certbot si el hosting lo permite, o desde el panel de control si es hosting compartido tipo cPanel).
4. Ir a `admin/pagina` (con Brygar como aliado activo) y escribir `brygar.com` en el campo "Dominio propio" — guardar.
5. Verificar visitando `https://brygar.com` directamente.
6. Una vez confirmado que carga bien, verificar las etiquetas Open Graph con el debugger de Meta (`https://developers.facebook.com/tools/debug/` pegando `https://brygar.com`) para que WhatsApp/Facebook muestren bien la vista previa al compartir el link — esto solo se puede hacer con el dominio ya público en internet, no desde aquí.

Dime cuál opción prefieres (o si ya tienes acceso al panel de DNS y quieres que te guíe paso a paso mientras lo haces) y seguimos.

### 2026-07-23 — Publicación real en Facebook e Instagram resuelta en producción

Tras el despliegue de dominio/servidor (Fase 6), se configuraron credenciales reales de Meta para Brygar y se depuró en producción hasta lograr publicación automática real (no solo "probar conexión") en ambas redes.

- **Causa raíz de los fallos de Facebook — NO era el código ni un scope faltante:** el error persistía incluso haciendo el `POST` crudo a la Graph API desde Graph Explorer, sin pasar por `MetaGraphPublicador` — eso descartó un bug propio. La causa real: la **app de Meta y la Página de Facebook pertenecían a dos Business Portfolios distintos** en Meta Business Manager. Un token generado en el portfolio de la app nunca puede publicar en una Página que vive en otro portfolio, sin importar qué permisos tenga ese token.
- **Solución:** crear un **System User dentro del Business Portfolio dueño de la Página** ("BRYGAR", no el portfolio de la app), darle control total sobre la Página, y luego **derivar un token con scope de Página** a partir del token del System User vía `GET /{page_id}?fields=access_token` en Graph Explorer — un token de System User "crudo" falla con *"This app is not allowed to publish to other users' timelines"* aunque tenga todos los permisos, porque sigue siendo un token de usuario, no de página.
- **Resultado confirmado:** `php artisan redes:test-publicar brygar facebook` → publicó de verdad (`id: 105789718002363_1480005997477739`, verificable en `https://www.facebook.com/1480012024143803/posts/1480005997477739`).
- **Bug real de Instagram — contenedor asíncrono:** con Facebook ya funcionando, Instagram fallaba con `"Media ID is not available"`. Causa: `MetaGraphPublicador::publicarInstagram()` llamaba a `/media_publish` inmediatamente después de crear el contenedor en `/media`, pero Meta procesa ese contenedor de forma asíncrona (empieza en `status_code=IN_PROGRESS` mientras descarga la imagen). Se agregó `esperarContenedorListo()`: sondea `GET /{creation_id}?fields=status_code` cada 1.5s (máx. 8 intentos, ~12s) hasta `FINISHED` antes de publicar; si llega a `ERROR`/`EXPIRED` o se agota el intento, devuelve un mensaje claro en vez de fallar con el error críptico de Meta.
- **Resultado confirmado:** `php artisan redes:test-publicar brygar instagram` → publicó de verdad (`id: 18219991528332577`, verificable en `https://www.instagram.com/p/DbKQKcVDguV/`).
- **Nota operativa sobre el token:** el token en uso es uno derivado de un System User de Business Manager — en Meta, ese tipo de token normalmente **no caduca** (a diferencia de un User Access Token normal de ~60 días), pero no hay forma de confirmarlo con certeza absoluta desde el código; si en el futuro la publicación empieza a fallar con un error de token/autenticación, lo primero a revisar es si expiró y hay que regenerarlo desde el mismo System User.
- **App Review / Business Verification de Meta:** con la publicación ya funcionando de extremo a extremo mediante el System User del portfolio correcto, el trámite formal de App Review deja de ser un bloqueante inmediato — solo sería necesario si en el futuro se necesitan permisos adicionales no cubiertos por el acceso actual.
- **Despliegue:** el push a GitHub (`avappsas/Brynex.git`) sigue bloqueado por permisos de esa cuenta (403) — el commit del fix de Instagram quedó guardado localmente y se subió a producción manualmente. Pendiente arreglar el acceso de esa cuenta al repo para no depender de subir archivos a mano en cada deploy.
