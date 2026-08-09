<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Db;
use App\Lib\Rpc;
use App\Lib\View;
use Base;

/**
 * Customer-facing: the claim page (redeem a voucher into your wallet) and a
 * "my licenses" view resolved live from chain state.
 */
final class LicenseController
{
    public function claim(Base $f3): void
    {
        $id = (int) $f3->get('PARAMS.id');
        $voucher = Db::conn()
            ->query('SELECT v.*, p.name AS product_name, p.description AS product_desc
                     FROM vouchers v JOIN products p ON p.id = v.product_id
                     WHERE v.id = ' . $id)
            ->fetch();

        if (!$voucher || $voucher['status'] === 'draft' || !$voucher['signature']) {
            $f3->error(404, 'Voucher not issued');
            return;
        }

        // Already minted? reflect chain truth.
        $owner = Rpc::ownerOf($voucher['token_id']);
        if ($owner && $owner !== '0x0000000000000000000000000000000000000000') {
            $voucher['status'] = 'claimed';
        }

        View::render('claim', [
            'voucher' => $voucher,
            'onchainOwner' => $owner,
            'typedData' => [
                'domain' => [
                    'name' => $f3->get('EIP712_NAME'),
                    'version' => $f3->get('EIP712_VERSION'),
                    'chainId' => (int) $f3->get('CHAIN_ID'),
                    'verifyingContract' => $f3->get('CONTRACT'),
                ],
                'voucher' => [
                    'tokenId' => $voucher['token_id'],
                    'productId' => (string) $voucher['product_id'],
                    'recipient' => $voucher['recipient'],
                    'expiry' => (int) $voucher['expiry'],
                    'uri' => $voucher['uri'],
                    'price' => $voucher['price_wei'],
                ],
                'signature' => $voucher['signature'],
            ],
        ]);
    }

    public function mine(Base $f3): void
    {
        $user = Auth::user();
        $owned = [];

        if ($user) {
            // PoC: scan issued vouchers, check current on-chain owner == user.
            $rows = Db::conn()->query(
                "SELECT v.*, p.name AS product_name FROM vouchers v
                 JOIN products p ON p.id = v.product_id
                 WHERE v.status IN ('issued','claimed')"
            )->fetchAll();

            foreach ($rows as $r) {
                $owner = Rpc::ownerOf($r['token_id']);
                if ($owner && Auth::user() && strtolower($owner) === strtolower($user)) {
                    $r['on_expiry'] = Rpc::expiryOf($r['token_id']);
                    $r['valid'] = Rpc::isValid($r['token_id']);
                    $owned[] = $r;
                }
            }
        }

        View::render('licenses', ['owned' => $owned]);
    }
}
