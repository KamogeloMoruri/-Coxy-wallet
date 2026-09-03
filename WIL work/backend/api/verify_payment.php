<?php
// ==========================================================
// POST /api/verify_payment.php
// Body: { "transaction_id": 12, "tx_hash": "abc123..." }
//
// Called after the frontend wallet signs and submits the ADA
// transaction. Independently checks the chain (via Blockfrost)
// before marking the transaction confirmed.
// ==========================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/blockfrost.php';
$config = require __DIR__ . '/../config/config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$transactionId = (int)($input['transaction_id'] ?? 0);
$txHash = trim($input['tx_hash'] ?? '');

if (!$transactionId || $txHash === '') {
    http_response_code(400);
    echo json_encode(['error' => 'transaction_id and tx_hash are required']);
    exit;
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE id = ?');
    $stmt->execute([$transactionId]);
    $txRow = $stmt->fetch();

    if (!$txRow) {
        http_response_code(404);
        echo json_encode(['error' => 'Transaction not found']);
        exit;
    }

    if ($txRow['status'] === 'confirmed') {
        echo json_encode(['status' => 'confirmed']);
        exit;
    }

    $verified = blockfrost_verify_payment(
        $txHash,
        $config['contest_wallet_address'],
        $config['entry_fee_lovelace']
    );

    if ($verified) {
        $pdo->prepare(
            "UPDATE transactions SET tx_hash = ?, status = 'confirmed', confirmed_at = CURRENT_TIMESTAMP WHERE id = ?"
        )->execute([$txHash, $transactionId]);
        echo json_encode(['status' => 'confirmed']);
    } else {
        // Not necessarily failed — the tx may just not have settled on
        // chain yet. The frontend polls this endpoint every few seconds.
        echo json_encode(['status' => 'pending']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error verifying payment']);
    error_log($e->getMessage());
}
