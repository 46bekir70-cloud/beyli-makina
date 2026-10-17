<?php
/**
 * Tek ürün ekleme / düzenleme.
 * id parametresi yoksa yeni ürün oluşturur.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../inc/products.php';

bm_admin_require_login();

$bm_admin_active = 'urunler';

if (!bm_config_exists() || !bm_db_ready()) {
    header('Location: /teknikel/veritabani-kurulum.php');
    exit;
}

// Sonradan eklenen sütunları/tabloları bir kez kurar
bm_products_migrate();
bm_categories_migrate();

$id = (int)($_GET['id'] ?? 0);
$product = $id ? bm_product_by_id($id) : null;

if ($id && !$product) {
    http_response_code(404);
    $bm_admin_title = 'Ürün bulunamadı';
    require __DIR__ . '/layout-top.php';
    echo '<div class="card"><h2>Ürün bulunamadı</h2><p style="color:var(--muted);">Bu ürün silinmiş olabilir.</p>';
    echo '<p style="margin-top:16px;"><a href="/teknikel/urunler.php" class="btn">Ürün Listesine Dön</a></p></div>';
    require __DIR__ . '/layout-bottom.php';
    exit;
}

$bm_admin_title = $product ? 'Ürünü Düzenle' : 'Yeni Ürün';
$bm_admin_save_form = 'urunForm';
$bm_admin_preview = '/teknikel/onizleme.php';
$message = '';
// "Kaydet ve Yeni Ekle" ile boş forma dönüldüğünde, bir önce kaydedilen
// ürünün id'si burada tutulur; bildirimde ona dönen bağlantı verilebilsin.
$sonKayitId = 0;
$categories = bm_category_flat(null); // ağaç sırası + girinti bilgisi

// Formda gösterilecek değerler (POST hatalıysa kullanıcının yazdıkları kalsın)
$form = [
    'title'       => $product['title'] ?? '',
    'code'        => $product['code'] ?? '',
    'category_id' => $product['category_id'] ?? '',
    'extra_cats'  => $product ? bm_product_category_ids($product['id']) : [],
    'description' => $product['description'] ?? '',
    'emoji'       => $product['emoji'] ?? '',
    'image'       => $product['image'] ?? '',
    'image_focus' => $product['image_focus'] ?? '',
    'image_zoom'  => (int)($product['image_zoom'] ?? 100),
    'specs'       => $product['specs'] ?? [],
    'is_featured' => (int)($product['is_featured'] ?? 0),
    'is_active'   => (int)($product['is_active'] ?? 1),
    // Ürün sayfasındaki "farklı ölçüde de üretiriz" kutusu bu üründe görünsün mü
    'show_note'   => (int)($product['show_note'] ?? 1),
    'card_label'  => $product['card_label'] ?? '',
    'quote_type'  => $product['quote_type'] ?? '',
    'sort_order'  => (int)($product['sort_order'] ?? 0),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        // Teknik özellikler iki paralel dizi olarak geliyor: etiket[] ve değer[]
        $specLabels = (array)($_POST['spec_label'] ?? []);
        $specValues = (array)($_POST['spec_value'] ?? []);
        $specs = [];
        foreach ($specLabels as $i => $label) {
            $label = trim((string)$label);
            $value = trim((string)($specValues[$i] ?? ''));
            if ($label === '' && $value === '') continue;
            $specs[] = [$label, $value];
        }

        $form = [
            'title'       => trim((string)($_POST['title'] ?? '')),
            'code'        => trim((string)($_POST['code'] ?? '')),
            'category_id' => (string)($_POST['category_id'] ?? ''),
            'extra_cats'  => array_map('intval', (array)($_POST['extra_cats'] ?? [])),
            'description' => trim((string)($_POST['description'] ?? '')),
            'emoji'       => trim((string)($_POST['emoji'] ?? '')),
            'image'       => trim((string)($_POST['image'] ?? '')),
            'image_focus' => trim((string)($_POST['image_focus'] ?? '')),
            'image_zoom'  => bm_num_range($_POST['image_zoom'] ?? null, 100, 250, 100),
            'specs'       => $specs,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
            'show_note'   => isset($_POST['show_note']) ? 1 : 0,
            'card_label'  => trim((string)($_POST['card_label'] ?? '')),
            'quote_type'  => trim((string)($_POST['quote_type'] ?? '')),
            'sort_order'  => (int)($_POST['sort_order'] ?? 0),
        ];

        if ($form['title'] === '') {
            $message = 'err|Ürün adı boş olamaz.';
        } else {
            try {
                // Sütun listesi tabloya göre kuruluyor: image_focus sonradan
                // eklendi ve çok eski kurulumlarda (ALTER yetkisi yoksa)
                // bulunmayabilir — o durumda kaydetme yine çalışsın.
                $alanlar = ['category_id', 'title', 'code', 'description', 'emoji', 'image'];
                $params = [
                    $form['category_id'] !== '' ? (int)$form['category_id'] : null,
                    $form['title'],
                    $form['code'] !== '' ? $form['code'] : null,
                    $form['description'] !== '' ? $form['description'] : null,
                    $form['emoji'] !== '' ? $form['emoji'] : null,
                    $form['image'] !== '' ? $form['image'] : null,
                ];
                if (bm_products_has_focus()) {
                    $alanlar[] = 'image_focus';
                    $params[]  = $form['image_focus'] !== '' ? $form['image_focus'] : null;
                }
                if (bm_products_has('image_zoom')) {
                    $alanlar[] = 'image_zoom';
                    // 100 = olduğu gibi; boşa yakın veriyi NULL tutuyoruz
                    $params[]  = $form['image_zoom'] > 100 ? (int)$form['image_zoom'] : null;
                }
                if (bm_products_has('show_note')) {
                    $alanlar[] = 'show_note';
                    $params[]  = $form['show_note'];
                }
                if (bm_products_has('card_label')) {
                    $alanlar[] = 'card_label';
                    $params[]  = $form['card_label'] !== '' ? $form['card_label'] : null;
                }
                if (bm_products_has('quote_type')) {
                    $alanlar[] = 'quote_type';
                    $params[]  = $form['quote_type'] !== '' ? $form['quote_type'] : null;
                }
                foreach (['specs', 'is_featured', 'is_active', 'sort_order'] as $k) $alanlar[] = $k;
                $params[] = json_encode($form['specs'], JSON_UNESCAPED_UNICODE);
                $params[] = $form['is_featured'];
                $params[] = $form['is_active'];
                $params[] = $form['sort_order'];

                if ($product) {
                    // updated_at elle yazılıyor: eski MySQL/MariaDB sürümlerinde
                    // tabloda ikinci bir otomatik zaman damgası kullanılamıyor.
                    $set = implode('=?, ', $alanlar) . '=?';
                    $st = bm_db()->prepare(
                        "UPDATE products SET $set, updated_at=NOW() WHERE id=?"
                    );
                    $params[] = $product['id'];
                    $st->execute($params);
                    // Ana kategori + ek kategoriler ara tabloya yazılır. Ana kategori
                    // listenin başında durur; kartta görünen etiket odur.
                    $tumKat = array_merge(
                        $form['category_id'] !== '' ? [(int)$form['category_id']] : [],
                        $form['extra_cats']
                    );
                    bm_set_product_categories($product['id'], $tumKat);
                    // "Kaydet ve Yeni Ekle": kayıt tamamlandı, boş forma geçiyoruz.
                    // Kaydedilen ürünün id'si adrese konuyor ki bildirimden ona
                    // geri dönmek tek tık olsun.
                    if (isset($_POST['kaydet_yeni'])) {
                        header('Location: /teknikel/urun-duzenle.php?eklendi=' . (int)$product['id']);
                        exit;
                    }
                    $message = 'ok|Ürün kaydedildi.';
                    $product = bm_product_by_id($product['id']);
                    $form['extra_cats'] = bm_product_category_ids($product['id']);
                } else {
                    // Yeni kayıtta slug başlıktan üretilir; sonradan başlık
                    // değişse bile adres sabit kalsın diye güncellemede dokunulmuyor.
                    $sutunlar = implode(', ', array_merge($alanlar, ['slug']));
                    $yerTutucu = implode(',', array_fill(0, count($alanlar) + 1, '?'));
                    $st = bm_db()->prepare(
                        "INSERT INTO products ($sutunlar) VALUES ($yerTutucu)"
                    );
                    $params[] = bm_unique_product_slug($form['title']);
                    $st->execute($params);
                    $newId = (int)bm_db()->lastInsertId();
                    $tumKat = array_merge(
                        $form['category_id'] !== '' ? [(int)$form['category_id']] : [],
                        $form['extra_cats']
                    );
                    bm_set_product_categories($newId, $tumKat);
                    if (isset($_POST['kaydet_yeni'])) {
                        header('Location: /teknikel/urun-duzenle.php?eklendi=' . $newId);
                        exit;
                    }
                    header('Location: /teknikel/urun-duzenle.php?id=' . $newId . '&yeni=1');
                    exit;
                }
            } catch (Throwable $e) {
                $message = 'err|Kaydedilemedi: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['yeni'])) {
    $message = 'ok|Ürün oluşturuldu.';
}

// "Kaydet ve Yeni Ekle" ile gelindi: form boş, önceki kayıt tamamlandı.
if (isset($_GET['eklendi'])) {
    $message = 'ok|Ürün kaydedildi. Yeni ürün için form hazır.';
    $sonKayitId = (int)$_GET['eklendi'];
}

// En az bir boş özellik satırı göster
$specRows = $form['specs'];
if (!$specRows) $specRows = [['', '']];

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1><?php echo $product ? 'Ürünü Düzenle' : 'Yeni Ürün'; ?></h1>
    <div class="sub">
      <?php if ($product): ?>
        Adres: <code>/urun/<?php echo e($product['slug']); ?></code>
      <?php else: ?>
        Ürün adını yazıp kaydedin, ardından detayları tamamlayabilirsiniz.
      <?php endif; ?>
    </div>
  </div>
  <a href="/teknikel/urunler.php" class="logout-link">← Ürün Listesi</a>
</div>

<?php if ($message): [$type, $text] = explode('|', $message, 2); ?>
  <div class="flash <?php echo $type === 'ok' ? 'ok' : 'err'; ?>">
    <span><?php echo e($text); ?></span>
    <?php if ($type === 'ok'): ?>
      <?php /* Kaydettikten sonra en sık yapılan iş yeni bir ürün eklemek;
               bildirimin içinden tek tıkla boş forma geçiliyor. */ ?>
      <span class="flash-actions">
        <a href="/teknikel/urun-duzenle.php" class="btn small">+ Bir Ürün Daha Ekle</a>
        <?php if ($sonKayitId): ?>
          <a href="/teknikel/urun-duzenle.php?id=<?php echo (int)$sonKayitId; ?>" class="btn ghost small">Kaydedilen Ürünü Aç</a>
        <?php endif; ?>
        <a href="/teknikel/urunler.php" class="btn ghost small">Ürün Listesi</a>
      </span>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" class="product-form" id="urunForm">
  <input type="hidden" name="onizleme_turu" value="urun">
  <input type="hidden" name="preview_slug" value="<?php echo e($product['slug'] ?? 'onizleme'); ?>">
  <!-- Önizlemede "Benzer Parçalar" listesi ürünün kendisini dışlayabilsin diye -->
  <input type="hidden" name="preview_id" value="<?php echo (int)($product['id'] ?? 0); ?>">
  <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">

  <div class="card">
    <h2>Temel Bilgiler</h2>

    <div class="field">
      <label for="title">Ürün Adı *</label>
      <input type="text" id="title" name="title" value="<?php echo e($form['title']); ?>" required
             placeholder="Örn. Miller Şaft Ø40">
    </div>

    <div class="field-row">
      <div class="field">
        <label for="code">Parça / Stok Kodu</label>
        <input type="text" id="code" name="code" value="<?php echo e($form['code']); ?>"
               placeholder="Örn. BM-TO-0042">
        <span class="hint">Müşteriler bu kodla arama yapabilir.</span>
      </div>
      <?php /* Ana kategori için ayrı açılır liste YOK. Derin ağaçlarda o liste
               "— — — Makara Dişli Parçası" gibi okunmaz hâle geliyordu ve
               aşağıdaki kutularla ayrı ayrı uğraşmak gerekiyordu. Artık ana
               kategori, işaretlenen kategoriler arasından sağdaki panelden
               seçiliyor; buradaki gizli alan yalnızca değeri taşıyor. */ ?>
      <input type="hidden" id="category_id" name="category_id"
             value="<?php echo e((string)$form['category_id']); ?>">
    </div>

    <?php if (bm_has_category_map()): ?>
      <div class="field">
        <label>Kategoriler</label>
        <p class="card-hint" style="margin:-2px 0 10px;">
          Ürünün görüneceği kategorileri işaretle — birden fazla olabilir.
          Bir alt kategoriyi işaretlediğinde <strong>üstündeki bütün kategoriler
          kendiliğinden işaretlenir</strong>; işareti kaldırdığında da o dalın altındakiler
          birlikte kalkar. Filtredeki ürün sayısı yine tek sayılır, mükerrer olmaz.
          <br><strong>Ana kategori</strong> (kartta ve ürün sayfasında yazan etiket)
          sağdaki listeden seçilir; ilk işaretlediğin kendiliğinden ana olur.
        </p>
        <!-- Solda seçim, sağda anlık önizleme: ürünün hangi listelerde
             görüneceğini kaydetmeden görebilmek için. -->
        <div class="kategori-duzen">
          <div class="kategori-secim">
            <?php foreach ($categories as $c): ?>
              <?php
                // Kategorinin üst zinciri — önizlemede "CNC › Torna" diye çıkar
                $zincirAd = [];
                $zincirId = [];
                foreach (bm_category_path((int)$c['id']) as $pid) {
                    // Kendisi hariç yalnız üstleri sakla (otomatik işaretleme için)
                    if ($pid !== (int)$c['id']) $zincirId[] = $pid;
                    foreach ($categories as $k) {
                        if ((int)$k['id'] === $pid) { $zincirAd[] = $k['label']; break; }
                    }
                }
              ?>
              <label class="check" style="padding-left:<?php echo (int)($c['depth'] ?? 0) * 20; ?>px;">
                <input type="checkbox" name="extra_cats[]" value="<?php echo (int)$c['id']; ?>"
                  data-yol="<?php echo e(implode(' › ', $zincirAd)); ?>"
                  data-ust="<?php echo e(implode(',', $zincirId)); ?>"
                  <?php echo in_array((int)$c['id'], $form['extra_cats'], true) ? 'checked' : ''; ?>>
                <?php echo e($c['label']); ?>
                <span class="hint" style="margin:0;">(<?php echo (int)$c['product_count']; ?>)</span>
              </label>
            <?php endforeach; ?>
          </div>

          <aside class="kategori-onizleme" id="kategoriOnizleme">
            <h4>Sitede nerede görünecek?</h4>
            <div class="ko-liste"></div>
            <p class="ko-not">
              Kutuları işaretledikçe burası anında güncellenir.
              <strong>ana</strong> etiketli olan, kartta ve ürün sayfasında yazan
              kategoridir — değiştirmek için satırdaki <em>ana yap</em>'a bas.
            </p>
          </aside>
        </div>
      </div>
    <?php endif; ?>

    <?php if (bm_products_has('card_label')): ?>
      <?php
        // Otomatik yol: kullanıcı kutuyu boş bırakırsa sitede bu yazacak.
        $otoYol = $product ? bm_product_category_path_label(array_merge($product, ['card_label' => '']))
                           : '';
      ?>
      <div class="field">
        <label for="card_label">Kartta Görünen Kategori Yazısı</label>
        <input type="text" id="card_label" name="card_label"
               value="<?php echo e($form['card_label']); ?>"
               placeholder="<?php echo e($otoYol !== '' ? $otoYol : 'İPLİK › OPEN-END › S11'); ?>">
        <div class="hint">
          Ürün kartında ve ürün sayfasında, adın hemen üstünde çıkan küçük yazı.
          <strong>Boş bırakırsan kategori yolu otomatik yazılır</strong>
          <?php if ($otoYol !== ''): ?>(şu an: <em><?php echo e($otoYol); ?></em>)<?php endif; ?>.
          Yol uzun kalıyorsa buraya kısa bir karşılık yaz — örn.
          <em>OPEN-END S11</em>.
        </div>
      </div>
    <?php endif; ?>

    <div class="field">
      <label for="description">Açıklama</label>
      <textarea id="description" name="description" rows="4"
                placeholder="Ürünün kullanım alanı, üretim yöntemi vb."><?php echo e($form['description']); ?></textarea>
    </div>
  </div>

  <div class="card">
    <h2>🖼️ Görsel</h2>
    <p class="card-hint">
      <strong>Yükle</strong> ile bilgisayarından yeni fotoğraf ekle,
      <strong>Siteden Seç</strong> ile daha önce yüklediklerinden klasörlerin
      içinden seç. Yüklenen dosya <strong>ürünün adıyla</strong> kaydedilir,
      böylece kütüphanede aramayla bulunur.
    </p>
    <?php echo bm_admin_icon_field('image', $form['image'], [
      'label'     => 'Ürün Fotoğrafı',
      'big'       => true,
      // id şart: ortalama aracı görsel yolunu bu id ile buluyor. Olmadığında
      // fotoğraf yüklenmiş olsa bile "Önce bir görsel seçin" yazıyordu.
      'id'        => 'image',
      'name_from' => 'title', // dosya adı ürün adından üretilsin
      'hint'      => 'Boş bırakırsan ürün kartında sade bir dişli simgesi görünür.',
    ]); ?>
    <?php /* Emoji alanı kaldırıldı; yerine "Siteden Seç" geldi. Eskiden girilmiş
             emoji değerleri veritabanında duruyor ve görseli olmayan üründe
             yedek olarak kullanılmaya devam ediyor — sadece artık düzenlenmiyor. */ ?>
    <input type="hidden" name="emoji" value="<?php echo e($form['emoji']); ?>">

    <?php if (bm_products_has_focus()): ?>
      <input type="hidden" id="image_focus" name="image_focus" value="<?php echo e($form['image_focus']); ?>">
      <input type="hidden" id="image_zoom" name="image_zoom" value="<?php echo (int)$form['image_zoom']; ?>">
      <div class="focal-picker"
           data-focal-input="image_focus"
           data-focal-src-input="image"
           <?php if (bm_products_has('image_zoom')): ?>data-focal-zoom-input="image_zoom"<?php endif; ?>
           data-focal-ratio="4/3"
           data-focal-hint="Görselin üstüne tıkla — kart ve ürün sayfasında o nokta ortada kalır. Parça küçük duruyorsa yakınlaştırma çubuğunu kullan."></div>
    <?php else: ?>
      <p class="hint">
        Görsel ortalama aracı için veritabanına küçük bir sütun eklenmesi gerekiyor;
        eklenemedi (kullanıcının ALTER yetkisi olmayabilir). Fotoğraf yükleme
        normal çalışıyor, ortalama devre dışı.
      </p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Teknik Özellikler</h2>
    <p class="card-hint">Ürün sayfasında tablo olarak görünür. Boş satırlar kaydedilmez.</p>
    <div id="specRows">
      <?php foreach ($specRows as $spec): ?>
        <div class="spec-row">
          <input type="text" name="spec_label[]" value="<?php echo e($spec[0] ?? ''); ?>" placeholder="Malzeme">
          <input type="text" name="spec_value[]" value="<?php echo e($spec[1] ?? ''); ?>" placeholder="Çelik (St 52)">
          <button type="button" class="link-btn danger spec-del">Sil</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" id="addSpec" class="btn ghost">+ Özellik Satırı Ekle</button>
  </div>

  <div class="card">
    <h2>Yayın</h2>
    <div class="field-row">
      <label class="check">
        <input type="checkbox" name="is_active" <?php echo $form['is_active'] ? 'checked' : ''; ?>>
        Sitede yayında
      </label>
      <label class="check">
        <input type="checkbox" name="is_featured" <?php echo $form['is_featured'] ? 'checked' : ''; ?>>
        Ana sayfa vitrininde göster
      </label>
      <?php if (bm_products_has('show_note')): ?>
        <label class="check">
          <input type="checkbox" name="show_note" <?php echo $form['show_note'] ? 'checked' : ''; ?>>
          "Farklı ölçüde de üretiriz" notunu göster
        </label>
      <?php endif; ?>
      <?php if (bm_products_has('quote_type')):
        // Seçenekler teklif sayfasındaki listeyle aynı olmalı; oradan okuyoruz
        // ki panelde tek yerden yönetilsin.
        $bmIcerik = bm_load_content();
        $teklifTurleri = array_values(array_filter((array)($bmIcerik['order_section']['part_types'] ?? [])));
        if (!$teklifTurleri) $teklifTurleri = ['CNC Torna Parçası', 'CNC Freze Parçası', 'Diğer / Özel Tasarım'];
      ?>
        <div class="field field-narrow" style="flex:1 1 260px;">
          <label for="quote_type">Teklif Formunda Seçili Gelecek Tür</label>
          <select id="quote_type" name="quote_type">
            <option value="">— Formdaki ilk seçenek —</option>
            <?php foreach ($teklifTurleri as $tt): ?>
              <option value="<?php echo e($tt); ?>"<?php echo $form['quote_type'] === $tt ? ' selected' : ''; ?>>
                <?php echo e($tt); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="hint">
            Ziyaretçi bu üründen "Teklif İste" dediğinde formda hangi tür seçili gelsin.
            Liste <a href="/teknikel/teklif-sayfasi.php" style="color:var(--blue);">İletişim / Teklif</a>
            ekranından düzenleniyor.
          </span>
        </div>
      <?php endif; ?>
      <div class="field field-narrow">
        <label for="sort_order">Sıra</label>
        <input type="number" id="sort_order" name="sort_order" value="<?php echo (int)$form['sort_order']; ?>">
        <span class="hint">Küçük sayı önce gelir.</span>
      </div>
    </div>
  </div>

  <div class="form-actions">
    <!-- Kaydet kenar çubuğunda da sabit duruyor; formun sonunda yalnızca geri
         götüren bir buton kalmasın diye burada da bulunuyor. -->
    <button type="submit" class="btn">Kaydet</button>
    <?php /* Seri ürün girişinde: kaydeder ve doğrudan boş bir yeni ürün formu
             açar. Kenar çubuğundaki sabit Kaydet bu ada sahip olmadığı için
             normal kaydetme davranışı değişmiyor. */ ?>
    <button type="submit" name="kaydet_yeni" value="1" class="btn gray">Kaydet ve Yeni Ekle</button>
    <a href="/teknikel/urunler.php" class="btn ghost">Vazgeç</a>
    <?php if ($product): ?>
      <a href="/urun/<?php echo e($product['slug']); ?>" target="_blank" class="btn ghost">Sayfayı Gör ↗</a>
    <?php endif; ?>
  </div>
