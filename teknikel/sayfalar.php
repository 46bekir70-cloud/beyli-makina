<?php
/**
 * Sayfalar merkezi.
 *
 * Sitedeki her sayfa buradan görünür — hem koda gömülü sabit sayfalar
 * (Ana Sayfa, Ürünler, İletişim, Hizmetler) hem de panelden eklenen sayfalar.
 * Sabit sayfalar silinemez ama yazıları ve görselleri kendi ekranlarından
 * düzenlenir; bu liste onlara giden kapı.
 */
require_once __DIR__ . '/config.php';
// Güvenlik: yetki kontrolü EN BAŞTA. Eskiden bu kontrol layout-top.php
// içinde, yani POST işlendikten SONRA devreye giriyordu; oturumu olmayan biri
// form gönderip içeriği değiştirebilirdi (çıktı görmese bile kayıt olurdu).
bm_admin_require_login();
$content = bm_load_content();
$bm_admin_active = 'sayfalar';
$bm_admin_title = 'Sayfalar';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $title = trim($_POST['new_title'] ?? '');
            if ($title === '') {
                $message = 'err|Sayfa başlığı boş olamaz.';
            } else {
                $slug = bm_slugify($title);
                $baseSlug = $slug; $n = 2;
                while (bm_find_page($content, $slug)) { $slug = $baseSlug . '-' . $n; $n++; }
                $maxOrder = 0;
                foreach (($content['pages'] ?? []) as $p) { $maxOrder = max($maxOrder, $p['nav_order'] ?? 0); }
                $content['pages'][] = [
                    'slug' => $slug,
                    'title' => $title,
                    'hero_tag' => '',
                    'hero_subtitle' => '',
                    'html' => '<p>Bu sayfanın içeriğini düzenlemek için "Düzenle" butonuna tıklayın.</p>',
                    'in_nav' => true,
                    'nav_order' => $maxOrder + 10,
                    'nav_group' => trim($_POST['new_group'] ?? ''),
                ];
                bm_save_content($content);
                $message = 'ok|"' . $title . '" sayfası oluşturuldu. Şimdi içeriğini düzenleyebilirsin.';
                $content = bm_load_content();
            }
        } elseif ($action === 'delete') {
            $slug = $_POST['slug'] ?? '';
            $content['pages'] = array_values(array_filter($content['pages'] ?? [], function ($p) use ($slug) {
                return $p['slug'] !== $slug;
            }));
            // Ana sayfadaki "Devamını Oku" butonu bu sayfaya işaret ediyorsa bağlantıyı temizle
            if (($content['about_teaser']['link_target'] ?? '') === $slug) {
                $content['about_teaser']['link_target'] = '';
            }
            bm_save_content($content);
            $message = 'ok|Sayfa silindi.';
            $content = bm_load_content();
        } elseif ($action === 'update_list') {
            $titles = $_POST['title'] ?? [];
            $slugs  = $_POST['slug'] ?? [];
            $orders = $_POST['nav_order'] ?? [];
            $groups = $_POST['nav_group'] ?? [];
            $inNav  = $_POST['in_nav'] ?? []; // sadece işaretli olanların slug'ı gelir
            foreach ($content['pages'] as &$p) {
                $idx = array_search($p['slug'], $slugs);
                if ($idx !== false) {
                    if (trim($titles[$idx] ?? '') !== '') $p['title'] = trim($titles[$idx]);
                    $p['nav_order'] = (int)($orders[$idx] ?? 0);
                    $p['nav_group'] = trim((string)($groups[$idx] ?? ''));
                    $p['in_nav'] = in_array($p['slug'], $inNav, true);
                }
            }
            unset($p);
            bm_save_content($content);
            $message = 'ok|Sayfa listesi güncellendi.';
            $content = bm_load_content();
        }
    }
}
[$msgType, $msgText] = array_pad(explode('|', $message, 2), 2, '');

$pages = $content['pages'] ?? [];
usort($pages, function ($a, $b) { return ($a['nav_order'] ?? 0) <=> ($b['nav_order'] ?? 0); });

