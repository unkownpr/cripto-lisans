<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Db;
use App\Lib\Rpc;
use App\Lib\View;
use Base;

/**
 * Admin panel: manage products, prepare + store signed lazy-mint vouchers.
 * The voucher is signed client-side (admin MetaMask signTypedData_v4); this
 * controller only persists the resulting signature.
 */
final class AdminController
{
    /** Non-admins see the home status card (how to log in / become admin). */
    private function guard(Base $f3): bool
    {
        if (!Auth::isAdmin()) {
            View::render('home');
            return false;
        }
        return true;
    }

    public function index(Base $f3): void
    {
        if (!$this->guard($f3)) {
            return;
        }
        $db = Db::conn();
        $counts = [
            'products' => (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn(),
            'vouchers' => (int) $db->query('SELECT COUNT(*) FROM vouchers')->fetchColumn(),
            'orders' => (int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'users' => (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        ];
        View::admin('dashboard', ['counts' => $counts], 'dashboard');
    }

    public function products(Base $f3): void
    {
        if (!$this->guard($f3)) {
            return;
        }
        $products = Db::conn()->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();
        View::admin('products', ['products' => $products], 'products');
    }

    public function licenses(Base $f3): void
    {
        if (!$this->guard($f3)) {
            return;
        }
        $vouchers = Db::conn()->query(
            'SELECT v.*, p.name AS product_name FROM vouchers v
             JOIN products p ON p.id = v.product_id ORDER BY v.id DESC'
        )->fetchAll();
        View::admin('licenses', ['vouchers' => $vouchers], 'licenses');
    }

    public function orders(Base $f3): void
    {
        if (!$this->guard($f3)) {
            return;
        }
        $orders = Db::conn()->query(
            'SELECT o.*, p.name AS product_name FROM orders o
             LEFT JOIN products p ON p.id = o.product_id ORDER BY o.id DESC'
        )->fetchAll();
        View::admin('orders', ['orders' => $orders], 'orders');
    }

    public function siteForm(Base $f3): void
    {
        if (!$this->guard($f3)) {
            return;
        }
        View::admin('site', [], 'site');
    }

    public function networkForm(Base $f3): void
    {
        if (!$this->guard($f3)) {
            return;
        }
        View::admin('network', [], 'network');
    }

    public function docs(Base $f3): void
    {
        if (!$this->guard($f3)) {
            return;
        }
        View::admin('docs', [], 'docs');
    }

    public function mailForm(Base $f3): void
    {
        if (!$this->guard($f3)) {
            return;
        }
        View::admin('mail', [], 'mail');
    }

    public function createProduct(Base $f3): void
    {
        if (!Auth::requireAdmin($f3)) {
            return;
        }

        $db = Db::conn();
        $stmt = $db->prepare(
            'INSERT INTO products (name, description, price_type, price_wei, price_fiat, currency, license_type, duration_days, active, created_at)
             VALUES (:name, :description, :price_type, :price_wei, :price_fiat, :currency, :license_type, :duration_days, 1, :created_at)'
        );
        $type = ($_POST['license_type'] ?? 'perpetual') === 'duration' ? 'duration' : 'perpetual';
        $priceType = in_array($_POST['price_type'] ?? 'free', ['free', 'fiat', 'crypto'], true)
            ? $_POST['price_type'] : 'free';
        $stmt->execute([
            'name' => trim((string) ($_POST['name'] ?? 'Untitled')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'price_type' => $priceType,
            'price_wei' => preg_replace('/\D/', '', (string) ($_POST['price_wei'] ?? '0')) ?: '0',
            'price_fiat' => preg_replace('/[^0-9.]/', '', (string) ($_POST['price_fiat'] ?? '0')) ?: '0',
            'currency' => strtoupper(substr(trim((string) ($_POST['currency'] ?? 'USD')), 0, 8)) ?: 'USD',
            'license_type' => $type,
            'duration_days' => (int) ($_POST['duration_days'] ?? 0),
            'created_at' => time(),
        ]);

        // Optional product image on create.
        $productId = (int) $db->lastInsertId();
        $url = $this->saveProductImage($productId);
        if ($url !== null) {
            $db->prepare('UPDATE products SET image = :img WHERE id = :id')
                ->execute(['img' => $url, 'id' => $productId]);
        }
        $f3->reroute('/admin/products');
    }

    /** Upload/replace a product photo for an existing product. */
    public function updateProductImage(Base $f3): void
    {
        if (!Auth::requireAdmin($f3)) {
            return;
        }
        $id = (int) ($_POST['product_id'] ?? 0);
        $db = Db::conn();
        $product = $db->query('SELECT id FROM products WHERE id = ' . $id)->fetch();
        if ($product) {
            $url = $this->saveProductImage($id);
            if ($url !== null) {
                $db->prepare('UPDATE products SET image = :img WHERE id = :id')
                    ->execute(['img' => $url, 'id' => $id]);
            }
        }
        $f3->reroute('/admin/products');
    }

    /**
     * Validate + store $_FILES['image'] as public/assets/products/product_<id>.<ext>.
     * Returns the public URL path, or null if no valid file was uploaded.
     * Mirrors the favicon upload conventions (whitelist + size cap + is_uploaded_file).
     */
    private function saveProductImage(int $productId): ?string
    {
        if ($productId <= 0 || empty($_FILES['image']['tmp_name']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            return null;
        }
        // SVG deliberately excluded: it can carry inline <script> and would run as
        // stored XSS when served same-origin from /assets/products/.
        $allowed = ['png' => 'png', 'jpg' => 'jpg', 'jpeg' => 'jpg', 'gif' => 'gif', 'webp' => 'webp'];
        $ext = strtolower(pathinfo((string) $_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!isset($allowed[$ext]) || $_FILES['image']['size'] > 3 * 1024 * 1024) {
            return null;
        }
        $dir = dirname(__DIR__, 2) . '/public/assets/products';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // Drop any prior image for this product (extension may differ).
        foreach (glob($dir . '/product_' . $productId . '.*') ?: [] as $old) {
            @unlink($old);
        }
        $file = 'product_' . $productId . '.' . $allowed[$ext];
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . '/' . $file)) {
            return null;
        }
        return '/assets/products/' . $file;
    }

    /**
     * Build the EIP-712 typed-data payload for a new voucher and persist a draft.
     * Returns JSON the browser feeds to signTypedData_v4.
     */
    public function prepareVoucher(Base $f3): void
    {
        if (!Auth::requireAdmin($f3)) {
            return;
        }
        $in = $this->input();

        $productId = (int) ($in['product_id'] ?? 0);
        $db = Db::conn();
        $product = $db->query('SELECT * FROM products WHERE id = ' . $productId)->fetch();
        if (!$product) {
            $this->json($f3, ['ok' => false, 'error' => 'product not found'], 404);
            return;
        }

        // token id: unix-ns-ish unique-ish; PoC uses max+1 fallback random
        $tokenId = (string) ($in['token_id'] ?? '');
        if ($tokenId === '' || !ctype_digit($tokenId)) {
            $tokenId = (string) random_int(1, PHP_INT_MAX);
        }

        $recipient = strtolower((string) ($in['recipient'] ?? ''));
        if (!preg_match('/^0x[0-9a-f]{40}$/', $recipient)) {
            $recipient = '0x0000000000000000000000000000000000000000';
        }

        // expiry: 0 = perpetual, else now + duration_days (or explicit expiry)
        $expiry = 0;
        if ($product['license_type'] === 'duration' && (int) $product['duration_days'] > 0) {
            $expiry = time() + ((int) $product['duration_days'] * 86400);
        }
        if (!empty($in['expiry']) && ctype_digit((string) $in['expiry'])) {
            $expiry = (int) $in['expiry'];
        }

        $uri = (string) ($in['uri'] ?? ('data:application/json,' . rawurlencode(json_encode([
            'name' => $product['name'],
            'description' => $product['description'],
        ]))));

        $stmt = $db->prepare(
            'INSERT INTO vouchers (product_id, token_id, recipient, expiry, uri, price_wei, status, created_at)
             VALUES (:pid, :tid, :rec, :exp, :uri, :price, :status, :ts)'
        );
        $stmt->execute([
            'pid' => $productId,
            'tid' => $tokenId,
            'rec' => $recipient,
            'exp' => $expiry,
            'uri' => $uri,
            'price' => (string) $product['price_wei'],
            'status' => 'draft',
            'ts' => time(),
        ]);
        $voucherId = (int) $db->lastInsertId();

        $this->json($f3, [
            'ok' => true,
            'voucher_id' => $voucherId,
            'typedData' => $this->typedData($f3, [
                'tokenId' => $tokenId,
                'productId' => (string) $productId,
                'recipient' => $recipient,
                'expiry' => $expiry,
                'uri' => $uri,
                'price' => (string) $product['price_wei'],
            ]),
        ]);
    }

    public function storeVoucher(Base $f3): void
    {
        if (!Auth::requireAdmin($f3)) {
            return;
        }
        $in = $this->input();
        $voucherId = (int) ($in['voucher_id'] ?? 0);
        $signature = (string) ($in['signature'] ?? '');

        if ($voucherId <= 0 || !preg_match('/^0x[0-9a-fA-F]{130}$/', $signature)) {
            $this->json($f3, ['ok' => false, 'error' => 'bad input'], 400);
            return;
        }

        $stmt = Db::conn()->prepare(
            "UPDATE vouchers SET signature = :sig, status = 'issued' WHERE id = :id"
        );
        $stmt->execute(['sig' => $signature, 'id' => $voucherId]);

        $this->json($f3, [
            'ok' => true,
            'claim_url' => $f3->get('APP_URL') . '/claim/' . $voucherId,
        ]);
    }

    public function revoke(Base $f3): void
    {
        // On-chain revoke is an admin tx done in the browser; here we just note intent.
        if (!Auth::requireAdmin($f3)) {
            return;
        }
        $tokenId = (string) ($_POST['token_id'] ?? '');
        $this->json($f3, ['ok' => true, 'note' => 'call revoke(' . $tokenId . ') on-chain from admin wallet']);
    }

    /** Admin edits site identity + SEO (name, author, description, keywords, favicon). */
    public function saveSite(Base $f3): void
    {
        if (!Auth::requireAdmin($f3)) {
            return;
        }
        $updates = [
            'SITE_NAME' => trim((string) ($_POST['site_name'] ?? 'Kripto Lisans Paneli')),
            'APP_URL' => rtrim(trim((string) ($_POST['app_url'] ?? $f3->get('APP_URL'))), '/'),
            'SITE_DESC' => trim((string) ($_POST['site_desc'] ?? '')),
            'SITE_TAGLINE' => trim((string) ($_POST['site_tagline'] ?? '')),
            'SITE_AUTHOR' => trim((string) ($_POST['site_author'] ?? '')),
            'SITE_KEYWORDS' => trim((string) ($_POST['site_keywords'] ?? '')),
        ];

        // Optional favicon upload -> public/assets/favicon.<ext>
        if (!empty($_FILES['favicon']['tmp_name']) && is_uploaded_file($_FILES['favicon']['tmp_name'])) {
            // SVG excluded (stored-XSS risk when served same-origin); PNG/ICO cover favicons.
            $allowed = ['png' => 'png', 'ico' => 'ico', 'jpg' => 'jpg', 'jpeg' => 'jpg', 'gif' => 'gif'];
            $ext = strtolower(pathinfo((string) $_FILES['favicon']['name'], PATHINFO_EXTENSION));
            if (isset($allowed[$ext]) && $_FILES['favicon']['size'] <= 512 * 1024) {
                $dest = dirname(__DIR__, 2) . '/public/assets/favicon.' . $allowed[$ext];
                if (move_uploaded_file($_FILES['favicon']['tmp_name'], $dest)) {
                    $updates['SITE_FAVICON'] = '/assets/favicon.' . $allowed[$ext];
                }
            }
        }
        \App\Lib\Env::update($updates);
        $f3->reroute('/admin');
    }

    /** Admin edits SMTP / e-posta settings (PHPMailer transport). */
    public function saveMail(Base $f3): void
    {
        if (!Auth::requireAdmin($f3)) {
            return;
        }
        $secure = strtolower(trim((string) ($_POST['smtp_secure'] ?? 'tls')));
        if (!in_array($secure, ['tls', 'ssl', 'none'], true)) {
            $secure = 'tls';
        }
        $updates = [
            'SMTP_HOST' => trim((string) ($_POST['smtp_host'] ?? '')),
            'SMTP_PORT' => (string) ((int) ($_POST['smtp_port'] ?? 587) ?: 587),
            'SMTP_USER' => trim((string) ($_POST['smtp_user'] ?? '')),
            'SMTP_SECURE' => $secure,
            'SMTP_FROM' => trim((string) ($_POST['smtp_from'] ?? '')),
            'SMTP_FROM_NAME' => trim((string) ($_POST['smtp_from_name'] ?? '')),
        ];
        // Only overwrite the password when a new one is typed (blank = keep existing).
        $pass = (string) ($_POST['smtp_pass'] ?? '');
        if ($pass !== '') {
            $updates['SMTP_PASS'] = $pass;
        }
        \App\Lib\Env::update($updates);
        $f3->reroute('/admin/mail');
    }

    /** Send a test email to the given address using the saved SMTP config. */
    public function testMail(Base $f3): void
    {
        if (!Auth::requireAdmin($f3)) {
            return;
        }
        $to = trim((string) ($_POST['to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['mail_flash'] = [false, 'Geçersiz e-posta adresi.'];
            $f3->reroute('/admin/mail');
            return;
        }
        [$ok, $err] = \App\Lib\Mailer::send(
            $to,
            'SMTP test — ' . $f3->get('SITE_NAME'),
            '<p>Bu bir test e-postasıdır. SMTP ayarların çalışıyor. ✅</p>'
            . '<p style="color:#888">' . View::e((string) $f3->get('SITE_NAME')) . '</p>'
        );
        $_SESSION['mail_flash'] = $ok
            ? [true, 'Test e-postası gönderildi: ' . $to]
            : [false, 'Gönderilemedi: ' . $err];
        $f3->reroute('/admin/mail');
    }

    /** Save the contract address after a one-click browser deploy. */
    public function setContract(Base $f3): void
    {
        header('Content-Type: application/json');
        if (!Auth::isAdmin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'admin gerekli']);
            return;
        }
        $in = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
        $contract = strtolower(trim((string) ($in['contract'] ?? '')));
        if (!preg_match('/^0x[0-9a-f]{40}$/', $contract)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'geçersiz adres']);
            return;
        }
        \App\Lib\Env::update(['CONTRACT' => $contract]);
        echo json_encode(['ok' => true, 'contract' => $contract]);
    }

    /** Admin changes the active network (chain id + RPC + contract) from the panel. */
    public function saveSettings(Base $f3): void
    {
        if (!Auth::requireAdmin($f3)) {
            return;
        }
        $chainId = (int) ($_POST['chain_id'] ?? 0);
        $rpc = trim((string) ($_POST['rpc_url'] ?? ''));
        $contract = strtolower(trim((string) ($_POST['contract'] ?? '')));
        if ($contract !== '' && !preg_match('/^0x[0-9a-f]{40}$/', $contract)) {
            $contract = '';
        }
        \App\Lib\Env::update([
            'CHAIN_ID' => (string) ($chainId ?: 11155111),
            'RPC_URL' => $rpc,
            'CONTRACT' => $contract,
        ]);
        $f3->reroute('/admin');
    }

    public function deleteVoucher(Base $f3): void
    {
        if (!Auth::requireAdmin($f3)) {
            return;
        }
        $id = (int) ($_POST['voucher_id'] ?? 0);
        Db::conn()->prepare('DELETE FROM vouchers WHERE id = :id')->execute(['id' => $id]);
        Db::conn()->prepare('DELETE FROM orders WHERE voucher_id = :id')->execute(['id' => $id]);
        $f3->reroute('/admin');
    }

    public function deleteProduct(Base $f3): void
    {
        if (!Auth::requireAdmin($f3)) {
            return;
        }
        $id = (int) ($_POST['product_id'] ?? 0);
        $db = Db::conn();
        $db->prepare('DELETE FROM vouchers WHERE product_id = :id')->execute(['id' => $id]);
        $db->prepare('DELETE FROM orders WHERE product_id = :id')->execute(['id' => $id]);
        $db->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $id]);
        foreach (glob(dirname(__DIR__, 2) . '/public/assets/products/product_' . $id . '.*') ?: [] as $img) {
            @unlink($img);
        }
        $f3->reroute('/admin/products');
    }

    /** EIP-712 domain + Voucher type, matching LicenseNFT.sol exactly. */
    private function typedData(Base $f3, array $voucher): array
    {
        return [
            'domain' => [
                'name' => $f3->get('EIP712_NAME'),
                'version' => $f3->get('EIP712_VERSION'),
                'chainId' => (int) $f3->get('CHAIN_ID'),
                'verifyingContract' => $f3->get('CONTRACT'),
            ],
            'types' => [
                'Voucher' => [
                    ['name' => 'tokenId', 'type' => 'uint256'],
                    ['name' => 'productId', 'type' => 'uint256'],
                    ['name' => 'recipient', 'type' => 'address'],
                    ['name' => 'expiry', 'type' => 'uint64'],
                    ['name' => 'uri', 'type' => 'string'],
                    ['name' => 'price', 'type' => 'uint256'],
                ],
            ],
            'primaryType' => 'Voucher',
            'message' => $voucher,
        ];
    }

    private function input(): array
    {
        $json = json_decode((string) file_get_contents('php://input'), true);
        return is_array($json) ? $json : $_POST;
    }

    private function json(Base $f3, array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
