#!/usr/bin/env bash
# ============================================================
# Certus — Script de déploiement production
# Usage : ./deploy.sh [--skip-migrate] [--rollback]
# ============================================================
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG_FILE="${APP_DIR}/storage/logs/deploy_${TIMESTAMP}.log"

log()  { echo "[$(date '+%H:%M:%S')] $*" | tee -a "$LOG_FILE"; }
fail() { log "ERREUR : $*"; exit 1; }

# -- Arguments --------------------------------------------------
SKIP_MIGRATE=false
ROLLBACK=false
for arg in "$@"; do
    case $arg in
        --skip-migrate) SKIP_MIGRATE=true ;;
        --rollback)     ROLLBACK=true ;;
    esac
done

# == ROLLBACK ==================================================
if $ROLLBACK; then
    log "=== ROLLBACK ==="
    git stash
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan cache:clear
    php artisan queue:restart
    log "Rollback terminé — redémarrer manuellement le serveur si nécessaire."
    exit 0
fi

# == DÉPLOIEMENT ===============================================
log "=== Déploiement Certus — ${TIMESTAMP} ==="
cd "$APP_DIR"

# 1. Récupérer le code
log "[1/7] git pull origin main"
git pull origin main >> "$LOG_FILE" 2>&1 || fail "git pull a échoué"

# 2. Dépendances PHP (prod uniquement)
log "[2/7] composer install --no-dev"
composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    >> "$LOG_FILE" 2>&1 || fail "composer install a échoué"

# 3. Caches Laravel
log "[3/7] Caches Laravel"
php artisan config:cache  >> "$LOG_FILE" 2>&1
php artisan route:cache   >> "$LOG_FILE" 2>&1
php artisan view:cache    >> "$LOG_FILE" 2>&1
php artisan event:cache   >> "$LOG_FILE" 2>&1

# 4. Migrations
if ! $SKIP_MIGRATE; then
    log "[4/7] php artisan migrate --force"
    php artisan migrate --force >> "$LOG_FILE" 2>&1 || fail "migrate a échoué"
else
    log "[4/7] Migrations ignorées (--skip-migrate)"
fi

# 5. Permissions storage
log "[5/7] Permissions storage"
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 6. Redémarrer les workers de queue
log "[6/7] Queue restart"
php artisan queue:restart >> "$LOG_FILE" 2>&1

# 7. Health check
log "[7/7] Health check"
php artisan certus:check-health >> "$LOG_FILE" 2>&1 \
    && log "Health check OK" \
    || log "AVERTISSEMENT : health check a signalé des anomalies — voir ${LOG_FILE}"

log "=== Déploiement terminé — journal : ${LOG_FILE} ==="
