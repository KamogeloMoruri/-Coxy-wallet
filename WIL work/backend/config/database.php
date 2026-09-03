<?php
// ==========================================================
// Database connection (PDO, SQLite)
// ==========================================================

function get_db_connection(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $dbPath = $config['db']['path'];

    if (!is_dir(dirname($dbPath))) {
        mkdir(dirname($dbPath), 0755, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // SQLite doesn't enforce foreign keys unless told to, per connection
    $pdo->exec('PRAGMA foreign_keys = ON;');

    return $pdo;
}

/**
 * Returns the id of the current active contest round, or null if
 * none is marked 'active'. Payments and entries are always tied
 * to this round.
 */
function get_active_contest_id(PDO $pdo): ?int {
    $stmt = $pdo->query("SELECT id FROM contests WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

/**
 * Finds a user by wallet address, or creates one with a default
 * username derived from the address. Returns the user's id.
 */
function find_or_create_user(PDO $pdo, string $walletAddress, ?string $email = null): int {
    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE wallet_address = ?');
    $stmt->execute([$walletAddress]);
    $user = $stmt->fetch();

    if ($user) {
        if ($email && empty($user['email'])) {
            $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $user['id']]);
        }
        return (int)$user['id'];
    }

    $defaultUsername = 'wallet_' . substr($walletAddress, -6);
    $pdo->prepare('INSERT INTO users (wallet_address, username, email) VALUES (?, ?, ?)')
        ->execute([$walletAddress, $defaultUsername, $email]);

    return (int)$pdo->lastInsertId();
}
