<?php
/**
 * Görsel kütüphanesi — dosya ve klasör işlemleri.
 *
 * Bütün işlemler images/uploads/ altında kalır. Dışarı çıkma (path traversal)
 * denemeleri bm_media_guvenli_yol() içinde durdurulur: gelen her yol
 * normalleştirilip kökün altında olduğu doğrulanır.
 *
 * Yeniden adlandırma ve taşımada dosyanın adresi değiştiği için, o adresi
 * kullanan ürün/sayfa/hizmet kayıtları da güncellenir — yoksa taşınan her
 * görsel sitede kırık bağlantıya döner.
 */

// articles.php içinde products.php de yükleniyor; makale görselleri de
// yeniden adlandırmadan etkilendiği için ikisi de gerekli.
require_once __DIR__ . '/../inc/articles.php';

/** Kütüphanenin kök dizini (sonunda / yok) */
function bm_media_root() {
    return BM_ROOT . '/images/uploads';
}

/** Site içindeki adres karşılığı: "urunler/mil.jpg" -> "/images/uploads/urunler/mil.jpg" */
function bm_media_url_of($rel) {
    $rel = trim((string)$rel, '/');
    return '/images/uploads' . ($rel !== '' ? '/' . $rel : '');
}

/**
 * Göreli yolu güvenli hâle getirir.
 *
 * "../../inc/config.php" gibi denemeler, ters bölü, çift nokta ve boş parçalar
 * temizlenir. Dönen değer her zaman kökün altındadır; geçersizse '' döner.
 */
function bm_media_guvenli_yol($rel) {
    $rel = str_replace('\\', '/', (string)$rel);
    $parcalar = [];
    foreach (explode('/', $rel) as $p) {
        $p = trim($p);
        if ($p === '' || $p === '.' || $p === '..') continue;   // ".." asla ilerletilmez
        $parcalar[] = $p;
    }
    return implode('/', $parcalar);
}

/** Göreli yolun disk üzerindeki tam karşılığı; kök dışına çıkıyorsa null */
function bm_media_tam_yol($rel) {
    $rel = bm_media_guvenli_yol($rel);
    $tam = bm_media_root() . ($rel !== '' ? '/' . $rel : '');
    $kok = realpath(bm_media_root());
    if ($kok === false) return null;
    // Dosya henüz yoksa (yeni ad) üst klasörünü doğrularız
    $kontrol = file_exists($tam) ? realpath($tam) : realpath(dirname($tam));
    if ($kontrol === false) return null;
    if ($kontrol !== $kok && strpos($kontrol, $kok . DIRECTORY_SEPARATOR) !== 0) return null;
    return $tam;
}

/**
 * Klasör/dosya adını web adresinde kullanılabilir hâle getirir.
 *
 * DİKKAT — burada bilerek slug'a çevriliyor (Türkçe harfler sadeleşiyor,
 * boşluklar tireye dönüyor). Sebebi: bu adlar doğrudan görsel adresine giriyor
 * ("/images/uploads/urun-fotograflari/mil.jpg"). Boşluklu ve Türkçe karakterli
 * yollar sunucuya göre 404 verebiliyor — yüklenen dosya adları da aynı sebeple
 * zaten slug'lanıyor (upload-handler.php).
 *
 * "Ürün Fotoğrafları" -> "urun-fotograflari"
 */
function bm_media_temiz_ad($ad) {
    $ad = trim((string)$ad);
    $ad = str_replace(['/', '\\', "\0"], '', $ad);
    $ad = bm_slugify($ad);
    return trim($ad, '.-');
}

/** Kütüphanedeki bütün klasörler (kök dâhil), ağaç sırasında düz liste */
function bm_media_klasorler($rel = '', $derinlik = 0) {
    $out = [];
    $tam = bm_media_tam_yol($rel);
    if ($tam === null || !is_dir($tam)) return $out;

    $alt = [];
    foreach (scandir($tam) ?: [] as $ad) {
        if ($ad === '.' || $ad === '..') continue;
        if (!is_dir($tam . '/' . $ad)) continue;
        $alt[] = $ad;
    }
    natcasesort($alt);
    foreach ($alt as $ad) {
        $altRel = ($rel !== '' ? $rel . '/' : '') . $ad;
        $out[] = [
            'rel'      => $altRel,
            'ad'       => $ad,
            'derinlik' => $derinlik,
            'sayi'     => bm_media_dosya_sayisi($altRel),
        ];
        $out = array_merge($out, bm_media_klasorler($altRel, $derinlik + 1));
    }
    return $out;
}

