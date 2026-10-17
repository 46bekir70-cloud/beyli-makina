<?php
require_once __DIR__ . '/inc/functions.php';
$contentTmp = bm_load_content();
// Admin panelin "kaydetmeden önizleme" özelliği sayfayı hazır dizi olarak
// enjekte eder; normal ziyaretlerde bu değişken tanımsızdır.
if (!empty($GLOBALS['bm_preview_page'])) {
    $page = $GLOBALS['bm_preview_page'];
    $slug = $page['slug'] ?? '';
} else {
    $slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
    $page = $slug !== '' ? bm_find_page($contentTmp, $slug) : null;
}

if (!$page) {
    http_response_code(404);
    $bm_title = 'Sayfa Bulunamadı | Beyli Makina';
    require __DIR__ . '/inc/header.php';
    echo '<section style="text-align:center;padding:120px 24px;">';
    echo '<h1 style="font-size:32px;color:var(--blue-dark);margin-bottom:14px;">' . e(bm_t('nf.page_title', 'Sayfa Bulunamadı')) . '</h1>';
    echo '<p style="color:var(--muted);margin-bottom:24px;">' . e(bm_t('nf.page_text', 'Aradığınız sayfa kaldırılmış ya da hiç var olmamış olabilir.')) . '</p>';
    echo '<a href="/" class="btn">' . e(bm_t('nf.page_btn', 'Ana Sayfaya Dön')) . '</a>';
    echo '</section>';
    require __DIR__ . '/inc/footer.php';
    exit;
}

$bm_title = $page['title'] . ' | Beyli Makina';
$bm_description = $page['hero_subtitle'] ?? ($page['title'] . ' sayfası');
$bm_active = $page['slug'];
$bm_image = $page['hero_image'] ?? '';
// Konum zinciri — arama motoruna yapısal veri olarak gider
$bm_breadcrumb = [
    ['ad' => 'Ana Sayfa', 'yol' => '/'],
    ['ad' => $page['title'], 'yol' => ''],
];
require __DIR__ . '/inc/header.php';
?>

<section class="page-hero<?php echo !empty($page['hero_image']) ? ' has-image' : ''; ?>"<?php echo bm_hero_style($page['hero_image'] ?? '', $page['hero_focus'] ?? ''); ?>>
  <?php if (!empty($page['hero_tag'])): ?>
  <div class="tag-pill"><span class="dot" style="width:8px;height:8px;border-radius:50%;background:var(--red);display:inline-block;"></span> <?php echo e($page['hero_tag']); ?></div>
  <?php endif; ?>
  <h1><?php echo e($page['title']); ?></h1>
  <?php if (!empty($page['hero_subtitle'])): ?>
    <p><?php echo e($page['hero_subtitle']); ?></p>
  <?php endif; ?>
</section>

<section id="sayfa-icerik">
  <div class="reveal bm-page-content">
    <?php echo $page['html'] ?? ''; /* admin panelinden zengin metin editörü ile girilir */ ?>
  </div>
</section>

<section id="siparis-cta">
  <div class="order-section reveal" style="text-align:center;padding:56px 40px;">
    <span class="tag" style="display:block;color:#ff9d98;font-weight:700;font-size:13px;letter-spacing:3px;text-transform:uppercase;margin-bottom:10px;"><?php echo e(bm_t('cta.page_tag', 'Birlikte Çalışalım')); ?></span>
    <h2 style="font-size:27px;font-weight:800;margin:6px 0 10px;color:#fff;"><?php echo e(bm_t('cta.page_title', 'Projeniz İçin Teklif Alın')); ?></h2>
    <p style="color:#c9d8f5;font-weight:300;margin-bottom:24px;"><?php echo e(bm_t('cta.page_text', 'Parçanızın teknik detaylarını iletin, size en kısa sürede dönüş yapalım.')); ?></p>
    <a href="/teklif" class="btn"><?php echo e(bm_t('cta.page_btn', 'Özel Sipariş Formuna Git →')); ?></a>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
