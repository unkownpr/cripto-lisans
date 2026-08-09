<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Db;
use App\Lib\View;
use Base;
use PDO;

/**
 * First-run install wizard. Collects site name, DB (MySQL/SQLite), chain config
 * and the admin wallet (captured via MetaMask in-browser). Writes .env, runs
 * migrations, drops the install lock. Guarded off once installed.
 */
final class InstallController
{
    public function index(Base $f3): void
    {
        if ($f3->get('INSTALLED')) {
            $f3->reroute('/');
            return;
        }
        // Mint the one-time setup token on first visit. It is written to disk only;
        // the operator reads it from the server and pastes it into the form. This
        // closes the "first anonymous visitor becomes admin" race on a fresh deploy.
        $this->ensureSetupToken($f3);
        View::renderRaw('install', [
            'autoUrl' => $f3->get('APP_URL'),
            'chainId' => $f3->get('CHAIN_ID'),
        ]);
    }

    /** POST — test a MySQL connection before committing. */
    public function dbtest(Base $f3): void
    {
        header('Content-Type: application/json');
        if ($f3->get('INSTALLED')) {
            echo json_encode(['ok' => false, 'error' => 'already installed']);
            return;
        }
        if (!$this->checkSetupToken($f3)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Kurulum kodu geçersiz (data/setup_token.txt)']);
            return;
        }
        $in = $this->input();
        if (($in['db_driver'] ?? 'sqlite') === 'sqlite') {
            echo json_encode(['ok' => true, 'note' => 'SQLite — bağlantı gerekmez']);
            return;
        }
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $in['db_host'] ?? '127.0.0.1',
                (int) ($in['db_port'] ?? 3306),
                $in['db_name'] ?? ''
            );
            new PDO($dsn, $in['db_user'] ?? '', $in['db_pass'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo json_encode(['ok' => true, 'note' => 'MySQL bağlantısı başarılı']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** POST — commit install. */
    public function run(Base $f3): void
    {
        header('Content-Type: application/json');
        if ($f3->get('INSTALLED')) {
            echo json_encode(['ok' => false, 'error' => 'already installed']);
            return;
        }
        if (!$this->checkSetupToken($f3)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Kurulum kodu geçersiz (data/setup_token.txt)']);
            return;
        }

        $in = $this->input();
        $admin = strtolower(trim((string) ($in['admin_address'] ?? '')));
        if (!preg_match('/^0x[0-9a-f]{40}$/', $admin)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'MetaMask ile admin cüzdanı bağla']);
            return;
        }

        $driver = ($in['db_driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';

        // Apply to hive so we can migrate immediately.
        $vals = [
            'SITE_NAME' => trim((string) ($in['site_name'] ?? 'Kripto Lisans Paneli')),
            'SITE_DESC' => trim((string) ($in['site_desc'] ?? $f3->get('SITE_DESC'))),
            'APP_URL' => rtrim((string) ($in['app_url'] ?? $f3->get('APP_URL')), '/'),
            'CHAIN_ID' => (int) ($in['chain_id'] ?? 11155111),
            'RPC_URL' => trim((string) ($in['rpc_url'] ?? '')),
            'CONTRACT' => strtolower(trim((string) ($in['contract'] ?? ''))),
            'EIP712_NAME' => 'LicensePanel',
            'EIP712_VERSION' => '1',
            'ADMIN_ADDRESSES' => $admin,
            'DB_DRIVER' => $driver,
            'DB_HOST' => (string) ($in['db_host'] ?? '127.0.0.1'),
            'DB_PORT' => (int) ($in['db_port'] ?? 3306),
            'DB_NAME' => (string) ($in['db_name'] ?? 'cripto_lisans'),
            'DB_USER' => (string) ($in['db_user'] ?? 'root'),
            'DB_PASS' => (string) ($in['db_pass'] ?? ''),
            'STRIPE_SECRET' => trim((string) ($in['stripe_secret'] ?? '')),
            'STRIPE_PUBLIC' => trim((string) ($in['stripe_public'] ?? '')),
        ];
        foreach ($vals as $k => $v) {
            $f3->set($k, $k === 'ADMIN_ADDRESSES' ? [$admin] : $v);
        }

        // Migrate (also validates DB reachability).
        try {
            $pdo = Db::connect($f3);
            Db::migrate($pdo, $driver);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'DB hatası: ' . $e->getMessage()]);
            return;
        }

        // Persist .env + lock.
        $this->writeEnv(dirname(__DIR__, 2) . '/.env', $vals);
        file_put_contents($f3->get('INSTALL_LOCK'), (string) time());

        // Install is done — the setup token has served its purpose.
        @unlink($this->setupTokenPath($f3));

        echo json_encode(['ok' => true, 'redirect' => '/']);
    }

    private function writeEnv(string $path, array $vals): void
    {
        $lines = ['# Auto-generated by install wizard', ''];
        foreach ($vals as $k => $v) {
            // Strip CR/LF so a crafted field (e.g. site_name) can't inject
            // extra .env lines like SIGNER_KEY=...
            $clean = str_replace(["\r", "\n"], '', (string) $v);
            $lines[] = $k . '=' . $clean;
        }
        file_put_contents($path, implode("\n", $lines) . "\n");
        @chmod($path, 0600);
    }

    private function setupTokenPath(Base $f3): string
    {
        return $f3->get('DATA_DIR') . '/setup_token.txt';
    }

    /** Create the setup token on first run; return it. Written 0600, never sent to the client. */
    private function ensureSetupToken(Base $f3): string
    {
        $path = $this->setupTokenPath($f3);
        $existing = is_file($path) ? trim((string) file_get_contents($path)) : '';
        if ($existing !== '') {
            return $existing;
        }
        $token = bin2hex(random_bytes(16));
        file_put_contents($path, $token);
        @chmod($path, 0600);
        return $token;
    }

    /** Constant-time compare of the submitted setup token against the on-disk one. */
    private function checkSetupToken(Base $f3): bool
    {
        $path = $this->setupTokenPath($f3);
        $expected = is_file($path) ? trim((string) file_get_contents($path)) : '';
        if ($expected === '') {
            return false;
        }
        $sent = trim((string) ($this->input()['setup_token'] ?? ''));
        return $sent !== '' && hash_equals($expected, $sent);
    }

    private function input(): array
    {
        $json = json_decode((string) file_get_contents('php://input'), true);
        return is_array($json) ? $json : $_POST;
    }
}
