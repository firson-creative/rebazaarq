<?php
$page_title = "Kelola Kategori Menu";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';

// Proteksi Halaman: Kasir atau Admin
require_role(['kasir', 'admin']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Sesi formulir tidak sah.";
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Tambah Kategori
        if ($action === 'tambah') {
            $nama = trim($_POST['nama'] ?? '');
            if (empty($nama)) {
                $error = "Nama kategori wajib diisi.";
            } else {
                $stmt = $pdo->prepare("SELECT id FROM kategori WHERE nama = ?");
                $stmt->execute([$nama]);
                if ($stmt->fetch()) {
                    $error = "Kategori dengan nama tersebut sudah ada.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO kategori (nama) VALUES (?)");
                    if ($stmt->execute([$nama])) {
                        $success = "Kategori berhasil ditambahkan.";
                    } else {
                        $error = "Gagal menambahkan kategori.";
                    }
                }
            }
        }

        // 2. Edit Kategori
        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            if (empty($nama)) {
                $error = "Nama kategori wajib diisi.";
            } else {
                $stmt = $pdo->prepare("UPDATE kategori SET nama = ? WHERE id = ?");
                if ($stmt->execute([$nama, $id])) {
                    $success = "Kategori berhasil diperbarui.";
                } else {
                    $error = "Gagal memperbarui kategori.";
                }
            }
        }

        // 3. Hapus Kategori
        elseif ($action === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);
            
            // Cek apakah dipakai oleh produk
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM produk WHERE kategori_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Kategori tidak dapat dihapus karena sedang digunakan oleh beberapa produk.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM kategori WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $success = "Kategori berhasil dihapus.";
                } else {
                    $error = "Gagal menghapus kategori.";
                }
            }
        }
    }
}

// Ambil Seluruh Kategori beserta Jumlah Produk
$stmt = $pdo->query("
    SELECT k.*, COUNT(p.id) AS jumlah_produk 
    FROM kategori k 
    LEFT JOIN produk p ON k.id = p.kategori_id 
    GROUP BY k.id 
    ORDER BY k.nama ASC
");
$categories = $stmt->fetchAll();

$token = generate_csrf_token();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-grow-1 p-4 overflow-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="section-label mb-1">Manajemen Menu</p>
            <h2 class="heading-display h3 m-0">Kategori Menu</h2>
        </div>
        <button class="btn btn-sky" data-bs-toggle="modal" data-bs-target="#modalTambahKategori">
            <i class="bi bi-plus-lg me-1"></i> Tambah Kategori
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card card-rebazaar overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light border-bottom">
                    <tr>
                        <th class="ps-3" style="width: 80px;">No</th>
                        <th>Nama Kategori</th>
                        <th>Jumlah Produk Terkait</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">Belum ada kategori menu. Klik tombol "+ Tambah Kategori" untuk membuatnya.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($categories as $cat): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $no++ ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($cat['nama']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= $cat['jumlah_produk'] ?> Produk
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-light border me-1" data-bs-toggle="modal" data-bs-target="#modalEditCat<?= $cat['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-light border text-danger" data-bs-toggle="modal" data-bs-target="#modalHapusCat<?= $cat['id'] ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- Modal Edit Kategori -->
                            <div class="modal fade" id="modalEditCat<?= $cat['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= $token ?>">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title fs-6 fw-bold">Edit Kategori</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Nama Kategori *</label>
                                                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($cat['nama']) ?>" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-sky btn-sm">Simpan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Hapus Kategori -->
                            <div class="modal fade" id="modalHapusCat<?= $cat['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= $token ?>">
                                            <input type="hidden" name="action" value="hapus">
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title fs-6 fw-bold">Hapus Kategori</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                Apakah Anda yakin ingin menghapus kategori <strong><?= htmlspecialchars($cat['nama']) ?></strong>?
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal Tambah Kategori -->
<div class="modal fade" id="modalTambahKategori" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold">Tambah Kategori Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Kategori *</label>
                        <input type="text" name="nama" class="form-control" placeholder="Misal: Minuman, Makanan, Snack" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sky btn-sm">Tambah Kategori</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>