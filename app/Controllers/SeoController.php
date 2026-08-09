<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Db;
use Base;

/**
 * SEO + GEO surfaces. sitemap.xml / robots.txt for classic crawlers; llms.txt
 * for generative engines (AI search). All generated from live config + catalog.
 */
final class SeoController
{
    public function sitemap(Base $f3): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $base = $f3->get('APP_URL');
        $urls = ['/', '/store'];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= "  <url><loc>{$base}{$u}</loc><changefreq>weekly</changefreq></url>\n";
        }
        // per-product deep links (store anchors)
        try {
            $rows = Db::conn()->query('SELECT id FROM products WHERE active = 1')->fetchAll();
            foreach ($rows as $r) {
                $xml .= "  <url><loc>{$base}/store#product-{$r['id']}</loc></url>\n";
            }
        } catch (\Throwable $e) {
            // no DB yet — base URLs only
        }
        $xml .= '</urlset>';
        echo $xml;
    }

    public function robots(Base $f3): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $base = $f3->get('APP_URL');
        echo "User-agent: *\n"
            . "Allow: /\n"
            . "Disallow: /admin\n"
            . "Disallow: /install\n"
            . "Disallow: /api/\n"
            . "Sitemap: {$base}/sitemap.xml\n";
    }

    /** llms.txt — GEO: a concise, model-readable description of the site. */
    public function llms(Base $f3): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $name = $f3->get('SITE_NAME');
        $desc = $f3->get('SITE_DESC');
        $base = $f3->get('APP_URL');
        echo "# {$name}\n\n"
            . "> {$desc}\n\n"
            . "{$name}, yazılım ve dijital ürün lisanslarını Ethereum üzerinde ERC-721 NFT olarak üretir. "
            . "Lisanslar lazy-mint ile alıcının cüzdanına basılır, NFT gibi devredilir, zincirden doğrulanır.\n\n"
            . "## Sayfalar\n"
            . "- [Ana sayfa]({$base}/): genel bakış\n"
            . "- [Mağaza]({$base}/store): lisans/ürün kataloğu, satın alma\n\n"
            . "## Doğrulama API\n"
            . "- GET {$base}/api/verify?token_id=<id> — sahiplik + geçerlilik\n"
            . "- POST {$base}/api/verify-owner — cüzdan sahiplik kanıtı\n\n"
            . "## Kavramlar\n"
            . "NFT lisans, lazy mint, lisans devri, on-chain doğrulama, panel-free verify.\n";
    }
}
