# Instalación del worker de ADRES en producción

Todo esto se corre **dentro del servidor como root**. Si estás en tu Mac, entra
primero con `ssh brynex-prod` — ese alias solo existe en tu `~/.ssh/config`
local, dentro del servidor no resuelve.

## 1. Node 20 o superior

Playwright 1.62 exige Node ≥20 y el servidor traía 18.20.8. Con Node 18 el
`npm install` pasa con un warning y el worker revienta después, en ejecución.

```bash
node -v   # si ya dice v20+ o v22+, saltar este paso
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y nodejs
node -v
```

Nada más en el servidor usa Node (no hay procesos ni servicios), así que la
actualización no arrastra a nadie.

## 2. Código y dependencias

```bash
cd /var/www/brynex
git pull
php artisan migrate
```

```bash
cd /var/www/brynex/adres-worker
npm install
PLAYWRIGHT_BROWSERS_PATH=/opt/ms-playwright npx playwright install --with-deps chromium
```

`PLAYWRIGHT_BROWSERS_PATH` no es opcional: sin él los binarios quedan en
`/root/.cache/ms-playwright` y `www-data` —que es quien corre el servicio— no
los encuentra.

```bash
chown -R www-data:www-data /var/www/brynex/adres-worker/node_modules
chown -R www-data:www-data /opt/ms-playwright
```

## 3. Token compartido

El mismo valor va en dos lados: el `.env` de Laravel y el del worker.

```bash
TOKEN=$(openssl rand -hex 24)

printf '\nADRES_WORKER_URL=http://127.0.0.1:8801\nADRES_WORKER_TOKEN=%s\n' "$TOKEN" \
  >> /var/www/brynex/.env

install -d -m 0750 -o root -g www-data /etc/brynex
install -m 0640 -o root -g www-data \
  /var/www/brynex/adres-worker/deploy/adres-worker.env.ejemplo \
  /etc/brynex/adres-worker.env
sed -i "s|^ADRES_WORKER_TOKEN=.*|ADRES_WORKER_TOKEN=${TOKEN}|" /etc/brynex/adres-worker.env

unset TOKEN
```

**Reconstruir el caché de configuración. No es opcional.**

```bash
cd /var/www/brynex && sudo -u www-data php artisan config:cache
```

Si hay un `bootstrap/cache/config.php` viejo, Laravel lee de ahí y **no** del
`.env`: `config('services.adres_worker.token')` devuelve vacío y el worker
responde `Token inválido` aunque los dos archivos tengan el mismo token. Pasó en
el despliegue inicial y cuesta un rato ver de dónde viene, porque `/salud`
—que no pide token— sigue respondiendo bien.

Va con `sudo -u www-data`: si se corre como root, el caché queda con dueño root y
después la app no puede regenerarlo.

**Y reiniciar las colas. Tampoco es opcional.**

```bash
cd /var/www/brynex && sudo -u www-data php artisan queue:restart
```

Los `queue:work` son procesos largos: cargan la configuración al arrancar y la
guardan en memoria. Regenerar el caché **no** los afecta — siguen con el token
viejo hasta que se reinicien. Y como el chequeo de ADRES corre entero dentro de
la cola (tanto la tool de la IA como el job que resuelve el código), sin este
paso todo falla con `Token inválido` aunque por CLI funcione perfecto.

Pasó en el despliegue inicial: la config se cacheó cinco minutos después de que
arrancaran los workers.

## 4. Servicio

```bash
cp /var/www/brynex/adres-worker/deploy/adres-worker.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now adres-worker
systemctl status adres-worker --no-pager
```

## 5. Comprobar

```bash
curl -s http://127.0.0.1:8801/salud
```

Debe responder algo como:

```json
{"ok":true,"sesiones_abiertas":0,"navegador_conectado":false,"max_intentos":3,"ttl_sesion_ms":720000}
```

Que `navegador_conectado` sea `false` al arrancar es normal: Chromium se abre en
la primera consulta.

Verificar que **no** quedó expuesto en la red — esto debe fallar:

```bash
curl -s -m 5 http://$(hostname -I | awk '{print $1}'):8801/salud || echo "OK: no responde por IP pública"
```

Y que el token efectivamente protege:

```bash
curl -s -X POST http://127.0.0.1:8801/consultas -d '{}'   # -> {"ok":false,"error":"Token inválido."}
```

## Operación

```bash
journalctl -u adres-worker -f          # ver en vivo
systemctl restart adres-worker         # reiniciar
```

Después de cada `git pull` que toque `adres-worker/`:

```bash
cd /var/www/brynex/adres-worker && npm install && systemctl restart adres-worker
```

## Si algo falla

| Síntoma | Causa probable |
|---|---|
| `browserType.launch: Executable doesn't exist` | Los navegadores quedaron en otra ruta; reinstalar con `PLAYWRIGHT_BROWSERS_PATH=/opt/ms-playwright` |
| Arranca y muere en bucle | Puerto 8801 ocupado, o falta `ADRES_WORKER_TOKEN` en `/etc/brynex/adres-worker.env` |
| Laravel dice "no se pudo contactar el worker" | Token distinto entre el `.env` de Laravel y el del worker |
| `Token inválido` pero los dos archivos tienen el mismo token | Caché de config viejo, o colas sin reiniciar: `config:cache` **y** `queue:restart` |
| `psysh` no puede escribir su config al usar tinker como www-data | Anteponer `env HOME=/tmp XDG_CONFIG_HOME=/tmp` |
| `No se encontró el tipo de documento` | ADRES redesplegó y cambió el formulario; revisar `lib/consulta.mjs` |
