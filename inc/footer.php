<?php
/**
 * Ortak footer + kapanış şablonu. header.php ile birlikte kullanılır,
 * $content zaten tanımlı olmalı.
 *
 * Buradaki her şey panelden yönetilir (Menü & Footer bölümü):
 * kolon başlıkları, hızlı linkler, sosyal butonlar.
 */
$site     = $content['site'] ?? [];
$footer   = $content['footer'] ?? [];
$headings = $footer['headings'] ?? [];
$navPages = bm_nav_pages($content);

// Hızlı linkler panelde boş bırakılırsa menüdeki sayfalardan üretilir.
$quickLinks = bm_button_list($content, 'footer.quick_links');
if (!$quickLinks) {
    $quickLinks = [['text' => bm_t('nav.home', 'Ana Sayfa'), 'type' => 'link', 'value' => '/']];
    foreach ($navPages as $p) {
        $quickLinks[] = ['text' => $p['title'], 'type' => 'link', 'value' => '/' . rawurlencode($p['slug'])];
    }
    $quickLinks[] = ['text' => bm_t('nav.contact', 'İletişim'), 'type' => 'link', 'value' => '/teklif'];
}

$socialButtons = bm_button_list($content, 'footer.social_buttons');

// Yasal bağlantılar kendi kolonunda durur. Liste boşsa kolon hiç basılmaz,
// böylece footer eski düzenine (4 kolon) kendiliğinden geri döner.
$legalLinks = bm_button_list($content, 'footer.legal_links');
$footerServices = array_slice(bm_services($content, true), 0, 5);
?>
<footer>
  <div class="footer-inner<?php echo $legalLinks ? ' has-legal' : ''; ?>">
    <div class="footer-col">
      <a href="/" class="logo footer-logo">
        <img src="/images/logo.png" alt="<?php echo e(bm_t('site.name', 'Beyli Makina Sanayi ve Ticaret Ltd. Şti.')); ?>" class="logo-img">
      </a>
      <?php if (!empty($site['footer_about'])): ?>
        <p><?php echo e($site['footer_about']); ?></p>
      <?php endif; ?>

      <?php if ($socialButtons): ?>
        <div class="footer-social">
          <?php foreach ($socialButtons as $btn): ?>
            <?php $href = bm_contact_button_href($btn); ?>
            <?php if ($href !== ''): ?>
              <a href="<?php echo e($href); ?>"
                 <?php echo strpos($href, 'http') === 0 ? 'target="_blank" rel="noopener"' : ''; ?>
                 aria-label="<?php echo e($btn['label'] ?? $btn['text'] ?? ''); ?>"
                 title="<?php echo e($btn['label'] ?? $btn['text'] ?? ''); ?>">
                <?php
                  // PNG yüklenmişse logo, yoksa emoji/harf
                  echo bm_button_icon($btn, 'soc-ic') ?: e($btn['text'] ?? '');
                ?>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="footer-col">
      <h4><?php echo e($headings['links'] ?: bm_t('footer.links_title', 'Hızlı Linkler')); ?></h4>
      <ul>
        <?php foreach ($quickLinks as $ql): ?>
          <?php $href = bm_contact_button_href($ql); ?>
          <li><a href="<?php echo e($href !== '' ? $href : '#'); ?>"
                 <?php echo strpos($href, 'http') === 0 ? 'target="_blank" rel="noopener"' : ''; ?>>
            <?php echo e($ql['text'] ?? ''); ?>
          </a></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <?php if ($footerServices): ?>
    <div class="footer-col">
      <h4><?php echo e($headings['services'] ?: bm_t('footer.services_title', 'Hizmetlerimiz')); ?></h4>
      <ul>
        <?php foreach ($footerServices as $s): ?>
          <li><a href="/hizmet/<?php echo e($s['slug']); ?>"><?php echo e($s['title']); ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if ($legalLinks): ?>
    <div class="footer-col">
      <h4><?php echo e(($headings['legal'] ?? '') ?: bm_t('footer.legal_title', 'Yasal Bilgilendirme')); ?></h4>
      <ul>
        <?php foreach ($legalLinks as $lg): ?>
          <?php $href = bm_contact_button_href($lg); ?>
          <li><a href="<?php echo e($href !== '' ? $href : '#'); ?>"
                 <?php echo strpos($href, 'http') === 0 ? 'target="_blank" rel="noopener"' : ''; ?>>
            <?php echo e($lg['text'] ?? ''); ?>
          </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="footer-col">
      <h4><?php echo e($headings['contact'] ?: bm_t('footer.contact_title', 'İletişim')); ?></h4>
      <?php if (!empty($site['address'])): ?>
        <p class="contact-line"><?php echo bm_site_icon($site, 'address_icon', '📍'); ?><?php echo e($site['address']); ?></p>
      <?php endif; ?>
      <?php if (!empty($site['phone_display'])): ?>
        <p class="contact-line"><?php echo bm_site_icon($site, 'phone_icon', '📞'); ?><?php echo e($site['phone_display']); ?></p>
      <?php endif; ?>
      <?php if (!empty($site['email'])): ?>
        <p class="contact-line"><?php echo bm_site_icon($site, 'email_icon', '✉️'); ?><?php echo e($site['email']); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($site['footer_note'])): ?>
    <div class="footer-bottom"><?php echo e($site['footer_note']); ?></div>
  <?php endif; ?>
