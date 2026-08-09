<?php

declare(strict_types=1);

namespace App\Lib;

use Base;

/**
 * Session-backed identity. The logged-in wallet address IS the user.
 */
final class Auth
{
    public static function user(): ?string
    {
        return $_SESSION['siwe_user'] ?? null;
    }

    public static function login(string $address): void
    {
        $_SESSION['siwe_user'] = strtolower($address);
        session_regenerate_id(true);
        $_SESSION['siwe_user'] = strtolower($address);
    }

    public static function logout(): void
    {
        unset($_SESSION['siwe_user'], $_SESSION['siwe_nonce']);
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        if ($u === null) {
            return false;
        }
        $admins = Base::instance()->get('ADMIN_ADDRESSES') ?: [];
        return in_array($u, $admins, true);
    }

    /** Guard: send non-admins away. */
    public static function requireAdmin(Base $f3): bool
    {
        if (!self::isAdmin()) {
            $f3->error(403, 'Admin wallet required');
            return false;
        }
        return true;
    }
}
