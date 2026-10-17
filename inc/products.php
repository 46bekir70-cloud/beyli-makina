<?php
/**
 * Ürün kataloğu sorguları.
 *
 * 1000+ kalem hedeflendiği için listeleme her zaman sayfalıdır ve arama
 * veritabanı tarafında yapılır — hiçbir yerde bütün ürünler belleğe alınmaz.
 */

require_once __DIR__ . '/db.php';

/** Sayfa başına gösterilecek ürün sayısı */
define('BM_PRODUCTS_PER_PAGE', 24);

/**
 * products tablosundaki sütun adları — istek başına bir kez sorulur.
 * Sonradan eklenen sütunlar (ör. image_focus) kurulu veritabanlarında
 * bulunmayabiliyor; yazma sorguları buna bakarak kendini ayarlıyor.
 */
function bm_product_columns($yenile = false) {
    static $cols = null;
    if ($cols !== null && !$yenile) return $cols;
    $cols = [];
    if (!bm_db_ready()) return $cols;
    try {
        foreach (bm_db()->query('SHOW COLUMNS FROM products') as $r) {
            $cols[] = $r['Field'];
        }
    } catch (Throwable $e) {
        $cols = [];
    }
    return $cols;
}

/** Belirtilen sütun products tablosunda var mı? */
function bm_products_has($sutun) {
    return in_array($sutun, bm_product_columns(), true);
}

/** Görsel odak noktası sütunu var mı? (geriye dönük ad) */
function bm_products_has_focus() {
    return bm_products_has('image_focus');
}

/**
 * Sürüm yükseltmeleriyle eklenen sütunları tamamlar. Ürün düzenleme ekranı
 * açılırken çağrılıyor; eksik varsa bir kez ekliyor, sonrasında yalnızca sütun
 * kontrolü yapıyor. Böylece ayrıca "veritabanını güncelle" adımı gerekmiyor.
 */
function bm_products_migrate() {
    if (!bm_db_ready()) return false;
    $ekle = [];
    if (!bm_products_has('image_focus')) {
        $ekle[] = 'ADD COLUMN image_focus VARCHAR(16) NULL AFTER image';
    }
    if (!bm_products_has('image_zoom')) {
        $ekle[] = 'ADD COLUMN image_zoom SMALLINT UNSIGNED NULL AFTER image_focus';
    }
    // Ürün sayfasındaki "farklı ölçü ve malzemede de üretebiliriz" kutusu
    // her parça için doğru olmuyor (stok/standart parçalarda yanıltıcı).
    // Varsayılan 1: mevcut ürünlerin görünümü değişmesin.
    if (!bm_products_has('show_note')) {
        $ekle[] = 'ADD COLUMN show_note TINYINT(1) NOT NULL DEFAULT 1';
    }
    // Kartta ve ürün sayfasında görünen kategori yazısı. Boş bırakılırsa
    // kategori yolu otomatik yazılıyor (İPLİK › OPEN-END › S11); uzun kalırsa
    // buraya kısa bir metin girilip elle kısaltılabiliyor.
    if (!bm_products_has('card_label')) {
        $ekle[] = 'ADD COLUMN card_label VARCHAR(190) NULL';
    }
    // Teklif formunda bu ürün için önceden seçili gelecek "Parça/Ürün Türü"
    if (!bm_products_has('quote_type')) {
        $ekle[] = 'ADD COLUMN quote_type VARCHAR(120) NULL';
    }
    if (!$ekle) return false;
    try {
        bm_db()->exec('ALTER TABLE products ' . implode(', ', $ekle));
    } catch (Throwable $e) {
        return false;
    }
    bm_product_columns(true); // sütun listesini tazele
    return true;
}

