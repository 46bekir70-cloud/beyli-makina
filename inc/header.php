<?php
/**
 * Ortak <head> + <header> şablonu.
 * Kullanmadan önce şu değişkenler tanımlanmalı (opsiyonel, tanımlı değilse varsayılan kullanılır):
 *   $bm_title        -> <title> metni
 *   $bm_description  -> meta description
 *   $bm_active       -> aktif menü öğesinin slug'ı ("home", "hakkimizda", vb.)
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/products.php';
$content = bm_load_content();
$site = $content['site'] ?? [];
$menu = $content['menu'] ?? [];
$navPages = bm_nav_pages($content);
// Menüdeki açılır listeler artık elle girilmiyor:
// ürün kategorileri veritabanından, hizmetler ise içerikten otomatik geliyor.
// NOT: Kategoriler artık üst menüde listelenmiyor (açılır liste kaldırıldı),
// bu yüzden burada sorgulanmıyor da — her sayfa yüklemesinde gereksiz bir
// veritabanı sorgusu dönüyordu.
$navServices = bm_services($content, true);

// Sayfanın kendi başlığı/açıklaması yoksa panelden yönetilen varsayılanlar kullanılır
$bm_title = $bm_title ?? bm_t('seo.title', 'Beyli Makina | CNC Torna & Freze İmalat');
$bm_description = $bm_description ?? bm_t('seo.desc', 'Kahramanmaraş Organize Sanayi Bölgesi\'nde hassas CNC torna ve freze imalatı.');
$bm_active = $bm_active ?? '';
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo e($bm_title); ?></title>
<meta name="description" content="<?php echo e($bm_description); ?>">
<?php
  // Sayfanın tek geçerli adresi. Aynı sayfaya farklı adreslerden (www'suz,
  // /index.php, arama parametreli) ulaşılabildiği için Google'a hangisinin
  // asıl adres olduğunu söylüyoruz.
  $bm_canonical = bm_site_url() . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
  if (substr($bm_canonical, -10) === '/index.php') $bm_canonical = substr($bm_canonical, 0, -9);
?>
<link rel="canonical" href="<?php echo e($bm_canonical); ?>">
<meta name="robots" content="index, follow, max-image-preview:large">
<?php
  /* ----------------------------------------------------------------------
     SOSYAL PAYLAŞIM KARTI (Open Graph + Twitter)
     WhatsApp, LinkedIn, X, Facebook, Telegram bir linki paylaşınca sayfayı
     açıp aşağıdaki etiketleri okur ve önizleme kutusunu bunlarla çizer.
     Bu etiketler olmadan karşı tarafta çıplak bir adres görünür.

     Sayfalar isteğe bağlı olarak $bm_image (kendi görseli) ve $bm_og_type
     tanımlar; tanımlamazsa panelden seçilen paylaşım görseli kullanılır.
     ---------------------------------------------------------------------- */
  $bm_og_type  = $bm_og_type ?? 'website';
  $bm_og_image = bm_share_image($content, $bm_image ?? '');
?>
<meta property="og:type" content="<?php echo e($bm_og_type); ?>">
<meta property="og:site_name" content="<?php echo e(bm_t('site.name', 'Beyli Makina Sanayi ve Ticaret Ltd. Şti.')); ?>">
<meta property="og:locale" content="tr_TR">
<meta property="og:title" content="<?php echo e($bm_title); ?>">
<meta property="og:description" content="<?php echo e($bm_description); ?>">
<meta property="og:url" content="<?php echo e($bm_canonical); ?>">
<meta property="og:image" content="<?php echo e($bm_og_image); ?>">
<meta property="og:image:alt" content="<?php echo e($bm_title); ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo e($bm_title); ?>">
<meta name="twitter:description" content="<?php echo e($bm_description); ?>">
<meta name="twitter:image" content="<?php echo e($bm_og_image); ?>">
<?php
  // Arama motoru doğrulama kodları (Genel Ayarlar'dan giriliyor).
  // Yandex/Google, siteyi gerçekten senin yönettiğini bu satırdan anlıyor.
  //
  // Panele üç biçimden hangisi yapıştırılırsa yapıştırılsın doğru çalışsın:
  //   1) sadece kod:            78jW0lcY9tk-4zne...
  //   2) meta etiketinin tamamı: <meta name="..." content="78jW..." />
  //   3) DNS TXT kaydı biçimi:   google-site-verification=78jW...
  // Not: kodun SONUNDAKİ "=" işaretleri (base64 dolgusu) korunmalı, bu yüzden
  // yalnızca baştaki "ad=" öneki kırpılıyor.
  foreach (['yandex' => 'yandex-verification', 'google' => 'google-site-verification'] as $anahtar => $metaAd) {
      $kod = trim((string)($site['verify_' . $anahtar] ?? ''));
      if ($kod === '') continue;
      if (preg_match('/content=["\']([^"\']+)["\']/i', $kod, $m)) {
          $kod = trim($m[1]);
      } else {
          $kod = preg_replace('/^\s*(google-site-verification|yandex-verification)\s*=\s*/i', '', $kod);
          $kod = trim($kod);
      }
      if ($kod === '') continue;
      echo '<meta name="' . $metaAd . '" content="' . e($kod) . '">' . "\n";
  }