/** Bir klasördeki dosya sayısı (alt klasörler hariç) */
function bm_media_dosya_sayisi($rel) {
    $tam = bm_media_tam_yol($rel);
    if ($tam === null || !is_dir($tam)) return 0;
    $n = 0;
    foreach (scandir($tam) ?: [] as $ad) {
        if ($ad === '.' || $ad === '..' || $ad[0] === '.') continue;
        if (is_file($tam . '/' . $ad)) $n++;
    }
    return $n;
}

/**
 * Bir klasördeki dosyalar.
 *
 * $q verilirse ARAMA bütün kütüphanede yapılır (klasör fark etmeksizin) —
 * "şu dosya nerede" sorusunun cevabı böyle bulunuyor.
 */
function bm_media_dosyalar($rel = '', $q = '', $altlarDahil = false) {
    $out = [];
    $tam = bm_media_tam_yol($rel);
    if ($tam === null || !is_dir($tam)) return $out;

    foreach (scandir($tam) ?: [] as $ad) {
        if ($ad === '.' || $ad === '..' || $ad[0] === '.') continue;
        $yol = $tam . '/' . $ad;
        $altRel = ($rel !== '' ? $rel . '/' : '') . $ad;
        if (is_dir($yol)) {
            if ($altlarDahil) $out = array_merge($out, bm_media_dosyalar($altRel, $q, true));
            continue;
        }
        if ($q !== '' && mb_stripos($ad, $q, 0, 'UTF-8') === false) continue;
        $out[] = [
            'rel'    => $altRel,
            'ad'     => $ad,
            'url'    => bm_media_url_of($altRel),
            'boyut'  => filesize($yol) ?: 0,
            'zaman'  => filemtime($yol) ?: 0,
            'klasor' => $rel,
        ];
    }
    usort($out, function ($a, $b) { return $b['zaman'] <=> $a['zaman']; });
    return $out;
}

/** Yeni klasör oluşturur. Dönüş: [başarılı mı, mesaj] */
function bm_media_klasor_ekle($ustRel, $ad) {
    $ad = bm_media_temiz_ad($ad);
    if ($ad === '') return [false, 'Klasör adı boş olamaz.'];
    $hedef = bm_media_tam_yol(($ustRel !== '' ? $ustRel . '/' : '') . $ad);
    if ($hedef === null) return [false, 'Geçersiz konum.'];
    if (file_exists($hedef)) return [false, '"' . $ad . '" zaten var.'];
    if (!@mkdir($hedef, 0775, true)) return [false, 'Klasör oluşturulamadı (yazma izni?).'];
    return [true, '"' . $ad . '" klasörü oluşturuldu.'];
}

/** Klasörü siler — yalnızca boşsa. İçi doluysa kullanıcıya dokunmadan uyarır. */
function bm_media_klasor_sil($rel) {
    $rel = bm_media_guvenli_yol($rel);
    if ($rel === '') return [false, 'Kök klasör silinemez.'];
    $tam = bm_media_tam_yol($rel);
    if ($tam === null || !is_dir($tam)) return [false, 'Klasör bulunamadı.'];

    $icerik = array_diff(scandir($tam) ?: [], ['.', '..']);
    if ($icerik) {
        return [false, 'Klasör boş değil — içinde ' . count($icerik)
                     . ' öğe var. Önce içindekileri taşı ya da sil.'];
    }
    if (!@rmdir($tam)) return [false, 'Klasör silinemedi.'];
    return [true, 'Klasör silindi.'];
}

/**
 * Klasörü yeniden adlandırır. İçindeki bütün dosyaların adresi değiştiği için
 * kullanan kayıtlar da güncellenir.
 */
