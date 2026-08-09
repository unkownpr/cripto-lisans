<?php

declare(strict_types=1);

namespace App\Lib;

/**
 * Integration code templates. Shared by the docs page (inline + copy) and the
 * download endpoints so there is a single source of truth.
 */
final class Templates
{
    /** The drop-in PHP license guard, with the panel URL baked in. */
    public static function licensePhp(string $panelUrl): string
    {
        $tpl = <<<'PHP'
<?php
// license.php — script'inin en başında require et.
// Müşteri lisans nosunu license.key dosyasına yazar.
final class License
{
    private const PANEL      = '__PANEL__';
    private const PRODUCT_ID  = 1;                        // Ürünler sayfasındaki # numarası
    private const KEY_FILE    = __DIR__ . '/license.key';
    private const CACHE_FILE  = __DIR__ . '/.license_cache';
    private const CACHE_TTL   = 21600;                    // 6 saat

    public static function ok(): bool
    {
        $cache = @json_decode((string) @file_get_contents(self::CACHE_FILE), true);
        if (is_array($cache) && ($cache['exp'] ?? 0) > time()) {
            return (bool) $cache['valid'];
        }
        $key = trim((string) @file_get_contents(self::KEY_FILE));
        if ($key === '') return false;

        $ctx = stream_context_create(['http' => ['timeout' => 6]]);
        $raw = @file_get_contents(self::PANEL . '/api/verify?token_id=' . urlencode($key), false, $ctx);
        $d = json_decode((string) $raw, true);
        if (!is_array($d)) return is_array($cache) ? (bool) ($cache['valid'] ?? false) : false;

        $valid = ($d['valid'] ?? false) && (($d['product_id'] ?? null) == self::PRODUCT_ID);
        @file_put_contents(self::CACHE_FILE, json_encode(['valid' => $valid, 'exp' => time() + self::CACHE_TTL]));
        return $valid;
    }
}

// Kullanım — script'in en başında:
// require 'license.php';
// if (!License::ok()) { http_response_code(403); exit('Geçersiz lisans.'); }
PHP;

        return str_replace('__PANEL__', rtrim($panelUrl, '/'), $tpl);
    }

    /** Drop-in Python license check (stdlib only). */
    public static function licensePy(string $panelUrl): string
    {
        $tpl = <<<'PY'
# license.py — lisans no ile geçerlilik kontrolü (bağımlılık yok)
import json
import urllib.parse
import urllib.request

PANEL = "__PANEL__"
PRODUCT_ID = 1  # Ürünler sayfasındaki # numarası


def license_ok(token_id: str) -> bool:
    url = f"{PANEL}/api/verify?token_id={urllib.parse.quote(token_id)}"
    try:
        with urllib.request.urlopen(url, timeout=8) as r:
            d = json.load(r)
    except Exception:
        return False
    return bool(d.get("valid")) and int(d.get("product_id", -1)) == PRODUCT_ID


if __name__ == "__main__":
    # Kullanım: müşteri lisans nosunu ver
    if not license_ok("123"):
        raise SystemExit("Geçersiz lisans.")
    print("Lisans geçerli.")
PY;

        return str_replace('__PANEL__', rtrim($panelUrl, '/'), $tpl);
    }

    /** Drop-in Go license check (stdlib only). */
    public static function licenseGo(string $panelUrl): string
    {
        $tpl = <<<'GO'
// license.go — lisans no ile geçerlilik kontrolü (stdlib)
package license

import (
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"time"
)

const (
	panel     = "__PANEL__"
	productID = 1 // Ürünler sayfasındaki # numarası
)

// OK, token_id geçerli VE bu ürüne ait mi döner.
func OK(tokenID string) (bool, error) {
	u := fmt.Sprintf("%s/api/verify?token_id=%s", panel, url.QueryEscape(tokenID))
	c := &http.Client{Timeout: 8 * time.Second}
	resp, err := c.Get(u)
	if err != nil {
		return false, err
	}
	defer resp.Body.Close()

	var out struct {
		Valid     bool `json:"valid"`
		ProductID int  `json:"product_id"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return false, err
	}
	return out.Valid && out.ProductID == productID, nil
}
GO;

        return str_replace('__PANEL__', rtrim($panelUrl, '/'), $tpl);
    }

    /** Drop-in C# / .NET license check (HttpClient). */
    public static function licenseCs(string $panelUrl): string
    {
        $tpl = <<<'CS'
// License.cs — lisans no ile geçerlilik kontrolü (.NET, HttpClient)
using System;
using System.Net.Http;
using System.Text.Json;
using System.Threading.Tasks;

public static class License
{
    const string Panel = "__PANEL__";
    const int ProductId = 1; // Ürünler sayfasındaki # numarası
    static readonly HttpClient Http = new() { Timeout = TimeSpan.FromSeconds(8) };

    // token_id geçerli VE bu ürüne ait mi döner.
    public static async Task<bool> OkAsync(string tokenId)
    {
        var url = $"{Panel}/api/verify?token_id={Uri.EscapeDataString(tokenId)}";
        try
        {
            using var doc = JsonDocument.Parse(await Http.GetStringAsync(url));
            var root = doc.RootElement;
            var valid = root.TryGetProperty("valid", out var v) && v.GetBoolean();
            var pid = root.TryGetProperty("product_id", out var p) && p.TryGetInt32(out var n) ? n : -1;
            return valid && pid == ProductId;
        }
        catch
        {
            return false;
        }
    }
}
CS;

        return str_replace('__PANEL__', rtrim($panelUrl, '/'), $tpl);
    }
}
