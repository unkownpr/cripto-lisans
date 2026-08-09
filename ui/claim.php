<?php
use App\Lib\View;
/** @var array $voucher */
/** @var ?string $onchainOwner */
/** @var array $typedData */
$claimed = ($onchainOwner && $onchainOwner !== '0x0000000000000000000000000000000000000000');
$priceWei = (int) ($voucher['price_wei'] ?? 0);
$priceLabel = $priceWei === 0 ? 'Ücretsiz' : rtrim(rtrim(sprintf('%.6f', $priceWei / 1e18), '0'), '.') . ' ETH';
?>
<section class="card" id="claim"
         data-typeddata='<?= View::e(json_encode($typedData)) ?>'>
    <h1>Lisansını al</h1>
    <p class="muted">Cüzdanını bağla, <strong>"Lisansı cüzdanıma al"</strong>a bas. Lisans senin cüzdanına eklenir ve sadece sana ait olur — istersen sonra başkasına devredebilirsin.</p>

    <div class="license-box">
        <h3 style="margin-bottom:2px"><?= View::e($voucher['product_name']) ?></h3>
        <p class="muted" style="margin:0"><?= View::e($voucher['product_desc']) ?></p>
        <table class="info-table">
            <tr><th>Ücret</th><td><?= $priceLabel ?></td></tr>
            <tr><th>Geçerlilik</th><td><?= (int) $voucher['expiry'] === 0 ? 'Süresiz' : date('d.m.Y', (int) $voucher['expiry']) . '\'e kadar' ?></td></tr>
            <tr><th>Kimler alabilir</th><td><?= $voucher['recipient'] === '0x0000000000000000000000000000000000000000' ? 'Herkes' : 'Belirli cüzdan' ?></td></tr>
        </table>
    </div>

    <?php if ($claimed): ?>
        <p class="ok">✓ Bu lisans zaten alınmış.</p>
    <?php else: ?>
        <div style="margin-top:var(--space-md)">
            <button id="btn-claim" class="btn btn-big">Lisansı cüzdanıma al</button>
            <p class="muted" style="font-size:var(--text-xs);margin-top:var(--space-sm)">Not: <?= $priceWei === 0 ? 'Ücretsiz, sadece küçük bir ağ ücreti olabilir.' : 'Ücret + küçük ağ ücreti cüzdanından ödenir.' ?> Cüzdanın yoksa MetaMask kurman gerekir.</p>
            <p id="claim-status" class="mono"></p>
        </div>
    <?php endif; ?>
</section>
