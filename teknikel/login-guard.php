<?php
// Doğrudan açılırsa sessizce 404 — sunucu yolu ve kod satırı sızmasın
if (!defined('BM_ROOT')) { http_response_code(404); exit; }
/**
 * Giriş denemesi koruması (kaba kuvvet / brute force savunması).
 *
 * Sorun: giriş ekranı sınırsız deneme kabul ediyordu. Saldırgan saniyede
 * onlarca şifre deneyip zayıf bir şifreyi kırabilirdi; kimse de farkına
 * varmazdı çünkü hiçbir kayıt tutulmuyordu.
 *
 * Çözüm üç katmanlı:
 *   1) IP başına deneme sayacı — 5 hatada 15 dakika, 10 hatada 1 saat kilit.
 *   2) Her hatalı denemede küçük bir gecikme — otomatik araçları yavaşlatır.
 *   3) Kayıt tutma — panelde "son denemeler" görünür, saldırı fark edilir.
 *
 * Veriler content/login-attempts.json içinde. O klasördeki .json dosyalarına
 * dışarıdan erişim .htaccess ile kapalı (403).
 *
 * Kilitli kaldıysan: sunucudan content/login-attempts.json dosyasını sil.
 */

define('BM_GIRIS_DOSYA', BM_ROOT . '/content/login-attempts.json');
define('BM_GIRIS_PENCERE', 900);   // 15 dakika
define('BM_GIRIS_SINIR_1', 5);     // bu kadar hatadan sonra kısa kilit
define('BM_GIRIS_SINIR_2', 10);    // bu kadar hatadan sonra uzun kilit
define('BM_GIRIS_KILIT_1', 900);   // 15 dakika
define('BM_GIRIS_KILIT_2', 3600);  // 1 saat

/**
 * Ziyaretçinin gerçek IP'si.
 *
 * Plesk'te istek nginx üzerinden geldiği için REMOTE_ADDR 127.0.0.1 olabiliyor;
 * o durumda gerçek adres X-Forwarded-For'un SONUNDA duruyor (en son ekleyen,
 * bize en yakın vekil sunucu). Başındaki değerler istemci tarafından
 * uydurulabildiği için onlara güvenilmiyor.
 */
if (!function_exists('bm_client_ip')) {
    function bm_client_ip() {
        $uzak = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $herkeseAcikMi = filter_var($uzak, FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        if (!$herkeseAcikMi && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $liste = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            $son = end($liste);
            if (filter_var($son, FILTER_VALIDATE_IP)) return $son;
        }
        return $uzak;
    }
}

/** Kayıt dosyasını oku */
function bm_giris_kayit_oku() {
    if (!file_exists(BM_GIRIS_DOSYA)) return ['ip' => [], 'log' => []];
    $d = json_decode((string)file_get_contents(BM_GIRIS_DOSYA), true);
    return is_array($d) ? $d + ['ip' => [], 'log' => []] : ['ip' => [], 'log' => []];
}

/** Kayıt dosyasını yaz (eski kayıtları temizleyerek) */
function bm_giris_kayit_yaz($d) {
    $simdi = time();
    foreach ($d['ip'] as $ip => $bilgi) {
        // Penceresi geçmiş ve kilidi bitmiş kayıtlar silinir — dosya şişmesin
        if (($bilgi['son'] ?? 0) < $simdi - 86400 && ($bilgi['kilit'] ?? 0) < $simdi) {
            unset($d['ip'][$ip]);
        }
    }
    $d['log'] = array_slice($d['log'], -25); // son 25 deneme yeter
    @file_put_contents(BM_GIRIS_DOSYA, json_encode($d, JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Bu IP kilitli mi? Kilitliyse kalan saniyeyi döndürür, değilse 0.
 */
function bm_giris_kilit_suresi() {
    $d = bm_giris_kayit_oku();
    $ip = bm_client_ip();
    $kilit = (int)($d['ip'][$ip]['kilit'] ?? 0);
    return $kilit > time() ? $kilit - time() : 0;
}

/** Başarısız denemeyi işle; gerekiyorsa kilit koyar */
function bm_giris_basarisiz() {
    $d = bm_giris_kayit_oku();
    $ip = bm_client_ip();
    $simdi = time();
    $k = $d['ip'][$ip] ?? ['sayi' => 0, 'ilk' => $simdi, 'son' => $simdi, 'kilit' => 0];

    // Pencere dolduysa sayaç sıfırlanır
    if ($simdi - ($k['ilk'] ?? $simdi) > BM_GIRIS_PENCERE) {
        $k['sayi'] = 0;
        $k['ilk'] = $simdi;
    }
    $k['sayi'] = (int)$k['sayi'] + 1;
    $k['son'] = $simdi;

    if ($k['sayi'] >= BM_GIRIS_SINIR_2)      $k['kilit'] = $simdi + BM_GIRIS_KILIT_2;
    elseif ($k['sayi'] >= BM_GIRIS_SINIR_1)  $k['kilit'] = $simdi + BM_GIRIS_KILIT_1;

    $d['ip'][$ip] = $k;
    $d['log'][] = ['t' => $simdi, 'ip' => $ip, 'sonuc' => 'hata'];
    bm_giris_kayit_yaz($d);

    // Otomatik araçları yavaşlat (insan için farkedilmez)
    usleep(400000);
    return $k;
}

/** Başarılı giriş: bu IP'nin sayacı sıfırlanır */
function bm_giris_basarili() {
    $d = bm_giris_kayit_oku();
    $ip = bm_client_ip();
    // Son başarılı girişten bu yana kaç hatalı deneme olmuş?
    $sonBasari = 0;
    foreach (array_reverse($d['log']) as $kayit) {
        if (($kayit['sonuc'] ?? '') === 'ok') { $sonBasari = $kayit['t']; break; }
    }
    $hataliSayi = 0;
    foreach ($d['log'] as $kayit) {
        if (($kayit['sonuc'] ?? '') === 'hata' && ($kayit['t'] ?? 0) > $sonBasari) $hataliSayi++;
    }

    unset($d['ip'][$ip]);
    $d['log'][] = ['t' => time(), 'ip' => $ip, 'sonuc' => 'ok'];
    bm_giris_kayit_yaz($d);
    return $hataliSayi;
}

/** Panelde göstermek için son denemeler */
function bm_giris_son_denemeler($adet = 10) {
    $d = bm_giris_kayit_oku();
    return array_slice(array_reverse($d['log']), 0, $adet);
}

/** Şu an kilitli olan IP'ler */
function bm_giris_kilitli_ipler() {
    $d = bm_giris_kayit_oku();
    $out = [];
    foreach ($d['ip'] as $ip => $b) {
        if (($b['kilit'] ?? 0) > time()) $out[$ip] = $b;
    }
    return $out;
}
