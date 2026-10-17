<?php
/**
 * Tek ürün detay sayfası — /urun/<slug>
 * Teklif formuna ürünün adı ve kodu önceden doldurulmuş olarak gider.
 */
require_once __DIR__ . '/inc/products.php';

// Admin panelin "kaydetmeden önizleme" özelliği ürünü hazır dizi olarak
// enjekte eder; normal ziyaretlerde bu değişken tanımsızdır.
if (!empty($GLOBALS['bm_preview_product'])) {
    $product = $GLOBALS['bm_preview_product'];
    $slug = $product['slug'] ?? '';
} else {
    $slug    = trim((string)($_GET['slug'] ?? ''));
    $product = $slug !== '' ? bm_product_by_slug($slug) : null;
}

if (!$product) {
    http_response_code(404);
    $bm_title = 'Ürün Bulunamadı | Beyli Makina';
    require __DIR__ . '/inc/header.php';
    echo '<section style="text-align:center;padding:110px 24px;">';
    echo '<h1 style="font-size:30px;color:var(--blue-dark);margin-bottom:14px;">' . e(bm_t('nf.product_title', 'Ürün Bulunamadı')) . '</h1>';
    echo '<p style="color:var(--muted);margin-bottom:24px;">' . e(bm_t('nf.product_text', 'Bu ürün kaldırılmış ya da adres hatalı olabilir.')) . '</p>';
    echo '<a href="/urunler" class="btn">' . e(bm_t('nf.product_btn', 'Ürün Kataloğuna Dön')) . '</a>';
    echo '</section>';
    require __DIR__ . '/inc/footer.php';
    exit;
}

$bm_title = $product['title'] . ' | Beyli Makina';
$bm_description = $product['description']
    ?: ($product['title'] . ' — Beyli Makina hassas CNC imalat kataloğu.');
$bm_active = 'urunler';
// Paylaşım kartında ürünün kendi fotoğrafı çıksın
$bm_image = $product['image'] ?? '';
$bm_og_type = 'product';
// Google'ın ürünü ürün olarak tanıması için yapısal veri
$bm_urunSema = bm_product_jsonld($product);
if ($bm_urunSema !== '') $bm_jsonld = [$bm_urunSema];

$related = bm_related_products($product, 4);

// Ürünün bulunduğu kategoriler tam yol hâlinde (İPLİK › OPEN-END › S11) — alt/üst ilişkisi
// görünsün ve her basamak tıklanabilir olsun diye
$katYollari = bm_product_category_paths($product['id'] ?? 0);
// Birden fazla yol ya da en az bir üst zincir varsa açıklama satırı göster
$katCokluMu = false;
foreach ($katYollari as $bmYol) {
    foreach ($bmYol as $bmAdim) {
        if (empty($bmAdim['direct'])) { $katCokluMu = true; break 2; }
    }
}
if (count($katYollari) > 1) $katCokluMu = true;
// Ana kategorinin üst zinciri — konum çubuğunda tam yol gösterilir
$anaZincir = [];
if (!empty($product['category_id'])) {
    $duz = [];
    foreach (bm_category_flat(null) as $c) $duz[(int)$c['id']] = $c;
    foreach (bm_category_path((int)$product['category_id']) as $cid) {
        if (isset($duz[$cid])) $anaZincir[] = $duz[$cid];
    }
}

// Teklif formuna taşınacak ön bilgi
$onTalep = $product['title'] . ($product['code'] ? ' (' . $product['code'] . ')' : '');

// Aynı konum zinciri arama motoruna da bildiriliyor (header.php JSON-LD üretir)
$bm_breadcrumb = [['ad' => 'Ana Sayfa', 'yol' => '/'], ['ad' => 'Ürünler', 'yol' => '/urunler']];
foreach ($anaZincir as $z) {
    $bm_breadcrumb[] = ['ad' => $z['label'], 'yol' => '/urunler?kategori=' . rawurlencode($z['slug'])];
}
$bm_breadcrumb[] = ['ad' => $product['title'], 'yol' => ''];

require __DIR__ . '/inc/header.php';
?>

