<?php
/**
 * Beyli Makina — ortak yardımcı fonksiyonlar.
 * Site içeriği tek bir JSON dosyasında (content/content.json) tutulur.
 * Admin panel bu dosyayı okuyup yazarak siteyi günceller.
 */

define('BM_ROOT', dirname(__DIR__));
define('BM_CONTENT_FILE', BM_ROOT . '/content/content.json');

/**
 * Hata mesajları ziyaretçiye gösterilmesin.
 *
 * Bir dosya doğrudan çağrıldığında (ör. /inc/header.php) PHP hata metni
 * sunucudaki tam klasör yolunu ve kod satırlarını ekrana basıyor; bu, saldırgana
 * sitenin iç yapısını veriyor. Hatalar sunucu günlüğüne yazılmaya devam ediyor,
 * yalnızca ekrana basılması kapatılıyor. Localhost'ta geliştirme için açık.
 */
$bm_yerel = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
    || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;
if (!$bm_yerel) {
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');
}
unset($bm_yerel);

// Hizmet kartına ilk kez görsel konduğunda kullanılacak okunurluk ayarları.
// Ham fotoğrafın üstündeki beyaz yazı çoğu zaman okunmuyor; hafif bulanıklık
// ve kısılmış parlaklık güvenli bir başlangıç noktası. Panelden değiştirilir.
define('BM_CARD_BLUR_DEFAULT', 2);
define('BM_CARD_BRIGHT_DEFAULT', 65);

/**
 * İçerik önbelleğini tutan yardımcı.
 * Referans döndürdüğü için bm_content_set() ile dışarıdan da değiştirilebilir —
 * admin panelin "önizleme" özelliği, kaydetmeden önce içeriği geçici olarak
 * değiştirmek için bunu kullanıyor.
 */
function &bm_content_store() {
    static $store = null;
    return $store;
}

/** İçeriği oku (associative array olarak) */
function bm_load_content() {
    $store = &bm_content_store();
    if ($store !== null) return $store;
    if (!file_exists(BM_CONTENT_FILE)) {
        die('İçerik dosyası bulunamadı: content/content.json');
    }
    $json = file_get_contents(BM_CONTENT_FILE);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        die('İçerik dosyası okunamadı veya bozuk (content/content.json).');
    }
    $store = $data;
    return $store;
}

/** Bellekteki içeriği değiştir (diske YAZMAZ) */
function bm_content_set(array $data) {
    $store = &bm_content_store();
    $store = $data;
}

/** İçeriği kaydet (dosya kilidi ile) */
function bm_save_content($data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $fp = fopen(BM_CONTENT_FILE, 'c+');
    if (!$fp) return false;
    $ok = false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        $ok = fwrite($fp, $json) !== false;
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    // Önbelleği tazele: aksi halde kaydettikten sonra aynı istekte
    // bm_load_content() eski değerleri döndürüyor ve panel kaydedilmiş
    // veriyi değil eskisini gösteriyordu.
    if ($ok) bm_content_set($data);
    return $ok;
}

/**
 * Oturumu başlatır.
 *
 * Bazı paylaşımlı hostinglerde PHP'nin varsayılan oturum klasörüne yazma izni
 * olmuyor; bu durumda oturum sessizce çalışmıyor (girişte sürekli login
 * ekranına dönmek, form mesajlarının kaybolması gibi). Bu yüzden oturum
 * dosyalarını site içindeki yazılabilir klasöre alıyoruz. Admin ve ön yüz
 * aynı yolu kullanmalı.
 */
function bm_session_start() {
    if (session_status() !== PHP_SESSION_NONE) return;

    $dir = BM_ROOT . '/content/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
    }

    // secure: çerez yalnızca şifreli bağlantıda gönderilir. Aksi hâlde
    // http'ye düşen bir istekte oturum çerezi açık ağda görünebiliyor ve
    // yakalayan kişi panele girebiliyor.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,          // JavaScript çerezi okuyamaz
        'secure'   => $https,
        'samesite' => 'Lax',         // başka siteden gelen isteklerde gönderilmez
    ]);
    session_start();

    // Özel oturum klasörü temizliği:
    // Ubuntu/Debian/Plesk'te varsayılan cron bu özel dizini temizlemez.
    // ~50 istekte 1 kez çalışarak 24 saatten eski sess_* dosyalarını temizler.
    if (mt_rand(1, 50) === 1 && is_dir($dir)) {
        $sinir = time() - 86400;
        foreach (glob($dir . '/sess_*') ?: [] as $sessDosya) {
            if (is_file($sessDosya) && filemtime($sessDosya) < $sinir) {
                @unlink($sessDosya);
            }
        }
    }
}

/** "8M", "512K", "2G" gibi PHP ayar değerlerini bayta çevirir */
function bm_ini_bytes($val) {
    $val = trim((string)$val);
    if ($val === '') return 0;
    $unit = strtolower(substr($val, -1));
    $num = (float)$val;
    switch ($unit) {
        case 'g': $num *= 1024;
        case 'm': $num *= 1024;
        case 'k': $num *= 1024;
    }
    return (int)$num;
}

/**
 * Yüklenebilecek gerçek en büyük dosya boyutu (bayt).
 *
 * config.php'de yazan değer yeterli değil: sunucunun upload_max_filesize ve
 * post_max_size ayarları daha düşükse gerçek sınır onlardır. İkisinin küçüğü
 * alınır ki formda yazan rakam yalan olmasın.
 */
function bm_max_upload_bytes() {
    // bm_config() inc/db.php içinde; yalnızca functions.php yükleyen sayfalarda
    // tanımsız olabilir, o yüzden varlığı kontrol ediliyor.
    $mb = function_exists('bm_config') ? (int)(bm_config('form')['max_upload_mb'] ?? 15) : 15;
    $configured = $mb * 1024 * 1024;
    $limits = array_filter([
        bm_ini_bytes(ini_get('upload_max_filesize')),
        bm_ini_bytes(ini_get('post_max_size')),
    ]);
    return $limits ? min($configured, min($limits)) : $configured;
}

/** Yukarıdakinin MB cinsinden, gösterime uygun hâli */
function bm_max_upload_mb() {
    return max(1, (int)floor(bm_max_upload_bytes() / (1024 * 1024)));
}

/**
 * CSS/JS bağlantılarına sürüm damgası ekler.
 *
 * Sunucu bu dosyalara 7 günlük önbellek veriyor; adres değişmediği sürece
 * ziyaretçinin tarayıcısı yeni sürümü bir hafta boyunca istemiyor. Dosyanın
 * değiştirilme zamanını adrese ekleyince her güncellemede adres de değişiyor
 * ve tarayıcı dosyayı yeniden indiriyor.
 */
function bm_asset_version($path) {
    $file = BM_ROOT . '/' . ltrim($path, '/');
    return is_file($file) ? (string)filemtime($file) : '1';
}

/** Güvenli HTML çıktısı (metin alanları için) */
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Görsel/dosya adresini web adresine uygun hâle getirir.
 *
 * Kütüphanede boşluk ve Türkçe karakter içeren klasör adları olabiliyor
 * ("/images/uploads/Ürünler Deneme/mil.jpg"). Tarayıcılar bunu kendiliğinden
 * kodluyor ama araya giren her katman (bazı sunucular, önbellek/CDN, dış
 * araçlar) aynı hoşgörüyü göstermiyor ve görsel kırılıyor. Bu yüzden adresi
 * biz kodluyoruz.
 *
 * Yol parça parça kodlanır ki "/" ayraçları bozulmasın. Zaten kodlanmış bir
 * adres iki kez kodlanmaz (önce çözülüp yeniden kodlanıyor).
 */
function bm_src($yol) {
    $yol = trim((string)$yol);
    if ($yol === '') return '';
    // Dış adresler ve gömülü veriler olduğu gibi geçer
    if (preg_match('#^(https?:|data:|//)#i', $yol)) return $yol;

    $parcalar = explode('/', $yol);
    foreach ($parcalar as $i => $p) {
        if ($p === '') continue;                 // baştaki "/" korunur
        $parcalar[$i] = rawurlencode(rawurldecode($p));
    }
    return implode('/', $parcalar);
}

/**
 * Site yazısı (etiket) döndürür.
 *
 * Şablonlardaki sabit metinler buradan geçer. Panelde "Site Yazıları"
 * bölümünden değiştirilebilir; boş bırakılırsa koddaki varsayılan kullanılır.
 * Böylece yeni bir yazı eklediğimde panele elle alan açmak gerekmiyor —
 * varsayılanıyla çalışır, isteyen değiştirir.
 */
function bm_t($key, $default = '') {
    static $labels = null;
    if ($labels === null) {
        $c = bm_load_content();
        $labels = is_array($c['labels'] ?? null) ? $c['labels'] : [];
    }
    $v = $labels[$key] ?? '';
    return trim((string)$v) !== '' ? $v : $default;
}

