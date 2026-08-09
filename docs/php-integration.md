# PHP script'ini bu panelle sat + lisansla

Senaryo: bir PHP script'in var. Onu bu panelle satacaksın ve script çalışırken lisansı doğrulayacak.

## 1) Panelde sat (satıcı)

1. **Yönetim → Ürünler → Yeni ürün**: adını yaz ("Benim Script"), ücret seç (ücretsiz / kart / kripto), kaydet.
2. Ürünün **#** numarasını not al (ör. `1`) — script'e bunu gömeceksin.
3. Müşteri **Mağaza**'dan satın alır → NFT lisansı kendi cüzdanına gelir.
4. Müşteri **Lisanslarım**'da **Lisans no**sunu görür (bu onun lisans anahtarı).

## 2) Script'e lisans kontrolü göm (en pratik: lisans no)

Müşteri lisans nosunu bir `license.key` dosyasına yazar; script her açılışta panele sorar. Aşağıdaki dosyayı script'ine ekle:

```php
<?php
// license.php — script'inin en başında require et.

final class License
{
    private const PANEL      = 'https://panel.senin-domainin.com'; // panel adresin
    private const PRODUCT_ID = 1;                                   // panelde ürün #
    private const KEY_FILE   = __DIR__ . '/license.key';           // müşterinin lisans nosu
    private const CACHE_FILE = __DIR__ . '/.license_cache';        // çevrimdışı önbellek
    private const CACHE_TTL  = 21600;                              // 6 saat

    public static function ok(): bool
    {
        // 1) taze önbellek varsa API'ye gitme (hız + panel çökse de çalışır)
        $cache = @json_decode((string) @file_get_contents(self::CACHE_FILE), true);
        if (is_array($cache) && ($cache['exp'] ?? 0) > time()) {
            return (bool) $cache['valid'];
        }

        $key = trim((string) @file_get_contents(self::KEY_FILE));
        if ($key === '') {
            return false;
        }

        // 2) panele sor
        $ctx = stream_context_create(['http' => ['timeout' => 6]]);
        $raw = @file_get_contents(
            self::PANEL . '/api/verify?token_id=' . urlencode($key),
            false,
            $ctx
        );
        $d = json_decode((string) $raw, true);

        // panel erişilemezse: son bilinen önbelleği kabul et (grace)
        if (!is_array($d)) {
            return is_array($cache) ? (bool) ($cache['valid'] ?? false) : false;
        }

        $valid = ($d['valid'] ?? false) && (($d['product_id'] ?? null) == self::PRODUCT_ID);

        @file_put_contents(self::CACHE_FILE, json_encode([
            'valid' => $valid,
            'exp'   => time() + self::CACHE_TTL,
        ]));

        return $valid;
    }
}

// Kullanım — script'in en başında:
if (!License::ok()) {
    http_response_code(403);
    exit('Geçersiz veya eksik lisans. Lisans numaranı license.key dosyasına yaz.');
}
```

Müşteriye teslim: script + kısa not — *"Lisans nonuzu `license.key` dosyasına yapıştırın."*

## Nasıl çalışır

- Script her açılışta (önbellek bitince) `GET /api/verify?token_id=<key>` çağırır.
- Panel zincirden okur: `valid` (aktif mi, süresi dolmuş mu, iptal mi) + `product_id`.
- **Süre dolarsa / iptal edilirse** → `valid:false` → script kapanır.
- **Devir olursa** → lisans yeni sahibin, eski sahibinde çalışmaya devam eder mi? `verify` sadece token'ın geçerliliğine bakar (sahip kim olursa olsun). Sahip-bağlama istersen aşağı bak.

## Zayıf nokta: lisans no paylaşılabilir

`token_id` herkese açık. Biri lisans nosunu başka müşteriye verirse iki yerde çalışır. Sağlamlaştırma:

**A. Cüzdan kanıtı** (`/api/access`) — müşteri cüzdanıyla imzalar, sahibi olduğu kanıtlanır. Ama PHP script'i sunucuda çalışır, cüzdan yok → tarayıcıda bir kez aktivasyon adımı gerekir.

**B. Domain/makine bağlama** — script ilk çalıştığında domainini panele kaydeder; panel o lisansı sadece o domain için geçerli sayar. Tek anahtar = tek site. *(Panelde bu endpoint henüz yok — istersen eklerim.)*

Düşük riskli ürün için **lisans no** yeterli. Çok satılan/pahalı script için **domain bağlama** öneririm.
