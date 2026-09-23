#!/usr/bin/env bash
# Amplía el túnel de la oficina para que también llegue a Comfenalco Valle.
# Correr como root:
#
#   ssh netcup 'bash -s' < scripts/tunel-portales/ampliar-comfenalco.sh
#
# El túnel de Nueva EPS (scripts/tunel-nueva-eps/) ya trae al servidor la IP de
# la oficina, que es lo que el WAF de Comfenalco acepta. Aquí solo se le
# permiten al mismo usuario dos puertos más, uno por dominio:
#
#   18444 → virtual.comfenalcovalle.com.co:443   (la Sucursal Virtual)
#   18445 → authcomfeempresasprod.web.app:443    (el login de AuthComfe)
#
# Se mantiene el destino fijo a propósito: con un SOCKS (-R 1080) el servidor
# alcanzaría la red de la oficina y su SQL Server. Así solo llega a esos dos
# sitios, y el TLS sigue siendo de punta a punta con ellos.
set -euo pipefail

USUARIO=tunelnep
PUERTOS="127.0.0.1:18443 127.0.0.1:18444 127.0.0.1:18445"
CONF=/etc/ssh/sshd_config
RESPALDO="$CONF.antes-tunel-comfenalco"

if ! grep -q "Match User $USUARIO" "$CONF"; then
  echo "No está el bloque del túnel: corre antes scripts/tunel-nueva-eps/instalar-servidor.sh" >&2
  exit 1
fi

if grep -q "PermitListen $PUERTOS" "$CONF"; then
  echo "Los puertos de Comfenalco ya estaban permitidos."
  exit 0
fi

cp -a "$CONF" "$RESPALDO"

# Solo la línea PermitListen que está dentro del bloque del túnel.
sed -i "/Match User $USUARIO/,/^# <<< BryNex/ s|^\( *\)PermitListen .*|\1PermitListen $PUERTOS|" "$CONF"

if ! sshd -t; then
  mv "$RESPALDO" "$CONF"
  echo "sshd -t falló: se restauró sshd_config sin cambios." >&2
  exit 1
fi

systemctl reload ssh
echo "Listo. El PC de la oficina ya puede abrir 18444 y 18445:"
grep -n "PermitListen" "$CONF" | tail -1
