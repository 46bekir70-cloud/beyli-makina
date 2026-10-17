<?php
/**
 * Görsel seçici penceresinin veri kaynağı.
 *
 * Panelde bir görsel alanının yanındaki "Siteden Seç" düğmesi bu adresi
 * çağırıp klasörleri ve dosyaları JSON olarak alıyor. Sadece giriş yapmış
 * yönetici erişebilir; okuma dışında hiçbir şey yapmaz.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/medya-lib.php';

header('Content-Type: application/json; charset=utf-8');

if (!bm_admin_logged_in()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'hata' => 'Oturum kapanmış. Sayfayı yenileyip tekrar giriş yap.']);
    exit;
}

$klasor = bm_media_guvenli_yol($_GET['klasor'] ?? '');
if ($klasor !== '' && !is_dir(bm_media_tam_yol($klasor) ?: '')) $klasor = '';

$q = trim((string)($_GET['q'] ?? ''));

// Arama varsa bütün kütüphanede, yoksa yalnızca bulunulan klasörde
$dosyalar = $q !== ''
    ? bm_media_dosyalar('', $q, true)
    : bm_media_dosyalar($klasor, '', false);

// Seçici penceresi küçük; çok sonuçta ilk 300 yeterli (arama zaten daraltıyor)
$dosyalar = array_slice($dosyalar, 0, 300);

echo json_encode([
    'ok'      => true,
    'klasor'  => $klasor,
    'klasorler' => array_map(function ($k) {
        return ['rel' => $k['rel'], 'ad' => $k['ad'], 'derinlik' => $k['derinlik'], 'sayi' => $k['sayi']];
    }, bm_media_klasorler('')),
    'dosyalar' => array_map(function ($f) {
        // url: tarayıcıda kullanılacak kodlu adres; ham hâli forma yazılacak
        // değer olarak gerekmiyor çünkü sunucu tarafı da kodluyu çözebiliyor.
        return ['ad' => $f['ad'], 'url' => bm_src($f['url']), 'klasor' => $f['klasor']];
    }, $dosyalar),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
