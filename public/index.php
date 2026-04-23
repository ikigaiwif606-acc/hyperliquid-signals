<?php
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';

$window = $_GET['w'] ?? 'day';
if (!in_array($window, ['day', 'week', 'month', 'score'], true)) $window = 'day';
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
  .up { color: #22c55e; } .down { color: #ef4444; } .mute { color: #71717a; }
  .hero-num { font-size: 1.625rem; line-height: 1.1; font-weight: 800; letter-spacing: -0.02em; }
  .label { font-size: 10px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #71717a; }
</style>
</head>
<body class="bg-[#0a0a0a] text-zinc-100">

<header class="border-b border-zinc-900">
  <div class="max-w-6xl mx-auto px-6 h-12 flex items-center justify-between">
    <a href="/" class="font-black tracking-tight text-[15px]">HL<span class="text-emerald-400">.</span>SIGNALS</a>
    <div class="flex items-center gap-4">
      <div class="inline-flex rounded-md border border-zinc-800 text-xs overflow-hidden">
        <?php foreach (['day' => '24h', 'week' => '7d', 'month' => '30d', 'score' => 'Score'] as $w => $label): ?>
          <a href="?w=<?= $w ?>" class="px-3 py-1.5 <?= $w === $window ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:text-white' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
      <span class="flex items-center gap-1.5 text-xs text-zinc-500">
        <span class="pulse-dot inline-block w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
        live
      </span>
    </div>
  </div>
</header>

<main class="max-w-6xl mx-auto px-6 py-8 space-y-10">

  <!-- HERO STRIP -->
  <section id="hero"
           hx-get="/api.php?q=hero"
           hx-trigger="load, every 30s"
           hx-swap="innerHTML"
           class="grid grid-cols-2 md:grid-cols-4 gap-6 min-h-[64px]">
    <div class="mute text-xs">loading…</div>
  </section>

  <!-- FEATURED SIGNAL + SECONDARY PILLS -->
  <section>
    <div class="flex items-baseline justify-between mb-3">
      <h2 class="label">Signals <span class="text-zinc-700 normal-case tracking-normal ml-1">· ≥8 of top-20 same side</span></h2>
      <span class="mute text-[10px] mono">60s</span>
    </div>
    <div id="signals"
         hx-get="/api.php?q=signals"
         hx-trigger="load, every 60s"
         hx-swap="innerHTML"
         class="min-h-[60px]">
      <div class="mute text-xs">loading…</div>
    </div>
  </section>

  <!-- TWO MAIN COLUMNS -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <section>
      <div class="flex items-baseline justify-between mb-3">
        <h2 class="label">
          Top Traders
          <?php if ($window === 'score'): ?>
            <span class="text-zinc-700 normal-case tracking-normal ml-1">· composite score</span>
          <?php endif; ?>
        </h2>
        <span class="mute text-[10px] mono">30s</span>
      </div>
      <div id="traders"
           hx-get="/api.php?q=traders&w=<?= htmlspecialchars($window) ?>"
           hx-trigger="load, every 30s"
           hx-swap="innerHTML"
           class="border border-zinc-900 rounded-lg min-h-[300px]">
        <div class="p-5 mute text-xs">loading…</div>
      </div>
    </section>

    <section>
      <div class="flex items-baseline justify-between mb-3">
        <h2 class="label">
          Top 10 Profitable Positions <span class="text-emerald-400 tracking-normal normal-case ml-1">· live</span>
        </h2>
        <span class="mute text-[10px] mono">15s</span>
      </div>
      <div id="positions"
           hx-get="/api.php?q=positions"
           hx-trigger="load, every 15s"
           hx-swap="innerHTML"
           class="border border-zinc-900 rounded-lg divide-y divide-zinc-900 min-h-[300px]">
        <div class="p-5 mute text-xs">loading…</div>
      </div>
    </section>

  </div>

  <!-- COIN CONSENSUS -->
  <section>
    <div class="flex items-baseline justify-between mb-3">
      <h2 class="label">Coin Consensus <span class="text-zinc-700 normal-case tracking-normal ml-1">· top-20 positioning</span></h2>
      <span class="mute text-[10px] mono">30s</span>
    </div>
    <div id="consensus"
         hx-get="/api.php?q=consensus"
         hx-trigger="load, every 30s"
         hx-swap="innerHTML"
         class="border border-zinc-900 rounded-lg p-4 min-h-[120px]">
      <div class="mute text-xs">loading…</div>
    </div>
  </section>

</main>

<footer class="border-t border-zinc-900 py-5 mt-12">
  <div class="max-w-6xl mx-auto px-6 text-[11px] text-zinc-600 mono flex justify-between flex-wrap gap-2">
    <span>hl.signals · data from Hyperliquid public API</span>
    <span>not financial advice</span>
  </div>
</footer>

<script>
  // --- helpers ---
  const shortAddr = a => !a ? '—' : a.slice(0, 6) + '…' + a.slice(-4);
  const money = n => {
    const abs = Math.abs(n), sign = n < 0 ? '-' : (n > 0 ? '+' : '');
    if (abs >= 1e6) return `${sign}$${(abs/1e6).toFixed(2)}M`;
    if (abs >= 1e3) return `${sign}$${(abs/1e3).toFixed(1)}k`;
    return `${sign}$${Math.round(abs).toLocaleString()}`;
  };
  const moneyRaw = n => {
    const abs = Math.abs(n);
    if (abs >= 1e6) return `$${(abs/1e6).toFixed(2)}M`;
    if (abs >= 1e3) return `$${(abs/1e3).toFixed(1)}k`;
    return `$${Math.round(abs).toLocaleString()}`;
  };
  const age = ts => {
    if (!ts) return '—';
    const d = Math.floor(Date.now()/1000) - ts;
    if (d < 60) return d + 's';
    if (d < 3600) return Math.floor(d/60) + 'm';
    if (d < 86400) return Math.floor(d/3600) + 'h';
    return Math.floor(d/86400) + 'd';
  };
  const fmtPrice = n => {
    if (!n) return '—';
    if (n >= 1000) return '$' + Number(n).toLocaleString(undefined, {maximumFractionDigits:0});
    if (n >= 1) return '$' + Number(n).toFixed(3);
    return '$' + Number(n).toFixed(5);
  };

  // --- htmx transformer ---
  document.body.addEventListener('htmx:beforeSwap', (e) => {
    const id = e.detail.target.id;
    try {
      const data = JSON.parse(e.detail.serverResponse);
      if (id === 'hero') e.detail.serverResponse = renderHero(data);
      else if (id === 'signals') e.detail.serverResponse = renderSignals(data);
      else if (id === 'traders') e.detail.serverResponse = renderTraders(data);
      else if (id === 'positions') e.detail.serverResponse = renderPositions(data);
      else if (id === 'consensus') e.detail.serverResponse = renderConsensus(data);
    } catch {}
  });

  // --- hero strip ---
  function renderHero(d) {
    if (!d) return '';
    const totalSig = d.signals_long + d.signals_short;
    const totalWhale = d.whale_long_positions + d.whale_short_positions;
    const whaleLongPct = totalWhale > 0 ? Math.round(d.whale_long_positions / totalWhale * 100) : 0;
    const whaleShortPct = 100 - whaleLongPct;
    const whaleMood = totalWhale === 0
      ? { label: '—', class: 'mute' }
      : whaleShortPct >= 60 ? { label: 'Net short', class: 'down' }
      : whaleLongPct  >= 60 ? { label: 'Net long',  class: 'up' }
      : { label: 'Mixed', class: 'text-zinc-300' };
    return `
      <div>
        <div class="hero-num text-zinc-50 mono">${d.traders.toLocaleString()}</div>
        <div class="label mt-1">Traders tracked</div>
      </div>
      <div>
        <div class="hero-num mono"><span class="up">${d.signals_long}▲</span> <span class="down">${d.signals_short}▼</span></div>
        <div class="label mt-1">${totalSig} active signals</div>
      </div>
      <div>
        <div class="hero-num mono ${whaleMood.class}">${whaleMood.label}</div>
        <div class="label mt-1">${whaleLongPct}% long · ${whaleShortPct}% short</div>
      </div>
      <div>
        <div class="hero-num mono up">${moneyRaw(d.top10_pnl_sum)}</div>
        <div class="label mt-1">Top 10 open profit</div>
      </div>
    `;
  }

  // --- signals (featured card + secondary pills) ---
  function renderSignals(rows) {
    if (!rows || !rows.length) return `<div class="border border-dashed border-zinc-800 rounded-lg p-6 text-center mute text-sm">no active consensus signals</div>`;
    const sorted = [...rows].sort((a,b) => (b.consensus_count - a.consensus_count) || (b.total_notional - a.total_notional));
    const top = sorted[0];
    const rest = sorted.slice(1);

    const movePct = (r) => (r.mark_at_signal && r.avg_entry)
      ? ((r.mark_at_signal / r.avg_entry - 1) * 100 * (r.direction === 'long' ? 1 : -1)).toFixed(2) + '%'
      : '';
    const moveCls = (r) => (r.mark_at_signal && r.avg_entry)
      ? ((r.mark_at_signal / r.avg_entry - 1) * (r.direction === 'long' ? 1 : -1) >= 0 ? 'up' : 'down')
      : 'mute';

    const dirLong = top.direction === 'long';
    const cardCls = dirLong
      ? 'block border border-zinc-900 hover:border-emerald-500/40 transition rounded-lg p-5 mb-3 bg-gradient-to-br from-emerald-500/5 to-transparent'
      : 'block border border-zinc-900 hover:border-red-500/40 transition rounded-lg p-5 mb-3 bg-gradient-to-br from-red-500/5 to-transparent';
    const badgeCls = dirLong
      ? 'text-[10px] bg-emerald-500/15 text-emerald-400 px-2 py-1 rounded font-bold uppercase tracking-wider'
      : 'text-[10px] bg-red-500/15 text-red-400 px-2 py-1 rounded font-bold uppercase tracking-wider';
    const numCls = dirLong ? 'mono font-bold text-emerald-400' : 'mono font-bold text-red-400';

    const featured = `
      <a href="/coin.php?c=${top.coin}" class="${cardCls}">
        <div class="flex items-start justify-between gap-6 flex-wrap">
          <div>
            <div class="flex items-center gap-3">
              <span class="mono font-black text-2xl tracking-tight">${top.coin}</span>
              <span class="${badgeCls}">${dirLong ? '▲ LONG' : '▼ SHORT'}</span>
            </div>
            <div class="mt-2 text-sm text-zinc-300">
              <span class="${numCls}">${top.consensus_count}</span>
              <span class="mute"> of top-${top.top_n} ${dirLong ? 'opened longs' : 'opened shorts'} in last 24h</span>
            </div>
            <div class="mono text-xs mute mt-1">avg entry ${fmtPrice(top.avg_entry)}${top.mark_at_signal ? ` · mark ${fmtPrice(top.mark_at_signal)}` : ''}</div>
          </div>
          <div class="text-right shrink-0">
            <div class="mono text-2xl font-bold ${moveCls(top)}">${movePct(top) || '—'}</div>
            <div class="label mt-1">since signal</div>
            <div class="mono text-sm text-zinc-300 mt-3">${moneyRaw(top.total_notional)}</div>
            <div class="label mt-0.5">notional</div>
          </div>
        </div>
      </a>`;

    const pills = rest.length ? `<div class="flex flex-wrap gap-2">` + rest.map(r => {
      const dlong = r.direction === 'long';
      const pillCls = dlong
        ? 'mono text-xs inline-flex items-center gap-2 border border-zinc-800 hover:border-emerald-500/40 bg-zinc-950/40 rounded-md px-3 py-1.5 transition'
        : 'mono text-xs inline-flex items-center gap-2 border border-zinc-800 hover:border-red-500/40 bg-zinc-950/40 rounded-md px-3 py-1.5 transition';
      const arrowCls = dlong ? 'text-emerald-400 font-bold' : 'text-red-400 font-bold';
      return `<a href="/coin.php?c=${r.coin}" class="${pillCls}">
        <span class="${arrowCls}">${dlong ? '▲' : '▼'}</span>
        <span class="font-bold">${r.coin}</span>
        <span class="mute">${r.consensus_count}/${r.top_n}</span>
        <span class="text-zinc-500">${moneyRaw(r.total_notional)}</span>
        ${movePct(r) ? `<span class="${moveCls(r)}">${movePct(r)}</span>` : ''}
      </a>`;
    }).join('') + `</div>` : '';

    return featured + pills;
  }

  // --- top traders table ---
  function renderTraders(rows) {
    if (!rows || !rows.length) return `<div class="p-5 mute text-xs">no data yet</div>`;
    const head = `<table class="w-full text-[13px]">
      <thead class="label">
        <tr class="text-zinc-600">
          <th class="text-left font-semibold px-4 py-2.5 w-8">#</th>
          <th class="text-left font-semibold px-4 py-2.5">Trader</th>
          <th class="text-right font-semibold px-4 py-2.5">PnL</th>
          <th class="text-right font-semibold px-4 py-2.5">Vol</th>
          <th class="text-right font-semibold px-4 py-2.5">Equity</th>
        </tr>
      </thead><tbody class="mono">`;
    const body = rows.slice(0, 12).map((r, i) => {
      const pnl = Number(r.pnl || 0);
      const cls = pnl >= 0 ? 'up' : 'down';
      return `<tr class="border-t border-zinc-900 cursor-pointer" onclick="location.href='/trader.php?addr=${r.address}'">
        <td class="px-4 py-2.5 text-zinc-600">${i+1}</td>
        <td class="px-4 py-2.5">
          <a href="/trader.php?addr=${r.address}" class="text-zinc-100 hover:text-emerald-400 font-medium">${shortAddr(r.address)}</a>
          ${r.label ? ` <span class="text-[10px] mute ml-1">${r.label}</span>` : ''}
        </td>
        <td class="px-4 py-2.5 text-right ${cls} font-semibold">${money(pnl)}</td>
        <td class="px-4 py-2.5 text-right text-zinc-500">${moneyRaw(Number(r.volume||0))}</td>
        <td class="px-4 py-2.5 text-right text-zinc-500">${moneyRaw(Number(r.account_value||0))}</td>
      </tr>`;
    }).join('');
    return head + body + '</tbody></table>';
  }

  // --- top 10 profitable positions ---
  function renderPositions(rows) {
    if (!rows || !rows.length) return `<div class="p-5 mute text-xs">no open positions yet</div>`;
    return rows.map((r, i) => {
      const sideCls = r.side === 'long'
        ? 'bg-emerald-500/15 text-emerald-400'
        : 'bg-red-500/15 text-red-400';
      return `<div class="px-4 py-3 flex items-center gap-4 hover:bg-white/[.02] cursor-pointer transition" onclick="location.href='/trader.php?addr=${r.address}'">
        <div class="mono mute text-xs w-5 shrink-0">${i+1}</div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <a href="/coin.php?c=${r.coin}" class="mono font-bold text-[13px] hover:text-emerald-400" onclick="event.stopPropagation()">${r.coin}</a>
            <span class="text-[9px] ${sideCls} px-1.5 py-0.5 rounded font-bold uppercase tracking-wider">${r.side}</span>
            <span class="mono text-[10px] mute">${Number(r.leverage).toFixed(0)}×</span>
          </div>
          <div class="mono text-[11px] mute mt-0.5 truncate">
            <a href="/trader.php?addr=${r.address}" class="hover:text-emerald-400" onclick="event.stopPropagation()">${shortAddr(r.address)}</a>
            <span class="mx-1 text-zinc-700">·</span>${age(r.updated_at)}
            <span class="mx-1 text-zinc-700">·</span>${fmtPrice(r.entry_px)} → ${fmtPrice(r.mark_px)}
          </div>
        </div>
        <div class="text-right shrink-0">
          <div class="mono up font-bold text-[13px]">${money(Number(r.unrealized_pnl))}</div>
          <div class="mono text-[10px] up">+${Number(r.unrealized_pct).toFixed(1)}%</div>
        </div>
      </div>`;
    }).join('');
  }

  // --- coin consensus bars ---
  function renderConsensus(rows) {
    if (!rows || !rows.length) return `<div class="mute text-xs">not enough top-20 positions yet</div>`;
    return `<div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5">` + rows.map(r => {
      const longs = Number(r.longs);
      const shorts = Number(r.shorts);
      const total = longs + shorts;
      const longPct = total > 0 ? (longs / total * 100) : 0;
      const shortPct = 100 - longPct;
      const mood = Math.abs(longPct - 50) < 15 ? 'text-zinc-400'
                 : longPct > 50 ? 'up' : 'down';
      return `<a href="/coin.php?c=${r.coin}" class="group block">
        <div class="flex items-center justify-between mono text-[11px] mb-1">
          <span class="font-bold text-[13px] text-zinc-200 group-hover:text-emerald-400">${r.coin}</span>
          <span class="${mood}"><span class="up">${longs}▲</span> <span class="mute mx-0.5">·</span> <span class="down">${shorts}▼</span></span>
        </div>
        <div class="flex h-1.5 rounded-sm overflow-hidden bg-zinc-900">
          <div class="bg-emerald-500" style="width:${longPct}%"></div>
          <div class="bg-red-500" style="width:${shortPct}%"></div>
        </div>
      </a>`;
    }).join('') + `</div>`;
  }
</script>

</body>
</html>
