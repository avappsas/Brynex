# Botón «Ver logs»

Lee los logs de producción de netcup desde GitHub → *Actions* → *Ver logs* →
*Run workflow*, sin la Mac. Un hilo de Claude también puede correrlo y leer lo
que devuelve, para diagnosticar desde el celular.

| app | qué muestra |
|---|---|
| `servidor` | disco, memoria, si pide reinicio, servicios caídos, supervisor, errores de Apache |
| `brynex` | errores de Laravel de hoy y ayer (con conteo por mensaje) y los workers |
| `cuentafacil` | lo mismo, del Laravel de Cuenta Fácil |
| `bahia` | `journalctl -u bahia-api` de las últimas 24 h (con `todo`, también los respaldos) |
| `liderapp` | `journalctl -u liderapp` de las últimas 24 h (con `todo`, también `/var/log/liderapp`) |

**Solo lee.** La llave es aparte de la de desplegar y tiene un `command=`
forzado: no abre consola, no lee `.env` y no ejecuta nada de lo que llega.
Cada renglón sale recortado a 400 caracteres y con contraseñas, tokens y
cadenas largas tapadas, porque queda guardado en el log de GitHub Actions.

## Instalación en netcup (una sola vez)

Se hace desde la Mac con `ssh netcup`, como root. La llave privada **nunca**
se pega en un chat.

```bash
# 1. Instalar el script (copiado, no enlazado al repo)
cd /var/www/brynex && git pull -q   # o desplegar primero, para tener scripts/ver-logs-gh.sh
install -m 755 -o root -g root /var/www/brynex/scripts/ver-logs-gh.sh /usr/local/sbin/ver-logs-gh

# 2. Probarlo a mano
SSH_ORIGINAL_COMMAND="servidor errores 20" /usr/local/sbin/ver-logs-gh
SSH_ORIGINAL_COMMAND="brynex errores 20" /usr/local/sbin/ver-logs-gh
SSH_ORIGINAL_COMMAND="rm -rf /" /usr/local/sbin/ver-logs-gh   # debe decir "App no permitida"

# 3. Crear la llave y autorizarla solo para ese script
ssh-keygen -t ed25519 -N '' -C gh-actions-logs -f /root/gh-actions-logs
echo "restrict,command=\"/usr/local/sbin/ver-logs-gh\" $(cat /root/gh-actions-logs.pub)" >> /root/.ssh/authorized_keys

# 4. Copiar la privada al secret (se ve una sola vez)
cat /root/gh-actions-logs
```

Pegar ese contenido en GitHub → brayan3000-gv/Brynex → *Settings* → *Secrets and
variables* → *Actions* → *New repository secret*, con el nombre
`NETCUP_LOGS_KEY`. Después borrarla del servidor:

```bash
shred -u /root/gh-actions-logs /root/gh-actions-logs.pub
```

Si `cuentafacil` dice que no encuentra la carpeta, el script lista lo que hay
en `/var/www`; se agrega esa ruta a la lista del script y se reinstala (paso 1).

## Para cambiar lo que puede leer

Se edita `scripts/ver-logs-gh.sh`, se mergea y se repite el paso 1. Mientras
no se reinstale, el servidor sigue con la copia vieja: es a propósito, para
que un cambio en `main` no cambie solo lo que deja hacer la llave.
