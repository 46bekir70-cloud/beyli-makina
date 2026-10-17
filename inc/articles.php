<?php
/**
 * Blog / makale katmanı.
 *
 * Makaleler ürünlerle aynı mantıkla veritabanında tutulur: sayfalama ve arama
 * her zaman SQL tarafında yapılır, hiçbir yerde bütün makaleler belleğe alınmaz.
 *
 * Etiketler ayrı tabloda değil, virgülle ayrılmış tek sütunda duruyor. Bir
 * makale birkaç etiket taşıyor (ör. "CNC Torna, Malzeme"); ayrı tablo kurmak
 * bu ölçekte kazanç sağlamadan iki tablo daha bakım demek olurdu.
 */

// products.php de gerekiyor: bm_escape_like() orada tanımlı ve arama
// sorgularında kullanılıyor.
require_once __DIR__ . '/products.php';

/** Blog listesinde sayfa başına makale sayısı */
define('BM_ARTICLES_PER_PAGE', 9);

/** articles tablosu kurulu mu? */
function bm_articles_ready($yenile = false) {
    static $hazir = null;
    if ($hazir !== null && !$yenile) return $hazir;
    try {
        bm_db()->query('SELECT 1 FROM articles LIMIT 1');
        $hazir = true;
    } catch (Throwable $e) {
        $hazir = false;
    }
    return $hazir;
}

/**
 * Tabloyu yoksa oluşturur. Panelin makale ekranları açılırken çağrılıyor;
 * ayrıca bir "veritabanını güncelle" adımı gerekmesin diye.
 *
 * NOT: MariaDB 5.5 bir tabloda yalnızca tek otomatik TIMESTAMP'e izin veriyor,
 * bu yüzden güncelleme zamanı için ikinci bir otomatik sütun konmadı.
 */
function bm_articles_migrate() {
    if (!bm_config_exists()) return false;

    // Tablo zaten varsa yalnızca eksik sütunları tamamla. Sunucuda başka bir
    // sebeple "articles" adında bir tablo bulunuyorsa kaydetme sırasında
    // "unknown column" hatası vermek yerine sütunları ekliyoruz.
    if (bm_articles_ready()) {
        $mevcut = [];
        try {
            foreach (bm_db()->query('SHOW COLUMNS FROM articles') as $c) $mevcut[] = $c['Field'];
        } catch (Throwable $e) {
            return false;
        }
        $gereken = [
            'summary'      => 'TEXT NULL',
            'body'         => 'MEDIUMTEXT NULL',
            'image'        => 'VARCHAR(255) NULL',
            'image_focus'  => 'VARCHAR(16) NULL',
            'image_zoom'   => 'SMALLINT UNSIGNED NULL',
            'tags'         => 'VARCHAR(255) NULL',
            'author'       => 'VARCHAR(120) NULL',
            'published_at' => 'DATE NULL',
            'is_active'    => 'TINYINT(1) NOT NULL DEFAULT 1',
            'is_featured'  => 'TINYINT(1) NOT NULL DEFAULT 0',
        ];
        $ekle = [];
        foreach ($gereken as $ad => $tanim) {
            if (!in_array($ad, $mevcut, true)) $ekle[] = "ADD COLUMN $ad $tanim";
        }
        if (!$ekle) return false;
        try {
            bm_db()->exec('ALTER TABLE articles ' . implode(', ', $ekle));
        } catch (Throwable $e) {
            return false;
        }
        return true;
    }

    try {
        bm_db()->exec(
            "CREATE TABLE IF NOT EXISTS articles (
               id INT UNSIGNED NOT NULL AUTO_INCREMENT,
               slug VARCHAR(190) NOT NULL,
               title VARCHAR(255) NOT NULL,
               summary TEXT NULL,
               body MEDIUMTEXT NULL,
               image VARCHAR(255) NULL,
               image_focus VARCHAR(16) NULL,
               image_zoom SMALLINT UNSIGNED NULL,
               tags VARCHAR(255) NULL,
               author VARCHAR(120) NULL,
               published_at DATE NULL,
               is_active TINYINT(1) NOT NULL DEFAULT 1,
               is_featured TINYINT(1) NOT NULL DEFAULT 0,
               created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
               PRIMARY KEY (id),
               UNIQUE KEY slug (slug),
               KEY yayin (is_active, published_at)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (Throwable $e) {
        return false;
    }
    bm_articles_ready(true);
    return true;
}

/** "CNC Torna, Malzeme" -> ['CNC Torna', 'Malzeme'] */
function bm_article_tag_list($ham) {
    $out = [];
    foreach (explode(',', (string)$ham) as $t) {
        $t = trim($t);
        if ($t !== '' && !in_array($t, $out, true)) $out[] = $t;
    }
    return $out;
}

/** Etiketi adres parçasına çevirir (filtre linkleri için) */
function bm_article_tag_slug($etiket) {
    return bm_slugify($etiket);
}

/** Yazının tahmini okuma süresi — dakika (dakikada ~200 kelime) */
function bm_article_reading_time($govde) {
    $duz = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$govde)));
    if ($duz === '') return 1;
    $kelime = count(preg_split('/\s+/u', $duz));
    return max(1, (int)ceil($kelime / 200));
}

