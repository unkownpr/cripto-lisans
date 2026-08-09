<?php
use App\Lib\View;
/** @var \Base $f3 */
$url = rtrim((string) $f3->get('APP_URL'), '/');

$phpKey = <<<'PHP'
<?php
require 'license.php';
if (!License::ok()) { http_response_code(403); exit('Geçersiz lisans.'); }
PHP;

$phpClass = \App\Lib\Templates::licensePhp($url);

$jsCode = str_replace('__PANEL__', $url, <<<'JS'
// Web / Electron uygulamanda — cüzdanla sahiplik kanıtı (anti-share)
async function lisansKontrol() {
  const PANEL = '__PANEL__';
  const PRODUCT_ID = 1;
  const provider = new ethers.BrowserProvider(window.ethereum);
  await provider.send('eth_requestAccounts', []);
  const signer = await provider.getSigner();

  const { message } = await fetch(`${PANEL}/api/access?product_id=${PRODUCT_ID}`).then(r => r.json());
  const signature = await signer.signMessage(message);
  const res = await fetch(`${PANEL}/api/access`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ product_id: PRODUCT_ID, message, signature }),
  }).then(r => r.json());
  return res.valid; // true → aç
}
JS);

$contract = $f3->get('CONTRACT') ?: '0xKONTRAT_ADRESIN';
$jsFree = str_replace('__CONTRACT__', (string) $contract, <<<'JS'
// Panelsiz — doğrudan zincirden doğrula. Panel çökse de çalışır.
// public/verify-lib/license-verify.js dosyasını ürününe kopyala + ethers.js ekle.
import { verifyLicense } from './license-verify.js';

const ok = await verifyLicense({
  contract:  '__CONTRACT__',   // Ağ & Kontrat sayfasındaki kontrat adresi
  productId: 1,                // Ürünler sayfasındaki #
  tokenId:   '123',            // müşterinin lisans nosu
  rpcUrl:    'https://ethereum-sepolia-rpc.publicnode.com', // opsiyonel
});
if (ok) unlockApp();
JS);

$pyCode = \App\Lib\Templates::licensePy($url);
$goCode = \App\Lib\Templates::licenseGo($url);
$csCode = \App\Lib\Templates::licenseCs($url);

$codeBlock = static function (string $code): string {
    return '<div class="code-wrap"><button class="copy-btn" type="button">Kopyala</button><pre>'
        . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</pre></div>';
};
?>
<div class="admin-head"><h1>Entegrasyon</h1></div>

<section class="card">
    <p class="admin-sub">Yazılımına lisans kontrolü ekle. Panel adresin: <code class="mono"><?= View::e($url) ?></code> · Ürün numarası (<code>PRODUCT_ID</code>) için <a href="/admin/products">Ürünler</a> sayfasındaki <strong>#</strong> sütununa bak.</p>

    <h2>Doğrulama yöntemleri</h2>
    <div class="method">
        <span class="method-n">1</span>
        <div class="method-body">
            <h3>Lisans no ile <span class="badge badge--muted">en basit</span></h3>
            <p class="muted">Müşteri lisans nosunu girer, yazılımın panele sorar. PHP script'leri için ideal.</p>
            <code class="endpoint">GET <?= View::e($url) ?>/api/verify?token_id=123</code>
        </div>
    </div>
    <div class="method">
        <span class="method-n">2</span>
        <div class="method-body">
            <h3>Cüzdanla <span class="badge badge--warn">anti-share</span></h3>
            <p class="muted">Müşteri cüzdanıyla imzalar; sahibi olduğu kanıtlanır. Web/masaüstü için.</p>
            <code class="endpoint">GET /api/access?product_id=1 → imzala → POST /api/access</code>
        </div>
    </div>
    <div class="method">
        <span class="method-n">3</span>
        <div class="method-body">
            <h3>Panel-free <span class="badge badge--ok">sıfır bağımlılık</span></h3>
            <p class="muted">Doğrudan zincirden, panelsiz. <a href="<?= View::e($url) ?>/verify-lib/license-verify.js" download>kütüphaneyi indir</a></p>
        </div>
    </div>
</section>

