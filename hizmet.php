<?php
/**
 * Hizmet detay sayfası — /hizmet/<slug>
 *
 * Amaç: hizmeti ve o hizmette kullanılan makineleri teknik özellikleriyle
 * tanıtmak. İçeriğin tamamı yönetim panelinden girilir.
 */
require_once __DIR__ . '/inc/functions.php';

$contentTmp = bm_load_content();

// Admin panelin "kaydetmeden önizleme" özelliği hizmeti hazır dizi olarak
// enjekte eder; normal ziyaretlerde bu değişken tanımsızdır.
if (!empty($GLOBALS['bm_preview_service'])) {
    $service = $GLOBALS['bm_preview_service'];
    $slug = $service['slug'] ?? '';
} else {
    $slug = trim((string)($_GET['slug'] ?? ''));
    $service = $slug !== '' ? bm_find_service($contentTmp, $slug) : null;
    // Detay sayfası kapatılmış hizmetler dışarıdan açılamaz
    if ($service && empty($service['has_page'])) $service = null;
}

if (!$service) {
    http_response_code(404);
    $bm_title = 'Hizmet Bulunamadı | Beyli Makina';
    require __DIR__ . '/inc/header.php';
    echo '<section style="text-align:center;padding:110px 24px;">';
    echo '<h1 style="font-size:30px;color:var(--blue-dark);margin-bottom:14px;">' . e(bm_t('nf.service_title', 'Hizmet Bulunamadı')) . '</h1>';
    echo '<p style="color:var(--muted);margin-bottom:24px;">' . e(bm_t('nf.service_text', 'Bu sayfa kaldırılmış ya da adres hatalı olabilir.')) . '</p>';
    echo '<a href="/#hizmetler" class="btn">' . e(bm_t('nf.service_btn', 'Hizmetlerimize Dön')) . '</a>';
    echo '</section>';
    require __DIR__ . '/inc/footer.php';
    exit;
}

$bm_title = $service['title'] . ' | Beyli Makina';
$bm_description = $service['hero_subtitle'] ?: ($service['desc'] ?? $service['title']);
$bm_active = 'hizmet-' . ($service['slug'] ?? '');
// Paylaşım kartı görseli: önce sayfa başlık görseli, yoksa kart görseli
$bm_image = ($service['hero_image'] ?? '') ?: (($service['card_image'] ?? '') ?: ($service['image'] ?? ''));

$stats    = array_values(array_filter((array)($service['stats'] ?? []), function ($s) {
    return trim((string)($s['num'] ?? '')) !== '' || trim((string)($s['label'] ?? '')) !== '';
}));
$machines = array_values(array_filter((array)($service['machines'] ?? []), function ($m) {
    return trim((string)($m['name'] ?? '')) !== '';
}));
$otherServices = array_values(array_filter(bm_services($contentTmp, true), function ($s) use ($service) {
    return ($s['slug'] ?? '') !== ($service['slug'] ?? '');
}));

// Konum zinciri — arama motoruna yapısal veri olarak gider
$bm_breadcrumb = [
    ['ad' => 'Ana Sayfa', 'yol' => '/'],
    ['ad' => 'Hizmetlerimiz', 'yol' => '/#hizmetler'],
    ['ad' => $service['title'], 'yol' => ''],
];

// Hizmetin arama motoru ve yapay zeka yapısal verisi (header.php basıyor)
$bm_jsonld = [bm_service_jsonld($service)];

require __DIR__ . '/inc/header.php';
?>

<section class="page-hero<?php echo !empty($service['hero_image']) ? ' has-image' : ''; ?>"<?php echo bm_hero_style($service['hero_image'] ?? '', $service['hero_focus'] ?? ''); ?>>
  <?php if (!empty($service['hero_tag'])): ?>
    <div class="tag-pill"><span class="dot"></span> <?php echo e($service['hero_tag']); ?></div>
  <?php endif; ?>
  <h1><?php echo e($service['title']); ?></h1>
  <?php if (!empty($service['hero_subtitle'])): ?>
    <p><?php echo e($service['hero_subtitle']); ?></p>
  <?php endif; ?>
</section>

<?php if ($stats): ?>
<section class="service-stats-section">
  <div class="service-stats">
    <?php foreach ($stats as $s): ?>
      <div class="svc-stat reveal">
        <div class="num"><?php echo e($s['num'] ?? ''); ?></div>
        <div class="lbl"><?php echo e($s['label'] ?? ''); ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($service['html']) || !empty($service['image'])): ?>
