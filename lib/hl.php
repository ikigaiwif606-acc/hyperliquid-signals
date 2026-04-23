<?php
declare(strict_types=1);

const HL_INFO_URL = 'https://api.hyperliquid.xyz/info';

function hl_info(array $body): mixed {
    $ch = curl_init(HL_INFO_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) throw new RuntimeException("hl curl error: $err");
    if ($code !== 200) throw new RuntimeException("hl http $code: " . substr((string)$raw, 0, 200));
    return json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);
}

function hl_portfolio(string $address): array {
    $res = hl_info(['type' => 'portfolio', 'user' => $address]);
    $out = [
        'pnl_day' => 0.0, 'pnl_week' => 0.0, 'pnl_month' => 0.0, 'pnl_all' => 0.0,
        'vlm_day' => 0.0, 'vlm_week' => 0.0, 'vlm_month' => 0.0, 'vlm_all' => 0.0,
        'account_value' => 0.0,
    ];
    if (!is_array($res)) return $out;
    // Response: [[period, {pnlHistory: [[ts, pnlStr], ...], vlm: "...", accountValueHistory: [[ts, eqStr], ...]}], ...]
    // Periods include: day, week, month, allTime (and perp* variants we ignore — totals already include perp+spot)
    $map = ['day' => 'day', 'week' => 'week', 'month' => 'month', 'allTime' => 'all'];
    foreach ($res as $entry) {
        if (!is_array($entry) || count($entry) < 2) continue;
        [$period, $data] = $entry;
        if (!isset($map[$period]) || !is_array($data)) continue;
        $key = $map[$period];
        $pnl_hist = $data['pnlHistory'] ?? [];
        if (is_array($pnl_hist) && !empty($pnl_hist)) {
            $last = end($pnl_hist);
            if (is_array($last) && isset($last[1])) $out["pnl_$key"] = (float)$last[1];
        }
        $out["vlm_$key"] = (float)($data['vlm'] ?? 0);
        if ($key === 'day') {
            $eq_hist = $data['accountValueHistory'] ?? [];
            if (is_array($eq_hist) && !empty($eq_hist)) {
                $last = end($eq_hist);
                if (is_array($last) && isset($last[1])) $out['account_value'] = (float)$last[1];
            }
        }
    }
    return $out;
}

function hl_clearinghouse(string $address): array {
    return hl_info(['type' => 'clearinghouseState', 'user' => $address]) ?: [];
}

function hl_user_role(string $address): string {
    $res = hl_info(['type' => 'userRole', 'user' => $address]);
    if (is_array($res) && isset($res['role'])) return (string)$res['role'];
    return 'missing';
}

function hl_all_mids(): array {
    $res = hl_info(['type' => 'allMids']);
    return is_array($res) ? $res : [];
}
