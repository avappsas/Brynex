# Endurecimiento del servidor — agosto 2026

Registro de los cambios de **infraestructura** aplicados el 2026-08-08. No están
en el código, así que sin este documento vivirían solo en el servidor. Todo está
aplicado y verificado; cada apartado dice cómo revertir.

Empezó como un error 500 al iniciar sesión y terminó tocando permisos, cron,
colas, credenciales y firewall. El hilo conductor es el mismo: **fallos que no
avisaban**.

## 1. La causa del 500: caché de archivos con dueño `root`

`POST /login` pasa por `throttle:5,1`, y el rate limiter guarda el contador en
`storage/framework/cache`, en un subdirectorio cuyo nombre es el hash de la IP.
8 de 91 subdirectorios eran `root:root 755`, así que Apache (`www-data`) no podía
crear el archivo: `fopen(...): Failed to open stream` → **500 antes de validar la
contraseña**. Solo fallaban las IPs cuyo hash caía en un shard de root, y por eso
parecía problema de una persona: era global e intermitente.

Arreglo en dos capas:

```bash
chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod g+s {} +
find storage bootstrap/cache -type d -exec setfacl \
  -m u:www-data:rwx,g:www-data:rwx,d:u:www-data:rwx,d:g:www-data:rwx {} +
find storage bootstrap/cache -type f -exec setfacl -m u:www-data:rw,g:www-data:rw {} +
```

Lo que cierra el bucle es la **ACL por defecto**: cuando existe, el kernel
**ignora el umask** del proceso que crea. Verificado con `umask 0022` de root
creando un shard nuevo: sale `drwxrwsr-x+` y www-data puede escribir. Requiere el
paquete `acl` (instalado; ext4 lo soporta aunque no salga en `mount`).

Aplicado también a `/var/www/cf` (cuentafacil.co), que comparte servidor, usuario
y `CACHE_DRIVER=file`: tenía 3.068 archivos de root, entre ellos **los 16 logs
del canal `daily`**, o sea que sus errores web no se registraban.

## 2. El cron pasa a `www-data`

La fuente que envenenaba la caché era el `schedule:run` de root: el mutex de
`withoutOverlapping()` se guarda en la caché y lo crea el proceso de
`schedule:run` (en `Event::run()`, `shouldSkipDueToOverlapping()` corre **antes**
de `start()`, que es donde `->user()` recién aplica). Por eso el
`->user('www-data')` del commit 5b60d48 nunca lo evitó.

Ahora los dos `schedule:run` (brynex y cf) están en `crontab -u www-data` y root
solo conserva sus backups. **Requisito**: quitar los `->user('www-data')` del
Kernel (commit 7f81275) — Laravel los implementa como `sudo -u www-data`, y
www-data **no está en sudoers**: el subproceso muere y `schedule:run` reporta
`DONE` igual. Respaldos del crontab en `/root/crontab-root-respaldo-*`.

### El daño invisible: mutexes atascados 24 h

Al no poder escribirse la caché, `schedule:finish` nunca borraba el mutex, y el
default de `withoutOverlapping()` son **1440 minutos**. Siete tareas llevaban
**17 horas sin correr** sin un solo error: `videos:procesar` y
`publicaciones:despachar` congelados desde el 7-ago 18:18. `clientes:completar-ruaf`
fue la única viva, porque era la única con expiración explícita (120).

Todas llevan ya minutos explícitos (commits 86541fb y, en cf, a4226ea): 15 para
las de cada 1-5 min, 30 para las de 15-60, 60 para diarias y mensuales. Se
limpian con `schedule:clear-cache`; para ver si hay alguno tomado, recorrer
`$sch->events()` y mirar `$e->mutex->exists($e)`.

## 3. Tres scripts ejecutables expuestos en `public/` de cf

Mismo patrón que C-1/C-2/C-3 de [auditoria-seguridad.md](auditoria-seguridad.md),
esta vez en cuentafacil.co, los tres sirviendo HTTP 200 (commits 5369935, 5436efe):

