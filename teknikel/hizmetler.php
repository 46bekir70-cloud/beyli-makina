<?php
/**
 * Hizmet listesi — ekle, sırala, sil, detay sayfasını aç/kapa.
 * Tek hizmetin ayrıntılı düzenlemesi hizmet-duzenle.php içinde.
 */
require_once __DIR__ . '/config.php';

bm_admin_require_login();

$content = bm_load_content();
$bm_admin_active = 'hizmetler';
$bm_admin_title = 'Hizmetler';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $title = trim($_POST['new_title'] ?? '');
            if ($title === '') {
                $message = 'err|Hizmet adı boş olamaz.';
            } else {
                // Slug adreste kullanılıyor, benzersiz olmalı
                $slug = bm_slugify($title);
                $base = $slug; $n = 2;
                while (bm_find_service($content, $slug)) { $slug = $base . '-' . $n; $n++; }

                $maxOrder = 0;
                foreach (($content['services'] ?? []) as $s) {
                    $maxOrder = max($maxOrder, (int)($s['sort_order'] ?? 0));
                }
                $content['services'][] = [
                    'slug'          => $slug,
                    'title'         => $title,
                    'desc'          => '',
                    'card_image'    => '',
                    'card_focus'    => '50% 50%',
                    'card_blur'     => BM_CARD_BLUR_DEFAULT,
                    'card_bright'   => BM_CARD_BRIGHT_DEFAULT,
                    'sort_order'    => $maxOrder + 10,
                    'has_page'      => true,
                    'hero_tag'      => 'Hizmetlerimiz',
                    'hero_subtitle' => '',
                    'image'         => '',
                    'html'          => '',
                    'stats'         => [],
                    'machines'      => [],
                ];
                bm_save_content($content);
                header('Location: /teknikel/hizmet-duzenle.php?slug=' . urlencode($slug) . '&yeni=1');
                exit;
            }

        } elseif ($action === 'delete') {
            $slug = $_POST['slug'] ?? '';
            $content['services'] = array_values(array_filter($content['services'] ?? [],
                function ($s) use ($slug) { return ($s['slug'] ?? '') !== $slug; }));
            bm_save_content($content);
            $message = 'ok|Hizmet silindi.';

        } elseif ($action === 'update_list') {
            $slugs  = (array)($_POST['slug'] ?? []);
            $orders = (array)($_POST['sort_order'] ?? []);
            $pages  = (array)($_POST['has_page'] ?? []); // sadece işaretliler gelir
            foreach ($content['services'] as &$s) {
                $idx = array_search($s['slug'] ?? '', $slugs, true);
                if ($idx === false) continue;
                $s['sort_order'] = (int)($orders[$idx] ?? 0);
                $s['has_page'] = in_array($s['slug'] ?? '', $pages, true);
            }
            unset($s);
            bm_save_content($content);
            $message = 'ok|Sıralama ve görünürlük kaydedildi.';
        }
        $content = bm_load_content();
    }
}

$services = bm_services($content);

// Kaydet butonu kenar çubuğunda; liste boşsa gönderilecek form da yok.
$bm_admin_save_form = $services ? 'hizmetListForm' : '';

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Hizmetler</h1>
    <div class="sub">
      Ana sayfadaki hizmet kartları ve her hizmetin kendi tanıtım sayfası
    </div>
  </div>
  <a href="/teknikel/index.php" class="logout-link">← Panele Dön</a>
</div>

<?php if ($message): [$type, $text] = explode('|', $message, 2); ?>
  <div class="flash <?php echo $type === 'ok' ? 'ok' : 'err'; ?>"><?php echo e($text); ?></div>
<?php endif; ?>

<div class="card">
  <h2>Yeni Hizmet</h2>
  <form method="post" class="filter-bar">
    <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
    <input type="hidden" name="action" value="create">
    <input type="text" name="new_title" placeholder="Hizmet adı — örn. CNC Taşlama" required>
    <button type="submit" class="btn">Ekle ve Düzenle</button>
  </form>
</div>

