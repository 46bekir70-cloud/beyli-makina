<?php
/**
 * Tek makale ekleme / düzenleme.
 * id parametresi yoksa yeni makale oluşturur.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../inc/articles.php';

bm_admin_require_login();

$bm_admin_active = 'makaleler';

if (!bm_config_exists() || !bm_db_ready()) {
    header('Location: /teknikel/veritabani-kurulum.php');
    exit;
}
bm_articles_migrate();   // tablo yoksa bir kez oluşturur

$id = (int)($_GET['id'] ?? 0);
$makale = $id ? bm_article_by_id($id) : null;

if ($id && !$makale) {
    http_response_code(404);
    $bm_admin_title = 'Makale bulunamadı';
    require __DIR__ . '/layout-top.php';
    echo '<div class="card"><h2>Makale bulunamadı</h2><p style="color:var(--muted);">Bu yazı silinmiş olabilir.</p>';
    echo '<p style="margin-top:16px;"><a href="/teknikel/makaleler.php" class="btn">Makale Listesine Dön</a></p></div>';
    require __DIR__ . '/layout-bottom.php';
    exit;
}

$bm_admin_title = $makale ? 'Makaleyi Düzenle' : 'Yeni Makale';
$bm_admin_save_form = 'makaleForm';
$message = '';

// Panelde daha önce kullanılmış etiketler — yazarken öneri olarak çıkar
$mevcutEtiketler = bm_article_tags(true);

$form = [
    'title'        => $makale['title'] ?? '',
    'summary'      => $makale['summary'] ?? '',
    'body'         => $makale['body'] ?? '',
    'image'        => $makale['image'] ?? '',
    'image_focus'  => $makale['image_focus'] ?? '',
    'image_zoom'   => (int)($makale['image_zoom'] ?? 100),
    'tags'         => $makale['tags'] ?? '',
    'author'       => $makale['author'] ?? '',
    'published_at' => $makale['published_at'] ?? date('Y-m-d'),
    'is_active'    => (int)($makale['is_active'] ?? 1),
    'is_featured'  => (int)($makale['is_featured'] ?? 0),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        $form = [
            'title'       => trim((string)($_POST['title'] ?? '')),
            'summary'     => trim((string)($_POST['summary'] ?? '')),
            'body'        => (string)($_POST['body'] ?? ''),
            'image'       => trim((string)($_POST['image'] ?? '')),
            'image_focus' => trim((string)($_POST['image_focus'] ?? '')),
            'image_zoom'  => bm_num_range($_POST['image_zoom'] ?? null, 100, 250, 100),
            // Etiketler serbest metin; sadece boşlukları ve tekrarları temizliyoruz
            'tags'        => implode(', ', bm_article_tag_list($_POST['tags'] ?? '')),
            'author'      => trim((string)($_POST['author'] ?? '')),
            'published_at' => trim((string)($_POST['published_at'] ?? '')),
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        ];

        if ($form['title'] === '') {
            $message = 'err|Makale başlığı boş olamaz.';
        } else {
            try {
                $alanlar = ['title', 'summary', 'body', 'image', 'image_focus', 'image_zoom',
                            'tags', 'author', 'published_at', 'is_active', 'is_featured'];
                $params = [
                    $form['title'],
                    $form['summary'] !== '' ? $form['summary'] : null,
                    $form['body'] !== '' ? $form['body'] : null,
                    $form['image'] !== '' ? $form['image'] : null,
                    $form['image_focus'] !== '' ? $form['image_focus'] : null,
                    $form['image_zoom'] > 100 ? (int)$form['image_zoom'] : null,
                    $form['tags'] !== '' ? $form['tags'] : null,
                    $form['author'] !== '' ? $form['author'] : null,
                    // Boş tarih '' olarak yazılırsa MySQL hata veriyor; NULL geçiyoruz
                    $form['published_at'] !== '' ? $form['published_at'] : null,
                    $form['is_active'],
                    $form['is_featured'],
                ];

                if ($makale) {
                    $set = implode('=?, ', $alanlar) . '=?';
                    $st = bm_db()->prepare("UPDATE articles SET $set WHERE id=?");
                    $params[] = $makale['id'];
                    $st->execute($params);
                    $message = 'ok|Makale kaydedildi.';
                    $makale = bm_article_by_id($makale['id']);
                } else {
                    // Adres parçası başlıktan üretilir; sonradan başlık değişse
                    // bile adres sabit kalsın diye güncellemede dokunulmuyor.
                    $sutunlar  = implode(', ', array_merge($alanlar, ['slug']));
                    $yerTutucu = implode(',', array_fill(0, count($alanlar) + 1, '?'));
                    $st = bm_db()->prepare("INSERT INTO articles ($sutunlar) VALUES ($yerTutucu)");
                    $params[] = bm_unique_article_slug($form['title']);
                    $st->execute($params);
                    header('Location: /teknikel/makale-duzenle.php?id=' . (int)bm_db()->lastInsertId() . '&yeni=1');
                    exit;
                }
            } catch (Throwable $e) {
                $message = 'err|Kaydedilemedi: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['yeni'])) $message = 'ok|Makale oluşturuldu.';
[$msgType, $msgText] = array_pad(explode('|', $message, 2), 2, '');

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1><?php echo $makale ? e($makale['title']) : 'Yeni Makale'; ?></h1>
    <div class="sub">
      <?php if ($makale): ?>
        /makale/<?php echo e($makale['slug']); ?> —
        <a href="/makale/<?php echo e($makale['slug']); ?>" target="_blank" style="color:var(--blue);">canlı önizleme ↗</a>
      <?php else: ?>
        Başlığı yazıp kaydet, adresi kendiliğinden oluşur.
      <?php endif; ?>
    </div>
  </div>
  <a href="/teknikel/makaleler.php" class="logout-link">← Makale Listesine Dön</a>
</div>

<?php if ($msgText): ?><div class="flash <?php echo $msgType; ?>"><?php echo e($msgText); ?></div><?php endif; ?>

<form method="post" id="makaleForm">
  <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">

  <div class="card">
    <h2>Yazı Bilgileri</h2>
    <div class="field">
      <label for="title">Başlık</label>
      <input type="text" id="title" name="title" value="<?php echo e($form['title']); ?>"
             required placeholder="Örn. CNC Tornada Yüzey Kalitesini Belirleyen 5 Etken">
    </div>
    <div class="field">
      <label for="summary">Kısa Özet</label>
      <textarea id="summary" name="summary" rows="3"
                placeholder="Blog listesindeki kartta ve Google sonucunda görünen 1-2 cümle."><?php echo e($form['summary']); ?></textarea>
      <div class="hint">Boş bırakırsan yazının ilk cümlelerinden otomatik üretilir.</div>
    </div>
    <div class="grid-2">
      <div class="field">
        <label for="tags">Etiketler (virgülle ayır)</label>
        <input type="text" id="tags" name="tags" value="<?php echo e($form['tags']); ?>"
               list="etiketOnerileri" placeholder="CNC Torna, Malzeme">
        <datalist id="etiketOnerileri">
          <?php foreach ($mevcutEtiketler as $t): ?>
            <option value="<?php echo e($t['label']); ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <div class="hint">
          Blog sayfasındaki filtre çipleri bunlardan oluşuyor. Aynı etiketi
          başka yazılarda da kullanırsan altlarında toplanırlar.
          <?php if ($mevcutEtiketler): ?>
            <br>Kullandıkların:
            <?php echo e(implode(' · ', array_column($mevcutEtiketler, 'label'))); ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="field">
        <label for="published_at">Yayın Tarihi</label>
        <input type="date" id="published_at" name="published_at" value="<?php echo e($form['published_at']); ?>">
        <div class="hint">Listede yeniden eskiye sıralanır.</div>
      </div>
    </div>
    <div class="grid-2">
      <div class="field">
        <label for="author">Yazar (opsiyonel)</label>
        <input type="text" id="author" name="author" value="<?php echo e($form['author']); ?>" placeholder="Beyli Makina">
      </div>
      <div class="field">
        <label>Durum</label>
        <label style="display:block;margin-bottom:6px;">
          <input type="checkbox" name="is_active" <?php echo $form['is_active'] ? 'checked' : ''; ?>
                 style="width:auto;display:inline-block;margin-right:8px;">Yayında (işareti kaldırırsan taslak kalır)
        </label>
        <label style="display:block;">
          <input type="checkbox" name="is_featured" <?php echo $form['is_featured'] ? 'checked' : ''; ?>
                 style="width:auto;display:inline-block;margin-right:8px;">Öne çıkan yazı
        </label>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>🖼️ Kapak Görseli</h2>
    <p class="card-hint">
      Blog listesindeki kartta ve yazının başında görünür. Yatay fotoğraflar
      en iyi durur (ör. 1200×800). Dosya doğrudan buradan yüklenir.
    </p>
    <?php echo bm_admin_icon_field('image', $form['image'], [
      'label'     => 'Kapak Görseli',
      'big'       => true,
      'id'        => 'image',
      'name_from' => 'title',
      'hint'      => 'Boş bırakırsan kartta sade bir simge görünür.',
    ]); ?>
    <input type="hidden" id="image_focus" name="image_focus" value="<?php echo e($form['image_focus']); ?>">
    <input type="hidden" id="image_zoom" name="image_zoom" value="<?php echo (int)$form['image_zoom']; ?>">
    <div class="focal-picker"
         data-focal-input="image_focus"
         data-focal-src-input="image"
         data-focal-zoom-input="image_zoom"
         data-focal-ratio="16/9"
         data-focal-hint="Görselin üstüne tıkla — kartta ve yazının başında o nokta ortada kalır."></div>
  </div>

  <div class="card">
    <h2>Yazı İçeriği</h2>
    <p class="card-hint">
      Ara başlık, kalın yazı, madde listesi ve görsel ekleyebilirsin.
      Görsel eklemek için araç çubuğundaki resim simgesini kullan —
      dosya doğrudan siteye yüklenir.
    </p>
    <div id="makaleEditor" style="background:#fff;border:1px solid var(--line);border-radius:8px;min-height:420px;"><?php echo $form['body']; ?></div>
    <?php /* JS çalışmazsa yazı silinmesin — bkz. sayfa-duzenle.php */ ?>
    <textarea name="body" id="body_field" style="display:none;"><?php echo e($form['body']); ?></textarea>
  </div>

  <?php /* Kaydet butonu kenar çubuğunda sabit duruyor — layout-top.php */ ?>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
<script src="/teknikel/assets/quill-upload.js?v=<?php echo e(bm_asset_version('teknikel/assets/quill-upload.js')); ?>"></script>
<script>
window.BM_CSRF = <?php echo json_encode(bm_csrf_token()); ?>;

const makaleQuill = new Quill('#makaleEditor', {
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
bmAttachImageHandler(makaleQuill);

// Zengin metni gizli alana yaz — kaydetmeden hemen önce
window.bmSyncEditors = function () {
  document.getElementById('body_field').value = makaleQuill.root.innerHTML;
};
document.getElementById('makaleForm').addEventListener('submit', window.bmSyncEditors);
</script>
<?php require __DIR__ . '/layout-bottom.php'; ?>
