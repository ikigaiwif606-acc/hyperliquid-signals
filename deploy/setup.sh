#!/usr/bin/env bash
# One-shot VPS bootstrap. Run as root on a fresh Ubuntu 24.04 LTS box.
# Usage: curl -sSL .../setup.sh | bash  (or copy-paste)
set -euo pipefail

APP_DIR=/var/www/hyperliquid-signals
REPO=${REPO:-https://github.com/YOUR_USER/hyperliquid-signals.git}

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

echo "==> clone repo"
if [ ! -d "$APP_DIR/.git" ]; then
    git clone "$REPO" "$APP_DIR"
else
    git -C "$APP_DIR" pull
fi
mkdir -p "$APP_DIR/data"
chown -R www-data:www-data "$APP_DIR/data"

echo "==> init db"
sudo -u www-data php "$APP_DIR/cron/seed_traders.php" || true
sudo -u www-data php "$APP_DIR/cron/refresh_portfolios.php" || true
sudo -u www-data php "$APP_DIR/cron/refresh_positions.php" || true

echo "==> caddy"
cp "$APP_DIR/deploy/Caddyfile" /etc/caddy/Caddyfile
systemctl enable --now caddy
systemctl reload caddy

echo "==> systemd timers"
cp "$APP_DIR/deploy/hl-refresh-portfolios.service" /etc/systemd/system/
cp "$APP_DIR/deploy/hl-refresh-portfolios.timer"   /etc/systemd/system/
cp "$APP_DIR/deploy/hl-refresh-positions.service"  /etc/systemd/system/
cp "$APP_DIR/deploy/hl-refresh-positions.timer"    /etc/systemd/system/
cp "$APP_DIR/deploy/hl-seed-traders.service"       /etc/systemd/system/
cp "$APP_DIR/deploy/hl-seed-traders.timer"         /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now hl-refresh-portfolios.timer
systemctl enable --now hl-refresh-positions.timer
systemctl enable --now hl-seed-traders.timer

echo
echo "==> DONE"
echo "Visit https://talkchaintoday.com (DNS must already point here)"
echo "Check timers: systemctl list-timers | grep hl-"
echo "Logs:          journalctl -u hl-refresh-positions -f"
