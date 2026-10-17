<?php
/**
 * Ürünler sekmesi — kategori filtresi, arama ve sayfalama.
 * Bütün filtreleme veritabanı tarafında yapılır; sayfa kaç ürün olursa olsun
 * aynı hızda açılır.
 */
require_once __DIR__ . '/inc/products.php';

$q        = trim((string)($_GET['q'] ?? ''));
$catSlug  = trim((string)($_GET['kategori'] ?? ''));
$page     = max(1, (int)($_GET['sayfa'] ?? 1));

$categoryTree = bm_category_tree(true);          // yalnızca ürünü olan dallar
$activeCat  = $catSlug !== '' ? bm_category_by_slug($catSlug) : null;
if ($catSlug !== '' && !$activeCat) $catSlug = '';
// Seçili kategorinin üst zinciri: o dal açık başlasın, üstleri de vurgulanır
$activePath = $activeCat ? bm_category_path($activeCat['id']) : [];

$result = bm_products([
    'category' => $catSlug,
    'q'        => $q,
    'page'     => $page,
]);

$bm_title = ($activeCat ? $activeCat['label'] . ' | ' : '') . 'Ürünlerimiz | Beyli Makina';
$bm_description = 'Beyli Makina ürün kataloğu — CNC torna, freze ve flanş parçaları. Kod veya isimle arayın.';
$bm_active = 'urunler';

// Konum zinciri — arama motoruna yapısal veri olarak gider
$bm_breadcrumb = [['ad' => 'Ana Sayfa', 'yol' => '/']];
if ($activeCat) {
    $bm_breadcrumb[] = ['ad' => 'Ürünler', 'yol' => '/urunler'];
    $duzKat = [];
    foreach (bm_category_flat(null) as $c) $duzKat[(int)$c['id']] = $c;
    foreach ($activePath as $cid) {
        if (!isset($duzKat[$cid])) continue;
        $sonMu = ($cid === (int)$activeCat['id']);
        $bm_breadcrumb[] = [
            'ad'  => $duzKat[$cid]['label'],
            'yol' => $sonMu ? '' : '/urunler?kategori=' . rawurlencode($duzKat[$cid]['slug']),
        ];
    }
} else {
    $bm_breadcrumb[] = ['ad' => 'Ürünler', 'yol' => ''];
}

/** Mevcut filtreleri koruyarak link üretir */
function bm_url($overrides = []) {
    $params = array_merge([
        'kategori' => $_GET['kategori'] ?? '',
        'q'        => $_GET['q'] ?? '',
        'sayfa'    => $_GET['sayfa'] ?? '',
    ], $overrides);
    $params = array_filter($params, function ($v) { return $v !== '' && $v !== null; });
    return '/urunler' . ($params ? ('?' . http_build_query($params)) : '');
}

/**
 * Sol paneldeki kategori ağacını basar.
 * Seçili kategori ve üst zinciri işaretlenir; o dal açık gelir.
 */
function bm_kategori_agaci($dugumler, $catSlug, $activePath, $derinlik = 0) {
    echo '<ul class="cat-tree' . ($derinlik ? ' alt' : '') . '">';
    foreach ($dugumler as $c) {
        $secili   = $catSlug === $c['slug'];
        $yolda    = in_array((int)$c['id'], $activePath, true);
        $cocuklar = array_values($c['children'] ?? []);
        echo '<li class="' . ($secili ? 'secili' : '') . ($yolda ? ' yolda' : '') . '">';
        echo '<a href="' . e(bm_url(['kategori' => $c['slug'], 'sayfa' => ''])) . '">';
        echo '<span class="ad">' . e($c['label']) . '</span>';
        echo '<span class="sayi">' . (int)($c['total_count'] ?? $c['product_count']) . '</span>';
        echo '</a>';
        // Alt kategoriler: seçili dalda açık, diğerlerinde kapalı dursun ki
        // liste uzayıp göz yormasın.
        if ($cocuklar) {
            $ac = $yolda || $secili;
            echo '<details class="cat-alt"' . ($ac ? ' open' : '') . '>';
            echo '<summary>' . e(sprintf(bm_t('products.subcats', '%s alt kategori'), count($cocuklar))) . '</summary>';
            bm_kategori_agaci($cocuklar, $catSlug, $activePath, $derinlik + 1);
            echo '</details>';
        }
        echo '</li>';
    }
    echo '</ul>';
}

require __DIR__ . '/inc/header.php';
?>

<?php // $content header.php tarafından tanımlanıyor
  $ph = $content['page_heroes']['urunler'] ?? []; ?>
