#!/usr/bin/env bash
# One-shot VPS bootstrap. Run as root on a fresh Ubuntu 24.04 LTS box.
# Usage:  cd /var/www/hyperliquid-signals && bash deploy/setup.sh
set -euo pipefail

APP_DIR=/var/www/hyperliquid-signals

if [ "$(pwd)" != "$APP_DIR" ]; then
    echo "run this from inside $APP_DIR (clone the repo there first)"
    exit 1
fi

echo "==> apt install"
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y \
    curl git debian-keyring debian-archive-keyring apt-transport-https \
    php8.3-cli php8.3-fpm php8.3-sqlite3 php8.3-curl \
    sqlite3 ca-certificates ufw

echo "==> install Caddy"
if ! command -v caddy >/dev/null; then
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
    apt-get update && apt-get install -y caddy
fi

echo "==> firewall"
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "==> permissions"
mkdir -p "$APP_DIR/data" /var/log/caddy
chown -R www-data:www-data "$APP_DIR/data"

echo "==> seed db"
sudo -u www-data php "$APP_DIR/cron/seed_traders.php" || true
sudo -u www-data php "$APP_DIR/cron/refresh_portfolios.php" || true
sudo -u www-data php "$APP_DIR/cron/refresh_positions.php" || true

echo "==> caddy"
cp "$APP_DIR/deploy/Caddyfile" /etc/caddy/Caddyfile
systemctl enable --now caddy
systemctl reload caddy

echo "==> systemd timers"
cp "$APP_DIR/deploy/"hl-*.service /etc/systemd/system/
cp "$APP_DIR/deploy/"hl-*.timer   /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now hl-refresh-portfolios.timer
systemctl enable --now hl-refresh-positions.timer
systemctl enable --now hl-seed-traders.timer

echo
echo "==> DONE"
echo "Visit https://talkchaintoday.com (DNS must already point here)"
echo "Check timers: systemctl list-timers | grep hl-"
echo "Logs:         journalctl -u hl-refresh-positions -f"
