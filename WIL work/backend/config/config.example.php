<?php
// ==========================================================
// Site configuration — EXAMPLE FILE
// Copy this to config.php, fill in real values, and make sure
// config.php is in your .gitignore. Never commit real secrets.
// ==========================================================

return [
    // ---- Database (SQLite) ----
    // Path to your .db file. Keep it OUTSIDE the public web root if
    // possible; if it must live under the web root, block direct
    // HTTP access to it (see the setup guide).
    'db' => [
        'path' => __DIR__ . '/../data/contest.db',
    ],

    // ---- Cardano payments ----
    // The wallet address that receives contest entry fees
    'contest_wallet_address' => 'addr1_your_contest_receiving_address_here',

    'entry_fee_ada'      => 10,
    'entry_fee_lovelace' => 10000000, // 1 ADA = 1,000,000 lovelace

    // Blockfrost.io is used server-side to verify transactions on-chain.
    // Sign up free at https://blockfrost.io
    'blockfrost_api_key'  => 'YOUR_BLOCKFROST_PROJECT_ID',
    'blockfrost_base_url' => 'https://cardano-mainnet.blockfrost.io/api/v0',
    // Swap to https://cardano-preprod.blockfrost.io/api/v0 while testing
    // with the free Cardano preprod testnet.

    // ---- File uploads ----
    'upload_dir'          => __DIR__ . '/../uploads/audio/',
    'max_upload_bytes'    => 25 * 1024 * 1024, // 25MB
    'allowed_audio_types' => ['audio/mpeg', 'audio/wav', 'audio/mp3', 'audio/x-wav'],

    // ---- Email (SMTP via PHPMailer) ----
    'mail' => [
        'host'       => 'smtp.yourprovider.com',
        'port'       => 587,
        'username'   => 'contest@yoursite.com',
        'password'   => 'CHANGE_ME',
        'from_email' => 'contest@yoursite.com',
        'from_name'  => 'Coxy Wallet AI Music Contest',
    ],
];
