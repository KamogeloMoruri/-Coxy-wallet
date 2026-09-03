<?php
// ==========================================================
// POST /api/create_payment.php
// Body: { "wallet_address": "addr1..." }
//
// Finds/creates the user and opens a "pending" row in the
// transactions table (type = entry_fee). Returns the address +
// amount the frontend should send ADA to.
// ==========================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
$config = require __DIR__ . '/../config/config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$walletAddress = trim($input['wallet_address'] ?? '');

if ($walletAddress === '') {
    http_response_code(400);
    echo json_encode(['error' => 'wallet_address is required']);
    exit;
}

try {
    $pdo = get_db_connection();

    $contestId = get_active_contest_id($pdo);
    if (!$contestId) {
        http_response_code(409);
        echo json_encode(['error' => 'No active contest round right now']);
        exit;
    }

    $userId = find_or_create_user($pdo, $walletAddress);

    $stmt = $pdo->prepare(
        "INSERT INTO transactions (user_id, amount_ada, status, type) VALUES (?, ?, 'pending', 'entry_fee')"
    );
    $stmt->execute([$userId, $config['entry_fee_ada']]);
    $transactionId = $pdo->lastInsertId();

    echo json_encode([
        'transaction_id'  => (int)$transactionId,
        'contest_id'      => $contestId,
        'pay_to_address'  => $config['contest_wallet_address'],
        'amount_lovelace' => $config['entry_fee_lovelace'],
        'amount_ada'      => $config['entry_fee_ada'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error creating payment']);
    error_log($e->getMessage());
}
