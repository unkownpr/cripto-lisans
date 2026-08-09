<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Db;
use App\Lib\EthSig;
use App\Lib\RateLimit;
use Base;

/**
 * Sign-In With Ethereum (simplified EIP-4361). Flow:
 *   1. GET  /auth/nonce   -> issue + store a one-time nonce
 *   2. client personal_signs a message embedding that nonce
 *   3. POST /auth/verify  -> recover signer, match nonce, open session
 */
final class AuthController
{
    public function nonce(Base $f3): void
    {
        if (!RateLimit::allow('nonce:' . RateLimit::ip(), 60, 60)) {
            $this->json($f3, ['error' => 'Çok fazla istek, biraz bekleyin'], 429);
            return;
        }
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['siwe_nonce'] = $nonce;

        $this->json($f3, [
            'nonce' => $nonce,
            'domain' => parse_url($f3->get('APP_URL'), PHP_URL_HOST) ?: 'localhost',
            'chainId' => $f3->get('CHAIN_ID'),
        ]);
    }

    public function verify(Base $f3): void
    {
        $in = $this->input();
        $address = strtolower((string) ($in['address'] ?? ''));
        $message = (string) ($in['message'] ?? '');
        $signature = (string) ($in['signature'] ?? '');

        $nonce = $_SESSION['siwe_nonce'] ?? null;
        if (!$nonce || !str_contains($message, $nonce)) {
            $this->json($f3, ['ok' => false, 'error' => 'nonce mismatch'], 400);
            return;
        }

        $recovered = EthSig::recoverPersonal($message, $signature);
        if ($recovered === null || !EthSig::equalsAddr($recovered, $address)) {
            $this->json($f3, ['ok' => false, 'error' => 'signature invalid'], 401);
            return;
        }

        unset($_SESSION['siwe_nonce']);
        Auth::login($address);
        $this->autoRegister($address);

        $this->json($f3, [
            'ok' => true,
            'address' => $address,
            'isAdmin' => Auth::isAdmin(),
        ]);
    }

    /** Auto-register the wallet as a user on first login (cross-driver upsert). */
    private function autoRegister(string $address): void
    {
        $db = Db::conn();
        $now = time();
        $sel = $db->prepare('SELECT address FROM users WHERE address = :a');
        $sel->execute(['a' => $address]);
        if ($sel->fetch()) {
            $db->prepare('UPDATE users SET last_login = :t WHERE address = :a')
                ->execute(['t' => $now, 'a' => $address]);
        } else {
            $db->prepare('INSERT INTO users (address, created_at, last_login) VALUES (:a, :c, :l)')
                ->execute(['a' => $address, 'c' => $now, 'l' => $now]);
        }
    }

    public function logout(Base $f3): void
    {
        Auth::logout();
        $f3->reroute('/');
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        $json = json_decode((string) $raw, true);
        return is_array($json) ? $json : $_POST;
    }

    private function json(Base $f3, array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
