-- AI Donation Tracker — MySQL / MariaDB schema
-- Run once against your database, e.g.:
--   mysql -u root -p donation_tracker < schema.sql

CREATE TABLE IF NOT EXISTS donations (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_name        VARCHAR(120)      NOT NULL,
    amount            DECIMAL(12,2)     NOT NULL,          -- amount in ZAR (Rand)
    purpose           VARCHAR(60)       NOT NULL,
    cardano_tx_hash   CHAR(64)          NULL,              -- set once a Cardano donation is verified
    ada_lovelace      BIGINT UNSIGNED   NULL,               -- on-chain amount, in lovelace (1 ADA = 1,000,000 lovelace)
    verified          TINYINT(1)        NOT NULL DEFAULT 0,
    created_at        TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_tx_hash (cardano_tx_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_queries (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question      TEXT      NOT NULL,
    answer        TEXT      NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- seed row matching the placeholder shown in the original static table
INSERT INTO donations (donor_name, amount, purpose, verified, created_at)
VALUES ('Example: Donor', 500.00, 'Food', 1, '2026-08-31 00:00:00')
ON DUPLICATE KEY UPDATE donor_name = donor_name;
