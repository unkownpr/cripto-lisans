/* Kripto Lisans Paneli — wallet + chain interactions (ethers v6). */
'use strict';

const META = {
  chainId: parseInt(document.querySelector('meta[name="chain-id"]')?.content || '0', 10),
  chainName: document.querySelector('meta[name="chain-name"]')?.content || 'Blockchain',
  rpcUrl: document.querySelector('meta[name="rpc-url"]')?.content || '',
  contract: document.querySelector('meta[name="contract"]')?.content || '',
};

/* Ensure MetaMask is on the panel's configured chain; add it if unknown. */
async function ensureChain() {
  if (!window.ethereum || !META.chainId || !META.rpcUrl) return;
  const hexId = '0x' + META.chainId.toString(16);
  try {
    await window.ethereum.request({ method: 'wallet_switchEthereumChain', params: [{ chainId: hexId }] });
  } catch (e) {
    const code = e.code || e?.data?.originalError?.code;
    if (code === 4902) {
      await window.ethereum.request({ method: 'wallet_addEthereumChain', params: [{
        chainId: hexId,
        chainName: META.chainName,
        rpcUrls: [META.rpcUrl],
        nativeCurrency: { name: 'ETH', symbol: 'ETH', decimals: 18 },
      }]});
    } else {
      throw e;
    }
  }
}

const ABI = [
  'function redeem((uint256 tokenId,uint256 productId,address recipient,uint64 expiry,string uri,uint256 price) v, bytes sig) payable',
  'function transferFrom(address from, address to, uint256 tokenId)',
  'function revoke(uint256 tokenId)',
  'function withdraw(address to)',
  'function ownerOf(uint256 tokenId) view returns (address)',
];

async function getSigner() {
  if (!window.ethereum) {
    alert('MetaMask gerekli.');
    throw new Error('no wallet');
  }
  const provider = new ethers.BrowserProvider(window.ethereum);
  await provider.send('eth_requestAccounts', []);
  await ensureChain();
  return new ethers.BrowserProvider(window.ethereum).getSigner();
}

/* ---------- SIWE login ---------- */
// Runs the connect + sign + verify flow. Returns true on success. No reload.
async function siweLogin() {
  const signer = await getSigner();
  const address = await signer.getAddress();

  const { nonce, domain, chainId } = await fetch('/auth/nonce').then((r) => r.json());
  const message =
    `${domain} sizi kimlik doğrulaması için imzalamaya davet ediyor.\n\n` +
    `Adres: ${address}\nChain: ${chainId}\nNonce: ${nonce}`;
  const signature = await signer.signMessage(message);

  const out = await fetch('/auth/verify', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ address, message, signature }),
  }).then((r) => r.json());
  return !!out.ok;
}

// Login button — reloads so the header/session reflect the new state.
async function login() {
  try {
    if (await siweLogin()) location.reload();
    else alert('Giriş başarısız.');
  } catch (e) {
    alert('Giriş hatası: ' + (e.shortMessage || e.message || e));
  }
}

/* ---------- Admin: prepare + sign voucher ---------- */
async function makeVoucher(pid, recipient) {
  const status = document.getElementById('voucher-result');
  status.textContent = 'Lisans hazırlanıyor…';

  try {
  const prep = await fetch('/admin/voucher/prepare', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ product_id: pid, recipient }),
  });
  const data = await prep.json();
  if (!data.ok) { status.textContent = 'Hata: ' + (data.error || '?'); return; }

  const signer = await getSigner();
  const td = data.typedData;
  const signature = await signer.signTypedData(td.domain, td.types, td.message);

  const store = await fetch('/admin/voucher/store', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ voucher_id: data.voucher_id, signature }),
  });
  const stored = await store.json();
  status.innerHTML = stored.ok
    ? '✓ Lisans hazır. Müşteriye şu linki gönder: <a href="' + stored.claim_url + '">' + stored.claim_url + '</a>'
    : 'Kaydedilemedi, tekrar dene';
  if (stored.ok) setTimeout(() => location.reload(), 2000);
  } catch (e) {
    status.textContent = 'Hata: ' + (e.shortMessage || e.message || e);
  }
}

