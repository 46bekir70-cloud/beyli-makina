<?php
/**
 * Ürün kategorileri — ekle, yeniden adlandır, sırala, sil.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../inc/products.php';

bm_admin_require_login();

$bm_admin_active = 'urunler';
$bm_admin_title = 'Kategoriler';
$message = '';

if (!bm_config_exists() || !bm_db_ready()) {
    header('Location: /teknikel/veritabani-kurulum.php');
    exit;
}

// Alt kategori sütunu ve çoklu kategori ara tablosunu bir kez kurar
bm_categories_migrate();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'create') {
                $label = trim((string)($_POST['label'] ?? ''));
                if ($label === '') {
                    $message = 'err|Kategori adı boş olamaz.';
                } else {
                    // Slug adreslerde kullanılıyor; benzersiz olmalı.
                    $slug = bm_slugify($label);
                    $base = $slug; $n = 2;
                    $check = bm_db()->prepare('SELECT id FROM product_categories WHERE slug = ?');
                    while (true) {
                        $check->execute([$slug]);
                        if (!$check->fetch()) break;
                        $slug = $base . '-' . $n; $n++;
                    }
                    $max = (int)bm_db()->query('SELECT COALESCE(MAX(sort_order),0) FROM product_categories')->fetchColumn();
                    $ust = (int)($_POST['parent_id'] ?? 0) ?: null;
                    if (bm_categories_has_tree()) {
                        $st = bm_db()->prepare('INSERT INTO product_categories (slug, label, sort_order, parent_id) VALUES (?,?,?,?)');
                        $st->execute([$slug, $label, $max + 10, $ust]);
                    } else {
                        $st = bm_db()->prepare('INSERT INTO product_categories (slug, label, sort_order) VALUES (?,?,?)');
                        $st->execute([$slug, $label, $max + 10]);
                    }
                    $message = 'ok|"' . $label . '" kategorisi eklendi.';
                }

            } elseif ($action === 'update') {
                $ids    = (array)($_POST['id'] ?? []);
                $labels = (array)($_POST['label'] ?? []);
                $orders = (array)($_POST['sort_order'] ?? []);
                $parents = (array)($_POST['parent_id_row'] ?? []);
                $agac = bm_categories_has_tree();
                $st  = bm_db()->prepare('UPDATE product_categories SET label = ?, sort_order = ? WHERE id = ?');
                $stU = bm_db()->prepare('UPDATE product_categories SET label = ?, sort_order = ?, parent_id = ? WHERE id = ?');
                foreach ($ids as $i => $cid) {
                    $label = trim((string)($labels[$i] ?? ''));
                    if ($label === '') continue;
                    $cid = (int)$cid;
                    if (!$agac) { $st->execute([$label, (int)($orders[$i] ?? 0), $cid]); continue; }
                    $ust = (int)($parents[$i] ?? 0) ?: null;
                    // Kategori kendisinin ya da kendi altındakinin çocuğu olamaz —
                    // yoksa ağaçta sonsuz döngü oluşur.
                    if ($ust && ($ust === $cid || in_array($ust, bm_category_descendant_ids($cid), true))) {
                        $ust = null;
                    }
                    $stU->execute([$label, (int)($orders[$i] ?? 0), $ust, $cid]);
                }
                $message = 'ok|Kategoriler güncellendi.';

            } elseif ($action === 'delete') {
                // Şemadaki ON DELETE SET NULL sayesinde bu kategorideki ürünler
                // silinmez, sadece kategorisiz kalır.
                $silId = (int)($_POST['id'] ?? 0);
                if (bm_categories_has_tree()) {
                    // Alt kategoriler silinmesin, bir üst seviyeye çıksın
                    $ustu = bm_db()->prepare('SELECT parent_id FROM product_categories WHERE id = ?');
                    $ustu->execute([$silId]);
                    $yeniUst = $ustu->fetchColumn() ?: null;
                    $tas = bm_db()->prepare('UPDATE product_categories SET parent_id = ? WHERE parent_id = ?');
                    $tas->execute([$yeniUst, $silId]);
                }
                if (bm_has_category_map()) {
                    bm_db()->prepare('DELETE FROM product_category_map WHERE category_id = ?')->execute([$silId]);
                }
                $st = bm_db()->prepare('DELETE FROM product_categories WHERE id = ?');
                $st->execute([$silId]);
                $message = 'ok|Kategori silindi. İçindeki ürünler "kategorisiz" olarak kaldı.';
            }
        } catch (Throwable $e) {
            $message = 'err|İşlem yapılamadı: ' . $e->getMessage();
        }
    }
}

$agac = bm_category_tree();           // iç içe yapı — ağaç görünümü için
$categories = bm_category_flat($agac); // düz liste — açılır menüler için

// Kaydet butonu kenar çubuğunda; liste boşsa gönderilecek form da yok.
$bm_admin_save_form = $categories ? 'kategoriListForm' : '';

/**
 * Kategori ağacını iç içe listeler hâlinde basar.
 *
 * Form alanları (id[], label[], parent_id_row[], sort_order[]) belge sırasıyla
 * gönderildiği için kaydetme tarafında ek bir eşleştirmeye gerek yok.
 */
