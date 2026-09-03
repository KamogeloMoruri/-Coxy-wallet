/**
 * cardano/offchain.js
 * ---------------------------------------------------------------------
 * Off-chain logic for Cardano donations, run in the browser.
 *
 * Why in the browser and not PHP: building a transaction is fine to do
 * anywhere, but *signing* one requires a private key. That key must stay
 * inside the donor's own wallet extension (Nami, Eternl, Lace, etc.) and
 * never touch our server. So the flow is:
 *
 *   1. Connect to the donor's wallet via the CIP-30 browser API.
 *   2. Read their UTXOs / balance (off-chain read).
 *   3. Build an unsigned transaction paying the charity address, with
 *      donor + purpose recorded as transaction metadata.
 *   4. Ask the wallet to sign it (donor approves in their wallet popup).
 *   5. Submit the signed transaction to the chain via Blockfrost.
 *   6. Tell our PHP backend the resulting tx hash, so it can verify the
 *      payment server-side (see backend/verify_transaction.php) and the
 *      Plutus validator can enforce the on-chain rules
 *      (see plutus/DonationValidator.hs).
 *
 * Dependencies (load these via <script> tags before this file, or bundle
 * with a tool like Vite/Webpack):
 *   - @emurgo/cardano-serialization-lib-browser
 *     https://github.com/Emurgo/cardano-serialization-lib
 *
 * This module intentionally does its own minimal UTXO selection and fee
 * handling for clarity. For production use, consider a higher-level
 * library such as Lucid (https://lucid.spacebudz.io) or Mesh
 * (https://meshjs.dev), which handle protocol-parameter edge cases this
 * simplified version does not.
 */

