<?php
/**
 * Teklif sayfası (/teklif) düzenleme ekranı.
 * Sayfa başlığı, açıklamalar ve formdaki "Parça / Hizmet Türü" seçenekleri.
 */
require_once __DIR__ . '/config.php';
// bm_config() ve yükleme sınırı yardımcıları burada tanımlı; admin/config.php
// yalnızca inc/functions.php'yi yüklüyor, bu yüzden ayrıca gerekiyor.
require_once __DIR__ . '/../inc/db.php';

bm_admin_require_login();

/**
 * POST'tan paralel dizi hâlinde gelen buton listesini toplar.
 * Hem iletişim (cb) hem sosyal medya (sb) listeleri aynı alanları kullanıyor.
 */
function bm_teklif_butonlari($prefix) {
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

$content = bm_load_content();
$bm_admin_active = 'sayfalar'; // Sayfalar bölümünün altında yaşıyor
$bm_admin_title = 'Teklif Sayfası';
$bm_admin_save_form = 'teklifForm';
$bm_admin_preview = '/teknikel/onizleme.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        $order = $content['order_section'] ?? [];
        $order['tag']        = trim($_POST['order_tag'] ?? '');
        $order['title']      = trim($_POST['order_title'] ?? '');
        $order['text']       = trim($_POST['order_text'] ?? '');
        $order['side_title'] = trim($_POST['side_title'] ?? '');
        $order['side_text']  = trim($_POST['side_text'] ?? '');
        $order['form_title'] = trim($_POST['form_title'] ?? '');
        $order['hero_image'] = trim($_POST['hero_image'] ?? '');
        $order['hero_focus'] = trim($_POST['hero_focus'] ?? '');

        // --- Kurumsal form ayarları (Bireysel / Kurumsal sekmeleri)
        $kurumsal = [];
        $kurumsal['enabled']           = !empty($_POST['corp_enabled']) ? 1 : 0;
        $kurumsal['consent_box']       = !empty($_POST['corp_consent_box']) ? 1 : 0;
        $kurumsal['extras_individual'] = !empty($_POST['corp_extras_individual']) ? 1 : 0;
        $kurumsal['default_country']   = strtoupper(trim((string)($_POST['corp_default_country'] ?? 'TR'))) ?: 'TR';
        $kurumsal['kvkk_url']          = trim((string)($_POST['corp_kvkk_url'] ?? ''));
        $kurumsal['fields']            = [];
        foreach (array_keys(bm_corporate_field_defs()) as $alanAdi) {
            $durum = (string)($_POST['corp_field'][$alanAdi] ?? '');
            if (!in_array($durum, ['kapali', 'istege', 'zorunlu'], true)) $durum = 'kapali';
            $kurumsal['fields'][$alanAdi] = $durum;
        }
        $order['corporate'] = $kurumsal;

        // İletişim butonları (cb) ve sosyal medya butonları (sb) aynı yapıda
        $order['contact_buttons'] = bm_teklif_butonlari('cb');
        $order['social_buttons']  = bm_teklif_butonlari('sb');

        $types = [];
        foreach ((array)($_POST['part_type'] ?? []) as $t) {
            $t = trim((string)$t);
            if ($t !== '') $types[] = $t;
        }
        // Liste boş bırakılırsa formda hiç seçenek kalmaz; en az bir tane şart.
        if (!$types) {
            $message = 'err|En az bir "Parça / Hizmet Türü" seçeneği olmalı.';
        } else {
            $order['part_types'] = $types;
            $content['order_section'] = $order;
            bm_save_content($content);
            $message = 'ok|Teklif sayfası kaydedildi.';
            $content = bm_load_content();
        }
    }
}

$order = $content['order_section'] ?? [];
$partTypes = array_values(array_filter((array)($order['part_types'] ?? [])));
if (!$partTypes) $partTypes = [''];

