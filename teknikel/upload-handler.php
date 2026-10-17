<?php
/**
 * AJAX görsel yükleme endpoint'i — hem Quill editördeki resim butonundan,
 * hem de Medya Kütüphanesi sayfasından çağrılır. JSON döner.
 *
 * NOT: Bazı paylaşımlı hostinglerde PHP'nin standart $_FILES yükleme mekanizması
 * (multipart/form-data) sunucunun "geçici klasör" (upload_tmp_dir) ayarı bozuk
 * olduğu için çalışmayabilir ("Sunucuda geçici tmp klasör bulunamadı" hatası).
 * Bunu bypass etmek için dosyayı ham (raw) HTTP gövdesi olarak alıp doğrudan
 * php://input üzerinden okuyoruz — bu yöntem upload_tmp_dir'e ihtiyaç duymaz.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/medya-lib.php';
header('Content-Type: application/json; charset=utf-8');

function bm_json_fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if (!bm_admin_logged_in()) {
    bm_json_fail('Giriş yapmalısınız.', 401);
}

$sentCsrf = $_SERVER['HTTP_X_CSRF'] ?? ($_GET['csrf'] ?? ($_POST['csrf'] ?? ''));
if (!hash_equals($_SESSION['bm_csrf'] ?? '', $sentCsrf)) {
    bm_json_fail('Geçersiz oturum (CSRF). Sayfayı yenileyip tekrar deneyin.');
}

// Gelen dosya sınırı. Sunucuda saklanan hâli aşağıdaki optimizasyondan sonra
// çok daha küçük olduğu için giriş sınırı biraz geniş tutulabiliyor; telefonla
// çekilmiş fotoğraflar kolayca 8 MB'ı geçiyor.
$maxBytes = 10 * 1024 * 1024;

/**
 * Yüklenen dosyanın adı.
 *
 * İstek "ad" parametresiyle bir ipucu gönderirse (ör. ürünün adı) dosya adı
 * onunla başlıyor: "miller-saft-o40-20260811_0032_a1b2c3.jpg". Binlerce görsel
 * arasında arama ancak böyle işe yarıyor — yalnızca tarih/rastgele karakterden
 * oluşan adlarda hangi dosyanın hangi ürüne ait olduğu anlaşılmıyor.
 */
/**
 * Yüklemenin hangi klasöre gideceğini belirler.
 *
 * "klasor" parametresi images/uploads altındaki göreli yoldur (ör. "urunler").
 * Boşsa ya da geçersizse kök klasöre düşer — hiçbir koşulda uploads dışına
 * çıkılamaz (bm_media_guvenli_yol ".." parçalarını atıyor).
 */
function bm_upload_klasor() {
    $rel = bm_media_guvenli_yol($_GET['klasor'] ?? $_POST['klasor'] ?? '');
    if ($rel === '') return ['', BM_ROOT . '/images/uploads/'];
    $tam = bm_media_tam_yol($rel);
    if ($tam === null || !is_dir($tam)) return ['', BM_ROOT . '/images/uploads/'];
    return [$rel, $tam . '/'];
}

function bm_upload_filename($ext) {
    $ad = trim((string)($_GET['ad'] ?? ''));
    $onEk = '';
    if ($ad !== '') {
        $onEk = substr(bm_slugify($ad), 0, 48) . '-';
    }
    return $onEk . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
}

/** Görselde gerçekten saydam piksel var mı? (ızgara örneklemesi — hızlı) */
function bm_has_alpha($im) {
    $w = imagesx($im); $h = imagesy($im);
    $adim = max(1, (int)(max($w, $h) / 200)); // en çok ~40.000 örnek
    for ($y = 0; $y < $h; $y += $adim) {
        for ($x = 0; $x < $w; $x += $adim) {
            if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) > 0) return true;
        }
    }
    return false;
}

/**
 * Görseli kaydederken küçültür ve sıkıştırır.
 *
 * Neden gerekli: telefondan/masaüstünden gelen dosyalar 2400 piksel genişlikte
 * ve 5 MB olabiliyor. Sayfada en fazla ~1600 piksel görüntülendiği için bu
 * boyut tamamen boşa gidiyor, mobil bağlantıda sayfayı dakikalarca bekletiyor.
 * 1000 kalem ürün fotoğrafı düşünülünce disk ve trafik farkı çok büyük.
 *
 * Kurallar:
 *   - 2000 pikselden geniş görseller 2000'e küçültülür (asla büyütülmez).
 *   - Saydamlığı OLMAYAN PNG'ler JPEG'e çevrilir (fotoğraflar için 10–25 kat
 *     küçük). Saydam PNG'ler (logo/ikon) PNG kalır, şeffaflık korunur.
 *   - Hareketli GIF'e dokunulmaz; yeniden kaydedilirse ilk kareye düşerdi.
 *   - İşlem sonunda dosya küçülmediyse orijinal veri yazılır.
 *
 * Dönüş: kaydedilen dosyanın adı, ya da hata durumunda null.
 */
