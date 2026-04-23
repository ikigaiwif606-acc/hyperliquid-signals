<?php
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/hl.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$q = $_GET['q'] ?? '';

switch ($q) {
    case 'traders':
        $window = $_GET['w'] ?? 'day';
        if ($window === 'score') {
            // Composite: PnL 30d weighted by efficiency (PnL/Volume) and consistency (PnL 7d sign)
            // Formula rewards profitable traders with high PnL-per-volume and recent continued edge.
            $sql = "
                SELECT t.address, t.label,
                       p.pnl_month AS pnl,
                       p.vlm_month AS volume,
                       p.account_value,
                       p.pnl_week,
                       (
                         p.pnl_month
                         * (1.0 + 3.0 * (p.pnl_month / NULLIF(p.vlm_month, 0)))
                         * CASE WHEN p.pnl_week > 0 THEN 1.2 ELSE 0.8 END
                       ) AS score
                FROM portfolios p
                JOIN traders t USING (address)
                WHERE COALESCE(t.role, 'user') = 'user'
                  AND p.vlm_month >= 500000
                  AND p.account_value >= 10000
                  AND p.pnl_month > 0
                ORDER BY score DESC
                LIMIT 20
            ";
            echo json_encode(db()->query($sql)->fetchAll());
            break;
        }
        $col = match ($window) {
            'week' => 'pnl_week',
            'month' => 'pnl_month',
            default => 'pnl_day',
        };
        $vcol = match ($window) {
            'week' => 'vlm_week',
            'month' => 'vlm_month',
            default => 'vlm_day',
        };
        $sql = "
            SELECT t.address, t.label,
                   p.$col AS pnl, p.$vcol AS volume, p.account_value
            FROM portfolios p
            JOIN traders t USING (address)
            WHERE COALESCE(t.role, 'user') = 'user'
              AND p.vlm_month >= 100000
              AND p.account_value >= 5000
            ORDER BY p.$col DESC
            LIMIT 20
        ";
        echo json_encode(db()->query($sql)->fetchAll());
        break;

    case 'positions':
        $sql = "
            SELECT p.address, p.coin, p.side, p.size, p.entry_px, p.mark_px,
                   p.leverage, p.unrealized_pnl, p.unrealized_pct, p.updated_at,
                   t.label
            FROM positions p
            JOIN traders t USING (address)
            WHERE p.unrealized_pnl > 0
            ORDER BY p.unrealized_pnl DESC
            LIMIT 10
        ";
        echo json_encode(db()->query($sql)->fetchAll());
        break;

    case 'stats':
        echo json_encode([
            'traders' => (int)db()->query('SELECT COUNT(*) FROM traders')->fetchColumn(),
            'portfolios' => (int)db()->query('SELECT COUNT(*) FROM portfolios')->fetchColumn(),
            'positions' => (int)db()->query('SELECT COUNT(*) FROM positions')->fetchColumn(),
            'last_portfolio' => (int)db()->query('SELECT MAX(updated_at) FROM portfolios')->fetchColumn(),
            'last_position' => (int)db()->query('SELECT MAX(updated_at) FROM positions')->fetchColumn(),
        ]);
        break;

    case 'signals':
        $sql = "
            SELECT id, created_at, coin, direction, consensus_count, top_n,
                   avg_entry, total_notional, mark_at_signal, status
            FROM signals
            WHERE status = 'active'
            ORDER BY created_at DESC
            LIMIT 20
        ";
        echo json_encode(db()->query($sql)->fetchAll());
        break;

    case 'coin':
        $coin = strtoupper(trim((string)($_GET['c'] ?? '')));
        if (!preg_match('/^[A-Z0-9]{1,20}$/', $coin)) {
            http_response_code(400);
            echo json_encode(['error' => 'bad coin']);
            break;
        }
        // current mark from allMids (cheap call)
        $mids = hl_all_mids();
        $mark = isset($mids[$coin]) ? (float)$mids[$coin] : null;

        // who's long and short, ranked by notional
        $sql = "
            SELECT p.address, p.side, p.size, p.entry_px, p.mark_px,
                   p.leverage, p.unrealized_pnl, p.unrealized_pct, p.updated_at,
                   pt.pnl_month, pt.vlm_month, pt.account_value
            FROM positions p
            LEFT JOIN portfolios pt ON pt.address = p.address
            WHERE p.coin = :c
            ORDER BY p.mark_px * p.size DESC
            LIMIT 100
        ";
        $stmt = db()->prepare($sql);
        $stmt->execute([':c' => $coin]);
        $positions = $stmt->fetchAll();

        // aggregates
        $longs = array_filter($positions, fn($r) => $r['side'] === 'long');
        $shorts = array_filter($positions, fn($r) => $r['side'] === 'short');
        $sumNotional = fn($rows) => array_sum(array_map(fn($r) => (float)$r['mark_px'] * (float)$r['size'], $rows));
        $avgEntry = function ($rows) {
            $wsum = $szsum = 0.0;
            foreach ($rows as $r) {
                $sz = (float)$r['size'];
                $wsum += (float)$r['entry_px'] * $sz;
                $szsum += $sz;
            }
            return $szsum > 0 ? $wsum / $szsum : 0;
        };

        echo json_encode([
            'coin' => $coin,
            'mark' => $mark,
            'long_count' => count($longs),
            'short_count' => count($shorts),
            'long_notional' => $sumNotional($longs),
            'short_notional' => $sumNotional($shorts),
            'long_avg_entry' => $avgEntry($longs),
            'short_avg_entry' => $avgEntry($shorts),
            'positions' => $positions,
        ]);
        break;

    case 'trader':
        $addr = strtolower($_GET['addr'] ?? '');
        if (!preg_match('/^0x[0-9a-f]{40}$/', $addr)) {
            http_response_code(400);
            echo json_encode(['error' => 'bad address']);
            break;
        }

        $profile = db()->prepare('
            SELECT t.address, t.label, t.first_seen, t.last_active, t.role,
                   p.pnl_day, p.pnl_week, p.pnl_month, p.pnl_all,
                   p.vlm_day, p.vlm_week, p.vlm_month, p.vlm_all,
                   p.account_value, p.updated_at AS portfolio_updated_at
            FROM traders t
            LEFT JOIN portfolios p ON p.address = t.address
            WHERE t.address = ?
        ');
        $profile->execute([$addr]);
        $prof = $profile->fetch();
        if (!$prof) {
            http_response_code(404);
            echo json_encode(['error' => 'trader not tracked']);
            break;
        }

        $positions = [];
        $fills = [];
        $error = null;
        try {
            $state = hl_clearinghouse($addr);
            foreach (($state['assetPositions'] ?? []) as $ap) {
                $pos = $ap['position'] ?? null;
                if (!$pos) continue;
                $szi = (float)($pos['szi'] ?? 0);
                if ($szi == 0) continue;
                $pos_value = (float)($pos['positionValue'] ?? 0);
                $upnl = (float)($pos['unrealizedPnl'] ?? 0);
                $margin = (float)($pos['marginUsed'] ?? 0);
                $positions[] = [
                    'coin' => $pos['coin'] ?? '',
                    'side' => $szi > 0 ? 'long' : 'short',
                    'size' => abs($szi),
                    'entry_px' => (float)($pos['entryPx'] ?? 0),
                    'mark_px' => $szi != 0 ? $pos_value / abs($szi) : 0,
                    'leverage' => (float)(($pos['leverage']['value'] ?? 1)),
                    'unrealized_pnl' => $upnl,
                    'unrealized_pct' => $margin > 0 ? ($upnl / $margin * 100) : 0,
                    'notional' => $pos_value,
                ];
            }
            usort($positions, fn($a, $b) => $b['unrealized_pnl'] <=> $a['unrealized_pnl']);

            $raw_fills = hl_info(['type' => 'userFills', 'user' => $addr]) ?: [];
            foreach (array_slice($raw_fills, 0, 100) as $f) {
                $fills[] = [
                    'coin' => $f['coin'] ?? '',
                    'side' => $f['side'] ?? '',
                    'dir' => $f['dir'] ?? '',
                    'px' => (float)($f['px'] ?? 0),
                    'sz' => (float)($f['sz'] ?? 0),
                    'fee' => (float)($f['fee'] ?? 0),
                    'closed_pnl' => (float)($f['closedPnl'] ?? 0),
                    'time' => (int)(($f['time'] ?? 0) / 1000),
                    'hash' => $f['hash'] ?? '',
                ];
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        echo json_encode([
            'profile' => $prof,
            'positions' => $positions,
            'fills' => $fills,
            'error' => $error,
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'unknown q']);
}
