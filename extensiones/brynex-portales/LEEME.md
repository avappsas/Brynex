# BryNex Portales (extensión de Chrome)

Hace los trámites de afiliación de BryNex en los portales de las EPS usando la
sesión que la persona abre en su propio Chrome. Hoy: S.O.S. (novedad de inicio
laboral: consultar, radicar, adjuntar el lado B y bajar el certificado).

Existe porque el login de S.O.S. pide reCAPTCHA y Google no deja pasar un Chrome
automatizado desde el servidor. La extensión no ve ni guarda claves: la persona
inicia sesión como siempre.

## Instalar (una vez por equipo)

1. Copia esta carpeta `brynex-portales` al equipo.
2. Chrome → `chrome://extensions` → activa **Modo de desarrollador**.
3. **Cargar descomprimida** → elige la carpeta.
4. Recarga BryNex.

## Uso

Afiliaciones → radicado de EPS de un contrato con S.O.S. → **🏥 Novedad S.O.S.**
El modal pide abrir S.O.S. en otra pestaña; se inicia sesión ahí (con captcha) y
se vuelve a BryNex. Mientras corre el trámite no hay que usar la pestaña de S.O.S.

## Cómo funciona

- `puente.js` corre dentro de BryNex y reenvía los pedidos de la página
  (`window.postMessage`, canal `brynex-portales`) al service worker.
- `background.js` solo acepta pedidos de brynex.co y localhost:8000, busca la
  pestaña de S.O.S. y ejecuta los pasos en ella con `chrome.scripting`.
- BryNex (`SosController`) prepara los datos, entrega el lado B y registra en el
  radicado lo que la extensión trajo.
