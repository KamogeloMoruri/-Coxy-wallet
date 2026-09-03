<?php
// ==========================================================
// Cardano payment verification via Blockfrost.io
//
// The frontend builds and submits the ADA transaction directly
// through the user's wallet (see app.js). This file's job is to
// independently confirm, from the server side, that a given
// transaction hash really did pay the expected amount to the
// contest wallet — never trust a "yes I paid" claim from the browser.
// ==========================================================

function blockfrost_get_tx_utxos(string $txHash): ?array {
    $config = require __DIR__ . '/../config/config.php';
    $url = rtrim($config['blockfrost_base_url'], '/') . '/txs/' . urlencode($txHash) . '/utxos';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['project_id: ' . $config['blockfrost_api_key']],
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !$response) {
        return null;
    }
    return json_decode($response, true);
}

/**
 * Confirms a transaction paid at least $expectedLovelace to $expectedAddress.
 */
function blockfrost_verify_payment(string $txHash, string $expectedAddress, int $expectedLovelace): bool {
    $tx = blockfrost_get_tx_utxos($txHash);
    if (!$tx || empty($tx['outputs'])) {
        return false;
    }

    foreach ($tx['outputs'] as $output) {
        if (($output['address'] ?? null) !== $expectedAddress) {
            continue;
        }
        foreach ($output['amount'] ?? [] as $amt) {
            if (($amt['unit'] ?? null) === 'lovelace' && (int)$amt['quantity'] >= $expectedLovelace) {
                return true;
            }
        }
    }
    return false;
}
