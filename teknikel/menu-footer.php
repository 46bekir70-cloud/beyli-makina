<?php
/**
 * Menü & Footer yönetimi.
 *
 * Sitede panelden değiştirilemeyen yer kalmasın diye açıldı: mobil menü paneli
 * (drawer), footer kolon başlıkları, hızlı linkler ve sosyal butonlar.
 *
 * Butonların hepsi aynı yapıyı paylaşıyor (ikon/PNG + etiket + tür + değer),
 * böylece teklif sayfasındaki iletişim butonlarıyla aynı mantıkla çalışıyorlar.
 */
require_once __DIR__ . '/config.php';

bm_admin_require_login();

$content = bm_load_content();
$bm_admin_active = 'menufooter';
$bm_admin_title = 'Menü & Footer';
$bm_admin_save_form = 'menuFooterForm';
$message = '';

/** POST'tan paralel dizi hâlinde gelen buton listesini toplar */
function bm_collect_buttons($prefix) {
    $out = [];
    foreach ((array)($_POST[$prefix . '_text'] ?? []) as $i => $text) {
        $text  = trim((string)$text);
        $label = trim((string)($_POST[$prefix . '_label'][$i] ?? ''));
        $image = trim((string)($_POST[$prefix . '_image'][$i] ?? ''));
        // Üçü de boşsa satır kaydedilmez
        if ($text === '' && $label === '' && $image === '') continue;
        $out[] = [
            'icon'  => trim((string)($_POST[$prefix . '_icon'][$i] ?? '')),
            'image' => $image,
            'label' => $label,
            'text'  => $text,
            'type'  => trim((string)($_POST[$prefix . '_type'][$i] ?? 'text')),
            'value' => trim((string)($_POST[$prefix . '_value'][$i] ?? '')),
        ];
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        // --- Mobil menü paneli ---
        $content['drawer'] = [
            'cta_text' => trim($_POST['drawer_cta_text'] ?? ''),
            'cta_link' => trim($_POST['drawer_cta_link'] ?? ''),
            'buttons'  => bm_collect_buttons('dw'),
        ];

        // --- Footer ---
        $content['footer'] = [
            'headings' => [
                'links'    => trim($_POST['fh_links'] ?? ''),
                'services' => trim($_POST['fh_services'] ?? ''),
                'contact'  => trim($_POST['fh_contact'] ?? ''),
                'legal'    => trim($_POST['fh_legal'] ?? ''),
            ],
            'quick_links'    => bm_collect_buttons('ql'),
            'legal_links'    => bm_collect_buttons('lg'),
            'social_buttons' => bm_collect_buttons('sc'),
        ];

        bm_save_content($content);
        $message = 'ok|Menü ve footer kaydedildi.';
        $content = bm_load_content();
    }
}

$drawer   = $content['drawer'] ?? [];
$footer   = $content['footer'] ?? [];
$headings = $footer['headings'] ?? [];

$bosSatir = [['icon' => '', 'image' => '', 'label' => '', 'text' => '', 'type' => 'link', 'value' => '']];
$dwRows = bm_button_list($content, 'drawer.buttons')        ?: $bosSatir;
$qlRows = bm_button_list($content, 'footer.quick_links')    ?: $bosSatir;
$scRows = bm_button_list($content, 'footer.social_buttons') ?: $bosSatir;
$lgRows = bm_button_list($content, 'footer.legal_links')    ?: $bosSatir;

$buttonTypes = [
    'link'     => 'Bağlantı (adres yazın)',
    'tel'      => 'Telefon (tıklayınca arar)',
    'whatsapp' => 'WhatsApp (numara yazın)',
    'mail'     => 'E-posta',
    'text'     => 'Sadece yazı (tıklanmaz)',
];