/** Kategori tablosunun sütunları (alt kategori desteği sonradan eklendi) */
function bm_category_columns($yenile = false) {
    static $cols = null;
    if ($cols !== null && !$yenile) return $cols;
    $cols = [];
    if (!bm_db_ready()) return $cols;
    try {
        foreach (bm_db()->query('SHOW COLUMNS FROM product_categories') as $r) $cols[] = $r['Field'];
    } catch (Throwable $e) { $cols = []; }
    return $cols;
}

/** Alt kategori (ağaç) desteği kurulu mu? */
function bm_categories_has_tree() {
    return in_array('parent_id', bm_category_columns(), true);
}

/** Bir ürünün birden fazla kategoride olmasını sağlayan ara tablo var mı? */
function bm_has_category_map($yenile = false) {
    static $var = null;
    if ($var !== null && !$yenile) return $var;
    $var = false;
    if (!bm_db_ready()) return $var;
    try {
        $var = (bool)bm_db()->query("SHOW TABLES LIKE 'product_category_map'")->fetchColumn();
    } catch (Throwable $e) { $var = false; }
    return $var;
}

/**
 * Kategori yapısını günceller: alt kategori sütunu + çoklu kategori ara tablosu.
 * Kategoriler ve ürün düzenleme ekranları açılırken çağrılıyor; eksik olan
 * varsa bir kez kurulur, mevcut veri korunur.
 */
