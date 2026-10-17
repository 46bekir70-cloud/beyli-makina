<?php
require_once __DIR__ . '/config.php';
// Güvenlik: yetki kontrolü EN BAŞTA. Eskiden bu kontrol layout-top.php
// içinde, yani POST işlendikten SONRA devreye giriyordu; oturumu olmayan biri
// form gönderip içeriği değiştirebilirdi (çıktı görmese bile kayıt olurdu).
bm_admin_require_login();
$content = bm_load_content();
$bm_admin_active = 'anasayfa';
$bm_admin_title = 'Ana Sayfa İçeriği';
$bm_admin_save_form = 'anasayfaForm';
$bm_admin_preview = '/teknikel/onizleme.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        // ---- Hero ----
        $content['hero']['tag'] = trim($_POST['hero_tag'] ?? '');
        $content['hero']['tag_link'] = trim($_POST['hero_tag_link'] ?? '');
        $content['hero']['title_before'] = trim($_POST['hero_title_before'] ?? '');
        $content['hero']['title_highlight'] = trim($_POST['hero_title_highlight'] ?? '');
        $content['hero']['title_after'] = trim($_POST['hero_title_after'] ?? '');
        $content['hero']['subtitle'] = trim($_POST['hero_subtitle'] ?? '');
        $content['hero']['cta1_text'] = trim($_POST['hero_cta1_text'] ?? '');
        $content['hero']['cta1_link'] = trim($_POST['hero_cta1_link'] ?? '');
        $content['hero']['cta2_text'] = trim($_POST['hero_cta2_text'] ?? '');
        $content['hero']['cta2_link'] = trim($_POST['hero_cta2_link'] ?? '');
        $stats = [];
        foreach (($_POST['hero_stat_num'] ?? []) as $i => $num) {
            $num = trim($num); $label = trim($_POST['hero_stat_label'][$i] ?? '');
            if ($num !== '' || $label !== '') $stats[] = ['num' => $num, 'label' => $label];
        }
        $content['hero']['stats'] = $stats;

        // ---- Hero slayt gösterisi ----
        // Anahtar bir kez yazıldıktan sonra kurulumla gelen örnek görseller
        // devreye girmez; liste boş bırakılırsa slayt kapanır.
        $slaytlar = [];
        foreach ((array)($_POST['hero_slide_image'] ?? []) as $i => $img) {
            $img = trim((string)$img);
            if ($img === '') continue;
            $slaytlar[] = [
                'image' => $img,
                'focus' => trim((string)($_POST['hero_slide_focus'][$i] ?? '')),
            ];
        }
        $content['hero']['slides'] = $slaytlar;
        $content['hero']['slide_interval'] = bm_num_range($_POST['hero_slide_interval'] ?? null, 2, 20, 6);

        // ---- Hakkımızda Özeti ----
        $content['about_teaser']['tag'] = trim($_POST['about_tag'] ?? '');
        $content['about_teaser']['title'] = trim($_POST['about_title'] ?? '');
        $content['about_teaser']['html'] = $_POST['about_html'] ?? '';
        $content['about_teaser']['visual_emoji'] = trim($_POST['about_visual_emoji'] ?? '');
        $content['about_teaser']['visual_image'] = trim($_POST['about_visual_image'] ?? '');
        $content['about_teaser']['link_text'] = trim($_POST['about_link_text'] ?? '');
        $content['about_teaser']['link_target'] = trim($_POST['about_link_target'] ?? '');
        $aStats = [];
        foreach (($_POST['about_stat_num'] ?? []) as $i => $num) {
            $num = trim($num); $label = trim($_POST['about_stat_label'][$i] ?? '');
            if ($num !== '' || $label !== '') $aStats[] = ['num' => $num, 'label' => $label];
        }
        $content['about_teaser']['stats'] = $aStats;

        // NOT: Hizmetler artık burada değil, "Hizmetler" bölümünden yönetiliyor
        // (admin/hizmetler.php). Buradan yazmaya devam etseydik bu form
        // kaydedildiğinde hizmet alanları POST'ta olmadığı için tüm hizmetler
        // silinirdi.
        //
        // NOT: Ürün kataloğu da veritabanında ve "Ürünler" bölümünde.

        // ---- Sipariş bölümü metni ----
        $content['order_section']['tag'] = trim($_POST['order_tag'] ?? '');
        $content['order_section']['title'] = trim($_POST['order_title'] ?? '');
        $content['order_section']['text'] = trim($_POST['order_text'] ?? '');

        bm_save_content($content);
        $message = 'ok|Ana sayfa içeriği kaydedildi.';
        $content = bm_load_content();
    }
}
[$msgType, $msgText] = array_pad(explode('|', $message, 2), 2, '');

