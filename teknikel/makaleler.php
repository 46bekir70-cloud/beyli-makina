<?php
/**
 * Makale yönetimi — liste, arama, etiket filtresi, hızlı işlemler.
 * Tek makalenin düzenlemesi makale-duzenle.php içinde.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../inc/articles.php';

bm_admin_require_login();

$bm_admin_active = 'makaleler';
$bm_admin_title = 'Makaleler';
$message = '';

$ready = bm_config_exists() && bm_db_ready();
if ($ready) bm_articles_migrate();   // tablo yoksa bir kez oluşturur

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'delete') {
                $st = bm_db()->prepare('DELETE FROM articles WHERE id = ?');
                $st->execute([(int)$_POST['id']]);
                $message = 'ok|Makale silindi.';

            } elseif ($action === 'toggle_active') {
                $st = bm_db()->prepare('UPDATE articles SET is_active = 1 - is_active WHERE id = ?');
                $st->execute([(int)$_POST['id']]);
                $message = 'ok|Yayın durumu güncellendi.';

            } elseif ($action === 'toggle_featured') {
                $st = bm_db()->prepare('UPDATE articles SET is_featured = 1 - is_featured WHERE id = ?');
                $st->execute([(int)$_POST['id']]);
                $message = 'ok|Öne çıkarma durumu güncellendi.';
            }
        } catch (Throwable $e) {
            $message = 'err|İşlem yapılamadı: ' . $e->getMessage();
        }
    }
}

$q    = trim((string)($_GET['q'] ?? ''));
$tag  = trim((string)($_GET['etiket'] ?? ''));
$page = max(1, (int)($_GET['sayfa'] ?? 1));

$etiketler = $ready ? bm_article_tags(true) : [];
$sonuc = $ready ? bm_articles([
    'q'                => $q,
    'tag'              => $tag,
    'page'             => $page,
    'per_page'         => 25,
    'include_inactive' => true,
]) : ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];

function bm_makale_url($overrides = []) {
    $params = array_merge([
        'q'      => $_GET['q'] ?? '',
        'etiket' => $_GET['etiket'] ?? '',
        'sayfa'  => $_GET['sayfa'] ?? '',
    ], $overrides);
    $params = array_filter($params, function ($v) { return $v !== '' && $v !== null; });
    return '/teknikel/makaleler.php' . ($params ? ('?' . http_build_query($params)) : '');
}

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Makaleler</h1>
    <div class="sub">
      <?php if ($ready): ?>
        Toplam <strong><?php echo number_format($sonuc['total'], 0, ',', '.'); ?></strong> yazı —
        <a href="/blog" target="_blank" style="color:var(--blue);">blog sayfasını gör ↗</a>
      <?php else: ?>
        Veritabanı henüz kurulmadı
      <?php endif; ?>
    </div>
  </div>
  <?php if ($ready): ?>
    <a href="/teknikel/makale-duzenle.php" class="btn">+ Yeni Makale</a>
  <?php endif; ?>
</div>

<?php if ($message): [$type, $text] = explode('|', $message, 2); ?>
  <div class="flash <?php echo $type === 'ok' ? 'ok' : 'err'; ?>"><?php echo e($text); ?></div>
<?php endif; ?>

<?php if (!$ready): ?>
  <div class="card">
    <h2>Önce veritabanı kurulumu gerekiyor</h2>
    <p style="color:var(--muted);font-size:14px;line-height:1.7;margin-bottom:16px;">
      Makaleler ürünlerle aynı veritabanında tutuluyor. Kurulumu bir kez
      çalıştırman yeterli; makale tablosu kendiliğinden oluşuyor.
    </p>
    <a href="/teknikel/veritabani-kurulum.php" class="btn">Kuruluma Git →</a>
  </div>
<?php else: ?>

  <div class="card">
    <p class="card-hint">
      Blog sayfasının en üstündeki başlık, açıklama ve görsel
      <a href="/teknikel/sayfa-duzenle.php?slug=blog" style="color:var(--blue);">Sayfalar → Blog</a>
      ekranından düzenleniyor. Buradaki liste ise o sayfada görünen yazılar.
      Etiketler ziyaretçiye filtre çipleri olarak çıkıyor — aynı etiketi
      birden fazla yazıda kullanman yeterli, ayrıca kategori tanımlamana gerek yok.
    </p>
    <form method="get" class="filter-bar">
      <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Başlık, özet veya içerikte ara…">
      <select name="etiket">
        <option value="">Tüm etiketler</option>
        <?php foreach ($etiketler as $t): ?>
          <option value="<?php echo e($t['slug']); ?>"<?php echo $tag === $t['slug'] ? ' selected' : ''; ?>>
            <?php echo e($t['label']); ?> (<?php echo (int)$t['count']; ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn">Filtrele</button>
      <?php if ($q !== '' || $tag !== ''): ?>
        <a href="/teknikel/makaleler.php" class="btn ghost">Temizle</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!$sonuc['items']): ?>
    <div class="card" style="text-align:center;padding:48px 24px;">
      <p style="color:var(--muted);font-size:15px;margin-bottom:18px;">
        <?php echo ($q !== '' || $tag !== '') ? 'Bu filtreye uyan makale yok.' : 'Henüz makale eklenmemiş.'; ?>
      </p>
      <a href="/teknikel/makale-duzenle.php" class="btn">İlk Makaleyi Yaz</a>
    </div>
  <?php else: ?>

    <div class="card">
      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Makale</th>
              <th>Etiketler</th>
              <th>Tarih</th>
              <th>Öne çıkan</th>
              <th>Durum</th>
              <th class="col-actions">İşlem</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sonuc['items'] as $m): ?>
              <tr class="<?php echo $m['is_active'] ? '' : 'row-passive'; ?>">
                <td>
                  <div class="prod-cell">
                    <span class="thumb">
                      <?php if (!empty($m['image'])): ?>
                        <img src="<?php echo e(bm_src($m['image'])); ?>" alt="">
                      <?php else: ?>📄<?php endif; ?>
                    </span>
                    <a href="/teknikel/makale-duzenle.php?id=<?php echo (int)$m['id']; ?>" class="prod-title">
                      <?php echo e($m['title']); ?>
                    </a>
                  </div>
                </td>
                <td><?php echo e(implode(', ', bm_article_tag_list($m['tags'] ?? '')) ?: '—'); ?></td>
                <td class="mono"><?php echo e(bm_article_date($m) ?: '—'); ?></td>
                <td>
                  <button type="submit" form="mk<?php echo (int)$m['id']; ?>feat"
                          class="pill <?php echo $m['is_featured'] ? 'on' : ''; ?>"
                          title="Öne çıkan yazı olarak işaretle">
                    <?php echo $m['is_featured'] ? '★ Evet' : '☆ Hayır'; ?>
                  </button>
                </td>
                <td>
                  <button type="submit" form="mk<?php echo (int)$m['id']; ?>act"
                          class="pill <?php echo $m['is_active'] ? 'on' : 'off'; ?>">
                    <?php echo $m['is_active'] ? 'Yayında' : 'Taslak'; ?>
                  </button>
                </td>
                <td class="col-actions">
                  <a href="/makale/<?php echo e($m['slug']); ?>" target="_blank" class="link-btn">Gör</a>
                  <a href="/teknikel/makale-duzenle.php?id=<?php echo (int)$m['id']; ?>" class="link-btn">Düzenle</a>
                  <button type="submit" form="mk<?php echo (int)$m['id']; ?>del" class="link-btn danger"
                          onclick="return confirm('<?php echo e($m['title']); ?> silinecek. Emin misin?');">Sil</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php
      // Satır içi işlemler: formlar tablonun dışında, butonlara form="" ile bağlı
      foreach ($sonuc['items'] as $m):
        $id = (int)$m['id'];
        foreach ([['feat','toggle_featured'], ['act','toggle_active'], ['del','delete']] as [$ek, $act]):
    ?>
      <form id="mk<?php echo $id . $ek; ?>" method="post" class="hidden-form">
        <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
        <input type="hidden" name="action" value="<?php echo $act; ?>">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
      </form>
    <?php endforeach; endforeach; ?>

    <?php if ($sonuc['pages'] > 1): ?>
      <nav class="admin-pagination">
        <?php if ($sonuc['page'] > 1): ?>
          <a href="<?php echo e(bm_makale_url(['sayfa' => $sonuc['page'] - 1])); ?>">← Önceki</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $sonuc['pages']; $i++): ?>
          <a href="<?php echo e(bm_makale_url(['sayfa' => $i])); ?>"
             class="<?php echo $i === $sonuc['page'] ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($sonuc['page'] < $sonuc['pages']): ?>
          <a href="<?php echo e(bm_makale_url(['sayfa' => $sonuc['page'] + 1])); ?>">Sonraki →</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/layout-bottom.php'; ?>
