# Hyperliquid Signals — Architecture

**Style:** Pieter Levels. One server, one language, SQLite, no build step, ship today.
**Scope v1:** Prove the dashboard works. Two views only: realtime Top Traders + realtime Top 10 Profitable Open Positions. No signals, no monetization, no accounts.

## Philosophy

- **Boring tech wins.** PHP + SQLite + vanilla JS on a single VPS beats any "modern" stack for a solo indie project.
- **No build step.** Edit file, refresh browser. That's the loop.
- **No framework.** Raw PHP templates, htmx for live updates, Tailwind via CDN.
- **One box.** Everything — web, cron, DB — on one Hetzner VPS.
- **Deploy = `git pull`.**
- **Ship v1 in a weekend.** Expand only after it's live and working.

## v1 Scope — Two Views, One Page

**View A: Top Traders (realtime)**
Ranked table of the best traders on Hyperliquid by PnL over selectable window (24h / 7d / 30d). Refreshes every 30s.

**View B: Top 10 Profitable Positions (realtime)**
Currently open positions with the largest **unrealized PnL** across all tracked traders. Refreshes every 15s. Shows: trader address, coin, side, size, entry, mark, unrealized PnL, unrealized %, time held.

That's it. Two views. If these work and feel alive, everything else (signals, trader detail pages, alerts) becomes trivial to add on top.

## The Stack

| Layer | Pick | Why |
|---|---|---|
| Server | **1× Hetzner CX22** (~€4/mo, Ubuntu 24.04) | Cheap, fast, no nonsense |
| Web server | **Caddy** | Auto HTTPS, one-line config |
| Language | **PHP 8.3** | Fast enough, zero cold starts |
| DB | **SQLite** (WAL mode) | One file, no ops |
| Frontend | **HTML + Tailwind CDN + htmx** | Live updates without a JS framework |
| Cron/worker | **PHP CLI + systemd + crontab** | Same language as web, shared DB |
| DNS/CDN | **Cloudflare** (free) | DDoS shield, caching |
| Monitoring | `tail -f error.log` + BetterStack free | Good enough |

**Monthly cost:** ~€4.

## Directory Layout

```
/var/www/hyperliquid-signals/
├── public/
│   ├── index.php        # The one dashboard page
│   ├── api.php          # JSON endpoints for htmx polling
│   └── assets/
├── lib/
│   ├── db.php           # SQLite PDO singleton
│   └── hl.php           # Hyperliquid API client (curl)
├── cron/
│   ├── discover.php     # WS listener → collects addresses
│   ├── refresh_portfolios.php  # Hourly: pull portfolio per trader
│   └── refresh_positions.php   # Every 30s: pull clearinghouseState for top N
├── data/
│   └── signals.db
├── schema.sql
└── Caddyfile
```

## Data Model (SQLite)

```sql
CREATE TABLE traders (
  address TEXT PRIMARY KEY,
  first_seen INTEGER,
  last_active INTEGER,
  role TEXT,                  -- user | vault | subAccount
  label TEXT                  -- optional human tag
);

CREATE TABLE portfolios (
  address TEXT PRIMARY KEY,
  pnl_day REAL, pnl_week REAL, pnl_month REAL, pnl_all REAL,
  vlm_day REAL, vlm_week REAL, vlm_month REAL, vlm_all REAL,
  account_value REAL,
  updated_at INTEGER
);

CREATE TABLE positions (
  address TEXT,
  coin TEXT,
  side TEXT,                  -- long | short
  size REAL,
  entry_px REAL,
  mark_px REAL,
  leverage REAL,
  unrealized_pnl REAL,
  unrealized_pct REAL,
  opened_at INTEGER,
  updated_at INTEGER,
  PRIMARY KEY (address, coin)
);

CREATE INDEX idx_portfolio_pnl_day ON portfolios(pnl_day DESC);
CREATE INDEX idx_portfolio_pnl_week ON portfolios(pnl_week DESC);
CREATE INDEX idx_portfolio_pnl_month ON portfolios(pnl_month DESC);
CREATE INDEX idx_positions_unrealized ON positions(unrealized_pnl DESC);
```

