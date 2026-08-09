<?php
use App\Lib\View;
use App\Lib\Mailer;
/** @var \Base $f3 */
$secure = (string) $f3->get('SMTP_SECURE');
$flash = $_SESSION['mail_flash'] ?? null;
unset($_SESSION['mail_flash']);
?>
<div class="admin-head"><h1>E-posta (SMTP)</h1></div>

<?php if ($flash): ?>
    <section class="card" style="border-left:4px solid <?= $flash[0] ? 'var(--color-mint,#22c55e)' : 'var(--color-coral,#ef4444)' ?>">
        <p style="margin:0"><?= $flash[0] ? '✅ ' : '⚠️ ' ?><?= View::e((string) $flash[1]) ?></p>
    </section>
<?php endif; ?>

<section class="card">
    <p class="admin-sub">Giden e-posta ayarları. PHPMailer ile SMTP üzerinden gönderilir. Şifre boş bırakılırsa mevcut şifre korunur.</p>
    <form method="post" action="/admin/mail">
        <div class="grid">
            <label>SMTP sunucu (host)<br><input name="smtp_host" value="<?= View::e($f3->get('SMTP_HOST')) ?>" placeholder="smtp.gmail.com"></label>
            <label>Port<br><input name="smtp_port" type="number" value="<?= (int) $f3->get('SMTP_PORT') ?>" placeholder="587"></label>
            <label>Kullanıcı adı<br><input name="smtp_user" value="<?= View::e($f3->get('SMTP_USER')) ?>" placeholder="kullanici@domain.com" autocomplete="off"></label>
            <label>Şifre <span class="muted">— boş = değiştirme</span><br><input name="smtp_pass" type="password" value="" placeholder="••••••••" autocomplete="new-password"></label>
            <label>Şifreleme<br>
                <select name="smtp_secure">
                    <option value="tls" <?= $secure === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
                    <option value="ssl" <?= $secure === 'ssl' ? 'selected' : '' ?>>SSL/TLS (465)</option>
                    <option value="none" <?= $secure === 'none' ? 'selected' : '' ?>>Yok (şifresiz)</option>
                </select>
            </label>
            <label>Gönderen adres (from)<br><input name="smtp_from" value="<?= View::e($f3->get('SMTP_FROM')) ?>" placeholder="noreply@domain.com"></label>
            <label>Gönderen adı<br><input name="smtp_from_name" value="<?= View::e($f3->get('SMTP_FROM_NAME')) ?>" placeholder="<?= View::e($f3->get('SITE_NAME')) ?>"></label>
        </div>
        <button class="btn">Kaydet</button>
    </form>
</section>

<section class="card">
    <h2>Test e-postası</h2>
    <p class="muted">Ayarları kaydettikten sonra bir adrese test gönder.
        <?php if (!Mailer::configured($f3)): ?><strong>Önce host ve gönderen adresini doldur.</strong><?php endif; ?>
    </p>
    <form method="post" action="/admin/mail/test">
        <span style="display:flex;gap:8px;max-width:420px">
            <input name="to" type="email" placeholder="alici@domain.com" required style="flex:1">
            <button class="btn"<?= Mailer::configured($f3) ? '' : ' disabled' ?>>Test gönder</button>
        </span>
    </form>
</section>