</form>

<script>
(function () {
  var wrap = document.getElementById('specRows');
  document.getElementById('addSpec').addEventListener('click', function () {
    var row = document.createElement('div');
    row.className = 'spec-row';
    row.innerHTML = '<input type="text" name="spec_label[]" placeholder="Özellik">' +
                    '<input type="text" name="spec_value[]" placeholder="Değer">' +
                    '<button type="button" class="link-btn danger spec-del">Sil</button>';
    wrap.appendChild(row);
    row.querySelector('input').focus();
  });
  // Satırlar sonradan da eklendiği için olay dinleyicisi kapsayıcıda
  wrap.addEventListener('click', function (e) {
    if (!e.target.classList.contains('spec-del')) return;
    var rows = wrap.querySelectorAll('.spec-row');
    if (rows.length === 1) {
      // Son satır silinmesin, sadece temizlensin
      rows[0].querySelectorAll('input').forEach(function (i) { i.value = ''; });
    } else {
      e.target.closest('.spec-row').remove();
    }
  });
})();

/* ---- Kategori önizlemesi ----
   İşaretlenen kategorileri, ürünün sitede görüneceği liste yollarıyla birlikte
   anında gösteriyor. Ana kategori (üstteki seçim) ayrıca işaretleniyor. */
(function () {
  var kutu = document.getElementById('kategoriOnizleme');
  if (!kutu) return;
  var liste = kutu.querySelector('.ko-liste');
  var ana = document.getElementById('category_id');
  var kutular = document.querySelectorAll('input[name="extra_cats[]"]');

  function yol(input) {
    return input.dataset.yol || input.closest('label').textContent.trim().replace(/\s*\(\d+\)\s*$/, '');
  }

  /** İşaretli kutular, belge sırasında */
  function isaretliler() {
    return Array.prototype.filter.call(kutular, function (k) { return k.checked; });
  }

  /**
   * Ana kategoriyi geçerli tutar.
   * Ana kategori artık işaretli değilse (kutusu kaldırıldıysa) ilk işaretliye
   * geçer; hiç işaretli yoksa boşalır. Böylece kartta olmayan bir kategorinin
   * adı yazamaz.
   */
  function anaDuzelt() {
    var liste = isaretliler();
    if (!liste.length) { ana.value = ''; return; }
    var gecerli = liste.some(function (k) { return k.value === ana.value; });
    if (!gecerli) ana.value = liste[0].value;
  }

  function ciz() {
    anaDuzelt();
    var secili = isaretliler().map(function (k) {
      return { id: k.value, yol: yol(k), ana: k.value === ana.value };
    });
    // Ana kategori her zaman en üstte dursun
    secili.sort(function (a, b) { return (b.ana ? 1 : 0) - (a.ana ? 1 : 0); });

    if (!secili.length) {
      liste.innerHTML = '<p class="ko-bos">Hiç kategori seçilmedi — ürün yalnızca arama sonuçlarında ve tüm ürünler listesinde çıkar.</p>';
      return;
    }
    liste.innerHTML = secili.map(function (s) {
      return '<div class="ko-satir' + (s.ana ? ' ana' : '') + '">' +
        '<span class="ko-yol">Ürünler › ' + s.yol + '</span>' +
        (s.ana
          ? '<span class="ko-ana">ana</span>'
          : '<button type="button" class="ko-anayap" data-id="' + s.id + '">ana yap</button>') +
        '</div>';
    }).join('');
  }

  // "ana yap" düğmeleri her çizimde yeniden oluştuğu için dinleyici kapsayıcıda
  liste.addEventListener('click', function (e) {
    var btn = e.target.closest('.ko-anayap');
    if (!btn) return;
    ana.value = btn.dataset.id;
    ciz();
    // Önizleme çerçevesi de tazelensin
    var form = document.getElementById('urunForm');
    if (form) form.dispatchEvent(new Event('change', { bubbles: true }));
  });

  /* ---- Üst kategorileri otomatik işaretleme ----
     Bir alt kategori işaretlenince zincirdeki bütün üstleri de işaretlenir
     (S11 -> S1 -> ana grup). Tersi de geçerli: bir üst kategorinin işareti
     kaldırılırsa altındaki dallar da kalkar, yoksa ürün "alt kategoride var
     ama üstünde yok" gibi tutarsız bir duruma düşerdi. */
  var kutuHaritasi = {};
  kutular.forEach(function (k) { kutuHaritasi[k.value] = k; });

  /** Verilen kutunun üst zincirindeki kutular */
  function ustler(input) {
    return (input.dataset.ust || '')
      .split(',')
      .filter(function (id) { return id !== ''; })
      .map(function (id) { return kutuHaritasi[id]; })
      .filter(Boolean);
  }

  /** Verilen kutunun altındaki bütün dallar (kaç kademe olursa olsun) */
  function altlar(input) {
    return Array.prototype.filter.call(kutular, function (k) {
      if (k === input) return false;
      return (k.dataset.ust || '').split(',').indexOf(input.value) !== -1;
    });
  }

  /** Otomatik değişen satırı kısa süre vurgula ki kullanıcı ne olduğunu görsün */
  function vurgula(input) {
    var satir = input.closest('label');
    if (!satir) return;
    satir.classList.remove('kat-otomatik');
    void satir.offsetWidth; // animasyonu yeniden başlatmak için
    satir.classList.add('kat-otomatik');
    setTimeout(function () { satir.classList.remove('kat-otomatik'); }, 1200);
  }

  /* Kendiliğinden işaretlenenlerin kaydı: kullanıcının eliyle işaretledikleri
     burada durmaz. Böylece alt kategorinin işareti kalktığında yalnız onun
     yüzünden eklenmiş üstler geri kalkar, senin bilerek seçtiğin üst kalır. */
  var otomatik = {};

  /** Üst kategorinin altında hala işaretli bir dal var mı? */
  function altiIsaretliMi(input) {
    return altlar(input).some(function (a) { return a.checked; });
  }

  kutular.forEach(function (k) {
    k.addEventListener('change', function () {
      if (k.checked) {
        delete otomatik[k.value]; // artık kullanıcının kendi seçimi
        ustler(k).forEach(function (u) {
          if (!u.checked) { u.checked = true; otomatik[u.value] = true; vurgula(u); }
        });
      } else {
        delete otomatik[k.value];
        altlar(k).forEach(function (a) {
          if (a.checked) { a.checked = false; delete otomatik[a.value]; vurgula(a); }
        });
        // Üstten aşağı değil, aşağıdan yukarı temizliyoruz: önce en yakın üst
        // boşalmalı ki bir üstteki de boş sayılabilsin.
        ustler(k).slice().reverse().forEach(function (u) {
          if (u.checked && otomatik[u.value] && !altiIsaretliMi(u)) {
            u.checked = false; delete otomatik[u.value]; vurgula(u);
          }
        });
      }
      ciz();
    });
  });

  // Sayfa açılışında eski kayıtlarda eksik üst kategori varsa tamamla
  isaretliler().forEach(function (k) {
    ustler(k).forEach(function (u) {
      if (!u.checked) { u.checked = true; otomatik[u.value] = true; }
    });
  });
  ciz();
})();
</script>
<?php require __DIR__ . '/layout-bottom.php'; ?>
