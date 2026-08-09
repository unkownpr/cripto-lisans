<?php

declare(strict_types=1);

namespace App\Lib;

use Elliptic\EC;
use kornrunner\Keccak;

/**
 * Ethereum signature helpers. PoC uses this ONLY to recover the signer of a
 * personal_sign (EIP-191) message for SIWE login. No private keys held here.
 */
final class EthSig
{
    /** keccak256 -> hex (no 0x). */
    public static function keccak(string $bin): string
    {
        return Keccak::hash($bin, 256);
    }

    /** 4-byte function selector (hex, no 0x) for a Solidity signature string. */
    public static function selector(string $funcSig): string
    {
        return substr(self::keccak($funcSig), 0, 8);
    }

    /**
     * Recover the address that produced a personal_sign signature.
     * Returns lowercase 0x-address, or null on malformed input.
     */
    public static function recoverPersonal(string $message, string $signature): ?string
    {
        $sig = self::strip0x($signature);
        if (strlen($sig) !== 130) {
            return null;
        }

        $r = substr($sig, 0, 64);
        $s = substr($sig, 64, 64);
        $v = hexdec(substr($sig, 128, 2));
        if ($v < 27) {
            $v += 27;
        }
        $recId = $v - 27;
        if ($recId !== 0 && $recId !== 1) {
            return null;
        }

        // EIP-191 personal_sign digest
        $prefixed = "\x19Ethereum Signed Message:\n" . strlen($message) . $message;
        $hash = self::keccak($prefixed);

        try {
            $ec = new EC('secp256k1');
            $pub = $ec->recoverPubKey($hash, ['r' => $r, 's' => $s], $recId);
        } catch (\Throwable $e) {
            return null;
        }

        // pubkey encoded as 04 || X || Y ; address = last 20 bytes of keccak(X||Y)
        $pubHex = $pub->encode('hex');
        $addrHash = self::keccak(hex2bin(substr($pubHex, 2)));

        return '0x' . substr($addrHash, 24);
    }

    public static function strip0x(string $hex): string
    {
        return str_starts_with($hex, '0x') ? substr($hex, 2) : $hex;
    }

    public static function equalsAddr(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && strtolower($a) === strtolower($b);
    }
}
