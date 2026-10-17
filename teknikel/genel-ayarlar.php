<?php
require_once __DIR__ . '/config.php';
// Güvenlik: yetki kontrolü EN BAŞTA. Eskiden bu kontrol layout-top.php
// içinde, yani POST işlendikten SONRA devreye giriyordu; oturumu olmayan biri
// form gönderip içeriği değiştirebilirdi (çıktı görmese bile kayıt olurdu).
bm_admin_require_login();
$content = bm_load_content();
$bm_admin_active = 'genel';
$bm_admin_title = 'Genel Ayarlar';
$bm_admin_save_form = 'genelForm';
$bm_admin_preview = '/teknikel/onizleme.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        $content['site']['phone_display'] = trim($_POST['phone_display'] ?? '');
        $content['site']['phone_link'] = trim($_POST['phone_link'] ?? '');
        $content['site']['whatsapp_link'] = trim($_POST['whatsapp_link'] ?? '');
        $content['site']['whatsapp_text'] = trim($_POST['whatsapp_text'] ?? '');
        $content['site']['email'] = trim($_POST['email'] ?? '');
        $content['site']['address'] = trim($_POST['address'] ?? '');
        $content['site']['linkedin_link'] = trim($_POST['linkedin_link'] ?? '');
        $content['site']['footer_about'] = trim($_POST['footer_about'] ?? '');
        $content['site']['footer_note'] = trim($_POST['footer_note'] ?? '');
        $content['site']['verify_yandex'] = trim($_POST['verify_yandex'] ?? '');
        $content['site']['verify_google'] = trim($_POST['verify_google'] ?? '');
        $content['site']['share_image'] = trim($_POST['share_image'] ?? '');
        $content['site']['favicon_image'] = trim($_POST['favicon_image'] ?? '');

        // Site genelindeki sabit butonların PNG ikonları
        foreach (['phone_icon', 'whatsapp_icon', 'email_icon', 'address_icon'] as $k) {
            $content['site'][$k] = trim($_POST[$k] ?? '');
        }

        $content['menu']['urunler_label'] = trim($_POST['urunler_label'] ?? 'Ürünlerimiz');
        $content['menu']['hizmetler_label'] = trim($_POST['hizmetler_label'] ?? 'Hizmetlerimiz');

        // "Ürünlerimiz" açılır listesi artık elle girilmiyor; ürün kategorileri
        // veritabanından otomatik geliyor (admin/kategoriler.php).

        $hizmetlerItems = [];
        foreach (($_POST['hizmetler_item_label'] ?? []) as $i => $label) {
            $label = trim($label);
            $link = trim($_POST['hizmetler_item_link'][$i] ?? '');
            if ($label !== '') $hizmetlerItems[] = ['label' => $label, 'link' => $link !== '' ? $link : 'index.php#hizmetler'];
        }
        $content['menu']['hizmetler_items'] = $hizmetlerItems;

        bm_save_content($content);
        $message = 'ok|Ayarlar kaydedildi.';
    }
}
[$msgType, $msgText] = array_pad(explode('|', $message, 2), 2, '');

require __DIR__ . '/layout-top.php';
$site = $content['site'];
$menu = $content['menu'];
?>
<div class="admin-topline">
  <div>
    <h1>Genel Ayarlar</h1>
    <div class="sub">İletişim bilgileri ve üst menüdeki açılır listeler</div>
  </div>
  <a href="/teknikel/logout.php" class="logout-link">Çıkış Yap</a>
</div>

<?php if ($msgText): ?><div class="flash <?php echo $msgType; ?>"><?php echo e($msgText); ?></div><?php endif; ?>

