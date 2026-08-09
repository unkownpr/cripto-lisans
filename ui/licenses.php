<?php
use App\Lib\View;
/** @var array $owned */
/** @var ?string $user */
?>
<section class="card">
    <h1>Lisanslarım</h1>
    <?php if (!$user): ?>
        <p>Cüzdanınla giriş yap (sağ üst). Cüzdanın senin kimliğin — ayrı şifre yok.</p>
        <p><button id="btn-login" class="btn">MetaMask ile Giriş</button></p>
    <?php elseif (!$owned): ?>
        <p class="muted">Cüzdanında henüz lisans yok. <a href="/store">Mağazadan</a> alabilir ya da sana gönderilen linkten alabilirsin.</p>
    <?php else: ?>
        <p class="muted">Bu lisanslar senin cüzdanına ait. Birini devredersen artık diğer kişinin olur, sende kalmaz.</p>
        <table>
            <tr><th>Ürün</th><th>Lisans no</th><th>Geçerlilik</th><th>Durum</th><th>Başkasına devret</th></tr>
            <?php foreach ($owned as $r): ?>
            <tr data-token="<?= View::e($r['token_id']) ?>">
                <td><strong><?= View::e($r['product_name']) ?></strong></td>
                <td class="mono" title="<?= View::e($r['token_id']) ?>"><?= View::e(substr($r['token_id'], 0, 8)) ?>…</td>
                <td><?= (int) ($r['on_expiry'] ?? 0) === 0 ? 'Süresiz' : date('d.m.Y', (int) $r['on_expiry']) ?></td>
                <td><?php if (!empty($r['valid'])): ?><span class="badge badge--ok">Aktif</span><?php else: ?><span class="badge badge--muted">Süresi dolmuş</span><?php endif; ?></td>
                <td>
                    <input class="to" placeholder="Devredilecek cüzdan adresi (0x…)" style="width:230px">
                    <button class="btn btn-ghost btn-transfer" data-token="<?= View::e($r['token_id']) ?>">Devret</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p id="transfer-status" class="mono"></p>
        <p class="muted" style="font-size:var(--text-xs)">Devir cüzdan penceresinde onaylanır; küçük bir ağ ücreti olabilir.</p>
    <?php endif; ?>
</section>
