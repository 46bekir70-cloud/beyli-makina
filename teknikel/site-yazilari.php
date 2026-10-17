<?php
/**
 * Site Yazıları — şablonlardaki sabit metinlerin tek yerden yönetimi.
 *
 * Amaç: sitede panelden değiştirilemeyen yazı kalmasın. Metinler kodda
 * varsayılan olarak duruyor; burada bir değer girilirse o kullanılır, boş
 * bırakılırsa varsayılan geçerli olur. Böylece ileride eklenen yeni yazılar
 * da otomatik olarak bu listeye düşer (bm_label_catalog).
 *
 * Yalnızca yazılar: sayfa görselleri her sayfanın kendi ekranında duruyor.
 */
require_once __DIR__ . '/config.php';

bm_admin_require_login();

$content = bm_load_content();
$bm_admin_active = 'yazilar';  // Kenar çubuğunda kendi satırı var
$bm_admin_title = 'Site Yazıları';
$bm_admin_save_form = 'yazilarForm';
$message = '';

$catalog = bm_label_catalog();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        $labels = [];
        foreach ($catalog as $key => [$grup, $aciklama, $varsayilan]) {
            // Form alan adlarında nokta yerine alt çizgi kullanıyoruz
            $alan = 'lbl_' . str_replace('.', '__', $key);
            $deger = trim((string)($_POST[$alan] ?? ''));
            // Varsayılanla aynıysa kaydetmeye gerek yok — dosya şişmesin ve
            // ileride varsayılanı değiştirirsem otomatik güncellensin.
            if ($deger !== '' && $deger !== $varsayilan) {
                $labels[$key] = $deger;
            }
        }
        $content['labels'] = $labels;

        // NOT: Ürünler sayfasının başlık görseli artık burada değil,
        // admin/urunler-sayfasi.php içinde — aranacağı yerde dursun diye.
        // Buradan yazmaya devam etseydik bu form her kaydedildiğinde alan
        // POST'ta olmadığı için görsel silinirdi.

        bm_save_content($content);
        $message = 'ok|Site yazıları kaydedildi. (' . count($labels) . ' yazı özelleştirildi)';
        $content = bm_load_content();
    }
}

$labels = is_array($content['labels'] ?? null) ? $content['labels'] : [];

// Grupları koruyarak sırala
$gruplu = [];
foreach ($catalog as $key => [$grup, $aciklama, $varsayilan]) {
    $gruplu[$grup][$key] = [$aciklama, $varsayilan];
}

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Site Yazıları</h1>
    <div class="sub">Sitedeki sabit metinler — başlıklar, buton yazıları, etiketler</div>
  </div>
  <a href="/teknikel/index.php" class="logout-link">← Panele Dön</a>
</div>

<?php if ($message): [$type, $text] = explode('|', $message, 2); ?>
  <div class="flash <?php echo $type === 'ok' ? 'ok' : 'err'; ?>"><?php echo e($text); ?></div>
<?php endif; ?>

<div class="card">
  <h2>Aradığın yazıyı bul</h2>
  <p class="card-hint">
    Sitede gördüğün yazıyı buraya yaz — örneğin <em>Neler Yapıyoruz</em>,
    <em>Hızlı Linkler</em> ya da <em>Teklif</em>. İlgili alan anında süzülür.
    Başka ekranda düzenlenen yazılar da aramaya dâhil; onlar için bağlantı çıkar.
  </p>
  <div class="field" style="margin:0;">
    <input type="search" id="yaziAra" placeholder="Örn. Neler Yapıyoruz, Filtreler, Hızlı Linkler…"
           autocomplete="off">
    <div class="hint" id="yaziAramaSonuc"></div>
  </div>
</div>