?>
<?php
  $bm_customFavicon = trim((string)($site['favicon_image'] ?? ''));
  $bm_favIco = $bm_customFavicon !== '' ? $bm_customFavicon : '/favicon.ico';
  $bm_favPng = $bm_customFavicon !== '' ? $bm_customFavicon : '/images/favicon-48.png';
?>
<!-- Sekmede, yer imlerinde ve Google sonuçlarında görünen simge (Favicon) -->
<link rel="icon" href="<?php echo e($bm_favIco); ?>" sizes="48x48">
<link rel="icon" type="image/png" sizes="192x192" href="<?php echo e($bm_customFavicon ?: '/images/favicon-192.png'); ?>">
<link rel="icon" type="image/png" sizes="96x96" href="<?php echo e($bm_customFavicon ?: '/images/favicon-96.png'); ?>">
<link rel="icon" type="image/png" sizes="48x48" href="<?php echo e($bm_favPng); ?>">
<link rel="apple-touch-icon" href="<?php echo e($bm_customFavicon ?: '/images/apple-touch-icon.png'); ?>">
<?php if ($bm_active === 'home'): ?>
<!-- Firma kimliği: Google'ın marka adını ve logosunu tanıması için -->
<script type="application/ld+json"><?php echo bm_organization_jsonld($content); ?></script>
<!-- Site kimliği + site içi arama kutusu -->
<script type="application/ld+json"><?php echo bm_website_jsonld($content); ?></script>
<?php endif; ?>
<?php
  // Sayfa kendi konum zincirini $bm_breadcrumb olarak verirse, aynı zinciri
  // arama motoruna da bildiriyoruz: "Ana Sayfa › Ürünler › Freze › Parça"
  // Google bu yapıyı marka aramasında alt bağlantı çıkarmak için kullanıyor.
  if (!empty($bm_breadcrumb)) {
      $bm_bcJson = bm_breadcrumb_jsonld($bm_breadcrumb);
      if ($bm_bcJson !== '') {
          echo '<script type="application/ld+json">' . $bm_bcJson . "</script>\n";
      }
  }
  // Sayfaya özel ek yapısal veri (ör. makale sayfasındaki Article kaydı)
  if (!empty($bm_jsonld)) {
      foreach ((array)$bm_jsonld as $bm_ek) {
          if (trim((string)$bm_ek) !== '') {
              echo '<script type="application/ld+json">' . $bm_ek . "</script>\n";
          }
      }
  }
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css?v=<?php echo e(bm_asset_version('css/style.css')); ?>">
<script>
/* Animasyonlar yalnızca JS çalışıyorsa devreye girsin.
   Bu sınıf olmadan .reveal öğeleri gizlenmiyor — yani JS bir hata verirse
   sayfa boş görünmek yerine animasyonsuz ama eksiksiz açılır.
   <head> içinde duruyor ki ilk boyamadan önce eklensin, ekran titremesin. */
document.documentElement.classList.add('js');
</script>
</head>
<body>

