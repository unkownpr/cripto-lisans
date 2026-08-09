# Kripto Lisans Paneli

> NFT (ERC-721) tabanlı yazılım / dijital ürün lisans paneli. Lisansları zincirde
> üret, alıcının kendi cüzdanına **lazy-mint** ile bastır, NFT gibi **devret** ve
> herhangi bir yazılımdan **doğrula**.

**Stack:** Fat-Free Framework (F3) · PHP 8.3+ · Ethereum (ethers.js + MetaMask) · SQLite/MySQL

<sub>PHP hiçbir private key tutmaz. Tüm zincir *yazma* işlemleri (mint / transfer /
voucher imzası) tarayıcıda MetaMask ile yapılır; sunucu yalnızca imza *doğrular*
(SIWE) ve zinciri JSON-RPC ile *okur*.</sub>

---

## Özellikler

- **Cüzdan girişi (SIWE)** — Sign-In With Ethereum, şifre yok.
- **Lazy-mint voucher** — admin EIP-712 ile imzalar, müşteri kendi gas'ıyla `redeem()` eder.
- **Devredilebilir lisans** — `transferFrom` ile başka cüzdana geçer.
- **Doğrulama API** — `GET /api/verify?token_id=…` → zincirden `isValid`/`ownerOf`/`expiry`.
- **Cüzdan-bazlı erişim kontrolü** — `/api/access` ile ürün yazılımı "bu cüzdanın geçerli lisansı var mı?" sorar.
- **Kurulum sihirbazı** — `.env` elle yazmadan tarayıcıdan kurulum.
- **Tek-tık kontrat deploy** — panelden MetaMask ile kendi lisans kontratını deploy et.
- **Mağaza + admin paneli** — ürün yönetimi, ücretsiz/kripto satış, SMTP e-posta.
- **Çoklu dil entegrasyon istemcisi** — hazır `license.php` / `.py` / `.go` / `.cs` indirilebilir.

## Güvenlik

Sunucu tarafı sertleştirmeler:

- **CSRF** koruması (form + `fetch`) tüm state-değiştiren, cookie-authed uçlarda.
- **Session** cookie `HttpOnly` + `SameSite=Lax` + `Secure` (HTTPS'te oto) + strict session-id.
- **CSP header'ları** — `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`.
- **Kurulum kilidi** — ilk kurulum, sunucuda üretilen tek-kullanımlık `setup_token` ister ("ilk ziyaretçi admin olur" yarışını kapatır).
- **Rate-limit** public uçlarda + `challenges` tablosu otomatik temizlenir.
- **SVG upload kapalı** (stored-XSS), `.env` `0600` + newline-injection koruması.
- Parametreli SQL (injection yok), `DEBUG=0` default (stack trace sızmaz).

## Hızlı başlangıç (lokal)

```bash
git clone https://github.com/unkownpr/cripto-lisans.git
cd cripto-lisans
composer install
php -S localhost:8080 -t public
```

Tarayıcıda `http://localhost:8080` → sihirbaz açılır. Kurulum kodunu
`data/setup_token.txt`'den al, MetaMask'ı bağla, bitir.

## Kurulum sihirbazı

`.env` elle yazmana gerek yok. Sihirbaz sırayla:

1. **Kurulum kodu** — `data/setup_token.txt` içeriğini gir (güvenlik gate'i).
2. **Site** adı + URL (URL oto-algılanır).
3. **Admin cüzdanı** — MetaMask bağla, adres oto-dolar.
4. **Veritabanı** — SQLite (sıfır-config) veya MySQL.
5. **Zincir** — RPC + kontrat (boş bırakılabilir, sonra `/admin/network`'ten girilir).

Bitince `.env` (`0600`) yazılır, kurulum kilitlenir, `setup_token` silinir.

Sihirbaz sonrası panelden düzenlenebilir: SMTP → `/admin/mail`, zincir/kontrat →
`/admin/network` (tek-tık deploy), site/SEO → `/admin/site`.

## Gereksinimler

- **PHP 8.3+** — eklentiler: `gmp`, `curl`, `pdo`, `json`, `mbstring`
  > `gmp` **zorunlu** (imza doğrulama = login onunla çalışır). Shared hosting'de sık kapalı gelir; açtır.
- Composer (yerelde bağımlılık kurmak için)
- MetaMask + hedef ağda biraz gas (test için Sepolia + [faucet](https://sepoliafaucet.com))
- Bir RPC endpoint (Alchemy / Infura / public node)

## Paylaşımlı hosting'e kurulum (cPanel / Plesk — composer'sız)

Sunucuda `composer install` çalıştıramıyorsan:

1. **`vendor/` klasörünü dahil yükle.** Yerelde `composer install` çalıştır, sonra
   `vendor/` dahil tüm projeyi FTP/File Manager ile at. Sunucuda composer'a gerek kalmaz.
2. **PHP 8.3+ seç** — cPanel: *MultiPHP Manager* · Plesk: *Hosting Settings*.
3. **Eklentileri aç** — `gmp` + `curl` (cPanel *Select PHP Version → Extensions*).
4. **Docroot → `public/`** — domain'i projenin `public/` klasörüne yönlendir
   (subdomain/addon domain'de *Document Root* ayarı en temizi). `app/`, `data/`,
   `.env` asla web-kök altında olmamalı.
5. **Yazma izinleri** — `data/` ve `tmp/` → `755`; proje kökü `.env` için yazılabilir.
6. `/install` → `data/setup_token.txt`'yi File Manager'dan aç → kodu gir → kur.

## Doğrulama API

```bash
curl "https://panel.example.com/api/verify?token_id=123"
# → {"valid":true,"token_id":"123","owner":"0x…","expiry":0,"perpetual":true,"product_uri":"…"}
```

Cüzdan-bazlı erişim (ürün yazılımından):

```
GET  /api/access?product_id=1          → { nonce, message }   (cüzdan imzalar)
POST /api/access { product_id, message, signature }
                                       → { valid, address, token_id }
```

Hazır entegrasyon istemcileri: `/download/license.php` · `.py` · `.go` · `.cs`.

## Mimari

| Katman | Görev |
|---|---|
| **Kontrat** (`contracts/LicenseNFT.sol`) | ERC-721 + EIP-712 lazy mint + expiry + revoke |
| **Tarayıcı** (`public/assets/app.js`, ethers.js) | MetaMask: SIWE login, voucher imzası, redeem, transfer |
| **PHP panel** (F3) | SIWE ecrecover, admin yönetimi, zincir okuma, `/api/verify` |

## Sınırlar (bilinen)

- SIWE tam EIP-4361 parse etmez; nonce + adres + imza doğrular.
- "Lisanslarım" voucher tablosunu tarayıp `ownerOf` ile eşleştirir (event indexer
  yok — büyük ölçekte `Transfer` event indexer / `eth_getLogs` cron gerekir).
- Fiat ödeme (Stripe) akışı stub; kripto & ücretsiz satış çalışır.
- Cihaz bağlama / anti-korsan yok.

## Lisans

[MIT](LICENSE) © 2026 ssilistre
