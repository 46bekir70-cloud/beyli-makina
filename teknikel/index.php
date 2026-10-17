<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/login-guard.php';
require_once __DIR__ . '/../inc/articles.php';
$content = bm_load_content();
$productsReady = bm_config_exists() && bm_db_ready();
$productCount = $productsReady
    ? (int)bm_db()->query('SELECT COUNT(*) FROM products')->fetchColumn()
    : 0;
// Makale tablosu ilk kez Makaleler ekranı açıldığında kuruluyor; panel
// açılışında yalnızca var mı diye bakıyoruz, kurulum denemiyoruz.
$articlesReady = $productsReady && bm_articles_ready();
$articleCount = $articlesReady
    ? (int)bm_db()->query('SELECT COUNT(*) FROM articles')->fetchColumn()
    : 0;
$bm_admin_active = 'dashboard';
$bm_admin_title = 'Panel';
require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Hoş geldin 👋</h1>
    <div class="sub">Beyli Makina sitesini buradan yönetebilirsin.</div>
  </div>
  <a href="/teknikel/logout.php" class="logout-link">Çıkış Yap</a>
</div>

<?php
  // Girişten sonra bir kez gösterilip silinir
  $hataliUyari = (int)($_SESSION['bm_admin_uyari_hatali'] ?? 0);
  unset($_SESSION['bm_admin_uyari_hatali']);
  $kilitli = bm_giris_kilitli_ipler();
?>
<?php if ($hataliUyari > 2 || $kilitli): ?>
  <div class="flash err">
    <strong>Güvenlik uyarısı.</strong>
    <?php if ($hataliUyari > 2): ?>
      Son girişinden bu yana <strong><?php echo $hataliUyari; ?></strong> başarısız şifre denemesi oldu.
    <?php endif; ?>
    <?php if ($kilitli): ?>
      Şu an <strong><?php echo count($kilitli); ?></strong> IP adresi geçici olarak kilitli.
    <?php endif; ?>
    Şifren kısa ya da tahmin edilebilir bir şeyse hemen değiştir:
    <a href="/teknikel/sifre-degistir.php" style="color:inherit;font-weight:700;text-decoration:underline;">Şifre Değiştir</a>
  </div>
<?php endif; ?>

<div class="dashboard-grid">
  <a class="dash-tile" href="/teknikel/sayfalar.php">
    <div class="ic">📄</div>
    <h3>Sayfalar</h3>
    <p>Sitedeki bütün sayfalar — Ana Sayfa, Ürünler, İletişim ve kendi eklediklerin</p>
    <p style="margin-top:8px;"><span class="badge on"><?php echo count($content['pages'] ?? []); ?> kendi sayfan</span></p>
  </a>
  <a class="dash-tile" href="/teknikel/anasayfa.php">
    <div class="ic">🏭</div>
    <h3>Ana Sayfa</h3>
    <p>Slayt gösterisi, başlık, hakkımızda özeti ve teklif kutusu</p>
  </a>
  <a class="dash-tile" href="/teknikel/urunler.php">
    <div class="ic">📦</div>
    <h3>Ürünler</h3>
    <p>Ürün ekle, düzenle, kategorilere ayır, CSV ile toplu aktar</p>
    <p style="margin-top:8px;">
      <?php if ($productsReady): ?>
        <span class="badge on"><?php echo number_format($productCount, 0, ',', '.'); ?> ürün</span>
      <?php else: ?>
        <span class="badge off">kurulum gerekli</span>
      <?php endif; ?>
    </p>
  </a>
  <a class="dash-tile" href="/teknikel/hizmetler.php">
    <div class="ic">🛠️</div>
    <h3>Hizmetler</h3>
    <p>Hizmet kartları, tanıtım sayfaları, makine parkuru ve öne çıkan sayılar</p>
    <p style="margin-top:8px;">
      <span class="badge on"><?php echo count($content['services'] ?? []); ?> hizmet</span>
    </p>
  </a>
  <a class="dash-tile" href="/teknikel/makaleler.php">
    <div class="ic">📝</div>
    <h3>Makaleler (Blog)</h3>
    <p>Yazı ekle, etiketle, kapak görseli koy — blog sayfasında listelenir</p>
    <p style="margin-top:8px;">
      <?php if ($articlesReady): ?>
        <span class="badge on"><?php echo number_format($articleCount, 0, ',', '.'); ?> yazı</span>
      <?php else: ?>
        <span class="badge off">ilk açılışta kurulur</span>
      <?php endif; ?>
    </p>
  </a>
  <a class="dash-tile" href="/teknikel/teklif-sayfasi.php">
    <div class="ic">✉️</div>
    <h3>İletişim / Teklif</h3>
    <p>Başlık görseli, iletişim butonları, sosyal medya hesapları, form seçenekleri</p>
  </a>
  <a class="dash-tile" href="/teknikel/medya.php">
    <div class="ic">🖼️</div>
    <h3>Görseller</h3>
    <p>Görsel yükle, ara, nerede kullanıldığını gör, kullanılmayanları temizle</p>
  </a>
  <a class="dash-tile" href="/teknikel/genel-ayarlar.php">
    <div class="ic">⚙️</div>
    <h3>Genel Ayarlar</h3>
    <p>Telefon, e-posta, adres, buton ikonları ve menü başlıkları</p>
  </a>
  <a class="dash-tile" href="/teknikel/yuzen-butonlar.php">
    <div class="ic">💬</div>
    <h3>Yüzen Butonlar</h3>
    <p>Sağ veya sol alt köşede duran WhatsApp ve hızlı arama butonları</p>
  </a>