| script | qué hacía |
|---|---|
| `diagnostico_storage.php` | `?action=crear_link` ejecutaba `unlink()` + `symlink()` sobre `public/storage`, **sin autenticación** |
| `debug_schema.php` | publicaba host del SQL Server, `DB_DATABASE`, `CURRENT_USER`, `INFORMATION_SCHEMA` de dos bases y el mensaje crudo de las excepciones |
| `opcache_reset.php` | reseteaba el opcache a quien invocara la URL |

Tras el barrido, `public/` de ambos proyectos tiene **solo `index.php`**. Si
aparece otro `.php` suelto ahí, es un diagnóstico que se filtró: va como comando
Artisan. Para limpiar caché existe `artisan optimize:clear`.

## 4. La aplicación sale de `sa`

Ver [login-app-sin-sa.md](login-app-sin-sa.md) para el procedimiento completo.
`brynex.co` usa `brynex_app` (db_owner en `BryNex` y `BryNex_Finanzas`) y
`cuentafacil.co` usa `cf_app` (db_owner en `Cuenta`); ninguno es sysadmin.
Aislamiento probado en ambas direcciones.

La contraseña de `sa` se rotó de **10 a 28 caracteres**. Vive en
`/root/.brynex_credentials` (600, root), del que hacen `source` los cinco scripts
de `/usr/local/bin/backup-*.sh` — **ese archivo no se borra**. `sa` sigue en uso a
propósito para los backups y para la administración manual por SSMS.

## 5. Auditoría de logins exitosos

Antes solo se registraban los fallos, así que un acceso exitoso no dejaba rastro.
Ahora `AuditoriaLoginsExitosos` (SQL Server Audit) escribe en
`/var/opt/mssql/audit/`. Detalles y consulta en
[login-app-sin-sa.md](login-app-sin-sa.md).

## 6. Un solo worker de colas

Había **dos** definiciones de supervisor haciendo lo mismo, ambas sin `--queue`
y por tanto compitiendo por `default`, con `--tries` distinto (2 y 3): los
reintentos de un job dependían de cuál lo tomara. Quedó
`brynex-worker.conf` como única definición (`--tries=3`, `numprocs=2` para
conservar la capacidad, `stopwaitsecs=60` en vez de 3600) y
`brynex-whatsapp.conf` solo con reverb. Respaldos en
`/root/supervisor-backups/`. Ver el docblock del propio `.conf`.

## 7. Logging: `warning` y rotación diaria

`LOG_LEVEL` pasó de `error` a `warning`: con `error` se descartaban en silencio
las 43 llamadas a `Log::warning` del código, entre ellas la de
`RegistroAccesoService::registrar()`, que traga sus errores a propósito.

`LOG_CHANNEL` pasó de `stack` (que en `config/logging.php` lista solo el canal
`single`: un archivo único sin rotación, ya en 13 MB) a **`daily`**, que rota y
retiene 14 días. Consecuencias: los logs se leen en
`storage/logs/laravel-YYYY-MM-DD.log`, el `laravel.log` viejo queda congelado con
la historia previa, y **la historia de más de 14 días se borra sola**.

Revertir: `LOG_CHANNEL=stack` / `LOG_LEVEL=error` y reiniciar supervisor (los
procesos largos tienen la config en memoria).

## 8. Puerto 1433: de todo internet al ISP del dueño

`ufw` tenía `1433/tcp ALLOW IN Anywhere` (IPv4 e IPv6) y el SQL Server recibía
fuerza bruta continua contra `sa`: **459.745** intentos fallidos en 11 días, a
~0,5 por segundo, desde cientos de IPs. Regla vigente:

```
1433/tcp  ALLOW  200.29.0.0/16   # SQL Server solo desde el ISP del dueno
```

Efecto medido: **0 intentos nuevos en los 90 s siguientes**. La versión IPv6 quedó
cerrada por la política `deny (incoming)`. La aplicación no se ve afectada:
conecta por `localhost`, que no pasa por ufw.

Se rompe si se trabaja **desde otra red** (datos móviles, viaje). El síntoma es un
timeout al conectar, no un error de contraseña; el arreglo es añadir esa IP:

```bash
ufw allow from <ip> to any port 1433 proto tcp
```

Respaldos del firewall previo en `/root/ufw-backups/`. Revertir a abierto:
`ufw allow 1433/tcp`.

