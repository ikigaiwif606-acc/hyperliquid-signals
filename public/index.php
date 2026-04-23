<?php
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';

function short(string $addr): string {
    return strlen($addr) < 10 ? $addr : substr($addr, 0, 6) . '…' . substr($addr, -4);
}
function money(float $n): string {
    $abs = abs($n);
    $sign = $n < 0 ? '-' : ($n > 0 ? '+' : '');
    if ($abs >= 1_000_000) return $sign . '$' . number_format($abs / 1_000_000, 2) . 'M';
    if ($abs >= 1_000) return $sign . '$' . number_format($abs / 1_000, 1) . 'k';
    return $sign . '$' . number_format($abs, 0);
}
function human_age(int $ts): string {
    if ($ts <= 0) return '—';
    $d = time() - $ts;
    if ($d < 60) return $d . 's';
    if ($d < 3600) return floor($d / 60) . 'm';
    if ($d < 86400) return floor($d / 3600) . 'h';
    return floor($d / 86400) . 'd';
}

$window = $_GET['w'] ?? 'day';
if (!in_array($window, ['day', 'week', 'month'], true)) $window = 'day';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HL Signals — realtime top traders on Hyperliquid</title>
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<link rel="alternate icon" href="/assets/favicon.svg">
<meta name="description" content="Realtime top traders and top 10 profitable open positions on Hyperliquid.">
<meta name="theme-color" content="#0a0a0a">
<meta property="og:title" content="HL Signals — realtime top traders on Hyperliquid">
<meta property="og:description" content="Follow the whales. Realtime top traders + top 10 profitable open positions on Hyperliquid.">
<meta property="og:type" content="website">
<meta property="og:url" content="https://talkchaintoday.com/">
<meta property="og:image" content="https://talkchaintoday.com/assets/og-image.svg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="HL Signals">
<meta name="twitter:description" content="Follow the whales. Realtime top traders on Hyperliquid.">
<meta name="twitter:image" content="https://talkchaintoday.com/assets/og-image.svg">
<link rel="stylesheet" href="/assets/tailwind.css">
<script src="https://unpkg.com/htmx.org@2.0.3" integrity="sha384-0895/pl2MU10Hqc6jd4RvrthNlDiE9U1tWmX7WRESftEDRosgxNsQG/Ze9YMRzHq" crossorigin="anonymous"></script>
<style>
  body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; }
  .pulse-dot { animation: pulse 1.6s ease-in-out infinite; }
  @keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: .3 } }
  tbody tr:hover { background: rgba(255,255,255,.025); }
  .up { color: #22c55e; } .down { color: #ef4444; }
</style>
</head>
<body class="bg-[#0a0a0a] text-zinc-100">

<header class="border-b border-zinc-900">
  <div class="max-w-7xl mx-auto px-6 h-14 flex items-center justify-between">
    <a href="/" class="font-black tracking-tight text-base">HL<span class="text-emerald-400">.</span>SIGNALS</a>
    <div class="flex items-center gap-5">
      <div class="inline-flex rounded border border-zinc-800 text-xs overflow-hidden">
        <?php foreach (['day' => '24h', 'week' => '7d', 'month' => '30d'] as $w => $label): ?>
          <a href="?w=<?= $w ?>" class="px-3 py-1.5 <?= $w === $window ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:text-white' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
      <span class="flex items-center gap-2 text-xs text-zinc-500">
        <span class="pulse-dot inline-block w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
        live
      </span>
    </div>
  </div>
</header>

<main class="max-w-7xl mx-auto px-6 py-8 grid grid-cols-1 lg:grid-cols-2 gap-8">

  <section>
    <div class="flex items-baseline justify-between mb-4">
      <h2 class="text-sm font-semibold text-zinc-400 uppercase tracking-wider">Top Traders</h2>
      <span class="text-[10px] text-zinc-600 mono">refresh 30s</span>
    </div>

    <div id="traders"
         hx-get="/api.php?q=traders&w=<?= htmlspecialchars($window) ?>"
         hx-trigger="load, every 30s"
         hx-swap="innerHTML"
         class="border border-zinc-900 rounded min-h-[300px]">
      <div class="p-6 text-zinc-600 text-sm">loading…</div>
    </div>
  </section>

  <section>
    <div class="flex items-baseline justify-between mb-4">
      <h2 class="text-sm font-semibold text-zinc-400 uppercase tracking-wider">
        Top 10 Profitable Positions
        <span class="text-emerald-400 text-[10px] ml-1">live</span>
      </h2>
      <span class="text-[10px] text-zinc-600 mono">refresh 15s</span>
    </div>

    <div id="positions"
         hx-get="/api.php?q=positions"
         hx-trigger="load, every 15s"
         hx-swap="innerHTML"
         class="border border-zinc-900 rounded divide-y divide-zinc-900 min-h-[300px]">
      <div class="p-6 text-zinc-600 text-sm">loading…</div>
    </div>
  </section>

</main>

<footer class="border-t border-zinc-900 py-6 mt-12">
  <div class="max-w-7xl mx-auto px-6 text-[11px] text-zinc-600 mono flex justify-between flex-wrap gap-2">
    <span>hl.signals · data from Hyperliquid public API</span>
    <span>not financial advice</span>
  </div>
</footer>

<script>
  // Transform JSON responses from api.php into HTML partials.
  // Keeps api.php dumb (pure JSON) and rendering in one place.
  document.body.addEventListener('htmx:beforeSwap', (e) => {
    const target = e.detail.target.id;
    try {
      const data = JSON.parse(e.detail.serverResponse);
      if (target === 'traders') e.detail.serverResponse = renderTraders(data);
      else if (target === 'positions') e.detail.serverResponse = renderPositions(data);
    } catch {}
  });

  const shortAddr = a => !a ? '—' : a.slice(0, 6) + '…' + a.slice(-4);
  const money = n => {
    const abs = Math.abs(n), sign = n < 0 ? '-' : (n > 0 ? '+' : '');
    if (abs >= 1e6) return `${sign}$${(abs/1e6).toFixed(2)}M`;
    if (abs >= 1e3) return `${sign}$${(abs/1e3).toFixed(1)}k`;
    return `${sign}$${Math.round(abs).toLocaleString()}`;
  };
  const age = ts => {
    if (!ts) return '—';
    const d = Math.floor(Date.now()/1000) - ts;
    if (d < 60) return d + 's';
    if (d < 3600) return Math.floor(d/60) + 'm';
    if (d < 86400) return Math.floor(d/3600) + 'h';
    return Math.floor(d/86400) + 'd';
  };

  function renderTraders(rows) {
    if (!rows || !rows.length) return `<div class="p-6 text-zinc-600 text-sm">no data yet — run the crawler</div>`;
    const head = `
      <table class="w-full text-sm">
        <thead class="text-[10px] uppercase tracking-wider text-zinc-600">
          <tr>
            <th class="text-left font-medium px-4 py-3 w-8">#</th>
            <th class="text-left font-medium px-4 py-3">Trader</th>
            <th class="text-right font-medium px-4 py-3">PnL</th>
            <th class="text-right font-medium px-4 py-3">Volume</th>
            <th class="text-right font-medium px-4 py-3">Equity</th>
          </tr>
        </thead><tbody class="mono">`;
    const body = rows.map((r, i) => {
      const pnl = Number(r.pnl || 0);
      const cls = pnl >= 0 ? 'up' : 'down';
      return `<tr class="border-t border-zinc-900 cursor-pointer" onclick="location.href='/trader.php?addr=${r.address}'">
        <td class="px-4 py-3 text-zinc-600">${i+1}</td>
        <td class="px-4 py-3"><a href="/trader.php?addr=${r.address}" class="text-zinc-200 hover:text-emerald-400">${shortAddr(r.address)}</a>${r.label ? ` <span class="text-[10px] text-zinc-500 ml-1">${r.label}</span>` : ''}</td>
        <td class="px-4 py-3 text-right ${cls}">${money(pnl)}</td>
        <td class="px-4 py-3 text-right text-zinc-400">${money(Number(r.volume||0))}</td>
        <td class="px-4 py-3 text-right text-zinc-400">${money(Number(r.account_value||0))}</td>
      </tr>`;
    }).join('');
    return head + body + '</tbody></table>';
  }

  function renderPositions(rows) {
    if (!rows || !rows.length) return `<div class="p-6 text-zinc-600 text-sm">no open positions yet — run the position crawler</div>`;
    return rows.map((r, i) => {
      const sideCls = r.side === 'long'
        ? 'bg-emerald-500/15 text-emerald-400'
        : 'bg-red-500/15 text-red-400';
      return `<div class="p-4 flex items-center gap-4 hover:bg-white/[.02] cursor-pointer" onclick="location.href='/trader.php?addr=${r.address}'">
        <div class="mono text-zinc-600 text-xs w-5">${i+1}</div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="mono font-bold">${r.coin}</span>
            <span class="text-[10px] ${sideCls} px-1.5 py-0.5 rounded font-semibold uppercase">${r.side}</span>
            <span class="mono text-[10px] text-zinc-500">${Number(r.leverage).toFixed(1)}×</span>
          </div>
          <div class="mono text-xs text-zinc-500 mt-0.5">
            <a href="/trader.php?addr=${r.address}" class="hover:text-emerald-400" onclick="event.stopPropagation()">${shortAddr(r.address)}</a> · ${age(r.updated_at)} ago · entry $${Number(r.entry_px).toLocaleString()} → $${Number(r.mark_px).toLocaleString()}
          </div>
        </div>
        <div class="text-right">
          <div class="mono up font-bold">${money(Number(r.unrealized_pnl))}</div>
          <div class="mono text-xs up">+${Number(r.unrealized_pct).toFixed(1)}%</div>
        </div>
      </div>`;
    }).join('');
  }
</script>

</body>
</html>
