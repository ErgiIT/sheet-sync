#!/usr/bin/env bash
#
# Sets up Sheet Sync on a fresh Ubuntu 22.04 / 24.04 server:
# PHP 8.3 + extensions, Composer, MySQL, Node 22, nginx, a supervisor queue worker and the scheduler cron.
#
#   sudo ./scripts/setup-ubuntu.sh --domain sheets.example.com --admin-email me@example.com
#
# Safe to re-run: existing .env values (DB password, APP_KEY, admin password) are kept.
# Every option can also be passed as an environment variable of the same name (e.g. DOMAIN=...).

set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
DOMAIN="${DOMAIN:-_}"
APP_TIMEZONE="${APP_TIMEZONE:-UTC}"
PHP_VERSION="${PHP_VERSION:-8.3}"
NODE_MAJOR="${NODE_MAJOR:-22}"
DB_NAME="${DB_NAME:-sheet_sync}"
DB_USER="${DB_USER:-sheet_sync}"
DB_PASSWORD="${DB_PASSWORD:-}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
GOOGLE_KEY_FILE="${GOOGLE_KEY_FILE:-}"
GOOGLE_API_KEY="${GOOGLE_API_KEY:-}"
SYNC_TIME="${SYNC_TIME:-02:00}"
WEB_USER="www-data"
CREDENTIALS_FILE="/root/sheet-sync-credentials.txt"

usage() {
    cat <<EOF
Usage: sudo $0 [options]

  --app-dir DIR            Project directory (default: the repo this script is in)
  --domain NAME            nginx server_name (default: _ = any host)
  --timezone TZ            APP_TIMEZONE, also used for the daily sync time (default: UTC)
  --sync-time HH:MM        Daily sync time (default: 02:00)
  --db-name NAME           MySQL database (default: sheet_sync)
  --db-user NAME           MySQL user (default: sheet_sync)
  --db-password PASS       MySQL password (default: generated)
  --admin-email EMAIL      UI login (default: admin@example.com)
  --admin-password PASS    UI password (default: generated)
  --google-key FILE        Service account JSON key to install
  --google-api-key KEY     API key, for public sheets only
  -h, --help               Show this help
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-dir) APP_DIR="$2"; shift 2 ;;
        --domain) DOMAIN="$2"; shift 2 ;;
        --timezone) APP_TIMEZONE="$2"; shift 2 ;;
        --sync-time) SYNC_TIME="$2"; shift 2 ;;
        --db-name) DB_NAME="$2"; shift 2 ;;
        --db-user) DB_USER="$2"; shift 2 ;;
        --db-password) DB_PASSWORD="$2"; shift 2 ;;
        --admin-email) ADMIN_EMAIL="$2"; shift 2 ;;
        --admin-password) ADMIN_PASSWORD="$2"; shift 2 ;;
        --google-key) GOOGLE_KEY_FILE="$2"; shift 2 ;;
        --google-api-key) GOOGLE_API_KEY="$2"; shift 2 ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage; exit 1 ;;
    esac
done

step() { echo -e "\n\033[1;34m==> $*\033[0m"; }
fail() { echo -e "\033[1;31m$*\033[0m" >&2; exit 1; }
random_secret() { openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c "${1:-24}"; }

[[ $EUID -eq 0 ]] || fail "Run as root: sudo $0"
[[ -f "$APP_DIR/artisan" ]] || fail "$APP_DIR does not look like the project (no artisan file). Use --app-dir."
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ && "$DB_USER" =~ ^[A-Za-z0-9_]+$ ]] || fail "DB name and user may only contain letters, digits and _."
[[ -z "$GOOGLE_KEY_FILE" || -r "$GOOGLE_KEY_FILE" ]] || fail "Cannot read $GOOGLE_KEY_FILE."

. /etc/os-release
[[ "${ID:-}" == "ubuntu" ]] || echo "Warning: written for Ubuntu, detected ${PRETTY_NAME:-unknown}." >&2

export DEBIAN_FRONTEND=noninteractive
cd "$APP_DIR"

