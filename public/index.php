<?php
declare(strict_types=1);
require __DIR__ . '/../lib/db.php';

$view = $_GET['view'] ?? 'traders';
if (!in_array($view, ['traders', 'signals', 'coins'], true)) $view = 'traders';

$sort = $_GET['sort'] ?? 'score';
if (!in_array($sort, ['day', 'week', 'month', 'score'], true)) $sort = 'score';

$filters = [
    'profitable' => isset($_GET['profitable']),
    'whale' => isset($_GET['whale']),
    'active' => isset($_GET['active']),
    'highlev' => isset($_GET['highlev']),
];

function url_with(array $overrides): string {
    $p = array_merge($_GET, $overrides);
    foreach ($p as $k => $v) if ($v === null || $v === '' || $v === false) unset($p[$k]);
    return '?' . http_build_query($p);
}
function toggle_url(string $key): string {
    $p = $_GET;
    if (isset($p[$key])) unset($p[$key]); else $p[$key] = '1';
    return '?' . http_build_query($p);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HL Signals — follow the whales on Hyperliquid</title>
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<meta name="description" content="Realtime top traders and signals on Hyperliquid.">
<meta name="theme-color" content="#ffffff">
<meta property="og:title" content="HL Signals">
<meta property="og:description" content="Follow the whales on Hyperliquid.">
<meta property="og:image" content="https://talkchaintoday.com/assets/og-image.svg">
<meta name="twitter:card" content="summary_large_image">
<style>
  :root {
    --bg: #ffffff;
    --surface: #fafafa;
    --text: #111;
    --muted: #666;
    --border: #eee;
    --border-strong: #ddd;
    --sky: #3b9eff;
    --green: #2dbe60;
    --red: #ff5a5f;
    --amber: #ffb800;
    --card-shadow: 0 2px 8px rgba(0,0,0,.06);
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 14px;
    line-height: 1.4;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
  }
  a { color: inherit; text-decoration: none; }
  a:hover { text-decoration: underline; }
  .num { font-variant-numeric: tabular-nums; }

  /* TOP BAR */
  .topbar {
    height: 52px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 0 16px;
    background: var(--bg);
    position: sticky; top: 0; z-index: 10;
  }
  .logo { font-weight: 800; font-size: 16px; letter-spacing: -.02em; }
  .logo .dot { color: var(--green); }
  .tabs { display: flex; gap: 2px; }
  .tab {
    padding: 6px 12px; border-radius: 6px; font-size: 14px;
    color: var(--muted); font-weight: 600;
  }
  .tab:hover { background: #f4f4f4; text-decoration: none; }
  .tab.active { background: #111; color: #fff; }
  .topbar .live { margin-left: auto; font-size: 12px; color: var(--muted); }
  .topbar .live .pulse {
    display: inline-block; width: 6px; height: 6px; border-radius: 50%;
    background: var(--green); margin-right: 6px; animation: p 1.6s infinite;
    vertical-align: middle;
  }
  @keyframes p { 0%,100% { opacity: 1 } 50% { opacity: .3 } }

  /* LAYOUT */
  .shell { display: grid; grid-template-columns: 240px 1fr; min-height: calc(100vh - 52px); }
  .sidebar {
    border-right: 1px solid var(--border);
    padding: 16px 14px;
    position: sticky; top: 52px; align-self: start;
    height: calc(100vh - 52px); overflow-y: auto;
  }
  .main { padding: 14px; }

  /* SIDEBAR */
  .sb-section { margin-bottom: 16px; }
  .sb-head {
    font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
    color: var(--muted); margin-bottom: 8px;
  }
  .chips { display: flex; flex-wrap: wrap; gap: 6px; }
  .chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 10px; border-radius: 999px; font-size: 12.5px;
    border: 1px solid var(--border-strong); background: #fff; color: var(--text);
    font-weight: 600; cursor: pointer;
  }
  .chip:hover { background: var(--surface); text-decoration: none; }
  .chip.active { background: #111; color: #fff; border-color: #111; }
  .chip .em { font-size: 14px; }

  /* STATS ROW */
  .stats {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;
    margin-bottom: 16px;
  }
  .stat {
    background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
    padding: 10px 12px;
  }
  .stat .v { font-size: 20px; font-weight: 700; letter-spacing: -.01em; line-height: 1.1; }
  .stat .l { font-size: 11px; color: var(--muted); margin-top: 2px; font-weight: 600; }

  /* GRID OF CARDS */
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 10px;
  }
  .card {
    background: #fff; border: 1px solid var(--border); border-radius: 10px;
    padding: 12px; box-shadow: var(--card-shadow);
    display: flex; flex-direction: column; gap: 8px;
    position: relative;
    transition: transform .08s, box-shadow .08s;
  }
  .card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); text-decoration: none; transform: translateY(-1px); }
  .card .head {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
  }
  .card .id {
    display: flex; align-items: center; gap: 8px; min-width: 0;
  }
  .card .emoji { font-size: 20px; line-height: 1; }
  .card .title { font-weight: 700; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .card .sub { font-size: 11px; color: var(--muted); }
  .pill {
    flex-shrink: 0;
    font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px;
    color: #fff;
  }
  .pill.green { background: var(--green); }
  .pill.red { background: var(--red); }
  .pill.amber { background: var(--amber); color: #111; }
  .pill.sky { background: var(--sky); }
  .pill.ghost { background: transparent; color: var(--muted); border: 1px solid var(--border-strong); }

  .rows { display: flex; flex-direction: column; gap: 4px; }
  .row {
    display: flex; align-items: baseline; justify-content: space-between; gap: 8px;
    font-size: 12.5px;
  }
  .row .l { color: var(--muted); display: inline-flex; align-items: center; gap: 5px; }
  .row .v { font-weight: 700; font-variant-numeric: tabular-nums; }
  .row .v.up { color: var(--green); }
  .row .v.down { color: var(--red); }
  .row .v.mute { color: var(--muted); font-weight: 600; }

  .bar { height: 6px; background: #f0f0f0; border-radius: 3px; overflow: hidden; display: flex; }
  .bar .seg-long { background: var(--green); }
  .bar .seg-short { background: var(--red); }
  .bar-labels { display: flex; justify-content: space-between; font-size: 11px; margin-top: 3px; }

  /* FOOTER */
  footer {
    border-top: 1px solid var(--border); padding: 14px 16px;
    font-size: 11.5px; color: var(--muted);
    display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px;
  }

  /* MOBILE */
  @media (max-width: 820px) {
    .shell { grid-template-columns: 1fr; }
    .sidebar { position: static; height: auto; border-right: 0; border-bottom: 1px solid var(--border); }
    .stats { grid-template-columns: repeat(2, 1fr); }
    .grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; }
    .topbar { padding: 0 12px; gap: 8px; }
    .tab { padding: 6px 8px; font-size: 13px; }
  }
</style>
</head>
<body>

<header class="topbar">
  <a href="/" class="logo">🚀 HL<span class="dot">.</span>SIGNALS</a>
  <nav class="tabs">
    <a class="tab <?= $view === 'traders' ? 'active' : '' ?>" href="<?= url_with(['view' => 'traders']) ?>">🏆 Traders</a>
    <a class="tab <?= $view === 'signals' ? 'active' : '' ?>" href="<?= url_with(['view' => 'signals']) ?>">🔥 Signals</a>
    <a class="tab <?= $view === 'coins'   ? 'active' : '' ?>" href="<?= url_with(['view' => 'coins']) ?>">🪙 Coins</a>
  </nav>
  <span class="live"><span class="pulse"></span>live</span>
</header>

<div class="shell">

  <aside class="sidebar">
    <div class="sb-section">
      <div class="sb-head">🔽 Sort by</div>
      <div class="chips">
        <?php foreach (['score' => ['🎯','Score'], 'day' => ['⚡','24h PnL'], 'week' => ['📅','7d PnL'], 'month' => ['📆','30d PnL']] as $k => [$em,$lbl]): ?>
          <a class="chip <?= $sort === $k ? 'active' : '' ?>" href="<?= url_with(['sort' => $k]) ?>"><span class="em"><?= $em ?></span><?= $lbl ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($view === 'traders'): ?>
    <div class="sb-section">
      <div class="sb-head">🎛 Filters</div>
      <div class="chips">
        <a class="chip <?= $filters['profitable'] ? 'active' : '' ?>" href="<?= toggle_url('profitable') ?>"><span class="em">✅</span>Profitable 30d</a>
        <a class="chip <?= $filters['whale']      ? 'active' : '' ?>" href="<?= toggle_url('whale') ?>"><span class="em">🐋</span>Whale ≥ $1M</a>
        <a class="chip <?= $filters['active']     ? 'active' : '' ?>" href="<?= toggle_url('active') ?>"><span class="em">🔥</span>Active 24h</a>
        <a class="chip <?= $filters['highlev']    ? 'active' : '' ?>" href="<?= toggle_url('highlev') ?>"><span class="em">🎲</span>High leverage</a>
      </div>
    </div>
    <?php endif; ?>

    <div class="sb-section" id="livestats">
      <div class="sb-head">📡 Live</div>
      <div class="rows" style="font-size: 12px;">
        <div class="row"><span class="l"><span>👥</span>traders</span><span class="v num" data-s="traders">—</span></div>
        <div class="row"><span class="l"><span>🔥</span>signals</span><span class="v num" data-s="sig">—</span></div>
        <div class="row"><span class="l"><span>📊</span>positions</span><span class="v num" data-s="pos">—</span></div>
        <div class="row"><span class="l"><span>💰</span>top10 pnl</span><span class="v up num" data-s="pnl">—</span></div>
        <div class="row"><span class="l"><span>⚖️</span>whales</span><span class="v num" data-s="mood">—</span></div>
      </div>
    </div>

    <div class="sb-section">
      <div class="sb-head">ℹ️ About</div>
      <div style="font-size: 12px; color: var(--muted); line-height: 1.5;">
        Tracks active traders on Hyperliquid. Ranks by composite score. Emits signals when ≥8 of top-20 pile into the same coin within 24h.
      </div>
    </div>
  </aside>

  <main class="main">

    <!-- HERO STATS STRIP -->
    <section class="stats">
      <div class="stat"><div class="v num" id="s-traders">—</div><div class="l">👥 traders tracked</div></div>
      <div class="stat"><div class="v num" id="s-signals">—</div><div class="l">🔥 active signals</div></div>
      <div class="stat"><div class="v num" id="s-mood">—</div><div class="l">⚖️ whale positioning</div></div>
      <div class="stat"><div class="v num" id="s-pnl" style="color: var(--green);">—</div><div class="l">💰 top 10 open profit</div></div>
    </section>

    <!-- GRID -->
    <section id="grid" class="grid" data-view="<?= $view ?>" data-sort="<?= $sort ?>" data-filters="<?= htmlspecialchars(json_encode($filters)) ?>">
      <div style="grid-column: 1 / -1; color: var(--muted); padding: 20px; font-size: 13px;">loading…</div>
    </section>

  </main>
</div>

<footer>
  <div>🛠 hl.signals · data from Hyperliquid public API · built with PHP + SQLite</div>
  <div>not financial advice · DYOR</div>
</footer>

<script>
  const VIEW = document.getElementById('grid').dataset.view;
  const SORT = document.getElementById('grid').dataset.sort;
  const FILTERS = JSON.parse(document.getElementById('grid').dataset.filters);

  // -------- helpers --------
  const shortAddr = a => a ? a.slice(0,6) + '…' + a.slice(-4) : '—';
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
  const fmtPrice = n => {
    if (!n) return '—';
    if (n >= 1000) return '$' + Math.round(n).toLocaleString();
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
  const whaleTier = equity => equity >= 10e6 ? '🐋' : equity >= 1e6 ? '🦈' : equity >= 100e3 ? '🐟' : '🦐';
  const scoreColor = pnl => pnl > 0 ? 'green' : pnl < 0 ? 'red' : 'ghost';

  // -------- load hero stats --------
  async function loadHero() {
    const r = await fetch('/api.php?q=hero').then(r => r.json());
    document.getElementById('s-traders').textContent = r.traders.toLocaleString();
    document.getElementById('s-signals').innerHTML = `<span style="color: var(--green)">${r.signals_long}▲</span> <span style="color: var(--red)">${r.signals_short}▼</span>`;
    const wl = r.whale_long_positions, ws = r.whale_short_positions, total = wl + ws;
    const longPct = total > 0 ? Math.round(wl / total * 100) : 0;
    const mood = total === 0 ? '—'
      : longPct >= 60 ? `long ${longPct}%`
      : longPct <= 40 ? `short ${100-longPct}%`
      : `mixed`;
    const moodEl = document.getElementById('s-mood');
    moodEl.textContent = mood;
    moodEl.style.color = longPct >= 60 ? 'var(--green)' : longPct <= 40 ? 'var(--red)' : 'var(--text)';
    document.getElementById('s-pnl').textContent = '+' + moneyRaw(r.top10_pnl_sum);

    // sidebar mini stats
    const sb = document.querySelectorAll('#livestats [data-s]');
    sb.forEach(el => {
      const k = el.dataset.s;
      if (k === 'traders') el.textContent = r.traders.toLocaleString();
      if (k === 'sig') el.textContent = `${r.signals_long}▲ ${r.signals_short}▼`;
      if (k === 'pnl') el.textContent = '+' + moneyRaw(r.top10_pnl_sum);
      if (k === 'mood') el.textContent = mood;
    });
  }

  async function loadPositionsCount() {
    // light call: count positions from coin consensus endpoint isn't ideal; just skip
  }

  // -------- trader cards --------
  async function loadTraders() {
    const res = await fetch('/api.php?q=traders&w=' + SORT).then(r => r.json());
    const rows = res;
    const windowLabel = SORT === 'score' ? 'Score' : SORT === 'day' ? '24h' : SORT === 'week' ? '7d' : '30d';

    // apply front-end filters (we already filter on backend for score; here tweak)
    const filtered = rows.filter(r => {
      if (FILTERS.profitable && Number(r.pnl) <= 0) return false;
      if (FILTERS.whale && Number(r.account_value || 0) < 1e6) return false;
      return true;
    });

    document.getElementById('grid').innerHTML = filtered.map((r, i) => {
      const pnl = Number(r.pnl || 0);
      const equity = Number(r.account_value || 0);
      const vol = Number(r.volume || 0);
      const em = whaleTier(equity);
      const pillColor = scoreColor(pnl);
      const pillText = money(pnl);
      return `<a class="card" href="/trader.php?addr=${r.address}">
        <div class="head">
          <div class="id">
            <span class="emoji">${em}</span>
            <div style="min-width:0">
              <div class="title num">${shortAddr(r.address)}</div>
              <div class="sub">#${i+1} · ${windowLabel}</div>
            </div>
          </div>
          <span class="pill ${pillColor}">${pillText}</span>
        </div>
        <div class="rows">
          <div class="row"><span class="l"><span>💰</span>Equity</span><span class="v num">${moneyRaw(equity)}</span></div>
          <div class="row"><span class="l"><span>📊</span>Volume</span><span class="v num mute">${moneyRaw(vol)}</span></div>
          <div class="row"><span class="l"><span>📈</span>PnL 7d</span><span class="v num ${Number(r.pnl_week||0) >= 0 ? 'up' : 'down'}">${r.pnl_week !== undefined ? money(Number(r.pnl_week)) : '—'}</span></div>
          ${r.score !== undefined ? `<div class="row"><span class="l"><span>🎯</span>Score</span><span class="v num mute">${Math.round(r.score).toLocaleString()}</span></div>` : ''}
        </div>
      </a>`;
    }).join('') || `<div style="grid-column: 1 / -1; color: var(--muted); padding: 20px; font-size: 13px;">no traders match the current filters</div>`;
  }

  // -------- signal cards --------
  async function loadSignals() {
    const rows = await fetch('/api.php?q=signals').then(r => r.json());
    if (!rows || !rows.length) {
      document.getElementById('grid').innerHTML = `<div style="grid-column: 1 / -1; color: var(--muted); padding: 20px; font-size: 13px;">no active signals · check back in a few minutes</div>`;
      return;
    }
    document.getElementById('grid').innerHTML = rows.map(r => {
      const isLong = r.direction === 'long';
      const em = isLong ? '🚀' : '🔻';
      const pillColor = isLong ? 'green' : 'red';
      const move = (r.mark_at_signal && r.avg_entry)
        ? ((r.mark_at_signal / r.avg_entry - 1) * 100 * (isLong ? 1 : -1))
        : null;
      const moveStr = move !== null ? `${move >= 0 ? '+' : ''}${move.toFixed(2)}%` : '—';
      const moveCls = move !== null ? (move >= 0 ? 'up' : 'down') : 'mute';
      return `<a class="card" href="/coin.php?c=${r.coin}">
        <div class="head">
          <div class="id">
            <span class="emoji">${em}</span>
            <div style="min-width:0">
              <div class="title">${r.coin}</div>
              <div class="sub">${isLong ? '▲ LONG' : '▼ SHORT'} · ${age(r.created_at)} ago</div>
            </div>
          </div>
          <span class="pill ${pillColor}">${r.consensus_count}/${r.top_n}</span>
        </div>
        <div class="rows">
          <div class="row"><span class="l"><span>🎯</span>Consensus</span><span class="v num">${r.consensus_count} of top-${r.top_n}</span></div>
          <div class="row"><span class="l"><span>💰</span>Notional</span><span class="v num">${moneyRaw(r.total_notional)}</span></div>
          <div class="row"><span class="l"><span>📍</span>Avg entry</span><span class="v num mute">${fmtPrice(r.avg_entry)}</span></div>
          <div class="row"><span class="l"><span>📊</span>Since signal</span><span class="v num ${moveCls}">${moveStr}</span></div>
        </div>
      </a>`;
    }).join('');
  }

  // -------- coin cards --------
  async function loadCoins() {
    const rows = await fetch('/api.php?q=consensus').then(r => r.json());
    if (!rows || !rows.length) {
      document.getElementById('grid').innerHTML = `<div style="grid-column: 1 / -1; color: var(--muted); padding: 20px; font-size: 13px;">no consensus data yet</div>`;
      return;
    }
    document.getElementById('grid').innerHTML = rows.map(r => {
      const longs = Number(r.longs), shorts = Number(r.shorts), total = longs + shorts;
      const longPct = total > 0 ? (longs / total * 100) : 0;
      const shortPct = 100 - longPct;
      const biased = longPct >= 70 ? { em: '🚀', pill: 'green', label: 'Long-biased' }
                   : shortPct >= 70 ? { em: '🔻', pill: 'red', label: 'Short-biased' }
                   : { em: '⚖️', pill: 'ghost', label: 'Mixed' };
      return `<a class="card" href="/coin.php?c=${r.coin}">
        <div class="head">
          <div class="id">
            <span class="emoji">${biased.em}</span>
            <div style="min-width:0">
              <div class="title">${r.coin}</div>
              <div class="sub">${biased.label}</div>
            </div>
          </div>
          <span class="pill ${biased.pill}">${longs}▲ ${shorts}▼</span>
        </div>
        <div>
          <div class="bar">
            <div class="seg-long" style="width:${longPct}%"></div>
            <div class="seg-short" style="width:${shortPct}%"></div>
          </div>
          <div class="bar-labels"><span style="color: var(--green)">${longs} long</span><span style="color: var(--red)">${shorts} short</span></div>
        </div>
        <div class="rows">
          <div class="row"><span class="l"><span>👥</span>Total</span><span class="v num">${total} traders</span></div>
          <div class="row"><span class="l"><span>📈</span>Long %</span><span class="v num up">${Math.round(longPct)}%</span></div>
          <div class="row"><span class="l"><span>📉</span>Short %</span><span class="v num down">${Math.round(shortPct)}%</span></div>
        </div>
      </a>`;
    }).join('');
  }

  // -------- boot --------
  async function loadView() {
    if (VIEW === 'traders') await loadTraders();
    else if (VIEW === 'signals') await loadSignals();
    else if (VIEW === 'coins') await loadCoins();
  }

  loadHero();
  loadView();
  setInterval(loadHero, 30000);
  setInterval(loadView, VIEW === 'signals' ? 60000 : 30000);
</script>

</body>
</html>
