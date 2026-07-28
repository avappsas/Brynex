# Worker de consulta a ADRES

Servicio de Node que maneja el navegador contra el portal de ADRES
(`Maestro Afiliados Compensados`) para el chequeo de seguridad social.

## Por qué es un proceso aparte

ADRES pide un código de seguridad (Telerik RadCaptcha) que **resuelve una
persona**, no el sistema. Entre que se pide el código y el cliente lo responde
por WhatsApp pueden pasar minutos, y el captcha va atado a la sesión de ASP.NET:
el contexto del navegador tiene que seguir vivo todo ese rato. PHP no sostiene
eso entre peticiones; este worker sí.

**El captcha no se resuelve automáticamente.** El worker recorta la imagen y
espera el texto. No hay OCR ni servicios de resolución, y no debe agregarse: ese
control es de ADRES y se respeta.

## Instalación

```bash
cd adres-worker
npm install
npx playwright install chromium
```

En Linux puede hacer falta `npx playwright install-deps chromium`.

## Configuración

| Variable | Por defecto | Para qué |
|---|---|---|
| `ADRES_WORKER_PUERTO` | `8801` | Puerto de escucha |
| `ADRES_WORKER_HOST` | `127.0.0.1` | **Dejar en loopback** |
| `ADRES_WORKER_TOKEN` | — | Obligatorio; debe coincidir con el `.env` de Laravel |
| `ADRES_HEADLESS` | `true` | `false` para ver el navegador al depurar |
| `ADRES_MAX_INTENTOS` | `3` | Intentos de captcha por consulta |
| `ADRES_TTL_SESION_MS` | `720000` | Cuánto espera una sesión sin respuesta |

> El worker puede consultar el historial de salud de cualquier cédula. **No debe
> quedar expuesto en red**: solo loopback y con token.

## API

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | `/salud` | Estado; único endpoint sin token |
| `POST` | `/consultas` | `{cedula, tipo_documento}` → `{sesion_id, captcha_png_base64}` |
| `POST` | `/consultas/:id/captcha` | `{texto}` → resultado, o captcha nuevo si falló |
| `DELETE` | `/consultas/:id` | Cierra la sesión |

Autenticación por header `X-Worker-Token`.

## Notas de mantenimiento

- **El orden de los radios de tipo de documento cambia en cada carga.** Se vio
  `RblTipoDoc_13` y `RblTipoDoc_2` para el mismo campo en dos corridas seguidas.
  Se resuelven por el texto del label, nunca por índice.
- **El resultado se verifica contra la cédula pedida** antes de devolverlo.
- **Se descarga el PDF** en vez de raspar la tabla: la pantalla solo muestra 10
  filas de hasta 16 páginas, y el PDF trae todo con capa de texto.
- Si el conteo del PDF no cuadra con lo que declaró la web, el resultado se marca
  `completo: false` y del lado de Laravel se fuerza revisión humana.
- Los selectores (`txtNumDoc`, `btnConsultar`, `btnDescargar`, `RadCaptcha1_*`)
  los genera WebForms/Telerik. Si ADRES redespliega, el worker falla ruidosamente
  — nunca devuelve datos a medias en silencio.

## Ejecución

Con systemd, apuntando a `node servidor.mjs` con las variables de entorno y
`Restart=always`. Cada consulta abre un contexto de navegador y lo cierra al
terminar; las sesiones abandonadas se barren cada minuto.