<?php
  /**
   * Bu ekranda OLMAYAN, başka ekranlarda düzenlenen yazılar.
   *
   * Kullanıcı "şu yazıyı nereden değiştiriyorum" diye buraya bakıyor; burada
   * bulamayınca yazının sabit olduğunu sanıyor. Bu liste onu doğru ekrana
   * yolluyor ve yukarıdaki aramaya da dâhil.
   */
  $baskaEkran = [
    ['Footer — logo altındaki kısa açıklama', 'Genel Ayarlar', '/teknikel/genel-ayarlar.php'],
    ['Footer — en alttaki telif yazısı', 'Genel Ayarlar', '/teknikel/genel-ayarlar.php'],
    ['Telefon, e-posta, adres bilgileri', 'Genel Ayarlar', '/teknikel/genel-ayarlar.php'],
    ['Yüzen WhatsApp, arama ve hızlı iletişim butonları', 'Yüzen Butonlar', '/teknikel/yuzen-butonlar.php'],
    ['Footer sütun başlıkları (Hızlı Linkler / Hizmetlerimiz / İletişim)', 'Menü & Alt Bilgi', '/teknikel/menu-footer.php#footer'],
    ['Footer hızlı link adları ve sosyal butonlar', 'Menü & Alt Bilgi', '/teknikel/menu-footer.php#footer'],
    ['Mobil menü (hamburger) buton yazıları', 'Menü & Alt Bilgi', '/teknikel/menu-footer.php'],
    ['Üst menüdeki "Ürünlerimiz" ve "Hizmetlerimiz" adları', 'Genel Ayarlar', '/teknikel/genel-ayarlar.php'],
    ['Ana sayfa slayt başlıkları ve yazıları', 'Ana Sayfa', '/teknikel/anasayfa.php'],
    ['Ana sayfa "Hakkımızda" özeti ve sayılar', 'Ana Sayfa', '/teknikel/anasayfa.php'],
    ['Ana sayfa teklif kutusu başlığı ve metni', 'Ana Sayfa', '/teknikel/anasayfa.php'],
    ['Hizmet kartlarının adı ve açıklaması', 'Hizmetler', '/teknikel/hizmetler.php'],
    ['Hizmet sayfalarının içeriği ve makine parkuru', 'Hizmetler', '/teknikel/hizmetler.php'],
    ['Teklif sayfası başlığı, iletişim kutusu, parça türü listesi', 'İletişim / Teklif', '/teknikel/teklif-sayfasi.php'],
    ['Ürünler sayfasının başlık görseli ve yazısı', 'Ürünler Sayfası', '/teknikel/urunler-sayfasi.php'],
    ['Kendi sayfalarının (Hakkımızda, Blog…) başlığı ve içeriği', 'Sayfalar', '/teknikel/sayfalar.php'],
    ['Ürün adı, açıklaması, teknik özellikleri', 'Ürünler', '/teknikel/urunler.php'],
    ['Makale başlığı, özeti, içeriği', 'Makaleler', '/teknikel/makaleler.php'],
    ['Kategori adları', 'Kategoriler', '/teknikel/kategoriler.php'],
  ];
?>
<div class="card" id="baskaEkranKart">
  <h2>Başka Ekranlarda Düzenlenenler</h2>
  <p class="card-hint">
    Bu yazılar burada değil, kendi ekranlarında duruyor — çünkü tek tek değil,
    ait oldukları içerikle birlikte düzenleniyorlar.
  </p>
  <ul class="baska-ekran">
    <?php foreach ($baskaEkran as [$ne, $nerede, $link]): ?>
      <li>
        <span class="be-ne"><?php echo e($ne); ?></span>
        <a href="<?php echo e($link); ?>" class="be-git"><?php echo e($nerede); ?> →</a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="card">
  <h2>Nasıl çalışır?</h2>
  <p style="color:var(--muted);font-size:14px;line-height:1.8;margin:0;">
    Her satırda o yazının sitede göründüğü hâli var. Değiştirmek istediğini yaz, kaydet.
    <strong>Boş bıraktığın alan</strong> varsayılan metniyle görünmeye devam eder —
    yani hiçbir şeyi bozmadan sadece istediklerini değiştirebilirsin.
    Kutunun altındaki gri yazı varsayılanı gösterir.
  </p>
</div>