## 9. El token OAuth del backup offsite a Google Drive expira sin aviso previo

2026-08-11. Alerta por WhatsApp (`whatsapp:alerta-backup`, ver
[AlertaOperativaService.php](../app/Services/AlertaOperativaService.php)) de
`sync-offsite-logs` fallando cada 30 min desde las 11:30. Causa: el token OAuth
del remote `gdrive:` de `rclone` (`/root/.config/rclone/rclone.conf`) venció —
`invalid_grant: maybe token expired?`. Cron relevante (`crontab -u root`):

```
*/30 * * * * /usr/local/bin/sync-offsite-logs.sh >> /var/log/sync-offsite-logs.log 2>&1 || /usr/local/bin/alertar-fallo-backup.sh "sync-offsite-logs"
0 4 * * *    /usr/local/bin/sync-offsite-full.sh  >> /var/log/sync-offsite-full.log  2>&1 || /usr/local/bin/alertar-fallo-backup.sh "sync-offsite-full"
```

**`gdrivecrypt-brynex` y `gdrivecrypt-cf` comparten el mismo token.** Ambos son
remotes tipo *crypt* que envuelven el remote base `gdrive:` (solo cambia la
carpeta destino: "⚠️ NO TOCAR - Backup Brynex" / "⚠️ NO TOCAR - Backup CF").
Reautorizar `gdrive:` una sola vez arregla los dos — no hace falta repetir el
proceso por cada uno.

Cuenta de Google del backup: `seguridadsocial.brygar@gmail.com`.

### Cómo reautorizar

El servidor no tiene navegador, así que `rclone config reconnect gdrive:`
interactivo por SSH es frágil (el paso de pegar el token JSON a mano se rompe
fácil: procesos que quedan `Stopped` por un Ctrl+Z accidental, el JSON
interpretado por bash en vez de por el prompt de rclone, etc.). Camino que sí
funcionó, de punta a punta:

```bash
# En el servidor, en background, con salida a archivo (evita leer un token
# largo de una captura de pantalla — un solo caracter mal transcrito, tipo
# I/l/1 u O/0, da "State did not match"):
nohup rclone config update gdrive token '<pegar aquí el token actual del remote, o cualquier JSON — se descarta y se genera uno nuevo>' \
  > /tmp/rclone_update.log 2>&1 &
disown
cat /tmp/rclone_update.log   # imprime la URL con el ?state=... real
```

Desde la máquina de trabajo (con navegador y `rclone` instalado):

```bash
ssh -f -N -L 53682:localhost:53682 brynex-prod   # túnel al puerto local del rclone del servidor
# abrir en el navegador la URL que imprimió el cat de arriba (http://127.0.0.1:53682/auth?state=...)
# elegir la cuenta seguridadsocial.brygar@gmail.com y aceptar
# la página debe mostrar "Success!" y el proceso del servidor guarda el token solo
```

Verificar y limpiar:

```bash
rclone lsd gdrivecrypt-brynex:
rclone lsd gdrivecrypt-cf:
shred -u /tmp/rclone_update.log   # queda client_secret y el token en texto plano
kill %1                            # cerrar el túnel SSH local
```

## Lo que queda pendiente

- **Cuenta de servicio para `gdrive:`** en vez de OAuth de usuario: el token de
  usuario vence y obliga a este proceso manual; una *service account* con
  acceso delegado a la carpeta no expira sola. Pendiente evaluar si Google
  Workspace de Brygar lo permite.
- **Migrar al túnel SSH** en vez de la regla por rango: es inmune a la IP
  dinámica y deja el puerto cerrado del todo. Implica `DB_HOST=127.0.0.1` en el
  `.env` local y mantener `ssh -L 1433:localhost:1433` mientras se trabaja.
- **`fs.protected_regular`**: al pasar un secreto a otro usuario por un archivo en
  `/tmp`, escribir primero como root y hacer el `chown` **después**. Al revés,
  root recibe `Permission denied` sobre un archivo ajeno en un directorio sticky.
- El `.env` de producción de brynex **no tiene** variables `FINANZAS_*`: esa
  conexión hereda `DB_USERNAME`/`DB_PASSWORD` de los defaults de
  `config/database.php`. Solo el `.env` local las define.
