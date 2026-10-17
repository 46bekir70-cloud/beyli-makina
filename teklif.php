<?php
/**
 * Teklif / iletişim sayfası — /teklif
 *
 * Form eskiden ana sayfanın içinde büyük bir bölüm olarak duruyordu;
 * telefonda ekranın çoğunu kaplıyordu. Artık kendi sayfasında.
 */
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/db.php';

// Oturum HTML çıktısından ÖNCE başlamalı (gönderim sonrası mesaj için).
bm_session_start();
$formResult = $_SESSION['bm_form_result'] ?? null;
$formOld    = $_SESSION['bm_form_old'] ?? [];
unset($_SESSION['bm_form_result'], $_SESSION['bm_form_old']);
$maxUploadMb = bm_max_upload_mb(); // sunucunun gerçek sınırını da hesaba katar

$bm_title = 'Teklif Al | Beyli Makina';
$bm_description = 'Parçanızın teknik çizimini ve detaylarını iletin, size özel teklif hazırlayalım. Teknik çizim dosyanızı forma ekleyebilirsiniz.';
$bm_active = 'teklif';
// Konum zinciri — arama motoruna yapısal veri olarak gider
$bm_breadcrumb = [
    ['ad' => 'Ana Sayfa', 'yol' => '/'],
    ['ad' => 'Teklif Al', 'yol' => ''],
];

require __DIR__ . '/inc/header.php';

$order = $content['order_section'] ?? [];
// Kurumsal form ayarları (panel → Teklif Sayfası → Kurumsal Form)
require_once __DIR__ . '/inc/countries.php';
$corp      = bm_corporate_config($content);
$corpF     = $corp['fields'];
$ulkeler   = bm_countries();
// Hatalı gönderimden sonra kullanıcının seçimleri korunur
$eskiTur   = ($formOld['musteri_turu'] ?? '') === 'kurumsal' ? 'kurumsal' : 'bireysel';
$telUlke   = strtoupper((string)($formOld['phone_country'] ?? $corp['default_country'])) ?: 'TR';
$firmaUlke = strtoupper((string)($formOld['company_country'] ?? $corp['default_country'])) ?: 'TR';

/** Alan açık mı? (kapali/istege/zorunlu) */
function bm_alan_acik($corpF, $ad) { return ($corpF[$ad] ?? 'kapali') !== 'kapali'; }
/** Alan zorunlu mu? */
function bm_alan_zorunlu($corpF, $ad) { return ($corpF[$ad] ?? '') === 'zorunlu'; }
/** Etiketin yanındaki yıldız ya da "(isteğe bağlı)" yazısı */
function bm_alan_isaret($corpF, $ad) {
    return bm_alan_zorunlu($corpF, $ad)
        ? ' <span class="zorunlu-yildiz">*</span>'
        : ' <span class="opt">' . e(bm_t('form.optional', '(isteğe bağlı)')) . '</span>';
}

// Ürün detay sayfasından "Bu parça için teklif iste" ile gelinmiş olabilir.
$prefillProduct = trim((string)($_GET['urun'] ?? ''));
// Ürün sayfasından gelen "Parça/Ürün Türü" — o üründe seçilmiş olan tür
$prefillType = trim((string)($_GET['tur'] ?? ''));
?>

<section class="page-hero<?php echo !empty($order['hero_image']) ? ' has-image' : ''; ?>"<?php echo bm_hero_style($order['hero_image'] ?? '', $order['hero_focus'] ?? ''); ?>>
  <div class="tag-pill"><span class="dot"></span> <?php echo e($order['tag'] ?? 'Özel Sipariş'); ?></div>
  <h1><?php echo e($order['title'] ?? 'Teklif Al'); ?></h1>
  <p><?php echo e($order['text'] ?? ''); ?></p>
</section>