function bm_media_klasor_adlandir($rel, $yeniAd) {
    $rel = bm_media_guvenli_yol($rel);
    if ($rel === '') return [false, 'Kök klasör yeniden adlandırılamaz.'];
    $yeniAd = bm_media_temiz_ad($yeniAd);
    if ($yeniAd === '') return [false, 'Klasör adı boş olamaz.'];

    $eski = bm_media_tam_yol($rel);
    if ($eski === null || !is_dir($eski)) return [false, 'Klasör bulunamadı.'];

    $ust = trim(dirname($rel), '.');
    $ust = ($ust === '' || $ust === '/') ? '' : $ust;
    $yeniRel = ($ust !== '' ? $ust . '/' : '') . $yeniAd;
    if ($yeniRel === $rel) return [true, 'Değişiklik yok.'];

    $yeni = bm_media_tam_yol($yeniRel);
    if ($yeni === null) return [false, 'Geçersiz ad.'];
    if (file_exists($yeni)) return [false, '"' . $yeniAd . '" zaten var.'];
    if (!@rename($eski, $yeni)) return [false, 'Klasör adı değiştirilemedi.'];

    // Klasör altındaki her adres değişti: /images/uploads/eski/... -> .../yeni/...
    $sayi = bm_media_baglanti_guncelle(bm_media_url_of($rel) . '/', bm_media_url_of($yeniRel) . '/', true);
    return [true, 'Klasör yeniden adlandırıldı.'
                . ($sayi ? ' ' . $sayi . ' bağlantı güncellendi.' : '')];
}

/** Dosyayı yeniden adlandırır (uzantı korunur) */
function bm_media_dosya_adlandir($rel, $yeniAd) {
    $rel = bm_media_guvenli_yol($rel);
    $eski = bm_media_tam_yol($rel);
    if ($eski === null || !is_file($eski)) return [false, 'Dosya bulunamadı.'];

    $uzanti = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    $yeniAd = bm_media_temiz_ad($yeniAd);
    if ($yeniAd === '') return [false, 'Dosya adı boş olamaz.'];
    // Kullanıcı uzantıyı da yazdıysa iki kez eklenmesin
    if ($uzanti !== '' && strtolower(pathinfo($yeniAd, PATHINFO_EXTENSION)) === $uzanti) {
        $yeniAd = pathinfo($yeniAd, PATHINFO_FILENAME);
    }
    $yeniAd = $yeniAd . ($uzanti !== '' ? '.' . $uzanti : '');

    $klasor = trim(dirname($rel), '.');
    $klasor = ($klasor === '' || $klasor === '/') ? '' : $klasor;
    $yeniRel = ($klasor !== '' ? $klasor . '/' : '') . $yeniAd;
    if ($yeniRel === $rel) return [true, 'Değişiklik yok.'];

    $yeni = bm_media_tam_yol($yeniRel);
    if ($yeni === null) return [false, 'Geçersiz ad.'];
    if (file_exists($yeni)) return [false, '"' . $yeniAd . '" adında bir dosya zaten var.'];
    if (!@rename($eski, $yeni)) return [false, 'Dosya adı değiştirilemedi.'];

    $sayi = bm_media_baglanti_guncelle(bm_media_url_of($rel), bm_media_url_of($yeniRel), false);
    return [true, 'Dosya yeniden adlandırıldı.'
                . ($sayi ? ' ' . $sayi . ' bağlantı güncellendi.' : '')];
}

/** Dosyayı başka klasöre taşır */
function bm_media_dosya_tasi($rel, $hedefKlasor) {
    $rel = bm_media_guvenli_yol($rel);
    $eski = bm_media_tam_yol($rel);
    if ($eski === null || !is_file($eski)) return [false, 'Dosya bulunamadı.'];

    $hedefKlasor = bm_media_guvenli_yol($hedefKlasor);
    $hedefTam = bm_media_tam_yol($hedefKlasor);
    if ($hedefTam === null || !is_dir($hedefTam)) return [false, 'Hedef klasör bulunamadı.'];

    $ad = basename($rel);
    $yeniRel = ($hedefKlasor !== '' ? $hedefKlasor . '/' : '') . $ad;
    if ($yeniRel === $rel) return [true, 'Dosya zaten bu klasörde.'];
    if (file_exists($hedefTam . '/' . $ad)) {
        return [false, 'Hedef klasörde aynı adda dosya var: ' . $ad];
    }
    if (!@rename($eski, $hedefTam . '/' . $ad)) return [false, 'Dosya taşınamadı.'];

    $sayi = bm_media_baglanti_guncelle(bm_media_url_of($rel), bm_media_url_of($yeniRel), false);
    return [true, 'Dosya taşındı.' . ($sayi ? ' ' . $sayi . ' bağlantı güncellendi.' : '')];
}