const CardanoOffChain = (() => {
  // Populated by init(); avoids hardcoding network/API details in this file.
  let config = {
    network: 'preprod',          // 'mainnet' | 'preprod' | 'preview'
    blockfrostProjectId: null,   // browser-safe *read-only* project id
    charityAddress: null,        // where donations get sent
    backendBaseUrl: '/backend',  // our PHP backend
  };

  function init(userConfig) {
    config = { ...config, ...userConfig };
  }

  function blockfrostBaseUrl() {
    switch (config.network) {
      case 'mainnet': return 'https://cardano-mainnet.blockfrost.io/api/v0';
      case 'preview': return 'https://cardano-preview.blockfrost.io/api/v0';
      default:        return 'https://cardano-preprod.blockfrost.io/api/v0';
    }
  }

  async function blockfrostGet(path) {
    const res = await fetch(blockfrostBaseUrl() + path, {
      headers: { project_id: config.blockfrostProjectId },
    });
    if (!res.ok) {
      throw new Error(`Blockfrost ${path} failed: ${res.status}`);
    }
    return res.json();
  }

  // -----------------------------------------------------------------
  // Wallet connection (CIP-30)
  // -----------------------------------------------------------------

  /** Lists wallet extensions installed in this browser (nami, eternl, lace, ...). */
  function availableWallets() {
    if (typeof window === 'undefined' || !window.cardano) return [];
    return Object.keys(window.cardano).filter((key) => window.cardano[key]?.enable);
  }

  /** Connects to a named wallet and returns its CIP-30 API object. */
  async function connectWallet(walletName) {
    if (!window.cardano || !window.cardano[walletName]) {
      throw new Error(`Wallet "${walletName}" was not found. Is the extension installed?`);
    }
    const api = await window.cardano[walletName].enable();
    return api;
  }

  /** Reads the connected wallet's balance, in lovelace. */
  async function getBalanceLovelace(api) {
    const CSL = window.CardanoWasm; // from cardano-serialization-lib-browser
    const balanceCbor = await api.getBalance();
    const value = CSL.Value.from_bytes(hexToBytes(balanceCbor));
    return BigInt(value.coin().to_str());
  }

  // -----------------------------------------------------------------
  // Building & submitting a donation transaction
  // -----------------------------------------------------------------

  /**
   * Builds, has the wallet sign, and submits a donation transaction.
   *
   * @param {object} api          CIP-30 wallet API from connectWallet()
   * @param {number} adaAmount    Amount to donate, in ADA (not lovelace)
   * @param {string} donorName
   * @param {string} purpose
   * @returns {Promise<string>}   The submitted transaction hash
   */
  async function donate(api, adaAmount, donorName, purpose) {
    const CSL = window.CardanoWasm;
    if (!CSL) {
      throw new Error('cardano-serialization-lib-browser must be loaded before offchain.js');
    }
    if (!config.charityAddress) {
      throw new Error('CardanoOffChain.init() must be called with a charityAddress first');
    }

    const lovelaceToSend = BigInt(Math.round(adaAmount * 1_000_000));

    // 1. Fetch protocol parameters (fees, min UTXO, etc.) — needed for a
    //    correctly-built transaction. Read-only, so safe from the browser.
    const params = await blockfrostGet('/epochs/latest/parameters');

    const txBuilderConfig = CSL.TransactionBuilderConfigBuilder.new()
      .fee_algo(
        CSL.LinearFee.new(
          CSL.BigNum.from_str(String(params.min_fee_a)),
          CSL.BigNum.from_str(String(params.min_fee_b))
        )
      )
      .pool_deposit(CSL.BigNum.from_str(String(params.pool_deposit)))
      .key_deposit(CSL.BigNum.from_str(String(params.key_deposit)))
      .coins_per_utxo_byte(CSL.BigNum.from_str(String(params.coins_per_utxo_size ?? 4310)))
      .max_value_size(params.max_val_size ?? 5000)
      .max_tx_size(params.max_tx_size ?? 16384)
      .build();

    const txBuilder = CSL.TransactionBuilder.new(txBuilderConfig);

    // 2. Pull the wallet's UTXOs and pick enough to cover amount + fee.
    const utxoHexes = await api.getUtxos();
    if (!utxoHexes || utxoHexes.length === 0) {
      throw new Error('Wallet has no spendable UTXOs.');
    }

    let selected = 0n;
    const target = lovelaceToSend + 500_000n; // rough buffer for fees; CSL trims the exact fee later
    for (const hex of utxoHexes) {
      const utxo = CSL.TransactionUnspentOutput.from_bytes(hexToBytes(hex));
      const utxoLovelace = BigInt(utxo.output().amount().coin().to_str());
      txBuilder.add_input(
        utxo.output().address(),
        utxo.input(),
        utxo.output().amount()
      );
      selected += utxoLovelace;
      if (selected >= target) break;
    }
    if (selected < target) {
      throw new Error('Not enough ADA in wallet to cover this donation plus fees.');
    }

    // 3. Add the donation output to the charity address.
    const outputAddress = CSL.Address.from_bech32(config.charityAddress);
    txBuilder.add_output(
      CSL.TransactionOutput.new(
        outputAddress,
        CSL.Value.new(CSL.BigNum.from_str(lovelaceToSend.toString()))
      )
    );

    // 4. Attach donor name + purpose as on-chain metadata (label 674 is the
    //    conventional "message" label used by most Cardano wallets).
    const metadata = CSL.GeneralTransactionMetadata.new();
    const msgMap = CSL.MetadataMap.new();
    msgMap.insert_str('donor', CSL.TransactionMetadatum.new_text(donorName.slice(0, 64)));
    msgMap.insert_str('purpose', CSL.TransactionMetadatum.new_text(purpose.slice(0, 64)));
    metadata.insert(
      CSL.BigNum.from_str('674'),
      CSL.TransactionMetadatum.new_map(msgMap)
    );
    txBuilder.set_metadata(metadata);

    // 5. Change back to the donor's own address.
    const changeAddressHex = (await api.getChangeAddress());
    const changeAddress = CSL.Address.from_bytes(hexToBytes(changeAddressHex));
    txBuilder.add_change_if_needed(changeAddress);

    const txBody = txBuilder.build();
    const txHash = CSL.hash_transaction(txBody);

    const witnesses = CSL.TransactionWitnessSet.new();
    const auxData = CSL.AuxiliaryData.new();
    auxData.set_metadata(metadata);

    const unsignedTx = CSL.Transaction.new(txBody, witnesses, auxData);

    // 6. Ask the wallet to sign (the donor approves this in a wallet popup;
    //    their private key never leaves the extension).
    const witnessSetHex = await api.signTx(bytesToHex(unsignedTx.to_bytes()), true);
    const walletWitnesses = CSL.TransactionWitnessSet.from_bytes(hexToBytes(witnessSetHex));

    const signedTx = CSL.Transaction.new(txBody, walletWitnesses, auxData);

    // 7. Submit via the wallet (routes through the wallet's own node) —
    //    falls back to submitting via Blockfrost if the wallet can't.
    let submittedTxHash;
    try {
      submittedTxHash = await api.submitTx(bytesToHex(signedTx.to_bytes()));
    } catch (walletSubmitError) {
      submittedTxHash = await submitViaBlockfrost(signedTx.to_bytes());
    }

    // 8. Tell our backend so it can verify the payment on-chain and link
    //    it to a donation record (see backend/verify_transaction.php).
    await notifyBackend(submittedTxHash, donorName, purpose);

    return submittedTxHash;
  }

  async function submitViaBlockfrost(txBytes) {
    const res = await fetch(blockfrostBaseUrl() + '/tx/submit', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/cbor',
        project_id: config.blockfrostProjectId,
      },
      body: txBytes,
    });
    if (!res.ok) {
      throw new Error(`Submit failed: ${res.status} ${await res.text()}`);
    }
    return res.json();
  }

  async function notifyBackend(txHash, donorName, purpose) {
    const res = await fetch(`${config.backendBaseUrl}/verify_transaction.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ transactionId: txHash }),
    });
    return res.json().catch(() => null);
  }

  // -----------------------------------------------------------------
  // Reading chain data (used by the "Verify Transaction" button too,
  // for an instant client-side check before the backend confirms it)
  // -----------------------------------------------------------------

  async function getTransactionStatus(txHash) {
    try {
      const tx = await blockfrostGet(`/txs/${txHash}`);
      return { found: true, blockHeight: tx.block_height, fees: tx.fees };
    } catch {
      return { found: false };
    }
  }

  // -----------------------------------------------------------------
  // Utils
  // -----------------------------------------------------------------

  function hexToBytes(hex) {
    const bytes = new Uint8Array(hex.length / 2);
    for (let i = 0; i < bytes.length; i++) {
      bytes[i] = parseInt(hex.substr(i * 2, 2), 16);
    }
    return bytes;
  }

  function bytesToHex(bytes) {
    return Array.from(bytes).map((b) => b.toString(16).padStart(2, '0')).join('');
  }

  return {
    init,
    availableWallets,
    connectWallet,
    getBalanceLovelace,
    donate,
    getTransactionStatus,
  };
})();

// Expose for non-module <script> usage; also works with `export` if bundled.
if (typeof window !== 'undefined') {
  window.CardanoOffChain = CardanoOffChain;
}
