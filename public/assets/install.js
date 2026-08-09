/* Install wizard — MetaMask connect (auto admin) + DB test + commit. */
'use strict';

function formData() {
  const f = document.getElementById('install-form');
  const d = {};
  for (const el of f.querySelectorAll('input, select')) {
    if (el.name) d[el.name] = el.value;
  }
  return d;
}

async function connectWallet() {
  if (!window.ethereum) { alert('MetaMask gerekli.'); return; }
  const provider = new ethers.BrowserProvider(window.ethereum);
  await provider.send('eth_requestAccounts', []);
  const addr = (await (await provider.getSigner()).getAddress()).toLowerCase();
  document.getElementById('admin_address').value = addr;
  const chip = document.getElementById('admin-addr');
  chip.textContent = addr.slice(0, 8) + '…' + addr.slice(-4);
  chip.style.display = 'inline-block';
  document.getElementById('btn-connect').textContent = 'Cüzdan bağlı ✓';
}

async function dbtest() {
  const s = document.getElementById('dbtest-status');
  s.textContent = 'test ediliyor…';
  const res = await fetch('/install/dbtest', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(formData()),
  }).then(r => r.json());
  s.textContent = res.ok ? ('✓ ' + (res.note || 'ok')) : ('✗ ' + res.error);
  s.style.color = res.ok ? 'var(--color-ok)' : 'var(--color-danger)';
}

async function install() {
  const s = document.getElementById('install-status');
  const d = formData();
  if (!d.admin_address) { s.textContent = '✗ Önce MetaMask ile admin cüzdanı bağla.'; return; }
  s.textContent = 'kuruluyor…';
  const res = await fetch('/install/run', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(d),
  }).then(r => r.json());
  if (res.ok) {
    s.textContent = '✓ Kurulum tamam, yönlendiriliyor…';
    setTimeout(() => location.href = res.redirect || '/', 800);
  } else {
    s.textContent = '✗ ' + (res.error || 'hata');
  }
}

function toggleDbFields() {
  const drv = document.getElementById('db_driver').value;
  document.getElementById('mysql-fields').style.display = drv === 'mysql' ? '' : 'none';
}

document.addEventListener('click', (e) => {
  if (e.target.id === 'btn-connect') { e.preventDefault(); connectWallet(); }
  if (e.target.id === 'btn-dbtest') { e.preventDefault(); dbtest(); }
  if (e.target.id === 'btn-install') { e.preventDefault(); install(); }
});
document.addEventListener('change', (e) => {
  if (e.target.id === 'db_driver') { toggleDbFields(); }
});