function bm_categories_migrate() {
    if (!bm_db_ready()) return false;
    $degisti = false;

    if (!bm_categories_has_tree()) {
        try {
            bm_db()->exec('ALTER TABLE product_categories
                           ADD COLUMN parent_id INT UNSIGNED NULL AFTER id,
                           ADD KEY idx_cat_parent (parent_id)');
            bm_category_columns(true);
            $degisti = true;
        } catch (Throwable $e) { /* yetki yoksa eski düzende çalışmaya devam */ }
    }

    if (!bm_has_category_map()) {
        try {
            bm_db()->exec("CREATE TABLE IF NOT EXISTS product_category_map (
                product_id  INT UNSIGNED NOT NULL,
                category_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (product_id, category_id),
                KEY idx_map_category (category_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            // Mevcut tek kategorili ürünler ara tabloya taşınır — hiçbir ürün
            // kategorisiz kalmasın.
            bm_db()->exec('INSERT IGNORE INTO product_category_map (product_id, category_id)
                           SELECT id, category_id FROM products WHERE category_id IS NOT NULL');
            bm_has_category_map(true); // önbelleği tazele, yoksa aynı istekte "yok" görünüyor
            $degisti = true;
        } catch (Throwable $e) { /* oluşturulamadıysa tek kategorili düzen sürer */ }
    }
    return $degisti;
}

/**
 * Tüm kategoriler (ürün sayılarıyla). Ara tablo varsa sayım oradan yapılır,
 * böylece ikinci/üçüncü kategorisine eklenen ürün de sayılır.
 */
function bm_categories($onlyWithProducts = false) {
    if (!bm_db_ready()) return [];
    $ustSutun = bm_categories_has_tree() ? 'c.parent_id' : 'NULL AS parent_id';

    if (bm_has_category_map()) {
        $sql = "SELECT c.id, $ustSutun, c.slug, c.label, c.sort_order,
                       COUNT(DISTINCT p.id) AS product_count
                  FROM product_categories c
                  LEFT JOIN product_category_map m ON m.category_id = c.id
                  LEFT JOIN products p ON p.id = m.product_id AND p.is_active = 1
                 GROUP BY c.id, c.parent_id, c.slug, c.label, c.sort_order";
    } else {
        $sql = "SELECT c.id, $ustSutun, c.slug, c.label, c.sort_order,
                       COUNT(p.id) AS product_count
                  FROM product_categories c
                  LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
                 GROUP BY c.id, c.slug, c.label, c.sort_order";
    }
    // NOT: alt kategorileri de kapsayan sayılar bm_category_tree() içinde
    // hesaplanıyor; buradaki sayı yalnızca o kategorinin kendi ürünleri.
    $sql .= ' ORDER BY c.sort_order, c.label';
    $satirlar = bm_db()->query($sql)->fetchAll();

    if ($onlyWithProducts) {
        // Ağaçta üst kategorinin kendi ürünü olmasa da altında varsa görünmeli
        $toplam = bm_category_counts_with_children($satirlar);
        $satirlar = array_values(array_filter($satirlar, function ($c) use ($toplam) {
            return ($toplam[$c['id']] ?? 0) > 0;
        }));
    }
    return $satirlar;
}

/**
 * Hangi kategoride hangi ürünler var: [kategori_id => [urun_id => true]]
 *
 * Sayımların ürün kimliği üzerinden yapılabilmesi için gerekiyor. Kategori
 * ağacında bir ürün birden fazla dala eklenebildiği için "sayıları toplama"
 * yöntemi yanlış sonuç veriyordu (aşağıya bakın).
 *
 * Sorgu bir kez yapılıp saklanıyor; kategori/ürün kaydı değiştiren yerler
 * $yenile = true ile çağırıp tazeliyor.
 */
function bm_category_product_map($yenile = false) {
    static $harita = null;
    if ($harita !== null && !$yenile) return $harita;

    $harita = [];
    if (!bm_db_ready()) return $harita;

    if (bm_has_category_map()) {
        $sql = 'SELECT m.category_id, m.product_id
                  FROM product_category_map m
                  JOIN products p ON p.id = m.product_id
                 WHERE p.is_active = 1';
    } else {
        // Ara tablo yoksa ürünün tek kategorisi var
        $sql = 'SELECT p.category_id, p.id AS product_id
                  FROM products p
                 WHERE p.is_active = 1 AND p.category_id IS NOT NULL';
    }

    foreach (bm_db()->query($sql)->fetchAll() as $satir) {
        $cid = (int)($satir['category_id'] ?? 0);
        $pid = (int)($satir['product_id'] ?? 0);
        if (!$cid || !$pid) continue;
        $harita[$cid][$pid] = true;
    }
    return $harita;
}

/**
 * Her kategori için "kendi + altındaki tüm kategoriler" ürün sayısı.
 *
 * ÖNEMLİ: Sayılar toplanmaz, ÜRÜN KİMLİKLERİ birleştirilip tekil saydırılır.
 * Bir ürün hem "İPLİK" hem onun altındaki "OPEN-END" kategorisine eklenmişse,
 * eski yöntem (kendi sayısı + çocukların sayısı) o ürünü iki kez saydığı için
 * filtrede "İPLİK (2)" görünüp listede tek ürün çıkıyordu. Çok katmanlı
 * ağaçlarda hata daha da büyüyordu (her seviyede bir kez daha sayılıyordu).
 * Artık kaç dala eklenmiş olursa olsun her ürün bir kez sayılıyor ve sayılar
 * listedeki sonuç sayısıyla birebir tutuyor.
 */
function bm_category_counts_with_children($kategoriler) {
    $cocuk = [];
    $kendi = [];
    foreach ($kategoriler as $c) {
        $id = (int)$c['id'];
        $kendi[$id] = (int)$c['product_count'];
        $ust = (int)($c['parent_id'] ?? 0);
        if ($ust) $cocuk[$ust][] = $id;
    }

    $harita = bm_category_product_map();

    if ($harita) {
        // Alt ağacın tüm ürün kimliklerini tek kümede birleştirir.
        // ("+" birleştirmesi aynı anahtarı iki kez eklemez, tekillik buradan gelir.)
        $kume = function ($id) use (&$kume, $cocuk, $harita) {
            $set = $harita[$id] ?? [];
            foreach ($cocuk[$id] ?? [] as $c) {
                $set += $kume($c);
            }
            return $set;
        };
        $out = [];
        foreach ($kendi as $id => $_) $out[$id] = count($kume($id));
        return $out;
    }

    // Veritabanı okunamıyorsa eldeki sayılarla yetin (eski davranış)
    $topla = function ($id) use (&$topla, $cocuk, $kendi) {
        $t = $kendi[$id] ?? 0;
        foreach ($cocuk[$id] ?? [] as $c) $t += $topla($c);
        return $t;
    };
    $out = [];
    foreach ($kendi as $id => $_) $out[$id] = $topla($id);
    return $out;
}

/**
 * Kategorileri ağaç hâlinde döndürür: her satıra 'children', 'depth' ve
 * 'total_count' (kendi + alt kategoriler) eklenir.
 */
function bm_category_tree($onlyWithProducts = false) {
    $duz = bm_categories($onlyWithProducts);
    $toplam = bm_category_counts_with_children($duz);

    $indeks = [];
    foreach ($duz as $c) {
        $c['children'] = [];
        $c['total_count'] = $toplam[$c['id']] ?? (int)$c['product_count'];
        $indeks[$c['id']] = $c;
    }
    $agac = [];
    foreach ($indeks as $id => $c) {
        $ust = $c['parent_id'] ?? null;
        if ($ust && isset($indeks[$ust])) continue; // çocuk, aşağıda eklenecek
        $agac[$id] = &$indeks[$id];
    }
    // Çocukları yerleştir (referansla, iki seviyeden derin de çalışır)
    foreach ($indeks as $id => $c) {
        $ust = $c['parent_id'] ?? null;
        if ($ust && isset($indeks[$ust])) {
            $indeks[$ust]['children'][$id] = &$indeks[$id];
        }
    }
    /**
     * Sıralama: önce paneldeki Sıra alanı (küçük sayı önce).
     *
     * Eskiden ölçüt ürün sayısıydı, Sıra alanı yalnızca başa baş durumda
     * devreye giriyordu; bu yüzden panelde sıra yazılsa da kategori listesi
     * değişmiyor gibi görünüyordu. Sıra eşitse (örn. hepsi 0) çok ürünlü
     * kategori üstte kalır — ölçüt "kendi + alt kategoriler" toplamı
     * (total_count), yani üst kategorinin kendi ürünü olmasa da altındakiler
     * onu yukarı taşır. O da eşitse ad belirler. Her seviye kendi içinde
     * sıralanıyor, ağaç yapısı bozulmuyor.
     */
    $sirala = function (&$dugumler) use (&$sirala) {
        uasort($dugumler, function ($a, $b) {
            $fark = (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0);
            if ($fark !== 0) return $fark;
            $fark = (int)$b['total_count'] <=> (int)$a['total_count'];
            if ($fark !== 0) return $fark;
            return strcoll((string)$a['label'], (string)$b['label']);
        });
        foreach ($dugumler as &$d) {
            if (!empty($d['children'])) $sirala($d['children']);
        }
        unset($d);
    };
    $sirala($agac);

    // Derinlik bilgisi
    $derinlikVer = function (&$dugumler, $derinlik) use (&$derinlikVer) {
        foreach ($dugumler as &$d) {
            $d['depth'] = $derinlik;
            if (!empty($d['children'])) $derinlikVer($d['children'], $derinlik + 1);
        }
    };
    $derinlikVer($agac, 0);
    return array_values($agac);
}

/** Ağacı tek düz listeye çevirir (girinti için depth taşır) */
function bm_category_flat($agac = null, $onlyWithProducts = false) {
    if ($agac === null) $agac = bm_category_tree($onlyWithProducts);
    $out = [];
    foreach ($agac as $d) {
        $cocuklar = $d['children'] ?? [];
        unset($d['children']);
        $out[] = $d;
        if ($cocuklar) $out = array_merge($out, bm_category_flat(array_values($cocuklar)));
    }
    return $out;
}

/** Bir kategorinin üst zinciri (en üstten kendisine kadar id listesi) */
function bm_category_path($id) {
    $id = (int)$id;
    $ustler = [];
    foreach (bm_categories() as $c) $ustler[(int)$c['id']] = (int)($c['parent_id'] ?? 0);
    $yol = [];
    $guard = 0;
    while ($id && isset($ustler[$id]) && $guard++ < 20) {
        array_unshift($yol, $id);
        $id = $ustler[$id];
    }
    return $yol;
}

/** Bir kategorinin kendisi + tüm alt kategorilerinin id listesi */
function bm_category_descendant_ids($id) {
    $id = (int)$id;
    $ilişki = [];
    foreach (bm_categories() as $c) {
        $ust = $c['parent_id'] ?? null;
        if ($ust) $ilişki[(int)$ust][] = (int)$c['id'];
    }
    $out = [$id];
    $sira = [$id];
    while ($sira) {
        $g = array_shift($sira);
        foreach ($ilişki[$g] ?? [] as $c) {
            if (!in_array($c, $out, true)) { $out[] = $c; $sira[] = $c; }
        }
    }
    return $out;
}

/** Bir ürünün bağlı olduğu bütün kategori id'leri */
function bm_product_category_ids($productId) {
    $productId = (int)$productId;
    if (!$productId || !bm_db_ready()) return [];
    if (bm_has_category_map()) {
        $st = bm_db()->prepare('SELECT category_id FROM product_category_map WHERE product_id = ?');
        $st->execute([$productId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    $st = bm_db()->prepare('SELECT category_id FROM products WHERE id = ?');
    $st->execute([$productId]);
    $tek = $st->fetchColumn();
    return $tek ? [(int)$tek] : [];
}

/**
 * Ürünün kategorilerini yazar (eskileri silip yenilerini ekler).
 * İlk kategori "ana kategori" olarak products.category_id'ye de yazılır —
 * kartlarda ve ürün sayfasında görünen etiket odur.
 */
function bm_set_product_categories($productId, $categoryIds) {
    $productId = (int)$productId;
    if (!$productId || !bm_db_ready()) return;
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)$categoryIds))));

    if (bm_has_category_map()) {
        $st = bm_db()->prepare('DELETE FROM product_category_map WHERE product_id = ?');
        $st->execute([$productId]);
        if ($ids) {
            $ekle = bm_db()->prepare('INSERT IGNORE INTO product_category_map (product_id, category_id) VALUES (?, ?)');
            foreach ($ids as $cid) $ekle->execute([$productId, $cid]);
        }
    }
    $st = bm_db()->prepare('UPDATE products SET category_id = ? WHERE id = ?');
    $st->execute([$ids ? $ids[0] : null, $productId]);

    // Sayımların kullandığı harita eskidi; aynı istekte tekrar okunursa taze olsun
    bm_category_product_map(true);
}

/**
 * Ürünün bütün kategorileri (etiket ve adresleriyle), ağaç sırasında.
 * Ürün sayfasında rozet olarak gösteriliyor — ziyaretçi parçanın hangi
 * gruplarda geçtiğini görüp o listelere geçebiliyor.
 */
/**
 * Bir ürünün ana kategori yolunu metin olarak verir: "İPLİK › OPEN-END › S11".
 *
 * Kartta ve ürün sayfasında yalnızca en alt kategori yazınca ("S11") parçanın
 * hangi makineye ait olduğu anlaşılmıyordu. Ürün kaydında card_label doluysa
 * onun yerine o kullanılır — yol çok uzun kalırsa panelden kısaltılabilsin diye.
 */
function bm_product_category_path_label($product, $ayrac = ' › ') {
    $ozel = trim((string)($product['card_label'] ?? ''));
    if ($ozel !== '') return $ozel;

    $catId = (int)($product['category_id'] ?? 0);
    if (!$catId || !bm_categories_has_tree()) {
        return (string)($product['cat_label'] ?? '');
    }
    $duz = [];
    foreach (bm_category_flat(null) as $c) $duz[(int)$c['id']] = $c['label'];

    $parcalar = [];
    foreach (bm_category_path($catId) as $cid) {
        if (isset($duz[$cid])) $parcalar[] = $duz[$cid];
    }
    return $parcalar ? implode($ayrac, $parcalar) : (string)($product['cat_label'] ?? '');
}

function bm_product_categories_full($productId) {
    $idler = bm_product_category_ids($productId);
    if (!$idler) return [];
    $out = [];
    foreach (bm_category_flat(null) as $c) {
        if (in_array((int)$c['id'], $idler, true)) $out[] = $c;
    }
    return $out;
}

/** Ürün silinince ara tablodaki kayıtları da temizle */
function bm_delete_product_categories($productId) {
    if (!bm_has_category_map() || !bm_db_ready()) return;
    $st = bm_db()->prepare('DELETE FROM product_category_map WHERE product_id = ?');
    $st->execute([(int)$productId]);
    bm_category_product_map(true);
}

/**
 * Ürünün bulunduğu kategorileri TAM YOL olarak döndürür.
 *
 * Dönen biçim: her yol için bir dizi, içinde sırayla üstten alta kategoriler:
 *   [ [ ['label'=>'İPLİK','slug'=>'iplik','direct'=>true],
 *       ['label'=>'OPEN-END','slug'=>'open-end','direct'=>false],
 *       ['label'=>'S11','slug'=>'s11','direct'=>true] ], ... ]
 *
 * - direct = ürünün doğrudan eklendiği kategori (panelde işaretli olan)
 * - direct olmayanlar sadece üst zincir; ziyaretçi nerede olduğunu görsün diye var
 * - Bir yol, başka bir yolun başlangıcı ise (örn. ürün hem "İPLİK"e hem
 *   "İPLİK > OPEN-END"e eklenmişse) kısa olan ayrıca yazılmaz; uzun yolun
 *   içinde öyleğine görünür. Böylece aynı dal iki kez tekrar etmez.
 */
function bm_product_category_paths($productId) {
    $dogrudan = bm_product_category_ids($productId);
    if (!$dogrudan) return [];

    $duz = [];
    foreach (bm_category_flat(null) as $c) $duz[(int)$c['id']] = $c;

    // Her doğrudan kategori için üst zinciriyle birlikte yolu kur
    $yollar = [];
    foreach ($dogrudan as $cid) {
        $cid = (int)$cid;
        if (!isset($duz[$cid])) continue;
        $zincir = bm_category_path($cid);
        if (!$zincir) $zincir = [$cid];
        $yol = [];
        foreach ($zincir as $adim) {
            if (!isset($duz[$adim])) continue;
            $yol[] = [
                'id'     => (int)$adim,
                'label'  => (string)$duz[$adim]['label'],
                'slug'   => (string)$duz[$adim]['slug'],
                'direct' => in_array((int)$adim, array_map('intval', $dogrudan), true),
            ];
        }
        if ($yol) $yollar[] = $yol;
    }
    if (!$yollar) return [];

    // Uzun yolu kısa yola tercih et: "İPLİK" yolunu "İPLİK > OPEN-END" kapsıyor
    $kimlikler = array_map(function ($y) {
        return implode('/', array_column($y, 'id'));
    }, $yollar);

    $sonuc = [];
    foreach ($yollar as $i => $yol) {
        $kapsandi = false;
        foreach ($kimlikler as $j => $kimlik) {
            if ($i === $j) continue;
            if (strpos($kimlik . '/', $kimlikler[$i] . '/') === 0) { $kapsandi = true; break; }
        }
        if (!$kapsandi) $sonuc[] = $yol;
    }

    // Uzun yollar (derin dallar) üstte dursun, sonra alfabetik
    usort($sonuc, function ($a, $b) {
        $fark = count($b) <=> count($a);
        if ($fark !== 0) return $fark;
        return strcoll((string)$a[0]['label'], (string)$b[0]['label']);
    });

    return $sonuc;
}

/** Slug'a göre tek kategori */
function bm_category_by_slug($slug) {
    if (!bm_db_ready()) return null;
    $st = bm_db()->prepare('SELECT * FROM product_categories WHERE slug = ? LIMIT 1');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}

/**
 * Ürünleri filtreli/aramalı/sayfalı getirir.
 *
 * $opts: category (slug), q (arama metni), page, per_page,
 *        featured (bool), include_inactive (bool, admin için), sort
 *
 * Dönüş: ['items' => [...], 'total' => int, 'page' => int, 'pages' => int]
 */
function bm_products($opts = []) {
    if (!bm_db_ready()) {
        return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
    }

    $where  = [];
    $params = [];

    if (empty($opts['include_inactive'])) {
        $where[] = 'p.is_active = 1';
    }

    if (!empty($opts['category'])) {
        $kat = bm_category_by_slug($opts['category']);
        if (!$kat) {
            // Olmayan kategori: boş sonuç (eskiden tüm ürünleri getiriyordu)
            $where[] = '1 = 0';
        } elseif (bm_has_category_map()) {
            // Alt kategoriler de kapsanır: "CNC" seçilince altındaki
            // "Torna", "Freze" ürünleri de listelenir.
            $idler = bm_category_descendant_ids($kat['id']);
            $yerler = implode(',', array_map('intval', $idler));
            $where[] = "p.id IN (SELECT product_id FROM product_category_map
                                  WHERE category_id IN ($yerler))";
        } else {
            $where[] = 'c.slug = :cat';
            $params[':cat'] = $opts['category'];
        }
    }

    if (!empty($opts['featured'])) {
        $where[] = 'p.is_featured = 1';
    }

    $q = trim((string)($opts['q'] ?? ''));
    if ($q !== '') {
        // Parça kodlarında parça arama yapılabilmesi için LIKE kullanıyoruz;
        // FULLTEXT kelime bazlı çalıştığı için "M8-40" gibi kodlarda işe yaramıyor.
        // Not: PDO gerçek (emüle edilmemiş) hazır sorgularda aynı isimli
        // parametrenin tekrar kullanılmasına izin vermiyor — her sütun için
        // ayrı isim veriyoruz.
        $where[] = '(p.title LIKE :q1 OR p.code LIKE :q2 OR p.description LIKE :q3 OR c.label LIKE :q4)';
        $like = '%' . bm_escape_like($q) . '%';
        $params[':q1'] = $like;
        $params[':q2'] = $like;
        $params[':q3'] = $like;
        $params[':q4'] = $like;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countSql = "SELECT COUNT(*) FROM products p
                 LEFT JOIN product_categories c ON c.id = p.category_id
                 $whereSql";
    $st = bm_db()->prepare($countSql);
    $st->execute($params);
    $total = (int)$st->fetchColumn();

    $perPage = (int)($opts['per_page'] ?? BM_PRODUCTS_PER_PAGE);
    if ($perPage < 1) $perPage = BM_PRODUCTS_PER_PAGE;
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = max(1, min($pages, (int)($opts['page'] ?? 1)));
    $offset = ($page - 1) * $perPage;

    $order = 'p.sort_order, p.id DESC';
    switch ($opts['sort'] ?? '') {
        case 'title':  $order = 'p.title'; break;
        case 'newest': $order = 'p.id DESC'; break;
    }

    $sql = "SELECT p.*, c.slug AS cat_slug, c.label AS cat_label
              FROM products p
              LEFT JOIN product_categories c ON c.id = p.category_id
              $whereSql
             ORDER BY $order
             LIMIT $perPage OFFSET $offset";
    $st = bm_db()->prepare($sql);
    $st->execute($params);
    $items = $st->fetchAll();

    foreach ($items as &$item) {
        $item['specs'] = bm_decode_specs($item['specs']);
    }
    unset($item);

    return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
}

/** Tek ürün (slug ile) */
function bm_product_by_slug($slug, $includeInactive = false) {
    if (!bm_db_ready()) return null;
    $sql = 'SELECT p.*, c.slug AS cat_slug, c.label AS cat_label
              FROM products p
              LEFT JOIN product_categories c ON c.id = p.category_id
             WHERE p.slug = ?';
    if (!$includeInactive) $sql .= ' AND p.is_active = 1';
    $sql .= ' LIMIT 1';
    $st = bm_db()->prepare($sql);
    $st->execute([$slug]);
    $row = $st->fetch();
    if (!$row) return null;
    $row['specs'] = bm_decode_specs($row['specs']);
    return $row;
}

/** Tek ürün (id ile — admin tarafı için) */
function bm_product_by_id($id) {
    if (!bm_db_ready()) return null;
    $st = bm_db()->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $st->execute([(int)$id]);
    $row = $st->fetch();
    if (!$row) return null;
    $row['specs'] = bm_decode_specs($row['specs']);
    return $row;
}

/** Aynı kategoriden başka ürünler (detay sayfasının altı için) */
function bm_related_products($product, $limit = 4) {
    if (!bm_db_ready() || empty($product['category_id'])) return [];
    $limit = (int)$limit;
    $st = bm_db()->prepare(
        "SELECT p.*, c.slug AS cat_slug, c.label AS cat_label
           FROM products p
           LEFT JOIN product_categories c ON c.id = p.category_id
          WHERE p.category_id = ? AND p.id <> ? AND p.is_active = 1
          ORDER BY p.sort_order, p.id DESC
          LIMIT $limit"
    );
    $st->execute([$product['category_id'], $product['id']]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) { $r['specs'] = bm_decode_specs($r['specs']); }
    unset($r);
    return $rows;
}

/**
 * Gösterime uygun teknik özellikleri döndürür.
 *
 * Bir özellik "etiket + değer" çiftidir; yarısı boş bırakılmış satırlar
 * (ör. etiket yazılıp değer girilmemiş) kartlarda ve tabloda boş hücre olarak
 * göründüğü için burada ayıklanıyor. Veri silinmiyor, sadece gösterilmiyor —
 * panelden düzeltilince kendiliğinden geri gelir.
 */
function bm_visible_specs($specs) {
    $out = [];
    foreach ((array)$specs as $spec) {
        $label = trim((string)($spec[0] ?? ''));
        $value = trim((string)($spec[1] ?? ''));
        if ($label === '' || $value === '') continue;
        $out[] = [$label, $value];
    }
    return $out;
}

/** specs alanını diziye çevirir (bozuk veri gelirse boş dizi) */
function bm_decode_specs($raw) {
    if (is_array($raw)) return $raw;
    if (!$raw) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/** LIKE içinde geçen % ve _ karakterlerini kaçır */
function bm_escape_like($str) {
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $str);
}

/**
 * Benzersiz slug üretir. Aynı isimde ürün varsa sonuna -2, -3 ekler.
 * $ignoreId: düzenleme sırasında ürünün kendi kaydını hesaba katmamak için.
 */
function bm_unique_product_slug($title, $ignoreId = null) {
    $base = bm_slugify($title);
    $slug = $base;
    $i = 2;
    while (true) {
        $sql = 'SELECT id FROM products WHERE slug = ?';
        $params = [$slug];
        if ($ignoreId) { $sql .= ' AND id <> ?'; $params[] = (int)$ignoreId; }
        $st = bm_db()->prepare($sql . ' LIMIT 1');
        $st->execute($params);
        if (!$st->fetch()) return $slug;
        $slug = $base . '-' . $i;
        $i++;
    }
}