<section class="page-hero<?php echo !empty($ph['image']) ? ' has-image' : ''; ?>"<?php echo bm_hero_style($ph['image'] ?? '', $ph['focus'] ?? ''); ?>>
  <div class="tag-pill"><span class="dot"></span> <?php echo e(bm_t('products.tag', 'Ürün Kataloğu')); ?></div>
  <h1><?php echo e($activeCat ? $activeCat['label'] : bm_t('products.title', 'Ürünlerimiz')); ?></h1>
  <p><?php echo e(bm_t('products.text', 'Ürettiğimiz parçaları kategoriye göre inceleyin ya da isim/kod ile arayın.')); ?></p>
</section>

<section class="soft" id="urun-listesi">
  <div class="inner">
    <div class="catalog-layout">

      <!-- SOL FİLTRE PANELİ -->
      <aside class="catalog-side reveal">
        <details class="filter-box" id="filtrePaneli" open>
          <summary>
            <span><?php echo e(bm_t('products.filters', 'Filtreler')); ?></span>
            <?php if ($catSlug !== '' || $q !== ''): ?><span class="acik-filtre">1</span><?php endif; ?>
          </summary>

          <div class="filter-box-ic">
            <?php if ($catSlug !== '' || $q !== ''): ?>
              <div class="secili-filtreler">
                <?php if ($activeCat): ?>
                  <a href="<?php echo e(bm_url(['kategori' => '', 'sayfa' => ''])); ?>" class="filtre-etiket">
                    <?php echo e($activeCat['label']); ?> <span aria-hidden="true">✕</span>
                  </a>
                <?php endif; ?>
                <?php if ($q !== ''): ?>
                  <a href="<?php echo e(bm_url(['q' => '', 'sayfa' => ''])); ?>" class="filtre-etiket">
                    &ldquo;<?php echo e($q); ?>&rdquo; <span aria-hidden="true">✕</span>
                  </a>
                <?php endif; ?>
                <a href="/urunler" class="filtre-temizle"><?php echo e(bm_t('products.clear_all', 'Tümünü temizle')); ?></a>
              </div>
            <?php endif; ?>

            <h3 class="filter-baslik"><?php echo e(bm_t('products.cats_title', 'Kategoriler')); ?></h3>
            <ul class="cat-tree">
              <li class="<?php echo $catSlug === '' ? 'secili' : ''; ?>">
                <a href="<?php echo e(bm_url(['kategori' => '', 'sayfa' => ''])); ?>">
                  <span class="ad"><?php echo e(bm_t('products.all', 'Tümü')); ?></span>
                  <span class="sayi"><?php echo (int)bm_products(['per_page' => 1])['total']; ?></span>
                </a>
              </li>
            </ul>
            <?php bm_kategori_agaci($categoryTree, $catSlug, $activePath); ?>

            <?php if (!$categoryTree): ?>
              <p class="hint"><?php echo e(bm_t('products.no_cats', 'Henüz kategori yok.')); ?></p>
            <?php endif; ?>
          </div>
        </details>
      </aside>

      <!-- SAĞ: ARAMA + SONUÇLAR -->
      <div class="catalog-main">

    <form class="product-search reveal" method="get" action="/urunler" role="search">
      <?php if ($catSlug !== ''): ?>
        <input type="hidden" name="kategori" value="<?php echo e($catSlug); ?>">
      <?php endif; ?>
      <div class="search-field">
        <span class="search-ic" aria-hidden="true">🔍</span>
        <input type="search" name="q" value="<?php echo e($q); ?>"
               placeholder="<?php echo e(bm_t('products.search_ph', 'Ürün adı veya parça kodu ile ara — örn. BM-TO-0042')); ?>"
               aria-label="<?php echo e(bm_t('products.search_aria', 'Ürün ara')); ?>">
      </div>
      <button type="submit" class="btn"><?php echo e(bm_t('products.search_btn', 'Ara')); ?></button>
      <?php if ($q !== ''): ?>
        <a href="<?php echo e(bm_url(['q' => '', 'sayfa' => ''])); ?>" class="btn btn-outline"><?php echo e(bm_t('products.clear', 'Temizle')); ?></a>
      <?php endif; ?>
    </form>

    <div class="result-line reveal">
      <?php if ($result['total'] === 0): ?>
        <?php echo e(bm_t('products.result_none', 'Sonuç bulunamadı.')); ?>
      <?php else: ?>
        <strong><?php echo number_format($result['total'], 0, ',', '.'); ?></strong> <?php echo e(bm_t('products.count_word', 'ürün')); ?>
        <?php if ($q !== ''): ?>— &ldquo;<?php echo e($q); ?>&rdquo; <?php echo e(bm_t('products.search_for', 'araması')); ?><?php endif; ?>
        <?php if ($result['pages'] > 1): ?>
          <span class="page-of"><?php echo e(bm_t('products.page_label', 'Sayfa')); ?> <?php echo $result['page']; ?> / <?php echo $result['pages']; ?></span>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if ($result['total'] === 0): ?>
      <div class="empty-state reveal">
        <div class="ic">🔎</div>
        <h3><?php echo e(bm_t('products.empty_title', 'Aradığınız ürünü bulamadık')); ?></h3>
        <p><?php echo e(bm_t('products.empty_text', 'Farklı bir kelime deneyin ya da tüm kataloğa göz atın. Aradığınız parça listede yoksa da üretebiliriz — teklif formundan teknik çizimi iletmeniz yeterli.')); ?></p>
        <div class="empty-actions">
          <a href="/urunler" class="btn btn-outline"><?php echo e(bm_t('products.all_btn', 'Tüm Ürünler')); ?></a>
          <a href="/teklif" class="btn"><?php echo e(bm_t('products.custom_btn', 'Özel Sipariş Ver')); ?></a>
        </div>
      </div>
    <?php else: ?>
      <div class="catalog-grid">
        <?php foreach ($result['items'] as $item): ?>
          <!-- Kartın tamamı tek bir bağlantı. İçine ikinci bir <a>/<button>
               konulamaz (geçersiz HTML), o yüzden "Detay & Teklif" bir span. -->
          <a class="part-card reveal" href="/urun/<?php echo e($item['slug']); ?>">
            <span class="part-img">
              <?php if (!empty($item['image'])): ?>
                <img src="<?php echo e(bm_src($item['image'])); ?>" alt="<?php echo e($item['title']); ?>" loading="lazy"<?php echo bm_focus_style($item['image_focus'] ?? '', $item['image_zoom'] ?? null); ?>>
              <?php else: ?>
                <?php echo e($item['emoji'] ?: '⚙️'); ?>
              <?php endif; ?>
            </span>
            <div class="part-info">
              <span class="cat"><?php echo e(bm_product_category_path_label($item) ?: bm_t('home.catalog.no_cat', 'Ürün')); ?></span>
              <h3><?php echo e($item['title']); ?></h3>
              <?php if (!empty($item['code'])): ?>
                <span class="part-code"><?php echo e($item['code']); ?></span>
              <?php endif; ?>
              <ul class="spec-list">
                <?php foreach (array_slice(bm_visible_specs($item['specs']), 0, 3) as $spec): ?>
                  <li><span><?php echo e($spec[0]); ?></span><span><?php echo e($spec[1]); ?></span></li>
                <?php endforeach; ?>
              </ul>
              <span class="btn btn-outline part-order"><?php echo e(bm_t('products.card_cta', 'Detay & Teklif')); ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($result['pages'] > 1): ?>
        <?php
          // Kalabalık olmasın diye aktif sayfanın etrafındaki birkaç numara gösterilir.
          $start = max(1, $result['page'] - 2);
          $end   = min($result['pages'], $result['page'] + 2);
        ?>
        <nav class="pagination reveal" aria-label="<?php echo e(bm_t('a11y.pagination', 'Sayfalar')); ?>">
          <?php if ($result['page'] > 1): ?>
            <a href="<?php echo e(bm_url(['sayfa' => $result['page'] - 1])); ?>" class="pg"><?php echo e(bm_t('pager.prev', '← Önceki')); ?></a>
          <?php endif; ?>

          <?php if ($start > 1): ?>
            <a href="<?php echo e(bm_url(['sayfa' => 1])); ?>" class="pg">1</a>
            <?php if ($start > 2): ?><span class="pg dots">…</span><?php endif; ?>
          <?php endif; ?>

          <?php for ($i = $start; $i <= $end; $i++): ?>
            <a href="<?php echo e(bm_url(['sayfa' => $i])); ?>"
               class="pg <?php echo $i === $result['page'] ? 'active' : ''; ?>"><?php echo $i; ?></a>
          <?php endfor; ?>

          <?php if ($end < $result['pages']): ?>
            <?php if ($end < $result['pages'] - 1): ?><span class="pg dots">…</span><?php endif; ?>
            <a href="<?php echo e(bm_url(['sayfa' => $result['pages']])); ?>" class="pg"><?php echo $result['pages']; ?></a>
          <?php endif; ?>

          <?php if ($result['page'] < $result['pages']): ?>
            <a href="<?php echo e(bm_url(['sayfa' => $result['page'] + 1])); ?>" class="pg"><?php echo e(bm_t('pager.next', 'Sonraki →')); ?></a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>

      </div><!-- /catalog-main -->
    </div><!-- /catalog-layout -->
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
