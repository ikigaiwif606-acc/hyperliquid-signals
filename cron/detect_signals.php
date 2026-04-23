<?php
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/hl.php';

db_init();

const TOP_N = 20;
const CONSENSUS_MIN = 8;       // ≥ this many of top-N on same side → signal
const SIGNAL_COOLDOWN_HRS = 6; // don't re-emit same (coin, direction) within this window

// 1. top-N traders by composite score (same formula as api.php)
$top = db()->query("
    SELECT t.address
    FROM portfolios p
    JOIN traders t USING (address)
    WHERE COALESCE(t.role, 'user') = 'user'
      AND p.vlm_month >= 500000
      AND p.account_value >= 10000
      AND p.pnl_month > 0
    ORDER BY (p.pnl_month * (1.0 + 3.0 * (p.pnl_month / NULLIF(p.vlm_month, 0)))
              * CASE WHEN p.pnl_week > 0 THEN 1.2 ELSE 0.8 END) DESC
    LIMIT " . TOP_N
)->fetchAll(PDO::FETCH_COLUMN);

if (count($top) < CONSENSUS_MIN) {
    echo "not enough ranked traders yet (" . count($top) . ")\n";
    exit(0);
}
$placeholders = implode(',', array_fill(0, count($top), '?'));

// 2. aggregate per (coin, side) across top-N
$sql = "
    SELECT coin, side,
           COUNT(*) AS n,
           AVG(entry_px) AS avg_entry,
           SUM(mark_px * size) AS notional
    FROM positions
    WHERE address IN ($placeholders)
    GROUP BY coin, side
    HAVING n >= " . CONSENSUS_MIN . "
    ORDER BY n DESC, notional DESC
";
$stmt = db()->prepare($sql);
$stmt->execute($top);
$candidates = $stmt->fetchAll();

$mids = hl_all_mids();
$now = time();
$cooldown_cutoff = $now - (SIGNAL_COOLDOWN_HRS * 3600);

$ins = db()->prepare('
    INSERT INTO signals (created_at, coin, direction, consensus_count, top_n, avg_entry, total_notional, mark_at_signal, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, "active")
');
$checkRecent = db()->prepare('
    SELECT 1 FROM signals
    WHERE coin = ? AND direction = ? AND created_at > ?
    LIMIT 1
');

$emitted = 0;
foreach ($candidates as $c) {
    $checkRecent->execute([$c['coin'], $c['side'], $cooldown_cutoff]);
    if ($checkRecent->fetchColumn()) continue;   // already emitted recently

    $mark = isset($mids[$c['coin']]) ? (float)$mids[$c['coin']] : null;
    $ins->execute([
        $now, $c['coin'], $c['side'],
        (int)$c['n'], count($top),
        (float)$c['avg_entry'], (float)$c['notional'], $mark,
    ]);
    $emitted++;
    printf("  SIGNAL %s %s · %d/%d traders · notional=%s\n",
        $c['side'] === 'long' ? '🟢' : '🔴',
        $c['coin'] . ' ' . strtoupper($c['side']),
        $c['n'], count($top),
        number_format((float)$c['notional'], 0));
}

// expire signals older than 24h (still keep the row, just mark)
db()->prepare('UPDATE signals SET status = "expired" WHERE status = "active" AND created_at < ?')
    ->execute([$now - 86400]);

$active = (int)db()->query('SELECT COUNT(*) FROM signals WHERE status = "active"')->fetchColumn();
printf("emitted=%d, active_total=%d\n", $emitted, $active);
