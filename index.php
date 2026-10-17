<?php
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/products.php';
$bm_title = 'Beyli Makina | CNC Torna & Freze İmalat';
$bm_description = 'Kahramanmaraş Organize Sanayi Bölgesi\'nde hassas CNC torna ve freze imalatı. Prototipten seri üretime, makine mühendisliği destekli parça üretimi.';
$bm_active = 'home';

require __DIR__ . '/inc/header.php';

$hero = $content['hero'] ?? [];
$about = $content['about_teaser'] ?? [];
// Hizmet kartları panelden girilen "Sıra" değerine göre dizilir. Ham diziyi
// okumak yanlıştı: üst menü, footer, hizmet sayfası ve site haritası zaten
// bm_services() kullanıyor; ana sayfa da aynı sırayı kullansin.
$services = bm_services($content);
$order = $content['order_section'] ?? [];
$site = $content['site'] ?? [];

// Ana sayfada tüm katalog değil, panelden "öne çıkan" işaretlenmiş ürünler
// gösterilir. Tam liste /urunler sayfasında.
$featured = bm_products(['featured' => true, 'per_page' => 8]);
$productTotal = bm_products(['per_page' => 1])['total'];
?>

<?php
  $heroSlides = bm_hero_slides($content);
  $heroInterval = bm_hero_slide_interval($content);
?>
<section class="hero<?php echo $heroSlides ? ' has-slides' : ''; ?>">
  <?php if ($heroSlides): ?>
    <!-- Kayan arka plan görselleri. Yazının okunması için üstüne lacivert bir
         katman biniyor (CSS: .hero-slides::after). JS yoksa ilk görsel sabit
         durur, slayt kaymaz — içerik her koşulda görünür. -->
    <div class="hero-slides" data-hero-slider data-interval="<?php echo (int)($heroInterval * 1000); ?>">
      <div class="hero-track">
        <?php foreach ($heroSlides as $s): ?>
          <span class="hero-slide"<?php echo bm_hero_style($s['image'], $s['focus']); ?>></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
  <?php
    // Rozete panelden bir bağlantı verilirse tıklanabilir olur (ör. dükkan
    // konumu). Bağlantı yoksa eskisi gibi düz yazı kalır.
    $tagHref = bm_safe_url($hero['tag_link'] ?? '');
  ?>
  <?php if (!empty($hero['tag'])): ?>
    <?php if ($tagHref !== ''): ?>
      <a class="tag-pill is-link" href="<?php echo e($tagHref); ?>"
         <?php echo strpos($tagHref, 'http') === 0 ? 'target="_blank" rel="noopener"' : ''; ?>>
        <span class="dot"></span> <?php echo e($hero['tag']); ?>
        <span class="tag-arrow" aria-hidden="true">↗</span>
      </a>
    <?php else: ?>
      <div class="tag-pill"><span class="dot"></span> <?php echo e($hero['tag']); ?></div>
    <?php endif; ?>
  <?php endif; ?>
  <h1><?php echo e($hero['title_before'] ?? ''); ?> <span class="grad"><?php echo e($hero['title_highlight'] ?? ''); ?></span> <?php echo e($hero['title_after'] ?? ''); ?></h1>
  <p><?php echo e($hero['subtitle'] ?? ''); ?></p>
  <div class="hero-buttons">
    <a href="<?php echo e($hero['cta1_link'] ?? '#katalog'); ?>" class="btn"><?php echo e($hero['cta1_text'] ?? ''); ?></a>
    <a href="<?php echo e($hero['cta2_link'] ?? '#siparis'); ?>" class="btn btn-outline" style="border-color:#fff;color:#fff;"><?php echo e($hero['cta2_text'] ?? ''); ?></a>
  </div>
  <?php
    // Boş istatistik listesi basılmıyor: bloğun üstünde ayırıcı çizgi var,
    // içi boşken ekranda öksüz bir çizgi kalıyordu.
    $heroStats = array_values(array_filter((array)($hero['stats'] ?? []), function ($s) {
        return trim((string)($s['num'] ?? '')) !== '' || trim((string)($s['label'] ?? '')) !== '';
    }));
  ?>
  <?php if ($heroStats): ?>
    <div class="hero-stats">
      <?php foreach ($heroStats as $s): ?>
        <div class="stat"><h3><?php echo e($s['num'] ?? ''); ?></h3><p><?php echo e($s['label'] ?? ''); ?></p></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (count($heroSlides) > 1): ?>
    <!-- Nokta belirteçler: hangi slaytta olduğunu gösterir, tıklanınca o
         slayta geçer. Tek görsel varsa hiç basılmıyor. -->
    <div class="hero-dots" data-hero-dots>
      <?php foreach ($heroSlides as $i => $s): ?>
        <button type="button" class="hero-dot<?php echo $i === 0 ? ' active' : ''; ?>"
                data-go="<?php echo (int)$i; ?>"
                aria-label="<?php echo e(sprintf(bm_t('a11y.slide', '%s. görsel'), (int)$i + 1)); ?>"></button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="soft">
  <div class="inner">
    <div class="about">
      <div class="reveal">
        <span class="tag" style="display:block;color:var(--red);font-weight:700;font-size:13px;letter-spacing:3px;text-transform:uppercase;margin-bottom:10px;"><?php echo e($about['tag'] ?? ''); ?></span>
        <h2><?php echo e($about['title'] ?? ''); ?></h2>
        <?php echo $about['html'] ?? ''; /* zengin metin — admin tarafından girilir */ ?>
        <div class="stats">
          <?php foreach (($about['stats'] ?? []) as $s): ?>
            <div class="stat"><div class="num"><?php echo e($s['num']); ?></div><div class="lbl"><?php echo e($s['label']); ?></div></div>
          <?php endforeach; ?>
        </div>
        <?php if (!empty($about['link_target'])): ?>
        <div style="margin-top:24px;">
          <a href="/<?php echo urlencode($about['link_target']); ?>" class="btn btn-blue"><?php echo e($about['link_text'] ?? 'Devamını Oku →'); ?></a>
        </div>
        <?php endif; ?>
      </div>
      <div class="about-visual reveal">
        <?php if (!empty($about['visual_image'])): ?>
          <img src="<?php echo e(bm_src($about['visual_image'])); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:20px;position:relative;z-index:1;">
        <?php else: ?>
          <span><?php echo e($about['visual_emoji'] ?? '🏭'); ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section id="hizmetler">
  <div class="section-title reveal">
    <span class="tag"><?php echo e(bm_t('home.services.tag', 'Hizmetlerimiz')); ?></span>
    <h2><?php echo e(bm_t('home.services.title', 'Neler Yapıyoruz?')); ?></h2>
    <p><?php echo e(bm_t('home.services.text', 'Makine mühendisliği altyapımızla uçtan uca imalat çözümleri sunuyoruz.')); ?></p>
  </div>
  <div class="services-grid">
    <?php foreach ($services as $s): ?>
      <?php
        // Detay sayfası açık olan hizmetlerin kartı tıklanabilir olur.
        $hasPage = !empty($s['has_page']) && !empty($s['slug']);
        $tag = $hasPage ? 'a' : 'div';
        $href = $hasPage ? ' href="/hizmet/' . e($s['slug']) . '"' : '';
        $cardImg = trim((string)($s['card_image'] ?? ''));
      ?>
      <<?php echo $tag . $href; ?> class="service-card reveal<?php echo $hasPage ? ' is-link' : ''; ?><?php echo $cardImg !== '' ? ' has-bg' : ''; ?>">
        <?php if ($cardImg !== ''): ?>
          <span class="card-bg"<?php echo bm_card_bg_style($cardImg, $s['card_focus'] ?? '', $s['card_blur'] ?? null, $s['card_bright'] ?? null); ?>></span>
        <?php endif; ?>
        <h3><?php echo e($s['title']); ?></h3>
        <p><?php echo e($s['desc'] ?? ''); ?></p>
        <?php if ($hasPage): ?><span class="svc-more"><?php echo e(bm_t('home.services.more', 'Detayları Gör →')); ?></span><?php endif; ?>
      </<?php echo $tag; ?>>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($featured['items']): ?>