<section class="service-intro-section">
  <div class="service-intro <?php echo !empty($service['image']) ? 'has-image' : ''; ?>">
    <?php if (!empty($service['html'])): ?>
      <div class="bm-page-content reveal"><?php echo $service['html']; ?></div>
    <?php endif; ?>
    <?php if (!empty($service['image'])): ?>
      <div class="service-visual reveal">
        <img src="<?php echo e(bm_src($service['image'])); ?>" alt="<?php echo e($service['title']); ?>" loading="lazy">
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($machines): ?>
<section class="soft" id="makineler">
  <div class="inner">
    <div class="section-title reveal">
      <span class="tag"><?php echo e(bm_t('service.machines_tag', 'Makine Parkuru')); ?></span>
      <h2><?php echo e(bm_t('service.machines_title', 'Bu Hizmette Kullandığımız Makineler')); ?></h2>
      <p><?php echo e(bm_t('service.machines_text', 'Teknik kapasitelerimizi aşağıda bulabilirsiniz.')); ?></p>
    </div>

    <div class="machine-list">
      <?php foreach ($machines as $m): ?>
        <?php $mspecs = (array)($m['specs'] ?? []); ?>
        <article class="machine-card reveal">
          <div class="machine-visual<?php echo !empty($m['image']) ? ' has-img' : ''; ?>">
            <?php if (!empty($m['image'])): ?>
              <img src="<?php echo e(bm_src($m['image'])); ?>" alt="<?php echo e($m['name']); ?>" loading="lazy">
            <?php else: ?>
              <span class="ph"><?php echo e($m['emoji'] ?? '⚙️'); ?></span>
            <?php endif; ?>
          </div>
          <div class="machine-body">
            <h3><?php echo e($m['name']); ?></h3>
            <?php if (!empty($m['desc'])): ?>
              <p class="machine-desc"><?php echo nl2br(e($m['desc'])); ?></p>
            <?php endif; ?>
            <?php if ($mspecs): ?>
              <table class="spec-table">
                <tbody>
                  <?php foreach ($mspecs as $sp): ?>
                    <tr>
                      <th><?php echo e($sp[0] ?? ''); ?></th>
                      <td><?php echo e($sp[1] ?? ''); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section id="hizmet-cta">
  <div class="home-cta reveal">
    <span class="tag"><?php echo e(bm_t('service.cta_tag', 'Birlikte Çalışalım')); ?></span>
    <h2><?php echo e(sprintf(bm_t('service.cta_title', '%s için teklif alın'), $service['title'])); ?></h2>
    <p><?php echo e(bm_t('service.cta_text', 'Parçanızın teknik çizimini ve detaylarını iletin, size en kısa sürede dönüş yapalım.')); ?></p>
    <div class="home-cta-actions">
      <a href="/teklif?urun=<?php echo urlencode($service['title']); ?>" class="btn"><?php echo e(bm_t('service.cta_btn', 'Teklif Formuna Git →')); ?></a>
      <?php if (!empty($site['phone_display'])): ?>
        <a href="tel:<?php echo e($site['phone_link'] ?? ''); ?>" class="btn btn-outline btn-on-dark">
          <?php echo bm_site_icon($site, 'phone_icon', '📞'); ?><?php echo e($site['phone_display']); ?>
        </a>
      <?php endif; ?>
      <?php if (!empty($site['whatsapp_link'])): ?>
        <a href="<?php echo e(bm_safe_url($site['whatsapp_link'])); ?>" target="_blank" rel="noopener"
           class="btn btn-outline btn-on-dark btn-whatsapp">
          <?php echo bm_site_icon($site, 'whatsapp_icon', '💬'); ?><?php echo e($site['whatsapp_text'] ?? 'WhatsApp'); ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($otherServices): ?>
<section class="soft">
  <div class="inner">
    <div class="section-title reveal">
      <span class="tag"><?php echo e(bm_t('service.others_tag', 'Diğer Hizmetler')); ?></span>
      <h2><?php echo e(bm_t('service.others_title', 'Neler Yapıyoruz?')); ?></h2>
    </div>
    <div class="services-grid">
      <?php foreach ($otherServices as $s): ?>
        <?php $cardImg = trim((string)($s['card_image'] ?? '')); ?>
        <a class="service-card reveal is-link<?php echo $cardImg !== '' ? ' has-bg' : ''; ?>" href="/hizmet/<?php echo e($s['slug']); ?>">
          <?php if ($cardImg !== ''): ?>
            <span class="card-bg"<?php echo bm_card_bg_style($cardImg, $s['card_focus'] ?? '', $s['card_blur'] ?? null, $s['card_bright'] ?? null); ?>></span>
          <?php endif; ?>
          <h3><?php echo e($s['title']); ?></h3>
          <p><?php echo e($s['desc'] ?? ''); ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
