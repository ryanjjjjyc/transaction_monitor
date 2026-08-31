CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    username TEXT NOT NULL UNIQUE,
    api_key TEXT NOT NULL UNIQUE,
    balance DECIMAL(10, 2) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS transactions (
    id INTEGER PRIMARY KEY,
    from_user_id INTEGER NOT NULL,
    to_user_id INTEGER NOT NULL,
    amount DECIMAL(10, 2) DEFAULT 0,
    currency TEXT DEFAULT 'EUR',
    created_at TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'completed',
    reason_flagged TEXT,
    ml_score REAL,
    FOREIGN KEY (from_user_id) REFERENCES users(id),
    FOREIGN KEY (to_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS ml_features (
    id INTEGER PRIMARY KEY,
    transaction_id INTEGER NOT NULL UNIQUE,
    amount DECIMAL(10, 2) DEFAULT 0,
    hour_of_day INTEGER,
    day_of_week INTEGER,
    sender_avg_amount_7d DECIMAL(10, 2),
    sender_tx_count_1h INTEGER,
    receiver_avg_amount_7d DECIMAL(10, 2),
    amount_ratio_to_avg DECIMAL(10, 2),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id)
);

CREATE TABLE IF NOT EXISTS labels (
    transaction_id INTEGER PRIMARY KEY,
    label INTEGER NOT NULL CHECK (label IN (0,1)),
    labeled_at TEXT NOT NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id)
);

CREATE TABLE IF NOT EXISTS monitoring_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_id INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',  -- pending, processing, done
    FOREIGN KEY (transaction_id) REFERENCES transactions(id)
);