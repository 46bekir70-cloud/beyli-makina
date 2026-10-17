<?php
/**
 * Site haritası — /sitemap.xml adresinden servis edilir (.htaccess yönlendirir).
 *
 * Arama motorlarına (Google, Yandex, Bing) sitedeki bütün adreslerin listesini
 * tek dosyada verir. Ürünler veritabanından, sayfalar ve hizmetler
 * content.json'dan geldiği için liste her zaman güncel — elle bakım gerekmez.
 */
require_once __DIR__ . '/inc/articles.php';

$content = bm_load_content();
$kok = rtrim(bm_site_url(), '/');

/** Tek bir <url> satırı */
function bm_sitemap_satir($kok, $yol, $oncelik = '0.6', $siklik = 'weekly') {
    $adres = $kok . $yol;
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($adres, ENT_XML1) . "</loc>\n";
    echo "    <changefreq>$siklik</changefreq>\n";
    echo "    <priority>$oncelik</priority>\n";
    echo "  </url>\n";
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Ana sayfa ve sabit sayfalar
bm_sitemap_satir($kok, '/', '1.0', 'weekly');
bm_sitemap_satir($kok, '/urunler', '0.9', 'daily');
bm_sitemap_satir($kok, '/teklif', '0.8', 'monthly');

// Panelden eklenen sayfalar (Hakkımızda, Blog vb.)
foreach (($content['pages'] ?? []) as $sayfa) {
    $slug = trim((string)($sayfa['slug'] ?? ''));
    if ($slug === '') continue;
    // Blog sayfası makale listesi olarak ayrıca ekleniyor
    bm_sitemap_satir($kok, '/' . rawurlencode($slug), $slug === 'blog' ? '0.8' : '0.7',
                     $slug === 'blog' ? 'weekly' : 'monthly');
}

// Makaleler
if (bm_articles_ready()) {
    $sayfaNo = 1;
    do {
        $yazilar = bm_articles(['per_page' => 200, 'page' => $sayfaNo]);
        foreach ($yazilar['items'] as $yazi) {
            $slug = trim((string)($yazi['slug'] ?? ''));
            if ($slug === '') continue;
            bm_sitemap_satir($kok, '/makale/' . rawurlencode($slug), '0.7', 'monthly');
        }
        $sayfaNo++;
    } while ($sayfaNo <= (int)$yazilar['pages'] && $sayfaNo <= 20);
}

// Hizmet tanıtım sayfaları
foreach (bm_services($content, true) as $hizmet) {
    $slug = trim((string)($hizmet['slug'] ?? ''));
    if ($slug === '') continue;
    bm_sitemap_satir($kok, '/hizmet/' . rawurlencode($slug), '0.8', 'monthly');
}

// Ürün kategorileri (alt kategoriler dâhil)
if (bm_db_ready()) {
    foreach (bm_category_flat(null, true) as $kat) {
        $slug = trim((string)($kat['slug'] ?? ''));
        if ($slug === '') continue;
        bm_sitemap_satir($kok, '/urunler?kategori=' . rawurlencode($slug), '0.7', 'weekly');
    }

    // Ürünler. 1000+ ürün olabileceği için tek seferde çekiyoruz ama
    // yalnızca slug alanını kullanıyoruz.
    $sayfa = 1;
    do {
        $liste = bm_products(['per_page' => 500, 'page' => $sayfa]);
        foreach ($liste['items'] as $urun) {
            $slug = trim((string)($urun['slug'] ?? ''));
            if ($slug === '') continue;
            bm_sitemap_satir($kok, '/urun/' . rawurlencode($slug), '0.6', 'monthly');
        }
        $sayfa++;
    } while ($sayfa <= (int)$liste['pages'] && $sayfa <= 40); // en fazla 20.000 ürün
}

echo '</urlset>' . "\n";
