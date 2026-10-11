<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';

require_guest();

$error = '';
$session_error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Sesi formulir tidak sah, silakan coba lagi.";
    } else {
        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $ip_hash  = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        if (empty($username) || empty($password)) {
            $error = "Username dan Password wajib diisi.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] === 'pending') {
                    $error = "Akun Anda masih berstatus PENDING. Harap hubungi Admin.";
                } elseif ($user['status'] === 'nonaktif') {
                    $error = "Akun Anda telah DINONAKTIFKAN.";
                } else {
                    // Bersihkan riwayat login gagal
                    $stmt_clean = $pdo->prepare("DELETE FROM login_attempts WHERE username = ? OR ip_hash = ?");
                    $stmt_clean->execute([$username, $ip_hash]);

                    // Regenerate session id demi keamanan
                    session_regenerate_id(true);

                    // Set data session pengguna
                    $_SESSION['user_id']  = $user['id'];
                    $_SESSION['nama']     = $user['nama'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role']     = $user['role'];
                    $_SESSION['status']   = $user['status'];

                    if ($user['role'] === 'admin') {
                        header("Location: " . BASE_URL . "admin/dashboard.php");
                    } else {
                        header("Location: " . BASE_URL . "kasir/dashboard.php");
                    }
                    exit;
                }
            } else {
                $stmt_log = $pdo->prepare("INSERT INTO login_attempts (username, ip_hash) VALUES (?, ?)");
                $stmt_log->execute([$username, $ip_hash]);
                $error = "Username atau Password salah.";
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
    <title>Sign In - <?= APP_NAME ?></title>
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
            <p class="section-label mb-1">Masuk Sistem</p>
            <h3 class="heading-display h4 m-0">Sign In</h3>
        </div>

        <?php if ($session_error): ?>
            <div class="alert alert-warning py-2 small"><?= htmlspecialchars($session_error) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $token ?>">
            
            <div class="mb-3">
                <label class="form-label small fw-semibold">Username</label>
                <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autofocus>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small fw-semibold m-0">Password</label>
                    <a href="<?= BASE_URL ?>auth/lupa_password.php" class="small text-decoration-none">Lupa password?</a>
                </div>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-sky w-100 my-2">Sign In</button>
        </form>

        <div class="text-center mt-3 pt-3 border-top">
            <span class="small text-muted">Belum punya akun?</span> 
            <a href="<?= BASE_URL ?>auth/signup.php" class="small fw-semibold text-decoration-none">Daftar Panitia</a>
        </div>
    </div>
</div>

</body>
</html>