<form method="post" id="genelForm">
  <input type="hidden" name="onizleme_turu" value="genel">
  <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">

  <div class="card">
    <h2>İletişim Bilgileri</h2>
    <div class="grid-2">
      <div class="field"><label>Telefon (görünen)</label><input type="text" name="phone_display" value="<?php echo e($site['phone_display'] ?? ''); ?>" placeholder="+90 (5XX) XXX XX XX"></div>
      <div class="field"><label>Telefon (tel: link, boşluksuz)</label><input type="text" name="phone_link" value="<?php echo e($site['phone_link'] ?? ''); ?>" placeholder="+905XXXXXXXXX"></div>
      <div class="field"><label>WhatsApp Linki</label><input type="text" name="whatsapp_link" value="<?php echo e($site['whatsapp_link'] ?? ''); ?>" placeholder="https://wa.me/905XXXXXXXXX"></div>
      <div class="field"><label>WhatsApp Buton Yazısı</label><input type="text" name="whatsapp_text" value="<?php echo e($site['whatsapp_text'] ?? ''); ?>" placeholder="WhatsApp"><div class="hint">Ana sayfa ve hizmet sayfalarındaki WhatsApp butonunun üzerinde yazan metin. Numara da yazabilirsin: 0533 340 32 85. Boş bırakırsan "WhatsApp" görünür.</div></div>
      <div class="field"><label>E-posta</label><input type="email" name="email" value="<?php echo e($site['email'] ?? ''); ?>"></div>
      <div class="field"><label>Adres</label><input type="text" name="address" value="<?php echo e($site['address'] ?? ''); ?>"></div>
      <div class="field"><label>LinkedIn Linki</label><input type="text" name="linkedin_link" value="<?php echo e($site['linkedin_link'] ?? ''); ?>"></div>
    </div>
    <div class="field"><label>Footer — Kısa Açıklama</label><textarea name="footer_about"><?php echo e($site['footer_about'] ?? ''); ?></textarea></div>
    <div class="field"><label>Footer — Alt Yazı (telif hakkı)</label><input type="text" name="footer_note" value="<?php echo e($site['footer_note'] ?? ''); ?>"></div>
  </div>

  <div class="card">
    <h2>🔗 Paylaşım Görseli (WhatsApp / LinkedIn)</h2>
    <p class="card-hint">
      Site adresini WhatsApp'ta, LinkedIn'de ya da bir mailde paylaştığında
      karşı tarafta beliren önizleme kutusunun görseli.
      <br><strong>Ürün, hizmet, makale ve sayfa linklerinde</strong> o sayfanın kendi
      fotoğrafı otomatik kullanılır — buradaki görsel yalnızca kendi fotoğrafı
      olmayan sayfalar için (ana sayfa, teklif, yasal sayfalar) devreye girer.
      <br>En iyi sonuç: <strong>1200×630 piksel yatay</strong> bir görsel, boyutu
      300 KB'ın altında. Boş bırakırsan ana sayfa slaytının ilki kullanılır.
    </p>
    <?php echo bm_admin_icon_field('share_image', $site['share_image'] ?? '', [
      'label' => 'Görsel Adresi',
      'big'   => true,
      'id'    => 'share_image',
      'hint'  => 'Yükle ile bilgisayarından ekle, Siteden Seç ile daha önce yüklediğin görsellerden seç.',
    ]); ?>
  </div>

  <div class="card">
    <h2>⭐ Site Faviconu (Sekme Simgesi)</h2>
    <p class="card-hint">
      Tarayıcı sekmelerinde, yer imlerinde ve Google arama sonuçlarında site adının
      yanında görünen küçük kare simge.
      <br>En iyi sonuç: <strong>Kare (PNG veya ICO formatında, en az 48×48 veya 192×192 piksel)</strong>, saydam zeminli logo simgesi.
      Boş bırakırsan sitedeki varsayılan mavi "B" simgesi kullanılır.
    </p>
    <?php echo bm_admin_icon_field('favicon_image', $site['favicon_image'] ?? '', [
      'label' => 'Favicon Görseli',
      'id'    => 'favicon_image',
      'hint'  => 'Yükle butonu ile bilgisayarından yükleyebilir veya Siteden Seç ile mevcut görsellerden seçebilirsin.',
    ]); ?>
  </div>

  <div class="card">
    <h2>🔎 Arama Motoru Doğrulama</h2>
    <p class="card-hint">
      Siteyi Yandex ve Google'a tanıtırken "bu site senin mi?" diye sorulur.
      Onların verdiği <strong>doğrulama kodunu</strong> buraya yapıştır, kaydet,
      sonra o ekranda "Doğrula" de. Hangi biçimde kopyalarsan kopyala çalışır:
      sadece kod, <code>&lt;meta ...&gt;</code> satırının tamamı, ya da
      <code>google-site-verification=...</code> biçimi — gerekli kısım ayıklanıyor.
      <br><strong>Doğrulandıktan sonra bu satırları silme</strong>, Google kontrolü
      belli aralıklarla tekrarlıyor. Buraya yazdığın kod her yeni sürüm yüklemesinde
      yerinde kalır (dosya yöntemindeki gibi kaybolmaz).
    </p>
    <div class="grid-2">
      <div class="field">
        <label>Yandex Doğrulama Kodu</label>
        <input type="text" name="verify_yandex" value="<?php echo e($site['verify_yandex'] ?? ''); ?>" placeholder="a1b2c3d4e5f6g7h8">
        <div class="hint">webmaster.yandex.com.tr → Site ekle → "Meta etiketi" sekmesi</div>
      </div>
      <div class="field">
        <label>Google Doğrulama Kodu</label>
        <input type="text" name="verify_google" value="<?php echo e($site['verify_google'] ?? ''); ?>" placeholder="xYz123...">
        <div class="hint">search.google.com/search-console → "HTML etiketi" seçeneği</div>
      </div>
    </div>
    <p class="card-hint" style="margin-top:10px;">
      Site haritan hazır ve otomatik güncelleniyor:
      <strong>https://www.beylimakina.com/sitemap.xml</strong> — Yandex ve Google'a
      eklemen gereken adres bu.
    </p>
  </div>

  <div class="card">
    <h2>🔘 Buton İkonları</h2>
    <p class="card-hint">
      Sitenin sabit butonlarının başındaki küçük logolar. Gerçek WhatsApp / telefon
      logolarını buradan yükleyebilirsin — emoji her cihazda farklı çiziliyor.
      Yüklemediğin sürece yerine emoji kullanılır, yani boş bırakmak sorun değil.
      Kare ve saydam zeminli PNG en iyi durur (ör. 64×64).
    </p>
    <div class="grid-2">
      <?php echo bm_admin_icon_field('phone_icon', $site['phone_icon'] ?? '', [
        'label' => 'Telefon Butonu',
        'hint'  => 'Ana sayfa ve hizmet sayfalarındaki arama butonu, footer telefon satırı.',
      ]); ?>
      <?php echo bm_admin_icon_field('whatsapp_icon', $site['whatsapp_icon'] ?? '', [
        'label' => 'WhatsApp Butonu',
        'hint'  => 'Ana sayfadaki WhatsApp butonu.',
      ]); ?>
      <?php echo bm_admin_icon_field('email_icon', $site['email_icon'] ?? '', [
        'label' => 'E-posta',
        'hint'  => 'Footer e-posta satırı.',
      ]); ?>
      <?php echo bm_admin_icon_field('address_icon', $site['address_icon'] ?? '', [
        'label' => 'Adres / Konum',
        'hint'  => 'Footer adres satırı.',
      ]); ?>
    </div>
    <p class="card-hint" style="margin:14px 0 0;">
      Teklif sayfasındaki iletişim butonları, mobil menüdeki butonlar ve footer'daki
      sosyal butonların ikonları kendi sayfalarından ayarlanıyor:
      <a href="/teknikel/teklif-sayfasi.php" style="color:var(--blue);">Teklif Sayfası</a> ve
      <a href="/teknikel/menu-footer.php" style="color:var(--blue);">Menü &amp; Footer</a>.
    </p>
  </div>

  <div class="card">
    <h2>Üst Menü — "Ürünlerimiz" Sekmesi</h2>
    <div class="field">
      <label>Menü Başlığı</label>
      <input type="text" name="urunler_label" value="<?php echo e($menu['urunler_label'] ?? ''); ?>">
    </div>
    <p class="hint">
      Bu sekmenin açılır listesi otomatik oluşuyor — ürün kategorilerin ne ise menüde o görünür.
      Değiştirmek için <a href="/teknikel/kategoriler.php">Kategoriler</a> sayfasını kullan.
    </p>
  </div>

  <div class="card">
    <h2>Üst Menü — "Hizmetlerimiz" Açılır Listesi</h2>
    <div class="field"><label>Menü Başlığı</label><input type="text" name="hizmetler_label" value="<?php echo e($menu['hizmetler_label'] ?? ''); ?>"></div>
    <div id="hizmetlerItems">
      <?php foreach (($menu['hizmetler_items'] ?? []) as $item): ?>
        <div class="repeat-row">
          <button type="button" class="remove-row" onclick="this.closest('.repeat-row').remove()">✕</button>
          <div class="grid-2">
            <div class="field"><label>Etiket</label><input type="text" name="hizmetler_item_label[]" value="<?php echo e($item['label']); ?>"></div>
            <div class="field"><label>Link</label><input type="text" name="hizmetler_item_link[]" value="<?php echo e($item['link']); ?>"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn gray small" onclick="addRow('hizmetlerItems','hizmetler_item_label[]','hizmetler_item_link[]')">+ Öğe Ekle</button>
  </div>

  <?php /* Kaydet butonu kenar çubuğunda sabit duruyor — layout-top.php */ ?>
</form>

<script>
function addRow(containerId, nameA, nameB) {
  const c = document.getElementById(containerId);
  const div = document.createElement('div');
  div.className = 'repeat-row';
  div.innerHTML = '<button type="button" class="remove-row" onclick="this.closest(\'.repeat-row\').remove()">✕</button>' +
    '<div class="grid-2">' +
    '<div class="field"><label>Etiket</label><input type="text" name="' + nameA + '"></div>' +
    '<div class="field"><label>Link</label><input type="text" name="' + nameB + '"></div>' +
    '</div>';
  c.appendChild(div);
}
</script>
<?php require __DIR__ . '/layout-bottom.php'; ?>
