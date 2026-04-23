# hl-signals

Realtime top traders + top 10 open-profit positions on Hyperliquid.

**Stack:** PHP 8.3 + SQLite + htmx + Caddy. One VPS, no build step.

## Local dev

```bash
php -r 'require "lib/db.php"; db_init();'
php cron/seed_traders.php        # discover ~100 active addresses
php cron/refresh_portfolios.php  # pull PnL/volume
php cron/refresh_positions.php   # pull open positions
cd public && php -S 127.0.0.1:8787
```

Open http://127.0.0.1:8787

## Deploy

See [deploy/README.md](deploy/README.md).