/** Tek bir buton satırını basar */
function bm_button_row($prefix, $b, $buttonTypes, $sinif = 'btnrow') {
    ob_start(); ?>
    <div class="repeat-row <?php echo e($sinif); ?>">
      <button type="button" class="remove-row row-del" title="Satırı sil">✕</button>
      <div class="field-row">
        <?php echo bm_admin_icon_field($prefix . '_image[]', $b['image'] ?? '', [
          'label' => 'İkon (PNG)',
          'hint'  => 'Butonun başındaki küçük logo. Yüklenmezse yandaki emoji kullanılır.',
        ]); ?>
        <div class="field field-narrow">
          <label>Emoji (yedek)</label>
          <input type="text" name="<?php echo $prefix; ?>_icon[]" value="<?php echo e($b['icon'] ?? ''); ?>" maxlength="8">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Görünen Yazı</label>
          <input type="text" name="<?php echo $prefix; ?>_text[]" value="<?php echo e($b['text'] ?? ''); ?>">
        </div>
        <div class="field field-narrow">
          <label>Üst Etiket</label>
          <input type="text" name="<?php echo $prefix; ?>_label[]" value="<?php echo e($b['label'] ?? ''); ?>">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Tür</label>
          <select name="<?php echo $prefix; ?>_type[]">
            <?php foreach ($buttonTypes as $k => $d): ?>
              <option value="<?php echo e($k); ?>"<?php echo (($b['type'] ?? 'link') === $k) ? ' selected' : ''; ?>>
                <?php echo e($d); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Adres / Numara</label>
          <input type="text" name="<?php echo $prefix; ?>_value[]" value="<?php echo e($b['value'] ?? ''); ?>"
                 placeholder="https://... veya +905320000000">
        </div>
      </div>
    </div>
    <?php return ob_get_clean();
}

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Menü &amp; Footer</h1>
    <div class="sub">Mobil menü paneli, footer kolonları ve sosyal butonlar</div>
  </div>
  <a href="/teknikel/index.php" class="logout-link">← Panele Dön</a>
</div>

<?php if ($message): [$type, $text] = explode('|', $message, 2); ?>
  <div class="flash <?php echo $type === 'ok' ? 'ok' : 'err'; ?>"><?php echo e($text); ?></div>
<?php endif; ?>

<form method="post" id="menuFooterForm">
  <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">

  <div class="card">
    <h2>📱 Mobil Menü Paneli</h2>
    <p class="card-hint">
      Telefonda hamburger simgesine basınca sağdan açılan panel. Üst kısımdaki menü
      bağlantıları <a href="/teknikel/sayfalar.php">Sayfalar</a> ve ürün/hizmet
      bölümlerinden otomatik gelir; burada panelin <strong>alt kısmını</strong> yönetiyorsun.
    </p>
    <div class="field-row">
      <div class="field">
        <label>Buton Yazısı</label>
        <input type="text" name="drawer_cta_text" value="<?php echo e($drawer['cta_text'] ?? ''); ?>"
               placeholder="Teklif Al">
        <span class="hint">Boş bırakırsan buton hiç görünmez.</span>
      </div>
      <div class="field">
        <label>Buton Bağlantısı</label>
        <input type="text" name="drawer_cta_link" value="<?php echo e($drawer['cta_link'] ?? ''); ?>"
               placeholder="/teklif">
      </div>
    </div>

    <label style="margin-top:8px;">Panel Altındaki İletişim Butonları</label>
    <div id="dwRows">
      <?php foreach ($dwRows as $b) echo bm_button_row('dw', $b, $buttonTypes); ?>
    </div>
    <button type="button" class="btn ghost" data-add="dw">+ Buton Ekle</button>
  </div>

  <div class="card" id="footer">
    <h2>🔻 Footer — Kolon Başlıkları</h2>
    <div class="grid-3">
      <div class="field">
        <label>1. Kolon (linkler)</label>
        <input type="text" name="fh_links" value="<?php echo e($headings['links'] ?? ''); ?>" placeholder="Hızlı Linkler">
      </div>
      <div class="field">
        <label>2. Kolon (hizmetler)</label>
        <input type="text" name="fh_services" value="<?php echo e($headings['services'] ?? ''); ?>" placeholder="Hizmetlerimiz">
      </div>
      <div class="field">
        <label>3. Kolon (iletişim)</label>
        <input type="text" name="fh_contact" value="<?php echo e($headings['contact'] ?? ''); ?>" placeholder="İletişim">
      </div>
      <div class="field">
        <label>4. Kolon (yasal)</label>
        <input type="text" name="fh_legal" value="<?php echo e($headings['legal'] ?? ''); ?>" placeholder="Yasal Bilgilendirme">
      </div>
    </div>
    <p class="card-hint" style="margin-bottom:0;">
      Hizmetler kolonu otomatik dolar (Hizmetler bölümündeki ilk 5 hizmet).
      İletişim kolonu <a href="/teknikel/genel-ayarlar.php">Genel Ayarlar</a>'daki bilgileri gösterir.
    </p>
  </div>

  <div class="card">
    <h2>🔗 Footer — Hızlı Linkler</h2>
    <p class="card-hint">
      Boş bırakırsan menüdeki sayfalardan otomatik üretilir. Kendi listeni yapmak
      istersen buraya ekle — o zaman otomatik liste devre dışı kalır.
    </p>
    <div id="qlRows">
      <?php foreach ($qlRows as $b) echo bm_button_row('ql', $b, $buttonTypes); ?>
    </div>
    <button type="button" class="btn ghost" data-add="ql">+ Link Ekle</button>
  </div>

  <div class="card">
    <h2>⚖️ Footer — Yasal Bilgilendirme</h2>
    <p class="card-hint">
      Altbilgide <strong>kendi başlığı altında</strong> duran bağlantılar: KVKK aydınlatma
      metni, gizlilik politikası, çerez politikası, kurumsal bilgiler. Yasal metinler
      hızlı linklerin arasında kaybolmasın diye ayrı tutuluyor.
    </p>
    <p class="card-hint">
      <strong>Listeyi boşaltırsan bu kolon hiç görünmez</strong> ve footer eski dört kolonlu
      düzenine döner. Sayfaların yazılarını <a href="/teknikel/sayfalar.php">Sayfalar</a>
      bölümünden düzenleyebilirsin.
    </p>
    <div id="lgRows">
      <?php foreach ($lgRows as $b) echo bm_button_row('lg', $b, $buttonTypes); ?>
    </div>
    <button type="button" class="btn ghost" data-add="lg">+ Link Ekle</button>
  </div>

  <div class="card">
    <h2>🌐 Footer — Logo Butonları (sol alt köşe)</h2>
    <p class="card-hint">
      Sitenin en altında, logonun ve açıklamanın hemen altındaki küçük kare butonlar —
      şu an WhatsApp ve e-posta var. LinkedIn, Instagram, Facebook, YouTube…
      <strong>istediğin kadar ekle, istediğini sil.</strong>
    </p>
    <p class="card-hint">
      <strong>Bu butonlarda yazı görünmez, sadece logo görünür.</strong> Bir buton için
      iki şey yeterli: <em>İkon (PNG)</em> alanına logoyu yükle, <em>Tür</em> +
      <em>Adres</em> alanına da gideceği yeri yaz. "Görünen Yazı" ve "Üst Etiket"
      alanları burada sadece fare ile üstüne gelince çıkan ipucu ve görme engelli
      okuyucular için kullanılıyor — hesabın adını yazman yeterli.
    </p>
    <div id="scRows">
      <?php foreach ($scRows as $b) echo bm_button_row('sc', $b, $buttonTypes); ?>
    </div>
    <button type="button" class="btn ghost" data-add="sc">+ Logo Butonu Ekle</button>
  </div>

  <?php /* Kaydet butonu kenar çubuğunda sabit duruyor — layout-top.php */ ?>
