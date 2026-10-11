<?php
require_once __DIR__ . '/includes/db.php';

$nama = "Administrator Utama";
$username = "admin";
$password = "Admin123!";
$kode_unik = "BZR-ADM-00-0001";

$password_hash = password_hash($password, PASSWORD_BCRYPT);
$kode_unik_hash = password_hash($kode_unik, PASSWORD_BCRYPT);

try {
    // Hapus jika sudah ada
    $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$username]);
    $pdo->exec("TRUNCATE TABLE login_attempts");

    // Insert Admin Baru
    $stmt = $pdo->prepare("INSERT INTO users (nama, username, email, password_hash, kode_unik_hash, role, status) VALUES (?, ?, 'admin@org.id', ?, ?, 'admin', 'aktif')");
    $stmt->execute([$nama, $username, $password_hash, $kode_unik_hash]);

    echo "<h3>Akun Admin Berhasil Dibuat!</h3>";
    echo "<p>Username: <strong>admin</strong><br>Password: <strong>Admin123!</strong></p>";
    echo "<a href='auth/login.php'>Klik di sini untuk Sign In</a>";
} catch (Exception $e) {
    echo "Gagal: " . $e->getMessage();
}