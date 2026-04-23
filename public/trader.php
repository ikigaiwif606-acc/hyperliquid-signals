<?php
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';

$addr = strtolower($_GET['addr'] ?? '');
if (!preg_match('/^0x[0-9a-f]{40}$/', $addr)) {
    http_response_code(400);
    echo 'bad address';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Trader <?= htmlspecialchars(substr($addr, 0, 6) . '…' . substr($addr, -4)) ?> — HL Signals</title>
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<meta name="theme-color" content="#0a0a0a">
<script src="https://cdn.tailwindcss.com"></script>
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

  <div id="profile" class="mb-8">
    <div class="text-zinc-600 text-sm">loading…</div>
  </div>

  <section class="mb-10">
    <h2 class="text-sm font-semibold text-zinc-400 uppercase tracking-wider mb-4">Current Positions</h2>
    <div id="positions" class="border border-zinc-900 rounded min-h-[80px]">
      <div class="p-6 text-zinc-600 text-sm">loading…</div>
    </div>
  </section>

  <section>
    <h2 class="text-sm font-semibold text-zinc-400 uppercase tracking-wider mb-4">Recent Fills <span class="text-zinc-600 font-normal normal-case">(last 100)</span></h2>
    <div id="fills" class="border border-zinc-900 rounded min-h-[80px]">
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
const ADDR = <?= json_encode($addr) ?>;

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
  if (d < 60) return d + 's ago';
  if (d < 3600) return Math.floor(d/60) + 'm ago';
  if (d < 86400) return Math.floor(d/3600) + 'h ago';
  return Math.floor(d/86400) + 'd ago';
};
const fmtDate = ts => {
  if (!ts) return '—';
  const d = new Date(ts * 1000);
  const pad = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
};
const fmtPrice = n => {
  if (n >= 1000) return '$' + Number(n).toLocaleString(undefined, {maximumFractionDigits:2});
  if (n >= 1) return '$' + Number(n).toFixed(3);
  return '$' + Number(n).toFixed(5);
};

function renderProfile(p) {
  if (!p) return `<div class="text-zinc-600 text-sm">trader not found</div>`;
  const pnlRow = (label, v) => {
    const cls = v >= 0 ? 'up' : 'down';
    return `<div>
      <div class="text-[10px] uppercase tracking-wider text-zinc-500">${label}</div>
      <div class="mono font-bold text-lg ${cls}">${money(Number(v||0))}</div>
    </div>`;
  };
  const vlmRow = (label, v) => `<div>
    <div class="text-[10px] uppercase tracking-wider text-zinc-500">${label}</div>
    <div class="mono font-semibold text-zinc-300">${money(Number(v||0))}</div>
  </div>`;
  return `
    <div class="flex items-start justify-between flex-wrap gap-4 mb-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight">${shortAddr(p.address)}</h1>
        <div class="mono text-xs text-zinc-500 mt-1 break-all">${p.address}</div>
        ${p.label ? `<div class="text-xs text-zinc-400 mt-1">${p.label}</div>` : ''}
      </div>
      <div class="flex gap-2">
        <a target="_blank" rel="noopener" class="text-xs px-3 py-1.5 rounded border border-zinc-800 hover:border-zinc-600 text-zinc-400 hover:text-white" href="https://app.hyperliquid.xyz/explorer/address/${p.address}">view on HL explorer ↗</a>
        <button onclick="navigator.clipboard.writeText('${p.address}')" class="text-xs px-3 py-1.5 rounded border border-zinc-800 hover:border-zinc-600 text-zinc-400 hover:text-white">copy address</button>
      </div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mt-6">
      ${pnlRow('PnL 24h', p.pnl_day)}
      ${pnlRow('PnL 7d', p.pnl_week)}
      ${pnlRow('PnL 30d', p.pnl_month)}
      ${pnlRow('PnL all', p.pnl_all)}
      ${vlmRow('Equity', p.account_value)}
    </div>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mt-4 pt-4 border-t border-zinc-900">
      ${vlmRow('Volume 24h', p.vlm_day)}
      ${vlmRow('Volume 7d', p.vlm_week)}
      ${vlmRow('Volume 30d', p.vlm_month)}
      ${vlmRow('Volume all', p.vlm_all)}
      <div>
        <div class="text-[10px] uppercase tracking-wider text-zinc-500">Role</div>
        <div class="mono text-zinc-300">${p.role || 'user'}</div>
      </div>
    </div>
  `;
}