require __DIR__ . '/layout-top.php';
$hero = $content['hero']; $about = $content['about_teaser'];
$pages = $content['pages'] ?? [];
?>
<div class="admin-topline">
  <div>
    <h1>Ana Sayfa İçeriği</h1>
    <div class="sub">Hero, hakkımızda özeti, hizmetler ve ürün kataloğu</div>
  </div>
  <a href="/teknikel/logout.php" class="logout-link">Çıkış Yap</a>
</div>

<?php if ($msgText): ?><div class="flash <?php echo $msgType; ?>"><?php echo e($msgText); ?></div><?php endif; ?>

<form method="post" id="anasayfaForm">
  <input type="hidden" name="onizleme_turu" value="anasayfa">
<input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">

<div class="card">
  <h2>Hero (Üst Banner)</h2>
  <div class="grid-2">
    <div class="field"><label>Üst etiket (küçük yazı)</label><input type="text" name="hero_tag" value="<?php echo e($hero['tag'] ?? ''); ?>"></div>
    <div class="field">
      <label>Üst etiket bağlantısı (opsiyonel)</label>
      <input type="text" name="hero_tag_link" value="<?php echo e($hero['tag_link'] ?? ''); ?>" placeholder="https://maps.app.goo.gl/...">
      <div class="hint">Doldurursan etiket tıklanabilir buton olur. Dükkan konumu için Google Maps bağlantısını yapıştır.</div>
    </div>
  </div>
  <div class="grid-3">
    <div class="field"><label>Başlık — önce</label><input type="text" name="hero_title_before" value="<?php echo e($hero['title_before'] ?? ''); ?>"></div>
    <div class="field"><label>Başlık — vurgulu kısım (kırmızı)</label><input type="text" name="hero_title_highlight" value="<?php echo e($hero['title_highlight'] ?? ''); ?>"></div>
    <div class="field"><label>Başlık — sonra</label><input type="text" name="hero_title_after" value="<?php echo e($hero['title_after'] ?? ''); ?>"></div>
  </div>
  <div class="field"><label>Alt metin</label><textarea name="hero_subtitle"><?php echo e($hero['subtitle'] ?? ''); ?></textarea></div>
  <div class="grid-2">
    <div class="field"><label>1. Buton Yazısı</label><input type="text" name="hero_cta1_text" value="<?php echo e($hero['cta1_text'] ?? ''); ?>"></div>
    <div class="field"><label>1. Buton Linki</label><input type="text" name="hero_cta1_link" value="<?php echo e($hero['cta1_link'] ?? ''); ?>"></div>
    <div class="field"><label>2. Buton Yazısı</label><input type="text" name="hero_cta2_text" value="<?php echo e($hero['cta2_text'] ?? ''); ?>"></div>
    <div class="field"><label>2. Buton Linki</label><input type="text" name="hero_cta2_link" value="<?php echo e($hero['cta2_link'] ?? ''); ?>"></div>
  </div>
  <label>İstatistikler</label>
  <div id="heroStats">
    <?php foreach (($hero['stats'] ?? []) as $s): ?>
      <div class="repeat-row">
        <button type="button" class="remove-row" onclick="this.closest('.repeat-row').remove()">✕</button>
        <div class="grid-2">
          <div class="field"><label>Sayı</label><input type="text" name="hero_stat_num[]" value="<?php echo e($s['num']); ?>"></div>
          <div class="field"><label>Etiket</label><input type="text" name="hero_stat_label[]" value="<?php echo e($s['label']); ?>"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn gray small" onclick="addStatRow('heroStats','hero_stat_num[]','hero_stat_label[]')">+ İstatistik Ekle</button>
