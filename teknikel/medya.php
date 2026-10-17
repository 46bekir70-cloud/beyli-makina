<?php
/**
 * Görsel kütüphanesi — bilgisayardaki dosya yöneticisi gibi çalışır.
 *
 * Solda klasör ağacı, sağda o klasörün içeriği. Klasör oluşturma, yeniden
 * adlandırma, taşıma ve silme burada yapılıyor. Dosya işlemlerinin kendisi
 * medya-lib.php içinde; bu dosya yalnızca ekranı ve form isteklerini yönetiyor.
 *
 * 1000+ ürün hedeflendiği için üç şey korunuyor:
 *   1) Arama — bütün klasörlerde birden arar.
 *   2) Sayfalama — tek klasörde yüzlerce dosya olabilir.
 *   3) "Nerede kullanılıyor" — hangi dosyanın silinebileceği görünmeden
 *      yüzlerce dosya arasında temizlik yapılamıyor.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/medya-lib.php';

bm_admin_require_login();

$content = bm_load_content();
$bm_admin_active = 'medya';
$bm_admin_title = 'Görseller';
$message = '';

$uploadDir = bm_media_root();
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

define('BM_MEDIA_PER_PAGE', 48);

// Bulunulan klasör
$klasor = bm_media_guvenli_yol($_GET['klasor'] ?? $_POST['klasor'] ?? '');
if ($klasor !== '' && !is_dir(bm_media_tam_yol($klasor) ?: '')) $klasor = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        $eylem = $_POST['action'] ?? '';
        $hedef = (string)($_POST['rel'] ?? '');
        switch ($eylem) {
            case 'klasor_ekle':
                [$ok, $msj] = bm_media_klasor_ekle($klasor, $_POST['ad'] ?? '');
                break;
            case 'klasor_adlandir':
                [$ok, $msj] = bm_media_klasor_adlandir($hedef, $_POST['ad'] ?? '');
                break;
            case 'klasor_sil':
                [$ok, $msj] = bm_media_klasor_sil($hedef);
                if ($ok && ($hedef === $klasor || strpos($klasor, $hedef . '/') === 0)) {
                    $klasor = '';   // silinen klasörün içindeysek köke dön
                }
                break;
            case 'dosya_adlandir':
                [$ok, $msj] = bm_media_dosya_adlandir($hedef, $_POST['ad'] ?? '');
                break;
            case 'dosya_tasi':
                [$ok, $msj] = bm_media_dosya_tasi($hedef, $_POST['hedef_klasor'] ?? '');
                break;
            case 'delete':
            case 'dosya_sil':
                [$ok, $msj] = bm_media_dosya_sil($hedef);
                break;
            case 'toplu':
                $secili = array_filter((array)($_POST['secili'] ?? []));
                $islem  = $_POST['toplu_islem'] ?? '';
                if (!$secili) { [$ok, $msj] = [false, 'Önce dosya seç.']; break; }
                $sayac = 0; $hata = 0;
                foreach ($secili as $rel) {
                    if ($islem === 'sil')        [$b,] = bm_media_dosya_sil($rel);
                    elseif ($islem === 'tasi')   [$b,] = bm_media_dosya_tasi($rel, $_POST['hedef_klasor'] ?? '');
                    else                         $b = false;
                    $b ? $sayac++ : $hata++;
                }
                [$ok, $msj] = [$sayac > 0, $sayac . ' dosya işlendi' . ($hata ? ", $hata tanesi olmadı" : '') . '.'];
                break;
            default:
                [$ok, $msj] = [false, 'Bilinmeyen işlem.'];
        }
        $message = ($ok ? 'ok|' : 'err|') . $msj;
    }
}
[$msgType, $msgText] = array_pad(explode('|', $message, 2), 2, '');

/**
 * Hangi görselin nerede kullanıldığını çıkarır: "/images/uploads/x.jpg" => ["Ürün: Miller Şaft", ...]
 * Ürünler veritabanından, geri kalan her şey content.json'dan geliyor.
 */
