# Plutus donation validator — build & deploy notes

`DonationValidator.hs` is real, idiomatic Plutus (Plutus Tx + the
`plutus-apps` typed-validator scaffolding). It is **not** something that
can be compiled inside this chat sandbox — Plutus needs the Haskell/Nix
toolchain and the `plutus-apps` package set, which are multi-gigabyte and
not installable here. Below is exactly what to do on your own machine.

## What this contract enforces

Donated ADA is locked at a script address instead of going straight to
the charity's wallet. Two things can unlock it:

- **Release** — the charity (`ddBeneficiary`) withdraws the funds, but
  only if they actually receive at least `ddExpectedAmount` lovelace,
  and only before `ddDeadline`.
- **Refund** — after `ddDeadline`, if the charity never claimed it, the
  original donor (`ddDonor`) can take their ADA back.

This is what stops a compromised backend or a malicious frontend from
redirecting funds: even if `save_donation.php` or `offchain.js` were
tampered with, the Cardano node itself refuses to run the transaction
unless these conditions hold, because that logic is executed on-chain by
every validating node, not trusted from application code.

## 1. Set up the toolchain

```bash
# Nix is the supported way to get a reproducible plutus-apps environment
curl -L https://nixos.org/nix/install | sh
git clone https://github.com/input-output-hk/plutus-apps.git
cd plutus-apps
git checkout <a tagged release compatible with your cardano-node version>
nix develop
```

## 2. Project layout

Drop `DonationValidator.hs` into a Cabal package, e.g.:

```
donation-validator/
├── donation-validator.cabal
├── src/
│   └── DonationValidator.hs
└── app/
    └── Serialize.hs      -- writes the compiled script + address to disk
```

Minimal `donation-validator.cabal` dependencies:

```cabal
build-depends:
    base
  , plutus-tx
  , plutus-tx-plugin
  , plutus-ledger
  , plutus-ledger-api
  , plutus-script-utils
  , bytestring
  , cardano-api
```//

## 3. Serialize the compiled script

Add a small `app/Serialize.hs` that calls
`Cardano.Api.Shelley.writeFileTextEnvelope` on
`Scripts.validatorScript donationScript` (via
`PlutusScriptV2` from `cardano-api`) to produce a
`donation-validator.plutus` file — this is the artifact `cardano-cli`
and off-chain tooling deploy.

```bash
cabal run serialize -- --out donation-validator.plutus
```

## 4. Get the script address

```bash
cardano-cli address build \
  --payment-script-file donation-validator.plutus \
  --testnet-magic 1 \
  --out-file donation.addr
```

This is the address `offchain.js` should lock funds into (instead of
paying the charity directly) once you move from the simple "pay charity
directly" flow in this repo to the full escrow flow.

## 5. Locking & unlocking funds (cardano-cli sketch)

```bash
# Lock: pay into the script with an inline datum
cardano-cli transaction build \
  --tx-in <donor-utxo> \
  --tx-out "$(cat donation.addr)+${LOVELACE}" \
  --tx-out-inline-datum-file donation-datum.json \
  --change-address <donor-address> \
  --testnet-magic 1 \
  --out-file lock.raw

# Release: charity spends the script UTXO with the Release redeemer
cardano-cli transaction build \
  --tx-in <script-utxo> \
  --tx-in-script-file donation-validator.plutus \
  --tx-in-inline-datum-present \
  --tx-in-redeemer-file release-redeemer.json \
  --tx-in-collateral <charity-collateral-utxo> \
  --required-signer-hash <charity-pubkeyhash> \
  --tx-out "<charity-address>+${LOVELACE}" \
  --invalid-hereafter <slot-before-deadline> \
  --change-address <charity-address> \
  --testnet-magic 1 \
  --out-file release.raw
```

The `Refund` path mirrors this with the donor's own signature and
`--invalid-before <slot-after-deadline>` instead.

## 6. Where this plugs into the rest of the app

- `backend/verify_transaction.php` confirms a transaction happened and
  paid the right address/amount, using Blockfrost — a convenient,
  off-chain read for the UI.
- **This validator** is the actual enforcement layer: it's what a
  Cardano node checks before accepting the transaction into a block at
  all, regardless of what the PHP backend or JS frontend claim.
- `cardano/offchain.js`'s `donate()` function currently pays the charity
  address directly (the simplest flow to get you running end-to-end).
  To use the escrow contract above, change that function to build a
  transaction locking funds at `donation.addr` with a `DonationDatum`
  instead, and add a second flow for the charity to submit a `Release`
  transaction.
