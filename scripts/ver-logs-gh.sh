#!/bin/bash
# Lo único que puede hacer la llave del botón «Ver logs» de GitHub: leer.
#
# Va instalado en /usr/local/sbin/ver-logs-gh (copiado, no enlazado a este
# repositorio: si viviera aquí, el que cambie main cambiaría también qué deja
# hacer la llave). En /root/.ssh/authorized_keys la llave lleva
#
#     restrict,command="/usr/local/sbin/ver-logs-gh" ssh-ed25519 ... gh-actions-logs
#
# y así no abre una consola: lo que GitHub mande llega en SSH_ORIGINAL_COMMAND
# como «<app> <tipo> <lineas>» y cada palabra se compara contra una lista fija.
# Nada de lo que llega se ejecuta ni se usa como ruta.
#
# No lee .env ni configuración: solo logs, estado de servicios y del disco.
# Lo que imprime queda en el log de GitHub Actions, así que cada renglón se
# recorta y se le tapan contraseñas, tokens y cadenas largas que parezcan llaves.
#
# Instalación: ver scripts/VER-LOGS.md.
set -uo pipefail

read -r APP TIPO LINEAS EXTRA <<<"${SSH_ORIGINAL_COMMAND:-}"

case "${APP:-}" in
  brynex|cuentafacil|bahia|liderapp|avappi|servidor) ;;
  *) echo "App no permitida: '${APP:-}'. Opciones: brynex cuentafacil bahia liderapp avappi servidor" >&2; exit 2 ;;
esac
case "${TIPO:-errores}" in
  errores|todo) TIPO=${TIPO:-errores} ;;
  *) echo "Tipo no permitido: '$TIPO'. Opciones: errores todo" >&2; exit 2 ;;
esac
LINEAS=${LINEAS:-100}
if ! [[ "$LINEAS" =~ ^[0-9]{1,3}$ ]] || [ "$LINEAS" -lt 1 ] || [ "$LINEAS" -gt 500 ]; then
  echo "Líneas debe ser un número entre 1 y 500." >&2; exit 2
fi
if [ -n "${EXTRA:-}" ]; then
  echo "Sobran palabras en el comando." >&2; exit 2
fi

# Tapa lo que no debe quedar en el log de GitHub y recorta renglones eternos:
# contraseñas y tokens, los valores entre comillas simples de un SQL (ahí
# llegan nombres y cédulas), los textos de un payload de WhatsApp y los
# celulares. Las rutas quedan a la vista, que son las que dicen dónde falló.
limpiar() {
  sed -E \
    -e 's/(Bearer[[:space:]]+)[A-Za-z0-9._~+\/=-]+/\1***/g' \
    -e 's/((pass(word)?|pwd|secret|token|api[_-]?key|authorization|clave)["'"'"']?[[:space:]]*[:=][[:space:]]*["'"'"']?)[^"'"'"'[:space:],&]+/\1***/Ig' \
    -e 's/[A-Za-z0-9_+=-]{40,}/***/g' \
    -e "s/'[^']{3,}'/'…'/g" \
    -e 's/("(text|body|nombre|name|email|to|telefono|celular)":)"[^"]*"/\1"…"/g' \
    -e 's/\b57[0-9]{10}\b|\b3[0-9]{9}\b/[cel]/g' \
  | cut -c1-400
}

titulo() { printf '\n===== %s =====\n' "$*"; }

# Errores de Laravel: el renglón de cabecera de cada error (sin la traza, que
# es larga y repite rutas) de hoy y de ayer.
laravel() {
  local dir=$1
  if [ ! -d "$dir/storage/logs" ]; then
    echo "No encontré $dir/storage/logs"; return
  fi
  local archivos
  # Solo los de Laravel: en storage/logs también escriben los comandos
  # programados (publicaciones-despacho.log...) y son más nuevos casi siempre.
  archivos=$(ls -1t "$dir"/storage/logs/laravel*.log 2>/dev/null | head -2 | tac)
  if [ -z "$archivos" ]; then echo "Sin archivos de log."; return; fi
  titulo "Laravel ($dir/storage/logs) — $TIPO"
  echo "Archivos: $(echo "$archivos" | xargs -n1 basename | xargs)"
  if [ "$TIPO" = errores ]; then
    # shellcheck disable=SC2086
    cat $archivos | grep -E '^\[[0-9-]+ [0-9:]+\] [a-z]+\.(ERROR|CRITICAL|ALERT|EMERGENCY):' \
      | tail -n "$LINEAS" | limpiar
    echo
    echo "Conteo por mensaje (más frecuentes):"
    # shellcheck disable=SC2086
    cat $archivos | grep -E '^\[[0-9-]+ [0-9:]+\] [a-z]+\.(ERROR|CRITICAL|ALERT|EMERGENCY):' \
      | sed -E 's/^\[[^]]+\] //' | cut -c1-160 | sort | uniq -c | sort -rn | head -15 | limpiar
  else
    # shellcheck disable=SC2086
    tail -n "$LINEAS" $(echo "$archivos" | tail -1) | limpiar
  fi
}

