<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/lib/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$params = request_params();

$donorName = trim((string) ($params['donorName'] ?? ''));
$amountRaw = (string) ($params['amount'] ?? '');
$purpose   = trim((string) ($params['purpose'] ?? ''));

// --- Validation -------------------------------------------------------
$allowedPurposes = ['Food', 'Education', 'Healthcare', 'Shelter', 'Other'];

if ($donorName === '' || mb_strlen($donorName) > 120) {
    json_error('Donor name is required and must be under 120 characters.');
}
if (!is_numeric($amountRaw) || (float) $amountRaw <= 0) {
    json_error('Amount must be a positive number.');
}
if (!in_array($purpose, $allowedPurposes, true)) {
    json_error('Purpose must be one of: ' . implode(', ', $allowedPurposes));
}

$amount = round((float) $amountRaw, 2);

// --- Persist ------------------------------------------------------------
$pdo = Database::connection();

$stmt = $pdo->prepare(
    'INSERT INTO donations (donor_name, amount, purpose) VALUES (:donor_name, :amount, :purpose)'
);
$stmt->execute([
    ':donor_name' => $donorName,
    ':amount'     => $amount,
    ':purpose'    => $purpose,
]);

json_response([
    'ok' => true,
    'donation' => [
        'id'        => (int) $pdo->lastInsertId(),
        'donorName' => $donorName,
        'amount'    => $amount,
        'purpose'   => $purpose,
    ],
]);