/** Panelde listelenecek tüm site yazıları: anahtar => [grup, açıklama, varsayılan] */
function bm_label_catalog() {
    return [
        // --- Firma kimliği (arama motorlarına verilen bilgi) ---
        'site.name' => ['Firma Bilgisi', 'Resmî firma adı (Google için)', 'Beyli Makina Sanayi ve Ticaret Ltd. Şti.'],

        // --- Ana sayfa ---
        'home.services.tag'    => ['Ana Sayfa', 'Hizmetler — üst etiket', 'Hizmetlerimiz'],
        'home.services.title'  => ['Ana Sayfa', 'Hizmetler — başlık', 'Neler Yapıyoruz?'],
        'home.services.text'   => ['Ana Sayfa', 'Hizmetler — açıklama', 'Makine mühendisliği altyapımızla uçtan uca imalat çözümleri sunuyoruz.'],
        'home.services.more'   => ['Ana Sayfa', 'Hizmet kartı bağlantı yazısı', 'Detayları Gör →'],
        'home.catalog.tag'     => ['Ana Sayfa', 'Vitrin — üst etiket', 'Ürün Kataloğu'],
        'home.catalog.title'   => ['Ana Sayfa', 'Vitrin — başlık', 'Ürettiğimiz Parçalar'],
        'home.catalog.all'     => ['Ana Sayfa', 'Tüm ürünler butonu', 'Tüm Ürünleri Gör'],
        'home.catalog.hint'    => ['Ana Sayfa', 'Vitrin altı küçük yazı', 'Aradığınız parçayı isim ya da kod ile arayabilirsiniz.'],

        // --- Menü ---
        'nav.contact'          => ['Menü', '"İletişim" menü öğesi', 'İletişim'],
        'nav.all_products'     => ['Menü', 'Ürünler açılır listesindeki ilk öğe', 'Tüm Ürünler'],
        'nav.more'             => ['Menü', 'Sığmayan öğelerin toplandığı başlık', 'Diğer'],
        'nav.cta'              => ['Menü', 'Sağ üstteki buton', 'Teklif Al'],

        // --- Blog / makaleler ---
        'blog.search_ph'       => ['Blog', 'Arama kutusu yazısı', 'Makalelerde ara…'],
        'blog.all'             => ['Blog', '"Tümü" filtresi', 'Tümü'],
        'blog.empty'           => ['Blog', 'Sonuç yok — yazı', 'Bu aramaya uyan makale bulunamadı.'],
        'blog.soon'            => ['Blog', 'Hiç makale yokken görünen yazı', 'Makaleler yakında burada olacak.'],
        'blog.read_more'       => ['Blog', 'Kart üzerindeki bağlantı', 'Devamını oku →'],
        'blog.reading_time'    => ['Blog', 'Okuma süresi rozeti (%s = dakika)', '%s dk okuma'],
        'article.cta_title'    => ['Makale Sayfası', 'Alt kutu — başlık', 'Bu konuda bir parçaya mı ihtiyacın var?'],
        'article.cta_text'     => ['Makale Sayfası', 'Alt kutu — açıklama', 'Teknik çizimini ya da numunesini ilet, sana özel teklif hazırlayalım.'],
        'article.cta_btn'      => ['Makale Sayfası', 'Alt kutu — buton', 'Teklif İste'],
        'article.related_tag'  => ['Makale Sayfası', 'Benzer yazılar — üst etiket', 'Devamı'],
        'article.related_title'=> ['Makale Sayfası', 'Benzer yazılar — başlık', 'Bunlar da İlgini Çekebilir'],

        // --- Ürünler sayfası ---
        'products.tag'         => ['Ürünler Sayfası', 'Üst etiket', 'Ürün Kataloğu'],
        'products.title'       => ['Ürünler Sayfası', 'Başlık', 'Ürünlerimiz'],
        'products.text'        => ['Ürünler Sayfası', 'Açıklama', 'Ürettiğimiz parçaları kategoriye göre inceleyin ya da isim/kod ile arayın.'],
        'products.search_ph'   => ['Ürünler Sayfası', 'Arama kutusu yazısı', 'Ürün adı veya parça kodu ile ara — örn. BM-TO-0042'],
        'products.search_btn'  => ['Ürünler Sayfası', 'Arama butonu', 'Ara'],
        'products.clear'       => ['Ürünler Sayfası', 'Temizle butonu', 'Temizle'],
        'products.all'         => ['Ürünler Sayfası', '"Tümü" filtresi', 'Tümü'],
        'products.empty_title' => ['Ürünler Sayfası', 'Sonuç yok — başlık', 'Aradığınız ürünü bulamadık'],
        'products.empty_text'  => ['Ürünler Sayfası', 'Sonuç yok — açıklama', 'Farklı bir kelime deneyin ya da tüm kataloğa göz atın. Aradığınız parça listede yoksa da üretebiliriz — teklif formundan teknik çizimi iletmeniz yeterli.'],
        'products.card_cta'    => ['Ürünler Sayfası', 'Kart üzerindeki bağlantı', 'Detay & Teklif'],

        // --- Ürün detay ---
        'product.specs'        => ['Ürün Detayı', 'Teknik özellikler başlığı', 'Teknik Özellikler'],
        'product.cta'          => ['Ürün Detayı', 'Teklif butonu', 'Bu Parça İçin Teklif İste'],
        'product.similar_btn'  => ['Ürün Detayı', 'Benzer ürünler butonu', 'Benzer Ürünler'],
        'product.cats_label'   => ['Ürün Sayfası', 'Kategoriler başlığı', 'Kategoriler'],
        'product.cats_hint'    => ['Ürün Sayfası', 'Kategori yolları altındaki açıklama', 'Koyu renkli kategoriler parçanın doğrudan yer aldığı gruplardır.'],
        'product.note'         => ['Ürün Sayfası', 'Bilgi kutusu metni', 'Bu parçayı farklı ölçü, malzeme ve adette de üretebiliriz. Teknik çizimini ya da numunesini iletin, size özel teklif hazırlayalım.'],
        'product.quick_label'  => ['Ürün Sayfası', 'Hızlı iletişim başlığı', 'Hemen sorun:'],
        'product.similar_tag'  => ['Ürün Detayı', 'Alt bölüm etiketi', 'Aynı Kategoriden'],
        'product.similar_title'=> ['Ürün Detayı', 'Alt bölüm başlığı', 'Benzer Parçalar'],
        'product.code_label'   => ['Ürün Detayı', 'Parça kodu etiketi', 'Parça Kodu:'],

        // --- Hizmet detay ---
        'service.machines_tag'   => ['Hizmet Sayfası', 'Makineler — üst etiket', 'Makine Parkuru'],
        'service.machines_title' => ['Hizmet Sayfası', 'Makineler — başlık', 'Bu Hizmette Kullandığımız Makineler'],
        'service.machines_text'  => ['Hizmet Sayfası', 'Makineler — açıklama', 'Teknik kapasitelerimizi aşağıda bulabilirsiniz.'],
        'service.cta_tag'        => ['Hizmet Sayfası', 'Alt kutu etiketi', 'Birlikte Çalışalım'],
        'service.cta_btn'        => ['Hizmet Sayfası', 'Alt kutu butonu', 'Teklif Formuna Git →'],
        'service.others_tag'     => ['Hizmet Sayfası', 'Diğer hizmetler etiketi', 'Diğer Hizmetler'],
        'service.others_title'   => ['Hizmet Sayfası', 'Diğer hizmetler başlığı', 'Neler Yapıyoruz?'],

        // --- Teklif formu ---
        'form.fullname'   => ['Teklif Formu', 'Ad soyad etiketi', 'Ad Soyad'],
        'form.phone'      => ['Teklif Formu', 'Telefon etiketi', 'Telefon'],
        'form.email'      => ['Teklif Formu', 'E-posta etiketi', 'E-Posta'],
        'form.parttype'   => ['Teklif Formu', 'Parça türü etiketi', 'Parça / Hizmet Türü'],
        'form.details'    => ['Teklif Formu', 'Detaylar etiketi', 'Sipariş Detayları'],
        'form.details_ph' => ['Teklif Formu', 'Detaylar kutusu yazısı', 'Malzeme, adet, ölçü, tolerans ve diğer teknik detayları buraya yazınız...'],
        'form.file'       => ['Teklif Formu', 'Dosya etiketi', 'Teknik Çizim / Dosya'],
        'form.submit'     => ['Teklif Formu', 'Gönder butonu', 'Teklif Talebini Gönder ✉️'],

        // --- Ana sayfa teklif kutusu / CMS sayfa altı ---
        'cta.button'      => ['Teklif Kutusu', 'Formuna git butonu', 'Teklif Formuna Git →'],
        'cta.page_tag'    => ['Teklif Kutusu', 'CMS sayfa altı etiketi', 'Birlikte Çalışalım'],
        'cta.page_title'  => ['Teklif Kutusu', 'CMS sayfa altı başlığı', 'Projeniz İçin Teklif Alın'],
        'cta.page_text'   => ['Teklif Kutusu', 'CMS sayfa altı açıklaması', 'Parçanızın teknik detaylarını iletin, size en kısa sürede dönüş yapalım.'],
        'cta.page_btn'    => ['Teklif Kutusu', 'CMS sayfa altı butonu', 'Özel Sipariş Formuna Git →'],

        // --- Ana sayfa vitrini (sonradan açılan sabit metinler) ---
        'home.catalog.text_many' => ['Ana Sayfa', 'Vitrin açıklaması — çok ürün varken (%s = toplam ürün sayısı)', 'Kataloğumuzdan öne çıkan parçalar — toplam %s ürün arasından seçildi.'],
        'home.catalog.text_few'  => ['Ana Sayfa', 'Vitrin açıklaması — az ürün varken', 'Detaylı teknik bilgileriyle örnek üretim parçalarımız'],
        'home.catalog.no_cat'    => ['Ana Sayfa', 'Kategorisi olmayan ürünün kart etiketi', 'Ürün'],

        // --- Ürünler sayfası (sol filtre paneli ve liste) ---
        'products.filters'      => ['Ürünler Sayfası', 'Filtre paneli başlığı', 'Filtreler'],
        'products.cats_title'   => ['Ürünler Sayfası', 'Kategori listesi başlığı', 'Kategoriler'],
        'products.no_cats'      => ['Ürünler Sayfası', 'Hiç kategori yokken', 'Henüz kategori yok.'],
        'products.subcats'      => ['Ürünler Sayfası', 'Alt kategori sayısı (%s = adet)', '%s alt kategori'],
        'products.result_none'  => ['Ürünler Sayfası', 'Sonuç sayısı — hiç yokken', 'Sonuç bulunamadı.'],
        'products.search_for'   => ['Ürünler Sayfası', 'Arama sonucu eki', 'araması'],
        'products.page_label'   => ['Ürünler Sayfası', 'Sayfa numarası etiketi', 'Sayfa'],
        'products.clear_all'    => ['Ürünler Sayfası', 'Seçili filtreleri temizle bağlantısı', 'Tümünü temizle'],
        'products.count_word'   => ['Ürünler Sayfası', 'Sonuç sayısından sonraki kelime', 'ürün'],
        'products.search_aria'  => ['Ürünler Sayfası', 'Arama kutusunun ekran okuyucu adı', 'Ürün ara'],
        'products.all_btn'      => ['Ürünler Sayfası', 'Sonuç yok — tüm ürünler butonu', 'Tüm Ürünler'],
        'products.custom_btn'   => ['Ürünler Sayfası', 'Sonuç yok — özel sipariş butonu', 'Özel Sipariş Ver'],

        // --- Sayfalama (ürünler ve blog) ---
        // NOT: Footer sütun başlıkları bilerek bu listede DEĞİL. Onlar
        // "Menü & Alt Bilgi" ekranından yönetiliyor ve orada girilen değer
        // her zaman kazanıyor; burada da alan açsaydım kullanıcı buradan
        // değiştirip "çalışmıyor" derdi. footer.php yine bm_t() ile
        // varsayılanı okuyor, sadece panelde iki yerde görünmüyor.

        'pager.prev' => ['Sayfalama', 'Önceki sayfa bağlantısı', '← Önceki'],
        'pager.next' => ['Sayfalama', 'Sonraki sayfa bağlantısı', 'Sonraki →'],

        // --- Konum çubuğu ---
        'nav.home'      => ['Menü', 'Konum çubuğundaki "Ana Sayfa"', 'Ana Sayfa'],
        'nav.products'  => ['Menü', 'Konum çubuğundaki "Ürünler"', 'Ürünler'],

        // --- Bulunamadı (404) sayfaları ---
        'nf.product_title' => ['Bulunamadı Sayfaları', 'Ürün — başlık', 'Ürün Bulunamadı'],
        'nf.product_text'  => ['Bulunamadı Sayfaları', 'Ürün — açıklama', 'Bu ürün kaldırılmış ya da adres hatalı olabilir.'],
        'nf.product_btn'   => ['Bulunamadı Sayfaları', 'Ürün — buton', 'Ürün Kataloğuna Dön'],
        'nf.page_title'    => ['Bulunamadı Sayfaları', 'Sayfa — başlık', 'Sayfa Bulunamadı'],
        'nf.page_text'     => ['Bulunamadı Sayfaları', 'Sayfa — açıklama', 'Aradığınız sayfa kaldırılmış ya da hiç var olmamış olabilir.'],
        'nf.page_btn'      => ['Bulunamadı Sayfaları', 'Sayfa — buton', 'Ana Sayfaya Dön'],
        'nf.service_title' => ['Bulunamadı Sayfaları', 'Hizmet — başlık', 'Hizmet Bulunamadı'],
        'nf.service_text'  => ['Bulunamadı Sayfaları', 'Hizmet — açıklama', 'Bu sayfa kaldırılmış ya da adres hatalı olabilir.'],
        'nf.service_btn'   => ['Bulunamadı Sayfaları', 'Hizmet — buton', 'Hizmetlerimize Dön'],
        'nf.article_title' => ['Bulunamadı Sayfaları', 'Makale — başlık', 'Makale Bulunamadı'],
        'nf.article_text'  => ['Bulunamadı Sayfaları', 'Makale — açıklama', 'Bu yazı kaldırılmış ya da adres hatalı olabilir.'],
        'nf.article_btn'   => ['Bulunamadı Sayfaları', 'Makale — buton', 'Bütün Makaleler'],

        // --- Teklif formu (sonradan açılan sabit metinler) ---
        'form.file_optional' => ['Teklif Formu', 'Dosya etiketi yanındaki not', '(isteğe bağlı)'],
        'form.file_hint'     => ['Teklif Formu', 'Dosya alanı açıklaması (%s = sunucu sınırı, ör. 10 MB)', 'PDF, DWG, DXF, STEP, resim, Excel veya sıkıştırılmış dosya — en fazla %s.'],
        'form.website'       => ['Teklif Formu', 'Gizli alan etiketi (spam tuzağı)', 'Web sitesi'],

        // --- Hizmet sayfası (sonradan açılan sabit metinler) ---
        'service.cta_title' => ['Hizmet Sayfası', 'Alt kutu başlığı (%s = hizmet adı)', '%s için teklif alın'],
        'service.cta_text'  => ['Hizmet Sayfası', 'Alt kutu açıklaması', 'Parçanızın teknik çizimini ve detaylarını iletin, size en kısa sürede dönüş yapalım.'],

        // --- Blog (sonradan açılan sabit metinler) ---
        'blog.clear'     => ['Blog', 'Temizle butonu', 'Temizle'],
        'blog.all_btn'   => ['Blog', 'Sonuç yok — bütün makaleler butonu', 'Bütün Makaleler'],
        'article.reading_long' => ['Makale Sayfası', 'Okuma süresi yazısı (%s = dakika)', '%s dakika okuma'],

        // --- Teklif formu: kutu içindeki soluk örnek yazılar ---
        'form.fullname_ph' => ['Teklif Formu', 'Ad soyad kutusundaki örnek yazı', 'Adınız Soyadınız'],
        'form.phone_ph'    => ['Teklif Formu', 'Telefon kutusundaki örnek yazı', '05XX XXX XX XX'],
        'form.email_ph'    => ['Teklif Formu', 'E-posta kutusundaki örnek yazı', 'ornek@mail.com'],

        // --- Teklif formu: gönderimden sonra çıkan mesajlar ---
        'form.msg_ok'       => ['Form Mesajları', 'Başarılı gönderim', 'Teklif talebiniz bize ulaştı. En kısa sürede size dönüş yapacağız.'],
        'form.msg_send_err' => ['Form Mesajları', 'Mail gönderilemedi', 'Talebiniz şu anda gönderilemedi. Lütfen biraz sonra tekrar deneyin ya da doğrudan e-posta ile ulaşın.'],
        'form.msg_throttle' => ['Form Mesajları', 'Çok sık gönderim uyarısı (%s = kalan saniye)', 'Az önce bir talep gönderdiniz. Lütfen %s saniye sonra tekrar deneyin.'],
        'form.err_name'     => ['Form Mesajları', 'Ad soyad boş', 'Ad soyad alanı boş bırakılamaz.'],
        'form.err_contact'  => ['Form Mesajları', 'Telefon ve e-posta ikisi de boş', 'Size dönebilmemiz için e-posta ya da telefon bilgisi gerekli.'],
        'form.err_email'    => ['Form Mesajları', 'E-posta hatalı', 'E-posta adresi geçerli görünmüyor.'],
        'form.err_details'  => ['Form Mesajları', 'Sipariş detayı boş', 'Lütfen sipariş detaylarını yazın.'],

        // --- Teklif formu: dosya eki hataları ---
        'form.file_err_big'     => ['Form Dosya Hataları', 'Dosya çok büyük (%s = sınır, ör. 10 MB)', 'Dosya çok büyük (en fazla %s).'],
        'form.file_err_ini'     => ['Form Dosya Hataları', 'Sunucu sınırını aştı (%s = sınır)', 'Dosya sunucunun izin verdiği boyutu aşıyor (en fazla %s).'],
        'form.file_err_partial' => ['Form Dosya Hataları', 'Yükleme yarıda kaldı', 'Dosya yalnızca kısmen yüklendi, tekrar deneyin.'],
        'form.file_err_tmp'     => ['Form Dosya Hataları', 'Geçici klasör yok', 'Sunucuda geçici klasör bulunamadı (hosting ayarı).'],
        'form.file_err_write'   => ['Form Dosya Hataları', 'Diske yazılamadı', 'Dosya diske yazılamadı (sunucu izin sorunu).'],
        'form.file_err_ext'     => ['Form Dosya Hataları', 'Sunucu eklentisi engelledi', 'Bir sunucu eklentisi yüklemeyi durdurdu.'],
        'form.file_err_other'   => ['Form Dosya Hataları', 'Bilinmeyen yükleme hatası', 'Dosya yüklenemedi.'],
        'form.file_err_type'    => ['Form Dosya Hataları', 'Kabul edilmeyen dosya türü (%s = izin verilen uzantılar)', 'Bu dosya türü kabul edilmiyor. İzin verilenler: %s.'],
        'form.file_err_save'    => ['Form Dosya Hataları', 'Kaydedilemedi', 'Dosya kaydedilemedi. Lütfen tekrar deneyin.'],

        // --- Ekranda görünmeyen etiketler (ekran okuyucular ve arama motorları için) ---
        'a11y.menu'       => ['Görünmeyen Etiketler', 'Hamburger menü düğmesi', 'Menü'],
        'a11y.home'       => ['Görünmeyen Etiketler', 'Mobil menüdeki logo bağlantısı', 'Ana sayfa'],
        'a11y.pagination' => ['Görünmeyen Etiketler', 'Sayfalama çubuğu', 'Sayfalar'],
        'a11y.breadcrumb' => ['Görünmeyen Etiketler', 'Konum çubuğu (Ana Sayfa › Ürünler › …)', 'Konum'],
        'a11y.slide'      => ['Görünmeyen Etiketler', 'Slayt noktaları (%s = kaçıncı görsel)', '%s. görsel'],

        // --- Arama motoru varsayılanları (sayfanın kendi başlığı yoksa kullanılır) ---
        'seo.title'      => ['Arama Motoru', 'Varsayılan sayfa başlığı (tarayıcı sekmesi)', 'Beyli Makina | CNC Torna & Freze İmalat'],
        'seo.desc'       => ['Arama Motoru', 'Varsayılan sayfa açıklaması (Google\'da başlık altı)', 'Kahramanmaraş Organize Sanayi Bölgesi\'nde hassas CNC torna ve freze imalatı.'],
        'seo.blog_desc'  => ['Arama Motoru', 'Blog sayfası açıklaması (blog ekranında yazılmamışsa)', 'Beyli Makina mühendislerinden CNC torna, freze ve imalat üzerine makaleler.'],

        // --- Teklif formu: bireysel / kurumsal seçimi ---
        'form.type_individual' => ['Kurumsal Form', 'Bireysel sekmesinin adı', 'Bireysel'],
        'form.type_corporate'  => ['Kurumsal Form', 'Kurumsal sekmesinin adı', 'Kurumsal'],
        'form.type_hint'       => ['Kurumsal Form', 'Sekmelerin altındaki açıklama', 'Firma adına teklif istiyorsanız Kurumsal’ı seçin; fatura bilgilerinizi alalım.'],
        'form.corporate_title' => ['Kurumsal Form', 'Kurumsal alanların başlığı', 'Firma Bilgileri'],
        'form.company'         => ['Kurumsal Form', 'Firma ünvanı alanı', 'Firma Ünvanı'],
        'form.company_ph'      => ['Kurumsal Form', 'Firma ünvanı örnek yazısı', 'Örn. Örnek Makina San. ve Tic. Ltd. Şti.'],
        'form.taxoffice'       => ['Kurumsal Form', 'Vergi dairesi alanı', 'Vergi Dairesi'],
        'form.taxoffice_ph'    => ['Kurumsal Form', 'Vergi dairesi örnek yazısı', 'Örn. Kahramanmaraş'],
        'form.taxno'           => ['Kurumsal Form', 'Vergi no alanı', 'Vergi No / TCKN'],
        'form.taxno_ph'        => ['Kurumsal Form', 'Vergi no örnek yazısı', '10 haneli vergi no veya 11 haneli TC kimlik no'],
        'form.taxno_hint'      => ['Kurumsal Form', 'Vergi no altındaki açıklama', 'Şahıs firmaları TC kimlik numarası yazabilir.'],
        'form.taxno_warn'      => ['Kurumsal Form', 'Vergi no hatalıysa çıkan uyarı (engellemez)', 'Bu numara hatalı görünüyor, kontrol eder misiniz?'],
        'form.taxno_foreign'   => ['Kurumsal Form', 'Yurt dışı firma seçilince vergi no alanının adı', 'Vergi / VAT Numarası'],
        'form.role'            => ['Kurumsal Form', 'Görev alanı', 'Görev / Departman'],
        'form.role_ph'         => ['Kurumsal Form', 'Görev örnek yazısı', 'Örn. Satınalma'],
        'form.country'         => ['Kurumsal Form', 'Ülke alanı', 'Ülke'],
        'form.city'            => ['Kurumsal Form', 'İl alanı', 'İl / Şehir'],
        'form.district'        => ['Kurumsal Form', 'İlçe alanı', 'İlçe'],
        'form.address'         => ['Kurumsal Form', 'Adres alanı', 'Açık Adres (fatura adresi)'],
        'form.address_ph'      => ['Kurumsal Form', 'Adres örnek yazısı', 'Mahalle, cadde, kapı no, posta kodu'],
        'form.company_web'     => ['Kurumsal Form', 'Web sitesi alanı', 'Web Sitesi'],
        'form.qty'             => ['Kurumsal Form', 'Adet alanı', 'Tahmini Adet'],
        'form.qty_ph'          => ['Kurumsal Form', 'Adet örnek yazısı', 'Örn. 250'],
        'form.deadline'        => ['Kurumsal Form', 'Termin alanı', 'İstenen Termin'],
        'form.pono'            => ['Kurumsal Form', 'Satınalma sipariş no alanı', 'Satınalma Sipariş No'],
        'form.optional'        => ['Kurumsal Form', 'İsteğe bağlı alanların yanındaki yazı', '(isteğe bağlı)'],
        'form.required_hint'   => ['Kurumsal Form', 'Formun altındaki zorunlu alan notu', '* işaretli alanlar zorunludur.'],

        // --- Teklif formu: telefon alanı ---
        'form.phone_country'   => ['Teklif Formu', 'Telefon ülke kodu seçici etiketi', 'Ülke kodu'],
        'form.phone_search'    => ['Teklif Formu', 'Ülke arama kutusunun örnek yazısı', 'Ülke ara…'],
        'form.phone_none'      => ['Teklif Formu', 'Ülke araması sonuçsuzsa çıkan yazı', 'Sonuç bulunamadı'],

        // --- Teklif formu: KVKK onayları ---
        'form.kvkk_text'       => ['Kurumsal Form', 'Aydınlatma metni onay cümlesi (%s = bağlantı)', '%s’ni okudum.'],
        'form.kvkk_link'       => ['Kurumsal Form', 'Aydınlatma metni bağlantı yazısı', 'Aydınlatma Metni'],
        'form.consent_text'    => ['Kurumsal Form', 'İsteğe bağlı ticari ileti onayı', 'Kampanya ve duyurulardan haberdar olmak istiyorum.'],

        // --- Teklif formu: yeni hata mesajları ---
        'form.err_kvkk'      => ['Form Mesajları', 'Aydınlatma metni onaylanmadı', 'Devam etmek için aydınlatma metni onayı gerekli.'],
        'form.err_phone'     => ['Form Mesajları', 'Telefon numarası geçersiz', 'Telefon numarası hatalı görünüyor, kontrol eder misiniz?'],
        'form.err_company'   => ['Form Mesajları', 'Firma ünvanı boş', 'Firma ünvanı boş bırakılamaz.'],
        'form.err_taxoffice' => ['Form Mesajları', 'Vergi dairesi boş', 'Vergi dairesi boş bırakılamaz.'],
        'form.err_taxno'     => ['Form Mesajları', 'Vergi no boş', 'Vergi numarası boş bırakılamaz.'],
        'form.err_country'   => ['Form Mesajları', 'Ülke seçilmedi', 'Lütfen ülke seçin.'],
        'form.err_city'      => ['Form Mesajları', 'İl boş', 'İl / şehir boş bırakılamaz.'],
        'form.err_district'  => ['Form Mesajları', 'İlçe boş', 'İlçe boş bırakılamaz.'],
        'form.err_address'   => ['Form Mesajları', 'Adres boş', 'Açık adres boş bırakılamaz.'],
        'form.err_role'      => ['Form Mesajları', 'Görev boş', 'Görev / departman boş bırakılamaz.'],
        'form.err_web'       => ['Form Mesajları', 'Web sitesi boş', 'Web sitesi boş bırakılamaz.'],
        'form.err_qty'       => ['Form Mesajları', 'Adet boş', 'Tahmini adet boş bırakılamaz.'],
        'form.err_deadline'  => ['Form Mesajları', 'Termin boş', 'İstenen termin boş bırakılamaz.'],
        'form.err_pono'      => ['Form Mesajları', 'Sipariş no boş', 'Satınalma sipariş no boş bırakılamaz.'],
    ];
}

