# Sacar la aplicación de `sa`

**Estado al 2026-08-08: HECHO. La aplicación ya no usa `sa`.**

`brynex.co` y `cuentafacil.co` conectan con `brynex_app` y `cf_app`
respectivamente, ambos `db_owner` en sus bases, `CHECK_POLICY = ON` y sin roles
de servidor. Verificado en caliente:

```
sqlsrv    -> brynex_app  en BryNex            sysadmin: no
finanzas  -> brynex_app  en BryNex_Finanzas   sysadmin: no
cf        -> cf_app      en Cuenta            sysadmin: no
```

Los respaldos de los `.env` de antes del corte están en `/root/env-backups/`
(600, root, **fuera** de los directorios de los repos) y en
`~/.brynex-env-backups/` en la Mac. Se verificó que la credencial de `sa` del
respaldo conecta, o sea que el rollback de verdad sirve.

Detalle del `.env` de producción de brynex: **no tiene variables `FINANZAS_*`**.
La conexión `finanzas` hereda `DB_USERNAME`/`DB_PASSWORD` por los defaults de
`config/database.php`, así que basta cambiar las dos de `DB_*`. En el `.env`
local sí existen las cuatro y hay que cambiarlas todas.

Aislamiento comprobado con pruebas negativas: `brynex_app` no puede entrar a
`Cuenta`, ni crear logins, ni ejecutar `sp_configure`, ni habilitar
`xp_cmdshell`, ni respaldar bases ajenas. En `sys.sql_logins` ve 2 filas con
**0 hashes legibles**, contra 5 y 5 que ve `sa`.

Cuidado si se vuelve a correr la creación: es idempotente (`IF SUSER_ID(...) IS
NULL`), así que **no** cambia la contraseña de un login que ya existe. Generar
contraseñas nuevas y correrla otra vez deja el archivo de credenciales
desincronizado de la realidad. Para rotar una contraseña hay que usar
`ALTER LOGIN ... WITH PASSWORD`, no volver a ejecutar el script.

## Por qué

La aplicación se conecta al SQL Server con **`sa`**, que es sysadmin y además el
único login SQL del servidor. Verificado el 2026-08-08 con
`SELECT SUSER_SNAME()`: devuelve `sa` tanto en la conexión `sqlsrv` como en
`finanzas`.

Eso importa porque el puerto 1433 está abierto a internet y recibe fuerza bruta
continua contra esa misma cuenta — 458.729 intentos fallidos en 11 días. No hay
evidencia de compromiso (la contraseña no se ha cambiado desde el 2026-04-23),
pero si algún día aciertan, con `sa` se llevan **el servidor entero**: las tres
bases, incluida `BryNex_Finanzas`, más la capacidad de crear logins y de
reactivar `xp_cmdshell` (hoy deshabilitado, pero un sysadmin lo enciende con una
línea de `sp_configure`).

Con un login propio limitado a sus dos bases, el peor caso deja de ser "todo el
servidor" y pasa a ser "las bases que la app ya podía tocar de todos modos".

Esto **no** sustituye cerrar el 1433; son cosas independientes y esta se puede
hacer sin riesgo de perder acceso.

## Qué permisos necesita de verdad

No es un `db_datareader/db_datawriter`: la aplicación hace DDL en caliente, así
que necesita **`db_owner`** en sus dos bases. La evidencia, para que nadie lo
recorte por optimismo y lo descubra semanas después:

| Necesita | Por qué | Dónde |
|---|---|---|
| `CREATE TABLE`, `ALTER` | 243 migraciones, y `php artisan migrate` corre en producción | `database/migrations/` |
| `SELECT ... INTO` | crea tablas de respaldo al vuelo | `app/Console/Commands/CorregirArlLegacyGimave.php:131` |
| `ALTER TABLE ... NOCHECK CONSTRAINT ALL` | desactiva constraints durante la migración legacy | `app/Console/Commands/MigrateLegacyAliado.php:142` |
| `BACKUP DATABASE` | copia manual desde el panel | `app/Http/Controllers/BrynexBackupController.php:153` |
| `VIEW DEFINITION` | 19 consultas a `INFORMATION_SCHEMA` / `sys.tables` | varios |
| DDL sobre `BryNex_Finanzas` | 31 archivos usan la conexión `finanzas`, con migraciones propias | `database/migrations/finanzas/` |

`db_owner` cubre las seis, incluida `BACKUP DATABASE` (no hace falta
`db_backupoperator` aparte).

