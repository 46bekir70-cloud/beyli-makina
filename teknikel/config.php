<?php
/**
 * Admin oturum yönetimi ve şifre işlemleri.
 * Her admin sayfasının en başında require_once ile çağrılır.
 */
require_once __DIR__ . '/../inc/functions.php';

define('BM_ADMIN_CREDENTIALS_FILE', __DIR__ . '/credentials.json');

/**
 * Panel klasörünün adı "admin" iken "teknikel" oldu.
 *
 * Şifre dosyası güncelleme paketine bilerek konmuyor — konsaydı her yüklemede
 * şifre sıfırlanırdı. Ama bu yüzden yeni klasörde şifre dosyası bulunmuyor ve
 * güncellemeden sonra hiç giriş yapılamıyordu. Burada eski klasörde kalan dosya
 * bir kez yeni yere kopyalanıyor: kullanıcı hiçbir şey yapmadan aynı şifreyle
 * girebiliyor. Eski klasör silindikten sonra bu kod hiç çalışmıyor.
 */
if (!file_exists(BM_ADMIN_CREDENTIALS_FILE)) {
    $bm_eskiSifre = dirname(__DIR__) . '/admin/credentials.json';
    if (is_file($bm_eskiSifre)) {
        @copy($bm_eskiSifre, BM_ADMIN_CREDENTIALS_FILE);
    }
    unset($bm_eskiSifre);
}

// Oturum başlatma (yazılabilir klasör ayarı dahil) inc/functions.php içinde —
// ön yüzdeki teklif formu da aynı mekanizmayı kullanıyor.
bm_session_start();

function bm_admin_get_hash() {
    if (!file_exists(BM_ADMIN_CREDENTIALS_FILE)) return null;
    $data = json_decode(file_get_contents(BM_ADMIN_CREDENTIALS_FILE), true);
    return $data['password_hash'] ?? null;
}

function bm_admin_set_password($newPlainPassword) {
    $hash = password_hash($newPlainPassword, PASSWORD_DEFAULT);
    $ok = file_put_contents(BM_ADMIN_CREDENTIALS_FILE, json_encode(['password_hash' => $hash], JSON_PRETTY_PRINT));
    return $ok !== false;
}

function bm_admin_verify_password($plainPassword) {
    $hash = bm_admin_get_hash();
    if (!$hash) return false;
    return password_verify($plainPassword, $hash);
}

// Oturum ömrü: bu kadar hareketsizlikten sonra yeniden giriş istenir.
// Ortak/halka açık bir bilgisayarda açık kalan panel riskini sınırlıyor.
define('BM_ADMIN_BOSTA_SINIR', 4 * 3600);   // 4 saat hareketsizlik
define('BM_ADMIN_AZAMI_SURE', 24 * 3600);   // giriş üzerinden en fazla 24 saat

function bm_admin_logged_in() {
    if (empty($_SESSION['bm_admin_logged_in'])) return false;

    $simdi = time();
    $bosta = $simdi - (int)($_SESSION['bm_admin_son_hareket'] ?? $simdi);
    $gecen = $simdi - (int)($_SESSION['bm_admin_giris'] ?? $simdi);

    if ($bosta > BM_ADMIN_BOSTA_SINIR || $gecen > BM_ADMIN_AZAMI_SURE) {
        // Süresi geçmiş oturum tamamen boşaltılır
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
        return false;
    }
    $_SESSION['bm_admin_son_hareket'] = $simdi;
    return true;
}

function bm_admin_require_login() {
    if (!bm_admin_logged_in()) {
        header('Location: /teknikel/login.php');
        exit;
    }
}

/** Basit CSRF token üretimi/kontrolü (form gönderimlerinde kullanılır) */
function bm_csrf_token() {
    if (empty($_SESSION['bm_csrf'])) {
        $_SESSION['bm_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['bm_csrf'];
}
function bm_csrf_check() {
    $sent = $_POST['csrf'] ?? '';
    return !empty($_SESSION['bm_csrf']) && hash_equals($_SESSION['bm_csrf'], $sent);
}