</div>

<div class="card">
  <h2>Nasıl çalışır?</h2>
  <p style="color:var(--muted);font-size:14px;line-height:1.8;">
    <strong>Bir şeyi nerede bulacağını şöyle düşün:</strong> bir <em>sayfanın</em> görseli ya da
    yazısı, o sayfanın kendi ekranındadır — hepsinin listesi
    <a href="/teknikel/sayfalar.php" style="color:var(--blue);">Sayfalar</a> içinde.<br>
    Site genelinde tekrar eden şeyler ayrı yerlerde: iletişim bilgileri ve buton ikonları
    <strong>Genel Ayarlar</strong>'da, üst menü sırası ve grupları <strong>Sayfalar</strong>'da,
    telefondaki açılır menü ile sayfa altı (footer)
    <strong>Menü &amp; Alt Bilgi</strong> bölümünde.<br>
    Yaptığın her değişiklik kaydettiğin anda canlı siteye yansır — ayrıca bir "yayınla" adımı yok.
  </p>
</div>

<?php
  // /admin adresindeki sahte panele düşenler. Gerçek panel /teknikel'de;
  // oraya şifre deneyen herkes ya bot ya da işi olmayan biri demektir.
  $tuzak = [];
  $tuzakDosya = BM_ROOT . '/content/honeypot.json';
  if (is_file($tuzakDosya)) {
      $tuzak = array_slice(array_reverse(json_decode((string)file_get_contents($tuzakDosya), true) ?: []), 0, 8);
  }
?>
<?php if ($tuzak): ?>
<div class="card">
  <h2>🪤 Sahte Panele Düşenler <span class="count">(/admin)</span></h2>
  <p class="card-hint">
    Gerçek panel <strong>/teknikel</strong> adresinde. <strong>/admin</strong> adresinde
    hiçbir koşulda içeri almayan sahte bir giriş ekranı duruyor. Aşağıdakiler oraya
    şifre denemiş adresler — hiçbiri siteye giremedi. Denenen şifreler kaydedilmiyor,
    yalnızca kaç karakter olduğu tutuluyor.
  </p>
  <div class="table-wrap">
    <table class="admin-table" style="min-width:0;">
      <thead><tr><th>Zaman</th><th>Adres (IP)</th><th>Denenen kullanıcı</th><th>Şifre</th></tr></thead>
      <tbody>
        <?php foreach ($tuzak as $t): ?>
          <tr>
            <td class="mono"><?php echo e(date('d.m.Y H:i', strtotime($t['t'] ?? 'now'))); ?></td>
            <td class="mono"><?php echo e($t['ip'] ?? '—'); ?></td>
            <td><?php echo e(($t['kul'] ?? '') !== '' ? $t['kul'] : '—'); ?></td>
            <td class="mono"><?php echo (int)($t['uzn'] ?? 0); ?> karakter</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php $denemeler = bm_giris_son_denemeler(10); ?>
<?php if ($denemeler): ?>
<div class="card">
  <h2>🔐 Son Giriş Denemeleri</h2>
  <p class="card-hint">
    Panele kim girmeye çalıştı. 15 dakika içinde 5 hatalı denemede o adres
    15 dakika, 10 hatalı denemede 1 saat kilitlenir. Tanımadığın bir adresten
    çok sayıda "hatalı" görüyorsan şifreni uzun bir şeyle değiştir.
  </p>
  <div class="table-wrap">
    <table class="admin-table" style="min-width:0;">
      <thead><tr><th>Zaman</th><th>Adres (IP)</th><th>Sonuç</th></tr></thead>
      <tbody>
        <?php foreach ($denemeler as $d): ?>
          <tr>
            <td class="mono"><?php echo e(date('d.m.Y H:i', (int)($d['t'] ?? 0))); ?></td>
            <td class="mono"><?php echo e($d['ip'] ?? '—'); ?></td>
            <td>
              <?php if (($d['sonuc'] ?? '') === 'ok'): ?>
                <span class="badge on">giriş yapıldı</span>
              <?php else: ?>
                <span class="badge off" style="background:#fdecec;color:#a11616;">hatalı şifre</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/layout-bottom.php'; ?>
