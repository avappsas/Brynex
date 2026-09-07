# Plan — API de Bancolombia para Brygar

Estado: catálogo revisado el **6-sep-2026**, cuenta creada en el portal, falta
solicitar el ingreso a Sandbox. En BryNex ya está el módulo de movimientos con
un adaptador falso (`config/banco.php`), esperando credenciales.

Dos frentes:

- **A. Consulta de movimientos** de la cuenta de Brygar → confirmar cobros solos.
- **B. Botón / QR / llaves Bre-B** → que el cliente pague y se confirme en línea.

---

## 0. Dónde está esto parado (6-sep-2026)

Cuenta creada en el portal y sesión funcionando. Lo que está trabado:

- **No se puede crear la aplicación.** El formulario de `/my-apps/create-app`
  queda completo y válido, pero el backend rechaza el envío:
  `POST https://apic-ext.apps.bancolombia.com/api-portal-ext/public-partner/sb/apps`
  → *"No se ha podido crear la aplicación. Código: no disponible"*. Ocho
  intentos, siempre igual. Sin aplicación no hay `Client Id` ni `Client Secret`.
- **La documentación de las APIs que sirven no está publicada**, ni siquiera
  con sesión iniciada: Transactional Information, Button Payment Instruction y
  Account Information dicen "se está actualizando".

Las dos cosas apuntan a lo mismo, y el portal lo avisa en el propio formulario:
falta la **habilitación** de la cuenta para crear aplicaciones. Se pide a la
mesa de ayuda; es el único paso que desbloquea el resto.

**Radicado #81429** en el Centro de Ayuda APIs Bancolombia (7-sep-2026, 10:40),
motivo «Solicito suscripción producto aliado», ambiente Sandbox. Pide la
habilitación para crear la aplicación y, de paso, las cinco preguntas técnicas
que no se pueden responder sin documentación — sobre todo si Transactional
Information exige FUA, porque de eso depende que el extracto pueda consultarse
desde un cron sin que nadie se autentique.

Ya listo de nuestro lado:

- **Certificado X.509** generado para el mecanismo JWT — RSA 2048, SHA256,
  `C=CO, ST=Valle del Cauca, L=Cali, O=BRYGAR, OU=Bancolombia, CN=brynex.co`,
  vigente hasta el **6-sep-2028**. Vive en `~/.brynex/certs/` (fuera del repo):
  `bancolombia-api.crt` es el público que se pega en el portal, y
  `bancolombia-api.key` es la llave privada, permisos 600. Si esa llave se
  pierde hay que generar otro par y actualizar la aplicación.
- El módulo de extracto, el cruce y la bandeja funcionan con el adaptador
  falso, así que nada del desarrollo está esperando al banco.

---

## 1. El catálogo real (revisado el 6-sep-2026)

El API Market **sí es público**: el catálogo y la documentación de cada
producto se ven sin iniciar sesión en
`api-portal-external.apps.bancolombia.com/products`. Lo que exige cuenta y
aprobación es probar contra Sandbox.

Son ~100 productos. Los que le sirven a BryNex:

| Producto | Tipo | Para qué nos sirve |
|---|---|---|
| **Transactional Information** 1.0.1 | Pública · Sandbox | «Consulta información transaccional dentro de un período determinado» — es el extracto. **Su documentación no está publicada**: hay que verla con sesión o preguntarle a la mesa de ayuda |
| **Button Payment Instruction** 2.0.0 | Pública · Sandbox | Botón de pago. El flujo es: generar intención de pago → validar → generar pago → **notificar estado final**. Esa última parte es la confirmación en línea que hoy no existe |
| **BancolombiaPay Payments Keys Administration / Information / Transactions** 1.0.0 | Pública · Sandbox | **Bre-B**. Crear, cancelar y actualizar llaves; consultar las llaves de un cliente; y mover plata con llaves del sistema Bre-B |
| **QR Code** 3.0.1 · **QR Payments Information** 2.0.0 · **QR Code Information** · **QR Code Refunds** | Pública · Sandbox | Administrar códigos QR, consultar sus transacciones y reversar pagos |
| **Collections Operations And Services** 1.1.0 | Pública · Sandbox | Pago de facturas en corresponsales bancarios — el cliente que paga en efectivo en la esquina |
| **Deposit Account Ownership** 3.0.2 | Pública · Sandbox | Valida si una cédula o NIT es el titular de una cuenta. Sirve para no consignarle a la cuenta equivocada |
| **Account Information** 1.0.0 | Open Finance | Consulta de cuentas de ahorro y corriente del titular que da su consentimiento |
| **Corporate Payment Order Initiation / Information** 1.0.0 | Pública · Sandbox | Órdenes de pago empresarial (dispersión). Solo con confirmación humana, nunca automático |
| **Financial Institutions** 1.0.0 | Pública · Sandbox | Catálogo de entidades para transferencias interbancarias |