<section class="product-detail">
  <nav class="breadcrumb reveal" aria-label="<?php echo e(bm_t('a11y.breadcrumb', 'Konum')); ?>">
    <a href="/"><?php echo e(bm_t('nav.home', 'Ana Sayfa')); ?></a> <span>›</span><a href="/urunler"><?php echo e(bm_t('nav.products', 'Ürünler')); ?></a><?php
      // Ana kategorinin tam yolu: CNC › Dişliler › ... şeklinde
      foreach ($anaZincir as $z): ?><span>›</span><a href="/urunler?kategori=<?php echo e($z['slug']); ?>"><?php echo e($z['label']); ?></a><?php
      endforeach; ?><span>›</span><strong><?php echo e($product['title']); ?></strong>
  </nav>

  <div class="product-wrap">
    <div class="product-visual reveal">
      <?php if (!empty($product['image'])): ?>
        <img src="<?php echo e(bm_src($product['image'])); ?>" alt="<?php echo e($product['title']); ?>"<?php echo bm_focus_style($product['image_focus'] ?? '', $product['image_zoom'] ?? null); ?>>
      <?php else: ?>
        <span class="ph"><?php echo e($product['emoji'] ?: '⚙️'); ?></span>
      <?php endif; ?>
    </div>

    <div class="product-body reveal">
      <?php $katYolu = bm_product_category_path_label($product); ?>
      <?php if ($katYolu !== ''): ?>
        <span class="cat"><?php echo e($katYolu); ?></span>
      <?php endif; ?>
      <h1><?php echo e($product['title']); ?></h1>
      <?php if (!empty($product['code'])): ?>
        <div class="product-code"><?php echo e(bm_t('product.code_label', 'Parça Kodu:')); ?> <strong><?php echo e($product['code']); ?></strong></div>
      <?php endif; ?>

      <?php if (!empty($product['description'])): ?>
        <p class="product-desc"><?php echo nl2br(e($product['description'])); ?></p>
      <?php endif; ?>

      <?php $visibleSpecs = bm_visible_specs($product['specs']); ?>
      <?php if ($visibleSpecs): ?>
        <h2 class="spec-heading"><?php echo e(bm_t('product.specs', 'Teknik Özellikler')); ?></h2>
        <table class="spec-table">
          <tbody>
            <?php foreach ($visibleSpecs as $spec): ?>
              <tr>
                <th><?php echo e($spec[0]); ?></th>
                <td><?php echo e($spec[1]); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if ($katYollari): ?>
        <!-- Ürünün bulunduğu kategoriler, tam yolu ile: İPLİK › OPEN-END › S11.
             Her basamağı tıklanıp o listeye gidilebiliyor. Dolu rozetler ürünün
             doğrudan eklendiği kategoriler, soluk olanlar sadece üst zincir. -->
        <div class="product-cats">
          <span class="pc-baslik"><?php echo e(bm_t('product.cats_label', 'Kategoriler')); ?></span>
          <div class="pc-liste">
            <?php foreach ($katYollari as $yol): ?>
              <div class="pc-yol">
                <?php foreach ($yol as $i => $adim): ?>
                  <?php if ($i > 0): ?><span class="pc-ayrac" aria-hidden="true">›</span><?php endif; ?>
                  <a href="/urunler?kategori=<?php echo e($adim['slug']); ?>"
                     class="pc-rozet<?php echo $adim['direct'] ? ' dolu' : ''; ?>"><?php echo e($adim['label']); ?></a>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($katCokluMu): ?>
            <p class="pc-not"><?php echo e(bm_t('product.cats_hint', 'Koyu renkli kategoriler parçanın doğrudan yer aldığı gruplardır.')); ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php
        // Bu kutu her parça için doğru değil (standart/stok parçalarda yanıltıcı
        // olabiliyor), o yüzden ürün bazında açılıp kapanıyor. Sütunu olmayan
        // eski kurulumlarda eskisi gibi her üründe görünür.
        $notuGoster = !array_key_exists('show_note', $product) || (int)$product['show_note'] === 1;
      ?>
      <?php if ($notuGoster): ?>
        <div class="product-note">
          <span class="pn-ic" aria-hidden="true">⚙</span>
          <p><?php echo e(bm_t('product.note',
            'Bu parçayı farklı ölçü, malzeme ve adette de üretebiliriz. Teknik çizimini ya da numunesini iletin, size özel teklif hazırlayalım.')); ?></p>
        </div>
      <?php endif; ?>

      <div class="product-actions">
        <?php
          // Ürüne özel teklif türü varsa forma taşınıyor; yoksa form kendi
          // ilk seçeneğiyle açılıyor.
          $teklifAdres = '/teklif?urun=' . urlencode($onTalep);
          if (trim((string)($product['quote_type'] ?? '')) !== '') {
              $teklifAdres .= '&tur=' . urlencode($product['quote_type']);
          }
        ?>
        <a href="<?php echo e($teklifAdres); ?>" class="btn"><?php echo e(bm_t('product.cta', 'Bu Parça İçin Teklif İste')); ?></a>
        <a href="/urunler<?php echo !empty($product['cat_slug']) ? '?kategori=' . urlencode($product['cat_slug']) : ''; ?>"
           class="btn btn-outline"><?php echo e(bm_t('product.similar_btn', 'Benzer Ürünler')); ?></a>
      </div>

      <?php
        // İletişim kısayolları: teklif formuna gitmeden doğrudan arayıp
        // yazabilsin. Bilgiler Genel Ayarlar'dan geliyor, boşsa hiç basılmıyor.
        $site = $content['site'] ?? [];
      ?>
      <?php if (!empty($site['phone_display']) || !empty($site['whatsapp_link'])): ?>
        <div class="product-hizli">
          <span class="ph-baslik"><?php echo e(bm_t('product.quick_label', 'Hemen sorun:')); ?></span>
          <!-- Butonlar ayrı bir kapsayıcıda: başlık satırda yer kapladığı için
               dar ekranda ikisi kaydırmalı diziliyor ve hizaları bozuluyordu. -->
          <div class="ph-liste">
            <?php if (!empty($site['phone_display'])): ?>
              <a href="tel:<?php echo e($site['phone_link'] ?? ''); ?>" class="ph-link">
                <?php echo bm_site_icon($site, 'phone_icon', '📞'); ?><?php echo e($site['phone_display']); ?>
              </a>
            <?php endif; ?>
            <?php if (!empty($site['whatsapp_link'])): ?>
              <a href="<?php echo e(bm_safe_url($site['whatsapp_link'])); ?>" target="_blank" rel="noopener" class="ph-link">
                <?php echo bm_site_icon($site, 'whatsapp_icon', '💬'); ?><?php echo e($site['whatsapp_text'] ?? 'WhatsApp'); ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($related): ?>
