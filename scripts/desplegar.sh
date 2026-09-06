#!/usr/bin/env bash
#
# Despliega a brynex.co desde el Mac. El push a GitHub sigue siendo tuyo; esto
# solo lleva a netcup lo que ya esté en origin/main.
#
#   ./scripts/desplegar.sh              # despliega
#   ./scripts/desplegar.sh --dry-run    # solo dice qué se desplegaría
#   ./scripts/desplegar.sh --migrate    # además corre las migraciones nuevas
#
# El trabajo real lo hace scripts/deploy.sh, que se manda por stdin: el servidor
# corre siempre la versión que está en este repo, sin copias desactualizadas.
#
set -euo pipefail

HOST="${BRYNEX_SSH_HOST:-netcup}"
RAMA="${BRYNEX_RAMA:-main}"
AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Avisa si lo que tienes local todavía no está en GitHub: el servidor jala de ahí,
# no de tu Mac, así que un commit sin subir no se despliega.
if git -C "$AQUI/.." rev-parse --git-dir >/dev/null 2>&1; then
    local_head="$(git -C "$AQUI/.." rev-parse "$RAMA" 2>/dev/null || echo '')"
    remoto_head="$(git -C "$AQUI/.." ls-remote origin "$RAMA" 2>/dev/null | cut -f1)"

    if [[ -n "$local_head" && -n "$remoto_head" && "$local_head" != "$remoto_head" ]]; then
        printf '\033[33m!! Tu %s local (%s) no coincide con el de GitHub (%s).\033[0m\n' \
            "$RAMA" "${local_head:0:7}" "${remoto_head:0:7}"
        printf '   Si te falta subir algo: git push origin %s\n\n' "$RAMA"
    fi
fi

# Los argumentos se escapan para que lleguen enteros al bash remoto.
argumentos=""
for arg in "$@"; do
    argumentos+=" $(printf '%q' "$arg")"
done

exec ssh -o ConnectTimeout=20 "$HOST" "bash -s --$argumentos" < "$AQUI/deploy.sh"
