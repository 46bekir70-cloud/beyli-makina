<?php
/**
 * Teklif formu işleyicisi.
 *
 * Formu POST ile alır, doğrular, varsa dosyayı kontrol eder ve maili
 * ekiyle beraber gönderir. Sonra kullanıcıyı /teklif sayfasına sonuç
 * mesajıyla birlikte geri döndürür — böylece sayfa yenilendiğinde form
 * tekrar gönderilmez.
 */
require_once __DIR__ . '/inc/mailer.php';

bm_session_start();

/** Sonucu oturuma yazıp forma geri dön */
function bm_form_redirect($status, $message, $old = []) {
    $_SESSION['bm_form_result'] = ['status' => $status, 'message' => $message];
    // Hata durumunda kullanıcı yazdıklarını kaybetmesin
    $_SESSION['bm_form_old'] = $status === 'ok' ? [] : $old;
    header('Location: /teklif');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /teklif');
    exit;
}

$formCfg = bm_config('form');
$maxBytes = bm_max_upload_bytes();
$maxMb    = bm_max_upload_mb();

// Kurumsal alan ayarları (panel → Teklif Sayfası → Kurumsal Form)
require_once __DIR__ . '/inc/countries.php';
$corp  = bm_corporate_config();
$corpF = $corp['fields'];

$kurumsal = $corp['enabled'] && (($_POST['musteri_turu'] ?? '') === 'kurumsal');

$data = [
    'fullname' => trim((string)($_POST['fullname'] ?? '')),
    'phone'    => trim((string)($_POST['phone'] ?? '')),
    'email'    => trim((string)($_POST['email'] ?? '')),
    'parttype' => trim((string)($_POST['parttype'] ?? '')),
    'details'  => trim((string)($_POST['details'] ?? '')),
    'product'  => trim((string)($_POST['product'] ?? '')),

    // Bireysel / kurumsal ve firma bilgileri
    'musteri_turu'    => $kurumsal ? 'kurumsal' : 'bireysel',
    'phone_country'   => strtoupper(trim((string)($_POST['phone_country'] ?? 'TR'))),
    'company'         => trim((string)($_POST['company'] ?? '')),
    'taxoffice'       => trim((string)($_POST['taxoffice'] ?? '')),
    'taxno'           => trim((string)($_POST['taxno'] ?? '')),
    'role'            => trim((string)($_POST['role'] ?? '')),
    'company_country' => strtoupper(trim((string)($_POST['company_country'] ?? ''))),
    'city'            => trim((string)($_POST['city'] ?? '')),
    'district'        => trim((string)($_POST['district'] ?? '')),
    'address'         => trim((string)($_POST['address'] ?? '')),
    'company_web'     => trim((string)($_POST['company_web'] ?? '')),
    'qty'             => trim((string)($_POST['qty'] ?? '')),
    'deadline'        => trim((string)($_POST['deadline'] ?? '')),
    'pono'            => trim((string)($_POST['pono'] ?? '')),
    'kvkk'            => !empty($_POST['kvkk']) ? '1' : '',
    'izin'            => !empty($_POST['izin']) ? '1' : '',
];

// --- Bot tuzağı: gerçek kullanıcıya görünmeyen alan doldurulmuşsa sessizce yut
if (trim((string)($_POST['website'] ?? '')) !== '') {
    // Bota da normal başarı mesajı gösteriliyor — tuzağa düştüğünü anlamasın
    bm_form_redirect('ok', bm_t('form.msg_ok', 'Teklif talebiniz bize ulaştı. En kısa sürede size dönüş yapacağız.'));
}

// --- Sahte site koruması (CSRF): jeton yoksa ya da tutmuyorsa işleme alma
if (!bm_form_token_check($_POST['form_token'] ?? '')) {
    bm_form_redirect('err', bm_t(
        'form.err_token',
        'Güvenlik doğrulaması başarısız oldu. Sayfa uzun süre açık kalmış olabilir — sayfayı yenileyip tekrar gönderin.'
    ), $data);
}

