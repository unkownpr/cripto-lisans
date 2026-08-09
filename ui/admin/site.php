<?php
use App\Lib\View;
/** @var \Base $f3 */
?>
<div class="admin-head"><h1>Site ayarları</h1></div>

<section class="card">
    <p class="admin-sub">Site kimliği + SEO. Başlık, açıklama, yazar, anahtar kelimeler ve favicon.</p>
    <form method="post" action="/admin/site" enctype="multipart/form-data">
        <div class="grid">
            <label>Site adı<br><input name="site_name" value="<?= View::e($f3->get('SITE_NAME')) ?>"></label>
            <label>Author (yazar / marka)<br><input name="site_author" value="<?= View::e($f3->get('SITE_AUTHOR')) ?>" placeholder="Firma / kişi"></label>
        </div>
        <p><label>Site adresi (URL) <span class="muted">— dökümanlar, sitemap, linkler bunu kullanır</span><br><input name="app_url" value="<?= View::e($f3->get('APP_URL')) ?>" placeholder="https://panel.senin-domainin.com"></label></p>
        <p><label>Slogan <span class="muted">— mağaza + footer'da görünür</span><br><input name="site_tagline" value="<?= View::e($f3->get('SITE_TAGLINE')) ?>"></label></p>
        <p><label>Açıklama <span class="muted">— arama motoru açıklaması (SEO)</span><br><input name="site_desc" value="<?= View::e($f3->get('SITE_DESC')) ?>"></label></p>
        <p><label>Anahtar kelimeler <span class="muted">— virgülle</span><br><input name="site_keywords" value="<?= View::e($f3->get('SITE_KEYWORDS')) ?>"></label></p>
        <p>
            <label>Favicon <span class="muted">— png / ico, ≤512KB</span><br><input type="file" name="favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/jpeg,image/gif"></label>
            <?php if ($f3->get('SITE_FAVICON')): ?>
                <span class="muted">mevcut: <img src="<?= View::e($f3->get('SITE_FAVICON')) ?>" alt="favicon" style="height:18px;vertical-align:middle"></span>
            <?php endif; ?>
        </p>
        <button class="btn">Kaydet</button>
    </form>
</section>
