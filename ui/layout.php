<?php
/** @var string $content */
/** @var \Base $f3 */
/** @var ?string $user */
/** @var bool $isAdmin */
use App\Lib\View;
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    $siteName = View::e($f3->get('SITE_NAME'));
    $siteDesc = View::e($f3->get('SITE_DESC'));
    $appUrl = View::e($f3->get('APP_URL'));
    $siteAuthor = View::e($f3->get('SITE_AUTHOR'));
    $siteKeywords = View::e($f3->get('SITE_KEYWORDS'));
    $favicon = View::e($f3->get('SITE_FAVICON'));
    ?>
    <title><?= $siteName ?></title>
    <meta name="description" content="<?= $siteDesc ?>">
    <?php if ($siteAuthor): ?><meta name="author" content="<?= $siteAuthor ?>"><?php endif; ?>
    <?php if ($siteKeywords): ?><meta name="keywords" content="<?= $siteKeywords ?>"><?php endif; ?>
    <?php if ($favicon): ?><link rel="icon" href="<?= $favicon ?>"><?php else: ?><link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⛓</text></svg>"><?php endif; ?>
    <link rel="canonical" href="<?= $appUrl ?><?= View::e($_SERVER['REQUEST_URI'] ?? '/') ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $siteName ?>">
    <meta property="og:description" content="<?= $siteDesc ?>">
    <meta property="og:url" content="<?= $appUrl ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $siteName ?>">
    <meta name="twitter:description" content="<?= $siteDesc ?>">
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $f3->get('SITE_NAME'),
        'description' => $f3->get('SITE_DESC'),
        'url' => $f3->get('APP_URL'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Inter+Tight:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="/assets/tokens.css">
    <link rel="stylesheet" href="/assets/style.css">
    <meta name="csrf-token" content="<?= View::e($f3->get('CSRF_TOKEN')) ?>">
    <script src="https://cdn.jsdelivr.net/npm/ethers@6.13.0/dist/ethers.umd.min.js" defer></script>
    <script src="/assets/csrf.js" defer></script>
    <script src="/assets/app.js" defer></script>
    <?php
    $chainNames = [1 => 'Ethereum', 11155111 => 'Sepolia', 1337 => 'Ganache Local', 8453 => 'Base', 137 => 'Polygon'];
    $cn = $chainNames[(int) $f3->get('CHAIN_ID')] ?? ('Chain ' . (int) $f3->get('CHAIN_ID'));
    ?>
    <meta name="chain-id" content="<?= (int) $f3->get('CHAIN_ID') ?>">
    <meta name="chain-name" content="<?= View::e($cn) ?>">
    <meta name="rpc-url" content="<?= View::e($f3->get('RPC_URL')) ?>">
    <meta name="contract" content="<?= View::e($f3->get('CONTRACT')) ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/">
        <span class="mark" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24"><rect class="m-bg" x="2" y="2" width="20" height="20" rx="6"/><path class="m-fg" d="M8 12.4l2.6 2.6L16 9" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <?= View::e($f3->get('SITE_NAME')) ?>
    </a>
    <nav class="nav-center">
        <a href="/store">Mağaza</a>
        <a href="/licenses">Lisanslarım</a>
        <?php if ($isAdmin): ?><a href="/admin">Yönetim</a><?php endif; ?>
    </nav>
    <div class="nav-right">
        <?php if ($user): ?>
            <span class="addr" title="<?= View::e($user) ?>"><?= View::e(substr($user, 0, 6) . '…' . substr($user, -4)) ?></span>
            <a href="/auth/logout">Çıkış</a>
        <?php else: ?>
            <button id="btn-login" class="btn">MetaMask ile Giriş</button>
        <?php endif; ?>
    </div>
</header>
<main class="wrap">
    <?= $content ?>
</main>
<footer class="foot"><?= View::e($f3->get('SITE_NAME')) ?> · <?= View::e($f3->get('SITE_TAGLINE')) ?> · <?= date('Y') ?></footer>
</body>
</html>
