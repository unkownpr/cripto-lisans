<?php
/**
 * Loads .env into F3 hive vars. Included from public/index.php after autoload.
 * @var \Base $f3
 */

$envFile = __DIR__ . '/.env';
$env = [];
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
            $v = substr($v, 1, -1);
        }
        $env[trim($k)] = $v;
    }
}

$get = static function (string $key, $default = null) use ($env) {
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }
    return $env[$key] ?? $default;
};

// --- App / site ---
$autoUrl = (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080');
$f3->set('APP_URL', rtrim((string) $get('APP_URL', $autoUrl), '/'));
$f3->set('SITE_NAME', (string) $get('SITE_NAME', 'Kripto Lisans Paneli'));
$f3->set('SITE_DESC', (string) $get('SITE_DESC', 'Yazılım ve dijital ürün lisanslarını Ethereum NFT olarak üret, sat, devret ve doğrula.'));
$f3->set('SITE_TAGLINE', (string) $get('SITE_TAGLINE', 'Dijital ürün lisanslarını doğrudan cüzdanına al.'));
$f3->set('SITE_AUTHOR', (string) $get('SITE_AUTHOR', ''));
$f3->set('SITE_KEYWORDS', (string) $get('SITE_KEYWORDS', 'nft lisans, yazılım lisansı, blockchain lisans, dijital ürün'));
$f3->set('SITE_FAVICON', (string) $get('SITE_FAVICON', ''));
// DEBUG controls F3's error verbosity. Default 0 (production): no stack traces
// or source leaked to the client. Raise to 3 only in local development.
$f3->set('DEBUG', (int) $get('DEBUG', 0));
ini_set('display_errors', $f3->get('DEBUG') > 0 ? '1' : '0');

// --- Chain ---
$f3->set('CHAIN_ID', (int) $get('CHAIN_ID', 11155111));
$f3->set('RPC_URL', (string) $get('RPC_URL', ''));
$f3->set('CONTRACT', strtolower((string) $get('CONTRACT', '')));
$f3->set('EIP712_NAME', (string) $get('EIP712_NAME', 'LicensePanel'));
$f3->set('EIP712_VERSION', (string) $get('EIP712_VERSION', '1'));

$admins = array_filter(array_map(
    static fn ($a) => strtolower(trim($a)),
    explode(',', (string) $get('ADMIN_ADDRESSES', ''))
));
$f3->set('ADMIN_ADDRESSES', $admins);

// --- Database (dual driver) ---
$f3->set('DB_DRIVER', (string) $get('DB_DRIVER', 'sqlite'));
$f3->set('DB_HOST', (string) $get('DB_HOST', '127.0.0.1'));
$f3->set('DB_PORT', (int) $get('DB_PORT', 3306));
$f3->set('DB_NAME', (string) $get('DB_NAME', 'cripto_lisans'));
$f3->set('DB_USER', (string) $get('DB_USER', 'root'));
$f3->set('DB_PASS', (string) $get('DB_PASS', ''));

// --- Payments ---
$f3->set('STRIPE_SECRET', (string) $get('STRIPE_SECRET', ''));
$f3->set('STRIPE_PUBLIC', (string) $get('STRIPE_PUBLIC', ''));

// --- SMTP / e-posta (PHPMailer) ---
$f3->set('SMTP_HOST', (string) $get('SMTP_HOST', ''));
$f3->set('SMTP_PORT', (int) $get('SMTP_PORT', 587));
$f3->set('SMTP_USER', (string) $get('SMTP_USER', ''));
$f3->set('SMTP_PASS', (string) $get('SMTP_PASS', ''));
$f3->set('SMTP_SECURE', (string) $get('SMTP_SECURE', 'tls'));   // tls | ssl | none
$f3->set('SMTP_FROM', (string) $get('SMTP_FROM', ''));
$f3->set('SMTP_FROM_NAME', (string) $get('SMTP_FROM_NAME', $get('SITE_NAME', 'Kripto Lisans Paneli')));

// Optional server-side voucher signer (enables self-serve store auto-issue).
// Authorizes minting only; must hold SIGNER_ROLE on-chain. Empty = admin signs manually.
$f3->set('SIGNER_KEY', (string) $get('SIGNER_KEY', ''));

// Paths
$f3->set('DATA_DIR', __DIR__ . '/data');
$f3->set('VIEW_DIR', __DIR__ . '/ui');
$f3->set('TEMP', __DIR__ . '/tmp/');
$f3->set('INSTALL_LOCK', __DIR__ . '/data/installed.lock');
$f3->set('INSTALLED', is_file(__DIR__ . '/data/installed.lock'));

if (!is_dir($f3->get('DATA_DIR'))) {
    @mkdir($f3->get('DATA_DIR'), 0775, true);
}
if (!is_dir($f3->get('TEMP'))) {
    @mkdir($f3->get('TEMP'), 0775, true);
}

// Native PHP session (SIWE identity lives here). Hardened cookie: HttpOnly blocks
// JS theft, SameSite=Lax blunts CSRF, Secure auto-enables behind HTTPS (incl. a
// TLS-terminating proxy). Strict mode rejects attacker-fixated session ids.
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secureCookie = (($_SERVER['HTTPS'] ?? '') === 'on')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $secureCookie,
        'samesite' => 'Lax',
    ]);
    session_name('klp_sess');
    session_start();
}

// One CSRF token per session, exposed to templates via <meta name="csrf-token">.
$f3->set('CSRF_TOKEN', \App\Lib\Csrf::token());
