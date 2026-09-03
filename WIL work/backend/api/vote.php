<?php
// ==========================================================
// POST /api/vote.php
// Body: { "entry_id": 3, "wallet_address": "addr1..." }
//
// A wallet doesn't need to have paid an entry fee to vote — this
// finds or creates the corresponding user record. One vote per
// wallet per entry, enforced both here and by the UNIQUE(entry_id,
// user_id) constraint on the votes table as a second line of defense.
// ==========================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$entryId = (int)($input['entry_id'] ?? 0);
$walletAddress = trim($input['wallet_address'] ?? '');

if (!$entryId || $walletAddress === '') {
    http_response_code(400);
    echo json_encode(['error' => 'entry_id and wallet_address are required']);
    exit;
}

try {
    $pdo = get_db_connection();
    $userId = find_or_create_user($pdo, $walletAddress);

    $stmt = $pdo->prepare('SELECT id FROM votes WHERE entry_id = ? AND user_id = ?');
    $stmt->execute([$entryId, $userId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'You already voted for this entry']);
        exit;
    }

    $pdo->prepare('INSERT INTO votes (entry_id, user_id) VALUES (?, ?)')
        ->execute([$entryId, $userId]);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE entry_id = ?');
    $stmt->execute([$entryId]);
    $newCount = $stmt->fetchColumn();

    echo json_encode(['status' => 'ok', 'vote_count' => (int)$newCount]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error casting vote']);
    error_log($e->getMessage());
}
