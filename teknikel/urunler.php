<?php
/**
 * Ürün yönetimi — liste, arama, sayfalama, hızlı işlemler.
 * Tek ürünün ayrıntılı düzenlemesi urun-duzenle.php içinde.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../inc/products.php';

bm_admin_require_login();

$bm_admin_active = 'urunler';
$bm_admin_title = 'Ürünler';
$message = '';

// Kurulum yapılmamışsa liste yerine yönlendirme göster
$ready = bm_config_exists() && bm_db_ready();

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        $action = $_POST['action'] ?? '';
        // Liste üzerinden sıra düzenleme kendi düğmeleriyle geliyor; toplu işlem
        // seçimine bakılmadan çalışsın diye action burada belirleniyor.
        if (isset($_POST['sira_kaydet'])) $action = 'save_order';
        if (isset($_POST['sira_yenile'])) $action = 'renumber';
        try {
            if ($action === 'delete') {
                // Kategori bağlantıları da temizlenir, yoksa ara tabloda
                // sahibi olmayan satırlar kalır ve sayımlar şaşar.
                bm_delete_product_categories((int)$_POST['id']);
                $st = bm_db()->prepare('DELETE FROM products WHERE id = ?');
                $st->execute([(int)$_POST['id']]);
                $message = 'ok|Ürün silindi.';

            } elseif ($action === 'toggle_featured') {
                $st = bm_db()->prepare('UPDATE products SET is_featured = 1 - is_featured WHERE id = ?');
                $st->execute([(int)$_POST['id']]);
                $message = 'ok|Vitrin durumu güncellendi.';

            } elseif ($action === 'toggle_active') {
                $st = bm_db()->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?');
                $st->execute([(int)$_POST['id']]);
                $message = 'ok|Yayın durumu güncellendi.';

            } elseif ($action === 'save_order') {
                // Listedeki Sıra kutuları: sira[ürün_id] = sayı
                $siralar = (array)($_POST['sira'] ?? []);
                $st = bm_db()->prepare('UPDATE products SET sort_order = ? WHERE id = ?');
                $sayac = 0;
                foreach ($siralar as $pid => $deger) {
                    if ((int)$pid <= 0) continue;
                    $st->execute([(int)$deger, (int)$pid]);
                    $sayac++;
                }
                $message = $sayac
                    ? 'ok|Sıra kaydedildi. Site ve panel aynı sırayı gösteriyor.'
                    : 'err|Kaydedilecek sıra kutusu bulunamadı.';

            } elseif ($action === 'renumber') {
                // Geçerli sırayı 10, 20, 30… diye sabitler. Aralarını boş bırakmak
                // sonradan iki ürünün arasına yenisini sokmayı kolaylaştırır.
                // Filtreden bağımsız: tüm katalog numaralandırılır.
                $satirlar = bm_db()
                    ->query('SELECT id FROM products ORDER BY sort_order, id DESC')
                    ->fetchAll();
                $st = bm_db()->prepare('UPDATE products SET sort_order = ? WHERE id = ?');
                $sira = 10;
                foreach ($satirlar as $r) {
                    $st->execute([$sira, (int)$r['id']]);
                    $sira += 10;
                }
                $message = 'ok|' . count($satirlar) . ' ürünün sıra numarası 10, 20, 30… olarak yenilendi.';

            } elseif ($action === 'bulk') {
                $ids = array_map('intval', (array)($_POST['ids'] ?? []));
                $ids = array_filter($ids);
                $op  = $_POST['bulk_op'] ?? '';
                if (!$ids) {
                    $message = 'err|Önce listeden ürün seçin.';
                } else {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $map = [
                        'activate'   => "UPDATE products SET is_active = 1 WHERE id IN ($in)",
                        'deactivate' => "UPDATE products SET is_active = 0 WHERE id IN ($in)",
                        'feature'    => "UPDATE products SET is_featured = 1 WHERE id IN ($in)",
                        'unfeature'  => "UPDATE products SET is_featured = 0 WHERE id IN ($in)",
                        'delete'     => "DELETE FROM products WHERE id IN ($in)",
                    ];
                    if (!isset($map[$op])) {
                        $message = 'err|Geçersiz işlem.';
                    } else {
                        if ($op === 'delete') {
                            foreach ($ids as $sil) bm_delete_product_categories($sil);
                        }
                        bm_db()->prepare($map[$op])->execute($ids);
                        $message = 'ok|' . count($ids) . ' ürün güncellendi.';
                    }
                }
            }
        } catch (Throwable $e) {
            $message = 'err|İşlem yapılamadı: ' . $e->getMessage();
        }
    }
}

$q       = trim((string)($_GET['q'] ?? ''));
$catSlug = trim((string)($_GET['kategori'] ?? ''));
$page    = max(1, (int)($_GET['sayfa'] ?? 1));

$categories = $ready ? bm_categories() : [];
$result = $ready ? bm_products([
    'q'                => $q,
    'category'         => $catSlug,
    'page'             => $page,
    'per_page'         => 30,
    'include_inactive' => true,
    // Panel listesi sitedeki sırayı gösterir (Sıra alanı, sonra en yeni).
    // Eskiden 'newest' idi: paneldeki liste ile sitedeki düzen tutmuyordu ve
    // sıra değişikliğinin etkisi panelde hiç görülmüyordu.
]) : ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];

function bm_admin_url($overrides = []) {
    $params = array_merge([
        'q'        => $_GET['q'] ?? '',
        'kategori' => $_GET['kategori'] ?? '',
        'sayfa'    => $_GET['sayfa'] ?? '',
    ], $overrides);
    $params = array_filter($params, function ($v) { return $v !== '' && $v !== null; });
    return '/teknikel/urunler.php' . ($params ? ('?' . http_build_query($params)) : '');
}

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Ürünler</h1>
    <div class="sub">
      <?php if ($ready): ?>
        Toplam <strong><?php echo number_format($result['total'], 0, ',', '.'); ?></strong> kayıt
      <?php else: ?>
        Ürün veritabanı henüz kurulmadı
      <?php endif; ?>
    </div>
  </div>
  <?php if ($ready): ?>
    <a href="/teknikel/urun-duzenle.php" class="btn">+ Yeni Ürün</a>
  <?php endif; ?>
</div>

<?php if ($message): [$type, $text] = explode('|', $message, 2); ?>
  <div class="flash <?php echo $type === 'ok' ? 'ok' : 'err'; ?>"><?php echo e($text); ?></div>
<?php endif; ?>

<?php if (!$ready): ?>
  <div class="card">
    <h2>Önce veritabanı kurulumu gerekiyor</h2>
    <p style="color:var(--muted);font-size:14px;line-height:1.7;margin-bottom:16px;">
      Ürün kataloğu MySQL veritabanında tutuluyor. <code>inc/config.php</code> dosyasındaki
      veritabanı bilgilerini girdikten sonra kurulumu bir kez çalıştırmanız yeterli.
    </p>
    <a href="/teknikel/veritabani-kurulum.php" class="btn">Kuruluma Git →</a>
  </div>
<?php else: ?>

  <div class="card">
    <form method="get" class="filter-bar">
      <input type="search" name="q" value="<?php echo e($q); ?>"
             placeholder="Ürün adı, kod veya açıklamada ara…">
      <select name="kategori">
        <option value="">Tüm kategoriler</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?php echo e($c['slug']); ?>"<?php echo $catSlug === $c['slug'] ? ' selected' : ''; ?>>
            <?php echo e($c['label']); ?> (<?php echo (int)$c['product_count']; ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn">Filtrele</button>
      <?php if ($q !== '' || $catSlug !== ''): ?>
        <a href="/teknikel/urunler.php" class="btn ghost">Temizle</a>
      <?php endif; ?>
      <a href="/teknikel/kategoriler.php" class="btn ghost">Kategoriler</a>
      <a href="/teknikel/urun-ice-aktar.php" class="btn ghost">CSV İçe Aktar</a>
    </form>
  </div>

  <?php if (!$result['items']): ?>
    <div class="card" style="text-align:center;padding:48px 24px;">
      <p style="color:var(--muted);font-size:15px;margin-bottom:18px;">
        <?php echo ($q !== '' || $catSlug !== '') ? 'Bu filtreye uyan ürün yok.' : 'Henüz ürün eklenmemiş.'; ?>
      </p>
      <a href="/teknikel/urun-duzenle.php" class="btn">İlk Ürünü Ekle</a>
    </div>
  <?php else: ?>

  <form method="post" id="bulkForm">
    <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
    <input type="hidden" name="action" value="bulk">

    <div class="card">
      <div class="bulk-bar">
        <label class="check"><input type="checkbox" id="checkAll"> Tümünü seç</label>
        <select name="bulk_op">
          <option value="">Toplu işlem seç…</option>
          <option value="feature">Vitrine ekle</option>
          <option value="unfeature">Vitrinden çıkar</option>
          <option value="activate">Yayına al</option>
          <option value="deactivate">Yayından kaldır</option>
          <option value="delete">Sil</option>
        </select>
        <button type="submit" class="btn ghost"
                onclick="return confirm('Seçili ürünlere bu işlem uygulanacak. Emin misin?');">Uygula</button>
        <span class="bulk-count" id="bulkCount"></span>
        <?php /* Sıra düzenleme: tablodaki Sıra kutularını tek seferde kaydeder.
                 "Yenile" ise mevcut sırayı 10, 20, 30… diye sabitler. */ ?>
        <span class="bulk-sira">
          <button type="submit" name="sira_kaydet" value="1" class="btn">Sırayı Kaydet</button>
          <button type="submit" name="sira_yenile" value="1" class="btn ghost"
                  onclick="return confirm('Tüm ürünlerin sıra numarası şu andaki sıraya göre 10, 20, 30… olarak yenilenecek. Listenin görünen düzeni değişmez. Devam edilsin mi?');"
                  title="Mevcut sırayı 10, 20, 30… numaralarına sabitler">Sıra No Yenile</button>
        </span>
      </div>

      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th class="col-check"></th>
              <th>Ürün</th>
              <th>Kod</th>
              <th>Kategori</th>
              <th>Vitrin</th>
              <th>Durum</th>
              <th>Sıra</th>
              <th class="col-actions">İşlem</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($result['items'] as $p): ?>
              <tr class="<?php echo $p['is_active'] ? '' : 'row-passive'; ?>">
                <td class="col-check">
                  <input type="checkbox" name="ids[]" value="<?php echo (int)$p['id']; ?>" class="row-check">
                </td>
                <td>
                  <div class="prod-cell">
                    <span class="thumb">
                      <?php if (!empty($p['image'])): ?>
                        <img src="<?php echo e(bm_src($p['image'])); ?>" alt="">
                      <?php else: ?>
                        <?php echo e($p['emoji'] ?: '⚙️'); ?>
                      <?php endif; ?>
                    </span>
                    <a href="/teknikel/urun-duzenle.php?id=<?php echo (int)$p['id']; ?>" class="prod-title">
                      <?php echo e($p['title']); ?>
                    </a>
                  </div>
                </td>
                <td class="mono"><?php echo e($p['code'] ?: '—'); ?></td>
                <td><?php echo e($p['cat_label'] ?: '—'); ?></td>
                <td>
                  <button type="submit" form="row<?php echo (int)$p['id']; ?>feat"
                          class="pill <?php echo $p['is_featured'] ? 'on' : ''; ?>"
                          title="Ana sayfa vitrininde göster/gizle">
                    <?php echo $p['is_featured'] ? '★ Vitrinde' : '☆ Değil'; ?>
                  </button>
                </td>
                <td>
                  <button type="submit" form="row<?php echo (int)$p['id']; ?>act"
                          class="pill <?php echo $p['is_active'] ? 'on' : 'off'; ?>">
                    <?php echo $p['is_active'] ? 'Yayında' : 'Gizli'; ?>
                  </button>
                </td>
                <td>
                  <input type="number" name="sira[<?php echo (int)$p['id']; ?>]"
                         value="<?php echo (int)$p['sort_order']; ?>" class="input-narrow"
                         title="Küçük sayı önce gelir">
                </td>
                <td class="col-actions">
                  <a href="/urun/<?php echo e($p['slug']); ?>" target="_blank" class="link-btn">Gör</a>
                  <a href="/teknikel/urun-duzenle.php?id=<?php echo (int)$p['id']; ?>" class="link-btn">Düzenle</a>
                  <button type="submit" form="row<?php echo (int)$p['id']; ?>del" class="link-btn danger"
                          onclick="return confirm('<?php echo e($p['title']); ?> silinecek. Emin misin?');">Sil</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </form>

  <?php
    // Satır içi tek-ürün işlemleri: form'lar tablonun dışında duruyor ve
    // butonlara form="..." ile bağlanıyor — iç içe form olmaması için.
    foreach ($result['items'] as $p):
      $id = (int)$p['id'];
      foreach ([['feat','toggle_featured'], ['act','toggle_active'], ['del','delete']] as [$suffix, $act]):
  ?>
    <form id="row<?php echo $id . $suffix; ?>" method="post" class="hidden-form">
      <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
      <input type="hidden" name="action" value="<?php echo $act; ?>">
      <input type="hidden" name="id" value="<?php echo $id; ?>">
    </form>
  <?php endforeach; endforeach; ?>

  <?php if ($result['pages'] > 1): ?>
    <?php
      $start = max(1, $result['page'] - 2);
      $end   = min($result['pages'], $result['page'] + 2);
    ?>
    <nav class="admin-pagination">
      <?php if ($result['page'] > 1): ?>
        <a href="<?php echo e(bm_admin_url(['sayfa' => $result['page'] - 1])); ?>">← Önceki</a>
      <?php endif; ?>
      <?php if ($start > 1): ?>
        <a href="<?php echo e(bm_admin_url(['sayfa' => 1])); ?>">1</a>
        <?php if ($start > 2): ?><span class="dots">…</span><?php endif; ?>
      <?php endif; ?>
      <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?php echo e(bm_admin_url(['sayfa' => $i])); ?>"
           class="<?php echo $i === $result['page'] ? 'active' : ''; ?>"><?php echo $i; ?></a>
      <?php endfor; ?>
      <?php if ($end < $result['pages']): ?>
        <?php if ($end < $result['pages'] - 1): ?><span class="dots">…</span><?php endif; ?>
        <a href="<?php echo e(bm_admin_url(['sayfa' => $result['pages']])); ?>"><?php echo $result['pages']; ?></a>
      <?php endif; ?>
      <?php if ($result['page'] < $result['pages']): ?>
        <a href="<?php echo e(bm_admin_url(['sayfa' => $result['page'] + 1])); ?>">Sonraki →</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>

  <?php endif; ?>
<?php endif; ?>

<script>
(function () {
  var all = document.getElementById('checkAll');
  var counter = document.getElementById('bulkCount');
  if (!all) return;
  var boxes = Array.prototype.slice.call(document.querySelectorAll('.row-check'));

  function refresh() {
    var n = boxes.filter(function (b) { return b.checked; }).length;
    counter.textContent = n ? (n + ' ürün seçildi') : '';
    all.checked = n === boxes.length && n > 0;
  }
  all.addEventListener('change', function () {
    boxes.forEach(function (b) { b.checked = all.checked; });
    refresh();
  });
  boxes.forEach(function (b) { b.addEventListener('change', refresh); });
})();
</script>
<?php require __DIR__ . '/layout-bottom.php'; ?>
