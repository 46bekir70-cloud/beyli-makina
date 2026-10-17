<?php
/**
 * Blog / makale listesi — /blog ve /makaleler adreslerinden açılır.
 *
 * Sayfanın üst bandı (başlık, açıklama, görsel) panelde "Blog" sayfası olarak
 * duruyor; makale listesi onun altına basılıyor. Böylece üst bant eskisi gibi
 * Sayfalar ekranından düzenlenebiliyor, makaleler ayrı ekrandan yönetiliyor.
 */
require_once __DIR__ . '/inc/articles.php';

$contentTmp = bm_load_content();
$blogSayfa  = bm_find_page($contentTmp, 'blog');

$q       = trim((string)($_GET['q'] ?? ''));
$tag     = trim((string)($_GET['etiket'] ?? ''));
$page    = max(1, (int)($_GET['sayfa'] ?? 1));

$etiketler = bm_article_tags();
$tagLabel  = $tag !== '' ? bm_article_tag_label($tag) : '';
if ($tag !== '' && $tagLabel === '') $tag = '';   // olmayan etiket

$sonuc = bm_articles(['q' => $q, 'tag' => $tag, 'page' => $page]);
// "Tümü" çipindeki sayı: etiket filtresi yokken zaten elimizde, varken
// bir kez daha sorulur (etikete göre değil, aramaya göre toplam).
$toplamTum = $tag === '' ? (int)$sonuc['total'] : (int)bm_articles(['q' => $q, 'per_page' => 1])['total'];

$baslik = $blogSayfa['title'] ?? 'Makaleler';
$bm_title = ($tagLabel !== '' ? $tagLabel . ' | ' : '') . $baslik . ' | Beyli Makina';
$bm_description = $blogSayfa['hero_subtitle']
    ?? bm_t('seo.blog_desc', 'Beyli Makina mühendislerinden CNC torna, freze ve imalat üzerine makaleler.');
$bm_active = 'blog';
$bm_image = $blogSayfa['hero_image'] ?? '';

$bm_breadcrumb = [['ad' => 'Ana Sayfa', 'yol' => '/'], ['ad' => $baslik, 'yol' => $tag !== '' ? '/blog' : '']];
if ($tagLabel !== '') $bm_breadcrumb[] = ['ad' => $tagLabel, 'yol' => ''];

/** Mevcut filtreleri koruyarak link üretir */
function bm_blog_url($overrides = []) {
    $params = array_merge([
        'etiket' => $_GET['etiket'] ?? '',
        'q'      => $_GET['q'] ?? '',
        'sayfa'  => $_GET['sayfa'] ?? '',
    ], $overrides);
    $params = array_filter($params, function ($v) { return $v !== '' && $v !== null; });
    return '/blog' . ($params ? ('?' . http_build_query($params)) : '');
}

require __DIR__ . '/inc/header.php';
?>

<section class="page-hero<?php echo !empty($blogSayfa['hero_image']) ? ' has-image' : ''; ?>"<?php echo bm_hero_style($blogSayfa['hero_image'] ?? '', $blogSayfa['hero_focus'] ?? ''); ?>>
  <?php if (!empty($blogSayfa['hero_tag'])): ?>
    <div class="tag-pill"><span class="dot" style="width:8px;height:8px;border-radius:50%;background:var(--red);display:inline-block;"></span> <?php echo e($blogSayfa['hero_tag']); ?></div>
  <?php endif; ?>
  <h1><?php echo e($baslik); ?></h1>
  <?php if (!empty($blogSayfa['hero_subtitle'])): ?>
    <p><?php echo e($blogSayfa['hero_subtitle']); ?></p>
  <?php endif; ?>
</section>

<?php if (!empty($blogSayfa['html']) && trim(strip_tags($blogSayfa['html'])) !== ''): ?>
  <section id="blog-giris">
    <div class="reveal bm-page-content"><?php echo $blogSayfa['html']; ?></div>
  </section>
<?php endif; ?>

