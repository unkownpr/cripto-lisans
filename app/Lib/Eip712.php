<?php

declare(strict_types=1);

namespace App\Lib;

use Elliptic\EC;
use kornrunner\Keccak;

/**
 * Server-side EIP-712 signing for lazy-mint vouchers. Optional: only used when
 * SIGNER_KEY is configured (enables self-serve store auto-issue). The key
 * authorizes minting only — compromise means free mints, never fund theft.
 *
 * Domain + Voucher type MUST byte-match LicenseNFT.sol.
 */
final class Eip712
{
    private const DOMAIN_TYPE =
        'EIP712Domain(string name,string version,uint256 chainId,address verifyingContract)';
    private const VOUCHER_TYPE =
        'Voucher(uint256 tokenId,uint256 productId,address recipient,uint64 expiry,string uri,uint256 price)';

    /**
     * Sign a voucher. $domain = [name, version, chainId, verifyingContract].
     * $v = [tokenId, productId, recipient, expiry, uri, price] (strings/ints).
     * Returns 0x r||s||v (65 bytes).
     */
    public static function signVoucher(string $privHex, array $domain, array $v): string
    {
        $digest = self::digest($domain, $v);

        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate(EthSig::strip0x($privHex));
        $sig = $key->sign($digest, ['canonical' => true]);

        $r = str_pad($sig->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($sig->s->toString(16), 64, '0', STR_PAD_LEFT);
        $vv = dechex($sig->recoveryParam + 27);

        return '0x' . $r . $s . $vv;
    }

    /** The EIP-712 digest (hex, no 0x) that gets signed / recovered on-chain. */
    public static function digest(array $domain, array $v): string
    {
        $domainSep = self::domainSeparator($domain);
        $structHash = self::hashStruct($v);
        return Keccak::hash(hex2bin('1901' . $domainSep . $structHash), 256);
    }

    /** Derive the address for a private key (to set as contract signer). */
    public static function addressFor(string $privHex): string
    {
        $ec = new EC('secp256k1');
        $pub = $ec->keyFromPrivate(EthSig::strip0x($privHex))->getPublic(false, 'hex');
        return '0x' . substr(Keccak::hash(hex2bin(substr($pub, 2)), 256), 24);
    }

    private static function domainSeparator(array $d): string
    {
        return Keccak::hash(hex2bin(
            self::keccakHex(self::DOMAIN_TYPE)
            . self::keccakHex((string) $d['name'])
            . self::keccakHex((string) $d['version'])
            . self::uintWord((string) $d['chainId'])
            . self::addrWord((string) $d['verifyingContract'])
        ), 256);
    }

    private static function hashStruct(array $v): string
    {
        return Keccak::hash(hex2bin(
            self::keccakHex(self::VOUCHER_TYPE)
            . self::uintWord((string) $v['tokenId'])
            . self::uintWord((string) $v['productId'])
            . self::addrWord((string) $v['recipient'])
            . self::uintWord((string) $v['expiry'])
            . self::keccakHex((string) $v['uri'])
            . self::uintWord((string) $v['price'])
        ), 256);
    }

    private static function keccakHex(string $s): string
    {
        return Keccak::hash($s, 256);
    }

    private static function uintWord(string $dec): string
    {
        return str_pad(gmp_strval(gmp_init($dec, 10), 16), 64, '0', STR_PAD_LEFT);
    }

    private static function addrWord(string $addr): string
    {
        return str_pad(strtolower(EthSig::strip0x($addr)), 64, '0', STR_PAD_LEFT);
    }
}
