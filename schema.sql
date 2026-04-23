PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS traders (
  address TEXT PRIMARY KEY,
  first_seen INTEGER NOT NULL,
  last_active INTEGER NOT NULL,
  role TEXT,
  label TEXT
);

CREATE TABLE IF NOT EXISTS portfolios (
  address TEXT PRIMARY KEY,
  pnl_day REAL DEFAULT 0,
  pnl_week REAL DEFAULT 0,
  pnl_month REAL DEFAULT 0,
  pnl_all REAL DEFAULT 0,
  vlm_day REAL DEFAULT 0,
  vlm_week REAL DEFAULT 0,
  vlm_month REAL DEFAULT 0,
  vlm_all REAL DEFAULT 0,
  account_value REAL DEFAULT 0,
  updated_at INTEGER NOT NULL,
  FOREIGN KEY (address) REFERENCES traders(address)
);

CREATE TABLE IF NOT EXISTS positions (
  address TEXT NOT NULL,
  coin TEXT NOT NULL,
  side TEXT NOT NULL,
  size REAL NOT NULL,
  entry_px REAL NOT NULL,
  mark_px REAL NOT NULL,
  leverage REAL NOT NULL,
  unrealized_pnl REAL NOT NULL,
  unrealized_pct REAL NOT NULL,
  opened_at INTEGER,
  updated_at INTEGER NOT NULL,
  PRIMARY KEY (address, coin),
  FOREIGN KEY (address) REFERENCES traders(address)
);

CREATE INDEX IF NOT EXISTS idx_portfolio_pnl_day ON portfolios(pnl_day DESC);
CREATE INDEX IF NOT EXISTS idx_portfolio_pnl_week ON portfolios(pnl_week DESC);
CREATE INDEX IF NOT EXISTS idx_portfolio_pnl_month ON portfolios(pnl_month DESC);
CREATE INDEX IF NOT EXISTS idx_positions_unrealized ON positions(unrealized_pnl DESC);
CREATE INDEX IF NOT EXISTS idx_positions_updated ON positions(updated_at DESC);

CREATE TABLE IF NOT EXISTS signals (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  created_at INTEGER NOT NULL,
  coin TEXT NOT NULL,
  direction TEXT NOT NULL,
  consensus_count INTEGER NOT NULL,
  top_n INTEGER NOT NULL,
  avg_entry REAL,
  total_notional REAL,
  mark_at_signal REAL,
  status TEXT NOT NULL DEFAULT 'active'
);
CREATE INDEX IF NOT EXISTS idx_signals_created ON signals(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_signals_coin_dir ON signals(coin, direction, status);
