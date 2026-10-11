<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';

require_guest();

$error = '';
$success = '';
$step = 1; // Step 1: Verifikasi Kode Unik, Step 2: Form Password Baru
$verified_user_id = $_SESSION['reset_user_id'] ?? null;

if ($verified_user_id) {
    $step = 2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Sesi formulir tidak sah, silakan coba lagi.";
    } else {
        $action = $_POST['action'] ?? '';
        $ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        if ($action === 'verify_code') {
            $username = strtolower(trim($_POST['username'] ?? ''));
            $kode_unik = trim($_POST['kode_unik'] ?? '');

            if (empty($username) || empty($kode_unik)) {
                $error = "Username dan Kode Unik wajib diisi.";
            } else {
                // Rate Limiting Check (5 kali per 15 menit)
                $stmt_attempt = $pdo->prepare("
                    SELECT COUNT(*) FROM login_attempts 
                    WHERE (username = ? OR ip_hash = ?) 
                    AND waktu > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ");
                $stmt_attempt->execute([$username, $ip_hash]);
                if ($stmt_attempt->fetchColumn() >= 5) {
                    $error = "Terlalu banyak percobaan gagal. Silakan tunggu 15 menit sebelum mencoba lagi.";
                } else {
                    $stmt = $pdo->prepare("SELECT id, kode_unik_hash FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($kode_unik, $user['kode_unik_hash'])) {
                        $_SESSION['reset_user_id'] = $user['id'];
                        $verified_user_id = $user['id'];
                        $step = 2;
                    } else {
                        // Log gagal
                        $stmt_log = $pdo->prepare("INSERT INTO login_attempts (username, ip_hash) VALUES (?, ?)");
                        $stmt_log->execute([$username, $ip_hash]);
                        $error = "Username atau Kode Unik tidak cocok.";
                    }
                }
            }
        } elseif ($action === 'reset_password' && $verified_user_id) {
            $password = $_POST['password'] ?? '';
            $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

            if (empty($password) || empty($konfirmasi_password)) {
                $error = "Password baru dan konfirmasi password wajib diisi.";
            } elseif ($password !== $konfirmasi_password) {
                $error = "Konfirmasi password tidak cocok.";
            } elseif (strlen($password) < 6) {
                $error = "Password minimal terdiri dari 6 karakter.";
            } else {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                
                if ($stmt->execute([$password_hash, $verified_user_id])) {
                    unset($_SESSION['reset_user_id']);
                    $_SESSION['error'] = "Password berhasil diperbarui! Silakan Sign In dengan password baru Anda.";
                    header("Location: " . BASE_URL . "auth/login.php");
                    exit;
                } else {
                    $error = "Gagal memperbarui password, terjadi kesalahan sistem.";
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
    <title>Lupa Password - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light py-5">

<div class="container" style="max-width: 420px;">
    <div class="text-center mb-4">
        <h1 class="navbar-brand-wordmark fs-2 m-0"><?= APP_NAME ?></h1>
        <p class="text-muted small">Website Manajemen Transaksi Bazar Organisasi</p>
    </div>

    <div class="card card-rebazaar p-4">
        <div class="mb-3">
            <p class="section-label mb-1">Pemulihan Akun</p>
            <h3 class="heading-display h4 m-0">Reset Password</h3>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="verify_code">

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Username</label>
                    <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Kode Unik</label>
                    <input type="text" name="kode_unik" class="form-control" placeholder="BZR-XXX-XX-XXXX" required>
                    <div class="form-text text-muted small">Masukkan kode unik yang Anda terima saat pendaftaran.</div>
                </div>

                <button type="submit" class="btn btn-sky w-100 my-2">Verifikasi Kode</button>
            </form>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="reset_password">

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Password Baru</label>
                    <input type="password" name="password" class="form-control" required autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Konfirmasi Password Baru</label>
                    <input type="password" name="konfirmasi_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-sky w-100 my-2">Simpan Password Baru</button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-3 pt-3 border-top">
            <a href="<?= BASE_URL ?>auth/login.php" class="small fw-semibold text-decoration-none">← Kembali ke Sign In</a>
        </div>
    </div>
</div>

</body>
</html>