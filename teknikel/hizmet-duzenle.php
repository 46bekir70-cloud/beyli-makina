<?php
/**
 * Tek hizmetin düzenlenmesi: tanıtım metni, öne çıkan sayılar ve makine parkuru.
 */
require_once __DIR__ . '/config.php';

bm_admin_require_login();

$content = bm_load_content();
$slug = trim($_GET['slug'] ?? $_POST['slug'] ?? '');
$service = $slug !== '' ? bm_find_service($content, $slug) : null;

if (!$service) {
    header('Location: /teknikel/hizmetler.php');
    exit;
}

$bm_admin_active = 'hizmetler';
$bm_admin_title = 'Düzenle: ' . $service['title'];
$bm_admin_save_form = 'hizmetForm';
$bm_admin_preview = '/teknikel/onizleme.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            $message = 'err|Hizmet adı boş olamaz.';
        } else {
            foreach ($content['services'] as &$s) {
                if (($s['slug'] ?? '') !== $slug) continue;

                $s['title']         = $title;
                $s['desc']          = trim($_POST['desc'] ?? '');
                $s['card_image']    = trim($_POST['card_image'] ?? '');
                $s['card_focus']    = trim($_POST['card_focus'] ?? '');
                $s['card_blur']     = bm_num_range($_POST['card_blur'] ?? null, 0, 20, BM_CARD_BLUR_DEFAULT);
                $s['card_bright']   = bm_num_range($_POST['card_bright'] ?? null, 20, 130, BM_CARD_BRIGHT_DEFAULT);
                $s['hero_tag']      = trim($_POST['hero_tag'] ?? '');
                $s['hero_subtitle'] = trim($_POST['hero_subtitle'] ?? '');
                $s['hero_image']    = trim($_POST['hero_image'] ?? '');
                $s['hero_focus']    = trim($_POST['hero_focus'] ?? '');
                $s['image']         = trim($_POST['image'] ?? '');
                $s['html']          = $_POST['html'] ?? '';
                $s['has_page']      = isset($_POST['has_page']);
                $s['sort_order']    = (int)($_POST['sort_order'] ?? 0);

                // Öne çıkan sayılar
                $stats = [];
                foreach ((array)($_POST['stat_num'] ?? []) as $i => $num) {
                    $num = trim((string)$num);
                    $label = trim((string)($_POST['stat_label'][$i] ?? ''));
                    if ($num === '' && $label === '') continue;
                    $stats[] = ['num' => $num, 'label' => $label];
                }
                $s['stats'] = $stats;

                // Makineler
                $machines = [];
                foreach ((array)($_POST['mac_name'] ?? []) as $i => $name) {
                    $name = trim((string)$name);
                    if ($name === '') continue; // adsız makine kaydedilmez
                    $machines[] = [
                        'name'  => $name,
                        'emoji' => trim((string)($_POST['mac_emoji'][$i] ?? '')),
                        'image' => trim((string)($_POST['mac_image'][$i] ?? '')),
                        'desc'  => trim((string)($_POST['mac_desc'][$i] ?? '')),
                        'specs' => bm_parse_spec_lines($_POST['mac_specs'][$i] ?? ''),
                    ];
                }
                $s['machines'] = $machines;
                break;
            }
            unset($s);
            bm_save_content($content);
            $message = 'ok|Hizmet kaydedildi.';
            $content = bm_load_content();
            $service = bm_find_service($content, $slug);
        }
    }
}

if (isset($_GET['yeni'])) {
    $message = 'ok|Hizmet oluşturuldu. Şimdi içeriğini doldurabilirsin.';
}

// En az bir boş satır göster
$statRows = (array)($service['stats'] ?? []);
if (!$statRows) $statRows = [['num' => '', 'label' => '']];
$machineRows = (array)($service['machines'] ?? []);
if (!$machineRows) $machineRows = [['name' => '', 'emoji' => '', 'image' => '', 'desc' => '', 'specs' => []]];

// Kart görseli ayarları — hiç kaydedilmemişse okunaklı bir başlangıç değeri
$cardBlur   = bm_num_range($service['card_blur'] ?? null, 0, 20, BM_CARD_BLUR_DEFAULT);
$cardBright = bm_num_range($service['card_bright'] ?? null, 20, 130, BM_CARD_BRIGHT_DEFAULT);

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1><?php echo e($service['title']); ?></h1>
    <div class="sub">
      Adres: <code>/hizmet/<?php echo e($service['slug']); ?></code>
      <?php if (!empty($service['has_page'])): ?>
        — <a href="/hizmet/<?php echo e($service['slug']); ?>" target="_blank" style="color:var(--blue);">canlı sayfayı aç ↗</a>
      <?php endif; ?>
    </div>
  </div>
  <a href="/teknikel/hizmetler.php" class="logout-link">← Hizmet Listesi</a>