<section class="card">
    <h2>İndirilebilir dosyalar</h2>
    <p class="admin-sub">Entegrasyon dosyalarını buradan indir — panel kaynağına erişmeye gerek yok.</p>
    <table>
        <tr><th>Dosya</th><th>Ne için</th><th></th></tr>
        <tr><td class="mono">license.php</td><td>PHP script lisans kontrolü (panel URL gömülü)</td><td><a class="btn btn-ghost" style="padding:6px 12px" href="/download/license.php" download>İndir</a></td></tr>
        <tr><td class="mono">license.py</td><td>Python istemci (panel URL gömülü)</td><td><a class="btn btn-ghost" style="padding:6px 12px" href="/download/license.py" download>İndir</a></td></tr>
        <tr><td class="mono">license.go</td><td>Go istemci (panel URL gömülü)</td><td><a class="btn btn-ghost" style="padding:6px 12px" href="/download/license.go" download>İndir</a></td></tr>
        <tr><td class="mono">License.cs</td><td>C# / .NET istemci (panel URL gömülü)</td><td><a class="btn btn-ghost" style="padding:6px 12px" href="/download/license.cs" download>İndir</a></td></tr>
        <tr><td class="mono">license-verify.js</td><td>Panel-free (JS) doğrulama kütüphanesi</td><td><a class="btn btn-ghost" style="padding:6px 12px" href="/verify-lib/license-verify.js" download>İndir</a></td></tr>
        <tr><td class="mono">LicenseNFT.sol</td><td>Akıllı sözleşme kaynağı (denetim / doğrulama)</td><td><a class="btn btn-ghost" style="padding:6px 12px" href="/download/contract" download>İndir</a></td></tr>
        <tr><td class="mono">LicenseNFT.json</td><td>ABI + bytecode</td><td><a class="btn btn-ghost" style="padding:6px 12px" href="/assets/LicenseNFT.json" download>İndir</a></td></tr>
    </table>
</section>

<section class="card">
    <h2>PHP script</h2>
    <p class="admin-sub">Müşteri lisans nosunu <code>license.key</code> dosyasına yazar. Script'inin başında:</p>
    <?= $codeBlock($phpKey) ?>
    <p class="admin-sub" style="margin-top:var(--space-md)"><code>license.php</code> — önbellek + grace (panel çökse de çalışır):</p>
    <?= $codeBlock($phpClass) ?>
</section>

<section class="card">
    <h2>JavaScript (web / Electron)</h2>
    <p class="admin-sub">Cüzdanla sahiplik kanıtı — lisans no gerekmez, paylaşım engellenir:</p>
    <?= $codeBlock($jsCode) ?>
</section>

<section class="card">
    <h2>Diğer diller <span class="badge badge--muted">Go · Python · C#</span></h2>
    <p class="admin-sub">Sunucu tarafı / CLI uygulamaları için — <code class="mono">GET /api/verify?token_id=…</code> çağırıp <code>valid</code>'i kontrol et. Panel URL'i gömülü, ek bağımlılık yok. Cüzdanla anti-share isteyen web/masaüstü için yukarıdaki JavaScript yöntemini kullan.</p>

    <h3>Python <a class="mono" style="font-size:0.8rem;font-weight:400" href="/download/license.py" download>↓ license.py</a></h3>
    <?= $codeBlock($pyCode) ?>

    <h3>Go <a class="mono" style="font-size:0.8rem;font-weight:400" href="/download/license.go" download>↓ license.go</a></h3>
    <?= $codeBlock($goCode) ?>

    <h3>C# (.NET) <a class="mono" style="font-size:0.8rem;font-weight:400" href="/download/license.cs" download>↓ License.cs</a></h3>
    <?= $codeBlock($csCode) ?>
</section>

<section class="card">
    <h2>Panel-free (panelsiz)</h2>
    <p class="admin-sub">Ürün doğrudan zincirden doğrular — <strong>panele hiç bağlanmaz</strong>, panel çökse de çalışır. Ürün kimliği zincirde (<code class="mono">productOf</code>), sözleşme tek çağrıda karar verir:</p>
    <?= $codeBlock("verifyLicense(tokenId, productId, owner)\n  → ownerOf==owner && !revoked && productOf==productId && !expired") ?>
    <p class="admin-sub" style="margin-top:var(--space-md)">
        <strong>1)</strong> Kütüphaneyi indir → <a href="<?= View::e($url) ?>/verify-lib/license-verify.js" download><code class="mono">license-verify.js</code> indir</a> (veya adresi kopyala: <code class="mono"><?= View::e($url) ?>/verify-lib/license-verify.js</code>).
        <strong>2)</strong> Ürününe koy + ethers.js ekle.
        <strong>3)</strong> çağır:
    </p>
    <?= $codeBlock($jsFree) ?>
    <p class="admin-sub" style="margin-top:var(--space-md)">Nasıl çalışır: cüzdanı bağlar → kullanıcı ürünün ürettiği nonce'u imzalar (sahiplik kanıtı) → <code class="mono">signer==ownerOf</code> → sözleşmeden <code class="mono">verifyLicense</code> okur. Merkezi sunucu yok, tek arıza noktası yok.</p>
</section>

<section class="card">
    <h2>API yanıtları</h2>
    <?= $codeBlock("GET /api/verify?token_id=123\n→ { valid, token_id, product_id, owner, expiry, perpetual }\n\nPOST /api/access { product_id, message, signature }\n→ { valid, address, token_id }") ?>
</section>
