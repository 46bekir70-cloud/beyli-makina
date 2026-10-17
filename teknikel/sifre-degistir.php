<?php
require_once __DIR__ . '/config.php';
// Güvenlik: yetki kontrolü EN BAŞTA. Eskiden bu kontrol layout-top.php
// içinde, yani POST işlendikten SONRA devreye giriyordu; oturumu olmayan biri
// form gönderip içeriği değiştirebilirdi (çıktı görmese bile kayıt olurdu).
bm_admin_require_login();
$bm_admin_active = 'sifre';
$bm_admin_title = 'Şifre Değiştir';

$message = '';
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
        $messageType = 'err';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new1 = $_POST['new_password'] ?? '';
        $new2 = $_POST['new_password2'] ?? '';
        if (!bm_admin_verify_password($current)) {
            $message = 'Mevcut şifre yanlış.';
            $messageType = 'err';
        } elseif (mb_strlen($new1) < 12) {
            $message = 'Yeni şifre en az 12 karakter olmalı. İpucu: üç alakasız kelimeyi birleştirmek (ör. tornaMavi7Kapak) hem güçlü hem akılda kalıcıdır.';
            $messageType = 'err';
        } elseif ($new1 !== $new2) {
            $message = 'Yeni şifreler birbiriyle eşleşmiyor.';
            $messageType = 'err';
        } else {
            $saved = bm_admin_set_password($new1);
            if ($saved) {
                $message = 'Şifre başarıyla değiştirildi.';
                $messageType = 'ok';
            } else {
                $message = 'HATA: Şifre dosyaya yazılamadı. "teknikel" klasöründeki credentials.json dosyasının yazma izni yok (sunucu izin sorunu). Lütfen teknikel/credentials.json dosyasının izinlerini 666 yapıp tekrar deneyin.';
                $messageType = 'err';
            }
        }
    }
}

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Şifre Değiştir</h1>
    <div class="sub">Admin paneline giriş şifreni buradan güncelleyebilirsin.</div>
  </div>
  <a href="/teknikel/logout.php" class="logout-link">Çıkış Yap</a>
</div>

<?php if ($message): ?><div class="flash <?php echo $messageType === 'ok' ? 'ok' : 'err'; ?>"><?php echo e($message); ?></div><?php endif; ?>

<div class="card" style="max-width:420px;">
  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
    <div class="field">
      <label>Mevcut Şifre</label>
      <input type="password" name="current_password" required>
    </div>
    <div class="field">
      <label>Yeni Şifre</label>
      <input type="password" name="new_password" required minlength="12" autocomplete="new-password">
      <div class="hint">En az <strong>12 karakter</strong>. Uzunluk karmaşıklıktan daha önemli:
        üç alakasız kelimeyi birleştirmek (ör. <code>tornaMavi7Kapak</code>) hem güçlü hem akılda kalıcıdır.</div>
    </div>
    <div class="field">
      <label>Yeni Şifre (Tekrar)</label>
      <input type="password" name="new_password2" required minlength="12" autocomplete="new-password">
    </div>
    <button type="submit" class="btn">Şifreyi Güncelle</button>
  </form>
</div>
<?php require __DIR__ . '/layout-bottom.php'; ?>