// --- Art arda gönderim sınırı (oturum bazlı)
$throttle = (int)($formCfg['throttle_secs'] ?? 60);
$last = $_SESSION['bm_form_last'] ?? 0;
if ($throttle > 0 && $last && (time() - $last) < $throttle) {
    $kalan = $throttle - (time() - $last);
    bm_form_redirect('err', sprintf(
        bm_t('form.msg_throttle', 'Az önce bir talep gönderdiniz. Lütfen %s saniye sonra tekrar deneyin.'),
        $kalan
    ), $data);
}

// --- IP bazlı hız sınırı (çerez saklamayan spam botlarına karşı: 5 dakikada en fazla 5 talep)
if (!bm_form_ip_throttle_check(5, 300)) {
    bm_form_redirect('err', bm_t(
        'form.msg_throttle_ip',
        'Kısa süre içinde çok fazla talep gönderildi. Lütfen birkaç dakika sonra tekrar deneyin.'
    ), $data);
}

// --- Doğrulama
$errors = [];
if ($data['fullname'] === '') {
    $errors[] = bm_t('form.err_name', 'Ad soyad alanı boş bırakılamaz.');
}
if ($data['email'] === '' && $data['phone'] === '') {
    $errors[] = bm_t('form.err_contact', 'Size dönebilmemiz için e-posta ya da telefon bilgisi gerekli.');
}
if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = bm_t('form.err_email', 'E-posta adresi geçerli görünmüyor.');
}
if ($data['details'] === '') {
    $errors[] = bm_t('form.err_details', 'Lütfen sipariş detaylarını yazın.');
}

// --- Telefon: ülke koduyla birleştirilip biçimlendirilir
if ($data['phone'] !== '') {
    $tel = bm_phone_parse($data['phone_country'], $data['phone']);
    if (!$tel['valid']) {
        $errors[] = bm_t('form.err_phone', 'Telefon numarası hatalı görünüyor, kontrol eder misiniz?');
    } else {
        // Mailde uluslararası biçimde görünsün: +90 533 340 32 85
        $data['phone_display'] = $tel['display'];
        $data['phone_e164']    = $tel['e164'];
    }
}

// --- Aydınlatma metni onayı (metin adresi girilmemişse kutu zaten yok)
if ($corp['kvkk_url'] !== '' && $data['kvkk'] === '') {
    $errors[] = bm_t('form.err_kvkk', 'Devam etmek için aydınlatma metni onayı gerekli.');
}

// --- Kurumsal alanlar: panelde "zorunlu" işaretlenenler boş bırakılamaz
if ($kurumsal) {
    $zorunluAlanlar = [
        'company'   => ['company',         'form.err_company',   'Firma ünvanı boş bırakılamaz.'],
        'taxoffice' => ['taxoffice',       'form.err_taxoffice', 'Vergi dairesi boş bırakılamaz.'],
        'taxno'     => ['taxno',           'form.err_taxno',     'Vergi numarası boş bırakılamaz.'],
        'role'      => ['role',            'form.err_role',      'Görev / departman boş bırakılamaz.'],
        'country'   => ['company_country', 'form.err_country',   'Lütfen ülke seçin.'],
        'city'      => ['city',            'form.err_city',      'İl / şehir boş bırakılamaz.'],
        'district'  => ['district',        'form.err_district',  'İlçe boş bırakılamaz.'],
        'address'   => ['address',         'form.err_address',   'Açık adres boş bırakılamaz.'],
        'web'       => ['company_web',     'form.err_web',       'Web sitesi boş bırakılamaz.'],
        'qty'       => ['qty',             'form.err_qty',       'Tahmini adet boş bırakılamaz.'],
        'deadline'  => ['deadline',        'form.err_deadline',  'İstenen termin boş bırakılamaz.'],
        'pono'      => ['pono',            'form.err_pono',      'Satınalma sipariş no boş bırakılamaz.'],
    ];
    foreach ($zorunluAlanlar as $ayar => $bilgi) {
        if (($corpF[$ayar] ?? 'kapali') !== 'zorunlu') continue;
        if ($data[$bilgi[0]] === '') {
            $errors[] = bm_t($bilgi[1], $bilgi[2]);
        }
    }

    // Vergi no / TCKN: hatalıysa gönderim engellenmez, mailde not düşülür.
    if ($data['taxno'] !== '' && ($data['company_country'] === '' || $data['company_country'] === 'TR')) {
        $data['taxno_supheli'] = bm_vergino_gecerli($data['taxno']) ? '' : '1';
    }

    // Ülke adını mailde okunaklı yazabilmek için
    $firmaUlke = bm_country($data['company_country']);
    if ($firmaUlke) $data['company_country_name'] = $firmaUlke['name'];
}

