# Ürün Entegrasyonu — NFT'yi lisans olarak kullanma

İki seviye. Paylaşımı engellemek istiyorsan **Seviye 2** kullan.

## Seviye 1 — hızlı geçerlilik kontrolü

Token'ın gerçek + aktif olduğunu söyler. Çalıştıranın sahibi olduğunu KANITLAMAZ.

```bash
curl "https://panel.example.com/api/verify?token_id=123"
# → {"valid":true,"owner":"0x…","expiry":0,"perpetual":true,"product_uri":"…"}
```

## Seviye 2 — cüzdan sahiplik kanıtı (anti-share) ⭐

Kullanıcı cüzdanıyla bir nonce imzalar; panel imzayı `ownerOf` ile eşleştirir. Private key olmadan imza atılamaz → paylaşım engellenir. Devir olursa eski sahip otomatik düşer.

### Akış

```
1. Ürün → GET  /api/challenge?token_id=123        → { nonce, message }
2. Kullanıcı cüzdanı `message`'ı imzalar (MetaMask/WalletConnect, gas yok)
3. Ürün → POST /api/verify-owner {token_id, message, signature}
4. Panel → { valid, signer, owner, owns_it, active, expiry }
```

### Tarayıcı (ethers.js) örneği

```js
async function checkLicense(tokenId) {
  const provider = new ethers.BrowserProvider(window.ethereum);
  await provider.send('eth_requestAccounts', []);
  const signer = await provider.getSigner();

  // 1. challenge
  const { message } = await fetch(
    `https://panel.example.com/api/challenge?token_id=${tokenId}`
  ).then(r => r.json());

  // 2. wallet imzalar
  const signature = await signer.signMessage(message);

  // 3. panel doğrular
  const res = await fetch('https://panel.example.com/api/verify-owner', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token_id: tokenId, message, signature }),
  }).then(r => r.json());

  return res.valid;   // true → lisans aç
}
```

### Masaüstü / CLI

Yerel küçük sayfa aç → kullanıcı MetaMask ile `message` imzalar → imzayı `/api/verify-owner`'a yolla → dönen `valid` ile aktive et. Sonucu makineye cache'le, periyodik online recheck (transfer/expiry/revoke yakalar).

### Notlar

- `challenge` nonce'u tek kullanımlık, 5 dk geçerli.
- İleri: panel `valid` sonrası imzalı **JWT** üretip ürün offline doğrulasın (faz-3).

## Seviye 3 — PANEL-FREE (sıfır bağımlılık) ⭐⭐

Ürün kimliği artık **zincirde** (`productOf[tokenId]`). Ürün panele hiç bağlanmadan doğrular. Panel çökse de çalışır.

Kontrat tek çağrıda karar verir:

```solidity
verifyLicense(tokenId, productId, owner)
  → ownerOf==owner && !revoked && productOf==productId && !expired
```

Ürün `public/verify-lib/license-verify.js`'i gömer:

```js
import { verifyLicense } from './license-verify.js';

const ok = await verifyLicense({
  rpcUrl:    'https://eth-sepolia.g.alchemy.com/v2/KEY', // ürünün kendi RPC'si
  contract:  '0xLicenseContract',   // ürüne gömülü sabit
  productId: 7,                      // BU ürünün on-chain id'si
  tokenId:   '123',                  // kullanıcının lisansı
});
if (ok) unlockApp();
```

Yaptığı: cüzdanı bağlar → kullanıcı nonce imzalar (kontrol kanıtı, product kendi üretir) → `signer==ownerOf` → `verifyLicense(...)` zincirden okur. **Panel yok, `/api/*` yok.**

**Sonuç:** `productOf==benim && ownerOf==signer && isValid` → tek arıza noktası (panel) tamamen ortadan kalkar.
