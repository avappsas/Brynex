# Plan — API de Bancolombia para Brygar

Estado: **por solicitar al banco** (31-ago-2026). Nada implementado todavía.

Dos productos en la mira:

- **A. Consulta de saldos y movimientos** de la cuenta de Brygar.
- **B. Recaudo / botón de pagos** para que el cliente pague en línea.

---

## 1. Qué hay que pedirle al ejecutivo de Bancolombia

El catálogo del API Market no es público: se ve con credenciales, y la
activación **no es autoservicio** — la habilita el ejecutivo comercial
asignado. Lo confirmado en la documentación pública:

- El API Market (`api-portal-external.apps.bancolombia.com`) tiene el catálogo
  y la documentación técnica de cada producto.
- Las credenciales `Client-Id` / `Client-Secret` se piden por **formulario de
  la mesa de ayuda** (`soportedevs.bancolombia.com`).
- Hay **sandbox**, y el banco dice que cada operación refleja la misma
  información que producción.
- Para el Botón Bancolombia: cliente y comercio deben tener cuenta activa que
  permita créditos y débitos, el comercio debe estar inscrito en clave dinámica,
  y **la tarifa se negocia** con el gerente transaccional (no hay tarifa fija
  publicada).

### Lista para la reunión

Datos que hay que llevar:

- NIT y razón social de Brygar, y el número de la cuenta a conectar.
- Dominio: `brynex.co`.
- IP fija de salida del servidor (netcup) — probablemente la pidan para la
  lista blanca.

Preguntas que hay que hacer (de esto depende el diseño):

1. Nombre exacto del producto de **consulta de movimientos y saldos** y su
   alcance: ¿movimientos del día, histórico de cuántos meses, incluye la
   descripción/referencia de cada transacción y el NIT/nombre de quien
   consigna?
2. ¿Cuántas consultas al día están incluidas y desde cuánto se cobra? (Define
   si el cruce corre cada hora o una vez en la noche.)
3. Autenticación: ¿OAuth2 `client_credentials` a secas, o además certificado
   mTLS / firma del mensaje? ¿Exige IP en lista blanca?
4. Recaudo: cuál conviene para cobrar seguridad social mensual —
   **Botón Bancolombia** (transferencia desde la Sucursal Virtual, solo
   clientes Bancolombia, costo bajo), **convenio de recaudo con referencia**
   (el cliente paga en corresponsal/app y llega con la referencia de la
   factura), o **Wompi** (es de Bancolombia; cubre PSE, tarjeta, Nequi y
   Bancolombia, y se activa mucho más rápido).
5. ¿El recaudo notifica por **webhook** al comercio, o toca consultar? Si es
   webhook: cómo se firma para poder validarlo.
6. Tiempos: cuánto tarda sandbox y cuánto el paso a producción.

> Recomendación: pedir **A** en firme (no mueve dinero, riesgo bajo) y en
> paralelo cotizar **B**. Si el banco se demora con el Botón, Wompi resuelve
> lo mismo en días.

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

## 5. Orden sugerido

1. Pedir **A** al banco y conseguir sandbox.
2. `banco_movimientos` + servicio de sincronización + comando Artisan.
3. Conciliación automática sobre el informe de validación que ya existe.
4. Saldo real vs saldo BryNex en el cuadre diario.
5. Bandeja de entradas sin identificar.
6. Recaudo (**B**) cuando el banco defina producto y tarifa.
