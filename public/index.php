<?php
/**
 * Front controller. Serve with:  php -S localhost:8080 -t public
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$f3 = \Base::instance();
require __DIR__ . '/../config.php';

$f3->set('AUTOLOAD', __DIR__ . '/../app/');

// --- Baseline security headers (applied to every response) ---
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// --- Install guard: until installed, force the wizard (assets/verify-lib pass) ---
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$allowed = str_starts_with($path, '/install')
    || str_starts_with($path, '/assets')
    || str_starts_with($path, '/verify-lib');

if (!$f3->get('INSTALLED')) {
    if (!$allowed) {
        $f3->reroute('/install');
    }
} else {
    \App\Lib\Db::init($f3);
}

// --- CSRF gate: verify token on cookie-authenticated, state-changing requests.
// Excludes the public /api/* (cross-origin, signature-authed) and /auth/verify
// (bootstraps the session, protected by its own nonce + signature). ---
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$csrfProtected = $method === 'POST' && (
    str_starts_with($path, '/admin')
    || str_starts_with($path, '/install')
    || $path === '/store/buy'
);
if ($csrfProtected && !\App\Lib\Csrf::check()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'CSRF doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.']);
    exit;
}

// --- Installer ---
$f3->route('GET /install', 'App\Controllers\InstallController->index');
$f3->route('POST /install/dbtest', 'App\Controllers\InstallController->dbtest');
$f3->route('POST /install/run', 'App\Controllers\InstallController->run');

// --- Pages ---
$f3->route('GET /', 'App\Controllers\HomeController->index');
$f3->route('GET /admin', 'App\Controllers\AdminController->index');
$f3->route('GET /admin/products', 'App\Controllers\AdminController->products');
$f3->route('GET /admin/licenses', 'App\Controllers\AdminController->licenses');
$f3->route('GET /admin/orders', 'App\Controllers\AdminController->orders');
$f3->route('GET /admin/site', 'App\Controllers\AdminController->siteForm');
$f3->route('GET /admin/network', 'App\Controllers\AdminController->networkForm');
$f3->route('GET /admin/mail', 'App\Controllers\AdminController->mailForm');
$f3->route('GET /admin/docs', 'App\Controllers\AdminController->docs');
$f3->route('GET /licenses', 'App\Controllers\LicenseController->mine');
$f3->route('GET /claim/@id', 'App\Controllers\LicenseController->claim');

// --- Store (public catalog + purchase) ---
$f3->route('GET /store', 'App\Controllers\StoreController->index');
$f3->route('POST /store/buy', 'App\Controllers\StoreController->buy');

// --- Auth (SIWE) ---
$f3->route('GET /auth/nonce', 'App\Controllers\AuthController->nonce');
$f3->route('POST /auth/verify', 'App\Controllers\AuthController->verify');
$f3->route('GET /auth/logout', 'App\Controllers\AuthController->logout');

// --- Admin actions ---
$f3->route('POST /admin/product', 'App\Controllers\AdminController->createProduct');
$f3->route('POST /admin/voucher/prepare', 'App\Controllers\AdminController->prepareVoucher');
$f3->route('POST /admin/voucher/store', 'App\Controllers\AdminController->storeVoucher');
$f3->route('POST /admin/revoke', 'App\Controllers\AdminController->revoke');
$f3->route('POST /admin/voucher/delete', 'App\Controllers\AdminController->deleteVoucher');
$f3->route('POST /admin/product/delete', 'App\Controllers\AdminController->deleteProduct');
$f3->route('POST /admin/product/image', 'App\Controllers\AdminController->updateProductImage');
$f3->route('POST /admin/settings', 'App\Controllers\AdminController->saveSettings');
$f3->route('POST /admin/site', 'App\Controllers\AdminController->saveSite');
$f3->route('POST /admin/mail', 'App\Controllers\AdminController->saveMail');
$f3->route('POST /admin/mail/test', 'App\Controllers\AdminController->testMail');
$f3->route('POST /admin/contract-set', 'App\Controllers\AdminController->setContract');

// --- Public verification API ---
$f3->route('GET|POST /api/verify', 'App\Controllers\ApiController->verify');
$f3->route('GET /api/challenge', 'App\Controllers\ApiController->challenge');
$f3->route('POST /api/verify-owner', 'App\Controllers\ApiController->verifyOwner');
$f3->route('GET|POST /api/access', 'App\Controllers\ApiController->access');

// --- Integration downloads ---
$f3->route('GET /download/license.php', 'App\Controllers\DownloadController->licensePhp');
$f3->route('GET /download/license.py', 'App\Controllers\DownloadController->licensePy');
$f3->route('GET /download/license.go', 'App\Controllers\DownloadController->licenseGo');
$f3->route('GET /download/license.cs', 'App\Controllers\DownloadController->licenseCs');
$f3->route('GET /download/contract', 'App\Controllers\DownloadController->contract');

// --- SEO / GEO ---
$f3->route('GET /sitemap.xml', 'App\Controllers\SeoController->sitemap');
$f3->route('GET /robots.txt', 'App\Controllers\SeoController->robots');
$f3->route('GET /llms.txt', 'App\Controllers\SeoController->llms');

$f3->run();
