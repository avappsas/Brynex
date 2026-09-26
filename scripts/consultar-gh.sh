#!/bin/bash
# Lo único que puede hacer la llave del botón «Consultar datos» de GitHub:
# leer, con un SELECT, las bases de las apps.
#
# Va instalado en /usr/local/sbin/consultar-gh (copiado, no enlazado a este
# repositorio: si viviera aquí, el que cambie main cambiaría también qué deja
# hacer la llave). En /root/.ssh/authorized_keys la llave lleva
#
#     restrict,command="/usr/local/sbin/consultar-gh" ssh-ed25519 ... gh-actions-consultar
#
# y así no abre una consola. Lo que GitHub mande llega en SSH_ORIGINAL_COMMAND
# como «<app>», contra una lista fija, y el SELECT por la entrada estándar.
#
# **No puede escribir, aunque el SQL lo intente.** Corre como el rol
# `datos_lectura`, que solo tiene `pg_read_all_data` y transacciones de solo
# lectura por defecto: no es una regla de este script sino un permiso que la
# base niega. Tiene BYPASSRLS porque sin eso las tablas con RLS (LiderApp,
# Bahía) le responden vacías. Entra por el socket local con peer, así que no
# hay clave guardada en ninguna parte.
#
# Lo que devuelve queda en el log de GitHub Actions: quien lo lanza lee el
# resultado y borra el log de esa corrida.
#
# Por ahora las bases en PostgreSQL: liderapp, bahia, avappi. Las de SQL
# Server (Brynex, Cuenta Fácil, MegaTransportes) llegan aparte.
#
# Instalación: ver scripts/CONSULTAR.md.
set -uo pipefail

read -r APP EXTRA <<<"${SSH_ORIGINAL_COMMAND:-}"

falla() { echo "$*" >&2; exit 2; }

[ -z "${EXTRA:-}" ] || falla "Sobran palabras: el comando es solo el nombre de la app; el SQL va por la entrada estándar."

case "${APP:-}" in
  liderapp) BASE=liderapp ;;
  bahia) BASE=bahia ;;
  avappi)
    # Solo el nombre de la base, del final de DATABASE_URL.
    BASE=$(grep -E '^DATABASE_URL=' /var/www/avappi/.env 2>/dev/null | tail -1 \
      | sed -E 's#.*/([A-Za-z0-9_]+)(\?[^"'"'"']*)?["'"'"']?[[:space:]]*$#\1#')
    [[ "$BASE" =~ ^[A-Za-z0-9_]+$ ]] || falla "No pude leer el nombre de la base de Avappi en su .env."
    ;;
  *) falla "App no permitida: '${APP:-}'. Opciones: liderapp bahia avappi" ;;
esac

SQL=$(head -c 20000)
# Los comentarios se quitan y todo queda en un renglón, que es como \copy lo
# exige. Una sola consulta: con punto y coma en medio serían varias.
SQL=$(printf '%s\n' "$SQL" | sed -E 's/--.*$//' | tr '\n\r\t' '   ' \
  | sed -E 's/[[:space:]]+/ /g; s/^ //; s/ $//; s/;$//; s/ $//')
[ -n "$SQL" ] || falla "No llegó ningún SQL."
[[ "$SQL" != *";"* ]] || falla "Una sola consulta, sin punto y coma en medio."
[[ "$SQL" =~ ^([Ss][Ee][Ll][Ee][Cc][Tt]|[Ww][Ii][Tt][Hh])[[:space:]] ]] || falla "Solo SELECT o WITH."
[[ "$SQL" != *"\\"* ]] || falla "Sin barras invertidas: psql las lee como comandos."

echo "app=$APP base=$BASE · $(date '+%F %T %Z')" >&2
# El tope de filas y de tiempo no son de seguridad —el rol ya no escribe—
# sino para no dejar un log de cien megas ni una consulta pegada en la base.
sudo -u datos_lectura psql -X -q -d "$BASE" -v ON_ERROR_STOP=1 \
  -c "SET statement_timeout = '120s'" \
  -c "\\copy (SELECT * FROM ($SQL) AS q LIMIT 20000) TO STDOUT WITH (FORMAT csv, HEADER)"
