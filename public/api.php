<?php
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';

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

    default:
        http_response_code(400);
        echo json_encode(['error' => 'unknown q']);
}
