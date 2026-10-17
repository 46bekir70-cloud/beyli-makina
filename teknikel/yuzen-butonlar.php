<?php
/**
 * Yüzen (Floating) İletişim Butonları Yönetimi.
 *
 * Sitenin sağ veya sol alt köşesinde duran WhatsApp, doğrudan arama ve
 * hızlı bağlantı butonlarını yönetir.
 */
require_once __DIR__ . '/config.php';

bm_admin_require_login();

$content = bm_load_content();
$bm_admin_active = 'yuzenbuton';
$bm_admin_title = 'Yüzen Butonlar';
$bm_admin_save_form = 'yuzenForm';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $fb = [
            'enabled'          => isset($_POST['fb_enabled']) ? 1 : 0,
            'position'         => ($_POST['fb_position'] ?? 'right') === 'left' ? 'left' : 'right',
            'style'            => in_array($_POST['fb_style'] ?? 'circle', ['circle', 'pill']) ? $_POST['fb_style'] : 'circle',
            'pulse_animation'  => isset($_POST['fb_pulse']) ? 1 : 0,
            'mobile_icon_only' => isset($_POST['fb_mobile_icon_only']) ? 1 : 1,

            // WhatsApp
            'whatsapp_enabled' => isset($_POST['fb_wa_enabled']) ? 1 : 0,
            'whatsapp_number'  => trim((string)($_POST['fb_wa_number'] ?? '')),
            'whatsapp_text'    => trim((string)($_POST['fb_wa_text'] ?? 'WhatsApp')),
            'whatsapp_message' => trim((string)($_POST['fb_wa_message'] ?? '')),

            // Telefon
            'phone_enabled'    => isset($_POST['fb_phone_enabled']) ? 1 : 0,
            'phone_number'     => trim((string)($_POST['fb_phone_number'] ?? '')),
            'phone_text'       => trim((string)($_POST['fb_phone_text'] ?? 'Hemen Arayın')),

            // Özel butonlar
            'custom_buttons'   => [],
        ];

        // WhatsApp numarasını temizle (boşluksuz format)
        $cleanWa = preg_replace('/[^0-9]/', '', $fb['whatsapp_number']);
        if ($cleanWa !== '') {
            $fb['whatsapp_number'] = $cleanWa;
        }

        // Özel ek butonları topla
        $cLabels = (array)($_POST['cb_label'] ?? []);
        $cLinks  = (array)($_POST['cb_link'] ?? []);
        $cIcons  = (array)($_POST['cb_icon'] ?? []);
        $cColors = (array)($_POST['cb_color'] ?? []);

        foreach ($cLabels as $i => $lbl) {
            $lbl = trim((string)$lbl);
            $lnk = trim((string)($cLinks[$i] ?? ''));
            if ($lbl === '' && $lnk === '') continue;
            $fb['custom_buttons'][] = [
                'label' => $lbl,
                'link'  => $lnk,
                'icon'  => trim((string)($cIcons[$i] ?? '')),
                'color' => trim((string)($cColors[$i] ?? '#0d3b66')),
            ];
        }

        $content['floating_buttons'] = $fb;

        // Genel ayarlardaki WhatsApp ile de senkronize tut
        if ($fb['whatsapp_number'] !== '') {
            $content['site']['whatsapp_link'] = 'https://wa.me/' . $fb['whatsapp_number'];
        }
        if ($fb['whatsapp_text'] !== '') {
            $content['site']['whatsapp_text'] = $fb['whatsapp_text'];
        }

        bm_save_content($content);
        $message = 'ok|Yüzen buton ayarları başarıyla kaydedildi.';
        $content = bm_load_content();
    }
}

// Varsayılan değerler
$fb = $content['floating_buttons'] ?? [
    'enabled'          => 1,
    'position'         => 'right',
    'style'            => 'circle',
    'pulse_animation'  => 1,
    'mobile_icon_only' => 1,
    'whatsapp_enabled' => 1,
    'whatsapp_number'  => preg_replace('/[^0-9]/', '', (string)($content['site']['whatsapp_link'] ?? '905335598701')),
    'whatsapp_text'    => $content['site']['whatsapp_text'] ?? 'WhatsApp',
    'whatsapp_message' => 'Merhaba, Beyli Makina ürünleri ve imalat süreçleri hakkında bilgi ve teklif almak istiyorum.',
    'phone_enabled'    => 0,
    'phone_number'     => $content['site']['phone_link'] ?? '+905335598701',
    'phone_text'       => 'Hemen Arayın',
    'custom_buttons'   => [],
];

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Yüzen Butonlar &amp; Hızlı İletişim</h1>
    <div class="sub">Sayfanın köşesinde duran simetrik WhatsApp, telefon ve hızlı iletişim butonları</div>
  </div>
  <a href="/teknikel/index.php" class="logout-link">← Panele Dön</a>
</div>

<?php if ($message): [$type, $text] = explode('|', $message, 2); ?>
  <div class="flash <?php echo $type === 'ok' ? 'ok' : 'err'; ?>"><?php echo e($text); ?></div>
