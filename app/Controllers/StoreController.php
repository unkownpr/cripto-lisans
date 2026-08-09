<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Db;
use App\Lib\View;
use App\Lib\Vouchers;
use Base;

/**
 * Public store: browse active products, buy (free / crypto / fiat). Buying binds
 * a voucher to the buyer's wallet. If a server SIGNER_KEY is set the voucher is
 * auto-signed (instant claim); otherwise it's queued for the admin to sign.
 */
final class StoreController
{
    public function index(Base $f3): void
    {
        $products = Db::conn()
            ->query('SELECT * FROM products WHERE active = 1 ORDER BY id DESC')
            ->fetchAll();
        View::render('store', ['products' => $products]);
    }

    public function buy(Base $f3): void
    {
        header('Content-Type: application/json');

        $user = Auth::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Önce MetaMask ile giriş yap']);
            return;
        }

        $in = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
        $productId = (int) ($in['product_id'] ?? 0);
        $db = Db::conn();
        $stmt = $db->prepare('SELECT * FROM products WHERE id = :id AND active = 1');
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch();
        if (!$product) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'ürün bulunamadı']);
            return;
        }

        $method = $product['price_type']; // free | fiat | crypto

        // Fiat: needs a real gateway. Stub cleanly until Stripe keys wired.
        if ($method === 'fiat') {
            $this->order($db, $user, $productId, null, 'fiat', 'pending', (string) $product['price_fiat']);
            echo json_encode([
                'ok' => false,
                'fiat' => true,
                'error' => 'Fiat ödeme (Stripe) henüz bağlı değil — faz-sonrası. Şimdilik bedava/kripto ürün seç.',
            ]);
            return;
        }

        // Free & crypto: bind a voucher to the buyer's wallet.
        $v = Vouchers::create($product, $user);
        $orderId = $this->order(
            $db, $user, $productId, $v['voucher_id'], $method,
            $v['signed'] ? 'issued' : 'pending',
            $method === 'crypto' ? (string) $product['price_wei'] : '0'
        );

        if ($v['signed']) {
            echo json_encode([
                'ok' => true,
                'order_id' => $orderId,
                'claim_url' => $f3->get('APP_URL') . '/claim/' . $v['voucher_id'],
                'message' => $method === 'crypto'
                    ? 'Voucher hazır. Claim sayfasında ürün fiyatını ödeyip NFT’yi bas.'
                    : 'Voucher hazır. Claim sayfasında ücretsiz bas (sadece gas).',
            ]);
        } else {
            echo json_encode([
                'ok' => true,
                'pending' => true,
                'order_id' => $orderId,
                'message' => 'Talep alındı. Admin voucher’ı imzalayınca "Lisanslarım"da claim linkin çıkar.',
            ]);
        }
    }

    private function order($db, string $user, int $productId, ?int $voucherId, string $method, string $status, string $amount): int
    {
        $stmt = $db->prepare(
            'INSERT INTO orders (user_addr, product_id, voucher_id, method, status, amount, created_at)
             VALUES (:u, :p, :v, :m, :s, :a, :ts)'
        );
        $stmt->execute([
            'u' => $user, 'p' => $productId, 'v' => $voucherId,
            'm' => $method, 's' => $status, 'a' => $amount, 'ts' => time(),
        ]);
        return (int) $db->lastInsertId();
    }
}