/**
 * Makale listesi.
 *
 * $opts: q (arama), tag (etiket slug'ı), page, per_page,
 *        include_inactive, featured
 */
function bm_articles($opts = []) {
    $bos = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => BM_ARTICLES_PER_PAGE];
    if (!bm_articles_ready()) return $bos;

    $where = [];
    $params = [];

    if (empty($opts['include_inactive'])) {
        $where[] = 'is_active = 1';
    }
    if (!empty($opts['featured'])) {
        $where[] = 'is_featured = 1';
    }

    $q = trim((string)($opts['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(title LIKE :q1 OR summary LIKE :q2 OR body LIKE :q3 OR tags LIKE :q4)';
        $like = '%' . bm_escape_like($q) . '%';
        $params[':q1'] = $like; $params[':q2'] = $like;
        $params[':q3'] = $like; $params[':q4'] = $like;
    }

    // Etiket filtresi. Etiketler serbest metin olduğu için SQL tarafında
    // yalnızca kabaca daraltıyoruz; kesin eşleşme PHP tarafında slug
    // karşılaştırmasıyla yapılıyor (büyük/küçük harf ve Türkçe karakter farkı).
    $tagSlug = trim((string)($opts['tag'] ?? ''));
    $tagliMi = $tagSlug !== '';

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sirala = 'ORDER BY (published_at IS NULL), published_at DESC, id DESC';

    if ($tagliMi) {
        // Etiketli listede sayfalama PHP tarafında: etiket eşleşmesi slug
        // bazlı olduğu için SQL COUNT'u yanıltıcı olurdu.
        $st = bm_db()->prepare("SELECT * FROM articles $whereSql $sirala LIMIT 500");
        $st->execute($params);
        $hepsi = array_values(array_filter($st->fetchAll(), function ($m) use ($tagSlug) {
            foreach (bm_article_tag_list($m['tags'] ?? '') as $t) {
                if (bm_article_tag_slug($t) === $tagSlug) return true;
            }
            return false;
        }));
        $total = count($hepsi);
        $perPage = (int)($opts['per_page'] ?? BM_ARTICLES_PER_PAGE);
        if ($perPage < 1) $perPage = BM_ARTICLES_PER_PAGE;
        $pages = max(1, (int)ceil($total / $perPage));
        $page  = max(1, min($pages, (int)($opts['page'] ?? 1)));
        $items = array_slice($hepsi, ($page - 1) * $perPage, $perPage);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    $st = bm_db()->prepare("SELECT COUNT(*) FROM articles $whereSql");
    $st->execute($params);
    $total = (int)$st->fetchColumn();

    $perPage = (int)($opts['per_page'] ?? BM_ARTICLES_PER_PAGE);
    if ($perPage < 1) $perPage = BM_ARTICLES_PER_PAGE;
    $pages  = max(1, (int)ceil($total / $perPage));
    $page   = max(1, min($pages, (int)($opts['page'] ?? 1)));
    $offset = ($page - 1) * $perPage;

    $st = bm_db()->prepare("SELECT * FROM articles $whereSql $sirala LIMIT $perPage OFFSET $offset");
    $st->execute($params);

    return [
        'items' => $st->fetchAll(), 'total' => $total,
        'page' => $page, 'pages' => $pages, 'per_page' => $perPage,
    ];
}

/**
 * Yayındaki makalelerde geçen bütün etiketler ve kaç makalede kullanıldığı.
 * Blog sayfasındaki filtre çipleri bununla basılıyor.
 */
function bm_article_tags($includeInactive = false) {
    if (!bm_articles_ready()) return [];
    $sql = 'SELECT tags FROM articles' . ($includeInactive ? '' : ' WHERE is_active = 1');
    $sayac = [];
    foreach (bm_db()->query($sql) as $satir) {
        foreach (bm_article_tag_list($satir['tags'] ?? '') as $t) {
            $slug = bm_article_tag_slug($t);
            if ($slug === '') continue;
            if (!isset($sayac[$slug])) $sayac[$slug] = ['slug' => $slug, 'label' => $t, 'count' => 0];
            $sayac[$slug]['count']++;
        }
    }
    uasort($sayac, function ($a, $b) {
        return $b['count'] <=> $a['count'] ?: strcasecmp($a['label'], $b['label']);
    });
    return array_values($sayac);
}

/** Etiket slug'ından görünen adı bulur (başlıkta kullanılıyor) */
function bm_article_tag_label($slug) {
    foreach (bm_article_tags(true) as $t) {
        if ($t['slug'] === $slug) return $t['label'];
    }
    return '';
}

/** Tek makale — adres parçasıyla */
function bm_article_by_slug($slug, $includeInactive = false) {
    if (!bm_articles_ready()) return null;
    $sql = 'SELECT * FROM articles WHERE slug = ?' . ($includeInactive ? '' : ' AND is_active = 1');
    $st = bm_db()->prepare($sql);
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}

/** Tek makale — id ile (panel için; yayında olmayanlar da gelir) */
function bm_article_by_id($id) {
    if (!bm_articles_ready()) return null;
    $st = bm_db()->prepare('SELECT * FROM articles WHERE id = ?');
    $st->execute([(int)$id]);
    return $st->fetch() ?: null;
}

/**
 * Benzer makaleler: önce ortak etiketi olanlar, yetmezse en yeniler.
 * Makale sonunda "Bunlar da ilgini çekebilir" bölümünü besliyor.
 */
function bm_related_articles($makale, $limit = 3) {
    if (!bm_articles_ready()) return [];
    $st = bm_db()->prepare(
        'SELECT * FROM articles WHERE is_active = 1 AND id <> ?
          ORDER BY (published_at IS NULL), published_at DESC, id DESC LIMIT 60'
    );
    $st->execute([(int)($makale['id'] ?? 0)]);
    $aday = $st->fetchAll();

    $benimEtiketler = array_map('bm_article_tag_slug', bm_article_tag_list($makale['tags'] ?? ''));
    $ortak = [];
    $diger = [];
    foreach ($aday as $m) {
        $kesisim = array_intersect($benimEtiketler, array_map('bm_article_tag_slug', bm_article_tag_list($m['tags'] ?? '')));
        if ($kesisim) $ortak[] = $m; else $diger[] = $m;
    }
    return array_slice(array_merge($ortak, $diger), 0, $limit);
}

/** Başlıktan benzersiz adres parçası üretir */
function bm_unique_article_slug($baslik, $ignoreId = null) {
    $temel = bm_slugify($baslik);
    if ($temel === '') $temel = 'makale';
    $slug = $temel;
    $i = 2;
    while (true) {
        $sql = 'SELECT COUNT(*) FROM articles WHERE slug = ?' . ($ignoreId ? ' AND id <> ?' : '');
        $st = bm_db()->prepare($sql);
        $st->execute($ignoreId ? [$slug, (int)$ignoreId] : [$slug]);
        if (!(int)$st->fetchColumn()) return $slug;
        $slug = $temel . '-' . $i++;
    }
}

/** Kart ve liste için kısa özet — özet girilmediyse gövdeden üretilir */
function bm_article_summary($makale, $uzunluk = 160) {
    $ozet = trim((string)($makale['summary'] ?? ''));
    if ($ozet === '') {
        $ozet = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($makale['body'] ?? ''))));
    }
    if (mb_strlen($ozet) > $uzunluk) {
        $ozet = mb_substr($ozet, 0, $uzunluk);
        $son = mb_strrpos($ozet, ' ');
        if ($son > 40) $ozet = mb_substr($ozet, 0, $son);
        $ozet .= '…';
    }
    return $ozet;
}

