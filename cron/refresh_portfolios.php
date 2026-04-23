<?php
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/hl.php';

db_init();

$traders = db()->query('SELECT address FROM traders')->fetchAll(PDO::FETCH_COLUMN);
if (!$traders) {
    fwrite(STDERR, "no traders yet — run seed_traders.php first\n");
    exit(1);
}

$up = db()->prepare('
    INSERT INTO portfolios (address, pnl_day, pnl_week, pnl_month, pnl_all,
                            vlm_day, vlm_week, vlm_month, vlm_all,
                            account_value, updated_at)
    VALUES (:a, :pd, :pw, :pm, :pa, :vd, :vw, :vm, :va, :av, :t)
    ON CONFLICT(address) DO UPDATE SET
        pnl_day=excluded.pnl_day, pnl_week=excluded.pnl_week,
        pnl_month=excluded.pnl_month, pnl_all=excluded.pnl_all,
        vlm_day=excluded.vlm_day, vlm_week=excluded.vlm_week,
        vlm_month=excluded.vlm_month, vlm_all=excluded.vlm_all,
        account_value=excluded.account_value, updated_at=excluded.updated_at
');

$now = time();
$ok = $fail = 0;
foreach ($traders as $addr) {
    try {
        $p = hl_portfolio($addr);
        $up->execute([
            ':a' => $addr,
            ':pd' => $p['pnl_day'], ':pw' => $p['pnl_week'],
            ':pm' => $p['pnl_month'], ':pa' => $p['pnl_all'],
            ':vd' => $p['vlm_day'], ':vw' => $p['vlm_week'],
            ':vm' => $p['vlm_month'], ':va' => $p['vlm_all'],
            ':av' => $p['account_value'], ':t' => $now,
        ]);
        $ok++;
        usleep(100_000); // 10 req/s — well under IP limit
    } catch (Throwable $e) {
        $fail++;
        fwrite(STDERR, "fail $addr: " . $e->getMessage() . "\n");
    }
}
printf("portfolios refreshed: ok=%d fail=%d\n", $ok, $fail);