function bm_media_usage($content) {
    $map = [];
    $ekle = function ($yol, $nerede) use (&$map) {
        $yol = trim((string)$yol);
        if ($yol === '') return;
        $map[$yol][] = $nerede;
    };

    if (bm_db_ready()) {
        try {
            $st = bm_db()->query(
                "SELECT title, image FROM products WHERE image IS NOT NULL AND image <> ''"
            );
            foreach ($st as $r) $ekle($r['image'], 'Ürün: ' . $r['title']);
        } catch (Throwable $e) { /* veritabanı yoksa sessizce geç */ }
        try {
            if (bm_articles_ready()) {
                $st = bm_db()->query("SELECT title, image FROM articles WHERE image IS NOT NULL AND image <> ''");
                foreach ($st as $r) $ekle($r['image'], 'Makale: ' . $r['title']);
            }
        } catch (Throwable $e) { /* makale tablosu yoksa geç */ }
    }

    foreach (($content['services'] ?? []) as $s) {
        $ad = $s['title'] ?? 'Hizmet';
        $ekle($s['card_image'] ?? '', 'Hizmet kartı: ' . $ad);
        $ekle($s['hero_image'] ?? '', 'Hizmet başlığı: ' . $ad);
        $ekle($s['image'] ?? '', 'Hizmet görseli: ' . $ad);
        foreach (($s['machines'] ?? []) as $m) {
            $ekle($m['image'] ?? '', 'Makine: ' . ($m['name'] ?? $ad));
        }
    }
    foreach (($content['pages'] ?? []) as $p) {
        $ekle($p['hero_image'] ?? '', 'Sayfa başlığı: ' . ($p['title'] ?? ''));
    }
    $ekle($content['about_teaser']['visual_image'] ?? '', 'Ana sayfa — Hakkımızda görseli');
    $ekle($content['order_section']['hero_image'] ?? '', 'Teklif sayfası başlığı');
    foreach (bm_hero_slides($content) as $i => $slayt) {
        $ekle($slayt['image'] ?? '', 'Ana sayfa slaytı ' . ($i + 1));
    }

    foreach (['phone_icon' => 'Telefon ikonu', 'whatsapp_icon' => 'WhatsApp ikonu',
              'email_icon' => 'E-posta ikonu', 'address_icon' => 'Adres ikonu'] as $k => $ad) {
        $ekle($content['site'][$k] ?? '', $ad);
    }
    foreach ([
        'drawer.buttons'                 => 'Mobil menü butonu',
        'footer.social_buttons'          => 'Footer logo butonu',
        'footer.quick_links'             => 'Footer linki',
        'order_section.contact_buttons'  => 'Teklif iletişim butonu',
        'order_section.social_buttons'   => 'Teklif sosyal butonu',
    ] as $yol => $ad) {
        foreach (bm_button_list($content, $yol) as $b) {
            $ekle($b['image'] ?? '', $ad . ': ' . ($b['label'] ?? $b['text'] ?? ''));
        }
    }
    return $map;
}

$q      = trim((string)($_GET['q'] ?? ''));
$sadece = (string)($_GET['sadece'] ?? '');   // '' | 'bos' (kullanılmayanlar)
$sayfa  = max(1, (int)($_GET['sayfa'] ?? 1));

$kullanim  = bm_media_usage($content);
$klasorler = bm_media_klasorler('');

// Arama varsa bütün kütüphanede ara, yoksa yalnızca bulunulan klasör
$aramaModu = ($q !== '' || $sadece === 'bos');
$files = $aramaModu
    ? bm_media_dosyalar('', $q, true)
    : bm_media_dosyalar($klasor, '', false);

$toplam = 0;
foreach ($klasorler as $k) $toplam += $k['sayi'];
$toplam += bm_media_dosya_sayisi('');

if ($sadece === 'bos') {
    $files = array_values(array_filter($files, function ($f) use ($kullanim) {
        return empty($kullanim[$f['url']]);
    }));
}