<section class="soft" id="katalog">
  <div class="inner">
    <div class="section-title reveal">
      <span class="tag"><?php echo e(bm_t('home.catalog.tag', 'Ürün Kataloğu')); ?></span>
      <h2><?php echo e(bm_t('home.catalog.title', 'Ürettiğimiz Parçalar')); ?></h2>
      <p>
        <?php if ($productTotal > count($featured['items'])): ?>
          <?php echo e(sprintf(bm_t('home.catalog.text_many',
            'Kataloğumuzdan öne çıkan parçalar — toplam %s ürün arasından seçildi.'),
            number_format($productTotal, 0, ',', '.'))); ?>
        <?php else: ?>
          <?php echo e(bm_t('home.catalog.text_few', 'Detaylı teknik bilgileriyle örnek üretim parçalarımız')); ?>
        <?php endif; ?>
      </p>
    </div>

    <div class="catalog-grid">
      <?php foreach ($featured['items'] as $item): ?>
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

    <div class="catalog-cta reveal">
      <a href="/urunler" class="btn btn-blue">
        <?php echo e(bm_t('home.catalog.all', 'Tüm Ürünleri Gör')); ?>
        <?php if ($productTotal): ?>(<?php echo number_format($productTotal, 0, ',', '.'); ?>)<?php endif; ?> →
      </a>
      <p><?php echo e(bm_t('home.catalog.hint', 'Aradığınız parçayı isim ya da kod ile arayabilirsiniz.')); ?></p>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Teklif formunun kendisi artık /teklif sayfasında; burada sadece kısa bir
     yönlendirme duruyor. Eskiden bütün form ana sayfadaydı ve telefonda
     neredeyse iki ekran yer kaplıyordu. -->
<section id="siparis" class="home-cta-section">
  <div class="home-cta reveal">
    <span class="tag"><?php echo e($order['tag'] ?? 'Özel Sipariş'); ?></span>
    <h2><?php echo e($order['title'] ?? 'Kişiye Özel Parça Siparişi'); ?></h2>
    <p><?php echo e($order['text'] ?? ''); ?></p>
    <div class="home-cta-actions">
      <a href="/teklif" class="btn"><?php echo e(bm_t('cta.button', 'Teklif Formuna Git →')); ?></a>
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

<?php require __DIR__ . '/inc/footer.php'; ?>