// Hiç tanımlanmamışsa Genel Ayarlar'daki bilgilerden varsayılan liste gelir
$contactButtons = bm_contact_buttons($content);
if (!$contactButtons) $contactButtons = [['icon' => '', 'label' => '', 'text' => '', 'type' => 'tel', 'value' => '']];

// Sosyal medya butonları: varsayılanı yok, boş bir satırla başlar
$socialButtons = bm_quote_social_buttons($content);
if (!$socialButtons) $socialButtons = [['icon' => '', 'image' => '', 'label' => '', 'text' => '', 'type' => 'link', 'value' => '']];

$buttonTypes = [
    'tel'      => 'Telefon (tıklayınca arar)',
    'whatsapp' => 'WhatsApp (numara yazın)',
    'mail'     => 'E-posta (tıklayınca mail açar)',
    'link'     => 'Bağlantı (adres yazın)',
    'text'     => 'Sadece yazı (tıklanmaz)',
];

/**
 * Tek bir buton satırı. Hem iletişim (cb) hem sosyal medya (sb) listesi bunu
 * kullanıyor; "+ Ekle" ile eklenen boş satır da buradan üretiliyor, böylece
 * satır düzeni tek bir yerde duruyor.
 */
function bm_contact_row($b, $buttonTypes, $prefix = 'cb', $opts = []) {
    $sinif      = $opts['class']       ?? 'contact-row-item';
    $varsayilan = $opts['type']        ?? 'text';
    $emojiIpucu = $opts['emoji']       ?? '📞';
    $yaziIpucu  = $opts['text_ph']     ?? '+90 532 000 00 00';
    $etiketIpucu= $opts['label_ph']    ?? 'Telefon';
    ob_start(); ?>
    <div class="repeat-row <?php echo e($sinif); ?>">
      <button type="button" class="remove-row row-del" title="Butonu sil">✕</button>
      <div class="field-row">
        <?php echo bm_admin_icon_field($prefix . '_image[]', $b['image'] ?? '', [
          'label' => 'İkon (PNG)',
          'hint'  => 'Yüklenmezse yandaki emoji kullanılır.',
        ]); ?>
        <div class="field field-narrow">
          <label>Emoji (yedek)</label>
          <input type="text" name="<?php echo $prefix; ?>_icon[]" value="<?php echo e($b['icon'] ?? ''); ?>"
                 maxlength="8" placeholder="<?php echo e($emojiIpucu); ?>">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Üst Etiket</label>
          <input type="text" name="<?php echo $prefix; ?>_label[]" value="<?php echo e($b['label'] ?? ''); ?>"
                 placeholder="<?php echo e($etiketIpucu); ?>">
        </div>
        <div class="field">
          <label>Görünen Yazı</label>
          <input type="text" name="<?php echo $prefix; ?>_text[]" value="<?php echo e($b['text'] ?? ''); ?>"
                 placeholder="<?php echo e($yaziIpucu); ?>">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Tür</label>
          <select name="<?php echo $prefix; ?>_type[]">
            <?php foreach ($buttonTypes as $key => $desc): ?>
              <option value="<?php echo e($key); ?>"<?php echo (($b['type'] ?? $varsayilan) === $key) ? ' selected' : ''; ?>>
                <?php echo e($desc); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Numara / Adres</label>
          <input type="text" name="<?php echo $prefix; ?>_value[]" value="<?php echo e($b['value'] ?? ''); ?>"
                 placeholder="+905320000000 veya https://...">
          <span class="hint">Boş bırakırsan "Görünen Yazı" kullanılır.</span>
        </div>
      </div>
    </div>
    <?php return ob_get_clean();
}

/** Sosyal medya satırı için ipuçları */
function bm_social_row($b, $buttonTypes) {
    return bm_contact_row($b, $buttonTypes, 'sb', [
        'class'    => 'social-row-item',
        'type'     => 'link',
        'emoji'    => '📷',
        'label_ph' => 'Instagram',
        'text_ph'  => '@beylimakina',
    ]);
}

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Teklif Sayfası</h1>
    <div class="sub">
      Adres: <code>/teklif</code> —
      <a href="/teklif" target="_blank" style="color:var(--blue);">canlı sayfayı aç ↗</a>
    </div>
  </div>
  <a href="/teknikel/index.php" class="logout-link">← Panele Dön</a>
