// ==========================================================
// Coxy Wallet AI Music Contest — app.js
//
// Talks to the PHP backend in /backend/api/ for everything:
// creating a payment, verifying it on-chain, submitting an entry,
// listing entries, and voting.
//
// Wallet connect + building/signing the ADA transaction uses the
// Mesh SDK (https://meshjs.dev), which wraps the CIP-30 standard
// that Cardano browser wallets (Nami, Eternl, Flint, etc.) implement.
// Raw transaction construction isn't something plain JS can do
// alone, so this is a real dependency, not a placeholder.
// ==========================================================

import { BrowserWallet, Transaction } from "https://esm.sh/@meshsdk/core@1";

// Point this at wherever you deploy the backend/ folder
const API_BASE = "/backend/api";

let wallet = null;
let walletAddress = null;
let activeTransaction = null; // { transaction_id, pay_to_address, amount_lovelace }
let currentlyPlaying = null;

document.addEventListener("DOMContentLoaded", () => {
  startCountdown();
  loadEntries();

  document.getElementById("wallet-btn").addEventListener("click", connectWallet);
  document.getElementById("enter-btn").addEventListener("click", startEntryPayment);
  document.getElementById("view-entries-btn").addEventListener("click", () => {
    document.getElementById("entries").scrollIntoView({ behavior: "smooth" });
  });
  document.getElementById("modal-close").addEventListener("click", closeModal);
  document.getElementById("entry-form").addEventListener("submit", submitEntry);
});

// ----------------------------------------------------------
// Countdown (visual only — swap target for your real contest end date)
// ----------------------------------------------------------
function startCountdown() {
  const target = Date.now() + 7 * 24 * 60 * 60 * 1000;
  const tick = () => {
    const diff = Math.max(0, target - Date.now());
    document.getElementById("cd-days").textContent = String(Math.floor(diff / 864e5)).padStart(2, "0");
    document.getElementById("cd-hours").textContent = String(Math.floor(diff / 36e5) % 24).padStart(2, "0");
    document.getElementById("cd-mins").textContent = String(Math.floor(diff / 6e4) % 60).padStart(2, "0");
    document.getElementById("cd-secs").textContent = String(Math.floor(diff / 1e3) % 60).padStart(2, "0");
  };
  tick();
  setInterval(tick, 1000);
}

// ----------------------------------------------------------
// Wallet connect (CIP-30 via Mesh SDK)
// ----------------------------------------------------------
async function connectWallet() {
  const available = await BrowserWallet.getAvailableWallets();
  if (!available.length) {
    showToast("No Cardano wallet extension found (try Nami or Eternl)");
    return;
  }

  try {
    // Uses whichever supported wallet extension is installed first.
    // For multiple options, show a picker built from `available`.
    wallet = await BrowserWallet.enable(available[0].id);
    walletAddress = await wallet.getChangeAddress();

    const btn = document.getElementById("wallet-btn");
    btn.textContent = shortenAddress(walletAddress);
    btn.classList.add("connected");
    showToast("Wallet connected");
  } catch (err) {
    console.error(err);
    showToast("Could not connect wallet");
  }
}

function shortenAddress(addr) {
  return addr.slice(0, 8) + "…" + addr.slice(-4);
}