Ojo con el lenguaje del portal: Keys, Button y QR hablan de «comercios
aliados». Sandbox se prueba solo, pero producción dice **«según cotización»**
— hay convenio comercial de por medio.

No existe ningún producto llamado «recaudo», ni aparece nada de webhooks o
suscripciones como producto aparte: buscar `webhook`, `subscription` y
`notification` no devuelve nada. La notificación de estado vive dentro del
flujo del Button.

### Autenticación (documentada y pública)

- **OAuth2 `client_credentials`**, con `Authorization: Basic base64(client_id:client_secret)`
  o las credenciales en formData. Pide `scope`.
- **JWT** con certificado X.509 propio: RSA 2048, SHA256, base64 (RS256).

Es decir: el servidor de BryNex puede autenticarse solo, sin usuario humano.
Encaja con guardar las credenciales cifradas por cuenta bancaria.

### Cómo se llega a Sandbox

Registrarse en el portal no basta. Falta:

1. **Solicitar el ingreso a Sandbox** (formulario con nombre de la empresa y
   el producto de API que se quiere). Aprobación en **menos de 24 horas** de
   lunes a viernes.
2. Activar el segundo factor en el portal.
3. **Crear una aplicación** dentro del portal — de ahí salen el `client_id` y
   el `client_secret`.

Sandbox es **gratis**; producción se cotiza.

### Preguntas que siguen abiertas para el banco

1. La documentación de **Transactional Information** no está publicada: qué
   devuelve exactamente, cuánto histórico, y si trae la referencia del
   comprobante y el documento de quien consigna.
2. Si la transferencia **Bre-B** llega al extracto con nombre y documento del
   pagador. De eso depende que las entradas sueltas se amarren solas.
3. Si el QR puede llevar **valor y referencia por factura**, o solo es el QR
   estático de la llave.
4. Tarifa de producción de Button / QR / Keys, y si exigen convenio de comercio.
5. Cuántas consultas al día están incluidas en Transactional Information.

---

## 2. Qué se habilita en BryNex con "saldos y movimientos"

### 2.1 Conciliación automática de consignaciones (el mayor ahorro)

Hoy es 100% manual: en el informe de validación alguien marca cada
consignación como `pendiente / verificado / no_aparece` mirando el extracto
(`InformeController` ~1574-1650, campos `confirmado`, `no_aparece`,
`usuario_validador_id`, `fecha_validacion`).

Con el API se cruza cada consignación contra el movimiento real por
**fecha + valor + referencia** y se marca sola, firmada por un usuario
"sistema". Lo que no cruce queda en la cola manual de siempre.

### 2.2 Descuadre del banco a la vista

`SaldoBanco::saldoActual()` es el saldo que BryNex *cree* tener. Comparándolo
con el saldo real de la cuenta, el cuadre diario puede avisar el mismo día
cuando hay diferencia, en vez de descubrirlo semanas después.

### 2.3 Entradas sin identificar

Plata que entró al banco y no tiene consignación registrada: bandeja nueva
donde se asigna a factura, anticipo o traslado con dos clics. Hoy ese dinero
simplemente no existe en el sistema hasta que alguien lo digita.

### 2.4 Confirmación al cliente por WhatsApp

Al detectar el abono, disparar la plantilla de confirmación con
`WhatsappApiService`. El cliente deja de mandar la foto del comprobante.

### 2.5 Sirve para todos los aliados, no solo Brygar

`banco_cuentas` ya tiene `aliado_id`: las credenciales se guardan **por
cuenta**, no globales, y cada aliado que tenga cuenta Bancolombia puede
prenderlo sin tocar código.

---

## 3. Qué se habilita con "recaudo / botón de pagos"

### 3.1 Link de pago en el cobro por WhatsApp

