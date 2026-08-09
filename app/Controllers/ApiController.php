<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Db;
use App\Lib\EthSig;
use App\Lib\RateLimit;
use App\Lib\Rpc;
use Base;

/**
 * Public license verification endpoint for external software.
 *
 *   POST /api/verify   { "token_id": "123" }
 *   GET  /api/verify?token_id=123
 *
 * Response: { valid, token_id, owner, expiry, perpetual, product_uri }
 * Truth comes straight from chain (isValid / ownerOf / expiryOf).
 */
final class ApiController
{
    public function verify(Base $f3): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        $tokenId = $this->tokenId($f3);
        if ($tokenId === null) {
            http_response_code(400);
            echo json_encode(['valid' => false, 'error' => 'token_id required']);
            return;
        }

        $contract = (string) $f3->get('CONTRACT');
        $zero = '0x0000000000000000000000000000000000000000';
        if ($f3->get('RPC_URL') === '' || $contract === '' || $contract === $zero) {
            http_response_code(503);
            echo json_encode(['valid' => false, 'error' => 'chain not configured']);
            return;
        }

        $owner = Rpc::ownerOf($tokenId);
        if ($owner === null || $owner === '0x0000000000000000000000000000000000000000') {
            echo json_encode(['valid' => false, 'token_id' => $tokenId, 'reason' => 'not minted']);
            return;
        }

        $expiry = Rpc::expiryOf($tokenId) ?? 0;
        $valid = Rpc::isValid($tokenId);