# .env helpers. Values are written double-quoted; check_secret keeps out the characters dotenv would interpret.
# env_get prints nothing for a missing key: with pipefail, grep's no-match status would otherwise end the script.
env_get() { { grep -E "^$1=" .env 2>/dev/null || true; } | tail -n1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/'; }
env_set() {
    KEY="$1" VALUE="$2" awk '
        BEGIN { line = ENVIRON["KEY"] "=\"" ENVIRON["VALUE"] "\"" }
        $0 ~ "^#? ?" ENVIRON["KEY"] "=" { if (!done) print line; done = 1; next }
        { print }
        END { if (!done) print line }
    ' .env > .env.tmp && cat .env.tmp > .env && rm -f .env.tmp
}
check_secret() { [[ "$2" != *[\"\'\\\$\`]* ]] || fail "$1 may not contain quotes, backslashes, \$ or backticks."; }

check_secret "--db-password" "$DB_PASSWORD"
check_secret "--admin-password" "$ADMIN_PASSWORD"

step "Installing system packages"
apt-get update -q
apt-get install -yq software-properties-common ca-certificates curl gnupg unzip git openssl
if ! apt-cache show "php${PHP_VERSION}-fpm" >/dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -q
fi
apt-get install -yq nginx mysql-server supervisor \
    "php${PHP_VERSION}-cli" "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-intl"
update-alternatives --set php "/usr/bin/php${PHP_VERSION}" >/dev/null 2>&1 || true

step "Installing Node.js ${NODE_MAJOR}"
if ! command -v node >/dev/null || [[ "$(node -v | cut -d. -f1 | tr -d v)" -lt "$NODE_MAJOR" ]]; then
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
    apt-get install -yq nodejs
fi

step "Installing Composer"
if ! command -v composer >/dev/null; then
    expected="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    [[ "$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")" == "$expected" ]] || fail "Composer installer checksum mismatch."
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

step "Preparing .env"
[[ -f .env ]] || cp .env.example .env
DB_PASSWORD="${DB_PASSWORD:-$(env_get DB_PASSWORD)}"
DB_PASSWORD="${DB_PASSWORD:-$(random_secret 32)}"
EXISTING_ADMIN_PASSWORD="$(env_get ADMIN_PASSWORD)"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-${EXISTING_ADMIN_PASSWORD:-$(random_secret 16)}}"
APP_URL="http://${DOMAIN}"
[[ "$DOMAIN" == "_" ]] && APP_URL="http://$(hostname -I | awk '{print $1}')"

env_set APP_ENV production
env_set APP_DEBUG false
env_set APP_URL "$APP_URL"
env_set APP_TIMEZONE "$APP_TIMEZONE"
env_set LOG_LEVEL warning
env_set DB_CONNECTION mysql
env_set DB_HOST localhost
env_set DB_PORT 3306
env_set DB_DATABASE "$DB_NAME"
env_set DB_USERNAME "$DB_USER"
env_set DB_PASSWORD "$DB_PASSWORD"
env_set QUEUE_CONNECTION database
env_set DB_QUEUE_RETRY_AFTER 1900
env_set ADMIN_EMAIL "$ADMIN_EMAIL"
env_set ADMIN_PASSWORD "$ADMIN_PASSWORD"
env_set SHEETS_SYNC_TIME "$SYNC_TIME"
[[ -n "$GOOGLE_API_KEY" ]] && env_set GOOGLE_API_KEY "$GOOGLE_API_KEY"

step "Creating MySQL database and user"
systemctl enable --now mysql
mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

step "Installing PHP and JS dependencies"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
npm ci --no-audit --no-fund
npm run build

if [[ -n "$GOOGLE_KEY_FILE" ]]; then
    step "Installing Google service account key"
    install -D -m 640 -o root -g "$WEB_USER" "$GOOGLE_KEY_FILE" storage/app/private/google/service-account.json
    env_set GOOGLE_SERVICE_ACCOUNT_JSON storage/app/private/google/service-account.json
fi

step "Setting up the application"
[[ -n "$(env_get APP_KEY)" ]] || php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan optimize

chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
chown root:"$WEB_USER" .env
chmod 640 .env
# The recursive chown above hands storage to the web user; the Google key goes back to read-only for it.
if [[ -f storage/app/private/google/service-account.json ]]; then
    chown root:"$WEB_USER" storage/app/private/google storage/app/private/google/service-account.json
    chmod 750 storage/app/private/google
    chmod 640 storage/app/private/google/service-account.json
fi

step "Configuring nginx"
cat > /etc/nginx/sites-available/sheet-sync <<NGINX
server {
    listen 80;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;
    client_max_body_size 5m;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
ln -sf /etc/nginx/sites-available/sheet-sync /etc/nginx/sites-enabled/sheet-sync
[[ "$DOMAIN" == "_" ]] && rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now "php${PHP_VERSION}-fpm" nginx
systemctl reload nginx

step "Configuring the queue worker"
cat > /etc/supervisor/conf.d/sheet-sync-worker.conf <<CONF
[program:sheet-sync-worker]
command=/usr/bin/php ${APP_DIR}/artisan queue:work --tries=1 --timeout=1800 --sleep=3 --max-time=3600
directory=${APP_DIR}
user=${WEB_USER}
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=1860
redirect_stderr=true
stdout_logfile=${APP_DIR}/storage/logs/worker.log
CONF
systemctl enable --now supervisor
# systemd reports supervisor started before its control socket exists; wait until supervisorctl can connect.
for _ in {1..30}; do supervisorctl pid >/dev/null 2>&1 && break; sleep 1; done
supervisorctl pid >/dev/null 2>&1 || fail "supervisor did not start; check: journalctl -u supervisor"
supervisorctl reread
supervisorctl update
supervisorctl restart sheet-sync-worker

step "Configuring the scheduler"
cat > /etc/cron.d/sheet-sync <<CRON
* * * * * ${WEB_USER} cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/sheet-sync

umask 077
cat > "$CREDENTIALS_FILE" <<CREDS
Sheet Sync — generated $(date -Iseconds)
URL:            ${APP_URL}
Admin login:    ${ADMIN_EMAIL}
Admin password: ${ADMIN_PASSWORD}
MySQL:          ${DB_USER}@localhost / ${DB_NAME}
MySQL password: ${DB_PASSWORD}
CREDS

step "Done"
cat <<EOF
Open ${APP_URL} and log in as ${ADMIN_EMAIL}.
Credentials were saved to ${CREDENTIALS_FILE} (root only).

Daily sync runs at ${SYNC_TIME} ${APP_TIMEZONE}. Check it with:
  sudo -u ${WEB_USER} php ${APP_DIR}/artisan schedule:list
EOF

if [[ -z "$GOOGLE_KEY_FILE" && -z "$GOOGLE_API_KEY" && ! -f storage/app/private/google/service-account.json ]]; then
    cat <<EOF

No Google credentials yet. Follow docs/google-api.md, then re-run with
  --google-key /path/to/service-account.json
EOF
fi
