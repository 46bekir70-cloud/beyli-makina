<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/login-guard.php';

if (bm_admin_logged_in()) {
    header('Location: /teknikel/index.php');
    exit;
}

$error = '';
$kilit = bm_giris_kilit_suresi();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($kilit > 0) {
        // Kilitliyken şifre hiç kontrol edilmiyor: doğru şifre bile kabul
        // edilmez, böylece kilit atlatılamaz.
        $error = 'Çok fazla hatalı deneme. ' . ceil($kilit / 60) . ' dakika sonra tekrar deneyin.';
    } else {
        $password = $_POST['password'] ?? '';
        if (bm_admin_verify_password($password)) {
            $hataliSayi = bm_giris_basarili();
            // Oturum kimliği yenilenir: giriş öncesi sabitlenmiş bir oturum
            // kimliğiyle içeri sızılmasını engeller (session fixation).
            session_regenerate_id(true);
            $_SESSION['bm_admin_logged_in'] = true;
            $_SESSION['bm_admin_giris'] = time();
            $_SESSION['bm_admin_son_hareket'] = time();
            if ($hataliSayi > 0) $_SESSION['bm_admin_uyari_hatali'] = $hataliSayi;
            session_write_close();
            header('Location: /teknikel/index.php');
            exit;
        }
        $k = bm_giris_basarisiz();
        $kalan = max(0, BM_GIRIS_SINIR_1 - (int)$k['sayi']);
        $kilit = bm_giris_kilit_suresi();
        if ($kilit > 0) {
            $error = 'Çok fazla hatalı deneme. ' . ceil($kilit / 60) . ' dakika sonra tekrar deneyin.';
        } else {
            $error = 'Şifre yanlış.' . ($kalan <= 2 ? ' Kalan deneme: ' . $kalan : '');
        }
    }
}
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Yönetim Girişi | Beyli Makina</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" sizes="48x48" href="/images/favicon-48.png">

<link rel="stylesheet" href="/teknikel/assets/admin.css?v=<?php echo e(bm_asset_version('teknikel/assets/admin.css')); ?>">
</head>
<body>
<div class="admin-login-wrap">
  <div class="admin-login-box">
    <img src="/images/logo.png" alt="Beyli Makina">
    <h1>Site Yönetim Paneli</h1>
    <?php if ($error): ?><div class="flash err"><?php echo e($error); ?></div><?php endif; ?>
    <form method="post">
      <div class="field" style="text-align:left;">
        <label>Şifre</label>
        <input type="password" name="password" autofocus required
               <?php echo $kilit > 0 ? 'disabled' : ''; ?>>
      </div>
      <button type="submit" class="btn block" <?php echo $kilit > 0 ? 'disabled' : ''; ?>>
        <?php echo $kilit > 0 ? 'Geçici olarak kilitli' : 'Giriş Yap'; ?>
      </button>
    </form>
  </div>
</div>
</body>
</html>
