<?php
use App\Lib\View;
/** @var array $orders */
$method = ['free' => 'Ücretsiz', 'crypto' => 'Kripto', 'fiat' => 'Kart'];
$statusCls = ['issued' => 'badge--ok', 'claimed' => 'badge--ok', 'paid' => 'badge--warn', 'pending' => 'badge--muted'];
?>
<div class="admin-head"><h1>Siparişler</h1></div>

<section class="card">
    <table>
        <tr><th>#</th><th>Müşteri</th><th>Ürün</th><th>Yöntem</th><th>Durum</th><th>Tutar</th><th>Tarih</th></tr>
        <?php foreach ($orders as $o): ?>
        <tr>
            <td><?= (int) $o['id'] ?></td>
            <td class="mono"><?= View::e(substr($o['user_addr'], 0, 8)) ?>…</td>
            <td><?= View::e($o['product_name'] ?? ('#' . $o['product_id'])) ?></td>
            <td><?= $method[$o['method']] ?? View::e($o['method']) ?></td>
            <td><span class="badge <?= $statusCls[$o['status']] ?? 'badge--muted' ?>"><?= View::e($o['status']) ?></span></td>
            <td class="mono"><?= (int) $o['amount'] === 0 || $o['amount'] === '0' ? '—' : View::e($o['amount']) ?></td>
            <td class="muted"><?= date('d.m.Y H:i', (int) $o['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="7" class="muted">Henüz sipariş yok. Mağazadan satın alma yapılınca burada listelenir.</td></tr><?php endif; ?>
    </table>
</section>