<section class="soft" id="siparis">
  <div class="inner">
    <div class="quote-wrap">

      <aside class="quote-side reveal">
        <h2><?php echo e($order['side_title'] ?? 'İletişim'); ?></h2>
        <?php if (!empty($order['side_text'])): ?>
          <p class="quote-side-text"><?php echo e($order['side_text']); ?></p>
        <?php endif; ?>
        <div class="quote-contact">
          <?php foreach (bm_contact_buttons($content) as $btn): ?>
            <?php
              $href = bm_contact_button_href($btn);
              // Dış bağlantılar yeni sekmede açılsın; tel/mailto aynı sekmede kalmalı.
              $isExternal = strpos($href, 'http') === 0;
            ?>
            <?php if ($href !== ''): ?>
              <a href="<?php echo e($href); ?>"<?php echo $isExternal ? ' target="_blank" rel="noopener"' : ''; ?>>
                <?php echo bm_button_icon($btn); ?>
                <span>
                  <?php if (!empty($btn['label'])): ?><span class="lbl"><?php echo e($btn['label']); ?></span><?php endif; ?>
                  <?php echo e($btn['text'] ?? ''); ?>
                </span>
              </a>
            <?php else: ?>
              <div class="quote-address">
                <?php echo bm_button_icon($btn); ?>
                <span>
                  <?php if (!empty($btn['label'])): ?><span class="lbl"><?php echo e($btn['label']); ?></span><?php endif; ?>
                  <?php echo e($btn['text'] ?? ''); ?>
                </span>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>

        <?php $socialButtons = bm_quote_social_buttons($content); ?>
        <?php if ($socialButtons): ?>
          <div class="quote-social">
            <?php foreach ($socialButtons as $btn): ?>
              <?php
                $href = bm_contact_button_href($btn);
                $isExternal = strpos($href, 'http') === 0;
                $ad = trim((string)($btn['label'] ?? $btn['text'] ?? ''));
              ?>
              <?php if ($href !== ''): ?>
                <a href="<?php echo e($href); ?>"<?php echo $isExternal ? ' target="_blank" rel="noopener"' : ''; ?>
                   title="<?php echo e($ad); ?>">
                  <?php echo bm_button_icon($btn, 'soc-ic'); ?>
                  <span class="soc-name"><?php echo e($btn['text'] ?? ''); ?></span>
                </a>
              <?php else: ?>
                <span class="soc-plain">
                  <?php echo bm_button_icon($btn, 'soc-ic'); ?>
                  <span class="soc-name"><?php echo e($btn['text'] ?? ''); ?></span>
                </span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </aside>

      <div class="quote-form-box reveal">
        <h2><?php echo e($order['form_title'] ?? 'Teklif Formu'); ?></h2>

        <form id="orderForm" method="post" action="/teklif-gonder.php" enctype="multipart/form-data">
          <?php /* Formun gerçekten bu siteden gönderildiğini doğrulayan jeton. */ ?>
          <input type="hidden" name="form_token" value="<?php echo e(bm_form_token()); ?>">
          <?php if ($formResult): ?>
            <div class="form-flash <?php echo $formResult['status'] === 'ok' ? 'ok' : 'err'; ?>" role="status">
              <?php echo e($formResult['message']); ?>
            </div>
          <?php endif; ?>

          <?php if ($corp['enabled']): ?>
            <?php /* Bireysel / Kurumsal seçimi. JavaScript kapalıysa gizli alan
                     "bireysel" kalır ve firma bölümü de açık görünür. */ ?>
            <input type="hidden" id="musteriTuru" name="musteri_turu" value="<?php echo e($eskiTur); ?>">
            <div class="tur-secim" role="tablist">
              <button type="button" class="tur-sekme<?php echo $eskiTur === 'bireysel' ? ' aktif' : ''; ?>"
                      data-tur="bireysel" role="tab" aria-selected="<?php echo $eskiTur === 'bireysel' ? 'true' : 'false'; ?>">
                <?php echo e(bm_t('form.type_individual', 'Bireysel')); ?>
              </button>
              <button type="button" class="tur-sekme<?php echo $eskiTur === 'kurumsal' ? ' aktif' : ''; ?>"
                      data-tur="kurumsal" role="tab" aria-selected="<?php echo $eskiTur === 'kurumsal' ? 'true' : 'false'; ?>">
                <?php echo e(bm_t('form.type_corporate', 'Kurumsal')); ?>
              </button>
            </div>
            <p class="tur-ipucu"><?php echo e(bm_t('form.type_hint', 'Firma adına teklif istiyorsanız Kurumsal’ı seçin; fatura bilgilerinizi alalım.')); ?></p>
          <?php endif; ?>

          <label for="fullname"><?php echo e(bm_t('form.fullname', 'Ad Soyad')); ?></label>
          <input type="text" id="fullname" name="fullname" placeholder="<?php echo e(bm_t('form.fullname_ph', 'Adınız Soyadınız')); ?>"
                 value="<?php echo e($formOld['fullname'] ?? ''); ?>" required>

          <div class="form-row">
            <div>
              <label for="phone"><?php echo e(bm_t('form.phone', 'Telefon')); ?></label>
              <?php /* Telefon: solda ülke kodu, sağda numara — tek bir kutu gibi görünür.
                       Alttaki <select> JavaScript çalışırken gizlenip yerine aramalı
                       ülke seçici konur; çalışmazsa normal açılır liste olarak kalır. */ ?>
              <div class="tel-grup" data-tel-grup
                   data-ara="<?php echo e(bm_t('form.phone_search', 'Ülke ara…')); ?>"
                   data-bos="<?php echo e(bm_t('form.phone_none', 'Sonuç bulunamadı')); ?>">
                <select id="phoneCountry" name="phone_country" class="tel-ulke-select"
                        aria-label="<?php echo e(bm_t('form.phone_country', 'Ülke kodu')); ?>">
                  <?php foreach ($ulkeler as $u): ?>
                    <option value="<?php echo e($u['iso']); ?>"
                            data-dial="<?php echo e($u['dial']); ?>"
                            data-name="<?php echo e($u['name']); ?>"
                      <?php echo $u['iso'] === $telUlke ? ' selected' : ''; ?>>
                      <?php echo e($u['name']) . ' (+' . e($u['dial']) . ')'; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="tel" id="phone" name="phone" inputmode="tel" autocomplete="tel"
                       placeholder="<?php echo e(bm_t('form.phone_ph', '5XX XXX XX XX')); ?>"
                       value="<?php echo e($formOld['phone'] ?? ''); ?>">
              </div>
              <span class="alan-uyari" id="phoneNote" hidden><?php echo e(bm_t('form.err_phone', 'Telefon numarası hatalı görünüyor, kontrol eder misiniz?')); ?></span>
            </div>
            <div>
              <label for="email"><?php echo e(bm_t('form.email', 'E-Posta')); ?></label>
              <input type="email" id="email" name="email" placeholder="<?php echo e(bm_t('form.email_ph', 'ornek@mail.com')); ?>"
                     value="<?php echo e($formOld['email'] ?? ''); ?>">
            </div>
          </div>

          <?php if ($corp['enabled']): ?>
            <?php /* Firma bilgileri — yalnızca Kurumsal seçiliyken görünür.
                     Hangi alanın açık/zorunlu olduğu panelden ayarlanır. */ ?>
            <div class="kurumsal-kutu" id="kurumsalAlanlar"<?php echo $eskiTur === 'kurumsal' ? '' : ' hidden'; ?>>
              <h3><?php echo e(bm_t('form.corporate_title', 'Firma Bilgileri')); ?></h3>

              <?php if (bm_alan_acik($corpF, 'company')): ?>
                <div>
                  <label for="company"><?php echo e(bm_t('form.company', 'Firma Ünvanı')); ?><?php echo bm_alan_isaret($corpF, 'company'); ?></label>
                  <input type="text" id="company" name="company"
                         placeholder="<?php echo e(bm_t('form.company_ph', 'Örn. Örnek Makina San. ve Tic. Ltd. Şti.')); ?>"
                         value="<?php echo e($formOld['company'] ?? ''); ?>"
                         <?php echo bm_alan_zorunlu($corpF, 'company') ? 'data-zorunlu="1"' : ''; ?>>
                </div>
              <?php endif; ?>

              <?php if (bm_alan_acik($corpF, 'taxoffice') || bm_alan_acik($corpF, 'taxno')): ?>
                <div class="form-row">
                  <?php if (bm_alan_acik($corpF, 'taxoffice')): ?>
                    <div>
                      <label for="taxoffice"><?php echo e(bm_t('form.taxoffice', 'Vergi Dairesi')); ?><?php echo bm_alan_isaret($corpF, 'taxoffice'); ?></label>
                      <input type="text" id="taxoffice" name="taxoffice"
                             placeholder="<?php echo e(bm_t('form.taxoffice_ph', 'Örn. Kahramanmaraş')); ?>"
                             value="<?php echo e($formOld['taxoffice'] ?? ''); ?>"
                             <?php echo bm_alan_zorunlu($corpF, 'taxoffice') ? 'data-zorunlu="1"' : ''; ?>>
                    </div>
                  <?php endif; ?>
                  <?php if (bm_alan_acik($corpF, 'taxno')): ?>
                    <div>
                      <label for="taxno" id="taxnoLabel"
                             data-tr="<?php echo e(bm_t('form.taxno', 'Vergi No / TCKN')); ?>"
                             data-yd="<?php echo e(bm_t('form.taxno_foreign', 'Vergi / VAT Numarası')); ?>">
                        <?php echo e($firmaUlke === 'TR' ? bm_t('form.taxno', 'Vergi No / TCKN') : bm_t('form.taxno_foreign', 'Vergi / VAT Numarası')); ?>
                        <?php echo bm_alan_isaret($corpF, 'taxno'); ?>
                      </label>
                      <input type="text" id="taxno" name="taxno" inputmode="numeric" autocomplete="off"
                             placeholder="<?php echo e(bm_t('form.taxno_ph', '10 haneli vergi no veya 11 haneli TC kimlik no')); ?>"
                             value="<?php echo e($formOld['taxno'] ?? ''); ?>"
                             <?php echo bm_alan_zorunlu($corpF, 'taxno') ? 'data-zorunlu="1"' : ''; ?>>
                      <span class="alan-not"><?php echo e(bm_t('form.taxno_hint', 'Şahıs firmaları TC kimlik numarası yazabilir.')); ?></span>
                      <?php /* Numara tutmazsa uyarır ama gönderimi engellemez */ ?>
                      <span class="alan-uyari" id="taxnoUyari" hidden><?php echo e(bm_t('form.taxno_warn', 'Bu numara hatalı görünüyor, kontrol eder misiniz?')); ?></span>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <?php if (bm_alan_acik($corpF, 'role') || bm_alan_acik($corpF, 'web')): ?>
                <div class="form-row">
                  <?php if (bm_alan_acik($corpF, 'role')): ?>
                    <div>
                      <label for="role"><?php echo e(bm_t('form.role', 'Görev / Departman')); ?><?php echo bm_alan_isaret($corpF, 'role'); ?></label>
                      <input type="text" id="role" name="role"
                             placeholder="<?php echo e(bm_t('form.role_ph', 'Örn. Satınalma')); ?>"
                             value="<?php echo e($formOld['role'] ?? ''); ?>"
                             <?php echo bm_alan_zorunlu($corpF, 'role') ? 'data-zorunlu="1"' : ''; ?>>
                    </div>
                  <?php endif; ?>
                  <?php if (bm_alan_acik($corpF, 'web')): ?>
                    <div>
                      <label for="companyweb"><?php echo e(bm_t('form.company_web', 'Web Sitesi')); ?><?php echo bm_alan_isaret($corpF, 'web'); ?></label>
                      <input type="text" id="companyweb" name="company_web" placeholder="ornekfirma.com"
                             value="<?php echo e($formOld['company_web'] ?? ''); ?>"
                             <?php echo bm_alan_zorunlu($corpF, 'web') ? 'data-zorunlu="1"' : ''; ?>>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <?php if (bm_alan_acik($corpF, 'country')): ?>
                <div>
                  <label for="companyCountry"><?php echo e(bm_t('form.country', 'Ülke')); ?><?php echo bm_alan_isaret($corpF, 'country'); ?></label>
                  <select id="companyCountry" name="company_country"
                          <?php echo bm_alan_zorunlu($corpF, 'country') ? 'data-zorunlu="1"' : ''; ?>>
                    <?php foreach ($ulkeler as $u): ?>
                      <option value="<?php echo e($u['iso']); ?>"<?php echo $u['iso'] === $firmaUlke ? ' selected' : ''; ?>>
                        <?php echo e($u['name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>

              <?php if (bm_alan_acik($corpF, 'city') || bm_alan_acik($corpF, 'district')): ?>
                <div class="form-row">
                  <?php if (bm_alan_acik($corpF, 'city')): ?>
                    <div>
                      <label for="city"><?php echo e(bm_t('form.city', 'İl / Şehir')); ?><?php echo bm_alan_isaret($corpF, 'city'); ?></label>
                      <input type="text" id="city" name="city" value="<?php echo e($formOld['city'] ?? ''); ?>"
                             <?php echo bm_alan_zorunlu($corpF, 'city') ? 'data-zorunlu="1"' : ''; ?>>
                    </div>
                  <?php endif; ?>
                  <?php if (bm_alan_acik($corpF, 'district')): ?>
                    <div>
                      <label for="district"><?php echo e(bm_t('form.district', 'İlçe')); ?><?php echo bm_alan_isaret($corpF, 'district'); ?></label>
                      <input type="text" id="district" name="district" value="<?php echo e($formOld['district'] ?? ''); ?>"
                             <?php echo bm_alan_zorunlu($corpF, 'district') ? 'data-zorunlu="1"' : ''; ?>>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <?php if (bm_alan_acik($corpF, 'address')): ?>
                <div>
                  <label for="address"><?php echo e(bm_t('form.address', 'Açık Adres (fatura adresi)')); ?><?php echo bm_alan_isaret($corpF, 'address'); ?></label>
                  <textarea id="address" name="address" rows="2" style="min-height:74px;"
                            placeholder="<?php echo e(bm_t('form.address_ph', 'Mahalle, cadde, kapı no, posta kodu')); ?>"
                            <?php echo bm_alan_zorunlu($corpF, 'address') ? 'data-zorunlu="1"' : ''; ?>><?php echo e($formOld['address'] ?? ''); ?></textarea>
                </div>
              <?php endif; ?>

              <?php if (bm_alan_acik($corpF, 'qty') || bm_alan_acik($corpF, 'deadline') || bm_alan_acik($corpF, 'pono')): ?>
                <div class="form-row">
                  <?php if (bm_alan_acik($corpF, 'qty')): ?>
                    <div>
                      <label for="qty"><?php echo e(bm_t('form.qty', 'Tahmini Adet')); ?><?php echo bm_alan_isaret($corpF, 'qty'); ?></label>
                      <input type="text" id="qty" name="qty" inputmode="numeric"
                             placeholder="<?php echo e(bm_t('form.qty_ph', 'Örn. 250')); ?>"
                             value="<?php echo e($formOld['qty'] ?? ''); ?>"
                             <?php echo bm_alan_zorunlu($corpF, 'qty') ? 'data-zorunlu="1"' : ''; ?>>
                    </div>
                  <?php endif; ?>
                  <?php if (bm_alan_acik($corpF, 'deadline')): ?>
                    <div>
                      <label for="deadline"><?php echo e(bm_t('form.deadline', 'İstenen Termin')); ?><?php echo bm_alan_isaret($corpF, 'deadline'); ?></label>
                      <?php /* Tarih kutusu: tarayıcının kendi ok/takvim simgesi gizlenip
                               yerine kendi takvim düğmemiz konuyor (diğer alanlarla aynı görünüm). */ ?>
                      <div class="tarih-alan">
                        <input type="date" id="deadline" name="deadline"
                               value="<?php echo e($formOld['deadline'] ?? ''); ?>"
                               min="<?php echo e(date('Y-m-d')); ?>"
                               <?php echo bm_alan_zorunlu($corpF, 'deadline') ? 'data-zorunlu="1"' : ''; ?>>
                        <button type="button" class="tarih-dugme" tabindex="-1"
                                aria-label="<?php echo e(bm_t('form.deadline_pick', 'Takvimi aç')); ?>">
                          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                            <rect x="3.2" y="4.8" width="17.6" height="16" rx="2.6" fill="none" stroke="currentColor" stroke-width="1.7"/>
                            <path d="M3.2 9.6h17.6M8 3.2v3.4M16 3.2v3.4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            <rect x="7" y="12.4" width="3" height="3" rx=".7" fill="currentColor"/>
                          </svg>
                        </button>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
                <?php if (bm_alan_acik($corpF, 'pono')): ?>
                  <div>
                    <label for="pono"><?php echo e(bm_t('form.pono', 'Satınalma Sipariş No')); ?><?php echo bm_alan_isaret($corpF, 'pono'); ?></label>
                    <input type="text" id="pono" name="pono" value="<?php echo e($formOld['pono'] ?? ''); ?>"
                           <?php echo bm_alan_zorunlu($corpF, 'pono') ? 'data-zorunlu="1"' : ''; ?>>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <label for="parttype"><?php echo e(bm_t('form.parttype', 'Parça / Hizmet Türü')); ?></label>
          <select id="parttype" name="parttype">
            <?php
              // Seçenekler panelden düzenlenebiliyor (Teklif Sayfası bölümü).
              $partTypes = array_filter((array)($order['part_types'] ?? []));
              if (!$partTypes) $partTypes = ['CNC Torna Parçası', 'CNC Freze Parçası', 'Diğer / Özel Tasarım'];
              foreach ($partTypes as $pt):
            ?>
              <?php
                // Sıra: formda hata olduysa kullanıcının seçtiği, yoksa ürünün
                // türü, o da yoksa listenin ilki.
                $seciliTur = ($formOld['parttype'] ?? '') !== '' ? $formOld['parttype'] : $prefillType;
              ?>
              <option<?php echo ($seciliTur === $pt) ? ' selected' : ''; ?>><?php echo e($pt); ?></option>
            <?php endforeach; ?>
          </select>

          <label for="details"><?php echo e(bm_t('form.details', 'Sipariş Detayları')); ?></label>
          <textarea id="details" name="details"
                    placeholder="<?php echo e(bm_t('form.details_ph', 'Malzeme, adet, ölçü, tolerans ve diğer teknik detayları buraya yazınız...')); ?>"><?php echo e($formOld['details'] ?? ''); ?></textarea>

          <label for="attachment"><?php echo e(bm_t('form.file', 'Teknik Çizim / Dosya')); ?> <span class="opt"><?php echo e(bm_t('form.file_optional', '(isteğe bağlı)')); ?></span></label>
          <div class="file-field">
            <input type="file" id="attachment" name="attachment"
                   accept=".pdf,.jpg,.jpeg,.png,.webp,.dwg,.dxf,.step,.stp,.iges,.igs,.zip,.rar,.7z,.xls,.xlsx,.doc,.docx">
            <span class="file-hint"><?php echo e(sprintf(bm_t('form.file_hint', 'PDF, DWG, DXF, STEP, resim, Excel veya sıkıştırılmış dosya — en fazla %s.'), (int)$maxUploadMb . ' MB')); ?></span>
          </div>

          <!-- Bot tuzağı: gerçek kullanıcılara görünmez, doldurulursa istek yok sayılır -->
          <div class="hp-field" aria-hidden="true">
            <label for="website"><?php echo e(bm_t('form.website', 'Web sitesi')); ?></label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
          </div>

          <!-- Ürün detay sayfasından gelindiyse ilgili ürün buraya yazılır -->
          <input type="hidden" id="productField" name="product" value="<?php echo e($prefillProduct); ?>">

          <?php if ($corp['kvkk_url'] !== ''): ?>
            <?php /* Aydınlatma metni onayı (zorunlu). Metnin adresi panelden girilir;
                     boş bırakılırsa bu kutu hiç görünmez. */ ?>
            <div class="onay-satir">
              <input type="checkbox" id="kvkk" name="kvkk" value="1" required
                     <?php echo !empty($formOld['kvkk']) ? 'checked' : ''; ?>>
              <label for="kvkk"><?php
                echo sprintf(
                  e(bm_t('form.kvkk_text', '%s’ni okudum.')),
                  '<a href="' . e(bm_safe_url($corp['kvkk_url'])) . '" target="_blank" rel="noopener">'
                    . e(bm_t('form.kvkk_link', 'Aydınlatma Metni')) . '</a>'
                );
              ?> <span class="zorunlu-yildiz">*</span></label>
            </div>
          <?php endif; ?>

          <?php if ($corp['consent_box']): ?>
            <div class="onay-satir">
              <input type="checkbox" id="izin" name="izin" value="1"
                     <?php echo !empty($formOld['izin']) ? 'checked' : ''; ?>>
              <label for="izin"><?php echo e(bm_t('form.consent_text', 'Kampanya ve duyurulardan haberdar olmak istiyorum.')); ?></label>
            </div>
          <?php endif; ?>

          <button type="submit" class="btn"><?php echo e(bm_t('form.submit', 'Teklif Talebini Gönder ✉️')); ?></button>
          <p class="zorunlu-not"><?php echo e(bm_t('form.required_hint', '* işaretli alanlar zorunludur.')); ?></p>
        </form>
      </div>

    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