<?php endif; ?>

<form method="post" id="yuzenForm">
  <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">

  <!-- GENEL GÖRÜNÜM AYARLARI -->
  <div class="card">
    <h2>⚙️ Genel Görünüm ve Konum (Mac &amp; Mobil Uyumlu)</h2>
    <p class="card-hint">
      Sitede gezinirken ziyaretçinin gözünün önünde duran butonların simetrisi, konumu ve cihaz davranışları.
    </p>

    <div class="field" style="margin-bottom:18px;">
      <label style="display:flex;align-items:center;gap:10px;font-size:15px;cursor:pointer;">
        <input type="checkbox" name="fb_enabled" value="1" <?php echo !empty($fb['enabled']) ? 'checked' : ''; ?> style="width:20px;height:20px;">
        <strong>Yüzen Buton Sistemi Aktif</strong> (İşareti kaldırırsan sitedeki yüzen butonlar tamamen gizlenir)
      </label>
    </div>

    <div class="grid-2">
      <div class="field">
        <label>Görünüm Tarzı (Simetri)</label>
        <select name="fb_style">
          <option value="circle" <?php echo ($fb['style'] ?? 'circle') === 'circle' ? 'selected' : ''; ?>>Dairesel İkonlar (Kusursuz Simetrik - Mac, Tablet ve Mobil İçin İdeal)</option>
          <option value="pill" <?php echo ($fb['style'] ?? '') === 'pill' ? 'selected' : ''; ?>>Yazılı Oval Butonlar (İkon + Yanında Metin)</option>
        </select>
        <div class="hint">Dairesel modda butonlar eşit boyutta (52×52px) kusursuz daire olarak durur, masaüstünde fareyle üzerine gelince etiket görünür.</div>
      </div>

      <div class="field">
        <label>Ekrandaki Konum</label>
        <select name="fb_position">
          <option value="right" <?php echo ($fb['position'] ?? 'right') === 'right' ? 'selected' : ''; ?>>Sağ Alt Köşe (Standart / Önerilen)</option>
          <option value="left" <?php echo ($fb['position'] ?? '') === 'left' ? 'selected' : ''; ?>>Sol Alt Köşe</option>
        </select>
        <div class="hint">Butonların ekranın sağında mı solunda mı duracağını belirler.</div>
      </div>
    </div>

    <div class="grid-2" style="margin-top:14px;">
      <div class="field">
        <label>Animasyon</label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
          <input type="checkbox" name="fb_pulse" value="1" <?php echo !empty($fb['pulse_animation']) ? 'checked' : ''; ?>>
          <span>Hafif dalgalanma (Pulse) animasyonu (Dikkati çeker)</span>
        </label>
      </div>

      <div class="field">
        <label>Mobil Cihaz Davranışı</label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
          <input type="checkbox" name="fb_mobile_icon_only" value="1" <?php echo !empty($fb['mobile_icon_only']) ? 'checked' : ''; ?>>
          <span>Telefonda tam simetrik kompakt daire olarak göster (Ekranı kaplamaz)</span>
        </label>
      </div>
    </div>
  </div>

  <!-- WHATSAPP BUTONU -->
  <div class="card">
    <h2>💬 WhatsApp Hızlı İletişim Butonu</h2>
    <p class="card-hint">
      Müşterilerin tek tıkla doğrudan WhatsApp üzerinden yetkiliyle sohbete başlamasını sağlar.
    </p>

    <div class="field" style="margin-bottom:18px;">
      <label style="display:flex;align-items:center;gap:10px;font-size:15px;cursor:pointer;">
        <input type="checkbox" name="fb_wa_enabled" value="1" <?php echo !empty($fb['whatsapp_enabled']) ? 'checked' : ''; ?> style="width:20px;height:20px;">
        <strong>WhatsApp Butonunu Göster</strong>
      </label>
    </div>

    <div class="grid-2">
      <div class="field">
        <label>WhatsApp Numarası (Ülke kodu ile, başında + olmadan)</label>
        <input type="text" name="fb_wa_number" value="<?php echo e($fb['whatsapp_number'] ?? ''); ?>" placeholder="905335598701">
        <div class="hint">Örnek: 905335598701 (Boşluk veya tire bırakmayınız)</div>
      </div>

      <div class="field">
        <label>Buton / İpucu Yazısı</label>
        <input type="text" name="fb_wa_text" value="<?php echo e($fb['whatsapp_text'] ?? 'WhatsApp'); ?>" placeholder="WhatsApp">
        <div class="hint">Örn: "WhatsApp", "Bize Yazın", "Hızlı Teklif"</div>
      </div>
    </div>

    <div class="field">
      <label>Hazır Başlangıç Mesajı (Ziyaretçi tıkladığında WhatsApp sohbet kutusuna otomatik dolar)</label>
      <input type="text" name="fb_wa_message" value="<?php echo e($fb['whatsapp_message'] ?? ''); ?>" placeholder="Merhaba, ürünleriniz hakkında teklif almak istiyorum.">
      <div class="hint">Ziyaretçi butona bastığında doğrudan bu mesajla sohbet açılır. Boş bırakırsan standart boş sohbet başlar.</div>
    </div>
  </div>

  <!-- HIZLI ARAMA / TELEFON BUTONU -->
  <div class="card">
    <h2>📞 Hızlı Arama Butonu (Doğrudan Telefonla Arama)</h2>
    <p class="card-hint">
      WhatsApp'ın yanında veya tek başına doğrudan telefon arama butonu eklemek istersen açabilirsin.
    </p>

    <div class="field" style="margin-bottom:18px;">
      <label style="display:flex;align-items:center;gap:10px;font-size:15px;cursor:pointer;">
        <input type="checkbox" name="fb_phone_enabled" value="1" <?php echo !empty($fb['phone_enabled']) ? 'checked' : ''; ?> style="width:20px;height:20px;">
        <strong>Hızlı Arama Butonunu Göster</strong>
      </label>
    </div>

    <div class="grid-2">
      <div class="field">
        <label>Telefon Numarası</label>
        <input type="text" name="fb_phone_number" value="<?php echo e($fb['phone_number'] ?? ''); ?>" placeholder="+905335598701">
        <div class="hint">Tıklandığında doğrudan bu numarayı arar.</div>
      </div>

      <div class="field">
        <label>Buton / İpucu Yazısı</label>
        <input type="text" name="fb_phone_text" value="<?php echo e($fb['phone_text'] ?? 'Hemen Arayın'); ?>" placeholder="Hemen Arayın">
      </div>
    </div>
  </div>

  <!-- ÖZEL EK BUTONLAR -->
  <div class="card">
    <h2>➕ İsteğe Bağlı Ek Yüzen Butonlar</h2>
    <p class="card-hint">
      WhatsApp veya Telefon dışında sitenin köşesine sabitlemek istediğin özel linkler (Örn: "Teklif Al", "Katalog İndir").
    </p>

    <div id="customButtonsWrap">
      <?php foreach (($fb['custom_buttons'] ?? []) as $btn): ?>
        <div class="repeat-row" style="background:#f8fafc;padding:14px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:12px;position:relative;">
          <button type="button" class="remove-row" onclick="this.closest('.repeat-row').remove()" style="position:absolute;top:8px;right:8px;background:none;border:none;cursor:pointer;color:#94a3b8;font-size:16px;">✕</button>
          <div class="grid-2">
            <div class="field">
              <label>Buton Yazısı</label>
              <input type="text" name="cb_label[]" value="<?php echo e($btn['label'] ?? ''); ?>" placeholder="Örn: Teklif Al">
            </div>
            <div class="field">
              <label>Bağlantı Adresi (URL)</label>
              <input type="text" name="cb_link[]" value="<?php echo e($btn['link'] ?? ''); ?>" placeholder="/teklif veya https://...">
            </div>
          </div>
          <div class="grid-2" style="margin-top:8px;">
            <div class="field">
              <label>İkon (Emoji veya Kısa Metin)</label>
              <input type="text" name="cb_icon[]" value="<?php echo e($btn['icon'] ?? ''); ?>" placeholder="📄">
            </div>
            <div class="field">
              <label>Buton Rengi</label>
              <input type="color" name="cb_color[]" value="<?php echo e($btn['color'] ?? '#0d3b66'); ?>" style="height:40px;padding:2px;cursor:pointer;">
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <button type="button" class="btn ghost small" onclick="addCustomBtn()">+ Yeni Buton Ekle</button>
  </div>
