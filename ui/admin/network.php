<?php
use App\Lib\View;
/** @var \Base $f3 */
$zero = '0x0000000000000000000000000000000000000000';
$contractSet = $f3->get('CONTRACT') && $f3->get('CONTRACT') !== $zero;
?>
<div class="admin-head"><h1>Ağ &amp; Kontrat</h1></div>

<section class="card">
    <h2>Ağ ayarları</h2>
    <p class="admin-sub">Panelin bağlı olduğu blockchain. İstediğin zaman değiştir — hazır ağ seç, RPC otomatik dolar.</p>
    <form method="post" action="/admin/settings">
        <div class="grid">
            <label>Hazır ağ<br>
                <select id="net-preset">
                    <option value="">— seç (otomatik doldurur) —</option>
                    <option data-cid="11155111" data-rpc="https://ethereum-sepolia-rpc.publicnode.com">Sepolia (test, bedava)</option>
                    <option data-cid="8453" data-rpc="https://mainnet.base.org">Base (L2, ucuz)</option>
                    <option data-cid="137" data-rpc="https://polygon-rpc.com">Polygon (ucuz)</option>
                    <option data-cid="1" data-rpc="https://ethereum-rpc.publicnode.com">Ethereum (mainnet, pahalı)</option>
                    <option data-cid="1337" data-rpc="http://localhost:8545">Ganache Local (geliştirme)</option>
                </select>
            </label>
            <label>Chain ID<br><input name="chain_id" id="net-cid" value="<?= (int) $f3->get('CHAIN_ID') ?>"></label>
        </div>
        <p><label>RPC URL<br><input name="rpc_url" id="net-rpc" value="<?= View::e($f3->get('RPC_URL')) ?>" placeholder="https://..."></label></p>
        <p><label>Kontrat adresi <span class="muted">— o ağdaki deploy adresi</span><br><input name="contract" value="<?= View::e($f3->get('CONTRACT')) ?>" placeholder="0x…"></label></p>
        <button class="btn">Ağı kaydet</button>
    </form>
</section>

<section class="card">
    <h2>Kontratı kur <span class="badge <?= $contractSet ? 'badge--ok' : 'badge--muted' ?>"><?= $contractSet ? 'kurulu' : 'kurulu değil' ?></span></h2>
    <p class="admin-sub">Kendi lisans kontratını <strong>tek tıkla</strong> deploy et — Remix gerekmez. Cüzdanını bağla → "Kontratı kur" → onayla → adres <strong>otomatik kaydolur</strong>. Cüzdanında biraz gas (o ağın parası) olmalı.</p>
    <p class="muted" style="font-size:var(--text-xs)">Önce yukarıdan <strong>ağı seç + kaydet</strong>. Deploy, MetaMask'ın bağlı olduğu ağa yapılır. Bağlı cüzdan otomatik <strong>admin + imza yetkilisi</strong> olur.</p>
    <button id="btn-deploy" class="btn btn-accent">Kontratı kur</button>
    <p id="deploy-status" class="mono"></p>
</section>

<section class="card card--mint">
    <h2>Ödemeler</h2>
    <p class="admin-sub">Müşteriler kripto ile ödediyse para akıllı sözleşmede birikir. Kendi cüzdanına aktar:</p>
    <button id="btn-withdraw" class="btn">Paranı çek</button>
    <p id="withdraw-status" class="mono"></p>
</section>