</div>

<?php if ($message): [$type, $text] = explode('|', $message, 2); ?>
  <div class="flash <?php echo $type === 'ok' ? 'ok' : 'err'; ?>"><?php echo e($text); ?></div>
<?php endif; ?>

<form method="post" id="hizmetForm">
  <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
  <input type="hidden" name="onizleme_turu" value="hizmet">
  <input type="hidden" name="slug" value="<?php echo e($service['slug']); ?>">

  <div class="card">
    <h2>Kart Bilgileri (ana sayfada görünen)</h2>
    <div class="field-row">
      <div class="field">
        <label for="title">Hizmet Adı *</label>
        <input type="text" id="title" name="title" value="<?php echo e($service['title']); ?>" required>
      </div>
      <div class="field field-narrow">
        <label for="sort_order">Sıra</label>
        <input type="number" id="sort_order" name="sort_order" value="<?php echo (int)($service['sort_order'] ?? 0); ?>">
      </div>
    </div>
    <div class="field">
      <label for="desc">Kart Açıklaması</label>
      <textarea id="desc" name="desc" rows="2"><?php echo e($service['desc'] ?? ''); ?></textarea>
      <div class="hint">Ana sayfadaki hizmet kartında görünen kısa metin.</div>
    </div>
    <label class="check">
      <input type="checkbox" name="has_page" <?php echo !empty($service['has_page']) ? 'checked' : ''; ?>>
      Detay sayfası yayında (kart tıklanabilir olur, menüde görünür)
    </label>
  </div>

  <div class="card">
    <h2>🖼️ Kart Görseli</h2>
    <p class="card-hint">
      Ana sayfadaki hizmet kartının arkasına konur — kart, sayfa başlığı bandının
      küçük hâli gibi görünür: arkada fotoğraf, önde yazı. Boş bırakırsan kart
      beyaz zeminde kalır. Kartın boyutu her iki durumda da aynıdır.
    </p>
    <?php echo bm_admin_icon_field('card_image', $service['card_image'] ?? '', [
      'label'     => 'Görsel Adresi',
      'big'       => true,
      // id şart: aşağıdaki ortalama aracı görseli bu id ile buluyor.
      'id'        => 'card_image',
      'name_from' => 'title', // yüklenen dosya hizmetin adıyla kaydedilsin
      'hint'      => 'Yükle ile bilgisayarından ekle, Siteden Seç ile kütüphaneden seç.',
    ]); ?>

    <input type="hidden" id="card_focus" name="card_focus" value="<?php echo e($service['card_focus'] ?? ''); ?>">
    <div class="focal-picker"
         data-focal-input="card_focus"
         data-focal-src-input="card_image"
         data-focal-ratio="16/9"
         data-focal-hint="Görselin üstüne tıkla — o nokta kartın ortasında kalır."></div>

    <h3 class="sub-head">Yazının Okunurluğu</h3>
    <p class="card-hint">
      Fotoğrafın deseni yazıyı yutuyorsa bu iki çubukla dengele: bulanıklık
      arkadaki detayı siler, parlaklık ise fotoğrafı karartır. Aşağıdaki
      önizleme kartın sitedeki hâlini birebir gösterir.
    </p>
    <div class="tuner-rows">
      <div class="tuner">
        <label for="card_blur">Bulanıklık <span class="tuner-val" data-tuner-val="card_blur"></span></label>
        <input type="range" id="card_blur" name="card_blur" min="0" max="20" step="1"
               value="<?php echo (int)$cardBlur; ?>">
      </div>
      <div class="tuner">
        <label for="card_bright">Parlaklık <span class="tuner-val" data-tuner-val="card_bright"></span></label>
        <input type="range" id="card_bright" name="card_bright" min="20" max="130" step="5"
               value="<?php echo (int)$cardBright; ?>">
      </div>
    </div>

    <div class="card-tuner"
         data-tuner-src="card_image"
         data-tuner-focus="card_focus"
         data-tuner-blur="card_blur"
         data-tuner-bright="card_bright"
         data-tuner-title="title"
         data-tuner-desc="desc"></div>
  </div>

  <div class="card">
    <h2>Sayfa Üstü</h2>
    <div class="field-row">
      <div class="field">
        <label for="hero_tag">Üst Etiket</label>
        <input type="text" id="hero_tag" name="hero_tag" value="<?php echo e($service['hero_tag'] ?? ''); ?>"
               placeholder="Örn. Hizmetlerimiz">
      </div>
    </div>
    <div class="field">
      <label for="hero_subtitle">Sayfa Üstü Açıklama</label>
      <textarea id="hero_subtitle" name="hero_subtitle" rows="2"><?php echo e($service['hero_subtitle'] ?? ''); ?></textarea>
    </div>
  </div>

  <?php echo bm_admin_hero_card('hero_image', 'hero_focus', $service['hero_image'] ?? '', $service['hero_focus'] ?? ''); ?>

  <div class="card">
    <h2>Öne Çıkan Sayılar</h2>
    <p class="card-hint">
      Personel sayısı, tezgah adedi, aylık kapasite, tolerans gibi bilgiler.
      Sayfanın üstünde kutucuklar hâlinde görünür. Boş satırlar kaydedilmez.
    </p>
    <div id="statRows">
      <?php foreach ($statRows as $st): ?>
        <div class="spec-row">
          <input type="text" name="stat_num[]" value="<?php echo e($st['num'] ?? ''); ?>" placeholder="12">
          <input type="text" name="stat_label[]" value="<?php echo e($st['label'] ?? ''); ?>" placeholder="Uzman Personel">
          <button type="button" class="link-btn danger row-del">Sil</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn ghost" data-add="stat">+ Sayı Ekle</button>
  </div>

  <div class="card">
    <h2>Tanıtım Metni</h2>
    <div id="svcEditor" style="background:#fff;border:1px solid var(--line);border-radius:8px;min-height:220px;"><?php echo $service['html'] ?? ''; ?></div>
    <?php /* JS çalışmazsa içerik silinmesin — bkz. sayfa-duzenle.php */ ?>
    <textarea name="html" id="html_field" style="display:none;"><?php echo e($service['html'] ?? ''); ?></textarea>
    <div style="margin-top:16px;">
      <?php echo bm_admin_icon_field('image', $service['image'] ?? '', [
        'label'     => 'Sayfa Görseli (opsiyonel)',
        'big'       => true,
        'id'        => 'image',
        'name_from' => 'title',
        'hint'      => 'Tanıtım metninin yanında görünür.',
      ]); ?>
    </div>
  </div>

  <div class="card">
    <h2>Makineler</h2>
    <p class="card-hint">
      Bu hizmette kullandığın makineler. Teknik özellikleri her satıra bir tane olacak şekilde
      <code>Özellik: Değer</code> biçiminde yaz — örn. <code>Ayna Kapasitesi: Ø320 mm</code>.
      Adı boş bırakılan makine kaydedilmez.
    </p>
    <div id="machineRows">
      <?php foreach ($machineRows as $m): ?>
        <div class="repeat-row machine-row">
          <button type="button" class="remove-row row-del" title="Makineyi sil">✕</button>
          <div class="field-row">
            <div class="field">
              <label>Makine Adı</label>
              <input type="text" name="mac_name[]" value="<?php echo e($m['name'] ?? ''); ?>"
                     placeholder="Örn. Goodway GS-260 CNC Torna">
            </div>
            <div class="field field-narrow">
              <label>Emoji (görsel yoksa)</label>
              <input type="text" name="mac_emoji[]" value="<?php echo e($m['emoji'] ?? ''); ?>" maxlength="8" placeholder="⚙️">
            </div>
          </div>
          <?php echo bm_admin_icon_field('mac_image[]', $m['image'] ?? '', [
            'label' => 'Görsel (opsiyonel)',
            'big'   => true,
            'hint'  => 'Boş bırakırsan yukarıdaki emoji kullanılır.',
          ]); ?>
          <div class="field">
            <label>Kısa Açıklama</label>
            <textarea name="mac_desc[]" rows="2"><?php echo e($m['desc'] ?? ''); ?></textarea>
          </div>
          <div class="field">
            <label>Teknik Özellikler</label>
            <textarea name="mac_specs[]" rows="5" placeholder="Ayna Kapasitesi: Ø320 mm&#10;Punta Mesafesi: 750 mm&#10;Devir: 4500 rpm&#10;Eksen: 3"><?php echo e(bm_spec_lines_to_text($m['specs'] ?? [])); ?></textarea>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn ghost" data-add="machine">+ Makine Ekle</button>
  </div>

  <div class="form-actions">
    <!-- Kaydet kenar çubuğunda da sabit duruyor; formun sonunda yalnızca geri
         götüren bir buton kalmasın diye burada da bulunuyor. -->
    <button type="submit" class="btn">Kaydet</button>
    <a href="/teknikel/hizmetler.php" class="btn ghost">Vazgeç</a>
  </div>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
