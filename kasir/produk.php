<?php
$page_title = "Daftar Produk / Menu Bazar";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['kasir', 'admin']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Sesi formulir tidak sah.";
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Tambah Produk Baru
        if ($action === 'tambah') {
            $nama = trim($_POST['nama'] ?? '');
            $kategori_id = (int)($_POST['kategori_id'] ?? 0);
            $harga_bazar = (float)($_POST['harga_bazar'] ?? 0);
            $harga_asli = (float)($_POST['harga_asli'] ?? 0);

            if (empty($nama) || $kategori_id <= 0 || $harga_bazar <= 0 || $harga_asli <= 0) {
                $error = "Semua kolom wajib diisi dengan nilai yang valid.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO produk (kategori_id, nama, harga_bazar, harga_asli) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$kategori_id, $nama, $harga_bazar, $harga_asli])) {
                    $success = "Produk berhasil ditambahkan.";
                } else {
                    $error = "Gagal menambahkan produk.";
                }
            }
        }

        // 2. Edit Produk
        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $kategori_id = (int)($_POST['kategori_id'] ?? 0);
            $harga_bazar = (float)($_POST['harga_bazar'] ?? 0);
            $harga_asli = (float)($_POST['harga_asli'] ?? 0);
            $aktif = isset($_POST['aktif']) ? 1 : 0;

            if (empty($nama) || $kategori_id <= 0 || $harga_bazar <= 0 || $harga_asli <= 0) {
                $error = "Semua kolom wajib diisi dengan benar.";
            } else {
                $stmt = $pdo->prepare("UPDATE produk SET kategori_id = ?, nama = ?, harga_bazar = ?, harga_asli = ?, aktif = ? WHERE id = ?");
                if ($stmt->execute([$kategori_id, $nama, $harga_bazar, $harga_asli, $aktif, $id])) {
                    $success = "Data produk berhasil diperbarui.";
                } else {
                    $error = "Gagal memperbarui produk.";
                }
            }
        }

        // 3. Soft Delete / Hapus
        elseif ($action === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);

            // Cek apakah pernah ada di transaksi
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM transaksi_detail WHERE produk_id = ?");
            $stmt->execute([$id]);
            $pernah_terjual = $stmt->fetchColumn() > 0;

            if ($pernah_terjual) {
                // Soft delete
                $stmt = $pdo->prepare("UPDATE produk SET aktif = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Produk telah dineutralkan (nonaktif) karena memiliki riwayat transaksi.";
            } else {
                // Hard delete jika belum pernah terjual
                $stmt = $pdo->prepare("DELETE FROM produk WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Produk berhasil dihapus.";
            }
        }
    }
}

// Ambil Kategori untuk dropdown
$kategori_list = $pdo->query("SELECT * FROM kategori ORDER BY nama ASC")->fetchAll();

// Ambil Produk beserta Nama Kategori
$stmt = $pdo->query("
    SELECT p.*, k.nama AS nama_kategori 
    FROM produk p 
    JOIN kategori k ON p.kategori_id = k.id 
    ORDER BY p.aktif DESC, p.nama ASC
");
$products = $stmt->fetchAll();

$token = generate_csrf_token();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-grow-1 p-4 overflow-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="section-label mb-1">Manajemen Menu</p>
            <h2 class="heading-display h3 m-0">Produk / Daftar Menu</h2>
        </div>
        <button class="btn btn-sky" data-bs-toggle="modal" data-bs-target="#modalTambahProduk" <?= empty($kategori_list) ? 'disabled' : '' ?>>
            <i class="bi bi-plus-lg me-1"></i> Tambah Produk
        </button>
    </div>

    <?php if (empty($kategori_list)): ?>
        <div class="alert alert-warning mb-4 py-2">
            ⚠️ Belum ada kategori menu. Silakan tambahkan <a href="kategori.php" class="alert-link">Kategori Menu</a> terlebih dahulu sebelum menambah produk.
        </div>
    <?php endif; ?>

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
                        <th class="ps-3">Nama Produk</th>
                        <th>Kategori</th>
                        <th>Harga Bazar (Jual)</th>
                        <th>Harga Asli (Warkop)</th>
                        <th>Margin / Profit</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Belum ada produk yang didaftarkan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <?php $profit = $p['harga_bazar'] - $p['harga_asli']; ?>
                            <tr class="<?= $p['aktif'] ? '' : 'table-light text-muted' ?>">
                                <td class="ps-3 fw-bold"><?= htmlspecialchars($p['nama']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['nama_kategori']) ?></span></td>
                                <td class="fw-bold text-primary">Rp <?= number_format($p['harga_bazar'], 0, ',', '.') ?></td>
                                <td class="text-muted">Rp <?= number_format($p['harga_asli'], 0, ',', '.') ?></td>
                                <td>
                                    <span class="badge bg-success-subtle text-success">
                                        +Rp <?= number_format($profit, 0, ',', '.') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($p['aktif']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-light border me-1" data-bs-toggle="modal" data-bs-target="#modalEditProduk<?= $p['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-light border text-danger" data-bs-toggle="modal" data-bs-target="#modalHapusProduk<?= $p['id'] ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- Modal Edit Produk -->
                            <div class="modal fade" id="modalEditProduk<?= $p['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= $token ?>">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title fs-6 fw-bold">Edit Produk</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Nama Produk *</label>
                                                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($p['nama']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Kategori *</label>
                                                    <select name="kategori_id" class="form-select" required>
                                                        <?php foreach ($kategori_list as $k): ?>
                                                            <option value="<?= $k['id'] ?>" <?= $k['id'] == $p['kategori_id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($k['nama']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label small fw-semibold">Harga Bazar *</label>
                                                        <input type="number" name="harga_bazar" class="form-control" value="<?= $p['harga_bazar'] ?>" required>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small fw-semibold">Harga Asli *</label>
                                                        <input type="number" name="harga_asli" class="form-control" value="<?= $p['harga_asli'] ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="aktif" value="1" id="checkAktif<?= $p['id'] ?>" <?= $p['aktif'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label small" for="checkAktif<?= $p['id'] ?>">Produk Aktif (Tampil di menu bazar)</label>
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

                            <!-- Modal Hapus Produk -->
                            <div class="modal fade" id="modalHapusProduk<?= $p['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= $token ?>">
                                            <input type="hidden" name="action" value="hapus">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title fs-6 fw-bold">Hapus / Nonaktifkan Produk</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                Apakah Anda yakin ingin menghapus/menonaktifkan produk <strong><?= htmlspecialchars($p['nama']) ?></strong>?
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-danger btn-sm">Proses</button>
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

<!-- Modal Tambah Produk -->
<div class="modal fade" id="modalTambahProduk" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold">Tambah Produk Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Produk *</label>
                        <input type="text" name="nama" class="form-control" placeholder="Misal: Es Kopi Susu, Indomie Goreng" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kategori *</label>
                        <select name="kategori_id" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori_list as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Harga Bazar (Jual) *</label>
                            <input type="number" name="harga_bazar" class="form-control" placeholder="15000" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Harga Asli (Warkop) *</label>
                            <input type="number" name="harga_asli" class="form-control" placeholder="10000" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sky btn-sm">Tambah Produk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>