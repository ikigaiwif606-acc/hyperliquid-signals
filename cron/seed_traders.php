<?php
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/hl.php';

db_init();

// Discovery via recentTrades: each call returns 10 trades × 2 users = 20 addresses.
// Hit the most active perps so we collect whales, not retail.
$coins = ['BTC', 'ETH', 'SOL', 'HYPE', 'XRP', 'DOGE', 'FARTCOIN', 'PUMP', 'SUI', 'kPEPE', 'WLD', 'AVAX'];

$now = time();
$ins = db()->prepare(
    'INSERT INTO traders (address, first_seen, last_active, role) VALUES (?, ?, ?, ?)
     ON CONFLICT(address) DO UPDATE SET last_active = excluded.last_active'
);

$seen = 0;
foreach ($coins as $coin) {
    try {
        $trades = hl_info(['type' => 'recentTrades', 'coin' => $coin]);
        foreach ($trades as $t) {
            foreach ($t['users'] ?? [] as $addr) {
                $addr = strtolower($addr);
                if (!preg_match('/^0x[0-9a-f]{40}$/', $addr)) continue;
                $ins->execute([$addr, $now, $now, null]);
                $seen++;
            }
        }
        echo "$coin · running total: $seen\n";
        usleep(150_000);
    } catch (Throwable $e) {
        fwrite(STDERR, "fail $coin: " . $e->getMessage() . "\n");
    }
}

$total = (int)db()->query('SELECT COUNT(*) FROM traders')->fetchColumn();
echo "done · traders table: $total unique addresses\n";