        echo json_encode([
            'valid' => $valid,
            'token_id' => $tokenId,
            'product_id' => Rpc::productOf($tokenId),
            'owner' => $owner,
            'expiry' => $expiry,
            'perpetual' => $expiry === 0,
            'product_uri' => Rpc::tokenURI($tokenId),
        ]);
    }

    /**
     * Phase 2 — Step 1 of ownership proof: issue a one-time challenge.
     *   GET /api/challenge?token_id=123
     * Returns { nonce, message }. The wallet signs `message` verbatim.
     */
    public function challenge(Base $f3): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        if (!RateLimit::allow('chal:' . RateLimit::ip(), 120, 60)) {
            http_response_code(429);
            echo json_encode(['error' => 'rate limited']);
            return;
        }

        $tokenId = $this->tokenId($f3);
        if ($tokenId === null) {
            http_response_code(400);
            echo json_encode(['error' => 'token_id required']);
            return;
        }

        Db::gcChallenges();
        $nonce = bin2hex(random_bytes(16));
        $stmt = Db::conn()->prepare(
            'INSERT INTO challenges (nonce, token_id, created_at) VALUES (:n, :t, :ts)'
        );
        $stmt->execute(['n' => $nonce, 't' => $tokenId, 'ts' => time()]);

        $domain = parse_url((string) $f3->get('APP_URL'), PHP_URL_HOST) ?: 'localhost';
        $message = "{$domain} lisans sahiplik kanıtı.\n"
            . "Token: {$tokenId}\nNonce: {$nonce}";

        echo json_encode(['nonce' => $nonce, 'token_id' => $tokenId, 'message' => $message]);
    }

    /**
     * Phase 2 — Step 2: verify the signer actually owns the token.
     *   POST /api/verify-owner { token_id, message, signature }
     * Proves the caller controls the wallet that owns a valid token.
     */
    public function verifyOwner(Base $f3): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        $in = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
        $tokenId = (string) ($in['token_id'] ?? '');
        $message = (string) ($in['message'] ?? '');
        $signature = (string) ($in['signature'] ?? '');

        if (!ctype_digit($tokenId) || $message === '' || $signature === '') {
            http_response_code(400);
            echo json_encode(['valid' => false, 'error' => 'token_id, message, signature required']);
            return;
        }

        // Extract + consume the one-time nonce (bound to this token).
        if (!preg_match('/Nonce:\s*([0-9a-f]{32})/', $message, $m)) {
            http_response_code(400);
            echo json_encode(['valid' => false, 'error' => 'nonce missing in message']);
            return;
        }
        $nonce = $m[1];

        $db = Db::conn();
        $row = $db->prepare('SELECT token_id, created_at FROM challenges WHERE nonce = :n');
        $row->execute(['n' => $nonce]);
        $ch = $row->fetch();
        // one-time: delete regardless of outcome
        $db->prepare('DELETE FROM challenges WHERE nonce = :n')->execute(['n' => $nonce]);

        if (!$ch || $ch['token_id'] !== $tokenId || (time() - (int) $ch['created_at']) > 300) {
            http_response_code(401);
            echo json_encode(['valid' => false, 'error' => 'challenge invalid or expired']);
            return;
        }

        $recovered = EthSig::recoverPersonal($message, $signature);
        if ($recovered === null) {
            echo json_encode(['valid' => false, 'error' => 'bad signature']);
            return;
        }

        $owner = Rpc::ownerOf($tokenId);
        $ownsIt = $owner !== null && EthSig::equalsAddr($recovered, $owner);
        $active = Rpc::isValid($tokenId);

        echo json_encode([
            'valid' => $ownsIt && $active,
            'token_id' => $tokenId,
            'signer' => $recovered,
            'owner' => $owner,
            'owns_it' => $ownsIt,
            'active' => $active,
            'expiry' => Rpc::expiryOf($tokenId) ?? 0,
        ]);
    }

    /**
     * Wallet-based access check — the endpoint your product software calls.
     *
     *   GET  /api/access?product_id=1        -> { nonce, message }  (wallet signs this)
     *   POST /api/access { product_id, message, signature }
     *        -> { valid, address, token_id }  (does this wallet own a valid license?)
     *
     * No token id needed: the customer just connects their wallet.
     */
    public function access(Base $f3): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            if (!RateLimit::allow('acc:' . RateLimit::ip(), 120, 60)) {
                http_response_code(429);
                echo json_encode(['valid' => false, 'error' => 'rate limited']);
                return;
            }
            $productId = (int) ($_GET['product_id'] ?? 0);
            Db::gcChallenges();
            $nonce = bin2hex(random_bytes(16));
            Db::conn()->prepare('INSERT INTO challenges (nonce, token_id, created_at) VALUES (:n, :t, :ts)')
                ->execute(['n' => $nonce, 't' => 'access:' . $productId, 'ts' => time()]);
            $domain = parse_url((string) $f3->get('APP_URL'), PHP_URL_HOST) ?: 'localhost';
            $message = "{$domain} lisans erişim kontrolü.\nÜrün: {$productId}\nNonce: {$nonce}";
            echo json_encode(['nonce' => $nonce, 'product_id' => $productId, 'message' => $message]);
            return;
        }

        $in = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
        $productId = (int) ($in['product_id'] ?? 0);
        $message = (string) ($in['message'] ?? '');
        $signature = (string) ($in['signature'] ?? '');

        if (!preg_match('/Nonce:\s*([0-9a-f]{32})/', $message, $m)) {
            http_response_code(400);
            echo json_encode(['valid' => false, 'error' => 'geçersiz istek']);
            return;
        }
        $nonce = $m[1];
        $db = Db::conn();
        $row = $db->prepare('SELECT token_id, created_at FROM challenges WHERE nonce = :n');
        $row->execute(['n' => $nonce]);
        $ch = $row->fetch();
        $db->prepare('DELETE FROM challenges WHERE nonce = :n')->execute(['n' => $nonce]);
        if (!$ch || $ch['token_id'] !== 'access:' . $productId || (time() - (int) $ch['created_at']) > 300) {
            http_response_code(401);
            echo json_encode(['valid' => false, 'error' => 'challenge geçersiz/süresi doldu']);
            return;
        }

        $address = EthSig::recoverPersonal($message, $signature);
        if ($address === null) {
            echo json_encode(['valid' => false, 'error' => 'imza geçersiz']);
            return;
        }

        // Does this wallet own any valid token for this product?
        $rows = $db->prepare(
            "SELECT token_id FROM vouchers WHERE product_id = :p AND status IN ('issued','claimed')"
        );
        $rows->execute(['p' => $productId]);
        foreach ($rows->fetchAll() as $r) {
            $owner = Rpc::ownerOf($r['token_id']);
            if ($owner && EthSig::equalsAddr($owner, $address) && Rpc::isValid($r['token_id'])) {
                echo json_encode(['valid' => true, 'address' => $address, 'token_id' => $r['token_id']]);
                return;
            }
        }
        echo json_encode(['valid' => false, 'address' => $address, 'reason' => 'no valid license']);
    }

    private function tokenId(Base $f3): ?string
    {
        $json = json_decode((string) file_get_contents('php://input'), true);
        $raw = $json['token_id'] ?? $_GET['token_id'] ?? $_POST['token_id'] ?? null;
        if ($raw === null) {
            return null;
        }
        $raw = (string) $raw;
        return ctype_digit($raw) ? $raw : null;
    }
}
