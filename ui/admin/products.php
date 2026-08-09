<?php
use App\Lib\View;
/** @var array $products */
$fmtPrice = static fn (array $p): string => match ($p['price_type'] ?? 'free') {
    'crypto' => rtrim(rtrim(sprintf('%.6f', ((int) ($p['price_wei'] ?? 0)) / 1e18), '0'), '.') . ' ETH',
    'fiat' => View::e($p['price_fiat'] ?? '0') . ' ' . View::e($p['currency'] ?? 'TRY'),
    default => 'Ücretsiz',
};
?>
<div class="admin-head"><h1>Ürünler</h1></div>

<section class="card">
    <h2>Yeni ürün</h2>
    <form method="post" action="/admin/product" enctype="multipart/form-data">
        <div class="grid">
            <label>Ürün adı<br><input name="name" placeholder="Örn: Foto Editör Pro" required></label>
            <label>Kısa açıklama<br><input name="description" placeholder="Ne işe yarar?"></label>
            <label>Ürün görseli <span class="muted">— png/jpg/webp/gif, ≤3MB</span><br><input type="file" name="image" accept="image/png,image/jpeg,image/gif,image/webp"></label>
            <label>Ücret<br>
                <select name="price_type">
                    <option value="free">Ücretsiz</option>
                    <option value="fiat">Kartla ödeme</option>
                    <option value="crypto">Kripto (ETH)</option>
                </select>
            </label>
            <label>Geçerlilik<br>
                <select name="license_type">
                    <option value="perpetual">Süresiz</option>
                    <option value="duration">Süreli (gün)</option>
                </select>
            </label>
            <label>Süre (gün) <span class="muted">— süreliyse</span><br><input name="duration_days" type="number" value="0"></label>
            <label>Kart fiyatı <span class="muted">— kartla ödemede</span><br>
                <span style="display:flex;gap:8px">
                    <input name="price_fiat" placeholder="199.90" value="0">
                    <select name="currency" style="width:100px">
                        <option value="TRY">₺ TRY</option><option value="USD">$ USD</option><option value="EUR">€ EUR</option><option value="GBP">£ GBP</option>
                    </select>
                </span>
            </label>
        </div>
        <details style="margin:8px 0">
            <summary class="muted" style="cursor:pointer">Kripto fiyatı (ileri düzey)</summary>
            <p><label>ETH fiyatı (wei)<br><input name="price_wei" value="0"></label>
            <span class="muted">1 ETH = 1000000000000000000 wei.</span></p>
        </details>
        <button class="btn">Ürünü kaydet</button>
    </form>
</section>

<section class="card">
    <h2>Ürün listesi</h2>
    <p class="muted">Müşteriye lisans vermek için <strong>"Lisans oluştur"</strong>a bas — cüzdan penceresi açılır, <strong>onay imzası</strong>dır, para gitmez.</p>
    <table>
        <tr><th>#</th><th>Görsel</th><th>Ürün</th><th>Geçerlilik</th><th>Ücret</th><th>Müşteriye lisans ver</th></tr>
        <?php foreach ($products as $p): ?>
        <tr>
            <td><?= (int) $p['id'] ?></td>
            <td>
                <?php if (!empty($p['image'])): ?>
                    <img src="<?= View::e($p['image']) ?>" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:8px;display:block">
                <?php else: ?>
                    <span class="muted" style="font-size:12px">yok</span>
                <?php endif; ?>
                <form method="post" action="/admin/product/image" enctype="multipart/form-data" style="margin-top:4px">
                    <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                    <input type="file" name="image" accept="image/*" style="width:150px;font-size:12px" onchange="this.form.submit()">
                </form>
            </td>
            <td><strong><?= View::e($p['name']) ?></strong></td>
            <td><?= ($p['license_type'] ?? '') === 'duration' ? (int) $p['duration_days'] . ' gün' : 'Süresiz' ?></td>
            <td><?= $fmtPrice($p) ?></td>
            <td>
                <input class="rec" data-pid="<?= (int) $p['id'] ?>" placeholder="Alıcı cüzdanı (boş = herkes)" style="width:210px">
                <button class="btn btn-voucher" data-pid="<?= (int) $p['id'] ?>">Lisans oluştur</button>
                <form method="post" action="/admin/product/delete" data-confirm="Ürün ve tüm lisansları silinsin mi?" style="display:inline;margin-left:6px">
                    <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                    <button class="btn btn-ghost" style="padding:6px 12px">Sil</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?><tr><td colspan="6" class="muted">Henüz ürün yok. Yukarıdan ekle.</td></tr><?php endif; ?>
    </table>
    <p id="voucher-result" class="mono"></p>
</section>