$eslesen  = count($files);
$sonSayfa = max(1, (int)ceil($eslesen / BM_MEDIA_PER_PAGE));
if ($sayfa > $sonSayfa) $sayfa = $sonSayfa;
$gosterilen = array_slice($files, ($sayfa - 1) * BM_MEDIA_PER_PAGE, BM_MEDIA_PER_PAGE);

/** Sayfa bağlantısı — arama, filtre ve klasör korunarak */
function bm_media_url($ek = []) {
    $p = array_merge([
        'klasor' => $_GET['klasor'] ?? '',
        'q'      => $_GET['q'] ?? '',
        'sadece' => $_GET['sadece'] ?? '',
    ], $ek);
    $p = array_filter($p, function ($v) { return $v !== '' && $v !== null; });
    return '/teknikel/medya.php' . ($p ? '?' . http_build_query($p) : '');
}

/** Klasör yolunun parçaları — üst çubuktaki "Görseller › Ürünler › Torna" için */
function bm_media_yol_parcalari($rel) {
    $out = [['ad' => 'Görseller', 'rel' => '']];
    $birikim = '';
    foreach (array_filter(explode('/', $rel)) as $p) {
        $birikim = ($birikim !== '' ? $birikim . '/' : '') . $p;
        $out[] = ['ad' => $p, 'rel' => $birikim];
    }
    return $out;
}

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Görseller</h1>
    <div class="sub">
      Toplam <strong><?php echo number_format($toplam, 0, ',', '.'); ?></strong> dosya,
      <strong><?php echo count($klasorler); ?></strong> klasör
    </div>
  </div>
  <a href="/teknikel/logout.php" class="logout-link">Çıkış Yap</a>
</div>

<?php if ($msgText): ?><div class="flash <?php echo $msgType; ?>"><?php echo e($msgText); ?></div><?php endif; ?>

