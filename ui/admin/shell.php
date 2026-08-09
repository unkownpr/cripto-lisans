<?php
use App\Lib\View;
/** @var \Base $f3 */
/** @var string $content */
/** @var ?string $user */
/** @var string $active */

$nav = [
    'dashboard' => ['/admin', 'Genel bakış'],
    'products' => ['/admin/products', 'Ürünler'],
    'licenses' => ['/admin/licenses', 'Lisanslar'],
    'orders' => ['/admin/orders', 'Siparişler'],
];
$navSettings = [
    'site' => ['/admin/site', 'Site ayarları'],
    'mail' => ['/admin/mail', 'E-posta (SMTP)'],
    'network' => ['/admin/network', 'Ağ & Kontrat'],
    'docs' => ['/admin/docs', 'Entegrasyon'],
];
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Yönetim · <?= View::e($f3->get('SITE_NAME')) ?></title>
    <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Inter+Tight:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="/assets/tokens.css">
    <link rel="stylesheet" href="/assets/style.css">
    <meta name="csrf-token" content="<?= View::e($f3->get('CSRF_TOKEN')) ?>">
    <meta name="chain-id" content="<?= (int) $f3->get('CHAIN_ID') ?>">
    <?php $cn = [1=>'Ethereum',11155111=>'Sepolia',1337=>'Ganache Local',8453=>'Base',137=>'Polygon'][(int)$f3->get('CHAIN_ID')] ?? 'Chain '.(int)$f3->get('CHAIN_ID'); ?>
    <meta name="chain-name" content="<?= View::e($cn) ?>">
    <meta name="rpc-url" content="<?= View::e($f3->get('RPC_URL')) ?>">
    <meta name="contract" content="<?= View::e($f3->get('CONTRACT')) ?>">
    <script src="https://cdn.jsdelivr.net/npm/ethers@6.13.0/dist/ethers.umd.min.js" defer></script>
    <script src="/assets/csrf.js" defer></script>
    <script src="/assets/app.js" defer></script>
</head>
<body>
<div class="admin">
    <aside class="sidebar">
        <a class="brand" href="/">
            <span class="mark" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24"><rect class="m-bg" x="2" y="2" width="20" height="20" rx="6"/><path class="m-fg" d="M8 12.4l2.6 2.6L16 9" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <?= View::e($f3->get('SITE_NAME')) ?>
        </a>
        <nav>
            <?php foreach ($nav as $key => [$href, $label]): ?>
                <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
            <div class="side-label">Ayarlar</div>
            <?php foreach ($navSettings as $key => [$href, $label]): ?>
                <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
            <hr class="side-sep">
            <a href="/store">Mağaza (önyüz)</a>
            <a href="/licenses">Lisanslarım</a>
            <a href="/auth/logout">Çıkış</a>
        </nav>
    </aside>
    <main class="admin-main">
        <?= $content ?>
        <footer class="admin-footer">Developed by <a href="https://ssilistre.dev" target="_blank" rel="noopener">ssilistre.dev</a></footer>
    </main>
</div>

<div id="confirm-modal" class="modal-overlay" hidden>
    <div class="modal" role="dialog" aria-modal="true">
        <h3 id="confirm-title">Emin misin?</h3>
        <p id="confirm-msg" class="muted"></p>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" id="confirm-cancel">Vazgeç</button>
            <button type="button" class="btn btn-danger" id="confirm-ok">Sil</button>
        </div>
    </div>
</div>
</body>
</html>