</div>

<?php
  // Panelde hiç kaydedilmemişse kurulumla gelen örnekler gösterilir; kullanıcı
  // kaydettiği anda liste onun girdiği hâle sabitlenir.
  $slaytlar = bm_hero_slides($content);
  if (!$slaytlar) $slaytlar = [['image' => '', 'focus' => '']];
  $ornekMi = !array_key_exists('slides', $content['hero'] ?? []);
?>
<div class="card">
  <h2>🎞️ Ana Sayfa Slayt Gösterisi</h2>
  <p class="card-hint">
    Ana sayfanın en üstündeki lacivert alanın arkasında kayan görseller. Alt tarafta
    nokta belirteçler çıkar, ziyaretçi noktalara basarak da gezebilir.
    Geniş ve yatay fotoğraflar en iyi durur (ör. 1600×900). Görsellerin üstüne,
    yazının okunması için lacivert bir filtre biniyor.
  </p>
  <?php if ($ornekMi): ?>
    <div class="flash ok" style="margin-bottom:18px;">
      Şu an <strong>kurulumla gelen 4 örnek görsel</strong> gösteriliyor. Bunlar gerçek
      fotoğraf değil, yer tutucu desenler — atölyenden çektiğin fotoğrafları yükleyip
      kaydettiğinde örnekler tamamen devreden çıkar.
    </div>
  <?php endif; ?>

  <div class="field field-narrow">
    <label for="hero_slide_interval">Geçiş Süresi (saniye)</label>
    <input type="number" id="hero_slide_interval" name="hero_slide_interval" min="2" max="20"
           value="<?php echo (int)bm_hero_slide_interval($content); ?>">
  </div>

  <div id="heroSlides">
    <?php foreach ($slaytlar as $i => $s): ?>
      <div class="repeat-row slide-row">
        <button type="button" class="remove-row row-del" title="Slaytı sil">✕</button>
        <?php echo bm_admin_icon_field('hero_slide_image[]', $s['image'], [
          'label' => ((int)$i + 1) . '. Görsel',
          'big'   => true,
          'id'    => 'hero_slide_image_' . (int)$i,
        ]); ?>
        <input type="hidden" class="slide-focus" name="hero_slide_focus[]"
               id="hero_slide_focus_<?php echo (int)$i; ?>" value="<?php echo e($s['focus']); ?>">
        <div class="focal-picker"
             data-focal-input="hero_slide_focus_<?php echo (int)$i; ?>"
             data-focal-src-input="hero_slide_image_<?php echo (int)$i; ?>"
             data-focal-ratio="16/7"
             data-focal-hint="Görselin üstüne tıkla — o nokta slaytta ortada kalır."></div>
      </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn ghost" id="addSlide">+ Görsel Ekle</button>
  <p class="card-hint" style="margin:14px 0 0;">
    Tek görsel bırakırsan kaymaz, sabit arka plan olur. Hepsini silersen eski
    lacivert degrade zemin geri gelir.
  </p>
</div>