<form method="post" id="yazilarForm">
  <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">

  <?php foreach ($gruplu as $grup => $satirlar): ?>
    <div class="card">
      <h2><?php echo e($grup); ?></h2>
      <?php foreach ($satirlar as $key => [$aciklama, $varsayilan]): ?>
        <?php $alan = 'lbl_' . str_replace('.', '__', $key); ?>
        <div class="field">
          <label for="<?php echo e($alan); ?>"><?php echo e($aciklama); ?></label>
          <?php if (mb_strlen($varsayilan) > 70): ?>
            <textarea id="<?php echo e($alan); ?>" name="<?php echo e($alan); ?>" rows="2"
                      placeholder="<?php echo e($varsayilan); ?>"><?php echo e($labels[$key] ?? ''); ?></textarea>
          <?php else: ?>
            <input type="text" id="<?php echo e($alan); ?>" name="<?php echo e($alan); ?>"
                   value="<?php echo e($labels[$key] ?? ''); ?>"
                   placeholder="<?php echo e($varsayilan); ?>">
          <?php endif; ?>
          <div class="hint">Varsayılan: <?php echo e($varsayilan); ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div class="card">
    <h2>Görseller nerede?</h2>
    <p class="card-hint" style="margin:0;">
      Burası yalnızca <strong>yazılar</strong> için. Sayfa başlığı görselleri her sayfanın
      kendi ekranında: <a href="/teknikel/urunler-sayfasi.php" style="color:var(--blue);">Ürünler Sayfası</a>,
      <a href="/teknikel/teklif-sayfasi.php" style="color:var(--blue);">İletişim / Teklif</a>,
      <a href="/teknikel/sayfalar.php" style="color:var(--blue);">kendi sayfaların</a>.
    </p>
  </div>

  <?php /* Kaydet butonu kenar çubuğunda sabit duruyor — layout-top.php */ ?>
</form>

<script>
(function () {
  var kutu = document.getElementById('yaziAra');
  var sonucYazi = document.getElementById('yaziAramaSonuc');
  if (!kutu) return;

  // Aranabilir birimler: her yazı alanı + "başka ekranda" satırları
  var alanlar = [].slice.call(document.querySelectorAll('#yazilarForm .field')).map(function (el) {
    var girdi = el.querySelector('input, textarea');
    var kart = el.closest('.card');
    // Grup başlığı da aranabilir olmalı: "footer" yazan kişi, açıklamasında
    // "footer" geçmese de Footer grubundaki alanları görmeli.
    var grup = kart && kart.querySelector('h2') ? kart.querySelector('h2').textContent : '';
    return {
      el: el,
      kart: kart,
      metin: (grup + ' ' + el.textContent + ' ' + (girdi ? girdi.placeholder + ' ' + girdi.value : '')).toLowerCase()
    };
  });
  var satirlar = [].slice.call(document.querySelectorAll('.baska-ekran li')).map(function (el) {
    return { el: el, kart: document.getElementById('baskaEkranKart'), metin: el.textContent.toLowerCase() };
  });
  var kartlar = [].slice.call(document.querySelectorAll('#yazilarForm .card'));

  // Türkçe harfleri sadeleştir: "Yapıyoruz" yazan "yapiyoruz" ile de bulsun
  function sade(s) {
    return s.toLowerCase()
      .replace(/ı/g, 'i').replace(/ş/g, 's').replace(/ğ/g, 'g')
      .replace(/ü/g, 'u').replace(/ö/g, 'o').replace(/ç/g, 'c').replace(/â/g, 'a');
  }

  function suz() {
    var q = sade(kutu.value.trim());
    if (!q) {
      alanlar.forEach(function (a) { a.el.hidden = false; });
      satirlar.forEach(function (s) { s.el.hidden = false; });
      kartlar.forEach(function (k) { k.hidden = false; });
      document.getElementById('baskaEkranKart').hidden = false;
      sonucYazi.textContent = '';
      return;
    }
    var bulunan = 0;
    alanlar.forEach(function (a) {
      var uyar = sade(a.metin).indexOf(q) !== -1;
      a.el.hidden = !uyar;
      if (uyar) bulunan++;
    });
    var beBulunan = 0;
    satirlar.forEach(function (s) {
      var uyar = sade(s.metin).indexOf(q) !== -1;
      s.el.hidden = !uyar;
      if (uyar) beBulunan++;
    });
    // İçinde görünür alan kalmayan kartı gizle
    kartlar.forEach(function (k) {
      k.hidden = k.querySelectorAll('.field:not([hidden])').length === 0;
    });
    document.getElementById('baskaEkranKart').hidden = beBulunan === 0;

    var toplam = bulunan + beBulunan;
    sonucYazi.textContent = toplam
      ? toplam + ' sonuç bulundu.' + (beBulunan ? ' (' + beBulunan + ' tanesi başka ekranda)' : '')
      : 'Sonuç yok. Sitede gördüğün yazının bir kelimesini dene.';
  }

  kutu.addEventListener('input', suz);
})();
</script>
<?php require __DIR__ . '/layout-bottom.php'; ?>