/**
 * Sayfa başlığı bandının (page-hero) arka plan görseli için stil üretir.
 *
 * $focus "50% 40%" biçiminde odak noktasıdır — görselin hangi kısmının
 * ortada kalacağını belirler. Panelde görsele tıklayarak seçiliyor; boş
 * bırakılırsa merkez kullanılır.
 */
function bm_hero_style($image, $focus = '') {
    $image = trim((string)$image);
    if ($image === '') return '';
    $focus = trim((string)$focus);
    // Sadece "sayı% sayı%" kabul edilir; başka bir şey gelirse merkeze düşer
    if (!preg_match('/^\d{1,3}(\.\d+)?%\s+\d{1,3}(\.\d+)?%$/', $focus)) {
        $focus = '50% 50%';
    }
    return ' style="background-image:url(\'' . e(bm_src($image)) . '\');background-position:' . e($focus) . ';"';
}

/**
 * Panelden gelen sayıyı belirli bir aralığa sıkıştırır.
 * Boş, sayı olmayan ya da aralık dışı değerler varsayılana düşer — bu değerler
 * doğrudan CSS'e yazıldığı için aralığın dışına çıkmamaları gerekiyor.
 */
function bm_num_range($value, $min, $max, $default) {
    if ($value === null || $value === '' || !is_numeric($value)) return $default;
    return max($min, min($max, (int)$value));
}

