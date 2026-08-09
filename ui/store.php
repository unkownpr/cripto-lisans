<?php
use App\Lib\View;
/** @var array $products */
/** @var ?string $user */
/** @var \Base $f3 */

$fmtPrice = static function (array $p): string {
    return match ($p['price_type']) {
        'crypto' => rtrim(rtrim(sprintf('%.6f', ((int) $p['price_wei']) / 1e18), '0'), '.') . ' ETH',
        'fiat' => View::e($p['price_fiat']) . ' ' . View::e($p['currency']),
        default => 'Ücretsiz',
    };
};
?>
<section class="store-hero">
    <h1><?= View::e($f3->get('SITE_NAME')) ?></h1>
    <p><?= View::e($f3->get('SITE_TAGLINE')) ?></p>
    <p class="store-facts">
        <span><span class="dot">✓</span> Anında teslim</span>
        <span><span class="dot">✓</span> Devredilebilir</span>
        <span><span class="dot">✓</span> İptal edilemez</span>
    </p>
    <?php if (!$user): ?>
        <p style="margin-top:var(--space-lg)"><button id="btn-login" class="btn btn-big">MetaMask ile Giriş</button></p>
    <?php endif; ?>
</section>

<?php if (!$products): ?>
    <section class="card"><p class="muted" style="text-align:center;margin:0">Henüz ürün yok. Yönetimden ürün ekleyince burada listelenir.</p></section>
<?php else: ?>
    <div class="store-section-head">
        <h2>Ürünler</h2>
        <span class="muted"><?= count($products) ?> ürün</span>
    </div>
    <div class="store-grid">
        <?php foreach (array_values($products) as $i => $p): ?>
        <article class="product-card" id="product-<?= (int) $p['id'] ?>">
            <?php if (!empty($p['image'])): ?>
                <div class="pc-cover pc-cover--img"><img src="<?= View::e($p['image']) ?>" alt="<?= View::e($p['name']) ?>" loading="lazy"></div>
            <?php else: ?>
                <div class="pc-cover c<?= $i % 4 ?>"><span><?= View::e(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?></span></div>
            <?php endif; ?>
            <div class="pc-body">
                <h3><?= View::e($p['name']) ?></h3>
                <p class="muted"><?= View::e($p['description']) ?></p>
                <div class="pc-price"><?= $fmtPrice($p) ?></div>
                <div class="pc-meta">
                    <span class="badge badge--muted"><?= $p['license_type'] === 'duration' ? (int) $p['duration_days'] . ' gün geçerli' : 'Süresiz lisans' ?></span>
                </div>
                <button class="btn btn-buy" data-pid="<?= (int) $p['id'] ?>"><?= $p['price_type'] === 'free' ? 'Ücretsiz al' : 'Satın al' ?></button>
                <p class="buy-status" data-pid="<?= (int) $p['id'] ?>"></p>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