function bm_save_image($raw, $mime, $ext, $uploadDir) {
    $enBoy = 2000;
    $kalite = 82;

    $yaz = function ($ad, $veri) use ($uploadDir) {
        return file_put_contents($uploadDir . $ad, $veri) === false ? null : $ad;
    };

    if ($mime === 'image/gif') return $yaz(bm_upload_filename($ext), $raw);

    $im = @imagecreatefromstring($raw);
    if (!$im) return $yaz(bm_upload_filename($ext), $raw); // okunamadıysa olduğu gibi

    $w = imagesx($im);
    $saydam = ($ext !== 'jpg') && bm_has_alpha($im);

    $kucultuldu = false;
    if ($w > $enBoy) {
        $yeni = imagescale($im, $enBoy);
        if ($yeni) { $im = $yeni; $kucultuldu = true; }
    }

    // Saydamlığı olmayan görseller JPEG olarak en küçük çıkıyor
    $hedef = $saydam ? $ext : 'jpg';
    $ad = bm_upload_filename($hedef);
    $yol = $uploadDir . $ad;

    if ($hedef === 'jpg') {
        // JPEG saydamlığı desteklemiyor: altına beyaz zemin koyuyoruz
        $zemin = imagecreatetruecolor(imagesx($im), imagesy($im));
        imagefill($zemin, 0, 0, imagecolorallocate($zemin, 255, 255, 255));
        imagecopy($zemin, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
        $ok = imagejpeg($zemin, $yol, $kalite);
    } elseif ($hedef === 'webp') {
        $ok = imagewebp($im, $yol, $kalite);
    } else {
        imagesavealpha($im, true);
        $ok = imagepng($im, $yol, 8);
    }
    if (!$ok) return $yaz(bm_upload_filename($ext), $raw);

    // Sıkıştırma işe yaramadıysa (küçük ikonlarda olabiliyor) orijinali koru
    if (!$kucultuldu && $hedef === $ext && filesize($yol) >= strlen($raw)) {
        unlink($yol);
        return $yaz(bm_upload_filename($ext), $raw);
    }
    return $ad;
}

// --- YÖNTEM 1: Ham (raw) gövde ile yükleme (yeni, tmp klasör gerektirmez) ---
$rawUpload = !empty($_GET['raw']) || !empty($_SERVER['HTTP_X_RAW_UPLOAD']);

if ($rawUpload) {
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) === 0) {
        bm_json_fail('Dosya alınamadı (boş veri geldi).');
    }
    if (strlen($raw) > $maxBytes) {
        bm_json_fail('Dosya çok büyük (en fazla 10 MB).');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    // NOT: finfo_close() çağrılmıyor — PHP 8.5'te "deprecated" uyarısı basıyor
    // ve o uyarı JSON yanıtının başına düşüp yüklemeyi bozuyordu. Nesne istek
    // sonunda kendiliğinden serbest kalıyor, kapatmaya gerek yok.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_buffer($finfo, $raw);

    if (!isset($allowed[$mime])) {
        bm_json_fail('Sadece JPG, PNG, WEBP veya GIF görsel yükleyebilirsiniz. (Algılanan tür: ' . $mime . ')');
    }

    $ext = $allowed[$mime];
    [$klasorRel, $uploadDir] = bm_upload_klasor();
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

    $safeName = bm_save_image($raw, $mime, $ext, $uploadDir);
    if ($safeName === null) {
        bm_json_fail('Dosya kaydedilemedi. Klasör izinlerini kontrol edin (images/uploads yazılabilir olmalı).');
    }

    echo json_encode([
        'ok' => true,
        'url' => bm_media_url_of(($klasorRel !== '' ? $klasorRel . '/' : '') . $safeName),
        'name' => $safeName,
    ]);
    exit;
}

// --- YÖNTEM 2: Klasik $_FILES ile yükleme (eski/yedek yöntem) ---
if (empty($_FILES['image'])) {
    bm_json_fail('Dosya alınamadı (sunucuya hiç ulaşmadı). upload_max_filesize / post_max_size ayarlarını kontrol edin.');
}
if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errCodes = [
        1 => 'Dosya sunucunun izin verdiği maksimum boyutu (upload_max_filesize) aşıyor.',
        2 => 'Dosya form tarafından izin verilen boyutu aşıyor.',
        3 => 'Dosya yalnızca kısmen yüklendi, tekrar deneyin.',
        4 => 'Hiç dosya seçilmedi.',
        6 => 'Sunucuda geçici (tmp) klasör bulunamadı — hosting ayar sorunu.',
        7 => 'Dosya diske yazılamadı — sunucu izin sorunu.',
        8 => 'Bir PHP eklentisi yüklemeyi durdurdu.',
    ];
    $code = $_FILES['image']['error'];
    $detail = $errCodes[$code] ?? ('Bilinmeyen hata kodu: ' . $code);
    bm_json_fail('Dosya alınamadı. Sebep: ' . $detail);
}

$file = $_FILES['image'];
if ($file['size'] > $maxBytes) {
    bm_json_fail('Dosya çok büyük (en fazla 10 MB).');
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);

if (!isset($allowed[$mime])) {
    bm_json_fail('Sadece JPG, PNG, WEBP veya GIF görsel yükleyebilirsiniz.');
}

$ext = $allowed[$mime];
[$klasorRel, $uploadDir] = bm_upload_klasor();
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

// Ham yöntemle aynı optimizasyondan geçsin diye dosya belleğe alınıyor
$ham = file_get_contents($file['tmp_name']);
$safeName = $ham === false ? null : bm_save_image($ham, $mime, $ext, $uploadDir);
if ($safeName === null) {
    bm_json_fail('Dosya kaydedilemedi. Klasör izinlerini kontrol edin (images/uploads yazılabilir olmalı).');
}

echo json_encode([
    'ok' => true,
    'url' => bm_media_url_of(($klasorRel !== '' ? $klasorRel . '/' : '') . $safeName),
    'name' => $safeName,
]);
