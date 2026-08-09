<?php
use App\Lib\View;
/** @var \Base $f3 */
/** @var string $autoUrl */
/** @var int $chainId */
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kurulum · Kripto Lisans Paneli</title>
    <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Inter+Tight:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="/assets/tokens.css">
    <link rel="stylesheet" href="/assets/style.css">
    <meta name="csrf-token" content="<?= View::e($f3->get('CSRF_TOKEN')) ?>">
    <script src="https://cdn.jsdelivr.net/npm/ethers@6.13.0/dist/ethers.umd.min.js" defer></script>
    <script src="/assets/csrf.js" defer></script>
    <script src="/assets/install.js" defer></script>
</head>
<body>
<main class="wrap" style="max-width:640px">
    <section class="card">
        <span class="eyebrow">⚙ Kurulum sihirbazı</span>
        <h1>Paneli kur</h1>
        <p class="muted">Site URL otomatik algılandı. Admin cüzdanı MetaMask'tan otomatik alınır. Birkaç alan doldur, gerisi otomatik.</p>

        <form id="install-form" onsubmit="return false">
            <div class="card card--sky" style="box-shadow:none;margin:0 0 16px">
                <h3 style="margin-top:0">0 · Kurulum kodu</h3>
                <p class="muted" style="margin:0 0 8px">Güvenlik için sunucudaki <code>data/setup_token.txt</code> dosyasını aç ve içindeki kodu buraya yapıştır. Bu, paneli senden başkasının kurmasını engeller.</p>
                <label>Kurulum kodu<br><input name="setup_token" placeholder="data/setup_token.txt içeriği" autocomplete="off" required></label>
            </div>

            <h3>1 · Site</h3>
            <p><label>Site adı<br><input name="site_name" value="Kripto Lisans Paneli" required></label></p>
            <p><label>Site URL (otomatik)<br><input name="app_url" value="<?= View::e($autoUrl) ?>"></label></p>

            <h3>2 · Admin cüzdanı</h3>
            <p class="muted">MetaMask ile bağlan — adres otomatik alınır, admin olur.</p>
            <p>
                <button id="btn-connect" class="btn">MetaMask'ı bağla</button>
                <span id="admin-addr" class="addr" style="display:none"></span>
            </p>
            <input type="hidden" name="admin_address" id="admin_address">

            <h3>3 · Veritabanı</h3>
            <p><label>Sürücü<br>
                <select name="db_driver" id="db_driver">
                    <option value="mysql">MySQL / MariaDB</option>
                    <option value="sqlite">SQLite (sıfır-config)</option>
                </select>
            </label></p>
            <div id="mysql-fields">
                <div class="grid">
                    <label>Host<br><input name="db_host" value="127.0.0.1"></label>
                    <label>Port<br><input name="db_port" value="3306"></label>
                    <label>DB adı<br><input name="db_name" value="cripto_lisans"></label>
                    <label>Kullanıcı<br><input name="db_user" value="root"></label>
                    <label>Parola<br><input name="db_pass" type="password" value=""></label>
                </div>
                <p><button id="btn-dbtest" class="btn btn-ghost">Bağlantıyı test et</button> <span id="dbtest-status" class="mono"></span></p>
            </div>

            <h3>4 · Zincir (şimdilik boş bırakabilirsin)</h3>
            <div class="card card--sky" style="box-shadow:none;margin:0 0 12px">
                <p style="margin:0 0 8px"><strong>Bu adım ne işe yarar?</strong> Gerçek NFT lisansı <em>basmak</em> ve <em>doğrulamak</em> için panelin Ethereum'a bağlanması gerekir. İki bilgi ister:</p>
                <ul style="margin:0;padding-left:18px">
                    <li><strong>RPC URL</strong> — Ethereum'a "bağlantı hattı". Kendi sunucun olmadan ücretsiz alınır.</li>
                    <li><strong>Kontrat adresi</strong> — senin lisans sözleşmenin zincirdeki adresi (bir kez kurulur).</li>
                </ul>
                <p style="margin:8px 0 0"><strong>Boş bırakırsan:</strong> panel çalışır — giriş, ürün ekleme, mağaza. Sadece <em>gerçek NFT basma</em> kapalı olur. Sonra buraya eklersin.</p>
            </div>

            <div class="grid">
                <label>Chain ID<br><input name="chain_id" value="<?= (int) $chainId ?>"></label>
                <label>&nbsp;<br><span class="muted mono">11155111 = Sepolia test ağı</span></label>
            </div>
            <p><label>RPC URL<br><input name="rpc_url" placeholder="boş bırakılabilir — örn https://eth-sepolia.g.alchemy.com/v2/KEY"></label></p>

            <details style="margin:0 0 12px">
                <summary class="muted" style="cursor:pointer"><strong>RPC URL nasıl alınır? (adım adım)</strong></summary>
                <ol style="padding-left:18px">
                    <li><strong>alchemy.com</strong>'a gir, ücretsiz kaydol.</li>
                    <li>"Create App" → Chain: <strong>Ethereum</strong>, Network: <strong>Sepolia</strong>.</li>
                    <li>Uygulamada "API Key" → <strong>HTTPS</strong> adresini kopyala.</li>
                    <li>Buradaki RPC URL kutusuna yapıştır. (Alchemy yerine Infura da olur.)</li>
                </ol>
            </details>

            <p><label>Kontrat adresi<br><input name="contract" placeholder="boş bırakılabilir — 0x…"></label></p>

            <details style="margin:0">
                <summary class="muted" style="cursor:pointer"><strong>Kontrat adresi nasıl alınır? (adım adım)</strong></summary>
                <ol style="padding-left:18px">
                    <li>Sözleşme dosyası hazır: <code>contracts/LicenseNFT.sol</code>.</li>
                    <li><strong>remix.ethereum.org</strong>'u aç → yeni dosya oluştur → dosyanın içeriğini yapıştır.</li>
                    <li>"Solidity Compiler" → sürüm <strong>0.8.24</strong> → Compile.</li>
                    <li>"Deploy & Run" → Environment: <strong>Injected Provider - MetaMask</strong> (Sepolia bağlı).</li>
                    <li><code>_ADMIN</code> kutusuna <strong>kendi cüzdan adresini</strong> yaz → <strong>Deploy</strong> → MetaMask onayla.</li>
                    <li>Çıkan kontrat adresini kopyala, yukarıdaki kutuya yapıştır.</li>
                </ol>
                <p class="muted" style="margin:0"><strong>Zor geldi mi?</strong> Bu adımı boş geç — kurulum sağlayıcın senin için deploy edip adresi verebilir.</p>
            </details>

            <hr style="border:none;border-top:1px solid var(--color-line);margin:24px 0">
            <button id="btn-install" class="btn btn-big">Kurulumu tamamla</button>
            <p id="install-status" class="mono"></p>
        </form>
    </section>
    <p class="foot" style="border:none">Kurulum bitince bu sayfa kilitlenir. Yeniden kurmak için <code>data/installed.lock</code> sil.</p>
</main>
</body>
</html>
