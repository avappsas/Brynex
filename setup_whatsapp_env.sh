#!/bin/bash
# Script para configurar las variables de entorno en el servidor de Brynex
# Ejecutar como: bash setup_whatsapp_env.sh
# Ubicación: /var/www/brynex/

ENV_FILE="/var/www/brynex/.env"

echo "🔧 Configurando variables de entorno WhatsApp + Reverb..."

# ── Función para set/update variable en .env ─────────────────
set_env() {
    local KEY="$1"
    local VALUE="$2"
    if grep -q "^${KEY}=" "$ENV_FILE"; then
        sed -i "s|^${KEY}=.*|${KEY}=${VALUE}|" "$ENV_FILE"
        echo "  ✏️  Actualizado: ${KEY}"
    else
        echo "${KEY}=${VALUE}" >> "$ENV_FILE"
        echo "  ➕ Agregado: ${KEY}"
    fi
}

# ── WhatsApp Business API — BRYGAR ───────────────────────────
set_env "WHATSAPP_BRYNEX_WABA_ID"         "101366822755652"
set_env "WHATSAPP_BRYNEX_PHONE_NUMBER_ID" "102428932647057"
set_env "WHATSAPP_BRYNEX_TOKEN" "EAAPtPy8uQw4BPJmY7ydjAfl2zsOjPbTVbisIbqBkE3uHHzooyv7JrG2HKoH25I9ZAsZAOxXmi00E8AkCAGU0e4URZBOjbcHMwgCFZAlzEHmkNYViW9YY48f763CNvid7QrZBibCaTTCQkII3qZCC3OZADUt5gqjVICbrWUddUu0rAckUVJDifoX55f8ENizDg6zVwZDZD"
set_env "WHATSAPP_BRYNEX_NUMERO" "+573205400870"
set_env "WHATSAPP_WEBHOOK_VERIFY_TOKEN" "brynex_wh_secret_2026"
set_env "WHATSAPP_APP_SECRET" ""  # Completar con el App Secret de Meta Business

# ── Broadcaster: usar reverb ─────────────────────────────────
set_env "BROADCAST_DRIVER" "reverb"

# ── Laravel Reverb ───────────────────────────────────────────
set_env "REVERB_APP_ID" "330107"
set_env "REVERB_APP_KEY" "vesdxhh3b0tov612lyyp"
set_env "REVERB_APP_SECRET" "jjss7zkwvgq8tw7sarfq"
set_env "REVERB_HOST" "localhost"
set_env "REVERB_PORT" "8080"
set_env "REVERB_SCHEME" "https"

# ── Vite (frontend) ──────────────────────────────────────────
set_env "VITE_REVERB_APP_KEY" '${REVERB_APP_KEY}'
set_env "VITE_REVERB_HOST" '${REVERB_HOST}'
set_env "VITE_REVERB_PORT" '${REVERB_PORT}'
set_env "VITE_REVERB_SCHEME" '${REVERB_SCHEME}'

# ── Queue: cambiar a database para producción ─────────────────
set_env "QUEUE_CONNECTION" "database"

echo ""
echo "✅ Variables configuradas correctamente."
echo ""
echo "🚀 Próximos pasos en el servidor:"
echo "   cd /var/www/brynex"
echo "   php artisan config:clear"
echo "   php artisan reverb:install"
echo "   php artisan queue:table && php artisan migrate"
echo "   # Iniciar Reverb (en background con supervisor):"
echo "   php artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction &"
echo "   # Iniciar Queue worker:"
echo "   php artisan queue:work --daemon &"