function renderPositions(rows) {
  if (!rows || !rows.length) return `<div class="p-6 text-zinc-600 text-sm">no open positions</div>`;
  const head = `<table class="w-full text-sm">
    <thead class="text-[10px] uppercase tracking-wider text-zinc-600">
      <tr>
        <th class="text-left font-medium px-4 py-3">Coin</th>
        <th class="text-left font-medium px-4 py-3">Side</th>
        <th class="text-right font-medium px-4 py-3">Size</th>
        <th class="text-right font-medium px-4 py-3">Entry</th>
        <th class="text-right font-medium px-4 py-3">Mark</th>
        <th class="text-right font-medium px-4 py-3">Lev</th>
        <th class="text-right font-medium px-4 py-3">Notional</th>
        <th class="text-right font-medium px-4 py-3">Unrealized PnL</th>
      </tr>
    </thead><tbody class="mono">`;
  const body = rows.map(r => {
    const cls = r.unrealized_pnl >= 0 ? 'up' : 'down';
    const sideCls = r.side === 'long' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-red-500/15 text-red-400';
    return `<tr class="border-t border-zinc-900">
      <td class="px-4 py-3 font-bold">${r.coin}</td>
      <td class="px-4 py-3"><span class="text-[10px] ${sideCls} px-1.5 py-0.5 rounded font-semibold uppercase">${r.side}</span></td>
      <td class="px-4 py-3 text-right text-zinc-300">${Number(r.size).toLocaleString(undefined,{maximumFractionDigits:4})}</td>
      <td class="px-4 py-3 text-right text-zinc-300">${fmtPrice(r.entry_px)}</td>
      <td class="px-4 py-3 text-right text-zinc-300">${fmtPrice(r.mark_px)}</td>
      <td class="px-4 py-3 text-right text-zinc-400">${Number(r.leverage).toFixed(1)}×</td>
      <td class="px-4 py-3 text-right text-zinc-400">${money(r.notional)}</td>
      <td class="px-4 py-3 text-right ${cls} font-bold">${money(r.unrealized_pnl)} <span class="text-xs">(${r.unrealized_pct >= 0 ? '+' : ''}${Number(r.unrealized_pct).toFixed(1)}%)</span></td>
    </tr>`;
  }).join('');
  return head + body + '</tbody></table>';
}

function renderFills(rows) {
  if (!rows || !rows.length) return `<div class="p-6 text-zinc-600 text-sm">no fills</div>`;
  const head = `<table class="w-full text-sm">
    <thead class="text-[10px] uppercase tracking-wider text-zinc-600">
      <tr>
        <th class="text-left font-medium px-4 py-3">Time</th>
        <th class="text-left font-medium px-4 py-3">Coin</th>
        <th class="text-left font-medium px-4 py-3">Action</th>
        <th class="text-right font-medium px-4 py-3">Size</th>
        <th class="text-right font-medium px-4 py-3">Price</th>
        <th class="text-right font-medium px-4 py-3">Fee</th>
        <th class="text-right font-medium px-4 py-3">Closed PnL</th>
      </tr>
    </thead><tbody class="mono">`;
  const body = rows.map(r => {
    const pnlCls = r.closed_pnl > 0 ? 'up' : (r.closed_pnl < 0 ? 'down' : 'text-zinc-600');
    const dirColor = /Open Long|Buy/.test(r.dir) ? 'text-emerald-400'
                   : /Open Short|Sell/.test(r.dir) ? 'text-red-400'
                   : /Close Long/.test(r.dir) ? 'text-red-400'
                   : /Close Short/.test(r.dir) ? 'text-emerald-400'
                   : 'text-zinc-400';
    return `<tr class="border-t border-zinc-900">
      <td class="px-4 py-3 text-zinc-500 text-xs">${fmtDate(r.time)}</td>
      <td class="px-4 py-3 font-bold">${r.coin}</td>
      <td class="px-4 py-3 ${dirColor} text-xs font-semibold">${r.dir}</td>
      <td class="px-4 py-3 text-right text-zinc-300">${Number(r.sz).toLocaleString(undefined,{maximumFractionDigits:4})}</td>
      <td class="px-4 py-3 text-right text-zinc-300">${fmtPrice(r.px)}</td>
      <td class="px-4 py-3 text-right text-zinc-500">${r.fee > 0 ? '$' + Number(r.fee).toFixed(2) : '—'}</td>
      <td class="px-4 py-3 text-right ${pnlCls} ${r.closed_pnl !== 0 ? 'font-semibold' : ''}">${r.closed_pnl !== 0 ? money(r.closed_pnl) : '—'}</td>
    </tr>`;
  }).join('');
  return head + body + '</tbody></table>';
}

async function load() {
  const res = await fetch(`/api.php?q=trader&addr=${ADDR}`);
  if (!res.ok) {
    document.getElementById('profile').innerHTML = `<div class="text-red-400 text-sm">${(await res.json()).error || 'error'}</div>`;
    return;
  }
  const data = await res.json();
  document.getElementById('profile').innerHTML = renderProfile(data.profile);
  document.getElementById('positions').innerHTML = renderPositions(data.positions);
  document.getElementById('fills').innerHTML = renderFills(data.fills);
}
load();
setInterval(load, 30000);
</script>

</body>
</html>
