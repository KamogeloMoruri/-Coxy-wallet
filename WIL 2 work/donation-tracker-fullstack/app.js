/**
 * app.js
 * Wires the AI Donation Tracker page to:
 *   - backend/*.php       (save/list donations, verify Cardano tx, ask the AI)
 *   - cardano/offchain.js (wallet connect + build/sign/submit ADA donations)
 */

// Point this at wherever backend/ is actually served from.
const BACKEND_BASE_URL = 'backend';

// Public, browser-safe config for reading Cardano chain data (no secrets —
// a Blockfrost project ID used client-side should be a *read-only* key with
// rate limits you're comfortable exposing, or proxy reads through the PHP
// backend instead if you'd rather not expose any Blockfrost key at all).
const CARDANO_CONFIG = {
  network: 'preprod',
  blockfrostProjectId: 'REPLACE_WITH_A_BROWSER_SAFE_BLOCKFROST_PROJECT_ID',
  charityAddress: 'addr_test1REPLACE_WITH_THE_CHARITY_RECEIVE_ADDRESS',
  backendBaseUrl: BACKEND_BASE_URL,
};

if (window.CardanoOffChain) {
  window.CardanoOffChain.init(CARDANO_CONFIG);
}

// -------------------------------------------------------------------------
// Small helpers
// -------------------------------------------------------------------------

function setStatus(elementId, message, kind) {
  const el = document.getElementById(elementId);
  if (!el) return;
  el.textContent = message;
  el.classList.remove('success', 'error');
  if (kind) el.classList.add(kind);
}

async function postJson(path, body) {
  const res = await fetch(`${BACKEND_BASE_URL}/${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok || data.ok === false) {
    throw new Error(data.error || data.reason || `Request to ${path} failed.`);
  }
  return data;
}

async function getJson(path) {
  const res = await fetch(`${BACKEND_BASE_URL}/${path}`);
  const data = await res.json().catch(() => ({}));
  if (!res.ok || data.ok === false) {
    throw new Error(data.error || `Request to ${path} failed.`);
  }
  return data;
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

// -------------------------------------------------------------------------
// Donations: load + render table & total
// -------------------------------------------------------------------------

function renderDonations(donations, total) {
  const tbody = document.getElementById('donationsTableBody');
  const totalEl = document.getElementById('totalDonations');

  totalEl.textContent = `R${Number(total).toFixed(2)}`;

  if (donations.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5">No donations recorded yet.</td></tr>';
    return;
  }

  tbody.innerHTML = donations.map((d) => `
    <tr>
      <td>${escapeHtml(d.donorName)}</td>
      <td>R${Number(d.amount).toFixed(2)}</td>
      <td>${escapeHtml(d.purpose)}</td>
      <td>${escapeHtml(d.date)}</td>
      <td>${d.verified ? `✅ ${escapeHtml((d.txHash || '').slice(0, 10))}…` : '—'}</td>
    </tr>
  `).join('');
}

async function loadDonations() {
  try {
    const data = await getJson('get_donations.php');
    renderDonations(data.donations, data.total);
  } catch (err) {
    document.getElementById('donationsTableBody').innerHTML =
      `<tr><td colspan="5">Could not load donations: ${escapeHtml(err.message)}</td></tr>`;
  }
}

// -------------------------------------------------------------------------
// Add a donation (ZAR, form-based)
// -------------------------------------------------------------------------

const form = document.querySelector('#add-donation form');

form.addEventListener('submit', async function (event) {
  event.preventDefault();

  const name = document.getElementById('donorName').value.trim();
  const amount = document.getElementById('amount').value;
  const purpose = document.getElementById('purpose').value;

  if (!name || !amount || !purpose) {
    setStatus('donationStatus', 'Please fill in all fields.', 'error');
    return;
  }

  setStatus('donationStatus', 'Saving…', null);

  try {
    await postJson('save_donation.php', { donorName: name, amount, purpose });
    setStatus('donationStatus', 'Donation saved. Thank you!', 'success');
    form.reset();
    await loadDonations();
  } catch (err) {
    setStatus('donationStatus', err.message, 'error');
  }
});

// -------------------------------------------------------------------------
// AI assistant
// -------------------------------------------------------------------------

document.getElementById('askAiBtn').addEventListener('click', async function () {
  const question = document.getElementById('question').value.trim();
  if (!question) {
    setStatus('aiAnswer', 'Type a question first.', 'error');
    return;
  }

  setStatus('aiAnswer', 'Thinking…', null);

  try {
    const data = await postJson('ask_ai.php', { question });
    setStatus('aiAnswer', data.answer, 'success');
  } catch (err) {
    setStatus('aiAnswer', err.message, 'error');
  }
});

// -------------------------------------------------------------------------
// Cardano: wallet connect
// -------------------------------------------------------------------------

let walletApi = null;

document.getElementById('connectWalletBtn').addEventListener('click', async function () {
  const wallets = window.CardanoOffChain ? window.CardanoOffChain.availableWallets() : [];

  if (wallets.length === 0) {
    setStatus('walletStatus', 'No Cardano wallet extension found (try Nami, Eternl, or Lace).', 'error');
    return;
  }

  try {
    // If more than one wallet is installed, just take the first for
    // simplicity — swap this for a picker UI if you support several.
    const walletName = wallets[0];
    walletApi = await window.CardanoOffChain.connectWallet(walletName);
    setStatus('walletStatus', `Connected to ${walletName}.`, 'success');
  } catch (err) {
    setStatus('walletStatus', err.message, 'error');
  }
});

// -------------------------------------------------------------------------
// Cardano: donate with ADA
// -------------------------------------------------------------------------

document.getElementById('donateAdaBtn').addEventListener('click', async function () {
  if (!walletApi) {
    setStatus('adaDonationStatus', 'Connect a wallet first.', 'error');
    return;
  }

  const amount = parseFloat(document.getElementById('adaAmount').value);
  const donorName = document.getElementById('donorName').value.trim() || 'Anonymous';
  const purpose = document.getElementById('purpose').value || 'Other';

  if (!amount || amount <= 0) {
    setStatus('adaDonationStatus', 'Enter a valid ADA amount.', 'error');
    return;
  }

  setStatus('adaDonationStatus', 'Building transaction — approve it in your wallet…', null);

  try {
    const txHash = await window.CardanoOffChain.donate(walletApi, amount, donorName, purpose);
    setStatus('adaDonationStatus', `Submitted! Transaction: ${txHash}`, 'success');
    await loadDonations();
  } catch (err) {
    setStatus('adaDonationStatus', err.message, 'error');
  }
});

// -------------------------------------------------------------------------
// Cardano: verify an existing transaction ID
// -------------------------------------------------------------------------

document.getElementById('verifyTxBtn').addEventListener('click', async function () {
  const transactionId = document.getElementById('transactionId').value.trim();
  if (!transactionId) {
    setStatus('verifyStatus', 'Enter a transaction ID first.', 'error');
    return;
  }

  setStatus('verifyStatus', 'Checking the chain…', null);

  try {
    const data = await postJson('verify_transaction.php', { transactionId });
    if (data.verified) {
      setStatus('verifyStatus', `Verified — ${data.paidLovelace / 1_000_000} ADA confirmed on-chain.`, 'success');
      await loadDonations();
    } else {
      setStatus('verifyStatus', data.reason || 'Not yet confirmed.', 'error');
    }
  } catch (err) {
    setStatus('verifyStatus', err.message, 'error');
  }
});

// -------------------------------------------------------------------------
// Init
// -------------------------------------------------------------------------

loadDonations();
