<?php
// Atur nama session HANYA JIKA session belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_name('REBAZAARQ_SESS');
    session_start();
}

/**
 * Memastikan pengguna sudah login
 */
function require_login() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        $_SESSION['error'] = "Silakan Sign In terlebih dahulu.";
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
}

/**
 * Memastikan pengguna belum login (untuk halaman login/signup/lupa password)
 */
function require_guest() {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $role = $_SESSION['role'] ?? 'kasir';
        if ($role === 'admin') {
            header("Location: " . BASE_URL . "admin/dashboard.php");
        } else {
            header("Location: " . BASE_URL . "kasir/dashboard.php");
        }
        exit;
    }
}

/**
 * Memastikan pengguna memiliki role tertentu (String atau Array)
 */
function require_role($allowed_roles) {
    require_login();

    $user_role = $_SESSION['role'] ?? '';

    if (is_array($allowed_roles)) {
        if (!in_array($user_role, $allowed_roles, true)) {
            $_SESSION['error'] = "Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman tersebut.";
            header("Location: " . BASE_URL . "auth/login.php");
            exit;
        }
    } else {
        if ($user_role !== $allowed_roles) {
            $_SESSION['error'] = "Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman tersebut.";
            header("Location: " . BASE_URL . "auth/login.php");
            exit;
        }
    }
}