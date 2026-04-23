<?php
declare(strict_types=1);

$coin = strtoupper(trim((string)($_GET['c'] ?? '')));
if (!preg_match('/^[A-Z0-9]{1,20}$/', $coin)) {
    http_response_code(400);
    echo 'bad coin';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($coin) ?> — HL Signals</title>
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<meta name="theme-color" content="#0a0a0a">
<link rel="stylesheet" href="/assets/tailwind.css">
<style>
  body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; }
  tbody tr:hover { background: rgba(255,255,255,.025); }
  .up { color: #22c55e; } .down { color: #ef4444; }
  .pulse-dot { animation: pulse 1.6s ease-in-out infinite; }
  @keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: .3 } }
</style>
</head>
<body class="bg-[#0a0a0a] text-zinc-100">

<header class="border-b border-zinc-900">
  <div class="max-w-7xl mx-auto px-6 h-14 flex items-center justify-between">
    <a href="/" class="font-black tracking-tight text-base">HL<span class="text-emerald-400">.</span>SIGNALS</a>
    <span class="flex items-center gap-2 text-xs text-zinc-500">
      <span class="pulse-dot inline-block w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
      live
    </span>
  </div>
</header>

<main class="max-w-7xl mx-auto px-6 py-8">
  <div class="mb-2"><a href="/" class="text-xs text-zinc-500 hover:text-zinc-300">← back to dashboard</a></div>

  <div id="summary" class="mb-8">
    <div class="text-zinc-600 text-sm">loading…</div>
  </div>

  <div id="consensus" class="mb-10"></div>

  <section>
    <h2 class="text-sm font-semibold text-zinc-400 uppercase tracking-wider mb-4">Open Positions in <?= htmlspecialchars($coin) ?> <span class="text-zinc-600 font-normal normal-case">(tracked traders, by notional)</span></h2>
    <div id="positions" class="border border-zinc-900 rounded min-h-[200px]">
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
const COIN = <?= json_encode($coin) ?>;
const shortAddr = a => !a ? '—' : a.slice(0, 6) + '…' + a.slice(-4);
const money = n => {
  const abs = Math.abs(n), sign = n < 0 ? '-' : (n > 0 ? '+' : '');
  if (abs >= 1e6) return `${sign}$${(abs/1e6).toFixed(2)}M`;
  if (abs >= 1e3) return `${sign}$${(abs/1e3).toFixed(1)}k`;
  return `${sign}$${Math.round(abs).toLocaleString()}`;
};
const fmtPrice = n => {
  if (!n) return '—';
  if (n >= 1000) return '$' + Number(n).toLocaleString(undefined, {maximumFractionDigits:2});
  if (n >= 1) return '$' + Number(n).toFixed(3);
  return '$' + Number(n).toFixed(5);
};
const age = ts => {
  if (!ts) return '—';
  const d = Math.floor(Date.now()/1000) - ts;
  if (d < 60) return d + 's';
  if (d < 3600) return Math.floor(d/60) + 'm';
  if (d < 86400) return Math.floor(d/3600) + 'h';
  return Math.floor(d/86400) + 'd';
};

function renderSummary(d) {
  const totalCount = d.long_count + d.short_count;
  const longPct = totalCount > 0 ? (d.long_count / totalCount * 100).toFixed(0) : 0;
  const shortPct = 100 - longPct;
  return `
    <div class="flex items-baseline justify-between flex-wrap gap-4 mb-4">
      <h1 class="text-4xl font-black tracking-tight">${d.coin}</h1>
      ${d.mark !== null ? `<div class="mono text-2xl text-zinc-300">${fmtPrice(d.mark)}</div>` : ''}
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div>
        <div class="text-[10px] uppercase tracking-wider text-zinc-500">Tracked traders in</div>
        <div class="mono font-bold text-2xl">${totalCount}</div>
      </div>
      <div>
        <div class="text-[10px] uppercase tracking-wider text-zinc-500">Long / Short</div>
        <div class="mono font-bold text-lg"><span class="up">${d.long_count}</span> <span class="text-zinc-600">/</span> <span class="down">${d.short_count}</span></div>
      </div>
      <div>
        <div class="text-[10px] uppercase tracking-wider text-zinc-500">Long notional</div>
        <div class="mono font-bold text-lg up">${money(d.long_notional)}</div>
      </div>
      <div>
        <div class="text-[10px] uppercase tracking-wider text-zinc-500">Short notional</div>
        <div class="mono font-bold text-lg down">${money(d.short_notional)}</div>
      </div>
    </div>
  `;
}