<section class="soft">
  <div class="inner">
    <div class="section-title">
      <span class="tag"><?php echo e(bm_t('product.similar_tag', 'Aynı Kategoriden')); ?></span>
      <h2><?php echo e(bm_t('product.similar_title', 'Benzer Parçalar')); ?></h2>
    </div>
    <div class="catalog-grid">
      <?php foreach ($related as $item): ?>
        <a class="part-card reveal" href="/urun/<?php echo e($item['slug']); ?>">
          <span class="part-img">
            <?php if (!empty($item['image'])): ?>
              <img src="<?php echo e(bm_src($item['image'])); ?>" alt="<?php echo e($item['title']); ?>" loading="lazy"<?php echo bm_focus_style($item['image_focus'] ?? '', $item['image_zoom'] ?? null); ?>>
            <?php else: ?>
              <?php echo e($item['emoji'] ?: '⚙️'); ?>
            <?php endif; ?>
          </span>
          <div class="part-info">
            <span class="cat"><?php echo e(bm_product_category_path_label($item)); ?></span>
            <h3><?php echo e($item['title']); ?></h3>
            <?php if (!empty($item['code'])): ?>
              <span class="part-code"><?php echo e($item['code']); ?></span>
            <?php endif; ?>
            <span class="btn btn-outline part-order"><?php echo e(bm_t('products.card_cta', 'Detay & Teklif')); ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