/**
 * Hizmet kartının arkasındaki görselin stil özniteliği.
 *
 * Kart küçük bir sayfa başlığı bandı gibi çalışıyor: arkada fotoğraf, önde
 * yazı. Fotoğrafın deseni yazıyı yutabildiği için bulanıklık ve parlaklık
 * panelden ayarlanıyor — okunurluk her fotoğrafta ayrı dengeleniyor.
 */
function bm_card_bg_style($image, $focus = '', $blur = null, $bright = null) {
    $image = trim((string)$image);
    if ($image === '') return '';
    $focus = trim((string)$focus);
    if (!preg_match('/^\d{1,3}(\.\d+)?%\s+\d{1,3}(\.\d+)?%$/', $focus)) {
        $focus = '50% 50%';
    }
    $blur   = bm_num_range($blur, 0, 20, BM_CARD_BLUR_DEFAULT);
    $bright = bm_num_range($bright, 20, 130, BM_CARD_BRIGHT_DEFAULT);
    return ' style="background-image:url(\'' . e(bm_src($image)) . '\');'
         . 'background-position:' . e($focus) . ';'
         . 'filter:blur(' . $blur . 'px) brightness(' . $bright . '%);"';
}

/** Menüde görünen sayfaları sıraya göre döndür */
function bm_nav_pages($content) {
    $pages = $content['pages'] ?? [];
    $pages = array_filter($pages, function ($p) { return !empty($p['in_nav']); });
    usort($pages, function ($a, $b) { return ($a['nav_order'] ?? 0) <=> ($b['nav_order'] ?? 0); });
    return array_values($pages);
}

/**
 * Üst menünün sayfa bölümü — gruplanmış hâliyle.
 *
 * Panelde bir sayfaya "menü grubu" yazılırsa, aynı grup adını taşıyan sayfalar
 * üst menüde tek bir açılır listenin altında toplanır (Hizmetlerimiz gibi).
 * Grubun menüdeki yeri, o gruba ait ilk sayfanın sırasıyla belirlenir.
 *
 * Dönüş: [ ['type'=>'page','page'=>[...]], ['type'=>'group','label'=>'Diğer','pages'=>[...]], ... ]
 */
function bm_nav_tree($content) {
    $out = [];
    $konum = [];
    foreach (bm_nav_pages($content) as $p) {
        $grup = trim((string)($p['nav_group'] ?? ''));
        if ($grup === '') {
            $out[] = ['type' => 'page', 'page' => $p];
            continue;
        }
        if (!isset($konum[$grup])) {
            $konum[$grup] = count($out);
            $out[] = ['type' => 'group', 'label' => $grup, 'pages' => []];
        }
        $out[$konum[$grup]]['pages'][] = $p;
    }
    return $out;
}