<section class="blog-list">
  <div class="inner">

    <!-- Etiket filtresi + arama. Etiketler makalelerden otomatik çıkıyor,
         ayrıca bir kategori listesi tutmak gerekmiyor. -->
    <div class="blog-tools reveal">
      <?php if ($etiketler): ?>
        <div class="blog-tags" role="list">
          <a href="<?php echo e(bm_blog_url(['etiket' => '', 'sayfa' => ''])); ?>"
             class="blog-tag<?php echo $tag === '' ? ' on' : ''; ?>" role="listitem">
            <?php echo e(bm_t('blog.all', 'Tümü')); ?> <span class="n"><?php echo $toplamTum; ?></span>
          </a>
          <?php foreach ($etiketler as $t): ?>
            <a href="<?php echo e(bm_blog_url(['etiket' => $t['slug'], 'sayfa' => ''])); ?>"
               class="blog-tag<?php echo $tag === $t['slug'] ? ' on' : ''; ?>" role="listitem">
              <?php echo e($t['label']); ?> <span class="n"><?php echo (int)$t['count']; ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="get" class="blog-search">
        <?php if ($tag !== ''): ?><input type="hidden" name="etiket" value="<?php echo e($tag); ?>"><?php endif; ?>
        <?php $aramaYazi = bm_t('blog.search_ph', 'Makalelerde ara…'); ?>
        <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="<?php echo e($aramaYazi); ?>" aria-label="<?php echo e($aramaYazi); ?>">
        <button type="submit" class="btn"><?php echo e(bm_t('products.search_btn', 'Ara')); ?></button>
        <?php if ($q !== '' || $tag !== ''): ?>
          <a href="/blog" class="btn btn-outline"><?php echo e(bm_t('blog.clear', 'Temizle')); ?></a>
        <?php endif; ?>
      </form>
    </div>

    <?php if (!$sonuc['items']): ?>
      <div class="blog-empty reveal">
        <p>
          <?php if ($q !== '' || $tag !== ''): ?>
            <?php echo e(bm_t('blog.empty', 'Bu aramaya uyan makale bulunamadı.')); ?>
          <?php else: ?>
            <?php echo e(bm_t('blog.soon', 'Makaleler yakında burada olacak.')); ?>
          <?php endif; ?>
        </p>
        <?php if ($q !== '' || $tag !== ''): ?>
          <a href="/blog" class="btn btn-outline"><?php echo e(bm_t('blog.all_btn', 'Bütün Makaleler')); ?></a>
        <?php endif; ?>
      </div>
    <?php else: ?>

      <div class="blog-grid">
        <?php foreach ($sonuc['items'] as $m): ?>
          <?php
            $tarih = bm_article_date($m);
            $sure  = bm_article_reading_time($m['body'] ?? '');
          ?>
          <article class="blog-card reveal">
            <a class="bc-img" href="/makale/<?php echo e($m['slug']); ?>" aria-label="<?php echo e($m['title']); ?>">
              <?php if (!empty($m['image'])): ?>
                <img src="<?php echo e(bm_src($m['image'])); ?>" alt="<?php echo e($m['title']); ?>" loading="lazy"<?php echo bm_focus_style($m['image_focus'] ?? '', $m['image_zoom'] ?? null); ?>>
              <?php else: ?>
                <span class="bc-ph" aria-hidden="true">📄</span>
              <?php endif; ?>
              <span class="bc-sure"><?php echo e(sprintf(bm_t('blog.reading_time', '%s dk okuma'), (int)$sure)); ?></span>
            </a>
            <div class="bc-body">
              <?php $mEtiket = bm_article_tag_list($m['tags'] ?? ''); ?>
              <?php if ($mEtiket): ?>
                <div class="bc-tags">
                  <?php foreach (array_slice($mEtiket, 0, 2) as $t): ?>
                    <a href="<?php echo e(bm_blog_url(['etiket' => bm_article_tag_slug($t), 'q' => '', 'sayfa' => ''])); ?>" class="bc-tag"><?php echo e($t); ?></a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <h2><a href="/makale/<?php echo e($m['slug']); ?>"><?php echo e($m['title']); ?></a></h2>
              <p class="bc-ozet"><?php echo e(bm_article_summary($m)); ?></p>
              <div class="bc-alt">
                <?php if ($tarih !== ''): ?><span class="bc-tarih"><?php echo e($tarih); ?></span><?php endif; ?>
                <a href="/makale/<?php echo e($m['slug']); ?>" class="bc-devam"><?php echo e(bm_t('blog.read_more', 'Devamını oku →')); ?></a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($sonuc['pages'] > 1): ?>
        <?php
          // Ürünler sayfasıyla aynı düzen: aktif sayfanın etrafında birkaç numara.
          $ilk = max(1, $sonuc['page'] - 2);
          $son = min($sonuc['pages'], $sonuc['page'] + 2);
        ?>
        <nav class="pagination reveal" aria-label="<?php echo e(bm_t('a11y.pagination', 'Sayfalar')); ?>">
          <?php if ($sonuc['page'] > 1): ?>
            <a href="<?php echo e(bm_blog_url(['sayfa' => $sonuc['page'] - 1])); ?>" class="pg"><?php echo e(bm_t('pager.prev', '← Önceki')); ?></a>
          <?php endif; ?>
          <?php if ($ilk > 1): ?>
            <a href="<?php echo e(bm_blog_url(['sayfa' => 1])); ?>" class="pg">1</a>
            <?php if ($ilk > 2): ?><span class="pg dots">…</span><?php endif; ?>
          <?php endif; ?>
          <?php for ($i = $ilk; $i <= $son; $i++): ?>
            <a href="<?php echo e(bm_blog_url(['sayfa' => $i])); ?>"
               class="pg <?php echo $i === $sonuc['page'] ? 'active' : ''; ?>"><?php echo $i; ?></a>
          <?php endfor; ?>
          <?php if ($son < $sonuc['pages']): ?>
            <?php if ($son < $sonuc['pages'] - 1): ?><span class="pg dots">…</span><?php endif; ?>
            <a href="<?php echo e(bm_blog_url(['sayfa' => $sonuc['pages']])); ?>" class="pg"><?php echo $sonuc['pages']; ?></a>
          <?php endif; ?>
          <?php if ($sonuc['page'] < $sonuc['pages']): ?>
            <a href="<?php echo e(bm_blog_url(['sayfa' => $sonuc['page'] + 1])); ?>" class="pg"><?php echo e(bm_t('pager.next', 'Sonraki →')); ?></a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