/* ---------- Customer: claim (redeem) ---------- */
async function claim() {
  const el = document.getElementById('claim');
  const status = document.getElementById('claim-status');
  const td = JSON.parse(el.dataset.typeddata);

  try {
    const signer = await getSigner();
    const c = new ethers.Contract(META.contract, ABI, signer);
    const v = td.voucher;
    status.textContent = 'Cüzdan onayı bekleniyor…';
    const tx = await c.redeem(
      [v.tokenId, v.productId, v.recipient, v.expiry, v.uri, v.price],
      td.signature,
      { value: v.price }
    );
    status.textContent = 'İşlem gönderildi, ağ onayı bekleniyor…';
    await tx.wait();
    status.textContent = '✓ Lisans cüzdanına eklendi!';
    setTimeout(() => location.reload(), 2000);
  } catch (e) {
    status.textContent = 'Hata: ' + (e.shortMessage || e.message || e);
  }
}

/* ---------- Customer: transfer ---------- */
async function transfer(tokenId, to) {
  const status = document.getElementById('transfer-status');
  try {
    const signer = await getSigner();
    const from = await signer.getAddress();
    const c = new ethers.Contract(META.contract, ABI, signer);
    status.textContent = 'Cüzdan onayı bekleniyor…';
    const tx = await c.transferFrom(from, to, tokenId);
    status.textContent = 'İşlem gönderildi, ağ onayı bekleniyor…';
    await tx.wait();
    status.textContent = '✓ Lisans devredildi.';
    setTimeout(() => location.reload(), 2000);
  } catch (e) {
    status.textContent = 'Hata: ' + (e.shortMessage || e.message || e);
  }
}

/* ---------- Admin: withdraw crypto proceeds (phase 2) ---------- */
async function withdraw() {
  const status = document.getElementById('withdraw-status');
  try {
    const signer = await getSigner();
    const to = await signer.getAddress();
    const c = new ethers.Contract(META.contract, ABI, signer);
    status.textContent = 'Cüzdan onayı bekleniyor…';
    const tx = await c.withdraw(to);
    status.textContent = 'İşlem gönderildi, ağ onayı bekleniyor…';
    await tx.wait();
    status.textContent = '✓ Para cüzdanına aktarıldı.';
  } catch (e) {
    status.textContent = 'Hata: ' + (e.shortMessage || e.message || e);
  }
}

/* ---------- Store: buy (auto-connects wallet if needed) ---------- */
async function buy(pid) {
  const status = document.querySelector('.buy-status[data-pid="' + pid + '"]');
  const set = (msg, cls = '') => {
    status.textContent = msg;
    status.className = 'buy-status' + (cls ? ' ' + cls : '');
  };
  const call = () => fetch('/store/buy', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ product_id: pid }),
  });

  try {
    set('İşleniyor…');
    let r = await call();
    if (r.status === 401) {
      // Not logged in — connect wallet + sign in, then retry seamlessly.
      set('Cüzdan bağlanıyor…');
      if (!(await siweLogin())) { set('Giriş iptal edildi', 'is-err'); return; }
      set('İşleniyor…');
      r = await call();
    }
    const res = await r.json();
    if (res.ok && res.claim_url) {
      status.className = 'buy-status is-ok';
      status.innerHTML = (res.message || 'Hazır.') + ' <a href="' + res.claim_url + '">Lisansı al →</a>';
    } else if (res.ok) {
      set(res.message || 'Talep alındı.', 'is-ok');
    } else {
      set(res.error || 'Bir hata oluştu', 'is-err');
    }
  } catch (e) {
    set(e.shortMessage || e.message || String(e), 'is-err');
  }
}