/**
 * Panelden yönetilen bir buton listesini okur.
 *
 * Site genelinde üç yerde aynı yapı kullanılıyor: teklif sayfasındaki iletişim
 * kutusu, mobil menü paneli ve footer'daki sosyal butonlar. Hepsi tek bir
 * biçimi paylaşıyor ki panelde de aynı arayüzle yönetilebilsinler.
 *
 * Bir buton: icon (emoji), image (PNG yolu), label, text, type, value
 *
 * $path  content.json içindeki yol, ör. "footer.social_buttons"
 * $varsayilan  Liste hiç tanımlanmamışsa kullanılacak dizi
 */
function bm_button_list($content, $path, $varsayilan = []) {
    $node = $content;
    foreach (explode('.', $path) as $k) {
        if (!is_array($node) || !array_key_exists($k, $node)) { $node = null; break; }
        $node = $node[$k];
    }
    if (!is_array($node)) return $varsayilan;

    return array_values(array_filter($node, function ($b) {
        return trim((string)($b['text'] ?? '')) !== ''
            || trim((string)($b['label'] ?? '')) !== ''
            || trim((string)($b['image'] ?? '')) !== '';
    }));
}

/**
 * Bir butonun simgesini basar: PNG yüklenmişse onu, yoksa emojiyi.
 * $sinif ile kapsayıcı sınıf adı verilir (ör. "ic").
 */
function bm_button_icon($btn, $sinif = 'ic') {
    $image = trim((string)($btn['image'] ?? ''));
    $icon  = trim((string)($btn['icon'] ?? ''));
    $alt   = trim((string)($btn['label'] ?? $btn['text'] ?? ''));

    // Görsel kütüphaneden silinmişse butonun içi kırık resim simgesiyle
    // kalmasın: dosya yoksa emojiye geri düşülüyor. Yalnızca site içi yollar
    // kontrol ediliyor, dış adresler olduğu gibi basılıyor.
    if ($image !== '' && $image[0] === '/' && !file_exists(BM_ROOT . $image)) {
        $image = '';
    }

    if ($image !== '') {
        return '<span class="' . e($sinif) . ' has-img"><img src="' . e(bm_src($image))
             . '" alt="' . e($alt) . '" loading="lazy"></span>';
    }
    if ($icon !== '') {
        return '<span class="' . e($sinif) . '">' . e($icon) . '</span>';
    }
    return '';
}

/**
 * Teklif sayfasındaki sosyal medya butonları.
 * İletişim butonlarının altında yan yana durur; varsayılanı yoktur — panelden
 * eklenmediyse bölüm sitede hiç görünmez.
 */
function bm_quote_social_buttons($content) {
    return bm_button_list($content, 'order_section.social_buttons');
}

/**
 * Site genelindeki sabit butonların simgesi (telefon, WhatsApp, e-posta, adres).
 *
 * Panelden PNG yüklenmişse onu basar, yüklenmemişse verilen emojiye düşer —
 * böylece ikon yüklenene kadar buton simgesiz/boş görünmüyor.
 */
function bm_site_icon($site, $key, $emojiYedek = '') {
    return bm_button_icon([
        'image' => $site[$key] ?? '',
        'icon'  => $emojiYedek,
    ]);
}

/** Sitenin kök adresi (https://alanadi.com) — mutlak adres gereken yerler için */
function bm_site_url() {
    $sema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'www.beylimakina.com';
    return $sema . '://' . $host;
}

/**
 * Arama motorlarına firma kimliğini bildiren yapısal veri (JSON-LD).
 *
 * Google, sonuç satırındaki küçük simgeyi favicon'dan alıyor; marka adını ve
 * logosunu ise buradaki Organization verisinden tanıyor. İkisi birlikte
 * olmadan sonuçta jenerik dünya simgesi çıkıyor.
 */
