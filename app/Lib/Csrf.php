<?php

declare(strict_types=1);

namespace App\Lib;

/**
 * Double-submit CSRF protection. One random token per session; forms carry it
 * as a hidden `_csrf` field and fetch() calls as an `X-CSRF-Token` header
 * (both injected client-side by /assets/csrf.js). Verified server-side for every
 * state-changing, cookie-authenticated request.
 */
final class Csrf
{
    /** The session token, minted on first use. */
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /** True when the request carries a token matching the session token. */
    public static function check(): bool
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
        $token = $_SESSION['csrf'] ?? '';
        return is_string($sent) && $sent !== '' && $token !== '' && hash_equals($token, $sent);
    }
}