/* ---------- Admin: one-click contract deploy ---------- */
async function deployContract() {
  const status = document.getElementById('deploy-status');
  const btn = document.getElementById('btn-deploy');
  try {
    status.textContent = 'Cüzdan bağlanıyor…';
    const signer = await getSigner();
    const admin = await signer.getAddress();

    status.textContent = 'Kontrat hazırlanıyor…';
    const art = await fetch('/assets/LicenseNFT.json').then(r => r.json());
    const factory = new ethers.ContractFactory(art.abi, art.bytecode, signer);

    status.textContent = 'Cüzdan onayı bekleniyor (deploy)…';
    if (btn) btn.disabled = true;
    const c = await factory.deploy(admin); // constructor admin = bağlı cüzdan
    status.textContent = 'Deploy gönderildi, ağ onayı bekleniyor…';
    await c.waitForDeployment();
    const address = await c.getAddress();

    const saved = await fetch('/admin/contract-set', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ contract: address }),
    }).then(r => r.json());

    status.innerHTML = saved.ok
      ? '✓ Kontrat kuruldu: <span class="mono">' + address + '</span> — kaydedildi.'
      : '✓ Kontrat kuruldu: ' + address + ' (kaydetme hatası, Ağ ayarlarına elle gir)';
    setTimeout(() => location.reload(), 2500);
  } catch (e) {
    if (btn) btn.disabled = false;
    status.textContent = 'Hata: ' + (e.shortMessage || e.message || e);
  }
}

/* ---------- wire up ---------- */
document.addEventListener('click', (ev) => {
  const t = ev.target;
  if (t.id === 'btn-login') { login(); }
  if (t.id === 'btn-deploy') { deployContract(); }
  if (t.id === 'btn-claim') { claim(); }
  if (t.id === 'btn-withdraw') { withdraw(); }
  if (t.classList.contains('btn-buy')) { buy(t.dataset.pid); }
  if (t.classList.contains('btn-voucher')) {
    const pid = t.dataset.pid;
    const rec = document.querySelector('.rec[data-pid="' + pid + '"]')?.value || '';
    makeVoucher(pid, rec);
  }
  if (t.classList.contains('btn-transfer')) {
    const row = t.closest('tr');
    const to = row.querySelector('.to')?.value || '';
    if (!/^0x[0-9a-fA-F]{40}$/.test(to)) { alert('Geçerli alıcı adresi gir.'); return; }
    transfer(t.dataset.token, to);
  }
});

/* Admin network preset → auto-fill chain id + rpc */
document.addEventListener('change', (ev) => {
  if (ev.target.id === 'net-preset') {
    const opt = ev.target.selectedOptions[0];
    if (opt && opt.dataset.cid) {
      document.getElementById('net-cid').value = opt.dataset.cid;
      document.getElementById('net-rpc').value = opt.dataset.rpc;
    }
  }
});

/* ---------- themed confirm modal (forms with data-confirm) ---------- */
(function () {
  let pending = null;
  const modal = () => document.getElementById('confirm-modal');
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (!(f instanceof HTMLFormElement) || !f.dataset.confirm || !modal()) return;
    e.preventDefault();
    pending = f;
    document.getElementById('confirm-msg').textContent = f.dataset.confirm;
    document.getElementById('confirm-ok').textContent = f.dataset.confirmOk || 'Sil';
    modal().hidden = false;
  });
  document.addEventListener('click', (e) => {
    const t = e.target;
    if (t.id === 'confirm-cancel' || t.classList.contains('modal-overlay')) {
      if (modal()) modal().hidden = true;
      pending = null;
    } else if (t.id === 'confirm-ok' && pending) {
      modal().hidden = true;
      const f = pending; pending = null;
      f.submit(); // programmatic submit skips the submit listener
    }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal() && !modal().hidden) { modal().hidden = true; pending = null; }
  });
})();

/* ---------- copy-to-clipboard for code blocks ---------- */
document.addEventListener('click', async (e) => {
  if (!e.target.classList.contains('copy-btn')) return;
  const pre = e.target.parentElement.querySelector('pre');
  if (!pre) return;
  try {
    await navigator.clipboard.writeText(pre.textContent);
    const old = e.target.textContent;
    e.target.textContent = 'Kopyalandı ✓';
    setTimeout(() => { e.target.textContent = old; }, 1500);
  } catch (_) { /* clipboard unavailable */ }
});