<div class="dosya-yonetici">

  <!-- ---------- SOL: KLASÖR AĞACI ---------- -->
  <aside class="dy-kenar">
    <div class="dy-kenar-baslik">Klasörler</div>
    <ul class="dy-klasorler">
      <li>
        <a href="<?php echo e(bm_media_url(['klasor' => '', 'sayfa' => ''])); ?>"
           class="dy-klasor<?php echo $klasor === '' ? ' on' : ''; ?>">
          <span class="dy-ic">🗂️</span>
          <span class="dy-ad">Tüm Görseller</span>
          <span class="dy-sayi"><?php echo bm_media_dosya_sayisi(''); ?></span>
        </a>
      </li>
      <?php foreach ($klasorler as $k): ?>
        <li>
          <a href="<?php echo e(bm_media_url(['klasor' => $k['rel'], 'sayfa' => ''])); ?>"
             class="dy-klasor<?php echo $klasor === $k['rel'] ? ' on' : ''; ?>"
             style="padding-left:<?php echo 12 + $k['derinlik'] * 14; ?>px;">
            <span class="dy-ic"><?php echo $klasor === $k['rel'] ? '📂' : '📁'; ?></span>
            <span class="dy-ad"><?php echo e($k['ad']); ?></span>
            <span class="dy-sayi"><?php echo (int)$k['sayi']; ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <form method="post" class="dy-yeni-klasor">
      <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
      <input type="hidden" name="action" value="klasor_ekle">
      <input type="hidden" name="klasor" value="<?php echo e($klasor); ?>">
      <input type="text" name="ad" placeholder="Yeni klasör adı" required>
      <button type="submit" class="kat-mini">+ Ekle</button>
      <div class="hint" style="margin-top:6px;">
        <?php echo $klasor === '' ? 'Ana dizinde oluşturulur.' : '"' . e(basename($klasor)) . '" içinde oluşturulur.'; ?>
      </div>
    </form>
  </aside>

  <!-- ---------- SAĞ: İÇERİK ---------- -->
  <section class="dy-ana">

    <div class="dy-ust">
      <nav class="dy-yol" aria-label="Konum">
        <?php $parcalar = bm_media_yol_parcalari($klasor); ?>
        <?php foreach ($parcalar as $i => $p): ?>
          <?php if ($i > 0): ?><span class="dy-ok">›</span><?php endif; ?>
          <?php if ($i === count($parcalar) - 1): ?>
            <strong><?php echo e($p['ad']); ?></strong>
          <?php else: ?>
            <a href="<?php echo e(bm_media_url(['klasor' => $p['rel'], 'sayfa' => ''])); ?>"><?php echo e($p['ad']); ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>

      <?php if ($klasor !== ''): ?>
        <div class="dy-klasor-islem">
          <button type="button" class="kat-mini"
                  onclick="bmKlasorAdlandir('<?php echo e($klasor); ?>', '<?php echo e(basename($klasor)); ?>')">Yeniden Adlandır</button>
          <button type="submit" form="klasorSilForm" class="kat-mini danger"
                  onclick="return confirm('Bu klasör silinecek. (Yalnızca boşsa silinir.) Emin misin?');">Klasörü Sil</button>
        </div>
      <?php endif; ?>
    </div>

    <form method="get" class="filter-bar dy-arama">
      <?php if ($klasor !== '' && !$aramaModu): ?>
        <input type="hidden" name="klasor" value="<?php echo e($klasor); ?>">
      <?php endif; ?>
      <input type="search" name="q" value="<?php echo e($q); ?>"
             placeholder="Bütün klasörlerde ara — örn. miller-saft">
      <select name="sadece">
        <option value=""<?php echo $sadece === '' ? ' selected' : ''; ?>>Tüm görseller</option>
        <option value="bos"<?php echo $sadece === 'bos' ? ' selected' : ''; ?>>Yalnızca kullanılmayanlar</option>
      </select>
      <button type="submit" class="btn">Ara</button>
      <?php if ($aramaModu): ?>
        <a href="/teknikel/medya.php" class="btn ghost">Temizle</a>
      <?php endif; ?>
    </form>

    <div class="dy-yukle">
      <label class="dy-yukle-alan" id="dropAlan">
        <input type="file" id="mediaFileInput" accept="image/*" multiple hidden>
        <span class="dy-yukle-ic">⬆️</span>
        <span>
          <strong>Buraya sürükle</strong> ya da tıkla —
          <?php echo $klasor === '' ? 'ana dizine' : '<b>' . e(basename($klasor)) . '</b> klasörüne'; ?> yüklenir
        </span>
        <span class="hint">JPG, PNG, WEBP, GIF — dosya başına en fazla 10 MB. Birden fazla seçebilirsin.</span>
      </label>
      <div id="uploadStatus" class="hint"></div>
    </div>

    <form method="post" id="dosyaForm">
      <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
      <input type="hidden" name="action" value="toplu">
      <input type="hidden" name="klasor" value="<?php echo e($klasor); ?>">

      <div class="dy-arac" id="topluCubuk" hidden>
        <span class="dy-secim" id="secimSayisi"></span>
        <select name="toplu_islem" id="topluIslem">
          <option value="tasi">Şuraya taşı…</option>
          <option value="sil">Sil</option>
        </select>
        <select name="hedef_klasor" id="topluHedef">
          <option value="">Ana dizin</option>
          <?php foreach ($klasorler as $k): ?>
            <option value="<?php echo e($k['rel']); ?>">
              <?php echo str_repeat('— ', (int)$k['derinlik']) . e($k['ad']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="kat-mini" onclick="return bmTopluOnay();">Uygula</button>
        <button type="button" class="kat-mini" onclick="bmSecimTemizle()">Seçimi Bırak</button>
      </div>

      <?php if (!$gosterilen): ?>
        <p class="hint" style="padding:30px 0;text-align:center;">
          <?php echo $toplam === 0
            ? 'Henüz görsel yüklenmemiş.'
            : ($aramaModu ? 'Bu aramaya uyan görsel yok.' : 'Bu klasör boş.'); ?>
        </p>
      <?php else: ?>
        <div class="dy-izgara">
          <?php foreach ($gosterilen as $f):
            $nerede = $kullanim[$f['url']] ?? [];
          ?>
            <div class="dy-dosya" data-rel="<?php echo e($f['rel']); ?>">
              <label class="dy-sec">
                <input type="checkbox" name="secili[]" value="<?php echo e($f['rel']); ?>" class="dy-kutu">
              </label>
              <div class="dy-onizleme">
                <img src="<?php echo e(bm_src($f['url'])); ?>" alt="<?php echo e($f['ad']); ?>" loading="lazy">
              </div>
              <div class="dy-ad-satir" title="<?php echo e($f['ad']); ?>"><?php echo e($f['ad']); ?></div>
              <div class="dy-bilgi">
                <?php if ($aramaModu && $f['klasor'] !== ''): ?>
                  <span class="dy-konum" title="Bulunduğu klasör">📁 <?php echo e($f['klasor']); ?></span>
                <?php endif; ?>
                <?php if ($nerede): ?>
                  <span class="badge on" title="<?php echo e(implode(' · ', $nerede)); ?>">
                    Kullanılıyor<?php echo count($nerede) > 1 ? ' (' . count($nerede) . ')' : ''; ?>
                  </span>
                <?php else: ?>
                  <span class="badge off">Kullanılmıyor</span>
                <?php endif; ?>
              </div>
              <div class="dy-islem">
                <button type="button" class="kat-mini"
                        onclick="bmDosyaAdlandir('<?php echo e($f['rel']); ?>', '<?php echo e($f['ad']); ?>')">Ad</button>
                <button type="button" class="kat-mini"
                        onclick="bmDosyaTasi('<?php echo e($f['rel']); ?>')">Taşı</button>
                <button type="button" class="kat-mini"
                        onclick="bmYoluKopyala(this, '<?php echo e($f['url']); ?>')">Yol</button>
                <button type="button" class="kat-mini danger"
                        onclick="bmDosyaSil('<?php echo e($f['rel']); ?>', '<?php echo e($f['ad']); ?>', <?php echo $nerede ? 'true' : 'false'; ?>)">Sil</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($sonSayfa > 1): ?>
          <div class="admin-pagination">
            <?php if ($sayfa > 1): ?>
              <a href="<?php echo e(bm_media_url(['sayfa' => $sayfa - 1])); ?>">← Önceki</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $sonSayfa; $i++):
              if ($i > 2 && $i < $sonSayfa - 1 && abs($i - $sayfa) > 1) {
                  if ($i === 3) echo '<span class="dots">…</span>';
                  continue;
              } ?>
              <a href="<?php echo e(bm_media_url(['sayfa' => $i])); ?>"
                 class="<?php echo $i === $sayfa ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($sayfa < $sonSayfa): ?>
              <a href="<?php echo e(bm_media_url(['sayfa' => $sayfa + 1])); ?>">Sonraki →</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </form>
  </section>
