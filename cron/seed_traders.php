<?php
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/hl.php';

db_init();

// Discovery: fetch the full perp universe, then recentTrades for each coin.
// Each call returns ~10 trades × 2 users = up to 20 addresses.
// Throttle 100ms between calls → ~230 coins = ~25s runtime. Well under rate limits.
$meta = hl_info(['type' => 'meta']);
$universe = $meta['universe'] ?? [];
$coins = [];
foreach ($universe as $u) {
    if (!empty($u['name']) && empty($u['isDelisted'])) $coins[] = $u['name'];
}
if (!$coins) {
    fwrite(STDERR, "no coins in universe — aborting\n");
    exit(1);
}
echo "universe: " . count($coins) . " coins\n";

$now = time();
$ins = db()->prepare(
    'INSERT INTO traders (address, first_seen, last_active, role) VALUES (?, ?, ?, ?)
     ON CONFLICT(address) DO UPDATE SET last_active = excluded.last_active'
);

$seen = 0;
$processed = 0;
foreach ($coins as $coin) {
    try {
        $trades = hl_info(['type' => 'recentTrades', 'coin' => $coin]);
        foreach ($trades ?: [] as $t) {
            foreach ($t['users'] ?? [] as $addr) {
                $addr = strtolower($addr);
                if (!preg_match('/^0x[0-9a-f]{40}$/', $addr)) continue;
                $ins->execute([$addr, $now, $now, null]);
                $seen++;
            }
        }
        $processed++;
    } catch (Throwable $e) {
        fwrite(STDERR, "fail $coin: " . $e->getMessage() . "\n");
    }
    usleep(100_000);
}

$total = (int)db()->query('SELECT COUNT(*) FROM traders')->fetchColumn();
printf("processed=%d/%d, seen=%d, total_traders=%d\n", $processed, count($coins), $seen, $total);