<?php if ($services): ?>
<div class="card">
  <h2>Mevcut Hizmetler</h2>
  <form method="post" id="hizmetListForm">
    <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
    <input type="hidden" name="action" value="update_list">
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Hizmet</th>
            <th>Adres</th>
            <th>Makine</th>
            <th>Detay Sayfası</th>
            <th>Sıra</th>
            <th class="col-actions">İşlem</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($services as $s): ?>
            <?php
              $machineCount = count(array_filter((array)($s['machines'] ?? []),
                  function ($m) { return trim((string)($m['name'] ?? '')) !== ''; }));
            ?>
            <tr>
              <td>
                <input type="hidden" name="slug[]" value="<?php echo e($s['slug']); ?>">
                <div class="prod-cell">
                  <span class="thumb">
                    <?php if (!empty($s['card_image'])): ?>
                      <img src="<?php echo e(bm_src($s['card_image'])); ?>" alt="">
                    <?php else: ?>
                      <?php echo e(mb_substr($s['title'] ?? '?', 0, 1, 'UTF-8')); ?>
                    <?php endif; ?>
                  </span>
                  <a href="/teknikel/hizmet-duzenle.php?slug=<?php echo urlencode($s['slug']); ?>" class="prod-title">
                    <?php echo e($s['title']); ?>
                  </a>
                </div>
              </td>
              <td class="mono">/hizmet/<?php echo e($s['slug']); ?></td>
              <td><?php echo $machineCount ?: '—'; ?></td>
              <td>
                <label class="check">
                  <input type="checkbox" name="has_page[]" value="<?php echo e($s['slug']); ?>"
                         <?php echo !empty($s['has_page']) ? 'checked' : ''; ?>>
                  Açık
                </label>
              </td>
              <td>
                <input type="number" name="sort_order[]" value="<?php echo (int)($s['sort_order'] ?? 0); ?>" class="input-narrow">
              </td>
              <td class="col-actions">
                <?php if (!empty($s['has_page'])): ?>
                  <a href="/hizmet/<?php echo e($s['slug']); ?>" target="_blank" class="link-btn">Gör</a>
                <?php endif; ?>
                <a href="/teknikel/hizmet-duzenle.php?slug=<?php echo urlencode($s['slug']); ?>" class="link-btn">Düzenle</a>
                <button type="submit" form="delsvc<?php echo e($s['slug']); ?>" class="link-btn danger"
                        onclick="return confirm('<?php echo e($s['title']); ?> silinecek. Emin misin?');">Sil</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php /* Kaydet butonu kenar çubuğunda sabit duruyor — layout-top.php */ ?>
  </form>
</div>

<?php foreach ($services as $s): ?>
  <form id="delsvc<?php echo e($s['slug']); ?>" method="post" class="hidden-form">
    <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="slug" value="<?php echo e($s['slug']); ?>">
  </form>
<?php endforeach; ?>

<div class="card">
  <h2>Nasıl çalışır?</h2>
  <p style="color:var(--muted);font-size:14px;line-height:1.8;">
    <strong>Detay Sayfası "Açık"</strong> olan hizmetlerin ana sayfadaki kartı tıklanabilir olur ve
    üst menüdeki "Hizmetlerimiz" açılır listesinde görünür. Kapatırsan kart yine görünür ama
    tıklanamaz — sayfayı hazırlarken kapalı tutabilirsin.<br>
    <strong>Sıra</strong> küçük sayı önce gelir. Buradaki sıra ana sayfadaki hizmet
    kartlarında, üst menüdeki "Hizmetlerimiz" listesinde ve footer'da aynı şekilde
    geçerlidir. Araları 10'lu vermek (10, 20, 30) sonradan araya bir hizmet sokmayı
    kolaylaştırır.<br>
    <strong>Düzenle</strong> ekranında hizmetin tanıtım metnini, öne çıkan sayıları
    (personel sayısı, tezgah adedi, kapasite gibi) ve makineleri teknik özellikleriyle girersin.
  </p>
</div>
<?php endif; ?>

<?php require __DIR__ . '/layout-bottom.php'; ?>
