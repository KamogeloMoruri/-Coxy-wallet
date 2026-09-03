<?php
declare(strict_types=1);

/**
 * Minimal read-only client for the Blockfrost API.
 *
 * This is deliberately read-only: it looks up transactions, UTXOs and
 * addresses to *verify* what has already happened on-chain. Building and
 * signing a transaction requires a wallet's private key, which must never
 * touch the server — that happens client-side in cardano/offchain.js via
 * the user's own wallet (CIP-30), and this backend only confirms the
 * result afterwards.
 *
 * Docs: https://docs.blockfrost.io/
 */
final class BlockfrostClient
{
    private string $baseUrl;
    private string $projectId;

    public function __construct(?string $projectId = null, ?string $network = null)
    {
        $this->projectId = $projectId ?? (env('BLOCKFROST_PROJECT_ID') ?? '');
        $network = $network ?? (env('BLOCKFROST_NETWORK', 'preprod') ?? 'preprod');

        $this->baseUrl = match ($network) {
            'mainnet' => 'https://cardano-mainnet.blockfrost.io/api/v0',
            'preview' => 'https://cardano-preview.blockfrost.io/api/v0',
            default   => 'https://cardano-preprod.blockfrost.io/api/v0',
        };

        if ($this->projectId === '') {
            throw new RuntimeException('BLOCKFROST_PROJECT_ID is not configured.');
        }
    }

    /**
     * Fetch a transaction's top-level details (block, fees, confirmations).
     * Returns null if the transaction hash doesn't exist (yet).
     */
    public function getTransaction(string $txHash): ?array
    {
        return $this->get("/txs/{$txHash}");
    }

    /** Fetch the inputs/outputs (UTXOs) of a transaction. */
    public function getTransactionUtxos(string $txHash): ?array
    {
        return $this->get("/txs/{$txHash}/utxos");
    }

    /** Fetch metadata attached to a transaction (e.g. donor/purpose labels). */
    public function getTransactionMetadata(string $txHash): ?array
    {
        return $this->get("/txs/{$txHash}/metadata");
    }

    /**
     * Confirms that a transaction paid at least `expectedLovelace` to
     * `expectedAddress`. Used by verify_transaction.php to cross-check a
     * donor-submitted tx hash against what actually happened on-chain,
     * rather than trusting client input blindly.
     */
    public function verifyPayment(string $txHash, string $expectedAddress, int $expectedLovelace): array
    {
        $tx = $this->getTransaction($txHash);
        if ($tx === null) {
            return ['confirmed' => false, 'reason' => 'Transaction not found on-chain yet.'];
        }

        $utxos = $this->getTransactionUtxos($txHash);
        if ($utxos === null) {
            return ['confirmed' => false, 'reason' => 'Could not read transaction outputs.'];
        }

        $paidLovelace = 0;
        foreach ($utxos['outputs'] ?? [] as $output) {
            if (($output['address'] ?? '') !== $expectedAddress) {
                continue;
            }
            foreach ($output['amount'] ?? [] as $amount) {
                if (($amount['unit'] ?? '') === 'lovelace') {
                    $paidLovelace += (int) $amount['quantity'];
                }
            }
        }

        if ($paidLovelace < $expectedLovelace) {
            return [
                'confirmed' => false,
                'reason' => "On-chain amount ({$paidLovelace} lovelace) is less than expected ({$expectedLovelace}).",
            ];
        }

        return [
            'confirmed'      => true,
            'block_height'   => $tx['block_height'] ?? null,
            'paid_lovelace'  => $paidLovelace,
        ];
    }

    private function get(string $path): ?array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ["project_id: {$this->projectId}"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            error_log("[Blockfrost] cURL error on {$path}: {$curlError}");
            return null;
        }
        if ($status === 404) {
            return null;
        }
        if ($status >= 400) {
            error_log("[Blockfrost] HTTP {$status} on {$path}: {$body}");
            return null;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
