<?php
/**
 * Admin oturum yönetimi ve şifre işlemleri.
 * Her admin sayfasının en başında require_once ile çağrılır.
 */
require_once __DIR__ . '/../inc/functions.php';

define('BM_ADMIN_CREDENTIALS_FILE', __DIR__ . '/credentials.json');

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

function bm_admin_logged_in() {
    return !empty($_SESSION['bm_admin_logged_in']);
}

function bm_admin_require_login() {
    if (!bm_admin_logged_in()) {
        header('Location: /admin/login.php');
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