function bm_kategori_dugum($dugumler, $hepsi, $agacVar) {
    echo '<ul class="kat-liste">';
    foreach ($dugumler as $c) {
        $cocuk = $c['children'] ?? [];
        $id = (int)$c['id'];
        echo '<li class="kat-dugum' . ($cocuk ? ' has-child' : '') . '">';
        echo '<div class="kat-satir">';

        // Aç/kapa düğmesi — çocuğu olmayanlarda hizayı bozmasın diye boş kutu
        if ($cocuk) {
            echo '<button type="button" class="kat-ac" aria-expanded="true" title="Alt kategorileri aç/kapa">▾</button>';
        } else {
            echo '<span class="kat-ac bos" aria-hidden="true"></span>';
        }

        echo '<span class="kat-ikon" aria-hidden="true">' . ($cocuk ? '📂' : '📄') . '</span>';
        echo '<input type="hidden" name="id[]" value="' . $id . '">';
        echo '<input type="text" name="label[]" class="kat-ad" value="' . e($c['label']) . '">';

        // Ürün sayısı: kendi ürünü / alt kategoriler dâhil
        $kendi = (int)$c['product_count'];
        $toplam = (int)($c['total_count'] ?? $kendi);
        $baslik = $toplam > $kendi
            ? $kendi . ' ürün doğrudan bu kategoride, alt kategorilerle birlikte ' . $toplam
            : $kendi . ' ürün';
        echo '<span class="kat-sayi" title="' . e($baslik) . '">' . $kendi
             . ($toplam > $kendi ? ' <b>/ ' . $toplam . '</b>' : '') . '</span>';

        echo '<div class="kat-islem">';
        echo '<button type="button" class="kat-mini" data-alt-ekle="' . $id . '" '
           . 'data-ad="' . e($c['label']) . '" title="Bunun altına yeni kategori ekle">+ Alt</button>';

        if ($agacVar) {
            echo '<select name="parent_id_row[]" class="kat-ust" title="Bu kategoriyi başka bir kategorinin altına taşı">';
            echo '<option value="">— en üst —</option>';
            foreach ($hepsi as $o) {
                if ((int)$o['id'] === $id) continue;   // kendisi seçilemez
                echo '<option value="' . (int)$o['id'] . '"'
                   . ((int)($c['parent_id'] ?? 0) === (int)$o['id'] ? ' selected' : '') . '>'
                   . str_repeat('— ', (int)($o['depth'] ?? 0)) . e($o['label']) . '</option>';
            }
            echo '</select>';
        }

        echo '<input type="number" name="sort_order[]" class="kat-sira" value="' . (int)$c['sort_order']
           . '" title="Sıra — küçük sayı önce gösterilir">';
        echo '<button type="submit" form="delcat' . $id . '" class="kat-mini danger" '
           . 'onclick="return confirm(\'' . e($c['label']) . ' silinecek. İçindeki ' . $kendi
           . ' ürün kategorisiz kalacak (silinmeyecek). Alt kategorileri bir üst seviyeye çıkar. Emin misin?\');">Sil</button>';
        echo '</div>';

        echo '</div>';
        if ($cocuk) bm_kategori_dugum(array_values($cocuk), $hepsi, $agacVar);
        echo '</li>';
    }
    echo '</ul>';
}

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Ürün Kategorileri</h1>
    <div class="sub">Menüde ve ürünler sayfasındaki filtrelerde görünür.</div>
  </div>
  <a href="/teknikel/urunler.php" class="logout-link">← Ürün Listesi</a>
</div>

<?php if ($message): [$type, $text] = explode('|', $message, 2); ?>
  <div class="flash <?php echo $type === 'ok' ? 'ok' : 'err'; ?>"><?php echo e($text); ?></div>
<?php endif; ?>

