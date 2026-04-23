<?php
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/hl.php';

db_init();

// Take top ~200 traders by 30d PnL. If we don't have enough ranked yet, fall back to everyone.
$rows = db()->query('
    SELECT t.address FROM traders t
    LEFT JOIN portfolios p ON p.address = t.address
    ORDER BY COALESCE(p.pnl_month, 0) DESC
    LIMIT 200
')->fetchAll(PDO::FETCH_COLUMN);

if (!$rows) {
    fwrite(STDERR, "no traders — seed first\n");
    exit(1);
}

$now = time();
$ins = db()->prepare('
    INSERT INTO positions (address, coin, side, size, entry_px, mark_px, leverage,
                           unrealized_pnl, unrealized_pct, opened_at, updated_at)
    VALUES (:a, :c, :s, :sz, :e, :m, :l, :u, :up, :o, :t)
    ON CONFLICT(address, coin) DO UPDATE SET
        side=excluded.side, size=excluded.size,
        entry_px=excluded.entry_px, mark_px=excluded.mark_px,
        leverage=excluded.leverage,
        unrealized_pnl=excluded.unrealized_pnl,
        unrealized_pct=excluded.unrealized_pct,
        updated_at=excluded.updated_at
');
$del = db()->prepare('DELETE FROM positions WHERE address = ? AND updated_at < ?');

$ok = $fail = $pos_count = 0;
foreach ($rows as $addr) {
    try {
        $state = hl_clearinghouse($addr);
        $asset_positions = $state['assetPositions'] ?? [];
        foreach ($asset_positions as $ap) {
            $pos = $ap['position'] ?? null;
            if (!$pos) continue;
            $szi = (float)($pos['szi'] ?? 0);
            if ($szi == 0.0) continue;
            $coin = (string)($pos['coin'] ?? '');
            $entry = (float)($pos['entryPx'] ?? 0);
            $upnl = (float)($pos['unrealizedPnl'] ?? 0);
            $lev_obj = $pos['leverage'] ?? [];
            $lev = (float)($lev_obj['value'] ?? 1);
            $pos_value = (float)($pos['positionValue'] ?? 0);
            $mark = $szi != 0 ? $pos_value / abs($szi) : $entry;
            $margin_used = (float)($pos['marginUsed'] ?? 0);
            $upnl_pct = $margin_used > 0 ? ($upnl / $margin_used) * 100 : 0;
            $ins->execute([
                ':a' => $addr, ':c' => $coin,
                ':s' => $szi > 0 ? 'long' : 'short',
                ':sz' => abs($szi), ':e' => $entry, ':m' => $mark, ':l' => $lev,
                ':u' => $upnl, ':up' => $upnl_pct,
                ':o' => null, ':t' => $now,
            ]);
            $pos_count++;
        }
        // purge closed positions for this address
        $del->execute([$addr, $now]);
        $ok++;
        usleep(100_000);
    } catch (Throwable $e) {
        $fail++;
        fwrite(STDERR, "fail $addr: " . $e->getMessage() . "\n");
    }
}
printf("positions refreshed: traders ok=%d fail=%d open_positions=%d\n", $ok, $fail, $pos_count);