if ($errors) {
    bm_form_redirect('err', implode(' ', $errors), $data);
}

// --- Dosya eki (isteğe bağlı)
$attachment = null;
$tmpDir = __DIR__ . '/content/tmp';

if (!empty($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['attachment'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        // Mesajlar panelden değiştirilebiliyor (Site Yazıları → Form Dosya Hataları)
        $sinir = $maxMb . ' MB';
        $errMap = [
            UPLOAD_ERR_INI_SIZE   => sprintf(bm_t('form.file_err_ini', 'Dosya sunucunun izin verdiği boyutu aşıyor (en fazla %s).'), $sinir),
            UPLOAD_ERR_FORM_SIZE  => sprintf(bm_t('form.file_err_big', 'Dosya çok büyük (en fazla %s).'), $sinir),
            UPLOAD_ERR_PARTIAL    => bm_t('form.file_err_partial', 'Dosya yalnızca kısmen yüklendi, tekrar deneyin.'),
            UPLOAD_ERR_NO_TMP_DIR => bm_t('form.file_err_tmp', 'Sunucuda geçici klasör bulunamadı (hosting ayarı).'),
            UPLOAD_ERR_CANT_WRITE => bm_t('form.file_err_write', 'Dosya diske yazılamadı (sunucu izin sorunu).'),
            UPLOAD_ERR_EXTENSION  => bm_t('form.file_err_ext', 'Bir sunucu eklentisi yüklemeyi durdurdu.'),
        ];
        bm_form_redirect('err', $errMap[$file['error']] ?? bm_t('form.file_err_other', 'Dosya yüklenemedi.'), $data);
    }

    if ($file['size'] > $maxBytes) {
        bm_form_redirect('err', sprintf(
            bm_t('form.file_err_big', 'Dosya çok büyük (en fazla %s).'),
            $maxMb . ' MB'
        ), $data);
    }

    // Teknik çizim dosyaları çoğu zaman "application/octet-stream" olarak
    // geldiği için MIME tek başına yeterli değil; uzantı da kontrol ediliyor.
    $allowedExt = ['pdf','jpg','jpeg','png','webp','dwg','dxf','step','stp','iges','igs','zip','rar','7z','xlsx','xls','doc','docx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        bm_form_redirect('err', sprintf(
            bm_t('form.file_err_type', 'Bu dosya türü kabul edilmiyor. İzin verilenler: %s.'),
            implode(', ', $allowedExt)
        ), $data);
    }

    if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);
    // Yüklenen dosya web'den erişilemeyen bir isimle geçici klasöre alınır,
    // mail gönderildikten sonra silinir.
    $safeName = 'ek_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $tmpPath  = $tmpDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
        bm_form_redirect('err', bm_t('form.file_err_save', 'Dosya kaydedilemedi. Lütfen tekrar deneyin.'), $data);
    }

    $attachment = [
        'path' => $tmpPath,
        // Müşterinin verdiği isim mailde görünecek; yol bileşenlerini temizliyoruz.
        'name' => basename(str_replace('\\', '/', $file['name'])),
    ];
}

// --- Gönder
try {
    bm_send_quote_mail($data, $attachment);
    $_SESSION['bm_form_last'] = time();
    bm_form_ip_throttle_record();
    $ok = true;
} catch (Throwable $e) {
    error_log('[beyli-teklif] ' . $e->getMessage());
    $ok = false;
}

if ($attachment && is_file($attachment['path'])) {
    @unlink($attachment['path']);
}

if ($ok) {
    bm_form_redirect('ok', bm_t('form.msg_ok', 'Teklif talebiniz bize ulaştı. En kısa sürede size dönüş yapacağız.'));
}

bm_form_redirect(
    'err',
    bm_t('form.msg_send_err', 'Talebiniz şu anda gönderilemedi. Lütfen biraz sonra tekrar deneyin ya da doğrudan e-posta ile ulaşın.'),
    $data
);
