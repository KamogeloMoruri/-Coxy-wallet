# AI Donation Tracker — full stack

```
website.html         Frontend page
style.css             Styling
app.js                 Frontend logic — calls backend/*.php and cardano/offchain.js

backend/               PHP backend (private rules, DB, file handling, requests)
  config.php            Env loading, security headers, CORS, JSON helpers
  schema.sql             MySQL schema
  .env.example            Copy to .env and fill in real secrets
  lib/
    Database.php          PDO connection
    BlockfrostClient.php    Server-side, read-only Cardano queries (verification)
  save_donation.php       POST — record a ZAR donation
  get_donations.php        GET  — list donations + running total
  verify_transaction.php    POST — confirm a Cardano tx really paid the charity
  ask_ai.php                POST — donation Q&A via the Claude API

cardano/
  offchain.js             Browser-side: wallet connect (CIP-30), build/sign/submit ADA tx

plutus/
  DonationValidator.hs      On-chain validator: the actual enforcement layer
  PLUTUS_SETUP.md            How to compile & deploy it (needs the Plutus toolchain)
```

## How the pieces fit together

1. **website.html / app.js** — what the donor sees and interacts with.
2. **backend/*.php** — your private server logic: validates input, talks to
   MySQL, and is the only thing holding secrets (DB password, Blockfrost
   key, Anthropic API key). Nothing here can move Cardano funds — it can
   only *read* chain state to verify what already happened.
3. **cardano/offchain.js** — runs in the donor's browser. Connects to
   their wallet extension, builds an ADA transaction, and asks the wallet
   to sign it. The donor's private key never leaves their wallet or
   touches your server.
4. **plutus/DonationValidator.hs** — the actual trust boundary. Once
   donations flow through the escrow address, this is what a Cardano
   node checks before accepting any transaction that spends locked
   funds — regardless of what the PHP backend or frontend JS say.

## Setup

### 1. Database

```bash
mysql -u root -p -e "CREATE DATABASE donation_tracker CHARACTER SET utf8mb4"
mysql -u root -p donation_tracker < backend/schema.sql
```

### 2. Backend secrets

```bash
cp backend/.env.example backend/.env
# then edit backend/.env with your real DB credentials,
# Blockfrost project ID, charity receive address, and Anthropic API key
```

`.env` is read by `backend/config.php` and is never sent to the browser —
keep it out of version control (add `backend/.env` to `.gitignore`).

### 3. Serve it

Any PHP 8.1+ server works, e.g. for local testing:

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/website.html`. Add that origin to
`ALLOWED_ORIGINS` in `.env` if you serve the frontend from a different
host/port than the backend.

### 4. Cardano config

In `app.js`, set:
- `blockfrostProjectId` — a **browser-safe, read-only** Blockfrost key
  (separate from the one in `backend/.env`, or proxy reads through the
  backend if you'd rather not expose any key client-side)
- `charityAddress` — the wallet address donations get paid to

### 5. Plutus contract (optional, for the escrow flow)

The current `offchain.js` pays the charity address directly, which is
enough to get the app running end-to-end. See `plutus/README.md` for how
to compile `DonationValidator.hs` and switch to the full lock/release/
refund escrow flow it enforces.

## Security notes

- All SQL uses prepared statements (`PDO`, no string concatenation).
- Secrets live only in `backend/.env`, loaded server-side.
- CORS is an explicit allowlist (`ALLOWED_ORIGINS`), not `*`.
- `verify_transaction.php` never trusts a donor-submitted tx hash at face
  value — it always re-checks the real payment via Blockfrost before
  marking anything verified.
- Wallet private keys never leave the browser extension; the backend
  never sees them and can't move funds on its own.
