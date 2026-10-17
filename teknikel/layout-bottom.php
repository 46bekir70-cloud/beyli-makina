<?php
// Doğrudan açılırsa sessizce 404 — bu dosya tek başına çalışmaz
if (!function_exists('bm_admin_require_login')) { http_response_code(404); exit; }
/** Admin panel ortak alt şablon. layout-top.php ile birlikte kullanılır. */
$bm_admin_preview = $bm_admin_preview ?? '';
$bm_admin_save_form = $bm_admin_save_form ?? '';
?>
<?php if ($bm_admin_preview): ?>
    </div><!-- /editor-col -->

    <aside class="preview-col">
      <div class="preview-head">
        <span class="preview-title">Önizleme</span>
        <div class="preview-tools">
          <?php /* Üç gerçek ekran genişliği. Önizleme paneli dar olduğu için
                   masaüstü ve tablet, gerçek genişlikte açılıp panele sığacak
                   kadar küçültülüyor — eskiden "Masaüstü" düğmesi panelin kendi
                   genişliğini kullanıyordu ve aslında tablet görünümü veriyordu. */ ?>
          <?php /* Simgeler emoji değil çizim (SVG): emoji seti işletim sistemine
                   göre değiştiği için Windows'ta yanlış/eksik çiziliyordu ve
                   tablet simgesi hiç yoktu. Çizim her yerde aynı görünür. */ ?>
          <button type="button" class="prev-size active" data-w="1280" title="Masaüstü (1280px)" aria-label="Masaüstü görünümü">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <rect x="2.5" y="4" width="19" height="12.5" rx="1.8"/>
              <path d="M9 20h6M12 16.5V20"/>
            </svg>
          </button>
          <button type="button" class="prev-size" data-w="820" title="Tablet (820px)" aria-label="Tablet görünümü">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <rect x="4.5" y="2.5" width="15" height="19" rx="2"/>
              <path d="M10.6 18.8h2.8"/>
            </svg>
          </button>
          <button type="button" class="prev-size" data-w="390" title="Telefon (390px)" aria-label="Telefon görünümü">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <rect x="7" y="2.5" width="10" height="19" rx="2.2"/>
              <path d="M11 18.9h2"/>
            </svg>
          </button>
          <button type="button" class="prev-refresh" title="Yenile" aria-label="Önizlemeyi yenile">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M20 12a8 8 0 1 1-2.4-5.7"/>
              <path d="M20 3.6V8h-4.4"/>
            </svg>
          </button>
        </div>
      </div>
      <div class="preview-frame-wrap" id="previewWrap">
        <iframe name="bmPreview" id="bmPreviewFrame" title="Önizleme"></iframe>
      </div>
      <div class="preview-note">
        Bu önizleme <strong>kaydedilmemiş</strong> hâlini gösterir. Alandan çıkınca kendiliğinden yenilenir.
      </div>
    </aside>
  </div><!-- /editor-layout -->

  <script src="/teknikel/assets/preview.js?v=<?php echo e(bm_asset_version('teknikel/assets/preview.js')); ?>"></script>
  <script>
    bmInitPreview({
      formId: <?php echo json_encode($bm_admin_save_form ?: 'previewForm'); ?>,
      url: <?php echo json_encode($bm_admin_preview); ?>
    });
  </script>
<?php endif; ?>
  <script src="/teknikel/assets/focal.js?v=<?php echo e(bm_asset_version('teknikel/assets/focal.js')); ?>"></script>
  <script src="/teknikel/assets/iconup.js?v=<?php echo e(bm_asset_version('teknikel/assets/iconup.js')); ?>"></script>
  <?php /* "Siteden Seç" penceresi — her görsel alanının yanındaki düğme bunu açar */ ?>
  <script src="/teknikel/assets/secici.js?v=<?php echo e(bm_asset_version('teknikel/assets/secici.js')); ?>"></script>
  <?php /* Sıralanabilir bütün listelere ↑ ↓ düğmeleri ekler. Sayfaya özel bir
           ayar gerekmiyor: satırları kendisi buluyor (bkz. sirala.js). */ ?>
  <script src="/teknikel/assets/sirala.js?v=<?php echo e(bm_asset_version('teknikel/assets/sirala.js')); ?>"></script>
  </main>
</div>
</body>
</html>
