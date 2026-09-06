#!/usr/bin/env bash
#
# Despliegue de BryNex en el servidor.
#
# Este script corre EN netcup, pero no se ejecuta desde una copia guardada allá:
# se envía por stdin desde el Mac (ver scripts/desplegar.sh), así que siempre se
# usa la versión que está en el repo local y nunca se reescribe a sí mismo a la
# mitad de un `git pull`.
#
#   bash deploy.sh [--dry-run] [--migrate] [--reverb] [--dir RUTA] [--branch RAMA]
#
#   --dry-run   Solo muestra qué se desplegaría. No toca nada.
#   --migrate   Corre las migraciones pendientes. Sin esta bandera, si el
#               despliegue trae migraciones nuevas el script se detiene ANTES
#               del pull (la base de datos es la de producción — ver CLAUDE.md).
#   --reverb    Además reinicia el proceso de Reverb (corta los websockets
#               abiertos, por eso no se hace por defecto).
#
set -euo pipefail

APP_DIR="/var/www/brynex"
BRANCH="main"
WEB_USER="www-data"
DRY_RUN=0
CORRER_MIGRACIONES=0
REINICIAR_REVERB=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run) DRY_RUN=1; shift ;;
        --migrate) CORRER_MIGRACIONES=1; shift ;;
        --reverb)  REINICIAR_REVERB=1; shift ;;
        --dir)     APP_DIR="$2"; shift 2 ;;
        --branch)  BRANCH="$2"; shift 2 ;;
        *) echo "Opción desconocida: $1" >&2; exit 2 ;;
    esac
done

WEB_HOME="$(getent passwd "$WEB_USER" | cut -d: -f6)"

titulo() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
aviso()  { printf '\033[33m!! %s\033[0m\n' "$*"; }
error()  { printf '\033[31mXX %s\033[0m\n' "$*" >&2; }

# psysh (tinker) y composer escriben en $HOME/.config, y el home de www-data
# (/var/www) es de root: sin esto, `sudo -u www-data php artisan tinker` muere
# con "Writing to directory /var/www/.config/psysh is not allowed". Se rehace en
# cada despliegue para que el arreglo no dependa de que alguien lo recuerde si
# se reconstruye el servidor. Ningún vhost sirve $WEB_HOME a secas (todos
# apuntan a subdirectorios), así que este directorio no queda expuesto por web.
asegurar_home_web() {
    install -d -o "$WEB_USER" -g "$WEB_USER" -m 750 "$WEB_HOME/.config"
}

# Corre un comando como el usuario de Apache para no dejar archivos de root.
como_web() {
    sudo -u "$WEB_USER" HOME="$WEB_HOME" "$@"
}

# ---------------------------------------------------------------- comprobaciones

[[ -d "$APP_DIR/.git" ]] || { error "$APP_DIR no es un repositorio git."; exit 1; }
cd "$APP_DIR"

if [[ $EUID -ne 0 ]]; then
    error "Hay que correr esto como root: necesita chown y sudo -u $WEB_USER."
    exit 1
fi

rama_actual="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$rama_actual" != "$BRANCH" ]]; then
    error "El servidor está en la rama '$rama_actual' y se esperaba '$BRANCH'."
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    error "El servidor tiene cambios sin commitear. Resuélvelos antes de desplegar:"
    git status --short
    exit 1
fi

titulo "Buscando cambios en origin/$BRANCH"
git fetch origin "$BRANCH" --quiet
commit_antes="$(git rev-parse HEAD)"
pendientes="$(git rev-list --count "HEAD..origin/$BRANCH")"

if [[ "$pendientes" -eq 0 ]]; then
    echo "Ya está al día en $(git rev-parse --short HEAD). No hay nada que desplegar."
    exit 0
fi

echo "$pendientes commit(s) por desplegar:"
git --no-pager log --oneline "HEAD..origin/$BRANCH"

archivos_cambiados="$(git diff --name-only "HEAD..origin/$BRANCH")"
echo
echo "Archivos que cambian:"
echo "$archivos_cambiados" | sed 's/^/  /'

# ------------------------------------------------------- migraciones y librerías

migraciones_nuevas="$(echo "$archivos_cambiados" | grep '^database/migrations/' || true)"
cambio_composer="$(echo "$archivos_cambiados" | grep '^composer\.\(json\|lock\)$' || true)"

if [[ -n "$migraciones_nuevas" ]]; then
    echo
    aviso "Este despliegue trae migraciones:"
    echo "$migraciones_nuevas" | sed 's/^/  /'
    if [[ $CORRER_MIGRACIONES -eq 0 ]]; then
        error "La base de datos es la de producción. Revisa esas migraciones y vuelve a lanzar con --migrate."
        exit 1
    fi
fi

if [[ $DRY_RUN -eq 1 ]]; then
    echo
    echo "(--dry-run: hasta aquí llega, no se tocó nada)"
    exit 0
fi

# --------------------------------------------------------------------- despliegue

titulo "Actualizando el código"
git pull --ff-only origin "$BRANCH"
commit_nuevo="$(git rev-parse HEAD)"

# Si algo falla de aquí en adelante, el código ya quedó actualizado: se avisa
# cómo devolverse en vez de hacerlo solo.
trap 'error "Falló el despliegue. Para devolver el código: cd '"$APP_DIR"' && git reset --hard '"$commit_antes"'"' ERR

asegurar_home_web

titulo "Devolviendo los archivos a $WEB_USER"
# git corre como root, así que lo que escribe queda de root y Apache pierde acceso.
git diff --name-only "$commit_antes" "$commit_nuevo" | tr '\n' '\0' \
    | xargs -0 --no-run-if-empty chown "$WEB_USER":"$WEB_USER"
chown -R "$WEB_USER":"$WEB_USER" .git

if [[ -n "$cambio_composer" ]]; then
    titulo "Instalando dependencias (cambió composer.lock)"
    como_web composer install --no-dev --optimize-autoloader --no-interaction
fi

if [[ $CORRER_MIGRACIONES -eq 1 && -n "$migraciones_nuevas" ]]; then
    titulo "Corriendo migraciones"
    como_web php artisan migrate --force
fi

titulo "Limpiando cachés"
# No se compilan assets: el proyecto no usa @vite en ninguna vista y public/build
# no existe en el servidor. Si algún día se usa, hay que agregar el npm run build.
como_web php artisan config:clear
como_web php artisan route:clear
como_web php artisan view:clear
# Recompilar las vistas de una vez sirve de chequeo: si una quedó con un error de
# sintaxis Blade, el despliegue lo grita ahora y no cuando entre un usuario.
como_web php artisan view:cache

titulo "Reiniciando procesos en segundo plano"
# Los workers tienen el código viejo cargado en memoria hasta que se reinician.
como_web php artisan queue:restart
if [[ $REINICIAR_REVERB -eq 1 ]]; then
    supervisorctl restart brynex-reverb
fi
supervisorctl status | sed 's/^/  /'

# ------------------------------------------------------------------------ cierre

titulo "Listo"
echo "  $(git rev-parse --short "$commit_antes") -> $(git rev-parse --short "$commit_nuevo")"
echo "  $(git --no-pager log --oneline -1)"

log_de_hoy="storage/logs/laravel-$(date +%F).log"
if [[ -f "$log_de_hoy" ]]; then
    recientes="$(grep -c 'production.ERROR' "$log_de_hoy" || true)"
    echo "  Errores en el log de hoy: ${recientes:-0} (revisa $log_de_hoy si el número te extraña)"
fi