**Lo que el login nuevo NO podrá hacer, y `sa` sí puede hoy:** entrar a `Cuenta`
(la base de CuentaFácil), crear o modificar logins, cambiar configuración del
servidor, habilitar `xp_cmdshell`, respaldar bases ajenas, ni leer
`sys.sql_logins`. Ninguna de esas cosas las usa la aplicación — se verificó que
no hay una sola consulta a vistas que exijan permisos de servidor
(`sys.dm_*`, `sys.server_*`, `SERVERPROPERTY`).

## Paso 1 — Generar la contraseña (la generas tú, no yo)

```bash
openssl rand -base64 32 | tr -dc 'A-Za-z0-9' | head -c 28; echo
```

Alfanumérica a propósito: `base64` puede soltar `=`, `+` o `/`, que obligan a
entrecomillar en el `.env` y a escapar en el SQL. Con 28 caracteres alfanuméricos
no hay nada que escapar en ninguno de los dos sitios.

Guárdala en tu gestor de contraseñas antes de seguir. En los pasos siguientes
sustituye `PON_AQUI_LA_CONTRASEÑA` por ella.

## Paso 2 — Crear el login (aditivo: no toca `sa` ni la app en marcha)

```sql
-- Ejecutar como sa. No modifica nada existente: solo agrega.
USE master;
GO

CREATE LOGIN brynex_app
    WITH PASSWORD  = 'PON_AQUI_LA_CONTRASEÑA',
         CHECK_POLICY = ON,          -- exige contraseña fuerte
         DEFAULT_DATABASE = BryNex;
GO

USE BryNex;
GO
CREATE USER brynex_app FOR LOGIN brynex_app WITH DEFAULT_SCHEMA = dbo;
ALTER ROLE db_owner ADD MEMBER brynex_app;
GO

USE BryNex_Finanzas;
GO
CREATE USER brynex_app FOR LOGIN brynex_app WITH DEFAULT_SCHEMA = dbo;
ALTER ROLE db_owner ADD MEMBER brynex_app;
GO

-- Y el de CuentaFácil, que tiene el mismo problema:
USE master;
GO
CREATE LOGIN cf_app
    WITH PASSWORD  = 'OTRA_CONTRASEÑA_DISTINTA',
         CHECK_POLICY = ON,
         DEFAULT_DATABASE = Cuenta;
GO
USE Cuenta;
GO
CREATE USER cf_app FOR LOGIN cf_app WITH DEFAULT_SCHEMA = dbo;
ALTER ROLE db_owner ADD MEMBER cf_app;
GO
```

Contraseñas **distintas** para cada uno: si no, comprometer cf da acceso a BryNex
y el aislamiento no sirve de nada.

Cómo ejecutarlo sin dejar la contraseña en el historial del shell — `sqlcmd` pide
la de `sa` por consola con `-P` vacío, o mejor, pegar el script en una sesión
interactiva:

```bash
ssh brynex-prod
/opt/mssql-tools18/bin/sqlcmd -S localhost -U SA -C -i /ruta/al/script.sql
```

## Paso 3 — Verificar ANTES de cortar

Aquí está la red de seguridad. El comando prueba el login nuevo **sin tocar el
`.env`**:

```bash
php artisan db:verificar-permisos --conexion=sqlsrv   --usuario=brynex_app --ddl
php artisan db:verificar-permisos --conexion=finanzas --usuario=brynex_app --ddl
```

Pide la contraseña por consola. **No uses `--password`**: queda en el historial y,
peor, en `ps`, donde cualquier usuario local la ve mientras corre. Para
automatizarlo sin ese riesgo, el comando toma `DB_VERIFY_PASSWORD` del entorno:

```bash
DB_VERIFY_PASSWORD="$(cat /ruta/segura)" php artisan db:verificar-permisos \
    --conexion=sqlsrv --usuario=brynex_app --ddl
```

Con `--ddl` crea, altera, escribe y borra una tabla `_zz_` propia: prueba de
verdad, sin tocar datos reales, y limpia siempre.

`sqlcmd` tiene su equivalente, **`SQLCMDPASSWORD`**, y por lo mismo conviene
usarlo en vez de `-P`. El binario está en
`/opt/mssql-tools18/bin/sqlcmd` (v18, necesita `-C` para confiar en el
certificado).

Debe decir **"Todo en orden"** y, esta vez, `sysadmin: no`. Si algo sale `FALTA`,
no sigas: falta un permiso y el corte dejaría la app rota de una forma que no se
ve al conectar, sino la próxima vez que corra una migración.

## Paso 4 — El corte

