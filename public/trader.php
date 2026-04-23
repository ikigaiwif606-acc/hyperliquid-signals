<?php
declare(strict_types=1);

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
<meta name="theme-color" content="#ffffff">
<style>
  :root { --bg:#fff; --surface:#fafafa; --text:#111; --muted:#666; --border:#eee; --border-strong:#ddd; --sky:#3b9eff; --green:#2dbe60; --red:#ff5a5f; --amber:#ffb800; --card-shadow: 0 2px 8px rgba(0,0,0,.06); }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; line-height: 1.4; -webkit-font-smoothing: antialiased; }
  a { color: inherit; text-decoration: none; } a:hover { text-decoration: underline; }
  .num { font-variant-numeric: tabular-nums; }
  .topbar { height: 52px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 16px; padding: 0 16px; position: sticky; top: 0; z-index: 10; background: #fff; }
  .logo { font-weight: 800; font-size: 16px; letter-spacing: -.02em; }
  .logo .dot { color: var(--green); }
  .back { font-size: 13px; color: var(--muted); }
  .live { margin-left: auto; font-size: 12px; color: var(--muted); }
  .live .pulse { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--green); margin-right: 6px; animation: p 1.6s infinite; vertical-align: middle; }
  @keyframes p { 0%,100% { opacity: 1 } 50% { opacity: .3 } }

  .wrap { max-width: 1100px; margin: 0 auto; padding: 20px 16px 40px; }
  .hero { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
  .hero h1 { font-size: 28px; font-weight: 800; letter-spacing: -.02em; margin: 0; }
  .hero .addr { font-size: 12px; color: var(--muted); margin-top: 4px; word-break: break-all; font-variant-numeric: tabular-nums; font-family: ui-monospace, Menlo, monospace; }
  .hero-actions { display: flex; gap: 8px; }
  .btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border-strong); background: #fff; font-size: 13px; font-weight: 600; cursor: pointer; color: var(--text); }
  .btn:hover { background: var(--surface); text-decoration: none; }
  .btn.primary { background: #111; color: #fff; border-color: #111; }

  .stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 20px; }
  .stat { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; }
  .stat .v { font-size: 18px; font-weight: 700; letter-spacing: -.01em; line-height: 1.1; }
  .stat .l { font-size: 11px; color: var(--muted); margin-top: 3px; font-weight: 600; }
  .stat .v.up { color: var(--green); } .stat .v.down { color: var(--red); }

  h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 28px 0 10px; }

  .panel { background: #fff; border: 1px solid var(--border); border-radius: 10px; box-shadow: var(--card-shadow); overflow: hidden; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { padding: 10px 12px; text-align: left; border-top: 1px solid var(--border); }
  th { background: var(--surface); font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); font-weight: 700; border-top: 0; position: sticky; top: 0; }
  td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
  tbody tr:hover { background: var(--surface); }
  .pill { display: inline-block; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 999px; color: #fff; text-transform: uppercase; letter-spacing: .04em; }
  .pill.green { background: var(--green); } .pill.red { background: var(--red); }
  .up { color: var(--green); } .down { color: var(--red); } .mute { color: var(--muted); }

  footer { border-top: 1px solid var(--border); padding: 14px 16px; font-size: 11.5px; color: var(--muted); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-top: 40px; }

  @media (max-width: 720px) {
    .stats { grid-template-columns: repeat(2, 1fr); }
    table { font-size: 12px; }
    th, td { padding: 8px 8px; }
  }
</style>
</head>
<body>

<header class="topbar">
  <a href="/" class="logo">🚀 HL<span class="dot">.</span>SIGNALS</a>
  <a href="/" class="back">← back</a>
  <span class="live"><span class="pulse"></span>live</span>
</header>

<main class="wrap">
  <div id="profile"><div class="mute">loading…</div></div>

  <h2>🎯 Current positions</h2>
  <div class="panel" id="positions"><div style="padding: 20px;" class="mute">loading…</div></div>

  <h2>📜 Recent fills <span style="font-weight:400; text-transform:none; letter-spacing:0; color:var(--muted)">(last 100)</span></h2>
  <div class="panel" id="fills"><div style="padding: 20px;" class="mute">loading…</div></div>
</main>

<footer>
  <div>🛠 hl.signals · data from Hyperliquid public API</div>
  <div>not financial advice</div>
</footer>

<script>
const ADDR = <?= json_encode($addr) ?>;
const shortAddr = a => a ? a.slice(0,6) + '…' + a.slice(-4) : '—';
const money = n => {
  const abs = Math.abs(n), sign = n < 0 ? '-' : (n > 0 ? '+' : '');
  if (abs >= 1e6) return `${sign}$${(abs/1e6).toFixed(2)}M`;
  if (abs >= 1e3) return `${sign}$${(abs/1e3).toFixed(1)}k`;
  return `${sign}$${Math.round(abs).toLocaleString()}`;
};
const moneyRaw = n => { const a = Math.abs(n); return a >= 1e6 ? `$${(a/1e6).toFixed(2)}M` : a >= 1e3 ? `$${(a/1e3).toFixed(1)}k` : `$${Math.round(a).toLocaleString()}`; };
const fmtPrice = n => { if (!n) return '—'; if (n >= 1000) return '$' + Math.round(n).toLocaleString(); if (n >= 1) return '$' + Number(n).toFixed(3); return '$' + Number(n).toFixed(5); };
const age = ts => { if (!ts) return '—'; const d = Math.floor(Date.now()/1000) - ts; return d < 60 ? d+'s' : d < 3600 ? Math.floor(d/60)+'m' : d < 86400 ? Math.floor(d/3600)+'h' : Math.floor(d/86400)+'d'; };
const fmtDate = ts => { if (!ts) return '—'; const d = new Date(ts*1000), p = n => String(n).padStart(2, '0'); return `${p(d.getMonth()+1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`; };
const whaleTier = e => e >= 10e6 ? '🐋' : e >= 1e6 ? '🦈' : e >= 100e3 ? '🐟' : '🦐';

function renderProfile(p) {
  if (!p) return `<div class="mute">trader not found</div>`;
  const em = whaleTier(Number(p.account_value || 0));
  const statCls = n => Number(n) >= 0 ? 'up' : 'down';
  return `
    <div class="hero">
      <div>
        <h1>${em} ${shortAddr(p.address)}</h1>
        <div class="addr">${p.address}</div>
      </div>
      <div class="hero-actions">
        <button class="btn" onclick="navigator.clipboard.writeText('${p.address}')">📋 Copy</button>
        <a class="btn primary" target="_blank" rel="noopener" href="https://app.hyperliquid.xyz/explorer/address/${p.address}">↗ Explorer</a>
      </div>
    </div>
    <section class="stats">
      <div class="stat"><div class="v num ${statCls(p.pnl_day)}">${money(Number(p.pnl_day||0))}</div><div class="l">⚡ PnL 24h</div></div>
      <div class="stat"><div class="v num ${statCls(p.pnl_week)}">${money(Number(p.pnl_week||0))}</div><div class="l">📅 PnL 7d</div></div>
      <div class="stat"><div class="v num ${statCls(p.pnl_month)}">${money(Number(p.pnl_month||0))}</div><div class="l">📆 PnL 30d</div></div>
      <div class="stat"><div class="v num ${statCls(p.pnl_all)}">${money(Number(p.pnl_all||0))}</div><div class="l">🌐 PnL all-time</div></div>
      <div class="stat"><div class="v num">${moneyRaw(Number(p.account_value||0))}</div><div class="l">💰 Equity</div></div>
    </section>
    <section class="stats">
      <div class="stat"><div class="v num mute">${moneyRaw(Number(p.vlm_day||0))}</div><div class="l">📊 Vol 24h</div></div>
      <div class="stat"><div class="v num mute">${moneyRaw(Number(p.vlm_week||0))}</div><div class="l">📊 Vol 7d</div></div>
      <div class="stat"><div class="v num mute">${moneyRaw(Number(p.vlm_month||0))}</div><div class="l">📊 Vol 30d</div></div>
      <div class="stat"><div class="v num mute">${moneyRaw(Number(p.vlm_all||0))}</div><div class="l">📊 Vol all</div></div>
      <div class="stat"><div class="v num">${p.role || 'user'}</div><div class="l">🏷 Role</div></div>
    </section>`;
}

function renderPositions(rows) {
  if (!rows || !rows.length) return `<div style="padding: 20px;" class="mute">no open positions</div>`;
  const head = `<table><thead><tr>
    <th>Coin</th><th>Side</th><th class="num">Size</th><th class="num">Entry</th>
    <th class="num">Mark</th><th class="num">Lev</th><th class="num">Notional</th><th class="num">Unrealized</th>
  </tr></thead><tbody>`;
  const body = rows.map(r => {
    const cls = r.unrealized_pnl >= 0 ? 'up' : 'down';
    return `<tr>
      <td><a href="/coin.php?c=${r.coin}"><b>${r.coin}</b></a></td>
      <td><span class="pill ${r.side === 'long' ? 'green' : 'red'}">${r.side}</span></td>
      <td class="num">${Number(r.size).toLocaleString(undefined, {maximumFractionDigits: 4})}</td>
      <td class="num">${fmtPrice(r.entry_px)}</td>
      <td class="num">${fmtPrice(r.mark_px)}</td>
      <td class="num mute">${Number(r.leverage).toFixed(1)}×</td>
      <td class="num mute">${moneyRaw(r.notional)}</td>
      <td class="num ${cls}"><b>${money(r.unrealized_pnl)}</b> <span style="font-size:11px">(${r.unrealized_pct >= 0 ? '+' : ''}${Number(r.unrealized_pct).toFixed(1)}%)</span></td>
    </tr>`;
  }).join('');
  return head + body + '</tbody></table>';
}

function renderFills(rows) {
  if (!rows || !rows.length) return `<div style="padding: 20px;" class="mute">no fills</div>`;
  const head = `<table><thead><tr>
    <th>Time</th><th>Coin</th><th>Action</th><th class="num">Size</th>
    <th class="num">Price</th><th class="num">Fee</th><th class="num">Closed PnL</th>
  </tr></thead><tbody>`;
  const body = rows.map(r => {
    const pnlCls = r.closed_pnl > 0 ? 'up' : r.closed_pnl < 0 ? 'down' : 'mute';
    const dirCls = /Long|Buy/i.test(r.dir) && !/Close/i.test(r.dir) ? 'up'
                 : /Short|Sell/i.test(r.dir) && !/Close/i.test(r.dir) ? 'down'
                 : /Close Long/i.test(r.dir) ? 'down'
                 : /Close Short/i.test(r.dir) ? 'up' : 'mute';
    return `<tr>
      <td class="mute" style="font-size:12px">${fmtDate(r.time)}</td>
      <td><b>${r.coin}</b></td>
      <td class="${dirCls}" style="font-weight:600">${r.dir}</td>
      <td class="num">${Number(r.sz).toLocaleString(undefined, {maximumFractionDigits:4})}</td>
      <td class="num">${fmtPrice(r.px)}</td>
      <td class="num mute">${r.fee > 0 ? '$' + Number(r.fee).toFixed(2) : '—'}</td>
      <td class="num ${pnlCls}">${r.closed_pnl !== 0 ? money(r.closed_pnl) : '—'}</td>
    </tr>`;
  }).join('');
  return head + body + '</tbody></table>';
}

async function load() {
  const res = await fetch(`/api.php?q=trader&addr=${ADDR}`);
  if (!res.ok) {
    document.getElementById('profile').innerHTML = `<div class="down">error loading trader</div>`;
    return;
  }
  const d = await res.json();
  document.getElementById('profile').innerHTML = renderProfile(d.profile);
  document.getElementById('positions').innerHTML = renderPositions(d.positions);
  document.getElementById('fills').innerHTML = renderFills(d.fills);
}
load();
setInterval(load, 30000);
</script>
</body>
</html>
