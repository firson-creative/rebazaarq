<?php
// 1. Aktifkan Error Reporting untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Include file pendukung
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';

require_guest();

$error = '';
$kode_unik_tampil = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Sesi formulir tidak sah, silakan coba lagi.";
    } else {
        $nama = trim($_POST['nama'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

        if (empty($nama) || empty($username) || empty($password) || empty($konfirmasi_password)) {
            $error = "Semua kolom bertanda bintang (*) wajib diisi.";
        } elseif ($password !== $konfirmasi_password) {
            $error = "Konfirmasi password tidak cocok.";
        } elseif (strlen($password) < 6) {
            $error = "Password minimal terdiri dari 6 karakter.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "Username sudah digunakan, silakan pilih username lain.";
            } else {
                $random_bytes = strtoupper(bin2hex(random_bytes(4)));
                $kode_unik_raw = "BZR-" . substr($random_bytes, 0, 3) . "-" . substr($random_bytes, 3, 2) . "-" . substr($random_bytes, 5, 3);
                
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $kode_unik_hash = password_hash($kode_unik_raw, PASSWORD_BCRYPT);

                $stmt = $pdo->prepare("INSERT INTO users (nama, username, email, password_hash, kode_unik_hash, role, status) VALUES (?, ?, ?, ?, ?, 'kasir', 'pending')");
                
                if ($stmt->execute([$nama, $username, $email ?: null, $password_hash, $kode_unik_hash])) {
                    $kode_unik_tampil = $kode_unik_raw;
                } else {
                    $error = "Gagal mendaftar, terjadi kesalahan sistem.";
                }
            }
        }
    }
}
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light py-5">

<div class="container" style="max-width: 480px;">
    <div class="text-center mb-4">
        <h1 class="navbar-brand-wordmark fs-2 m-0"><?= APP_NAME ?></h1>
        <p class="text-muted small">Website Manajemen Transaksi Bazar Organisasi</p>
    </div>

    <div class="card card-rebazaar p-4">
        <?php if ($kode_unik_tampil): ?>
            <div class="text-center">
                <span class="badge bg-success-subtle text-success border border-success-subtle mb-3 px-3 py-2">Pendaftaran Berhasil!</span>
                <h4 class="fw-bold mb-2">Simpan Kode Unik Anda</h4>
                <p class="text-muted small">
                    Kode ini digunakan untuk pemulihan akun jika Anda lupa password. 
                    <strong class="text-danger">Kode ini hanya ditampilkan SATU KALI ini saja</strong> dan tidak dapat dilihat kembali.
                </p>

                <div class="p-3 my-3 bg-light border rounded text-center">
                    <code class="fs-4 fw-bold text-dark" id="kodeUnikText"><?= htmlspecialchars($kode_unik_tampil) ?></code>
                </div>

                <button class="btn btn-sky w-100 mb-3" onclick="salinKode()">
                    📋 Salin Kode Unik
                </button>

                <div class="alert alert-warning small text-start mb-4">
                    📌 <strong>Catatan:</strong> Akun Anda saat ini berstatus <strong>Pending</strong>. Silakan hubungi Admin untuk mengaktifkan akun Anda sebelum bisa Sign In.
                </div>

                <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-sky-outline w-100">Lanjut ke Halaman Sign In</a>
            </div>
        <?php else: ?>
            <div class="mb-3">
                <p class="section-label mb-1">Registrasi Panitia</p>
                <h3 class="heading-display h4 m-0">Buat Akun Baru</h3>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $token ?>">
                
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Lengkap *</label>
                    <input type="text" name="nama" class="form-control" required value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Username *</label>
                    <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email (Opsional)</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Konfirmasi Password *</label>
                    <input type="password" name="konfirmasi_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-sky w-100 my-2">Daftar Akun</button>
            </form>

            <div class="text-center mt-3 pt-3 border-top">
                <span class="small text-muted">Sudah punya akun?</span> 
                <a href="<?= BASE_URL ?>auth/login.php" class="small fw-semibold text-decoration-none">Sign In</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function salinKode() {
    const kode = document.getElementById('kodeUnikText').innerText;
    navigator.clipboard.writeText(kode).then(() => {
        alert('Kode unik berhasil disalin ke clipboard!');
    }).catch(() => {
        alert('Gagal menyalin otomatis. Silakan salin secara manual.');
    });
}
</script>

</body>
</html>