<script src="/teknikel/assets/quill-upload.js?v=<?php echo e(bm_asset_version('teknikel/assets/quill-upload.js')); ?>"></script>
<script>
window.BM_CSRF = <?php echo json_encode(bm_csrf_token()); ?>;

const svcQuill = new Quill('#svcEditor', {
  theme: 'snow',
  modules: {
    toolbar: [
      [{ header: [2, 3, false] }],
      ['bold', 'italic', 'underline'],
      [{ list: 'ordered' }, { list: 'bullet' }],
      ['blockquote', 'link', 'image'],
      ['clean']
    ]
  }
});
bmAttachImageHandler(svcQuill);

window.bmSyncEditors = function () {
  document.getElementById('html_field').value = svcQuill.root.innerHTML;
};
document.getElementById('hizmetForm').addEventListener('submit', window.bmSyncEditors);

// Quill 'change' olayı yaymadığı için önizlemeyi elle tetikliyoruz
svcQuill.on('text-change', function () {
  clearTimeout(window.__bmQuillTimer);
  window.__bmQuillTimer = setTimeout(function () {
    document.getElementById('hizmetForm').dispatchEvent(new Event('change', { bubbles: true }));
  }, 700);
});

// ---- Satır ekle / sil ----
const statTemplate =
  '<input type="text" name="stat_num[]" placeholder="12">' +
  '<input type="text" name="stat_label[]" placeholder="Uzman Personel">' +
  '<button type="button" class="link-btn danger row-del">Sil</button>';

