<?php
$page_title = "Kelola Pengguna";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';

// Proteksi Halaman: Hanya Admin
require_role('admin');

$error = '';
$success = '';

// Handling Aksi Form (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Sesi formulir tidak valid, silakan coba lagi.";
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Tambah Pengguna Baru oleh Admin
        if ($action === 'tambah') {
            $nama = trim($_POST['nama'] ?? '');
            $username = strtolower(trim($_POST['username'] ?? ''));
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'kasir';

            if (empty($nama) || empty($username) || empty($password)) {
                $error = "Nama, Username, dan Password wajib diisi.";
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = "Username sudah digunakan.";
                } else {
                    $random_bytes = strtoupper(bin2hex(random_bytes(4)));
                    $kode_unik_raw = "BZR-" . substr($random_bytes, 0, 3) . "-" . substr($random_bytes, 3, 2) . "-" . substr($random_bytes, 5, 3);
                    
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $kode_unik_hash = password_hash($kode_unik_raw, PASSWORD_BCRYPT);

                    $stmt = $pdo->prepare("INSERT INTO users (nama, username, email, password_hash, kode_unik_hash, role, status) VALUES (?, ?, ?, ?, ?, ?, 'aktif')");
                    if ($stmt->execute([$nama, $username, $email ?: null, $password_hash, $kode_unik_hash, $role])) {
                        $success = "Pengguna berhasil ditambahkan. Kode Unik buatan: <strong>{$kode_unik_raw}</strong> (Catat & berikan ke pengguna).";
                    } else {
                        $error = "Gagal menambah pengguna.";
                    }
                }
            }
        }
        
        // 2. Ubah Status / Peran
        elseif ($action === 'update_status_role') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            $status = $_POST['status'] ?? 'pending';
            $role = $_POST['role'] ?? 'kasir';

            if ($user_id === $_SESSION['user_id'] && $status !== 'aktif') {
                $error = "Anda tidak dapat menonaktifkan akun Anda sendiri.";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = ?, role = ? WHERE id = ?");
                if ($stmt->execute([$status, $role, $user_id])) {
                    $success = "Data pengguna berhasil diperbarui.";
                } else {
                    $error = "Gagal memperbarui data pengguna.";
                }
            }
        }

        // 3. Reset Password oleh Admin
        elseif ($action === 'reset_password') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            $new_password = $_POST['new_password'] ?? '';

            if (strlen($new_password) < 6) {
                $error = "Password baru minimal 6 karakter.";
            } else {
                $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                if ($stmt->execute([$password_hash, $user_id])) {
                    $success = "Password pengguna berhasil direset.";
                } else {
                    $error = "Gagal mereset password.";
                }
            }
        }

        // 4. Hapus Pengguna
        elseif ($action === 'hapus') {
            $user_id = (int)($_POST['user_id'] ?? 0);

            if ($user_id === $_SESSION['user_id']) {
                $error = "Anda tidak dapat menghapus akun Anda sendiri.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $success = "Pengguna berhasil dihapus.";
                } else {
                    $error = "Gagal menghapus pengguna. Akun mungkin terikat data transaksi/sesi.";
                }
            }
        }
    }
}

// Ambil Seluruh Data Pengguna
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

$token = generate_csrf_token();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-grow-1 p-4 overflow-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="section-label mb-1">Manajemen Hak Akses</p>
            <h2 class="heading-display h3 m-0">Kelola Pengguna</h2>
        </div>
        <button class="btn btn-sky" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
            <i class="bi bi-person-plus me-1"></i> Tambah Pengguna
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card card-rebazaar overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light border-bottom">
                    <tr>
                        <th class="ps-3">Nama</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Peran</th>
                        <th>Status</th>
                        <th>Terdaftar</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="ps-3 fw-bold"><?= htmlspecialchars($u['nama']) ?></td>
                            <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                            <td><?= htmlspecialchars($u['email'] ?: '-') ?></td>
                            <td>
                                <span class="badge <?= $u['role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>">
                                    <?= strtoupper($u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['status'] === 'aktif'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                                <?php elseif ($u['status'] === 'pending'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Pending</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                            <td class="text-end pe-3">
                                <!-- Edit Status/Role -->
                                <button class="btn btn-sm btn-light border me-1" title="Edit Status/Role" data-bs-toggle="modal" data-bs-target="#modalEditUser<?= $u['id'] ?>">
                                    <i class="bi bi-pencil-square"></i>
                                </button>

                                <!-- Reset Password -->
                                <button class="btn btn-sm btn-light border text-warning me-1" title="Reset Password" data-bs-toggle="modal" data-bs-target="#modalResetPassword<?= $u['id'] ?>">
                                    <i class="bi bi-key"></i>
                                </button>

                                <!-- Hapus User -->
                                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-light border text-danger" title="Hapus User" data-bs-toggle="modal" data-bs-target="#modalHapusUser<?= $u['id'] ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Modal Edit Status/Role -->
                        <div class="modal fade" id="modalEditUser<?= $u['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= $token ?>">
                                        <input type="hidden" name="action" value="update_status_role">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title fs-6 fw-bold">Edit Pengguna: <?= htmlspecialchars($u['nama']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Peran (Role)</label>
                                                <select name="role" class="form-select">
                                                    <option value="kasir" <?= $u['role'] === 'kasir' ? 'selected' : '' ?>>Kasir / Panitia</option>
                                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Status Akun</label>
                                                <select name="status" class="form-select">
                                                    <option value="pending" <?= $u['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="aktif" <?= $u['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                                    <option value="nonaktif" <?= $u['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-sky btn-sm">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Reset Password -->
                        <div class="modal fade" id="modalResetPassword<?= $u['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= $token ?>">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title fs-6 fw-bold">Reset Password: <?= htmlspecialchars($u['nama']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Password Baru</label>
                                                <input type="password" name="new_password" class="form-control" required placeholder="Minimal 6 karakter">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-warning btn-sm text-dark">Reset Password</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Hapus User -->
                        <div class="modal fade" id="modalHapusUser<?= $u['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= $token ?>">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title fs-6 fw-bold">Hapus Pengguna</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            Apakah Anda yakin ingin menghapus pengguna <strong><?= htmlspecialchars($u['nama']) ?></strong>?
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-danger btn-sm">Ya, Hapus</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal Tambah User -->
<div class="modal fade" id="modalTambahUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold">Tambah Pengguna Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap *</label>
                        <input type="text" name="nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email (Opsional)</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Peran (Role)</label>
                        <select name="role" class="form-select">
                            <option value="kasir">Kasir / Panitia</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sky btn-sm">Tambah Pengguna</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>