<?php
use App\Lib\View;
/** @var array $vouchers */
$statusLabel = static fn (string $s): array => match ($s) {
    'claimed' => ['Müşteri aldı', 'badge--ok'],
    'issued' => ['Hazır — linki gönder', 'badge--warn'],
    default => ['Taslak', 'badge--muted'],
};
?>
<div class="admin-head"><h1>Lisanslar</h1></div>

<section class="card">
    <h2>Oluşturulan lisanslar</h2>
    <table>
        <tr><th>#</th><th>Ürün</th><th>Lisans no</th><th>Alıcı</th><th>Geçerlilik</th><th>Durum</th><th>Müşteri linki</th><th>Sil</th></tr>
        <?php foreach ($vouchers as $v): ?>
        <tr>
            <td><?= (int) $v['id'] ?></td>
            <td><?= View::e($v['product_name']) ?></td>
            <td class="mono" title="<?= View::e($v['token_id']) ?>"><?= View::e(substr($v['token_id'], 0, 8)) ?>…</td>
            <td class="mono"><?= $v['recipient'] === '0x0000000000000000000000000000000000000000' ? 'herkes' : View::e(substr($v['recipient'], 0, 8)) . '…' ?></td>
            <td><?= (int) $v['expiry'] === 0 ? 'Süresiz' : date('d.m.Y', (int) $v['expiry']) ?></td>
            <td><?php [$lbl, $cls] = $statusLabel($v['status']); ?><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
            <td><?php if ($v['status'] !== 'draft'): ?><a href="/claim/<?= (int) $v['id'] ?>">Linki aç</a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
            <td>
                <form method="post" action="/admin/voucher/delete" data-confirm="Bu lisans kaydı silinsin mi?" style="margin:0">
                    <input type="hidden" name="voucher_id" value="<?= (int) $v['id'] ?>">
                    <button class="btn btn-ghost" style="padding:4px 10px">Sil</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$vouchers): ?><tr><td colspan="8" class="muted">Henüz lisans oluşturulmadı. <a href="/admin/products">Ürünlerden</a> "Lisans oluştur" ile üret.</td></tr><?php endif; ?>
    </table>
</section>
