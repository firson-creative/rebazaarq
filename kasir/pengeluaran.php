<?php
$page_title = "Catat Pengeluaran Operasional";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['kasir', 'admin']);

$error = '';
$success = '';

// Ambil Sesi Bazar Aktif
$stmt_sesi = $pdo->query("SELECT * FROM sesi_bazar WHERE status = 'buka' LIMIT 1");
$sesi_aktif = $stmt_sesi->fetch();

if (!$sesi_aktif) {
    require_once __DIR__ . '/../includes/header.php';
    require_once __DIR__ . '/../includes/sidebar.php';
    echo '<main class="flex-grow-1 p-4"><div class="alert alert-warning m-0">⚠️ Tidak ada sesi bazar yang aktif saat ini. Harap <a href="sesi.php" class="alert-link">Buka Sesi Bazar</a> terlebih dahulu untuk mencatat pengeluaran.</div></main>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Sesi formulir tidak sah.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'tambah') {
            $keterangan = trim($_POST['keterangan'] ?? '');
            $jumlah = (float)($_POST['jumlah'] ?? 0);

            if (empty($keterangan) || $jumlah <= 0) {
                $error = "Keterangan dan nominal pengeluaran wajib diisi dengan benar.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO pengeluaran (sesi_id, keterangan, jumlah, dicatat_oleh) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$sesi_aktif['id'], $keterangan, $jumlah, $_SESSION['user_id']])) {
                    $success = "Pengeluaran operasional berhasil dicatat.";
                } else {
                    $error = "Gagal mencatat pengeluaran.";
                }
            }
        } elseif ($action === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM pengeluaran WHERE id = ? AND sesi_id = ?");
            if ($stmt->execute([$id, $sesi_aktif['id']])) {
                $success = "Pengeluaran berhasil dihapus.";
            } else {
                $error = "Gagal menghapus pengeluaran.";
            }
        }
    }
}

// Ambil Daftar Pengeluaran Sesi Aktif
$stmt_exp = $pdo->prepare("
    SELECT p.*, u.nama AS nama_pencatat 
    FROM pengeluaran p 
    JOIN users u ON p.dicatat_oleh = u.id 
    WHERE p.sesi_id = ? 
    ORDER BY p.id DESC
");
$stmt_exp->execute([$sesi_aktif['id']]);
$pengeluaran_list = $stmt_exp->fetchAll();

$token = generate_csrf_token();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-grow-1 p-4 overflow-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="section-label mb-1">Operasional Bazar</p>
            <h2 class="heading-display h3 m-0">Pengeluaran Operasional</h2>
            <small class="text-muted">Sesi: <strong><?= htmlspecialchars($sesi_aktif['nama_kegiatan']) ?></strong></small>
        </div>
        <button class="btn btn-sky" data-bs-toggle="modal" data-bs-target="#modalTambahPengeluaran">
            <i class="bi bi-plus-lg me-1"></i> Catat Pengeluaran
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
                        <th class="ps-3">Waktu</th>
                        <th>Keterangan</th>
                        <th>Nominal</th>
                        <th>Dicatat Oleh</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pengeluaran_list)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Belum ada pengeluaran operasional tambahan pada sesi ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php $total_exp = 0; foreach ($pengeluaran_list as $exp): $total_exp += $exp['jumlah']; ?>
                            <tr>
                                <td class="ps-3 small text-muted"><?= date('H:i:s', strtotime($exp['tanggal'])) ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($exp['keterangan']) ?></td>
                                <td class="fw-bold text-danger">Rp <?= number_format($exp['jumlah'], 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars($exp['nama_pencatat']) ?></td>
                                <td class="text-end pe-3">
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Hapus pengeluaran ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= $token ?>">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id" value="<?= $exp['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="2" class="ps-3">Total Pengeluaran Operasional</td>
                            <td class="text-danger">Rp <?= number_format($total_exp, 0, ',', '.') ?></td>
                            <td colspan="2"></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal Tambah Pengeluaran -->
<div class="modal fade" id="modalTambahPengeluaran" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold">Tambah Pengeluaran Operasional</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Keterangan Pengeluaran *</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="Misal: Es batu tambahan, Kantong plastik" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nominal (Rp) *</label>
                        <input type="number" name="jumlah" class="form-control" placeholder="15000" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sky btn-sm">Simpan Pengeluaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>