// ----------------------------------------------------------
// Entry payment flow: create_payment -> build & sign tx -> verify_payment
// ----------------------------------------------------------
async function startEntryPayment() {
  if (!wallet) {
    showToast("Connect your wallet first");
    return;
  }

  const enterBtn = document.getElementById("enter-btn");
  enterBtn.disabled = true;
  enterBtn.textContent = "Preparing payment…";

  try {
    const res = await fetch(`${API_BASE}/create_payment.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ wallet_address: walletAddress }),
    });
    const payment = await res.json();
    if (payment.error) throw new Error(payment.error);
    activeTransaction = payment;

    enterBtn.textContent = "Confirm in your wallet…";

    const tx = new Transaction({ initiator: wallet });
    tx.sendLovelace(payment.pay_to_address, String(payment.amount_lovelace));
    const unsignedTx = await tx.build();
    const signedTx = await wallet.signTx(unsignedTx);
    const txHash = await wallet.submitTx(signedTx);

    enterBtn.textContent = "Confirming on-chain…";
    await pollPaymentConfirmation(payment.transaction_id, txHash);
  } catch (err) {
    console.error(err);
    showToast("Payment was not completed");
  } finally {
    enterBtn.disabled = false;
    enterBtn.textContent = "Enter with 10 ADA";
  }
}

async function pollPaymentConfirmation(transactionId, txHash, attempt = 1) {
  const res = await fetch(`${API_BASE}/verify_payment.php`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ transaction_id: transactionId, tx_hash: txHash }),
  });
  const result = await res.json();

  if (result.status === "confirmed") {
    showToast("Payment confirmed — add your track");
    openModal();
    return;
  }

  if (attempt >= 10) {
    showToast("Still waiting on-chain — this can take a few minutes. Try again shortly.");
    return;
  }

  setTimeout(() => pollPaymentConfirmation(transactionId, txHash, attempt + 1), 6000);
}

// ----------------------------------------------------------
// Entry submission modal
// ----------------------------------------------------------
function openModal() {
  document.getElementById("entry-modal").classList.remove("hidden");
}

function closeModal() {
  document.getElementById("entry-modal").classList.add("hidden");
}

async function submitEntry(e) {
  e.preventDefault();
  if (!activeTransaction) {
    showToast("No confirmed payment found");
    return;
  }

  const form = e.target;
  const submitBtn = document.getElementById("submit-entry-btn");
  submitBtn.disabled = true;
  submitBtn.textContent = "Uploading…";

  const formData = new FormData(form);
  formData.append("transaction_id", activeTransaction.transaction_id);

  try {
    const res = await fetch(`${API_BASE}/upload_entry.php`, {
      method: "POST",
      body: formData,
    });
    const result = await res.json();
    if (result.error) throw new Error(result.error);

    showToast("Entry submitted — good luck!");
    closeModal();
    form.reset();
    activeTransaction = null;
    loadEntries();
  } catch (err) {
    console.error(err);
    showToast("Could not submit your entry");
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = "Submit entry";
  }
}

// ----------------------------------------------------------
// Entries list + voting
// ----------------------------------------------------------
async function loadEntries() {
  const list = document.getElementById("entries-list");
  try {
    const res = await fetch(`${API_BASE}/entries.php`);
    const entries = await res.json();

    list.innerHTML = "";
    if (!entries.length) {
      list.innerHTML = `<p class="entries-loading">No entries yet — be the first.</p>`;
      return;
    }
    entries.forEach((entry) => list.appendChild(buildEntryCard(entry)));
  } catch (err) {
    console.error(err);
    list.innerHTML = `<p class="entries-loading">Couldn't load entries. Try refreshing.</p>`;
  }
}

function buildEntryCard(entry) {
  const card = document.createElement("div");
  card.className = "entry-card";

  const playBtn = document.createElement("button");
  playBtn.className = "play-btn";
  playBtn.setAttribute("aria-label", `Play ${entry.title}`);
  playBtn.textContent = "▶";

  const audio = new Audio(entry.audio_url);
  playBtn.addEventListener("click", () => togglePlay(audio, playBtn));

  const info = document.createElement("div");
  info.className = "entry-info";
  info.innerHTML = `<h3>${escapeHtml(entry.title)}</h3>
    <p class="entry-meta">${escapeHtml(entry.artist || "Unknown artist")} &middot; made with ${escapeHtml(entry.ai_tool_used || "an AI tool")}</p>`;

  const votesWrap = document.createElement("div");
  votesWrap.className = "entry-votes";

  const count = document.createElement("span");
  count.className = "vote-count";
  count.textContent = entry.vote_count;

  const voteBtn = document.createElement("button");
  voteBtn.className = "vote-btn";
  voteBtn.textContent = "Vote";
  voteBtn.addEventListener("click", () => castVote(entry.id, voteBtn, count));

  votesWrap.append(count, voteBtn);
  card.append(playBtn, info, votesWrap);
  return card;
}

function togglePlay(audio, btn) {
  if (currentlyPlaying && currentlyPlaying.audio !== audio) {
    currentlyPlaying.audio.pause();
    currentlyPlaying.audio.currentTime = 0;
    currentlyPlaying.btn.textContent = "▶";
    currentlyPlaying.btn.classList.remove("playing");
  }

  if (audio.paused) {
    audio.play().catch(() => showToast("Couldn't play this track"));
    btn.textContent = "❚❚";
    btn.classList.add("playing");
    currentlyPlaying = { audio, btn };
    audio.onended = () => {
      btn.textContent = "▶";
      btn.classList.remove("playing");
    };
  } else {
    audio.pause();
    btn.textContent = "▶";
    btn.classList.remove("playing");
  }
}

async function castVote(entryId, btnEl, countEl) {
  if (!wallet || !walletAddress) {
    showToast("Connect your wallet to vote");
    return;
  }

  btnEl.disabled = true;
  try {
    const res = await fetch(`${API_BASE}/vote.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ entry_id: entryId, wallet_address: walletAddress }),
    });
    const result = await res.json();

    if (result.error) {
      showToast(result.error);
      btnEl.disabled = false;
      return;
    }

    countEl.textContent = result.vote_count;
    btnEl.textContent = "Voted";
    btnEl.classList.add("voted");
    showToast("Vote recorded");
  } catch (err) {
    console.error(err);
    showToast("Couldn't record your vote");
    btnEl.disabled = false;
  }
}

// ----------------------------------------------------------
// Helpers
// ----------------------------------------------------------
function escapeHtml(str) {
  const div = document.createElement("div");
  div.textContent = str;
  return div.innerHTML;
}

let toastTimer = null;
function showToast(message) {
  const toast = document.getElementById("toast");
  toast.textContent = message;
  toast.classList.add("show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("show"), 3000);
}