<div class="card">
  <h2>Hakkımızda Özeti (Ana Sayfada)</h2>
  <div class="grid-2">
    <div class="field"><label>Üst Etiket</label><input type="text" name="about_tag" value="<?php echo e($about['tag'] ?? ''); ?>"></div>
    <div class="field"><label>Başlık</label><input type="text" name="about_title" value="<?php echo e($about['title'] ?? ''); ?>"></div>
  </div>
  <div class="field">
    <label>Metin</label>
    <div id="aboutEditor" style="background:#fff;border:1px solid var(--line);border-radius:8px;min-height:160px;"><?php echo $about['html'] ?? ''; ?></div>
    <?php /* JS çalışmazsa metin silinmesin — bkz. sayfa-duzenle.php */ ?>
    <textarea name="about_html" id="about_html_field" style="display:none;"><?php echo e($about['html'] ?? ''); ?></textarea>
  </div>
  <div class="grid-2">
    <div class="field"><label>Görsel yoksa gösterilecek emoji</label><input type="text" name="about_visual_emoji" value="<?php echo e($about['visual_emoji'] ?? ''); ?>"></div>
    <?php echo bm_admin_icon_field('about_visual_image', $about['visual_image'] ?? '', [
      'label' => 'Görsel',
      'big'   => true,
      'id'    => 'about_visual_image',
      'hint'  => 'Boş bırakırsan yandaki emoji gösterilir.',
    ]); ?>
  </div>
  <label>İstatistikler</label>
  <div id="aboutStats">
    <?php foreach (($about['stats'] ?? []) as $s): ?>
      <div class="repeat-row">
        <button type="button" class="remove-row" onclick="this.closest('.repeat-row').remove()">✕</button>
        <div class="grid-2">
          <div class="field"><label>Sayı</label><input type="text" name="about_stat_num[]" value="<?php echo e($s['num']); ?>"></div>
          <div class="field"><label>Etiket</label><input type="text" name="about_stat_label[]" value="<?php echo e($s['label']); ?>"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn gray small" onclick="addStatRow('aboutStats','about_stat_num[]','about_stat_label[]')">+ İstatistik Ekle</button>
  <div class="grid-2" style="margin-top:16px;">
    <div class="field"><label>"Devamını Oku" Buton Yazısı</label><input type="text" name="about_link_text" value="<?php echo e($about['link_text'] ?? ''); ?>"></div>
    <div class="field">
      <label>"Devamını Oku" Hangi Sayfaya Gitsin</label>
      <select name="about_link_target">
        <option value="">— Buton gösterme —</option>
        <?php foreach ($pages as $p): ?>
          <option value="<?php echo e($p['slug']); ?>" <?php echo ($about['link_target'] ?? '') === $p['slug'] ? 'selected' : ''; ?>><?php echo e($p['title']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

<div class="card">
  <h2>Hizmetlerimiz Kartları</h2>
  <p class="hint" style="margin-bottom:16px;">
    Hizmetler artık burada değil, <strong>Hizmetler</strong> bölümünde yönetiliyor — her hizmetin
    kendi tanıtım sayfası, makine listesi ve öne çıkan sayıları olduğu için ayrı bir ekrana taşındı.
  </p>
  <a href="/teknikel/hizmetler.php" class="btn">Hizmetlere Git →</a>
</div>

<div class="card">
  <h2>Ürün Kataloğu</h2>
  <p class="hint" style="margin-bottom:16px;">
    Ürünler artık burada değil, <strong>Ürünler</strong> bölümünde yönetiliyor — binlerce kalemi
    rahatça yönetebilmek için veritabanına taşındı. Ana sayfada görünecek parçaları,
    ürün düzenleme ekranındaki <em>“Ana sayfa vitrininde göster”</em> kutusuyla seçebilirsin.
  </p>
  <a href="/teknikel/urunler.php" class="btn">Ürünlere Git →</a>
</div>

<div class="card">
  <h2>Sipariş Bölümü Metni</h2>
  <div class="grid-2">
    <div class="field"><label>Üst Etiket</label><input type="text" name="order_tag" value="<?php echo e($content['order_section']['tag'] ?? ''); ?>"></div>
    <div class="field"><label>Başlık</label><input type="text" name="order_title" value="<?php echo e($content['order_section']['title'] ?? ''); ?>"></div>
  </div>
  <div class="field"><label>Açıklama</label><textarea name="order_text"><?php echo e($content['order_section']['text'] ?? ''); ?></textarea></div>
</div>

<?php /* Kaydet butonu kenar çubuğunda sabit duruyor — layout-top.php */ ?>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
<script src="/teknikel/assets/quill-upload.js?v=<?php echo e(bm_asset_version('teknikel/assets/quill-upload.js')); ?>"></script>
<script>
window.BM_CSRF = <?php echo json_encode(bm_csrf_token()); ?>;

const aboutQuill = new Quill('#aboutEditor', {
  theme: 'snow',
  modules: { toolbar: [['bold','italic','underline'],[{list:'ordered'},{list:'bullet'}],['link','image'],['clean']] }
});
bmAttachImageHandler(aboutQuill);

// Zengin metni gizli alana yaz — hem kaydetmede hem önizlemede gerekli.
// preview.js bu fonksiyonu istek göndermeden hemen önce çağırıyor.
window.bmSyncEditors = function () {
  document.getElementById('about_html_field').value = aboutQuill.root.innerHTML;
};
document.getElementById('anasayfaForm').addEventListener('submit', window.bmSyncEditors);

// Quill 'change' olayı yaymadığı için önizlemeyi elle tetikliyoruz.
aboutQuill.on('text-change', function () {
  clearTimeout(window.__bmQuillTimer);
  window.__bmQuillTimer = setTimeout(function () {
    document.getElementById('anasayfaForm').dispatchEvent(new Event('change', { bubbles: true }));
  }, 700);
});

function addStatRow(containerId, nameA, nameB) {
  const c = document.getElementById(containerId);
  const div = document.createElement('div');
  div.className = 'repeat-row';
  div.innerHTML = '<button type="button" class="remove-row" onclick="this.closest(\'.repeat-row\').remove()">✕</button>' +
    '<div class="grid-2"><div class="field"><label>Sayı</label><input type="text" name="' + nameA + '"></div>' +
    '<div class="field"><label>Etiket</label><input type="text" name="' + nameB + '"></div></div>';
  c.appendChild(div);
}

/* ---- Slayt satırları ----
   Boş satırın HTML'i PHP'de üretiliyor (tek kaynak), sadece id'ler her satırda
   benzersiz olacak şekilde değiştiriliyor: odak noktası seçicisi görsel alanını
   id ile buluyor. */
(function () {
  var wrap = document.getElementById('heroSlides');
  var ekle = document.getElementById('addSlide');
  if (!wrap || !ekle) return;

  var sablon = <?php
    $bos = bm_admin_icon_field('hero_slide_image[]', '', [
      'label' => 'Görsel', 'big' => true, 'id' => 'hero_slide_image_@N@',
    ]);
    $bos .= '<input type="hidden" class="slide-focus" name="hero_slide_focus[]" id="hero_slide_focus_@N@" value="">'
          . '<div class="focal-picker" data-focal-input="hero_slide_focus_@N@"'
          . ' data-focal-src-input="hero_slide_image_@N@" data-focal-ratio="16/7"'
          . ' data-focal-hint="Görselin üstüne tıkla — o nokta slaytta ortada kalır."></div>';
    echo json_encode($bos, JSON_UNESCAPED_UNICODE);
  ?>;

  var sayac = wrap.querySelectorAll('.slide-row').length;

  ekle.addEventListener('click', function () {
    var n = 'y' + (sayac++) + '_' + Date.now(); // çakışmayan id eki
    var row = document.createElement('div');
    row.className = 'repeat-row slide-row';
    row.innerHTML = '<button type="button" class="remove-row row-del" title="Slaytı sil">✕</button>'
                  + sablon.replace(/@N@/g, n);
    wrap.appendChild(row);
    // Yeni satırdaki odak seçicisini kur (fonksiyon focal.js içinde global)
    if (window.bmInitFocalPickers) bmInitFocalPickers();
    row.scrollIntoView({ block: 'center', behavior: 'smooth' });
  });

  wrap.addEventListener('click', function (e) {
    if (!e.target.classList.contains('row-del')) return;
    var rows = wrap.querySelectorAll('.slide-row');
    // Tek satır kalmışsa silmek yerine boşalt: form yapısı bozulmasın
    if (rows.length === 1) {
      rows[0].querySelectorAll('input').forEach(function (i) { i.value = ''; });
      rows[0].querySelectorAll('[data-icon-up]').forEach(function (b) {
        b.classList.add('is-empty');
        var img = b.querySelector('.icon-up-prev img');
        if (img) img.removeAttribute('src');
      });
    } else {
      e.target.closest('.slide-row').remove();
    }
  });
})();
</script>
<?php require __DIR__ . '/layout-bottom.php'; ?>
