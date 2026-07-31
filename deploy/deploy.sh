#!/usr/bin/env bash
#
# Déploiement d'api-bot sur le VPS de prod (nginx + PHP-FPM, sans Docker).
# À lancer depuis le VPS : bash deploy/deploy.sh
#
# Contrat :
#   - s'arrête à la première erreur, aucune étape sautée silencieusement ;
#   - rejouable du début après un plantage (toutes les étapes sont idempotentes) ;
#   - en cas d'échec, la maintenance RESTE ACTIVE (mieux vaut un 503 qu'un site
#     à moitié déployé) — la rouvrir à la main une fois le problème réglé ;
#   - le backup MySQL est vérifié avant de continuer (seul filet contre les
#     migrations irréversibles).
#
# Pré-requis one-shot sur le VPS :
#   - ~/.my.cnf (chmod 600) avec les identifiants MySQL, sections [client]/[mysqldump] ;
#   - sudo sans mot de passe pour "systemctl reload php8.4-fpm" (ou lancer le
#     script depuis une session où sudo est déjà déverrouillé).

set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/var/www/beauce-tigers/api-bot}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups}"
DB_NAME="${DB_NAME:-apibot}"
PHP_FPM="${PHP_FPM:-php8.4-fpm}"

BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_$(date +%F_%H%M%S).sql"

step() { printf '\n==> %s\n' "$*"; }

on_error() {
    printf '\n!!! ÉCHEC du déploiement (étape : %s)\n' "${CURRENT_STEP:-inconnue}" >&2
    printf '!!! La maintenance reste ACTIVE (%s/maintenance.on).\n' "$PROJECT_DIR" >&2
    if [ -f "$BACKUP_FILE" ]; then
        printf '!!! Backup BDD disponible : %s\n' "$BACKUP_FILE" >&2
    fi
    printf '!!! Corriger, puis relancer le script (rejouable du début).\n' >&2
}
trap on_error ERR

cd "$PROJECT_DIR"

# --- 1. Préflight : échouer AVANT de couper le trafic -------------------
CURRENT_STEP="préflight"
step "Préflight"

for bin in git composer php mysqldump; do
    command -v "$bin" >/dev/null || { echo "Binaire manquant : $bin" >&2; exit 1; }
done

[ -f "$HOME/.my.cnf" ] || { echo "$HOME/.my.cnf absent : mysqldump ne pourra pas s'authentifier." >&2; exit 1; }

BRANCH="$(git rev-parse --abbrev-ref HEAD)"
[ "$BRANCH" = "master" ] || { echo "On déploie master, pas '$BRANCH'." >&2; exit 1; }

if [ -n "$(git status --porcelain)" ]; then
    echo "Le working tree n'est pas propre — on ne déploie pas par-dessus des modifs locales :" >&2
    git status --short >&2
    exit 1
fi

# --- 2. Maintenance ON ---------------------------------------------------
CURRENT_STEP="maintenance ON"
step "Maintenance activée"
touch maintenance.on

# --- 3. Backup BDD (vérifié) ----------------------------------------------
CURRENT_STEP="backup BDD"
step "Backup de $DB_NAME vers $BACKUP_FILE"
mkdir -p "$BACKUP_DIR"
mysqldump "$DB_NAME" > "$BACKUP_FILE"

# mysqldump termine toujours par "-- Dump completed" : sa présence garantit
# un dump complet (un simple test "non vide" laisserait passer un dump tronqué).
if ! tail -n 1 "$BACKUP_FILE" | grep -q '^-- Dump completed'; then
    echo "Dump incomplet ou corrompu : $BACKUP_FILE" >&2
    exit 1
fi
ls -lh "$BACKUP_FILE"

# --- 4. Code + dépendances -------------------------------------------------
CURRENT_STEP="mise à jour du code"
step "git pull --ff-only origin master"
git fetch origin
git pull --ff-only origin master

CURRENT_STEP="composer install"
step "composer install (prod)"
composer install --no-dev --optimize-autoloader --no-interaction

# --- 5. Cache (le warmup compile le conteneur : il attrape une config
#        cassée AVANT qu'on ne touche à la base) ---------------------------
CURRENT_STEP="cache"
step "cache:clear + cache:warmup"
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# --- 6. Migrations ---------------------------------------------------------
CURRENT_STEP="migrations"
step "État des migrations"
php bin/console doctrine:migrations:status --env=prod

step "Application des migrations"
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# --- 7. Reload PHP-FPM (purge OPcache) -------------------------------------
CURRENT_STEP="reload PHP-FPM"
step "Reload de $PHP_FPM"
sudo systemctl reload "$PHP_FPM"

# --- 8. Maintenance OFF — uniquement si tout a réussi -----------------------
CURRENT_STEP="maintenance OFF"
rm -f maintenance.on
step "Déploiement OK, trafic rouvert"

echo "Vérifications conseillées :"
echo "  - ouvrir /refresh et contrôler l'affichage"
echo "  - tail -f var/log/prod-$(date +%F).log   # erreurs HTTP"
echo "  - tail -f var/log/app-$(date +%F).log    # activité métier"
