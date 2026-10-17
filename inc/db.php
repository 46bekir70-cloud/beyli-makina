<?php
/**
 * Yapılandırma okuma + veritabanı bağlantısı.
 *
 * Ayarlar inc/config.php dosyasından gelir (örneği: inc/config.ornek.php).
 * Bağlantı ilk ihtiyaç duyulduğunda kurulur; sayfa veritabanı kullanmıyorsa
 * hiç bağlanılmaz.
 */

require_once __DIR__ . '/functions.php';

define('BM_CONFIG_FILE', __DIR__ . '/config.php');

/** Tüm yapılandırmayı (veya tek bir bölümünü) döndürür. */
function bm_config($section = null) {
    static $config = null;
    if ($config === null) {
        if (!file_exists(BM_CONFIG_FILE)) {
            $config = [];
        } else {
            $loaded = require BM_CONFIG_FILE;
            $config = is_array($loaded) ? $loaded : [];
        }
    }
    if ($section === null) return $config;
    return $config[$section] ?? [];
}

/** Yapılandırma dosyası oluşturulmuş mu? */
function bm_config_exists() {
    return file_exists(BM_CONFIG_FILE);
}

/**
 * PDO bağlantısı (tekil). Bağlanamazsa BM_DbError fırlatır — çağıran taraf
 * kullanıcıya anlaşılır bir mesaj gösterebilsin diye die() kullanılmıyor.
 */
function bm_db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $db = bm_config('db');
    if (empty($db['name'])) {
        throw new RuntimeException('Veritabanı ayarları eksik. inc/config.php dosyasını oluşturun.');
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db['host'] ?? 'localhost',
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

/**
 * Bir .sql dosyasındaki ifadeleri tek tek çalıştırır.
 * Bazı paylaşımlı hostingler tek exec() içinde çoklu ifadeye izin vermiyor.
 * Çalıştırılan ifade sayısını döndürür.
 */
function bm_run_sql_file($pdo, $path) {
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('SQL dosyası okunamadı: ' . $path);
    }
    // Önce "--" ile başlayan yorum satırlarını at, sonra ifadelere böl.
    // (Yorumları temizlemeden bölersek dosya başındaki yorum bloğu ilk
    // CREATE ifadesiyle aynı parçaya düşüyor ve o ifade atlanıyor.)
    $lines = preg_split('/\R/', $sql);
    $clean = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line)) continue;
        $clean[] = $line;
    }
    $sql = implode("\n", $clean);

    $count = 0;
    foreach (explode(';', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        $pdo->exec($stmt);
        $count++;
    }
    return $count;
}

/**
 * Ürün tabloları kurulmuş mu? (Kurulum yapılmadan sayfalar çökmesin diye
 * ön yüz bunu kontrol edip nazikçe boş liste gösterebilir.)
 */
function bm_db_ready() {
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        bm_db()->query('SELECT 1 FROM products LIMIT 1');
        $ready = true;
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}
