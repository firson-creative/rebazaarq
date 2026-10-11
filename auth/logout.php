<?php
require_once __DIR__ . '/../includes/db.php';

$uri = $_SERVER['REQUEST_URI'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

if (strpos($referer, '/admin/') !== false) {
    session_name('REBAZAAR_ADMIN_SESS');
} elseif (strpos($referer, '/kasir/') !== false) {
    session_name('REBAZAAR_KASIR_SESS');
} else {
    session_name('REBAZAAR_PUBLIC_SESS');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header("Location: " . BASE_URL . "auth/login.php");
exit;