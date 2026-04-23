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
  .hero { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
  .hero h1 { font-size: 40px; font-weight: 800; letter-spacing: -.02em; margin: 0; }
  .hero .mark { font-size: 28px; font-weight: 700; color: var(--text); font-variant-numeric: tabular-nums; }

  .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 20px; }
  .stat { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; }
  .stat .v { font-size: 20px; font-weight: 700; letter-spacing: -.01em; line-height: 1.1; font-variant-numeric: tabular-nums; }
  .stat .l { font-size: 11px; color: var(--muted); margin-top: 3px; font-weight: 600; }
  .stat .v.up { color: var(--green); } .stat .v.down { color: var(--red); }

  .consensus { margin-bottom: 20px; }
  .bar { height: 10px; background: #f0f0f0; border-radius: 5px; overflow: hidden; display: flex; }
  .bar .seg-long { background: var(--green); } .bar .seg-short { background: var(--red); }
  .bar-labels { display: flex; justify-content: space-between; font-size: 12px; margin-top: 6px; font-weight: 600; }

  h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 28px 0 10px; }
  .panel { background: #fff; border: 1px solid var(--border); border-radius: 10px; box-shadow: var(--card-shadow); overflow: hidden; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { padding: 10px 12px; text-align: left; border-top: 1px solid var(--border); }
  th { background: var(--surface); font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); font-weight: 700; border-top: 0; }
  td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
  tbody tr:hover { background: var(--surface); cursor: pointer; }
  .pill { display: inline-block; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 999px; color: #fff; text-transform: uppercase; letter-spacing: .04em; }
  .pill.green { background: var(--green); } .pill.red { background: var(--red); }
  .up { color: var(--green); } .down { color: var(--red); } .mute { color: var(--muted); }

  footer { border-top: 1px solid var(--border); padding: 14px 16px; font-size: 11.5px; color: var(--muted); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-top: 40px; }

  @media (max-width: 720px) {
    .hero h1 { font-size: 32px; }
    .stats { grid-template-columns: repeat(2, 1fr); }
    table { font-size: 12px; }
  }
</style>
</head>
<body>

<header class="topbar">
  <a href="/" class="logo">🚀 HL<span class="dot">.</span>SIGNALS</a>
  <a href="/?view=coins" class="back">← coins</a>
  <span class="live"><span class="pulse"></span>live</span>
</header>

<main class="wrap">
  <div id="summary"><div class="mute">loading…</div></div>
  <div id="consensus" class="consensus"></div>

  <h2>👥 Open positions in <?= htmlspecialchars($coin) ?> <span style="font-weight:400; text-transform:none; letter-spacing:0; color:var(--muted)">(tracked traders, by notional)</span></h2>
  <div class="panel" id="positions"><div style="padding: 20px;" class="mute">loading…</div></div>
</main>

<footer>
  <div>🛠 hl.signals · data from Hyperliquid public API</div>
  <div>not financial advice</div>
</footer>

<script>
const COIN = <?= json_encode($coin) ?>;
const shortAddr = a => a ? a.slice(0,6) + '…' + a.slice(-4) : '—';
const money = n => { const a = Math.abs(n), s = n < 0 ? '-' : (n > 0 ? '+' : ''); return a >= 1e6 ? `${s}$${(a/1e6).toFixed(2)}M` : a >= 1e3 ? `${s}$${(a/1e3).toFixed(1)}k` : `${s}$${Math.round(a).toLocaleString()}`; };
const moneyRaw = n => { const a = Math.abs(n); return a >= 1e6 ? `$${(a/1e6).toFixed(2)}M` : a >= 1e3 ? `$${(a/1e3).toFixed(1)}k` : `$${Math.round(a).toLocaleString()}`; };
const fmtPrice = n => { if (!n) return '—'; if (n >= 1000) return '$' + Math.round(n).toLocaleString(); if (n >= 1) return '$' + Number(n).toFixed(3); return '$' + Number(n).toFixed(5); };
const age = ts => { if (!ts) return '—'; const d = Math.floor(Date.now()/1000) - ts; return d < 60 ? d+'s' : d < 3600 ? Math.floor(d/60)+'m' : d < 86400 ? Math.floor(d/3600)+'h' : Math.floor(d/86400)+'d'; };

function renderSummary(d) {
  const total = d.long_count + d.short_count;
  const longPct = total > 0 ? (d.long_count / total * 100) : 0;
  const shortPct = 100 - longPct;
  const mood = longPct >= 70 ? { em: '🚀', label: 'Long-biased', cls: 'up' }
             : shortPct >= 70 ? { em: '🔻', label: 'Short-biased', cls: 'down' }
             : { em: '⚖️', label: 'Mixed', cls: '' };
  return `
    <div class="hero">
      <div>
        <h1>${mood.em} ${d.coin}</h1>
        <div class="mute" style="font-size: 13px; margin-top: 4px;">${mood.label} · ${total} tracked traders</div>
      </div>
      ${d.mark !== null ? `<div class="mark">${fmtPrice(d.mark)}</div>` : ''}
    </div>
    <section class="stats">
      <div class="stat"><div class="v num ${mood.cls}">${mood.label}</div><div class="l">📊 Consensus</div></div>
      <div class="stat"><div class="v num"><span class="up">${d.long_count}</span> / <span class="down">${d.short_count}</span></div><div class="l">⚖️ Long / Short</div></div>
      <div class="stat"><div class="v num up">${moneyRaw(d.long_notional)}</div><div class="l">🚀 Long notional</div></div>
      <div class="stat"><div class="v num down">${moneyRaw(d.short_notional)}</div><div class="l">🔻 Short notional</div></div>
    </section>
  `;
}

function renderConsensus(d) {
  const total = d.long_count + d.short_count;
  if (!total) return '';
  const longPct = d.long_count / total * 100;
  const shortPct = 100 - longPct;
  return `
    <div class="bar-labels"><span class="up">🚀 ${d.long_count} long · avg entry ${fmtPrice(d.long_avg_entry)}</span><span class="down">avg entry ${fmtPrice(d.short_avg_entry)} · ${d.short_count} short 🔻</span></div>
    <div class="bar" style="margin-top: 6px;">
      <div class="seg-long" style="width:${longPct}%"></div>
      <div class="seg-short" style="width:${shortPct}%"></div>
    </div>
  `;
}

function renderPositions(rows) {
  if (!rows || !rows.length) return `<div style="padding: 20px;" class="mute">no tracked open positions</div>`;
  const head = `<table><thead><tr>
    <th>Trader</th><th>Side</th><th class="num">Size</th><th class="num">Entry</th>
    <th class="num">Lev</th><th class="num">Notional</th><th class="num">Unrealized</th><th class="num">Updated</th>
  </tr></thead><tbody>`;
  const body = rows.map(r => {
    const notional = (r.mark_px || 0) * (r.size || 0);
    const cls = r.unrealized_pnl >= 0 ? 'up' : 'down';
    return `<tr onclick="location.href='/trader.php?addr=${r.address}'">
      <td><a href="/trader.php?addr=${r.address}"><b>${shortAddr(r.address)}</b></a></td>
      <td><span class="pill ${r.side === 'long' ? 'green' : 'red'}">${r.side}</span></td>
      <td class="num">${Number(r.size).toLocaleString(undefined, {maximumFractionDigits:4})}</td>
      <td class="num">${fmtPrice(r.entry_px)}</td>
      <td class="num mute">${Number(r.leverage).toFixed(1)}×</td>
      <td class="num mute">${moneyRaw(notional)}</td>
      <td class="num ${cls}"><b>${money(r.unrealized_pnl)}</b></td>
      <td class="num mute" style="font-size:11px">${age(r.updated_at)} ago</td>
    </tr>`;
  }).join('');
  return head + body + '</tbody></table>';
}

async function load() {
  const res = await fetch(`/api.php?q=coin&c=${COIN}`);
  if (!res.ok) {
    document.getElementById('summary').innerHTML = `<div class="down">error</div>`;
    return;
  }
  const d = await res.json();
  document.getElementById('summary').innerHTML = renderSummary(d);
  document.getElementById('consensus').innerHTML = renderConsensus(d);
  document.getElementById('positions').innerHTML = renderPositions(d.positions);
}
load();
setInterval(load, 30000);
</script>
</body>
</html>