// Listeyi kaydet butonu kenar çubuğunda; kaydedilecek sayfa yoksa pasif kalsın
$bm_admin_save_form = $pages ? 'sayfaListForm' : '';

// Koda gömülü, silinemeyen sayfalar
$sabitSayfalar = [
    [
        'ad'      => 'Ana Sayfa',
        'adres'   => '/',
        'aciklama'=> 'Slayt gösterisi, üst başlık, hakkımızda özeti, vitrin ve teklif kutusu',
        'duzenle' => '/teknikel/anasayfa.php',
    ],
    [
        'ad'      => 'Ürünler',
        'adres'   => '/urunler',
        'aciklama'=> 'Sayfa başlığı görseli ve yazıları. Ürünlerin kendisi "Ürünler" bölümünde.',
        'duzenle' => '/teknikel/urunler-sayfasi.php',
    ],
    [
        'ad'      => 'İletişim / Teklif',
        'adres'   => '/teklif',
        'aciklama'=> 'Başlık görseli, iletişim butonları, sosyal medya hesapları, form seçenekleri',
        'duzenle' => '/teknikel/teklif-sayfasi.php',
    ],
    [
        'ad'      => 'Hizmetler',
        'adres'   => '/#hizmetler',
        'aciklama'=> 'Hizmet kartları ve her hizmetin kendi tanıtım sayfası',
        'duzenle' => '/teknikel/hizmetler.php',
    ],
    [
        'ad'      => 'Makaleler (Blog)',
        'adres'   => '/blog',
        'aciklama'=> 'Sektörel yazılar, teknik makaleler ve blog içerikleri',
        'duzenle' => '/teknikel/makaleler.php',
    ],
];

// Menüde şu an kullanılan grup adları — yeni sayfa eklerken öneri olarak çıkar
$mevcutGruplar = [];
foreach ($pages as $p) {
    $g = trim((string)($p['nav_group'] ?? ''));
    if ($g !== '' && !in_array($g, $mevcutGruplar, true)) $mevcutGruplar[] = $g;
}

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Sayfalar</h1>
    <div class="sub">Sitedeki bütün sayfalar ve üst menü düzeni</div>
  </div>
  <a href="/teknikel/index.php" class="logout-link">← Panele Dön</a>
</div>

<?php if ($msgText): ?><div class="flash <?php echo $msgType; ?>"><?php echo e($msgText); ?></div><?php endif; ?>

<div class="card">
  <h2>🔒 Sabit Sayfalar</h2>
  <p class="card-hint">
    Bunlar sitenin işleyişi için gerekli sayfalar, silinemezler — ama yazıları ve
    görselleri düzenlenebilir. Her biri kendi ekranından yönetiliyor.
  </p>
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Sayfa</th><th>Adres</th><th>Neler düzenlenir</th><th class="col-actions">İşlem</th></tr>
      </thead>
      <tbody>
        <?php foreach ($sabitSayfalar as $s): ?>
          <tr>
            <td><strong><?php echo e($s['ad']); ?></strong></td>
            <td class="mono"><?php echo e($s['adres']); ?></td>
            <td style="color:var(--muted);font-size:13px;"><?php echo e($s['aciklama']); ?></td>
            <td class="col-actions" style="white-space:nowrap;">
              <a href="<?php echo e($s['adres']); ?>" target="_blank" class="link-btn">Gör</a>
              <a href="<?php echo e($s['duzenle']); ?>" class="link-btn">Düzenle</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="card-hint" style="margin:16px 0 0;">
    Bütün sayfalardaki sabit yazılar (başlıklar, buton metinleri, "Detay &amp; Teklif" gibi)
    tek yerden yönetiliyor: <a href="/teknikel/site-yazilari.php" style="color:var(--blue);font-weight:600;">Site Yazıları →</a>
  </p>
</div>

