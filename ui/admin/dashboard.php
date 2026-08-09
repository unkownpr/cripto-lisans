<?php
use App\Lib\View;
/** @var \Base $f3 */
/** @var array $counts */
$zero = '0x0000000000000000000000000000000000000000';
$contractSet = $f3->get('CONTRACT') && $f3->get('CONTRACT') !== $zero;
$cn = [1=>'Ethereum',11155111=>'Sepolia',1337=>'Ganache Local',8453=>'Base',137=>'Polygon'][(int)$f3->get('CHAIN_ID')] ?? ('Chain '.(int)$f3->get('CHAIN_ID'));
?>
<div class="admin-head">
    <h1>Genel bakış</h1>
    <span class="badge <?= $contractSet ? 'badge--ok' : 'badge--warn' ?>"><?= View::e($cn) ?><?= $contractSet ? '' : ' · kontrat yok' ?></span>
</div>

<div class="dash-grid">
    <div class="dash-card tint-coral"><b><?= (int) $counts['products'] ?></b><span>Ürün</span></div>
    <div class="dash-card tint-mint"><b><?= (int) $counts['vouchers'] ?></b><span>Lisans</span></div>
    <div class="dash-card tint-sky"><b><?= (int) $counts['orders'] ?></b><span>Sipariş</span></div>
    <div class="dash-card"><b><?= (int) $counts['users'] ?></b><span>Kullanıcı</span></div>
</div>

<section class="card card--sky" style="margin-top:var(--space-lg)">
    <h2>Nasıl çalışır</h2>
    <p style="margin-bottom:0"><strong>1)</strong> <a href="/admin/products">Ürün ekle</a>.
    <strong>2)</strong> Ürün satırında <em>"Lisans oluştur"</em> → cüzdan penceresinde <em>Onayla</em> (ücretsiz).
    <strong>3)</strong> Çıkan <em>müşteri linki</em>ni gönder — müşteri kendi cüzdanına lisansı alır.</p>
</section>

<?php if (!$contractSet): ?>
<section class="card">
    <h2>Başlamadan: kontrat kur</h2>
    <p class="muted">Gerçek NFT üretmek için bir kez kontrat gerekir. <a href="/admin/network">Ağ &amp; Kontrat</a> sayfasından tek tıkla kur.</p>
    <a class="btn" href="/admin/network">Ağ &amp; Kontrat →</a>
</section>
<?php endif; ?>