</div>

<?php if ($message): [$type, $text] = explode('|', $message, 2); ?>
  <div class="flash <?php echo $type === 'ok' ? 'ok' : 'err'; ?>"><?php echo e($text); ?></div>
<?php endif; ?>

<form method="post" id="teklifForm">
  <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
  <input type="hidden" name="onizleme_turu" value="teklif">

  <div class="card">
    <h2>Sayfa Başlığı</h2>
    <div class="field">
      <label>Üst Etiket</label>
      <input type="text" name="order_tag" value="<?php echo e($order['tag'] ?? ''); ?>"
             placeholder="Örn. Özel Sipariş">
    </div>
    <div class="field">
      <label>Başlık</label>
      <input type="text" name="order_title" value="<?php echo e($order['title'] ?? ''); ?>"
             placeholder="Örn. Kişiye Özel Parça Siparişi">
    </div>
    <div class="field">
      <label>Açıklama</label>
      <textarea name="order_text" rows="3"><?php echo e($order['text'] ?? ''); ?></textarea>
      <div class="hint">Bu üç alan ana sayfadaki teklif kutusunda da kullanılıyor.</div>
    </div>
  </div>

  <?php echo bm_admin_hero_card('hero_image', 'hero_focus', $order['hero_image'] ?? '', $order['hero_focus'] ?? ''); ?>

  <div class="card">
    <h2>İletişim Kutusu (solda)</h2>
    <div class="grid-2">
      <div class="field">
        <label>Kutu Başlığı</label>
        <input type="text" name="side_title" value="<?php echo e($order['side_title'] ?? ''); ?>"
               placeholder="İletişim">
      </div>
      <div class="field">
        <label>Form Kutusu Başlığı</label>
        <input type="text" name="form_title" value="<?php echo e($order['form_title'] ?? ''); ?>"
               placeholder="Teklif Formu">
      </div>
    </div>
    <div class="field">
      <label>Kutu Açıklaması</label>
      <textarea name="side_text" rows="2"><?php echo e($order['side_text'] ?? ''); ?></textarea>
      <div class="hint">
        Telefon, WhatsApp, e-posta ve adres bilgileri
        <a href="/teknikel/genel-ayarlar.php">Genel Ayarlar</a>'dan gelir.
      </div>
    </div>
  </div>

  <?php
    // Dosya boyutu sınırı iki yerden geliyor; hangisinin daraltıcı olduğunu
    // görmeden "neden 2 MB yazıyor" sorusuna cevap vermek zor.
    $cfgMb    = (int)(bm_config('form')['max_upload_mb'] ?? 15);
    $iniUp    = ini_get('upload_max_filesize');
    $iniPost  = ini_get('post_max_size');
    $etkin    = bm_max_upload_mb();
    $darBogaz = ($etkin < $cfgMb) ? 'sunucu' : 'config';
  ?>
  <div class="card">
    <h2>Dosya Yükleme Sınırı</h2>
    <div class="table-wrap">
      <table class="admin-table" style="min-width:0;">
        <tbody>
          <tr>
            <th style="text-align:left;">config.php içindeki değer</th>
            <td class="mono"><?php echo $cfgMb; ?> MB</td>
          </tr>
          <tr>
            <th style="text-align:left;">Sunucu — upload_max_filesize</th>
            <td class="mono"><?php echo e($iniUp); ?></td>
          </tr>
          <tr>
            <th style="text-align:left;">Sunucu — post_max_size</th>
            <td class="mono"><?php echo e($iniPost); ?></td>
          </tr>
          <tr>
            <th style="text-align:left;"><strong>Formda geçerli olan</strong></th>
            <td class="mono"><strong><?php echo $etkin; ?> MB</strong></td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php if ($darBogaz === 'sunucu'): ?>
      <div class="flash err" style="margin:14px 0 0;">
        Sınırı <strong>sunucu</strong> daraltıyor. config.php'de <?php echo $cfgMb; ?> MB yazıyor ama
        yukarıdaki sunucu değerleri daha küçük olduğu için geçerli sınır <?php echo $etkin; ?> MB.<br><br>
        Plesk &rsaquo; Web Siteleri ve Alan Adları &rsaquo; <em>PHP Ayarları</em> bölümünden
        <span class="mono">upload_max_filesize</span> ve <span class="mono">post_max_size</span>
        değerlerini yükseltip <strong>Uygula</strong>'ya basın. Alanın yanındaki liste
        "Varsayılan" ise "Özel değer" seçmeniz gerekebilir.
      </div>
    <?php else: ?>
      <p class="card-hint" style="margin:14px 0 0;">
        Sunucu yeterli; sınırı config.php belirliyor. Değiştirmek için
        <span class="mono">inc/config.php</span> içindeki
        <span class="mono">max_upload_mb</span> satırını düzenleyin —
        sunucu değerinden 1 MB aşağıda tutun.
      </p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>İletişim Butonları</h2>
    <p class="card-hint">
      Teklif sayfasında, iletişim kutusunun içinde görünür. İstediğin kadar ekleyebilir,
      silebilir, adlarını ve bağlantılarını değiştirebilirsin.
    </p>
    <div id="contactRows">
      <?php foreach ($contactButtons as $b) echo bm_contact_row($b, $buttonTypes); ?>
    </div>
    <button type="button" class="btn ghost" data-add="contact">+ Buton Ekle</button>

    <p class="card-hint" style="margin:16px 0 0;">
      <strong>Tür ne işe yarıyor?</strong><br>
      <em>Telefon</em> — numarayı yaz, tıklayınca telefon uygulaması açılır. Numarayı buradan değiştirirsin.<br>
      <em>WhatsApp</em> — sadece numara yaz (<span class="mono">905320000000</span>), bağlantıyı site kurar.<br>
      <em>Bağlantı</em> — Google Maps konumu, Instagram, ne istersen. Tam adresi yapıştır.<br>
      <em>Sadece yazı</em> — adres gibi tıklanması gerekmeyen bilgiler için.
    </p>
  </div>

  <div class="card">
    <h2>Sosyal Medya Hesapları</h2>
    <p class="card-hint">
      İletişim butonlarının hemen altında, yan yana küçük butonlar hâlinde görünür.
      Instagram, LinkedIn, Facebook, YouTube… istediğin kadar ekle, istediğini sil.
      Gerçek logoları koymak için her satıra PNG yükleyebilirsin.
      Hiç satır bırakmazsan bölüm sitede hiç görünmez.
    </p>
    <div id="socialRows">
      <?php foreach ($socialButtons as $b) echo bm_social_row($b, $buttonTypes); ?>
    </div>
    <button type="button" class="btn ghost" data-add="social">+ Hesap Ekle</button>
  </div>

  <?php
    // Kurumsal form ayarları: hangi alan görünsün, hangisi zorunlu olsun
    require_once BM_ROOT . '/inc/countries.php';
    $corpCfg   = bm_corporate_config($content);
    $corpAlan  = $corpCfg['fields'];
    $alanAdlar = [
        'company'   => ['Firma Ünvanı',         'Fatura unvanı — “Örnek Makina San. ve Tic. Ltd. Şti.”'],
        'taxoffice' => ['Vergi Dairesi',         'Faturanın kesileceği vergi dairesi'],
        'taxno'     => ['Vergi No / TCKN',       '10 hane vergi no veya 11 hane TC kimlik no'],
        'role'      => ['Görev / Departman',     '“Satınalma”, “İşletme Müdürü” gibi'],
        'country'   => ['Ülke',                  'Tüm dünya ülkeleri listelenir'],
        'city'      => ['İl / Şehir',            ''],
        'district'  => ['İlçe',                  ''],
        'address'   => ['Açık Adres',            'Fatura / teslim adresi'],
        'web'       => ['Web Sitesi',            'Firmanın internet adresi'],
        'qty'       => ['Tahmini Adet',          'Talep edilen parça adedi'],
        'deadline'  => ['İstenen Termin',        'Müşterinin istediği teslim tarihi'],
        'pono'      => ['Satınalma Sipariş No',  'Kurumların kendi sipariş numarası'],
    ];
    $durumlar = ['kapali' => 'Kapalı (görünmez)', 'istege' => 'İsteğe bağlı', 'zorunlu' => 'Zorunlu'];
  ?>
  <div class="card">
    <h2>Kurumsal Form</h2>
    <p class="card-hint">
      Teklif formunun üstünde <b>Bireysel</b> / <b>Kurumsal</b> sekmeleri çıkar.
      Kurumsal seçildiğinde aşağıdaki firma alanları görünür. Her alanı tek tek
      kapatabilir, isteğe bağlı ya da zorunlu yapabilirsiniz.
    </p>

    <label class="switch-line">
      <input type="checkbox" name="corp_enabled" value="1" <?php echo $corpCfg['enabled'] ? 'checked' : ''; ?>>
      Kurumsal sekmesi açık olsun
    </label>

    <div class="grid-2">
      <div>
        <label>Telefonda varsayılan ülke</label>
        <select name="corp_default_country">
          <?php foreach (bm_countries() as $u): ?>
            <option value="<?php echo e($u['iso']); ?>"<?php echo $u['iso'] === $corpCfg['default_country'] ? ' selected' : ''; ?>>
              <?php echo e($u['name']) . ' (+' . e($u['dial']) . ')'; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="card-hint">Form ilk açıldığında telefon kutusunda seçili gelen ülke.</p>
      </div>
      <div>
        <label>Aydınlatma metni sayfası (KVKK)</label>
        <input type="text" name="corp_kvkk_url" value="<?php echo e($corpCfg['kvkk_url']); ?>"
               placeholder="/kvkk-aydinlatma-metni">
        <p class="card-hint">
          Adres yazılırsa formda “Aydınlatma Metni’ni okudum” onay kutusu çıkar ve
          işaretlenmeden form gönderilemez. Boş bırakılırsa kutu hiç görünmez.
        </p>
      </div>
    </div>

    <label class="switch-line">
      <input type="checkbox" name="corp_consent_box" value="1" <?php echo $corpCfg['consent_box'] ? 'checked' : ''; ?>>
      “Kampanya ve duyurulardan haberdar olmak istiyorum” kutusu görünsün (isteğe bağlı)
    </label>

    <table class="mini-table">
      <thead>
        <tr><th>Alan</th><th style="width:210px;">Durum</th></tr>
      </thead>
      <tbody>
        <?php foreach ($alanAdlar as $anahtar => $bilgi): ?>
          <tr>
            <td>
              <b><?php echo e($bilgi[0]); ?></b>
              <?php if ($bilgi[1] !== ''): ?><br><span class="card-hint"><?php echo e($bilgi[1]); ?></span><?php endif; ?>
            </td>
            <td>
              <select name="corp_field[<?php echo e($anahtar); ?>]">
                <?php foreach ($durumlar as $deger => $etiket): ?>
                  <option value="<?php echo e($deger); ?>"<?php echo ($corpAlan[$anahtar] ?? 'kapali') === $deger ? ' selected' : ''; ?>>
                    <?php echo e($etiket); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="card-hint">
      Vergi no / TCKN alanı girildiğinde numaranın matematiği kontrol edilir.
      Tutmazsa müşteriye “hatalı görünüyor” uyarısı gösterilir ama gönderim
      engellenmez; teklif mailine “numara doğrulamadan geçmedi” notu düşer.
    </p>
  </div>

  <div class="card">
    <h2>Parça / Hizmet Türü Seçenekleri</h2>
    <p class="card-hint">Formdaki açılır listede çıkar. Boş satırlar kaydedilmez.</p>
    <div id="typeRows">
      <?php foreach ($partTypes as $t): ?>
        <div class="spec-row">
          <input type="text" name="part_type[]" value="<?php echo e($t); ?>" placeholder="Örn. CNC Torna Parçası">
          <button type="button" class="link-btn danger type-del">Sil</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" id="addType" class="btn ghost">+ Seçenek Ekle</button>
  </div>

  <?php /* Kaydet butonu kenar çubuğunda sabit duruyor — layout-top.php */ ?>