const machineTemplate =
  '<button type="button" class="remove-row row-del" title="Makineyi sil">✕</button>' +
  '<div class="field-row">' +
    '<div class="field"><label>Makine Adı</label><input type="text" name="mac_name[]" placeholder="Örn. Goodway GS-260 CNC Torna"></div>' +
    '<div class="field field-narrow"><label>Emoji (görsel yoksa)</label><input type="text" name="mac_emoji[]" maxlength="8" placeholder="⚙️"></div>' +
  '</div>' +
  <?php /* Yeni makine satırı da hazır görsel alanıyla gelsin: boş hâli PHP'de
           üretilip JS'e gömülüyor (bkz. anasayfa.php slayt satırı). */ ?>
  <?php echo json_encode(bm_admin_icon_field('mac_image[]', '', [
    'label' => 'Görsel (opsiyonel)',
    'big'   => true,
    'hint'  => 'Boş bırakırsan yukarıdaki emoji kullanılır.',
  ])); ?> +
  '<div class="field"><label>Kısa Açıklama</label><textarea name="mac_desc[]" rows="2"></textarea></div>' +
  '<div class="field"><label>Teknik Özellikler</label><textarea name="mac_specs[]" rows="5" placeholder="Ayna Kapasitesi: Ø320 mm&#10;Devir: 4500 rpm"></textarea></div>';

document.querySelectorAll('[data-add]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var isStat = btn.dataset.add === 'stat';
    var wrap = document.getElementById(isStat ? 'statRows' : 'machineRows');
    var row = document.createElement('div');
    row.className = isStat ? 'spec-row' : 'repeat-row machine-row';
    row.innerHTML = isStat ? statTemplate : machineTemplate;
    wrap.appendChild(row);
    var first = row.querySelector('input');
    if (first) first.focus();
  });
});

// Silme: satırlar sonradan da eklendiği için dinleyici kapsayıcıda
['statRows', 'machineRows'].forEach(function (id) {
  document.getElementById(id).addEventListener('click', function (e) {
    if (!e.target.classList.contains('row-del')) return;
    var row = e.target.closest('.spec-row, .machine-row');
    var rows = this.children.length;
    if (rows === 1) {
      // Son satır silinmesin, sadece temizlensin
      row.querySelectorAll('input, textarea').forEach(function (f) { f.value = ''; });
    } else {
      row.remove();
    }
    document.getElementById('hizmetForm').dispatchEvent(new Event('change', { bubbles: true }));
  });
});
</script>
<?php require __DIR__ . '/layout-bottom.php'; ?>
