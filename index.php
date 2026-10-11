<?php
require_once __DIR__ . '/includes/config.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: " . BASE_URL . "admin/dashboard.php");
    } else {
        header("Location: " . BASE_URL . "kasir/dashboard.php");
    }
} else {
    header("Location: " . BASE_URL . "anggota/index.php");
}
exit;