</form>

<script>
(function () {
  /* Boş satırın HTML'i PHP'de üretiliyor: satır düzeni tek yerde dursun.
     Eskiden aynı markup burada elle bir daha yazılıyordu ve alan eklenince
     ikisini birlikte güncellemek gerekiyordu. */
  var templates = <?php
    $bosSatir = [];
    foreach (['dw', 'ql', 'sc', 'lg'] as $p) {
        // Kapsayıcı div JS tarafında oluşturuluyor, sadece içi gerekiyor
        $html = bm_button_row($p, [], $buttonTypes);
        $bosSatir[$p] = preg_replace('#^\s*<div class="repeat-row[^"]*">(.*)</div>\s*$#s', '$1', $html);
    }
    echo json_encode($bosSatir, JSON_UNESCAPED_UNICODE);
  ?>;

  document.querySelectorAll('[data-add]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var prefix = btn.dataset.add;
      var wrap = document.getElementById(prefix + 'Rows');
      var row = document.createElement('div');
      row.className = 'repeat-row btnrow';
      row.innerHTML = templates[prefix] || '';
      wrap.appendChild(row);
      var first = row.querySelector('input');
      if (first) first.focus();
    });
  });

  // Silme dinleyicisi kapsayıcılarda: satırlar sonradan da ekleniyor
  ['dwRows', 'qlRows', 'scRows', 'lgRows'].forEach(function (id) {
    var wrap = document.getElementById(id);
    if (!wrap) return;
    wrap.addEventListener('click', function (e) {
      if (!e.target.classList.contains('row-del')) return;
      var rows = wrap.querySelectorAll('.btnrow');
      if (rows.length === 1) {
        // Tek satır kalmışsa silmek yerine boşalt — form yapısı bozulmasın
        rows[0].querySelectorAll('input').forEach(function (i) { i.value = ''; });
        rows[0].querySelectorAll('[data-icon-up]').forEach(function (box) {
          box.classList.add('is-empty');
          var img = box.querySelector('.icon-up-prev img');
          if (img) img.removeAttribute('src');
        });
      } else {
        e.target.closest('.btnrow').remove();
      }
    });
  });
})();
</script>
<?php require __DIR__ . '/layout-bottom.php'; ?>