</div>

<?php /* Tek dosyalık işlemler: JS bunları doldurup gönderiyor */ ?>
<form method="post" id="tekIslemForm" class="hidden-form">
  <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
  <input type="hidden" name="klasor" value="<?php echo e($klasor); ?>">
  <input type="hidden" name="action" id="ti_action">
  <input type="hidden" name="rel" id="ti_rel">
  <input type="hidden" name="ad" id="ti_ad">
  <input type="hidden" name="hedef_klasor" id="ti_hedef">
</form>
<form method="post" id="klasorSilForm" class="hidden-form">
  <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
  <input type="hidden" name="action" value="klasor_sil">
  <input type="hidden" name="rel" value="<?php echo e($klasor); ?>">
  <input type="hidden" name="klasor" value="<?php echo e($klasor); ?>">
</form>

<script>
window.BM_KLASORLER = <?php echo json_encode(array_map(function ($k) {
    return ['rel' => $k['rel'], 'ad' => str_repeat('— ', (int)$k['derinlik']) . $k['ad']];
}, $klasorler), JSON_UNESCAPED_UNICODE); ?>;
window.BM_KLASOR = <?php echo json_encode($klasor); ?>;
</script>
<script src="/teknikel/assets/medya.js?v=<?php echo e(bm_asset_version('teknikel/assets/medya.js')); ?>"></script>
<?php require __DIR__ . '/layout-bottom.php'; ?>
