PRAGMA foreign_keys = ON;

-- ------------------------------------------------------------
-- users: one row per connected wallet
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    wallet_address TEXT NOT NULL UNIQUE,
    username       TEXT NOT NULL,
    email          TEXT,                       -- added: needed to send entry confirmations & results
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- contests: a contest "round" that entries belong to
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contests (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    title       TEXT NOT NULL,
    description TEXT,
    start_date  TIMESTAMP,
    end_date    TIMESTAMP,
    status      TEXT NOT NULL DEFAULT 'upcoming'
                CHECK (status IN ('upcoming', 'active', 'closed')),
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- entries: a track submitted by a user to a contest
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS entries (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    contest_id   INTEGER NOT NULL REFERENCES contests(id) ON DELETE CASCADE,
    user_id      INTEGER NOT NULL REFERENCES users(id)    ON DELETE CASCADE,
    title        TEXT NOT NULL,
    audio_url    TEXT NOT NULL,
    description  TEXT,
    ai_tool_used TEXT,                          -- added: which AI tool made the track
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_entries_contest ON entries(contest_id);
CREATE INDEX IF NOT EXISTS idx_entries_user    ON entries(user_id);

-- ------------------------------------------------------------
-- votes: one vote per user per entry
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS votes (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id   INTEGER NOT NULL REFERENCES entries(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id)   ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (entry_id, user_id)  -- prevents double-voting
);

CREATE INDEX IF NOT EXISTS idx_votes_entry ON votes(entry_id);

-- ------------------------------------------------------------
-- transactions: ADA payments (entry fees, prizes, donations)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    entry_id     INTEGER REFERENCES entries(id) ON DELETE SET NULL,
    amount_ada   REAL NOT NULL,
    tx_hash      TEXT UNIQUE,                  -- changed to nullable: unknown until the wallet submits the tx
    status       TEXT NOT NULL DEFAULT 'pending'
                 CHECK (status IN ('pending', 'confirmed', 'failed')), -- added: tracks on-chain confirmation
    type         TEXT NOT NULL DEFAULT 'entry_fee'
                 CHECK (type IN ('entry_fee', 'prize_payout', 'donation')),
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP                     -- added: set once Blockfrost confirms the payment
);

CREATE INDEX IF NOT EXISTS idx_transactions_user ON transactions(user_id);

-- ------------------------------------------------------------
-- Sample data (optional — delete if not needed)
-- ------------------------------------------------------------
-- A contest must exist with status = 'active' for payments and
-- entries to be accepted — the API looks up the active round.
INSERT INTO contests (title, description, start_date, end_date, status)
VALUES (
    'Round 1',
    'The first Coxy Wallet AI Music Contest round.',
    CURRENT_TIMESTAMP,
    datetime(CURRENT_TIMESTAMP, '+7 days'),
    'active'
);