/** Dosyayı siler */
function bm_media_dosya_sil($rel) {
    $rel = bm_media_guvenli_yol($rel);
    $tam = bm_media_tam_yol($rel);
    if ($tam === null || !is_file($tam)) return [false, 'Dosya bulunamadı.'];
    if (!@unlink($tam)) return [false, 'Dosya silinemedi.'];
    return [true, 'Görsel silindi.'];
}

/**
 * Bir görsel adresi değiştiğinde onu kullanan bütün kayıtları günceller.
 *
 * $onEk true ise $eski bir klasör önekidir ("/images/uploads/logolar/") ve o
 * önekle başlayan bütün adresler yeni önekle değiştirilir.
 *
 * Dönüş: güncellenen kayıt sayısı.
 */
function bm_media_baglanti_guncelle($eski, $yeni, $onEk = false) {
    $sayac = 0;

    // 1) Ürünler ve makaleler (veritabanı)
    if (bm_db_ready()) {
        try {
            if ($onEk) {
                $st = bm_db()->prepare(
                    "UPDATE products SET image = CONCAT(?, SUBSTRING(image, ?))
                      WHERE image LIKE ?"
                );
                $st->execute([$yeni, strlen($eski) + 1, bm_escape_like($eski) . '%']);
            } else {
                $st = bm_db()->prepare('UPDATE products SET image = ? WHERE image = ?');
                $st->execute([$yeni, $eski]);
            }
            $sayac += $st->rowCount();
        } catch (Throwable $e) { /* sütun yoksa sessizce geç */ }

        try {
            if (bm_articles_ready()) {
                if ($onEk) {
                    $st = bm_db()->prepare(
                        "UPDATE articles SET image = CONCAT(?, SUBSTRING(image, ?))
                          WHERE image LIKE ?"
                    );
                    $st->execute([$yeni, strlen($eski) + 1, bm_escape_like($eski) . '%']);
                } else {
                    $st = bm_db()->prepare('UPDATE articles SET image = ? WHERE image = ?');
                    $st->execute([$yeni, $eski]);
                }
                $sayac += $st->rowCount();
                // Yazı gövdesine editörle eklenmiş görseller
                $st2 = bm_db()->prepare('UPDATE articles SET body = REPLACE(body, ?, ?) WHERE body LIKE ?');
                $st2->execute([$eski, $yeni, '%' . bm_escape_like($eski) . '%']);
                $sayac += $st2->rowCount();
            }
        } catch (Throwable $e) { /* makale tablosu yoksa geç */ }
    }

    // 2) content.json içindeki her yerde geçen adresler
    $content = bm_load_content();
    $degisti = 0;
    $gez = function (&$dugum) use (&$gez, $eski, $yeni, $onEk, &$degisti) {
        foreach ($dugum as $k => &$v) {
            if (is_array($v)) { $gez($v); continue; }
            if (!is_string($v) || $v === '') continue;
            if ($onEk) {
                if (strpos($v, $eski) === 0) { $v = $yeni . substr($v, strlen($eski)); $degisti++; }
            } elseif ($v === $eski) {
                $v = $yeni; $degisti++;
            } elseif (strpos($v, $eski) !== false && strpos($v, '<') !== false) {
                // Zengin metin içine gömülü görseller (sayfa içeriği)
                $v = str_replace($eski, $yeni, $v); $degisti++;
            }
        }
        unset($v);
    };
    $gez($content);
    if ($degisti) {
        bm_save_content($content);
        $sayac += $degisti;
    }

    return $sayac;
}