No fills table in v1. No signals table. Add them when we need them.

## Data Flow

```
Hyperliquid API (/info + WS)
         │
         ▼
┌─────────────────────────────────┐
│ cron/discover.php               │  systemd service, WS `trades`
│ → inserts new addresses         │
└─────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│ cron/refresh_portfolios.php     │  hourly
│ POST /info type=portfolio       │
│ → upserts portfolios            │
└─────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│ cron/refresh_positions.php      │  every 30s
│ top N by pnl_day → clearinghouse│
│ → upserts positions             │
└─────────────────────────────────┘
         │
         ▼
      SQLite
         │
         ▼
  public/index.php  ──► browser
         │
   htmx polls api.php every 15–30s
```

## The One Page

Single page, single URL: `/`.

**Top bar** — brand + live pulse dot + window toggle (24h / 7d / 30d).

**Left column — Top Traders**
Rank · trader (truncated 0x) · PnL · volume · win rate · account value. Top 20.

**Right column — Top 10 Profitable Positions**
Rank · trader · coin · side pill · size · entry → mark · unrealized PnL · unrealized % · held for. Sorted by unrealized PnL descending.

Both columns are htmx partials loaded from `/api.php?v=traders` and `/api.php?v=positions`. Polling:
- Traders: every 30s
- Positions: every 15s

## Ranking (v1)

No composite score yet. Just raw `pnl_<window>` descending, with simple filters:
- `role = 'user'` only (no vaults, no sub-accounts in the lead table)
- `vlm_month >= 100_000`
- `account_value >= 10_000`

Keep it dumb. Promote it to a composite score once the feed feels right.

## Top 10 Profitable Positions (v1)

From `positions` table:
```sql
SELECT p.*, t.label
FROM positions p
JOIN traders t USING (address)
WHERE p.unrealized_pnl > 0
ORDER BY p.unrealized_pnl DESC
LIMIT 10;
```

`refresh_positions.php` runs every 30s, pulling `clearinghouseState` for the top ~200 traders by `pnl_month`. This gives a live view of the biggest open winners without having to query every trader every cycle.

## Deployment

```bash
# One-time
ssh root@vps
apt install -y php8.3-cli php8.3-sqlite3 caddy git sqlite3
git clone https://github.com/you/hyperliquid-signals /var/www/hyperliquid-signals
sqlite3 /var/www/hyperliquid-signals/data/signals.db < schema.sql

# Deploy
git push && ssh vps 'cd /var/www/hyperliquid-signals && git pull'
```

Crontab:
```
0 * * * *   php /var/www/hyperliquid-signals/cron/refresh_portfolios.php
* * * * *   php /var/www/hyperliquid-signals/cron/refresh_positions.php   # every 30s via sleep trick or use systemd timer
```

systemd for the WS discovery worker:
```ini
[Service]
ExecStart=/usr/bin/php /var/www/hyperliquid-signals/cron/discover.php
Restart=always
```

## Not in v1 (deliberately deferred)

- Monetization (Stripe, paid tier, alerts)
- Auth / accounts / wallets
- Signals engine & consensus detection
- Trader detail pages
- Coin detail pages
- Email / Telegram alerts
- Historical charts / backtests
- Copy-trade integration
- Mobile app (desktop + responsive is fine)
- Any JS framework

## Weekend Milestones

- **Hour 1–2:** VPS + Caddy + PHP + SQLite; apply schema; `hl.php` can hit `/info type=portfolio`.
- **Hour 3–4:** `refresh_portfolios.php` pulls ~500 seed addresses (from Hyperliquid leaderboard scrape or known whales), populates `portfolios`.
- **Hour 5–6:** `refresh_positions.php` for top 200 → `positions` table.
- **Hour 7–8:** `index.php` + `api.php`; htmx wired; both tables render live.
- **Hour 9:** Polish Tailwind; tighten copy; ship behind Cloudflare.

Ship. Then listen.
