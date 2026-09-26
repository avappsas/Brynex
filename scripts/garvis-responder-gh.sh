#!/usr/bin/env bash
# Se instala como /usr/local/sbin/garvis-responder-gh y es el command= forzado de la
# llave gh-actions-garvis. Lo único que puede hacer esa llave es esto: tomar un texto
# por la entrada estándar y mandárselo a Brayan por WhatsApp con Brynex.
set -euo pipefail

# Tope de tamaño: una respuesta de GARVIS es un WhatsApp, no un archivo.
texto="$(head -c 20000)"

if [ -z "$texto" ]; then
  echo "Sin texto." >&2
  exit 1
fi

cd /var/www/brynex
printf '%s' "$texto" | sudo -u www-data php artisan garvis:responder
