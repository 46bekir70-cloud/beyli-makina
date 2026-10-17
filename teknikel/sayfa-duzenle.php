<?php
require_once __DIR__ . '/config.php';
// Güvenlik: yetki kontrolü EN BAŞTA. Eskiden bu kontrol layout-top.php
// içinde, yani POST işlendikten SONRA devreye giriyordu; oturumu olmayan biri
// form gönderip içeriği değiştirebilirdi (çıktı görmese bile kayıt olurdu).
bm_admin_require_login();
$content = bm_load_content();
$slug = trim($_GET['slug'] ?? $_POST['slug'] ?? '');
$page = bm_find_page($content, $slug);

if (!$page) {
    header('Location: /teknikel/sayfalar.php');
    exit;
}

$bm_admin_active = 'sayfalar';
$bm_admin_title = 'Düzenle: ' . $page['title'];
$bm_admin_save_form = 'pageForm';
$bm_admin_preview = '/teknikel/onizleme.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        foreach ($content['pages'] as &$p) {
            if ($p['slug'] === $slug) {
                $p['title'] = trim($_POST['title'] ?? $p['title']);
                $p['hero_tag'] = trim($_POST['hero_tag'] ?? '');
                $p['hero_subtitle'] = trim($_POST['hero_subtitle'] ?? '');
                $p['hero_image'] = trim($_POST['hero_image'] ?? '');
                $p['hero_focus'] = trim($_POST['hero_focus'] ?? '');
                $p['html'] = $_POST['html'] ?? $p['html'];
                $p['in_nav'] = isset($_POST['in_nav']);
                $p['nav_order'] = (int)($_POST['nav_order'] ?? $p['nav_order'] ?? 0);
                break;
            }
        }
        unset($p);
        bm_save_content($content);
        $message = 'ok|Sayfa kaydedildi.';
        $content = bm_load_content();
        $page = bm_find_page($content, $slug);
    }
}
[$msgType, $msgText] = array_pad(explode('|', $message, 2), 2, '');

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1><?php echo e($page['title']); ?></h1>
    <div class="sub">/<?php echo e($page['slug']); ?> — <a href="/<?php echo urlencode($page['slug']); ?>" target="_blank" style="color:var(--blue);">canlı önizleme ↗</a></div>
  </div>
  <a href="/teknikel/sayfalar.php" class="logout-link">← Sayfalar Listesine Dön</a>
</div>

<?php if ($msgText): ?><div class="flash <?php echo $msgType; ?>"><?php echo e($msgText); ?></div><?php endif; ?>

<form method="post" id="pageForm">
  <input type="hidden" name="onizleme_turu" value="sayfa">
  <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
  <input type="hidden" name="slug" value="<?php echo e($page['slug']); ?>">

  <div class="card">
    <h2>Sayfa Ayarları</h2>
    <div class="grid-2">
      <div class="field"><label>Sayfa Başlığı (menüde görünen)</label><input type="text" name="title" value="<?php echo e($page['title']); ?>" required></div>
      <div class="field"><label>Sıra (küçük sayı önce gösterilir)</label><input type="number" name="nav_order" value="<?php echo (int)($page['nav_order'] ?? 0); ?>"></div>
    </div>
    <div class="field">
      <label><input type="checkbox" name="in_nav" <?php echo !empty($page['in_nav']) ? 'checked' : ''; ?> style="width:auto;display:inline-block;margin-right:8px;">Üst menüde göster</label>
      <div class="hint">İşareti kaldırırsan sayfa yayında kalır ama menüde görünmez (link ile hâlâ erişilebilir).</div>
    </div>
    <div class="grid-2">
      <div class="field"><label>Sayfa üstü küçük etiket (opsiyonel)</label><input type="text" name="hero_tag" value="<?php echo e($page['hero_tag'] ?? ''); ?>" placeholder="Örn. Kahramanmaraş OSB"></div>
      <div class="field"><label>Sayfa üstü açıklama (opsiyonel)</label><input type="text" name="hero_subtitle" value="<?php echo e($page['hero_subtitle'] ?? ''); ?>"></div>
    </div>
  </div>

  <?php echo bm_admin_hero_card('hero_image', 'hero_focus', $page['hero_image'] ?? '', $page['hero_focus'] ?? ''); ?>

  <div class="card">
    <h2>Sayfa İçeriği</h2>
    <div id="pageEditor" style="background:#fff;border:1px solid var(--line);border-radius:8px;min-height:360px;"><?php echo $page['html'] ?? ''; ?></div>
    <?php /* İçerik sunucu tarafında da dolu gidiyor: JS çalışmazsa kaydetmek
             metni silmesin diye. JS çalıştığında bu değerin üzerine yazılıyor. */ ?>
    <textarea name="html" id="html_field" style="display:none;"><?php echo e($page['html'] ?? ''); ?></textarea>
  </div>

  <?php /* Kaydet butonu kenar çubuğunda sabit duruyor — layout-top.php */ ?>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
<script src="/teknikel/assets/quill-upload.js?v=<?php echo e(bm_asset_version('teknikel/assets/quill-upload.js')); ?>"></script>
<script>
window.BM_CSRF = <?php echo json_encode(bm_csrf_token()); ?>;

const pageQuill = new Quill('#pageEditor', {
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
bmAttachImageHandler(pageQuill);

// Zengin metni gizli alana yaz — hem kaydetmede hem önizlemede gerekli.
// preview.js bu fonksiyonu istek göndermeden hemen önce çağırıyor.
window.bmSyncEditors = function () {
  document.getElementById('html_field').value = pageQuill.root.innerHTML;
};
document.getElementById('pageForm').addEventListener('submit', window.bmSyncEditors);

// Quill 'change' olayı yaymadığı için önizlemeyi elle tetikliyoruz.
pageQuill.on('text-change', function () {
  clearTimeout(window.__bmQuillTimer);
  window.__bmQuillTimer = setTimeout(function () {
    document.getElementById('pageForm').dispatchEvent(new Event('change', { bubbles: true }));
  }, 700);
});
</script>
<?php require __DIR__ . '/layout-bottom.php'; ?>
