<?php
use App\Lib\View;
/** @var \Base $f3 */
/** @var ?string $user */
/** @var bool $isAdmin */
?>
<section class="card hero">
    <h1>Yazılım lisansların artık <span class="hl">sahibiyle</span><br>birlikte taşınır.</h1>
    <p>Ürünlerini sat; müşteri lisansını <strong>kendi hesabına</strong> alır. Lisans gerçekten onun olur — dilerse başkasına devreder, kimse geri alamaz. Yazılımın lisansı <strong>saniyede</strong> doğrular, sahtesi çalışmaz.</p>

    <p style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:var(--space-md)">
        <a class="btn btn-big" href="/store">Mağazaya git</a>
        <?php if (!$user): ?>
            <button id="btn-login" class="btn btn-ghost btn-big">MetaMask ile Giriş</button>
        <?php else: ?>
            <a class="btn btn-ghost btn-big" href="/licenses">Lisanslarım</a>
            <?php if ($isAdmin): ?><a class="btn btn-ghost btn-big" href="/admin">Yönetim</a><?php endif; ?>
        <?php endif; ?>
    </p>

    <div class="benefits">
        <div class="ben ben-lead card--coral"><b>Gerçek sahiplik</b><span>Lisans müşterinin blockchain hesabında. Kimse geri alamaz, kimse kopyalayamaz — kanıtı zincirde.</span></div>
        <div class="ben card--mint"><b>Devredilebilir</b><span>Sat, hediye et ya da devret; lisans sahibiyle taşınır.</span></div>
        <div class="ben card--sky"><b>Anında doğrulama</b><span>Yazılımın lisansı saniyede kontrol eder.</span></div>
    </div>
</section>

<section class="card">
    <h2>Nasıl çalışır</h2>
    <p class="muted">Üç adımda kur, sat, koru:</p>

    <div class="method">
        <span class="method-n">1</span>
        <div class="method-body">
            <h3>Ürününü ekle</h3>
            <p class="muted">Adını ve fiyatını belirle — ücretsiz, kartla veya kripto. Süreli ya da süresiz.</p>
        </div>
    </div>
    <div class="method">
        <span class="method-n">2</span>
        <div class="method-body">
            <h3>Müşteri satın alır</h3>
            <p class="muted">Mağazadan seçer, lisans <strong>doğrudan kendi hesabına</strong> gelir. Aracı yok, iptal edilemez.</p>
        </div>
    </div>
    <div class="method">
        <span class="method-n">3</span>
        <div class="method-body">
            <h3>Yazılımın doğrular</h3>
            <p class="muted">Ürünün, lisansın geçerli olup olmadığını otomatik kontrol eder. Devredilirse yeni sahibinde çalışır.</p>
        </div>
    </div>

    <p style="margin-top:var(--space-lg)"><a class="btn btn-big" href="/store">Ürünleri gör →</a></p>
</section>
