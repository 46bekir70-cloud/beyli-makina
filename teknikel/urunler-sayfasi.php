<?php
/**
 * Ürünler sayfasının (/urunler) görünümü.
 *
 * Ürünlerin kendisi veritabanında ve "Ürünler" bölümünden yönetiliyor; burada
 * yalnızca sayfanın üst kısmı var: başlık bandındaki görsel ve yazılar.
 *
 * Bu ekran, ayarların aranacağı yerde durması için açıldı — önceden başlık
 * görseli "Site Yazıları" sayfasının en altında duruyordu ve kimse bulamıyordu.
 */
require_once __DIR__ . '/config.php';

bm_admin_require_login();

$content = bm_load_content();
$bm_admin_active = 'sayfalar';
$bm_admin_title = 'Ürünler Sayfası';
$bm_admin_save_form = 'urunlerSayfaForm';
$message = '';

// Bu ekranda düzenlenen yazılar: form alanı => etiket anahtarı
$yazilar = [
    'lbl_tag'   => 'products.tag',
    'lbl_title' => 'products.title',
    'lbl_text'  => 'products.text',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } else {
        $content['page_heroes']['urunler'] = [
            'image' => trim($_POST['hero_image'] ?? ''),
            'focus' => trim($_POST['hero_focus'] ?? ''),
        ];

        // Etiketler ortak bir listede duruyor; yalnızca buradakileri güncelleyip
        // diğerlerine dokunmuyoruz (Site Yazıları ekranı aynı listeyi kullanıyor).
        $labels  = is_array($content['labels'] ?? null) ? $content['labels'] : [];
        $catalog = bm_label_catalog();
        foreach ($yazilar as $alan => $key) {
            $deger = trim((string)($_POST[$alan] ?? ''));
            $varsayilan = $catalog[$key][2] ?? '';
            // Varsayılanla aynıysa saklamıyoruz: ileride varsayılan değişirse
            // sayfa kendiliğinden güncellensin.
            if ($deger === '' || $deger === $varsayilan) unset($labels[$key]);
            else $labels[$key] = $deger;
        }
        $content['labels'] = $labels;

        bm_save_content($content);
        $message = 'ok|Ürünler sayfası kaydedildi.';
        $content = bm_load_content();
    }
}
[$msgType, $msgText] = array_pad(explode('|', $message, 2), 2, '');

$hero    = $content['page_heroes']['urunler'] ?? [];
$labels  = is_array($content['labels'] ?? null) ? $content['labels'] : [];
$catalog = bm_label_catalog();

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Ürünler Sayfası</h1>
    <div class="sub">
      Adres: <code>/urunler</code> —
      <a href="/urunler" target="_blank" style="color:var(--blue);">canlı sayfayı aç ↗</a>
    </div>
  </div>
  <a href="/teknikel/sayfalar.php" class="logout-link">← Sayfalar</a>
</div>

<?php if ($msgText): ?><div class="flash <?php echo $msgType; ?>"><?php echo e($msgText); ?></div><?php endif; ?>

<form method="post" id="urunlerSayfaForm">
  <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">

  <?php echo bm_admin_hero_card('hero_image', 'hero_focus', $hero['image'] ?? '', $hero['focus'] ?? ''); ?>

  <div class="card">
    <h2>Sayfa Yazıları</h2>
    <p class="card-hint">
      Başlık bandındaki metinler. Boş bırakırsan parantez içindeki varsayılan kullanılır.
    </p>
    <?php
      $alanlar = [
        'lbl_tag'   => ['Üst Etiket', 'products.tag'],
        'lbl_title' => ['Başlık', 'products.title'],
        'lbl_text'  => ['Açıklama', 'products.text'],
      ];
      foreach ($alanlar as $alan => [$etiket, $key]):
        $varsayilan = $catalog[$key][2] ?? '';
        $deger = $labels[$key] ?? '';
    ?>
      <div class="field">
        <label for="<?php echo e($alan); ?>"><?php echo e($etiket); ?></label>
        <?php if (mb_strlen($varsayilan) > 70): ?>
          <textarea id="<?php echo e($alan); ?>" name="<?php echo e($alan); ?>" rows="2"
                    placeholder="<?php echo e($varsayilan); ?>"><?php echo e($deger); ?></textarea>
        <?php else: ?>
          <input type="text" id="<?php echo e($alan); ?>" name="<?php echo e($alan); ?>"
                 value="<?php echo e($deger); ?>" placeholder="<?php echo e($varsayilan); ?>">
        <?php endif; ?>
        <div class="hint">Varsayılan: <?php echo e($varsayilan); ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>Bilmen gerekenler</h2>
    <p class="card-hint" style="margin:0;">
      <strong>Başlıktaki görsel kategoriye göre değişmez</strong> — ziyaretçi "Torna",
      "Flanş" gibi bir kategoriye geçtiğinde yalnızca başlık yazısı o kategorinin adına
      döner, arkadaki fotoğraf hep bu sayfada seçtiğin tek fotoğraf olarak kalır.<br><br>
      Ürünlerin kendisi (ekleme, düzenleme, kategoriler, CSV ile toplu aktarım)
      <a href="/teknikel/urunler.php" style="color:var(--blue);font-weight:600;">Ürünler</a>
      bölümünde. Arama kutusu, "Tümü" filtresi, "Detay &amp; Teklif" gibi diğer yazılar
      <a href="/teknikel/site-yazilari.php" style="color:var(--blue);font-weight:600;">Site Yazıları</a>
      içinde.
    </p>
  </div>

  <?php /* Kaydet butonu kenar çubuğunda sabit duruyor — layout-top.php */ ?>
</form>
<?php require __DIR__ . '/layout-bottom.php'; ?>
