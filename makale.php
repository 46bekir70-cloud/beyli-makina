<?php
/**
 * Tek makale — /makale/<slug>
 *
 * İçerik panelden zengin metin editörüyle giriliyor; bu sayfa yalnızca onu
 * okunaklı bir sütun genişliğinde basıyor ve altına benzer makaleleri koyuyor.
 */
require_once __DIR__ . '/inc/articles.php';

// Panelin "kaydetmeden önizleme" özelliği makaleyi hazır dizi olarak enjekte eder
if (!empty($GLOBALS['bm_preview_article'])) {
    $makale = $GLOBALS['bm_preview_article'];
} else {
    $slug = trim((string)($_GET['slug'] ?? ''));
    $makale = $slug !== '' ? bm_article_by_slug($slug) : null;
}

if (!$makale) {
    http_response_code(404);
    $bm_title = 'Makale Bulunamadı | Beyli Makina';
    require __DIR__ . '/inc/header.php';
    echo '<section style="text-align:center;padding:110px 24px;">';
    echo '<h1 style="font-size:30px;color:var(--blue-dark);margin-bottom:14px;">' . e(bm_t('nf.article_title', 'Makale Bulunamadı')) . '</h1>';
    echo '<p style="color:var(--muted);margin-bottom:24px;">' . e(bm_t('nf.article_text', 'Bu yazı kaldırılmış ya da adres hatalı olabilir.')) . '</p>';
    echo '<a href="/blog" class="btn">' . e(bm_t('nf.article_btn', 'Bütün Makaleler')) . '</a>';
    echo '</section>';
    require __DIR__ . '/inc/footer.php';
    exit;
}

$contentTmp = bm_load_content();
$blogSayfa  = bm_find_page($contentTmp, 'blog');
$blogAdi    = $blogSayfa['title'] ?? 'Makaleler';

$etiketler = bm_article_tag_list($makale['tags'] ?? '');
$tarih     = bm_article_date($makale);
$sure      = bm_article_reading_time($makale['body'] ?? '');
$benzer    = bm_related_articles($makale, 3);

$bm_title = $makale['title'] . ' | Beyli Makina';
$bm_description = bm_article_summary($makale, 155);
$bm_active = 'blog';
$bm_image = $makale['image'] ?? '';
$bm_og_type = 'article';
$bm_breadcrumb = [
    ['ad' => 'Ana Sayfa', 'yol' => '/'],
    ['ad' => $blogAdi, 'yol' => '/blog'],
    ['ad' => $makale['title'], 'yol' => ''],
];
// Makalenin arama motoru kaydı (header.php basıyor)
$bm_jsonld = [bm_article_jsonld($makale)];

require __DIR__ . '/inc/header.php';
?>

<section class="article-detail">
  <nav class="breadcrumb reveal" aria-label="<?php echo e(bm_t('a11y.breadcrumb', 'Konum')); ?>">
    <a href="/"><?php echo e(bm_t('nav.home', 'Ana Sayfa')); ?></a> <span>›</span><a href="/blog"><?php echo e($blogAdi); ?></a><span>›</span><strong><?php echo e($makale['title']); ?></strong>
  </nav>

  <article class="article-wrap">
    <header class="article-head reveal">
      <?php if ($etiketler): ?>
        <div class="ar-tags">
          <?php foreach ($etiketler as $t): ?>
            <a href="/blog?etiket=<?php echo e(bm_article_tag_slug($t)); ?>" class="ar-tag"><?php echo e($t); ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <h1><?php echo e($makale['title']); ?></h1>
      <div class="ar-meta">
        <?php if ($tarih !== ''): ?><span><?php echo e($tarih); ?></span><?php endif; ?>
        <span><?php echo e(sprintf(bm_t('article.reading_long', '%s dakika okuma'), (int)$sure)); ?></span>
        <?php if (!empty($makale['author'])): ?><span><?php echo e($makale['author']); ?></span><?php endif; ?>
      </div>
      <?php if (!empty($makale['summary'])): ?>
        <p class="ar-ozet"><?php echo e($makale['summary']); ?></p>
      <?php endif; ?>
    </header>

    <?php if (!empty($makale['image'])): ?>
      <figure class="article-visual reveal">
        <img src="<?php echo e(bm_src($makale['image'])); ?>" alt="<?php echo e($makale['title']); ?>"<?php echo bm_focus_style($makale['image_focus'] ?? '', $makale['image_zoom'] ?? null); ?>>
      </figure>
    <?php endif; ?>

    <div class="article-body bm-page-content reveal">
      <?php echo $makale['body'] ?? ''; /* panelde zengin metin editörüyle girilir */ ?>
    </div>

    <div class="article-cta reveal">
      <h2><?php echo e(bm_t('article.cta_title', 'Bu konuda bir parçaya mı ihtiyacın var?')); ?></h2>
      <p><?php echo e(bm_t('article.cta_text', 'Teknik çizimini ya da numunesini ilet, sana özel teklif hazırlayalım.')); ?></p>
      <a href="/teklif" class="btn"><?php echo e(bm_t('article.cta_btn', 'Teklif İste')); ?></a>
    </div>
  </article>
</section>

<?php if ($benzer): ?>
<section class="soft">
  <div class="inner">
    <div class="section-title">
      <span class="tag"><?php echo e(bm_t('article.related_tag', 'Devamı')); ?></span>
      <h2><?php echo e(bm_t('article.related_title', 'Bunlar da İlgini Çekebilir')); ?></h2>
    </div>
    <div class="blog-grid">
      <?php foreach ($benzer as $m): ?>
        <article class="blog-card reveal">
          <a class="bc-img" href="/makale/<?php echo e($m['slug']); ?>" aria-label="<?php echo e($m['title']); ?>">
            <?php if (!empty($m['image'])): ?>
              <img src="<?php echo e(bm_src($m['image'])); ?>" alt="<?php echo e($m['title']); ?>" loading="lazy"<?php echo bm_focus_style($m['image_focus'] ?? '', $m['image_zoom'] ?? null); ?>>
            <?php else: ?>
              <span class="bc-ph" aria-hidden="true">📄</span>
            <?php endif; ?>
            <span class="bc-sure"><?php echo e(sprintf(bm_t('blog.reading_time', '%s dk okuma'), (int)bm_article_reading_time($m['body'] ?? ''))); ?></span>
          </a>
          <div class="bc-body">
            <h2><a href="/makale/<?php echo e($m['slug']); ?>"><?php echo e($m['title']); ?></a></h2>
            <p class="bc-ozet"><?php echo e(bm_article_summary($m, 120)); ?></p>
            <div class="bc-alt">
              <?php $bt = bm_article_date($m); if ($bt !== ''): ?><span class="bc-tarih"><?php echo e($bt); ?></span><?php endif; ?>
              <a href="/makale/<?php echo e($m['slug']); ?>" class="bc-devam"><?php echo e(bm_t('blog.read_more', 'Devamını oku →')); ?></a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