/**
 * Makalenin arama motoru kaydı (Article).
 *
 * Google haber/makale sonuçlarında başlık, tarih ve görseli buradan okuyor;
 * marka aramasında da sitenin "yazı üreten" tarafını tanımasına yardım ediyor.
 */
function bm_article_jsonld($makale, $content = null) {
    $kok = bm_site_url();
    $veri = [
        '@context'         => 'https://schema.org',
        '@type'            => 'Article',
        'headline'         => (string)($makale['title'] ?? ''),
        'description'      => bm_article_summary($makale, 200),
        'mainEntityOfPage' => $kok . '/makale/' . rawurlencode((string)($makale['slug'] ?? '')),
        'publisher'        => [
            '@type' => 'Organization',
            'name'  => bm_t('site.name', 'Beyli Makina Sanayi ve Ticaret Ltd. Şti.'),
            'logo'  => ['@type' => 'ImageObject', 'url' => $kok . '/images/favicon-192.png'],
        ],
    ];
    $gorsel = trim((string)($makale['image'] ?? ''));
    if ($gorsel !== '') {
        $veri['image'] = strpos($gorsel, 'http') === 0 ? $gorsel : $kok . $gorsel;
    }
    $tarih = trim((string)($makale['published_at'] ?? ''));
    if ($tarih !== '' && $tarih !== '0000-00-00') $veri['datePublished'] = $tarih;

    $yazar = trim((string)($makale['author'] ?? ''));
    $veri['author'] = $yazar !== ''
        ? ['@type' => 'Person', 'name' => $yazar]
        : ['@type' => 'Organization', 'name' => bm_t('site.name', 'Beyli Makina Sanayi ve Ticaret Ltd. Şti.')];

    return json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

/** "14.07.2026" biçiminde tarih; tarih yoksa boş döner */
function bm_article_date($makale) {
    $t = trim((string)($makale['published_at'] ?? ''));
    if ($t === '' || $t === '0000-00-00') return '';
    $zaman = strtotime($t);
    return $zaman ? date('d.m.Y', $zaman) : '';
}