</footer>

<script src="/js/script.js?v=<?php echo e(bm_asset_version('js/script.js')); ?>"></script>
<?php /* Teklif formunun yardımcı dosyası yalnızca o sayfada yüklenir. */ ?>
<?php if (($bm_active ?? '') === 'teklif'): ?>
  <script src="/js/teklif-form.js?v=<?php echo e(bm_asset_version('js/teklif-form.js')); ?>"></script>
<?php endif; ?>
<?php
  // Yüzen (Floating) İletişim Butonları
  $fb = $content['floating_buttons'] ?? null;
  if ($fb === null && !empty($site['whatsapp_link'])) {
      $fb = [
          'enabled'          => 1,
          'position'         => 'right',
          'style'            => 'circle',
          'pulse_animation'  => 1,
          'mobile_icon_only' => 1,
          'whatsapp_enabled' => 1,
          'whatsapp_number'  => preg_replace('/[^0-9]/', '', $site['whatsapp_link']),
          'whatsapp_text'    => $site['whatsapp_text'] ?? 'WhatsApp',
          'whatsapp_message' => '',
          'phone_enabled'    => 0,
          'custom_buttons'   => [],
      ];
  }
  $bmFbStyle = ($fb['style'] ?? 'circle') === 'pill' ? 'bm-float-pill' : 'bm-float-circle';