</form>

<script>
function addCustomBtn() {
  const wrap = document.getElementById('customButtonsWrap');
  const div = document.createElement('div');
  div.className = 'repeat-row';
  div.style.cssText = 'background:#f8fafc;padding:14px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:12px;position:relative;';
  div.innerHTML = `
    <button type="button" class="remove-row" onclick="this.closest('.repeat-row').remove()" style="position:absolute;top:8px;right:8px;background:none;border:none;cursor:pointer;color:#94a3b8;font-size:16px;">✕</button>
    <div class="grid-2">
      <div class="field">
        <label>Buton Yazısı</label>
        <input type="text" name="cb_label[]" placeholder="Örn: Teklif Al">
      </div>
      <div class="field">
        <label>Bağlantı Adresi (URL)</label>
        <input type="text" name="cb_link[]" placeholder="/teklif veya https://...">
      </div>
    </div>
    <div class="grid-2" style="margin-top:8px;">
      <div class="field">
        <label>İkon (Emoji veya Kısa Metin)</label>
        <input type="text" name="cb_icon[]" placeholder="📄">
      </div>
      <div class="field">
        <label>Buton Rengi</label>
        <input type="color" name="cb_color[]" value="#0d3b66" style="height:40px;padding:2px;cursor:pointer;">
      </div>
    </div>
  `;
  wrap.appendChild(div);
}
</script>

<?php require __DIR__ . '/layout-bottom.php'; ?>
