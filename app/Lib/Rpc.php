<?php

declare(strict_types=1);

namespace App\Lib;

use Base;

/**
 * Minimal read-only JSON-RPC client. Encodes calldata for the few view
 * functions the panel needs and parses the returned 32-byte words.
 *
 * Only uint256/address args are supported (all this PoC uses).
 */
final class Rpc
{
    /** eth_call a contract view fn. $args are decimal-string or int (uint) or 0x-address. */
    public static function call(string $funcSig, array $args = [], ?string $to = null): ?string
    {
        $f3 = Base::instance();
        $to ??= $f3->get('CONTRACT');

        $data = '0x' . EthSig::selector($funcSig);
        foreach ($args as $arg) {
            $data .= self::encodeWord($arg);
        }

        $body = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'eth_call',
            'params' => [['to' => $to, 'data' => $data], 'latest'],
        ];

        $res = self::post($f3->get('RPC_URL'), $body);
        if ($res === null || isset($res['error'])) {
            return null; // revert (e.g. ownerOf on nonexistent token) or transport error
        }
        return $res['result'] ?? null;
    }

    public static function ownerOf(string $tokenId): ?string
    {
        $r = self::call('ownerOf(uint256)', [$tokenId]);
        return $r === null ? null : self::toAddress($r);
    }

    public static function expiryOf(string $tokenId): ?int
    {
        $r = self::call('expiryOf(uint256)', [$tokenId]);
        return $r === null ? null : self::toInt($r);
    }

    public static function isValid(string $tokenId): bool
    {
        $r = self::call('isValid(uint256)', [$tokenId]);
        return $r !== null && self::toInt($r) === 1;
    }

    public static function productOf(string $tokenId): ?int
    {
        $r = self::call('productOf(uint256)', [$tokenId]);
        return $r === null ? null : self::toInt($r);
    }

    public static function tokenURI(string $tokenId): ?string
    {
        $r = self::call('tokenURI(uint256)', [$tokenId]);
        return $r === null ? null : self::decodeString($r);
    }

    // --- encoding helpers ---

    private static function encodeWord(int|string $arg): string
    {
        if (is_string($arg) && str_starts_with($arg, '0x')) {
            // address -> right-aligned 32 bytes
            return str_pad(strtolower(EthSig::strip0x($arg)), 64, '0', STR_PAD_LEFT);
        }
        // uint256 as decimal string / int -> hex, right-aligned
        $hex = gmp_strval(gmp_init((string) $arg, 10), 16);
        return str_pad($hex, 64, '0', STR_PAD_LEFT);
    }

    private static function toAddress(string $hexWord): string
    {
        $h = EthSig::strip0x($hexWord);
        return '0x' . substr($h, -40);
    }

    private static function toInt(string $hexWord): int
    {
        $h = EthSig::strip0x($hexWord);
        if ($h === '' ) {
            return 0;
        }
        return (int) gmp_strval(gmp_init($h, 16), 10);
    }

    /** Decode ABI-encoded `string` return (offset, length, data). */
    private static function decodeString(string $hex): string
    {
        $h = EthSig::strip0x($hex);
        if (strlen($h) < 128) {
            return '';
        }
        $len = (int) gmp_strval(gmp_init(substr($h, 64, 64), 16), 10);
        $data = substr($h, 128, $len * 2);
        return $data === '' ? '' : (string) hex2bin($data);
    }

    private static function post(string $url, array $body): ?array
    {
        if ($url === '') {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => 10,
        ]);
        $out = curl_exec($ch);
        if ($out === false) {
            return null;
        }
        $decoded = json_decode((string) $out, true);
        return is_array($decoded) ? $decoded : null;
    }
}