function bm_organization_jsonld($content) {
    $site = $content['site'] ?? [];
    $kok  = bm_site_url();

    $veri = [
        '@context'      => 'https://schema.org',
        '@type'         => ['Organization', 'LocalBusiness', 'ManufacturingBusiness'],
        'name'          => bm_t('site.name', 'Beyli Makina Sanayi ve Ticaret Ltd. Şti.'),
        'legalName'     => 'Beyli Makina Sanayi ve Ticaret Limited Şirketi',
        'alternateName' => ['Beyli Makina', 'Teknikel Makina'],
        'foundingDate'  => '1963',
        'founder'       => [
            '@type'         => 'Person',
            'name'          => 'Bekir Çokbekler',
            'alternateName' => 'Tüfekçi Bekir',
        ],
        'taxID'         => '1671367463',
        'vatID'         => 'TR1671367463',
        'url'           => $kok . '/',
        // Google logo için en az 112x112 istiyor; kare işaret en iyi sonucu veriyor
        'logo'          => $kok . '/images/favicon-192.png',
        'image'         => $kok . '/images/logo.png',
        'hasMap'        => 'https://maps.app.goo.gl/1PdTvDTCfzaojur79',
    ];

    // Panelde henüz doldurulmamış örnek değerler (+905XXXXXXXXX gibi) arama
    // motoruna gönderilmesin; eksik bilgi, yanlış bilgiden iyidir.
    $gercek = function ($v) {
        $v = trim((string)$v);
        return ($v !== '' && stripos($v, 'xxx') === false) ? $v : '';
    };

    if ($gercek($site['phone_link'] ?? '')) $veri['telephone'] = $site['phone_link'];
    if ($gercek($site['email'] ?? ''))      $veri['email'] = $site['email'];

    // Müşteri hizmetleri / iletişim noktası
    $telefon = $gercek($site['phone_link'] ?? '') ?: '+903442363232';
    $veri['contactPoint'] = [
        [
            '@type'             => 'ContactPoint',
            'telephone'         => $telefon,
            'contactType'       => 'customer service',
            'areaServed'        => 'TR',
            'availableLanguage' => ['Turkish'],
        ],
    ];

    $adresMetni = !empty($site['address'])
        ? $site['address']
        : 'Yavuz Selim, Tornacılar Cd. No:61, 46080 Dulkadiroğlu/Kahramanmaraş';

    $veri['address'] = [
        '@type'           => 'PostalAddress',
        'streetAddress'   => $adresMetni,
        'addressLocality' => 'Kahramanmaraş',
        'addressRegion'   => 'Dulkadiroğlu',
        'postalCode'      => '46080',
        'addressCountry'  => 'TR',
    ];

    // Yapay zeka arama motorları için kurumsal uzmanlık alanları (Entity Knowledge)
    $veri['knowsAbout'] = [
        'Tekstil Makinaları Yedek Parça İmalatı',
        'Yaygın Tekstil Makinaları Yedek Parçaları',
        'Kişiye ve İşletmeye Özel Parça Üretimi',
        'Tekstil Fabrikaları İçin Özel Parça İmalatı',
        'CNC Torna İmalatı',
        'CNC Freze İmalatı',
        'Talaşlı İmalat',
        'Kuyumcu Aletleri ve Makinaları',
        'Özel Sipariş ve Fason İmalat',
    ];

    // Sunulan hizmet ve ürün katalog özeti
    $veri['hasOfferCatalog'] = [
        '@type' => 'OfferCatalog',
        'name'  => 'Beyli Makina İmalat ve Parça Kataloğu',
        'itemListElement' => [
            [
                '@type' => 'OfferCatalog',
                'name'  => 'Tekstil ve İplik Makineleri Yedek Parçaları (Yaygın & Özel İmalat)',
            ],
            [
                '@type' => 'OfferCatalog',
                'name'  => 'Kişiye ve İşletmeye Özel Parça Üretimi',
            ],
            [
                '@type' => 'OfferCatalog',
                'name'  => 'CNC Torna ve Freze Talaşlı İmalat',
            ],
            [
                '@type' => 'OfferCatalog',
                'name'  => 'Kuyumcu Makineleri ve El Aletleri',
            ],
            [
                '@type' => 'OfferCatalog',
                'name'  => 'Özel Sipariş & Fason Parça İmalatı',
            ],
        ],
    ];

    // Sosyal medya ve harita profilleri — Google ve yapay zeka bunları aynı markaya bağlar
    $sameAs = ['https://maps.app.goo.gl/1PdTvDTCfzaojur79'];
    $ekle = function ($u) use (&$sameAs, $gercek) {
        $u = bm_safe_url($u);
        if (strpos($u, 'http') !== 0 || !$gercek($u)) return;
        $yol = trim((string)parse_url($u, PHP_URL_PATH), '/');
        if ($yol === '') return;
        $sameAs[] = $u;
    };
    $ekle($site['linkedin_link'] ?? '');
    $ekle($site['whatsapp_link'] ?? '');
    foreach (bm_button_list($content, 'footer.social_buttons') as $b) {
        $ekle(bm_contact_button_href($b));
    }
    $sameAs = array_values(array_unique($sameAs));
    if ($sameAs) $veri['sameAs'] = $sameAs;

    return json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

/**
 * Hizmet sayfası için Schema.org Service yapısal verisi.
 *
 * Yapay zeka modelleri ve arama motorları sunulan hizmetin adını,
 * sağlayıcısını (Beyli Makina) ve açıklamasını doğrudan buradan çeker.
 */
function bm_service_jsonld($service) {
    if (empty($service) || empty($service['title'])) return '';
    $kok = bm_site_url();
    $ad  = trim((string)$service['title']);
    $url = $kok . '/hizmet/' . rawurlencode((string)($service['slug'] ?? ''));

    $veri = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Service',
        'name'        => $ad,
        'url'         => $url,
        'description' => trim((string)($service['hero_subtitle'] ?: ($service['desc'] ?? $ad))),
        'provider'    => [
            '@type' => 'ManufacturingBusiness',
            'name'  => bm_t('site.name', 'Beyli Makina Sanayi ve Ticaret Ltd. Şti.'),
            'url'   => $kok . '/',
        ],
        'areaServed'  => [
            '@type' => 'Country',
            'name'  => 'Türkiye',
        ],
    ];

    $img = trim((string)($service['hero_image'] ?: ($service['card_image'] ?? ($service['image'] ?? ''))));
    if ($img !== '') {
        $veri['image'] = preg_match('#^https?://#i', $img) ? $img : $kok . bm_src('/' . ltrim($img, '/'));
    }

    return json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

/**
 * Site kimliği + site içi arama kutusu (JSON-LD).
 *
 * Google'ın arama sonucunda marka adının altında çıkardığı alt bağlantılar
 * ("site links") ve arama kutusu bu veriyle besleniyor. Alt bağlantıları
 * Google kendi seçiyor — istekle eklenmiyor — ama sitenin yapısını ne kadar
 * net anlatırsak o kadar erken çıkarıyor.
 */
function bm_website_jsonld($content) {
    $kok = bm_site_url();
    $veri = [
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => bm_t('site.name', 'Beyli Makina Sanayi ve Ticaret Ltd. Şti.'),
        'alternateName' => 'Beyli Makina',
        'url'      => $kok . '/',
        // Site içi arama: Google sonuç satırının altına arama kutusu koyabiliyor
        'potentialAction' => [
            '@type'  => 'SearchAction',
            'target' => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => $kok . '/urunler?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];
    return json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

/**
 * Konum çubuğunun (breadcrumb) arama motoru karşılığı.
 *
 * $ogeler: [['ad' => 'Ürünler', 'yol' => '/urunler'], ...] — son öğenin yolu
 * boş bırakılabilir (bulunulan sayfa).
 */
function bm_breadcrumb_jsonld($ogeler) {
    $kok = bm_site_url();
    $liste = [];
    $sira = 1;
    foreach ($ogeler as $o) {
        $ad = trim((string)($o['ad'] ?? ''));
        if ($ad === '') continue;
        $oge = [
            '@type'    => 'ListItem',
            'position' => $sira++,
            'name'     => $ad,
        ];
        $yol = trim((string)($o['yol'] ?? ''));
        if ($yol !== '') $oge['item'] = $kok . $yol;
        $liste[] = $oge;
    }
    if (count($liste) < 2) return '';
    return json_encode([
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $liste,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

/** Kurulumla gelen örnek hero slaytları — panelden hiç kaydedilmediyse kullanılır */
function bm_hero_ornek_slaytlar() {
    $out = [];
    for ($i = 1; $i <= 4; $i++) {
        $yol = '/images/hero/ornek-' . $i . '.jpg';
        if (file_exists(BM_ROOT . $yol)) $out[] = ['image' => $yol, 'focus' => '50% 50%'];
    }
    return $out;
}

/**
 * Ana sayfa hero slaytları.
 *
 * Panelde "slides" hiç kaydedilmemişse (yeni kurulum) örnek görseller
 * gösterilir; böylece slayt boş bir lacivert zemin olarak durmuyor. Panelden
 * bir kez kaydedildikten sonra — liste boş bırakılmış olsa bile — yalnızca
 * girilenler geçerli olur, örnekler bir daha devreye girmez.
 */
function bm_hero_slides($content) {
    $hero = $content['hero'] ?? [];
    if (!array_key_exists('slides', $hero)) return bm_hero_ornek_slaytlar();

    $out = [];
    foreach ((array)$hero['slides'] as $s) {
        $img = trim((string)($s['image'] ?? ''));
        if ($img === '') continue;
        // Dosya silinmişse slayt boş bir kutu olarak kalmasın
        if ($img[0] === '/' && !file_exists(BM_ROOT . $img)) continue;
        $out[] = ['image' => $img, 'focus' => trim((string)($s['focus'] ?? ''))];
    }
    return $out;
}

/** Slaytların kaç saniyede bir değişeceği (panelden) */
function bm_hero_slide_interval($content) {
    return bm_num_range($content['hero']['slide_interval'] ?? null, 2, 20, 6);
}

/**
 * Kırpılarak gösterilen bir <img> için odak noktası stili.
 *
 * Ürün fotoğrafları kartlarda ve detay sayfasında object-fit:cover ile
 * kırpılıyor; panelden seçilen nokta bu sayede ortada kalıyor. Değer
 * geçersizse hiç stil basılmıyor, tarayıcı varsayılanı (merkez) kalıyor.
 */
function bm_focus_style($focus, $zoom = null) {
    $focus = trim((string)$focus);
    $gecerli = preg_match('/^\d{1,3}(\.\d+)?%\s+\d{1,3}(\.\d+)?%$/', $focus);
    $zoom = bm_num_range($zoom, 100, 300, 100);

    $stil = '';
    if ($gecerli && $focus !== '50% 50%') {
        $stil .= 'object-position:' . $focus . ';';
    }
    if ($zoom > 100) {
        // Kapsayıcılarda overflow:hidden var, büyüyen kısım kırpılıyor.
        // Büyütme merkezi odak noktası: yakınlaştırınca seçilen yer kaçmıyor.
        $stil .= 'transform:scale(' . round($zoom / 100, 3) . ');';
        $stil .= 'transform-origin:' . ($gecerli ? $focus : '50% 50%') . ';';
    }
    return $stil === '' ? '' : ' style="' . e($stil) . '"';
}

/**
 * Panelde bir butona PNG ikon yükleme alanı.
 *
 * Emoji her cihazda farklı çiziliyor ve gerçek WhatsApp/e-posta logosu yerine
 * geçmiyor; bu yüzden her butona kendi görselini yükleyebilme alanı gerekiyor.
 * Yükleme, Görseller sayfasına gidip yol kopyalamaya gerek kalmadan buradan
 * yapılıyor — dosya doğrudan images/uploads içine düşüyor.
 *
 * $name  form alanı adı (tekrar eden satırlarda "drawer_image[]" gibi)
 */
function bm_admin_icon_field($name, $value, $opts = []) {
    $label = $opts['label'] ?? 'PNG İkon';
    $hint  = $opts['hint'] ?? '';
    // 'big' => büyük önizleme (ürün/sayfa görselleri için)
    $ekSinif = !empty($opts['big']) ? ' icon-up-big' : '';
    // Yüklenen dosyanın adına eklenecek ipucu alanının id'si (ör. ürün adı)
    $adAlani = $opts['name_from'] ?? '';
    $value = trim((string)$value);

    // Kayıtlı dosya diskte yoksa (Görseller ekranından silinmiş olabilir)
    // önizlemede kırık resim simgesi çıkıyordu. Yolu kaybetmeden uyarı
    // gösteriyoruz: kullanıcı ya yeniden seçer ya "Kaldır" der.
    $eksikDosya = ($value !== '' && $value[0] === '/' && !file_exists(BM_ROOT . rawurldecode($value)));
    ob_start(); ?>
    <div class="field icon-up<?php echo $ekSinif; ?><?php echo ($value === '' || $eksikDosya) ? ' is-empty' : ''; ?>"
         data-icon-up<?php echo $adAlani !== '' ? ' data-name-from="' . e($adAlani) . '"' : ''; ?>>
      <label><?php echo e($label); ?></label>
      <div class="icon-up-row">
        <span class="icon-up-prev" aria-hidden="true">
          <img<?php echo ($value !== '' && !$eksikDosya) ? ' src="' . e(bm_src($value)) . '"' : ''; ?> alt="">
        </span>
        <input type="text" class="icon-up-path" name="<?php echo e($name); ?>"
               <?php if (!empty($opts['id'])): ?>id="<?php echo e($opts['id']); ?>"<?php endif; ?>
               value="<?php echo e($value); ?>" placeholder="/images/uploads/whatsapp.png">
        <button type="button" class="btn ghost icon-up-pick">Yükle</button>
        <?php /* Siteye daha önce yüklenmiş görsellerden seçmek için — klasörlü
                 seçici pencereyi açar (teknikel/assets/secici.js) */ ?>
        <button type="button" class="btn ghost" data-secici>Siteden Seç</button>
        <button type="button" class="link-btn danger icon-up-clear">Kaldır</button>
      </div>
      <div class="icon-up-note" role="status"></div>
      <?php if ($eksikDosya): ?>
        <div class="hint" style="color:#a11616;">
          ⚠ Bu dosya artık kütüphanede yok (silinmiş olabilir). Sitede yerine
          varsayılan simge görünüyor. Yeni bir görsel seç ya da “Kaldır”a bas.
        </div>
      <?php endif; ?>
      <?php if ($hint !== ''): ?><div class="hint"><?php echo e($hint); ?></div><?php endif; ?>
    </div>
    <?php return ob_get_clean();
}

/**
 * Teklif sayfasındaki iletişim butonları.
 *
 * Panelden yönetilir. Hiç tanımlanmamışsa (eski kurulumlar) Genel Ayarlar'daki
 * telefon/WhatsApp/e-posta/adres bilgilerinden varsayılan liste üretilir —
 * böylece güncelleme sonrası kimsenin iletişim bilgisi kaybolmaz.
 */
function bm_contact_buttons($content) {
    $buttons = $content['order_section']['contact_buttons'] ?? null;

    if (is_array($buttons)) {
        return array_values(array_filter($buttons, function ($b) {
            return trim((string)($b['text'] ?? '')) !== '' || trim((string)($b['label'] ?? '')) !== '';
        }));
    }

    $site = $content['site'] ?? [];
    $defaults = [];
    if (!empty($site['phone_display'])) {
        $defaults[] = ['icon' => '📞', 'label' => 'Telefon', 'text' => $site['phone_display'],
                       'type' => 'tel', 'value' => $site['phone_link'] ?? $site['phone_display']];
    }
    if (!empty($site['whatsapp_link'])) {
        $defaults[] = ['icon' => '💬', 'label' => 'WhatsApp', 'text' => 'Mesaj gönder',
                       'type' => 'link', 'value' => $site['whatsapp_link']];
    }
    if (!empty($site['email'])) {
        $defaults[] = ['icon' => '✉️', 'label' => 'E-Posta', 'text' => $site['email'],
                       'type' => 'mail', 'value' => $site['email']];
    }
    if (!empty($site['address'])) {
        $defaults[] = ['icon' => '📍', 'label' => 'Adres', 'text' => $site['address'],
                       'type' => 'text', 'value' => ''];
    }
    return $defaults;
}

/**
 * Panelden girilen serbest bir adresi güvenli hâle getirir.
 *
 * Yalnızca http, https, tel, mailto ve site içi (/ ile başlayan) adresler
 * kabul edilir. "javascript:" gibi bir değer yazılıp sayfaya kod
 * enjekte edilmesini engeller. Kabul edilmezse boş string döner.
 */
function bm_safe_url($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    // Site içi adresler (tek / veya ardında / gelmeyenler) ve çapa (#) adresleri
    if (($url[0] === '/' && (!isset($url[1]) || $url[1] !== '/')) || $url[0] === '#') return $url;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (in_array($scheme, ['http', 'https', 'tel', 'mailto'], true)) return $url;
    // Şemasız yazılmışsa (ör. "maps.google.com/...") https varsayılır
    if ($scheme === '' && preg_match('#^[\w.-]+\.[a-z]{2,}#i', $url)) return 'https://' . $url;
    return '';
}

/**
 * Bir iletişim butonunun bağlantı adresini üretir.
 * Boş string dönerse buton tıklanabilir değildir (ör. adres).
 *
 * Adres panelden geldiği için şema denetimi yapılıyor: yalnızca http, https,
 * tel ve mailto kabul ediliyor. Aksi hâlde "javascript:" gibi bir değer
 * yazılarak sayfaya kod enjekte edilebilirdi.
 */
function bm_contact_button_href($btn) {
    $type  = strtolower(trim((string)($btn['type'] ?? 'text')));
    $value = trim((string)($btn['value'] ?? ''));
    if ($value === '') $value = trim((string)($btn['text'] ?? ''));
    if ($value === '') return '';

    switch ($type) {
        case 'tel':
            // Numaradan boşluk, parantez ve tire temizlenir; "+" başta kalır.
            $digits = preg_replace('/[^0-9+]/', '', $value);
            return $digits !== '' ? 'tel:' . $digits : '';

        case 'mail':
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $value : '';

        case 'whatsapp':
            $digits = preg_replace('/[^0-9]/', '', $value);
            return $digits !== '' ? 'https://wa.me/' . $digits : '';

        case 'link':
            // bm_safe_url site içi adresleri (/urunler, #katalog) de kabul ediyor;
            // footer'daki hızlı linkler bu yüzden buradan geçiyor.
            return bm_safe_url($value);

        default: // 'text'
            return '';
    }
}

/** Hizmetleri sıraya göre döndür */
function bm_services($content, $onlyWithPage = false) {
    $services = $content['services'] ?? [];
    if ($onlyWithPage) {
        $services = array_filter($services, function ($s) {
            return !empty($s['has_page']) && !empty($s['slug']);
        });
    }
    usort($services, function ($a, $b) {
        return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
    });
    return array_values($services);
}

/** Slug'a göre tek bir hizmet bul */
function bm_find_service($content, $slug) {
    foreach ($content['services'] ?? [] as $s) {
        if (($s['slug'] ?? '') === $slug) return $s;
    }
    return null;
}

/**
 * "Etiket: Değer" biçimindeki çok satırlı metni [[etiket,değer], ...] dizisine
 * çevirir. Panelde makine özellikleri bu şekilde giriliyor — iç içe tekrar
 * eden alan grupları yerine tek bir metin alanı hem yazması hem yönetmesi kolay.
 */
function bm_parse_spec_lines($text) {
    $out = [];
    foreach (preg_split('/\R/', (string)$text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = explode(':', $line, 2);
        $label = trim($parts[0] ?? '');
        $value = trim($parts[1] ?? '');
        if ($label === '') continue;
        $out[] = [$label, $value];
    }
    return $out;
}

/** Yukarıdakinin tersi — panelde düzenlenebilir metne çevirir */
function bm_spec_lines_to_text($specs) {
    $lines = [];
    foreach ((array)$specs as $s) {
        $label = $s[0] ?? '';
        $value = $s[1] ?? '';
        if ($label === '' && $value === '') continue;
        $lines[] = $value === '' ? $label : ($label . ': ' . $value);
    }
    return implode("\n", $lines);
}

/** Slug'a göre tek bir sayfa bul */
function bm_find_page($content, $slug) {
    foreach ($content['pages'] ?? [] as $p) {
        if ($p['slug'] === $slug) return $p;
    }
    return null;
}

/** Basit, güvenli slug üretici (Türkçe karakterleri de dönüştürür) */
function bm_slugify($text) {
    $map = ['ç'=>'c','Ç'=>'c','ğ'=>'g','Ğ'=>'g','ı'=>'i','İ'=>'i','ö'=>'o','Ö'=>'o','ş'=>'s','Ş'=>'s','ü'=>'u','Ü'=>'u'];
    $text = strtr($text, $map);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    if ($text === '') $text = 'sayfa-' . substr(md5(uniqid('', true)), 0, 6);
    return $text;
}

/**
 * Sayfa başlığı (header) görseli kartı — odak noktası seçicisiyle birlikte.
 * $imgName/$focusName form alan adları, $imgVal/$focusVal mevcut değerler.
 */
function bm_admin_hero_card($imgName, $focusName, $imgVal, $focusVal) {
    ob_start(); ?>
    <div class="card">
      <h2>🖼️ Sayfa Başlığı Görseli</h2>
      <p class="card-hint">
        Sayfanın en üstündeki bandın arkasına konur. Boş bırakırsan lacivert zemin kalır.
        Geniş ve yatay fotoğraflar en iyi durur (ör. 1600×600).
      </p>
      <?php /* Ürün ekranındaki görsel alanının aynısı: Yükle / Siteden Seç /
               Kaldır düğmeleri ve küçük önizleme. Eskiden burada düz bir metin
               kutusu ve Medya ekranına giden bir bağlantı vardı; yolu elle
               kopyalamak gerekiyordu. */ ?>
      <?php echo bm_admin_icon_field($imgName, $imgVal, [
        'label' => 'Görsel Adresi',
        'big'   => true,
        // id şart: aşağıdaki ortalama aracı görsel yolunu bu id ile buluyor.
        'id'    => $imgName,
        'hint'  => 'Yükle ile bilgisayarından ekle, Siteden Seç ile daha önce yüklediklerinden seç.',
      ]); ?>
      <input type="hidden" id="<?php echo e($focusName); ?>" name="<?php echo e($focusName); ?>"
             value="<?php echo e($focusVal); ?>">
      <div class="focal-picker"
           data-focal-input="<?php echo e($focusName); ?>"
           data-focal-src-input="<?php echo e($imgName); ?>"></div>
    </div>
    <?php return ob_get_clean();
}

/* ==========================================================================
   TEKLİF FORMU — KURUMSAL ALANLAR, TELEFON VE VERGİ NO YARDIMCILARI
   ========================================================================== */

/**
 * Kurumsal alanların panelden yönetilen listesi.
 * Her alan için: anahtar => [etiket anahtarı, varsayılan durum]
 * Durumlar: 'kapali' | 'istege' | 'zorunlu'
 */
function bm_corporate_field_defs() {
    return [
        'company'   => ['form.company',     'zorunlu'],
        'taxoffice' => ['form.taxoffice',   'zorunlu'],
        'taxno'     => ['form.taxno',       'zorunlu'],
        'role'      => ['form.role',        'istege'],
        'country'   => ['form.country',     'zorunlu'],
        'city'      => ['form.city',        'zorunlu'],
        'district'  => ['form.district',    'zorunlu'],
        'address'   => ['form.address',     'zorunlu'],
        'web'       => ['form.company_web', 'istege'],
        'qty'       => ['form.qty',         'istege'],
        'deadline'  => ['form.deadline',    'istege'],
        'pono'      => ['form.pono',        'istege'],
    ];
}

/**
 * Teklif formunun kurumsal ayarlarını döndürür.
 * Panelde kaydedilmemişse varsayılanlar kullanılır.
 */
function bm_corporate_config($content = null) {
    if ($content === null) $content = bm_load_content();
    $cfg = (array)($content['order_section']['corporate'] ?? []);

    $alanlar = [];
    foreach (bm_corporate_field_defs() as $anahtar => $tanim) {
        $durum = strtolower(trim((string)($cfg['fields'][$anahtar] ?? '')));
        if (!in_array($durum, ['kapali', 'istege', 'zorunlu'], true)) {
            $durum = $tanim[1];
        }
        $alanlar[$anahtar] = $durum;
    }

    return [
        // Kurumsal sekmesi hiç görünmesin istenirse kapatılabilir
        'enabled'    => !isset($cfg['enabled']) || (int)$cfg['enabled'] === 1,
        'fields'     => $alanlar,
        // Telefon alanında başlangıçta seçili gelen ülke
        'default_country' => strtoupper(trim((string)($cfg['default_country'] ?? 'TR'))) ?: 'TR',
        // Aydınlatma metni sayfasının adresi (boşsa onay kutusu gösterilmez)
        'kvkk_url'   => trim((string)($cfg['kvkk_url'] ?? '')),
        // Ticari ileti (kampanya) onay kutusu görünsün mü
        'consent_box' => !empty($cfg['consent_box']),
        // Adet / termin / sipariş no alanları bireysel formda da görünsün mü
        'extras_individual' => !empty($cfg['extras_individual']),
    ];
}

/**
 * Vergi Kimlik Numarası (VKN) doğrulaması — 10 hane, Gelir İdaresi algoritması.
 */
function bm_vkn_gecerli($no) {
    $no = preg_replace('/\D/', '', (string)$no);
    if (strlen($no) !== 10) return false;
    if ((int)$no === 0) return false;

    $toplam = 0;
    for ($i = 0; $i < 9; $i++) {
        $rakam = (int)$no[$i];
        $q = ($rakam + (10 - ($i + 1))) % 10;
        if ($q === 0) {
            $toplam += $q;
        } else {
            $ara = ($q * (2 ** (10 - ($i + 1)))) % 9;
            $toplam += ($ara === 0) ? 9 : $ara;
        }
    }
    $kontrol = (10 - ($toplam % 10)) % 10;
    return $kontrol === (int)$no[9];
}

/**
 * TC Kimlik Numarası doğrulaması — 11 hane, Nüfus İdaresi algoritması.
 * (Sadece numaranın matematiğini kontrol eder, kişiyi sorgulamaz.)
 */
function bm_tckn_gecerli($no) {
    $no = preg_replace('/\D/', '', (string)$no);
    if (strlen($no) !== 11) return false;
    if ($no[0] === '0') return false;

    $tek = (int)$no[0] + (int)$no[2] + (int)$no[4] + (int)$no[6] + (int)$no[8];
    $cift = (int)$no[1] + (int)$no[3] + (int)$no[5] + (int)$no[7];

    $onuncu = (($tek * 7) - $cift) % 10;
    if ($onuncu < 0) $onuncu += 10;
    if ($onuncu !== (int)$no[9]) return false;

    $ilkOn = 0;
    for ($i = 0; $i < 10; $i++) $ilkOn += (int)$no[$i];
    return ($ilkOn % 10) === (int)$no[10];
}

/**
 * Vergi no / TCKN için ortak kontrol.
 * 10 hane -> VKN, 11 hane -> TCKN. Başka uzunluk geçersiz sayılır.
 * Dönüş: true (geçerli) / false (hatalı görünüyor)
 */
function bm_vergino_gecerli($no) {
    $temiz = preg_replace('/\D/', '', (string)$no);
    if (strlen($temiz) === 10) return bm_vkn_gecerli($temiz);
    if (strlen($temiz) === 11) return bm_tckn_gecerli($temiz);
    return false;
}

/**
 * Telefon numarasını ülke koduyla birleştirip düzenler.
 *
 * @param string $iso    Seçilen ülkenin ISO kodu (örn. TR)
 * @param string $numara Kullanıcının yazdığı numara
 * @return array ['e164' => '+905331234567', 'display' => '+90 533 123 45 67',
 *                'valid' => bool, 'dial' => '90', 'iso' => 'TR', 'local' => '5331234567']
 */
function bm_phone_parse($iso, $numara) {
    require_once __DIR__ . '/countries.php';

    $ulke = bm_country($iso);
    if (!$ulke) $ulke = bm_country('TR');
    $dial = $ulke ? $ulke['dial'] : '90';

    $ham = preg_replace('/\D/', '', (string)$numara);

    // Kullanıcı numaranın başına 0 ya da ülke kodunu yazmış olabilir; temizliyoruz.
    if ($ham !== '' && strpos($ham, '00' . $dial) === 0) {
        $ham = substr($ham, strlen('00' . $dial));
    } elseif ($ham !== '' && strlen($ham) > strlen($dial) && strpos($ham, $dial) === 0) {
        // "905331234567" gibi yazılmışsa ülke kodunu at
        $kalan = substr($ham, strlen($dial));
        if (strlen($kalan) >= 6) $ham = $kalan;
    }
    $ham = ltrim($ham, '0');

    if ($ham === '') {
        return ['e164' => '', 'display' => '', 'valid' => false, 'dial' => $dial,
                'iso' => $ulke ? $ulke['iso'] : 'TR', 'local' => ''];
    }

    $uzunluk = strlen($ham);
    // Türkiye için tam 10 hane; diğer ülkelerde uluslararası standart 4-14 hane.
    $gecerli = ($ulke && $ulke['iso'] === 'TR') ? ($uzunluk === 10) : ($uzunluk >= 4 && $uzunluk <= 14);

    // Okunaklı görünüm: Türkiye 5XX XXX XX XX, diğerleri üçerli gruplar
    if ($ulke && $ulke['iso'] === 'TR' && $uzunluk === 10) {
        $gorunum = substr($ham, 0, 3) . ' ' . substr($ham, 3, 3) . ' ' . substr($ham, 6, 2) . ' ' . substr($ham, 8, 2);
    } else {
        $gorunum = trim(chunk_split($ham, 3, ' '));
    }

    return [
        'e164'    => '+' . $dial . $ham,
        'display' => '+' . $dial . ' ' . $gorunum,
        'valid'   => $gecerli,
        'dial'    => $dial,
        'iso'     => $ulke ? $ulke['iso'] : 'TR',
        'local'   => $ham,
    ];
}

/* ==========================================================================
   SOSYAL PAYLAŞIM KARTI, ÜRÜN ŞEMASI VE ÖN YÜZ FORM JETONU
   ========================================================================== */

/**
 * Paylaşım görselinin tam adresi (https://... ile başlayan).
 *
 * WhatsApp, LinkedIn, X ve Facebook önizlemede görseli MUTLAKA tam adresle
 * ister; "/images/.." gibi kısa yol çalışmaz.
 *
 * Sıra: sayfanın kendi görseli → panelden seçilen paylaşım görseli →
 *       ana sayfa slaytının ilki → logo.
 */
function bm_share_image($content, $ozel = '') {
    $yol = trim((string)$ozel);
    if ($yol === '') $yol = trim((string)($content['site']['share_image'] ?? ''));
    if ($yol === '') {
        $slaytlar = $content['hero']['slides'] ?? [];
        if (!empty($slaytlar[0]['image'])) $yol = (string)$slaytlar[0]['image'];
    }
    if ($yol === '') $yol = '/images/logo.png';
    if (preg_match('#^https?://#i', $yol)) return $yol;
    return bm_site_url() . bm_src('/' . ltrim($yol, '/'));
}

/**
 * Ürün sayfası için Product yapısal verisi.
 *
 * Google'ın ürünü "ürün" olarak tanımasını sağlar: ad, kod, görsel, marka ve
 * teknik özellikler. Fiyat yayınlamadığımız için "offers" alanı bilinçli olarak
 * yok — fiyatsız sahte teklif eklemek Google tarafında hata üretiyor.
 */
function bm_product_jsonld($product, $content = null) {
    $kok = bm_site_url();
    $ad  = trim((string)($product['title'] ?? ''));
    if ($ad === '') return '';

    $veri = [
        '@context' => 'https://schema.org',
        '@type'    => 'Product',
        'name'     => $ad,
        'url'      => $kok . '/urun/' . rawurlencode((string)($product['slug'] ?? '')),
        'brand'    => [
            '@type' => 'Brand',
            'name'  => bm_t('site.name', 'Beyli Makina Sanayi ve Ticaret Ltd. Şti.'),
        ],
        'manufacturer' => [
            '@type' => 'Organization',
            'name'  => bm_t('site.name', 'Beyli Makina Sanayi ve Ticaret Ltd. Şti.'),
        ],
    ];

    $aciklama = trim((string)($product['description'] ?? ''));
    if ($aciklama !== '') $veri['description'] = mb_substr($aciklama, 0, 900);

    $kod = trim((string)($product['code'] ?? ''));
    if ($kod !== '') { $veri['sku'] = $kod; $veri['mpn'] = $kod; }

    $gorsel = trim((string)($product['image'] ?? ''));
    if ($gorsel !== '') {
        $veri['image'] = preg_match('#^https?://#i', $gorsel)
            ? $gorsel
            : $kok . bm_src('/' . ltrim($gorsel, '/'));
    }

    // Teknik özellik tablosu → additionalProperty
    if (function_exists('bm_visible_specs')) {
        $ozellikler = [];
        foreach (bm_visible_specs($product['specs'] ?? []) as $satir) {
            $ozellikler[] = [
                '@type' => 'PropertyValue',
                'name'  => $satir[0],
                'value' => $satir[1],
            ];
        }
        if ($ozellikler) $veri['additionalProperty'] = $ozellikler;
    }

    return json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Ön yüz formları için tek kullanımlık jeton (CSRF koruması).
 *
 * Panelin kendi jetonu (bm_csrf_token) yönetim oturumuna bağlı; teklif formunu
 * dolduran ziyaretçinin böyle bir oturumu yok. Bu yüzden ön yüz için ayrı ve
 * kalıcı bir jeton tutuluyor: form başka bir siteden gönderilirse jeton
 * gelmediği için istek reddedilir.
 */
function bm_form_token() {
    if (session_status() !== PHP_SESSION_ACTIVE) bm_session_start();
    if (empty($_SESSION['bm_form_csrf'])) {
        $_SESSION['bm_form_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['bm_form_csrf'];
}

/** Gönderilen jetonu doğrular. */
function bm_form_token_check($gelen) {
    if (session_status() !== PHP_SESSION_ACTIVE) bm_session_start();
    $beklenen = $_SESSION['bm_form_csrf'] ?? '';
    if ($beklenen === '' || !is_string($gelen) || $gelen === '') return false;
    return hash_equals($beklenen, $gelen);
}

/**
 * Ziyaretçinin gerçek IP adresi (Plesk/nginx arkasındaki X-Forwarded-For desteğiyle).
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

/**
 * IP bazlı teklif formu hız sınırı kontrolü.
 * Çerez kabul etmeyen veya her defasında yeni oturum açan botların spam gönderimini engeller.
 */
function bm_form_ip_throttle_check($maxRequests = 5, $windowSeconds = 300) {
    $ip = bm_client_ip();
    $file = BM_ROOT . '/content/form-throttles.json';
    if (!is_file($file)) return true;
    $data = json_decode((string)file_get_contents($file), true) ?: [];
    $timestamps = (array)($data[$ip] ?? []);
    $now = time();
    $valid = array_filter($timestamps, function ($t) use ($now, $windowSeconds) {
        return ($now - (int)$t) < $windowSeconds;
    });
    return count($valid) < $maxRequests;
}

/**
 * Başarılı/denenen form gönderiminde IP zaman damgasını kaydeder.
 */
function bm_form_ip_throttle_record() {
    $ip = bm_client_ip();
    $file = BM_ROOT . '/content/form-throttles.json';
    $data = [];
    if (is_file($file)) {
        $data = json_decode((string)file_get_contents($file), true) ?: [];
    }
    $now = time();
    foreach ($data as $k => $times) {
        $filtered = array_filter((array)$times, function ($t) use ($now) {
            return ($now - (int)$t) < 3600;
        });
        if ($filtered) {
            $data[$k] = array_values($filtered);
        } else {
            unset($data[$k]);
        }
    }
    $data[$ip][] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);
}
