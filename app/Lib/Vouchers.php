<?php

declare(strict_types=1);

namespace App\Lib;

use Base;

/**
 * Shared voucher creation. Builds voucher fields for a product + recipient and,
 * when a server signer key is set, signs it (self-serve auto-issue). Otherwise
 * leaves it a draft for the admin to sign in-browser.
 */
final class Vouchers
{
    /**
     * @return array{voucher_id:int, token_id:string, status:string, signed:bool}
     */
    public static function create(array $product, string $recipient, string $status = 'draft'): array
    {
        $f3 = Base::instance();
        $db = Db::conn();

        $tokenId = (string) random_int(1, PHP_INT_MAX);
        $expiry = 0;
        if ($product['license_type'] === 'duration' && (int) $product['duration_days'] > 0) {
            $expiry = time() + ((int) $product['duration_days'] * 86400);
        }
        $priceWei = $product['price_type'] === 'crypto' ? (string) $product['price_wei'] : '0';
        $uri = 'data:application/json,' . rawurlencode(json_encode([
            'name' => $product['name'],
            'description' => $product['description'] ?? '',
        ]));

        $signature = null;
        $signed = false;
        $signerKey = (string) $f3->get('SIGNER_KEY');
        if ($signerKey !== '') {
            $signature = Eip712::signVoucher($signerKey, [
                'name' => $f3->get('EIP712_NAME'),
                'version' => $f3->get('EIP712_VERSION'),
                'chainId' => (string) $f3->get('CHAIN_ID'),
                'verifyingContract' => $f3->get('CONTRACT'),
            ], [
                'tokenId' => $tokenId,
                'productId' => (string) $product['id'],
                'recipient' => $recipient,
                'expiry' => (string) $expiry,
                'uri' => $uri,
                'price' => $priceWei,
            ]);
            $signed = true;
            $status = 'issued';
        }

        $stmt = $db->prepare(
            'INSERT INTO vouchers (product_id, token_id, recipient, expiry, uri, price_wei, signature, status, created_at)
             VALUES (:pid, :tid, :rec, :exp, :uri, :price, :sig, :status, :ts)'
        );
        $stmt->execute([
            'pid' => $product['id'],
            'tid' => $tokenId,
            'rec' => $recipient,
            'exp' => $expiry,
            'uri' => $uri,
            'price' => $priceWei,
            'sig' => $signature,
            'status' => $status,
            'ts' => time(),
        ]);

        return [
            'voucher_id' => (int) $db->lastInsertId(),
            'token_id' => $tokenId,
            'status' => $status,
            'signed' => $signed,
        ];
    }
}