function renderConsensus(d) {
  const totalCount = d.long_count + d.short_count;
  if (!totalCount) return '';
  const longPct = d.long_count / totalCount * 100;
  const shortPct = 100 - longPct;
  return `
    <div class="mb-2 flex justify-between text-xs mono"><span class="up">${d.long_count} long · avg entry ${fmtPrice(d.long_avg_entry)}</span><span class="down">avg entry ${fmtPrice(d.short_avg_entry)} · ${d.short_count} short</span></div>
    <div class="flex h-3 rounded overflow-hidden bg-zinc-800">
      <div class="bg-emerald-500" style="width:${longPct}%"></div>
      <div class="bg-red-500" style="width:${shortPct}%"></div>
    </div>
  `;
}

function renderPositions(rows, mark) {
  if (!rows || !rows.length) return `<div class="p-6 text-zinc-600 text-sm">no open positions among tracked traders</div>`;
  const head = `<table class="w-full text-sm">
    <thead class="text-[10px] uppercase tracking-wider text-zinc-600">
      <tr>
        <th class="text-left font-medium px-4 py-3">Trader</th>
        <th class="text-left font-medium px-4 py-3">Side</th>
        <th class="text-right font-medium px-4 py-3">Size</th>
        <th class="text-right font-medium px-4 py-3">Entry</th>
        <th class="text-right font-medium px-4 py-3">Lev</th>
        <th class="text-right font-medium px-4 py-3">Notional</th>
        <th class="text-right font-medium px-4 py-3">Unrealized</th>
        <th class="text-right font-medium px-4 py-3">Updated</th>
      </tr>
    </thead><tbody class="mono">`;
  const body = rows.map(r => {
    const notional = (r.mark_px || 0) * (r.size || 0);
    const upnlCls = r.unrealized_pnl >= 0 ? 'up' : 'down';
    const sideCls = r.side === 'long' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-red-500/15 text-red-400';
    return `<tr class="border-t border-zinc-900 cursor-pointer" onclick="location.href='/trader.php?addr=${r.address}'">
      <td class="px-4 py-3"><a href="/trader.php?addr=${r.address}" class="text-zinc-200 hover:text-emerald-400">${shortAddr(r.address)}</a></td>
      <td class="px-4 py-3"><span class="text-[10px] ${sideCls} px-1.5 py-0.5 rounded font-semibold uppercase">${r.side}</span></td>
      <td class="px-4 py-3 text-right text-zinc-300">${Number(r.size).toLocaleString(undefined,{maximumFractionDigits:4})}</td>
      <td class="px-4 py-3 text-right text-zinc-300">${fmtPrice(r.entry_px)}</td>
      <td class="px-4 py-3 text-right text-zinc-400">${Number(r.leverage).toFixed(1)}×</td>
      <td class="px-4 py-3 text-right text-zinc-400">${money(notional)}</td>
      <td class="px-4 py-3 text-right ${upnlCls} font-semibold">${money(r.unrealized_pnl)}</td>
      <td class="px-4 py-3 text-right text-zinc-500 text-xs">${age(r.updated_at)} ago</td>
    </tr>`;
  }).join('');
  return head + body + '</tbody></table>';
}

async function load() {
  const res = await fetch(`/api.php?q=coin&c=${COIN}`);
  if (!res.ok) {
    document.getElementById('summary').innerHTML = `<div class="text-red-400 text-sm">${(await res.json()).error || 'error'}</div>`;
    return;
  }
  const d = await res.json();
  document.getElementById('summary').innerHTML = renderSummary(d);
  document.getElementById('consensus').innerHTML = renderConsensus(d);
  document.getElementById('positions').innerHTML = renderPositions(d.positions, d.mark);
}
load();
setInterval(load, 30000);
</script>
</body>
</html>
