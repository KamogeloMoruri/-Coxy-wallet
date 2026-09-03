<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/BlockfrostClient.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$params = request_params();

$txHash      = trim((string) ($params['transactionId'] ?? ''));
$donationId  = isset($params['donationId']) ? (int) $params['donationId'] : null;

// Cardano tx hashes are 64 hex characters.
if (!preg_match('/^[0-9a-fA-F]{64}$/', $txHash)) {
    json_error('That doesn\'t look like a valid Cardano transaction ID (expected 64 hex characters).');
}

$charityAddress = env('CHARITY_RECEIVE_ADDRESS', '');
if (!$charityAddress) {
    json_error('Server is missing CHARITY_RECEIVE_ADDRESS configuration.', 500);
}

try {
    $blockfrost = new BlockfrostClient();
} catch (RuntimeException $e) {
    json_error($e->getMessage(), 500);
}

// A donation of 0 lovelace expected just means "any payment to the charity
// address counts" — the real enforcement of exact amounts lives on-chain in
// the Plutus validator (see /plutus/DonationValidator.hs), this check is a
// second, off-chain confirmation for the UI/records.
$result = $blockfrost->verifyPayment($txHash, $charityAddress, 0);

if (!$result['confirmed']) {
    json_response(['ok' => false, 'verified' => false, 'reason' => $result['reason']], 200);
}

$pdo = Database::connection();

if ($donationId !== null) {
    $stmt = $pdo->prepare(
        'UPDATE donations
         SET cardano_tx_hash = :tx_hash, ada_lovelace = :lovelace, verified = 1
         WHERE id = :id'
    );
    $stmt->execute([
        ':tx_hash'  => $txHash,
        ':lovelace' => $result['paid_lovelace'],
        ':id'       => $donationId,
    ]);
}

json_response([
    'ok'            => true,
    'verified'      => true,
    'blockHeight'   => $result['block_height'],
    'paidLovelace'  => $result['paid_lovelace'],
]);