<div class="card">
  <h2>➕ Yeni Sayfa Ekle</h2>
  <form method="post" class="filter-bar">
    <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
    <input type="hidden" name="action" value="create">
    <input type="text" name="new_title" placeholder="Sayfa başlığı — örn. Referanslar, Sertifikalar, Kariyer" required>
    <input type="text" name="new_group" list="grupOnerileri" placeholder="Menü grubu (isteğe bağlı)">
    <button type="submit" class="btn">Oluştur</button>
  </form>
  <datalist id="grupOnerileri">
    <?php foreach ($mevcutGruplar as $g): ?><option value="<?php echo e($g); ?>"><?php endforeach; ?>
  </datalist>
</div>

<div class="card">
  <h2>📄 Kendi Sayfaların <span class="count">(<?php echo count($pages); ?>)</span></h2>
  <p class="card-hint">
    <strong>Menü grubu:</strong> aynı grup adını yazdığın sayfalar üst menüde tek bir
    açılır listenin altında toplanır — tıpkı "Hizmetlerimiz" gibi. Örneğin iki sayfaya
    da <em>Kurumsal</em> yazarsan menüde tek bir "Kurumsal ▾" sekmesi çıkar, ikisi de
    altında durur. Boş bırakırsan sayfa menüde tek başına görünür.
    Grubun menüdeki yeri, o gruptaki en küçük sıra numarasına göre belirlenir.<br>
    <em>Not:</em> menü ekrana sığmazsa site zaten kendiliğinden bir "Diğer" sekmesi
    açıyor; kendi grubuna da "Diğer" adını verirsen iki tane çıkabilir. Bu otomatik
    sekmenin adını <a href="/teknikel/site-yazilari.php" style="color:var(--blue);">Site Yazıları</a>'ndan
    değiştirebilirsin.
  </p>

  <?php if (empty($pages)): ?>
    <p class="hint">Henüz kendi sayfan yok. Yukarıdan oluşturabilirsin.</p>
  <?php else: ?>
  <form method="post" id="sayfaListForm">
    <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
    <input type="hidden" name="action" value="update_list">
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Başlık</th>
            <th>Menü Grubu</th>
            <th>Menüde</th>
            <th>Sıra</th>
            <th>Adres</th>
            <th class="col-actions">İşlem</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pages as $p): ?>
            <tr>
              <td>
                <input type="hidden" name="slug[]" value="<?php echo e($p['slug']); ?>">
                <input type="text" name="title[]" value="<?php echo e($p['title']); ?>" style="min-width:170px;">
              </td>
              <td>
                <input type="text" name="nav_group[]" list="grupOnerileri"
                       value="<?php echo e($p['nav_group'] ?? ''); ?>"
                       placeholder="—" style="min-width:130px;">
              </td>
              <td>
                <label class="check">
                  <input type="checkbox" name="in_nav[]" value="<?php echo e($p['slug']); ?>"
                         <?php echo !empty($p['in_nav']) ? 'checked' : ''; ?>>
                  Görünsün
                </label>
              </td>
              <td><input type="number" name="nav_order[]" value="<?php echo (int)($p['nav_order'] ?? 0); ?>" class="input-narrow"></td>
              <td class="mono">/<?php echo e($p['slug']); ?></td>
              <td class="col-actions" style="white-space:nowrap;">
                <a href="/<?php echo urlencode($p['slug']); ?>" target="_blank" class="link-btn">Gör</a>
                <a href="/teknikel/sayfa-duzenle.php?slug=<?php echo urlencode($p['slug']); ?>" class="link-btn">Düzenle</a>
                <button type="submit" form="delpage<?php echo e($p['slug']); ?>" class="link-btn danger"
                        onclick="return confirm('<?php echo e($p['title']); ?> sayfası silinecek. Emin misin?');">Sil</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php /* Kaydet butonu kenar çubuğunda sabit duruyor — layout-top.php */ ?>
  </form>

  <?php // Silme formları listenin dışında: form içinde form olmaz ?>
  <?php foreach ($pages as $p): ?>
    <form id="delpage<?php echo e($p['slug']); ?>" method="post" class="hidden-form">
      <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="slug" value="<?php echo e($p['slug']); ?>">
    </form>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/layout-bottom.php'; ?>