?>
<?php if (!empty($fb['enabled'])): ?>
  <div class="bm-float-wrap <?php echo ($fb['position'] ?? 'right') === 'left' ? 'bm-float-left' : 'bm-float-right'; ?> <?php echo $bmFbStyle; ?> <?php echo !empty($fb['mobile_icon_only']) ? 'bm-float-compact-mobile' : ''; ?>" role="region" aria-label="Hızlı İletişim">
    
    <?php if (!empty($fb['phone_enabled']) && !empty($fb['phone_number'])): 
      $bmPhoneClean = preg_replace('/[^0-9+]/', '', $fb['phone_number']);
    ?>
      <a href="tel:<?php echo e($bmPhoneClean); ?>" class="bm-float-btn bm-float-phone" aria-label="Bizi Arayın" title="<?php echo e($fb['phone_text'] ?? 'Hemen Arayın'); ?>">
        <span class="bm-float-btn-icon">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false">
            <path d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
          </svg>
        </span>
        <span class="bm-float-btn-text"><?php echo e($fb['phone_text'] ?? 'Hemen Arayın'); ?></span>
      </a>
    <?php endif; ?>

    <?php if (!empty($fb['custom_buttons'])): ?>
      <?php foreach ($fb['custom_buttons'] as $cb): 
        $cbLink = trim((string)($cb['link'] ?? ''));
        $cbLabel = trim((string)($cb['label'] ?? ''));
        $cbIcon = trim((string)($cb['icon'] ?? ''));
        $cbColor = trim((string)($cb['color'] ?? '#0d3b66'));
        if ($cbLink === '' || $cbLabel === '') continue;
      ?>
        <a href="<?php echo e(bm_safe_url($cbLink)); ?>" class="bm-float-btn bm-float-custom" style="background:<?php echo e($cbColor); ?>;" title="<?php echo e($cbLabel); ?>">
          <span class="bm-float-btn-icon"><?php echo $cbIcon !== '' ? e($cbIcon) : '🔗'; ?></span>
          <span class="bm-float-btn-text"><?php echo e($cbLabel); ?></span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($fb['whatsapp_enabled'])): 
      $bmWaNum = preg_replace('/[^0-9]/', '', $fb['whatsapp_number'] ?? '');
      if ($bmWaNum === '') $bmWaNum = preg_replace('/[^0-9]/', '', $site['whatsapp_link'] ?? '');
      $bmWaMsg = trim((string)($fb['whatsapp_message'] ?? ''));
      $bmWaUrl = 'https://wa.me/' . $bmWaNum . ($bmWaMsg !== '' ? '?text=' . rawurlencode($bmWaMsg) : '');
    ?>
      <a href="<?php echo e(bm_safe_url($bmWaUrl)); ?>" target="_blank" rel="noopener" class="bm-float-btn bm-float-whatsapp <?php echo !empty($fb['pulse_animation']) ? 'bm-float-pulse' : ''; ?>" aria-label="WhatsApp İle Hızlı İletişim" title="<?php echo e($fb['whatsapp_text'] ?? 'WhatsApp'); ?>">
        <span class="bm-float-btn-icon">
          <svg viewBox="0 0 32 32" width="24" height="24" fill="#fff" aria-hidden="true" focusable="false">
            <path d="M16 .5C7.4.5.5 7.4.5 16c0 2.8.7 5.4 2.1 7.8L.6 31.4l7.8-2c2.3 1.3 4.9 2 7.6 2 8.6 0 15.5-6.9 15.5-15.5S24.6.5 16 .5zm0 28.4c-2.4 0-4.7-.6-6.7-1.8l-.5-.3-4.6 1.2 1.2-4.5-.3-.5c-1.3-2.1-2-4.5-2-7 0-7.2 5.8-13 13-13s13 5.8 13 13-5.8 12.9-13.1 12.9zm7.1-9.7c-.4-.2-2.3-1.1-2.6-1.3-.4-.1-.6-.2-.9.2s-1 1.3-1.2 1.5-.5.3-.9.1c-.4-.2-1.7-.6-3.2-2-1.2-1.1-2-2.4-2.2-2.8s0-.6.2-.8c.2-.2.4-.4.6-.6.2-.2.3-.4.4-.6.1-.2.1-.4 0-.6s-.9-2.1-1.2-2.9c-.3-.8-.7-.7-.9-.7h-.8c-.3 0-.7.1-1.1.5s-1.5 1.5-1.5 3.6 1.5 4.2 1.7 4.5 3 4.6 7.3 6.4c1 .4 1.8.7 2.5.9 1.1.3 2 .3 2.8.2.9-.1 2.3-1 2.7-1.9.3-.9.3-1.8.2-1.9s-.4-.2-.8-.4z"/>
          </svg>
        </span>
        <span class="bm-float-btn-text"><?php echo e($fb['whatsapp_text'] ?? ($site['whatsapp_text'] ?? 'WhatsApp')); ?></span>
      </a>
    <?php endif; ?>

  </div>
<?php endif; ?>
</body>
</html>
