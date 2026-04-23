# Deploy to talkchaintoday.com

Three phases: **(1) VPS**, **(2) Code**, **(3) DNS**. Total time ≈ 20 min.

---

## Phase 1 — Provision a VPS (YOU do this)

**Hetzner CX22** — €4.15/mo, Ubuntu 24.04, Helsinki or Nuremberg.

1. Sign up at https://console.hetzner.cloud
2. New project → Add server → Shared vCPU → **CX22**
3. Image: **Ubuntu 24.04**
4. SSH keys: add your `~/.ssh/id_ed25519.pub` (or create one with `ssh-keygen -t ed25519`)
5. Create → note the **public IPv4** (e.g. `65.21.x.x`)

Test: `ssh root@<IP>` should land you in a shell.

Alternative hosts that work identically: DigitalOcean ($4 droplet), Vultr, OVH.

---

## Phase 2 — Push code + bootstrap the box

On your laptop:

```bash
cd ~/projects/hyperliquid-signals
git init -b main
git add -A
git commit -m "initial"

# Create an empty repo on GitHub named "hyperliquid-signals" (can be private)
gh repo create hyperliquid-signals --private --source=. --push
```

On the VPS (`ssh root@<IP>`):

```bash
# replace ikigaiwif606-acc with your github handle
export REPO=https://github.com/ikigaiwif606-acc/hyperliquid-signals.git
curl -sSL https://raw.githubusercontent.com/ikigaiwif606-acc/hyperliquid-signals/main/deploy/setup.sh | REPO="$REPO" bash
```

This installs PHP + Caddy + SQLite, clones the repo, seeds the DB, sets up systemd timers, starts Caddy. **Caddy will fail to get a cert until DNS points here — that's fine, we fix it in Phase 3.**

Verify:
```bash
systemctl list-timers | grep hl-
sqlite3 /var/www/hyperliquid-signals/data/signals.db 'SELECT COUNT(*) FROM traders'
curl -I http://<IP>       # should 200 OK on the dashboard
```

---

## Phase 3 — Point the domain (YOU do this)

Current state: `talkchaintoday.com` is on **Wix nameservers** (`ns4.wixdns.net` / `ns5.wixdns.net`), registered at **GoDaddy**.

### Option 3a — Keep Wix nameservers, just change A records (fastest)

1. Log into https://www.wix.com → Domains → talkchaintoday.com → **Advanced → DNS Records**
2. Edit the **A record for `@`** → set value to `<VPS_IP>`.
3. Edit the **A record for `www`** (or CNAME) → set to `<VPS_IP>` too. If no www record exists, add one.
4. Delete any conflicting AAAA / Wix-specific records for `@` and `www`.
5. Save. Propagation: 5–30 min (Wix TTL is usually 1 hour).

Test: `dig talkchaintoday.com +short` should return `<VPS_IP>`.

### Option 3b — Move DNS to Cloudflare (recommended long-term)

Better CDN, better analytics, free. Takes ~10 min longer.

1. Sign up at https://dash.cloudflare.com → Add site → `talkchaintoday.com`.
2. Copy the two Cloudflare nameservers they give you (e.g. `aria.ns.cloudflare.com`).
3. Log into GoDaddy → My Products → talkchaintoday.com → DNS → **Change Nameservers** → enter the Cloudflare pair.
4. In Cloudflare: DNS → add A record `@` → `<VPS_IP>` (proxied = orange cloud OK).
5. Add A record `www` → `<VPS_IP>`.
6. Wait for nameserver propagation (5–60 min).

Once DNS resolves to your VPS, Caddy auto-gets a TLS cert on the next request. Visit https://talkchaintoday.com — it should just work.

---

## Ongoing: deploy updates

From your laptop:
```bash
git push
ssh root@<IP> 'cd /var/www/hyperliquid-signals && git pull'
```

That's it. No CI, no Docker, no container registry.

---

## Monitoring

```bash
# tail live logs
ssh root@<IP> 'journalctl -u hl-refresh-positions -f'

# caddy access
ssh root@<IP> 'tail -f /var/log/caddy/talkchaintoday.access.log'

# db size / row counts
ssh root@<IP> 'sqlite3 /var/www/hyperliquid-signals/data/signals.db \
  "SELECT (SELECT COUNT(*) FROM traders) AS t, (SELECT COUNT(*) FROM portfolios) AS p, (SELECT COUNT(*) FROM positions) AS o"'
```

---

## Rollback

If something breaks:
```bash
ssh root@<IP>
cd /var/www/hyperliquid-signals
git log --oneline -10
git reset --hard <good-sha>
systemctl restart caddy
```

If you need to revert DNS: set A records back in Wix to whatever they were, or at the registrar set nameservers back to `ns4.wixdns.net` / `ns5.wixdns.net`.