El mensaje de cobro lleva un enlace con el valor y la referencia de la
factura. El cliente paga y la factura se marca pagada sin intervención.

### 3.2 Referencia única por factura — cierra un hueco viejo

Hoy `consignaciones.factura_id` viene vacío en el 99% de lo legacy y el
vínculo toca deducirlo de la observación. Con referencia de recaudo
(`numero_factura` + cédula) **el pago llega ya amarrado** a la factura. De
aquí en adelante deja de existir el problema.

### 3.3 Pago en la página pública del aliado

Encaja con `docs/plan-pagina-publica-aliado.md`: "paga tu seguridad social"
con la cédula, sin llamar a nadie.

### 3.4 Anticipos y préstamos

Cobrar antes de facturar (ya existe `TIPO_ANTICIPO`), y en finanzas
personales, mandar el link en el recordatorio de corte del préstamo.

---

## 4. Decisiones técnicas que hay que respetar

- **Credenciales por cuenta bancaria**, cifradas, siguiendo el patrón de
  `razon_social_credenciales`. Ojo con el accessor que desactiva el cast
  `encrypted` (ya pasó con las claves de ARL Sura).
- **Idempotencia**: tabla nueva `banco_movimientos` con índice único por
  (cuenta, id del movimiento en el banco). Sin eso, cada corrida duplica.
- **Nada de transferencias salientes automáticas.** El API de pagos, si se
  activa algún día, se usa con confirmación humana explícita.
- **Cola, no request**: la sincronización va como job en el worker que ya
  existe (supervisor, numprocs=2), no colgada de una petición web.
- **Webhook de recaudo**: ruta pública, firma validada, y responder 200 rápido
  encolando el trabajo — es un endpoint expuesto a internet.
- Multi-tenant: toda query filtra por `aliado_id`, no hay scope automático.

---

## 4-bis. Idea aplazada: prestarle una llave de Brygar a un aliado

Planteada el 7-sep-2026, **en pausa hasta que responda el banco**.

La idea: Brygar crea una llave Bre-B adicional (por sucursal, o por aliado) y
se la presta a un aliado para que sus clientes paguen ahí.

Se puede técnicamente —Bre-B admite varias llaves por titular—, pero la llave
no cambia el dueño del dinero: **todo cae en la cuenta de Brygar**, así que es
recaudo por cuenta ajena. Antes de montarlo hay que resolver, y no es una
decisión de código:

- **Tributario**: esa plata puede leerse como ingreso de Brygar. En régimen
  simple, los ingresos brutos definen tope y tarifa. Se maneja con contrato de
  mandato y contabilidad separada, pero lo valida el contador.
- **SARLAFT**: movimientos de terceros sin el soporte del mandato es lo que el
  banco marca.
- **4x1000** dos veces: al entrar y al trasladar al aliado.

Cómo se validaría lo que entró, de más a menos confiable:

1. **Cuenta dedicada por aliado.** Brygar ya tiene 13 cuentas. Una cuenta con
   su llave por aliado: el extracto de esa cuenta *es* la verdad de lo que
   entró. Funciona con el módulo tal como está hoy, sin tocar nada.
2. **Una cuenta con varias llaves.** Depende de un dato que no tenemos:
   **¿el detalle del movimiento identifica la llave de destino?** Si sí, se
   guarda esa llave en `banco_movimientos` y el reparto por aliado sale solo.
   Si no, habría que discriminar por valor y fecha — y con planillas de salario
   mínimo, todas del mismo monto, ahí la conciliación se vuelve adivinanza.
   Además exigiría que una cuenta de Brygar reciba pagos de clientes de otro
   aliado, y hoy `banco_cuentas.aliado_id` asume lo contrario.
3. **Referencia por factura**, que depende del producto de recaudo.

Pregunta para el banco cuando conteste el #81429:

> ¿El detalle del movimiento identifica la llave Bre-B de destino cuando la
> cuenta tiene varias llaves registradas?

---

## 5. Orden sugerido

1. Pedir **A** al banco y conseguir sandbox.
2. `banco_movimientos` + servicio de sincronización + comando Artisan.
3. Conciliación automática sobre el informe de validación que ya existe.
4. Saldo real vs saldo BryNex en el cuadre diario.
5. Bandeja de entradas sin identificar.
6. Recaudo (**B**) cuando el banco defina producto y tarifa.
