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
