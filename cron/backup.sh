#!/usr/bin/env bash
# Nightly SQLite backup. Keeps last 7 snapshots.
set -euo pipefail

DB=/var/www/hyperliquid-signals/data/signals.db
DIR=/var/backups/hl-signals
STAMP=$(date +%Y%m%d-%H%M)

mkdir -p "$DIR"

# .backup is SQLite's safe online backup (not a file copy — handles WAL correctly)
sqlite3 "$DB" ".backup '$DIR/signals-$STAMP.db'"
gzip -f "$DIR/signals-$STAMP.db"

# prune older than 7 days
find "$DIR" -name 'signals-*.db.gz' -mtime +7 -delete

ls -lh "$DIR" | tail -5
