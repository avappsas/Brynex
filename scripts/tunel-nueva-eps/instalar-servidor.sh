#!/usr/bin/env bash
# Túnel de BryNex hacia Nueva EPS — lado del servidor (netcup). Correr como root.
#
#   ssh netcup 'bash -s' < scripts/tunel-nueva-eps/instalar-servidor.sh
#   ssh netcup 'bash -s -- "ssh-ed25519 AAAA... tunel-nueva-eps@PC"' < scripts/tunel-nueva-eps/instalar-servidor.sh
#
# Crea el usuario `tunelnep`, sin consola, que SOLO puede abrir 127.0.0.1:18443
# como túnel inverso (-R). No puede abrir túneles locales (-L) hacia los
# servicios del servidor (SQL Server en 1433, etc.), ni pedir terminal. Las
# restricciones van dos veces: en sshd_config (Match) y en authorized_keys.
#
# Idempotente. Sin argumento solo prepara usuario y sshd; con la llave pública
# del PC, además la instala (reemplaza la anterior).
set -euo pipefail

USUARIO=tunelnep
PUERTO=18443
LLAVE="${1:-}"
CONF=/etc/ssh/sshd_config
MARCA="# >>> BryNex: túnel Nueva EPS"

if ! id "$USUARIO" >/dev/null 2>&1; then
  useradd --system --create-home --shell /usr/sbin/nologin "$USUARIO"
  echo "Usuario $USUARIO creado."
fi

# El Match va al FINAL de sshd_config y no en sshd_config.d/: el Include está
# arriba, y un Match ahí dejaría atadas a este usuario las líneas globales que
# vienen después (PasswordAuthentication no, PermitRootLogin...).
if ! grep -qF "$MARCA" "$CONF"; then
  cp -a "$CONF" "$CONF.antes-tunel-nueva-eps"
  cat >> "$CONF" <<EOF

$MARCA
Match User $USUARIO
    AuthenticationMethods publickey
    PasswordAuthentication no
    KbdInteractiveAuthentication no
    AllowTcpForwarding remote
    PermitListen 127.0.0.1:$PUERTO
    PermitOpen none
    GatewayPorts no
    PermitTTY no
    X11Forwarding no
    AllowAgentForwarding no
    AllowStreamLocalForwarding no
    PermitTunnel no
    ForceCommand /usr/sbin/nologin
    # Libera el puerto rápido si la conexión del PC muere sin cerrar.
    ClientAliveInterval 30
    ClientAliveCountMax 3
# <<< BryNex: túnel Nueva EPS
EOF
  if ! sshd -t; then
    mv "$CONF.antes-tunel-nueva-eps" "$CONF"
    echo "sshd -t falló: se restauró sshd_config sin cambios." >&2
    exit 1
  fi
  systemctl reload ssh
  echo "sshd_config actualizado y recargado."
fi

if [[ -n "$LLAVE" ]]; then
  if ! [[ "$LLAVE" =~ ^ssh-ed25519\ [A-Za-z0-9+/=]+(\ .*)?$ ]]; then
    echo "La llave no parece una llave pública ssh-ed25519." >&2
    exit 1
  fi
  install -d -m 700 -o "$USUARIO" -g "$USUARIO" "/home/$USUARIO/.ssh"
  echo "restrict,port-forwarding,permitlisten=\"127.0.0.1:$PUERTO\",permitopen=\"127.0.0.1:1\" $LLAVE" \
    > "/home/$USUARIO/.ssh/authorized_keys"
  chown "$USUARIO:$USUARIO" "/home/$USUARIO/.ssh/authorized_keys"
  chmod 600 "/home/$USUARIO/.ssh/authorized_keys"
  echo "Llave del PC instalada."
fi

echo "--- Verificación"
echo "root:     $(sshd -T -C user=root,host=x,addr=203.0.113.1 | grep -E '^(passwordauthentication|permitrootlogin|allowtcpforwarding) ' | tr '\n' ' ')"
echo "$USUARIO: $(sshd -T -C user=$USUARIO,host=x,addr=203.0.113.1 | grep -E '^(allowtcpforwarding|permitlisten|permitopen|forcecommand|clientaliveinterval) ' | tr '\n' ' ')"
if ss -ltn | grep -q "127.0.0.1:$PUERTO "; then echo "Túnel: CONECTADO en 127.0.0.1:$PUERTO"; else echo "Túnel: sin conectar"; fi