<header>
  <nav>
    <a href="/" class="logo">
      <img src="/images/logo.png" alt="Beyli Makina Sanayi ve Ticaret Ltd. Şti." class="logo-img">
    </a>

    <button class="nav-toggle" id="navToggle" aria-label="<?php echo e(bm_t('a11y.menu', 'Menü')); ?>">☰</button>

    <ul class="nav-links" id="navLinks">
      <!-- Aşağıdaki iki öğe yalnızca mobil menü panelinde görünür (CSS ile
           masaüstünde gizli). Panel logo + iletişim bilgileriyle dolduruluyor;
           mobilde üst çubuktaki iletişim yazıları gizlendiği için buradaki
           bağlantılar tek erişim noktası oluyor. -->
      <li class="nav-drawer-head">
        <a href="/" aria-label="<?php echo e(bm_t('a11y.home', 'Ana sayfa')); ?>">
          <img src="/images/logo.png" alt="Beyli Makina Sanayi ve Ticaret Ltd. Şti." class="logo-img">
        </a>
      </li>
      <?php /* Ürünlerimiz: açılır liste YOK, düz bağlantı. Kategoriler arttıkça
               menü uzayıp kullanışsız hâle geliyordu; kategori seçimi zaten
               ürünler sayfasının sol filtre panelinde yapılıyor. */ ?>
      <li>
        <a href="/urunler"<?php echo ($bm_active === 'urunler') ? ' class="active"' : ''; ?>><?php echo e($menu['urunler_label'] ?? 'Ürünlerimiz'); ?></a>
      </li>
      <li>
        <a href="/#hizmetler"><?php echo e($menu['hizmetler_label'] ?? 'Hizmetlerimiz'); ?><?php if ($navServices): ?> <span class="chev">▾</span><?php endif; ?></a>
        <?php if ($navServices): ?>
        <div class="dropdown">
          <?php foreach ($navServices as $svc): ?>
            <a href="/hizmet/<?php echo e($svc['slug']); ?>"><?php echo e($svc['title']); ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </li>
      <!-- İletişim, Ürünlerimiz ve Hizmetlerimiz'in hemen yanında duruyor ve
           "Diğer" listesine hiç taşınmıyor: siteye gelenin en çok aradığı üç
           sekmeden biri. Taşma sırasına yalnızca panelden eklenen sayfalar girer. -->
      <li id="navItemIletisim">
        <a href="/teklif"<?php echo ($bm_active === 'teklif') ? ' class="active"' : ''; ?>><?php echo e(bm_t('nav.contact', 'İletişim')); ?></a>
      </li>
      <?php foreach (bm_nav_tree($content) as $oge): ?>
        <?php if ($oge['type'] === 'page'): $p = $oge['page']; ?>
          <li class="nav-overflowable">
            <a href="/<?php echo urlencode($p['slug']); ?>"<?php echo ($bm_active === $p['slug']) ? ' class="active"' : ''; ?>><?php echo e($p['title']); ?></a>
          </li>
        <?php else: ?>
          <?php
            // Panelde aynı "menü grubu" verilen sayfalar tek başlık altında
            $grupAktif = false;
            foreach ($oge['pages'] as $p) if ($bm_active === $p['slug']) $grupAktif = true;
          ?>
          <li class="nav-overflowable">
            <a href="javascript:void(0)"<?php echo $grupAktif ? ' class="active"' : ''; ?>>
              <?php echo e($oge['label']); ?> <span class="chev">▾</span>
            </a>
            <div class="dropdown">
              <?php foreach ($oge['pages'] as $p): ?>
                <a href="/<?php echo urlencode($p['slug']); ?>"><?php echo e($p['title']); ?></a>
              <?php endforeach; ?>
            </div>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
      <li class="nav-more" id="navMore" style="display:none;">
        <a href="javascript:void(0)"><?php echo e(bm_t('nav.more', 'Diğer')); ?> <span class="chev">▾</span></a>
        <div class="dropdown" id="moreDropdown"></div>
      </li>

      <?php
        // Panel > Menü & Footer bölümünden yönetiliyor
        $drawer = $content['drawer'] ?? [];
        $drawerCtaText = trim((string)($drawer['cta_text'] ?? 'Teklif Al'));
        $drawerCtaHref = bm_safe_url($drawer['cta_link'] ?? '/teklif') ?: '/teklif';
        $drawerButtons = bm_button_list($content, 'drawer.buttons');
      ?>
      <li class="nav-drawer-foot">
        <?php if ($drawerCtaText !== ''): ?>
          <a href="<?php echo e($drawerCtaHref); ?>" class="btn drawer-cta"><?php echo e($drawerCtaText); ?></a>
        <?php endif; ?>
        <?php if ($drawerButtons): ?>
        <div class="drawer-contact">
          <?php foreach ($drawerButtons as $btn): ?>
            <?php $href = bm_contact_button_href($btn); ?>
            <?php if ($href !== ''): ?>
              <a href="<?php echo e($href); ?>"
                 <?php echo strpos($href, 'http') === 0 ? 'target="_blank" rel="noopener"' : ''; ?>>
                <?php echo bm_button_icon($btn); ?><?php echo e($btn['text'] ?? ''); ?>
              </a>
            <?php else: ?>
              <span class="drawer-address">
                <?php echo bm_button_icon($btn); ?><?php echo e($btn['text'] ?? ''); ?>
              </span>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </li>
    </ul>

    <a href="/teklif" class="btn"><?php echo e(bm_t('nav.cta', 'Teklif Al')); ?></a>
  </nav>

  <!-- Mobil menü açıkken arkayı karartan katman.
       header'ın kendi z-index'i (100) ayrı bir yığın bağlamı yarattığı için bu
       katman header'ın İÇİNDE durmalı; dışarıda kalırsa menünün üstüne biner. -->
  <div class="nav-backdrop" id="navBackdrop"></div>
</header>