<div class="card" id="yeniKategoriKart">
  <h2>Yeni Kategori Ekle</h2>
  <?php if (bm_categories_has_tree()): ?>
    <p class="card-hint">
      Kategoriler iç içe olabilir. Örnek: <em>CNC Torna</em> ana kategori olur,
      altına <em>3 Eksen</em> ve <em>4 Eksen</em> eklersin. Ziyaretçi "CNC Torna"yı
      seçtiğinde altındaki bütün ürünler listelenir, alt kategoriye inince liste
      daralır. Kaç kat derine ineceğin sınırlı değil.
      <br><strong>Kısayol:</strong> aşağıdaki ağaçta bir kategorinin yanındaki
      <strong>+ Alt</strong> düğmesine basarsan üst kategori burada kendiliğinden seçilir.
    </p>
  <?php endif; ?>
  <form method="post" class="filter-bar">
    <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
    <input type="hidden" name="action" value="create">
    <input type="text" name="label" id="yeniKategoriAd" placeholder="Kategori adı — örn. Dişli Grubu" required>
    <?php if (bm_categories_has_tree()): ?>
      <select name="parent_id" id="yeniKategoriUst">
        <option value="">— Ana kategori (en üst) —</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?php echo (int)$c['id']; ?>">
            <?php echo str_repeat('— ', (int)($c['depth'] ?? 0)) . e($c['label']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <button type="submit" class="btn">Ekle</button>
  </form>
</div>

<?php if ($categories): ?>
<div class="card">
  <div class="kat-baslik">
    <h2 style="margin:0;">Kategori Ağacı</h2>
    <div class="kat-arac">
      <button type="button" class="kat-mini" id="katHepsiniAc">Tümünü Aç</button>
      <button type="button" class="kat-mini" id="katHepsiniKapa">Tümünü Kapa</button>
    </div>
  </div>
  <p class="card-hint">
    Adı değiştirmek için üstüne tıklayıp yaz. Bir kategoriyi başka bir kategorinin
    altına almak için <strong>üst kategori</strong> kutusundan seç. Değişiklikler
    soldaki <strong>Kaydet</strong> ile uygulanır — <em>+ Alt</em> ve <em>Sil</em>
    ise anında çalışır.
    <br>Sayı sütunu: <strong>kendi ürünü / alt kategorilerle birlikte toplam</strong>.
  </p>
  <form method="post" id="kategoriListForm">
    <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
    <input type="hidden" name="action" value="update">
    <div class="kat-agac">
      <?php bm_kategori_dugum($agac, $categories, bm_categories_has_tree()); ?>
    </div>
    <?php /* Kaydet butonu kenar çubuğunda sabit duruyor — layout-top.php */ ?>
  </form>
</div>

<script>
(function () {
  // Aç/kapa
  document.querySelectorAll('.kat-ac:not(.bos)').forEach(function (b) {
    b.addEventListener('click', function () {
      var li = b.closest('.kat-dugum');
      var kapali = li.classList.toggle('kapali');
      b.textContent = kapali ? '▸' : '▾';
      b.setAttribute('aria-expanded', kapali ? 'false' : 'true');
    });
  });

  function hepsi(kapaliMi) {
    document.querySelectorAll('.kat-dugum.has-child').forEach(function (li) {
      li.classList.toggle('kapali', kapaliMi);
      var b = li.querySelector(':scope > .kat-satir > .kat-ac');
      if (b) { b.textContent = kapaliMi ? '▸' : '▾'; b.setAttribute('aria-expanded', kapaliMi ? 'false' : 'true'); }
    });
  }
  var ac = document.getElementById('katHepsiniAc');
  var kapa = document.getElementById('katHepsiniKapa');
  if (ac) ac.addEventListener('click', function () { hepsi(false); });
  if (kapa) kapa.addEventListener('click', function () { hepsi(true); });

  // "+ Alt": üstteki ekleme formunda üst kategoriyi seç ve ad alanına odaklan
  var ustSecim = document.getElementById('yeniKategoriUst');
  var adAlani = document.getElementById('yeniKategoriAd');
  document.querySelectorAll('[data-alt-ekle]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (ustSecim) ustSecim.value = b.getAttribute('data-alt-ekle');
      if (adAlani) {
        adAlani.placeholder = '"' + b.getAttribute('data-ad') + '" altına yeni kategori adı';
        document.getElementById('yeniKategoriKart').scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { adAlani.focus(); }, 300);
      }
    });
  });
})();
</script>

<?php foreach ($categories as $c): ?>
  <form id="delcat<?php echo (int)$c['id']; ?>" method="post" class="hidden-form">
    <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
  </form>
<?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/layout-bottom.php'; ?>