```bash
# brynex
DB_USERNAME=brynex_app
DB_PASSWORD=<la contraseña>
FINANZAS_DB_USERNAME=brynex_app
FINANZAS_DB_PASSWORD=<la misma>

# cf
DB_USERNAME=cf_app
DB_PASSWORD=<la de cf>
```

Hay que cambiarlo en **dos sitios**: `/var/www/brynex/.env` y `/var/www/cf/.env`
en el servidor, y también tu `.env` local — el de tu Mac apunta a la misma base
de producción.

No hace falta `config:clear`: no hay `bootstrap/cache/config.php`, así que el
`.env` se relee en cada request. Pero **sí hay que reiniciar los procesos
largos**, que tienen la configuración en memoria y seguirían usando `sa` hasta
reciclarse:

```bash
ssh brynex-prod 'supervisorctl restart brynex-queue brynex-worker: brynex-reverb'
```

Verificación después del corte:

```bash
php artisan db:verificar-permisos --conexion=sqlsrv      # debe decir sysadmin: no
ssh brynex-prod 'supervisorctl status'
curl -s -o /dev/null -w "%{http_code}\n" https://brynex.co
```

## Rollback

Devolver `DB_USERNAME`/`DB_PASSWORD` a `SA` en los `.env` y reiniciar supervisor.
Vuelve a funcionar de inmediato: el paso 2 no quita nada a `sa`, solo agrega
logins nuevos. Si quieres deshacerlo del todo:

```sql
USE BryNex;           DROP USER brynex_app;
USE BryNex_Finanzas;  DROP USER brynex_app;
USE master;           DROP LOGIN brynex_app;
```

## Lo que sigue usando `sa` a propósito

- Los cuatro scripts de backup del cron de root (`-U SA` / `-U sa` en
  `/usr/local/bin/backup-*.sh`): respaldar bases ajenas al login de la app es
  justamente lo que no queremos darle a la app.
- La administración manual del servidor.

La conexión `sqlsrv_legacy` no entra en esto: apunta a **otro** servidor
(`200.29.120.228:1533`) con el usuario `Brygar`.

## Registro de logins exitosos (hecho el 2026-08-08)

Antes solo se registraban los fallos, así que un acceso exitoso no dejaba rastro.
Ahora hay cobertura de los dos lados:

- **Fallos** → siguen en el errorlog de SQL Server, como siempre
  (`AuditLevel = 2`, que significa *solo fallos*: los valores son `1` éxitos,
  `2` fallos, `3` ambos — es fácil leerlos al revés).
- **Éxitos** → `AuditoriaLoginsExitosos`, un SQL Server Audit que escribe en
  `/var/opt/mssql/audit/`.

No se usó la vía clásica de subir `AuditLevel` a 3 porque **exige reiniciar el
servicio SQL Server** (downtime de la base para las dos webs) y habría metido los
éxitos en el errorlog, que ya arrastra 458.000 líneas de fuerza bruta. SQL Server
Audit se activa en caliente y va a un archivo aparte. Funciona en Express.

Configuración, y por qué:

| Ajuste | Valor | Motivo |
|---|---|---|
| `ON_FAILURE` | `CONTINUE` | **No cambiar a SHUTDOWN.** Con eso, un disco lleno tumbaría el servidor entero. Antes perder registros que la base. |
| `MAXSIZE` / `MAX_ROLLOVER_FILES` | 20 MB × 10 | Tope de 200 MB, rotación automática, sin cron que limpie. |
| `WHERE` | excluye `brynex_app` y `cf_app` | Las apps abren una conexión por request: sin filtro serían decenas de miles de líneas de ruido al día y la rotación se comería la historia. Filtrados, **cada línea que queda es interesante**. |

Consultar quién ha entrado:

```sql
SELECT server_principal_name AS login, client_ip AS ip, application_name AS programa,
       COUNT(*) AS veces, MAX(event_time) AS ultimo_utc
FROM sys.fn_get_audit_file('/var/opt/mssql/audit/*.sqlaudit', DEFAULT, DEFAULT)
GROUP BY server_principal_name, client_ip, application_name
ORDER BY veces DESC;
```

**`event_time` viene en UTC**, no en hora de Colombia: un acceso de las 12:50
aparece como 17:50.

Para ver también los logins de las aplicaciones, hay que rehacer el filtro
(`ALTER SERVER AUDIT` con `STATE = OFF`, cambiar el `WHERE`, `STATE = ON`), y
contar con que el archivo crecerá mucho más rápido.
