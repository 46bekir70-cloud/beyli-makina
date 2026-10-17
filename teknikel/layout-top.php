<?php
// Doğrudan açılırsa sessizce 404 — bu dosya tek başına çalışmaz
if (!function_exists('bm_admin_require_login')) { http_response_code(404); exit; }
/**
 * Admin panel ortak üst şablon (sidebar + başlık).
 * Kullanmadan önce: require config.php, bm_admin_require_login(), $bm_admin_active, $bm_admin_title tanımlanmalı.
 *
 * İsteğe bağlı değişkenler:
 *   $bm_admin_save_form -> Bir form id'si verilirse kenar çubuğuna "Kaydet"
 *                          butonu eklenir; buton HTML'in form="" özelliğiyle
 *                          sayfadaki o formu gönderir.
 *   $bm_admin_preview   -> Önizleme adresi verilirse içerik iki kolona bölünür,
 *                          sağda canlı önizleme çerçevesi durur.
 */
bm_admin_require_login();
$bm_admin_active = $bm_admin_active ?? '';
$bm_admin_title = $bm_admin_title ?? 'Yönetim Paneli';
$bm_admin_save_form = $bm_admin_save_form ?? '';
$bm_admin_preview = $bm_admin_preview ?? '';
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($bm_admin_title); ?> | Yönetim Paneli</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" sizes="48x48" href="/images/favicon-48.png">

<link rel="stylesheet" href="/teknikel/assets/admin.css?v=<?php echo e(bm_asset_version('teknikel/assets/admin.css')); ?>">
<!-- Görsel yükleme isteklerinin oturum anahtarı; ikon yükleyici bunu kullanıyor -->
<script>window.BM_CSRF = <?php echo json_encode(bm_csrf_token()); ?>;</script>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="brand"><img src="/images/logo.png" alt="Beyli Makina"></div>
    <?php
      // Menü başlıklı gruplara ayrıldı: her şey tek bir uzun listedeyken
      // "hangisi neydi" sorusu çıkıyordu. Sıra, işin doğal akışına göre:
      // önce içerik, sonra sitenin görünümü, en sonda ayarlar.
    ?>
    <nav>
      <a href="/teknikel/index.php" class="<?php echo $bm_admin_active === 'dashboard' ? 'active' : ''; ?>">🏠 Panel</a>

      <div class="nav-group">İçerik</div>
      <a href="/teknikel/sayfalar.php" class="<?php echo $bm_admin_active === 'sayfalar' ? 'active' : ''; ?>">📄 Sayfalar</a>
      <a href="/teknikel/anasayfa.php" class="<?php echo $bm_admin_active === 'anasayfa' ? 'active' : ''; ?>">🏭 Ana Sayfa</a>
      <a href="/teknikel/urunler.php" class="<?php echo $bm_admin_active === 'urunler' ? 'active' : ''; ?>">📦 Ürünler</a>
      <a href="/teknikel/hizmetler.php" class="<?php echo $bm_admin_active === 'hizmetler' ? 'active' : ''; ?>">🛠️ Hizmetler</a>
      <a href="/teknikel/makaleler.php" class="<?php echo $bm_admin_active === 'makaleler' ? 'active' : ''; ?>">📝 Makaleler (Blog)</a>
      <a href="/teknikel/site-yazilari.php" class="<?php echo $bm_admin_active === 'yazilar' ? 'active' : ''; ?>">🔤 Site Yazıları</a>
      <a href="/teknikel/medya.php" class="<?php echo $bm_admin_active === 'medya' ? 'active' : ''; ?>">🖼️ Görseller</a>

      <div class="nav-group">Menü &amp; Alt Bilgi</div>
      <a href="/teknikel/yuzen-butonlar.php" class="<?php echo in_array($bm_admin_active, ['yuzenbuton', 'sabitbuton']) ? 'active' : ''; ?>">💬 Yüzen Butonlar (WhatsApp &amp; Arama)</a>
      <a href="/teknikel/menu-footer.php" class="<?php echo $bm_admin_active === 'menufooter' ? 'active' : ''; ?>">📱 Mobil Menü (hamburger)</a>
      <a href="/teknikel/menu-footer.php#footer" class="<?php echo $bm_admin_active === 'footer' ? 'active' : ''; ?>">🔻 Footer (sayfa altı)</a>

      <div class="nav-group">Ayarlar</div>
      <a href="/teknikel/genel-ayarlar.php" class="<?php echo $bm_admin_active === 'genel' ? 'active' : ''; ?>">⚙️ Genel Ayarlar</a>
      <a href="/teknikel/sifre-degistir.php" class="<?php echo $bm_admin_active === 'sifre' ? 'active' : ''; ?>">🔒 Şifre Değiştir</a>
      <a href="/teknikel/logout.php">🚪 Çıkış Yap</a>
    </nav>

    <div class="sidebar-foot">
      <?php if ($bm_admin_save_form): ?>
        <!-- Kaydet butonu kenar çubuğunda: uzun formlarda sayfanın en altına
             inmek gerekmiyor. HTML'in form="" özelliği sayesinde buton formun
             dışında olduğu hâlde onu gönderiyor. -->
        <button type="submit" form="<?php echo e($bm_admin_save_form); ?>" class="sidebar-save">
          💾 Kaydet
        </button>
      <?php else: ?>
        <!-- Kaydedilecek bir formu olmayan sayfalarda (listeler, medya, içe
             aktarma) buton yerinde duruyor ama pasif: hem kenar çubuğunun
             düzeni her sayfada aynı kalıyor, hem de "kaydetmeyi unuttum mu"
             sorusu kalmıyor. Sebebi butonun üstünde yazıyor. -->
        <button type="button" class="sidebar-save is-off" disabled
                title="Bu sayfada kaydedilecek bir form yok — yaptığın işlemler anında uygulanıyor.">
          💾 Kaydet
        </button>
        <span class="sidebar-save-note">Bu sayfada işlemler anında uygulanır</span>
      <?php endif; ?>
      <a href="/" target="_blank" class="view-site">Siteyi Görüntüle ↗</a>
    </div>
  </aside>
  <main class="admin-main">
    <?php if ($bm_admin_preview): ?><div class="editor-layout"><div class="editor-col"><?php endif; ?>
