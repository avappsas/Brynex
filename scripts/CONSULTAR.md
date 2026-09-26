# Botón «Consultar datos»

Corre un SELECT contra las bases de producción de netcup desde GitHub →
*Actions* → *Consultar datos* → *Run workflow*, y devuelve el resultado en CSV
dentro del log. Lo usa sobre todo un hilo de Claude: lo lanza, lee el
resultado, arma lo que haga falta (una respuesta, un Excel) y borra el log de
esa corrida, porque trae datos personales.

| app | base |
|---|---|
| `liderapp` | `liderapp` (PostgreSQL) |
| `bahia` | `bahia` (PostgreSQL) |
| `avappi` | la de `DATABASE_URL` en `/var/www/avappi/.env` (PostgreSQL) |

Brynex, Cuenta Fácil y MegaTransportes (SQL Server) van aparte.

**Solo lee, y no por una regla del script sino de la base.** La consulta corre
como el rol `datos_lectura`: solo tiene `pg_read_all_data`, sus transacciones
son de solo lectura y no puede crear nada. Tiene `BYPASSRLS` porque sin eso
LiderApp y Bahía, que filtran con RLS, le responderían vacías. Entra por el
socket local con peer (el usuario del sistema `datos_lectura`), así que no hay
ninguna clave guardada.

La llave es aparte de la de desplegar y la de Ver logs, y tiene un `command=`
forzado: no abre consola, y lo único que recibe como comando es el nombre de la
app. El SQL llega por la entrada estándar; el script acepta un solo `SELECT` o
`WITH`, sin punto y coma ni comandos de psql, con tope de 20.000 filas y dos
minutos.

## Instalación en netcup (una sola vez)

Desde la Mac con `ssh netcup`, como root. La llave privada **nunca** se pega en
un chat.

```bash
# 1. El usuario del sistema y el rol de solo lectura
useradd --system --no-create-home --shell /usr/sbin/nologin datos_lectura
sudo -u postgres psql -v ON_ERROR_STOP=1 <<'SQL'
CREATE ROLE datos_lectura LOGIN BYPASSRLS;
GRANT pg_read_all_data TO datos_lectura;
ALTER ROLE datos_lectura SET default_transaction_read_only = on;
SQL
for b in liderapp bahia $(grep -E '^DATABASE_URL=' /var/www/avappi/.env | sed -E 's#.*/([A-Za-z0-9_]+).*#\1#'); do
  sudo -u postgres psql -c "GRANT CONNECT ON DATABASE $b TO datos_lectura"
done

# 2. Instalar el script (copiado, no enlazado al repo)
cd /var/www/brynex && git pull -q   # o desplegar primero
install -m 755 -o root -g root /var/www/brynex/scripts/consultar-gh.sh /usr/local/sbin/consultar-gh

# 3. Probarlo a mano
echo "SELECT count(*) FROM personas" | SSH_ORIGINAL_COMMAND=liderapp /usr/local/sbin/consultar-gh
echo "DELETE FROM personas" | SSH_ORIGINAL_COMMAND=liderapp /usr/local/sbin/consultar-gh           # debe decir "Solo SELECT o WITH"
sudo -u datos_lectura psql -d liderapp -c "UPDATE personas SET alias = alias WHERE false"  # debe fallar: solo lectura

# 4. Crear la llave y autorizarla solo para ese script
ssh-keygen -t ed25519 -N '' -C gh-actions-consultar -f /root/gh-actions-consultar
echo "restrict,command=\"/usr/local/sbin/consultar-gh\" $(cat /root/gh-actions-consultar.pub)" >> /root/.ssh/authorized_keys

# 5. Copiar la privada al secret (se ve una sola vez)
cat /root/gh-actions-consultar
```

Pegar ese contenido en GitHub → brayan3000-gv/Brynex → *Settings* → *Secrets and
variables* → *Actions* → *New repository secret*, con el nombre
`NETCUP_CONSULTAR_KEY`. Después borrarla del servidor:

```bash
shred -u /root/gh-actions-consultar /root/gh-actions-consultar.pub
```

Si `sudo -u datos_lectura psql` dice *Peer authentication failed*, el
`pg_hba.conf` no tiene la línea `local all all peer` de Debian: se agrega
`local all datos_lectura peer` antes de las demás y `systemctl reload postgresql`.

## Para cambiar lo que puede leer

Se edita `scripts/consultar-gh.sh`, se mergea y se repite el paso 2. Mientras
no se reinstale, el servidor sigue con la copia vieja: es a propósito, para
que un cambio en `main` no cambie solo lo que deja hacer la llave.