# Servicios de systemd: las últimas 24 horas.
diario() {
  local unidad=$1
  titulo "systemd: $unidad ($(systemctl is-active "$unidad" 2>/dev/null)) — $TIPO, últimas 24 h"
  if [ "$TIPO" = errores ]; then
    journalctl -u "$unidad" --since '24 hours ago' --no-pager -o short-iso 2>/dev/null \
      | grep -iE '"level":(50|60)|\berror\b|exception|fatal|unhandled|failed|ECONN|ETIMEDOUT' \
      | tail -n "$LINEAS" | limpiar
  else
    journalctl -u "$unidad" --since '24 hours ago' --no-pager -o short-iso -n "$LINEAS" 2>/dev/null | limpiar
  fi
}

apache() {
  titulo "Apache: errores recientes"
  local f
  for f in $(ls -1t /var/log/apache2/*error*.log 2>/dev/null | head -4); do
    echo "--- $f"
    # Se saca el ruido de los bots que buscan .env, phpinfo y rutas con ../:
    # Apache ya los rechaza y taparían los errores de verdad.
    tail -n 2000 "$f" \
      | grep -viE 'AH01909|AH00558|AH10244|AH01630|AH01276|not found or unable to stat' \
      | tail -n "$LINEAS" | limpiar
  done
}

archivo() {
  local f=$1
  [ -f "$f" ] || return
  titulo "$f"
  tail -n "$LINEAS" "$f" | limpiar
}

echo "Servidor: $(hostname) · $(date '+%F %T %Z') · app=$APP tipo=$TIPO lineas=$LINEAS"

case "$APP" in
  brynex)
    laravel /var/www/brynex
    titulo "Workers (supervisor)"
    supervisorctl status 2>/dev/null | grep -i brynex || echo "(supervisor no respondió)"
    ;;
  cuentafacil)
    encontrado=""
    for d in /var/www/cf /var/www/cuenta_facil /var/www/cuentafacil /var/www/cuenta-facil /var/www/Cuenta_facil; do
      [ -d "$d/storage/logs" ] && { encontrado=$d; break; }
    done
    if [ -n "$encontrado" ]; then laravel "$encontrado"
    else echo "No encontré la carpeta de Cuenta Fácil en /var/www. Hay: $(ls /var/www | xargs)"; fi
    ;;
  bahia)
    diario bahia-api
    [ "$TIPO" = todo ] && { archivo /var/log/backup-bahia.log; archivo /var/log/backup-bahia-frecuente.log; }
    ;;
  liderapp)
    diario liderapp
    if [ "$TIPO" = todo ]; then
      for f in /var/log/liderapp/*.log; do archivo "$f"; done
    fi
    ;;
  avappi)
    # La web y sus dos workers: un lote de envíos o de geo que se cae solo
    # aparece en el suyo, no en el de la web.
    diario avappi
    diario avappi-worker-envios
    diario avappi-worker-geo
    ;;
  servidor)
    titulo "Estado general"
    uptime
    [ -f /var/run/reboot-required ] && echo "AVISO: el servidor pide reinicio (reboot-required)."
    titulo "Disco"
    df -h -x tmpfs -x devtmpfs -x overlay 2>/dev/null
    titulo "Memoria"
    free -m
    titulo "Servicios caídos (systemctl --failed)"
    systemctl --failed --no-legend --no-pager 2>/dev/null || true
    titulo "Servicios de las apps"
    for s in apache2 nginx caddy mssql-server postgresql bahia-api liderapp avappi avappi-worker-envios avappi-worker-geo supervisor; do
      systemctl list-unit-files "$s.service" --no-legend 2>/dev/null | grep -q . \
        && printf '  %-22s %s\n' "$s" "$(systemctl is-active "$s" 2>/dev/null)"
    done
    titulo "Supervisor"
    supervisorctl status 2>/dev/null || echo "(supervisor no respondió)"
    apache
    ;;
esac

# Un grep sin coincidencias no es una falla: «sin errores» es la mejor respuesta.
exit 0