</form>

<script>
(function () {
  // ---- Parça / hizmet türü satırları ----
  var wrap = document.getElementById('typeRows');
  document.getElementById('addType').addEventListener('click', function () {
    var row = document.createElement('div');
    row.className = 'spec-row';
    row.innerHTML = '<input type="text" name="part_type[]" placeholder="Seçenek adı">' +
                    '<button type="button" class="link-btn danger type-del">Sil</button>';
    wrap.appendChild(row);
    row.querySelector('input').focus();
  });
  wrap.addEventListener('click', function (e) {
    if (!e.target.classList.contains('type-del')) return;
    var rows = wrap.querySelectorAll('.spec-row');
    if (rows.length === 1) {
      rows[0].querySelector('input').value = '';
    } else {
      e.target.closest('.spec-row').remove();
    }
    document.getElementById('teklifForm').dispatchEvent(new Event('change', { bubbles: true }));
  });

  // ---- İletişim ve sosyal medya butonları ----
  /* Boş satırların HTML'i PHP'de üretiliyor; kapsayıcı div JS'te oluşturulduğu
     için sadece içi alınıyor. İki liste aynı mantığı paylaşıyor. */
  var listeler = {
    contact: {
      wrap: 'contactRows',
      sinif: 'contact-row-item',
      sablon: <?php
        $bos = bm_contact_row([], $buttonTypes);
        echo json_encode(preg_replace('#^\s*<div class="repeat-row[^"]*">(.*)</div>\s*$#s', '$1', $bos),
                         JSON_UNESCAPED_UNICODE);
      ?>
    },
    social: {
      wrap: 'socialRows',
      sinif: 'social-row-item',
      sablon: <?php
        $bosSos = bm_social_row([], $buttonTypes);
        echo json_encode(preg_replace('#^\s*<div class="repeat-row[^"]*">(.*)</div>\s*$#s', '$1', $bosSos),
                         JSON_UNESCAPED_UNICODE);
      ?>
    }
  };

  Object.keys(listeler).forEach(function (ad) {
    var t = listeler[ad];
    var wrap = document.getElementById(t.wrap);
    var ekle = document.querySelector('[data-add="' + ad + '"]');
    if (!wrap || !ekle) return;

    ekle.addEventListener('click', function () {
      var row = document.createElement('div');
      row.className = 'repeat-row ' + t.sinif;
      row.innerHTML = t.sablon;
      wrap.appendChild(row);
      var ilk = row.querySelector('input:not(.icon-up-path)');
      if (ilk) ilk.focus();
    });

    // Silme dinleyicisi kapsayıcıda: satırlar sonradan da ekleniyor
    wrap.addEventListener('click', function (e) {
      if (!e.target.classList.contains('row-del')) return;
      var rows = wrap.querySelectorAll('.' + t.sinif);
      if (rows.length === 1) {
        // Tek satır kalmışsa silmek yerine boşalt — form yapısı bozulmasın
        rows[0].querySelectorAll('input').forEach(function (i) { i.value = ''; });
        rows[0].querySelectorAll('[data-icon-up]').forEach(function (box) {
          box.classList.add('is-empty');
          var img = box.querySelector('.icon-up-prev img');
          if (img) img.removeAttribute('src');
        });
      } else {
        e.target.closest('.' + t.sinif).remove();
      }
      document.getElementById('teklifForm').dispatchEvent(new Event('change', { bubbles: true }));
    });
  });
})();
</script>
<?php require __DIR__ . '/layout-bottom.php'; ?>
