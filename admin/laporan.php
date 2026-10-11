<?php
$page_title = "Laporan Transaksi";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

require_role(['admin', 'kasir']);

// Parameter Filter
$sesi_id = $_GET['sesi_id'] ?? '';
$tgl_mulai = $_GET['tgl_mulai'] ?? '';
$tgl_selesai = $_GET['tgl_selesai'] ?? '';
$kasir_id = $_GET['kasir_id'] ?? '';

// Build Query
$where = ["1=1"];
$params = [];

if ($sesi_id !== '') {
    $where[] = "t.sesi_id = ?";
    $params[] = $sesi_id;
}
if ($tgl_mulai !== '') {
    $where[] = "DATE(t.created_at) >= ?";
    $params[] = $tgl_mulai;
}
if ($tgl_selesai !== '') {
    $where[] = "DATE(t.created_at) <= ?";
    $params[] = $tgl_selesai;
}
if ($kasir_id !== '') {
    $where[] = "t.kasir_id = ?";
    $params[] = $kasir_id;
}

$where_clause = implode(" AND ", $where);

// Fetch Data Transaksi
$sql = "
    SELECT t.*, s.nama_kegiatan, u.nama AS nama_kasir,
           COALESCE(SUM(td.qty * td.harga_bazar), 0) AS total_bazar,
           COALESCE(SUM(td.qty * td.harga_asli), 0) AS total_asli
    FROM transaksi t
    JOIN sesi_bazar s ON t.sesi_id = s.id
    JOIN users u ON t.kasir_id = u.id
    LEFT JOIN transaksi_detail td ON td.transaksi_id = t.id
    WHERE {$where_clause}
    GROUP BY t.id
    ORDER BY t.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$laporan_list = $stmt->fetchAll();

// Dropdown Data
$sesi_options = $pdo->query("SELECT id, nama_kegiatan FROM sesi_bazar ORDER BY id DESC")->fetchAll();
$kasir_options = $pdo->query("SELECT id, nama FROM users WHERE role = 'kasir' ORDER BY nama ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-grow-1 p-4 overflow-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="section-label mb-1">Rekapitulasi Data</p>
            <h2 class="heading-display h3 m-0">Laporan Transaksi</h2>
        </div>
        <div>
            <!-- Tombol Ekspor Excel -->
            <a href="<?= BASE_URL ?>export/laporan_xlsx.php?<?= http_build_query($_GET) ?>" class="btn btn-success fw-semibold">
                <i class="bi bi-file-earmark-excel me-1"></i> Ekspor Excel (.xlsx)
            </a>
        </div>
    </div>

    <!-- FORM FILTER -->
    <div class="card card-rebazaar p-3 mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Sesi Bazar</label>
                <select name="sesi_id" class="form-select form-select-sm">
                    <option value="">-- Semua Sesi --</option>
                    <?php foreach ($sesi_options as $so): ?>
                        <option value="<?= $so['id'] ?>" <?= $sesi_id == $so['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($so['nama_kegiatan']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Dari Tanggal</label>
                <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_mulai) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Sampai Tanggal</label>
                <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_selesai) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Kasir / Panitia</label>
                <select name="kasir_id" class="form-select form-select-sm">
                    <option value="">-- Semua Kasir --</option>
                    <?php foreach ($kasir_options as $ko): ?>
                        <option value="<?= $ko['id'] ?>" <?= $kasir_id == $ko['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ko['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sky btn-sm w-100">Filter</button>
                <a href="laporan.php" class="btn btn-light border btn-sm">Reset</a>
            </div>
        </form>
    </div>

    <!-- TABEL DATA LAPORAN -->
    <div class="card card-rebazaar overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light border-bottom">
                    <tr>
                        <th class="ps-3">Kode TRX</th>
                        <th>Waktu</th>
                        <th>Sesi Bazar</th>
                        <th>Kasir</th>
                        <th>Omset (Harga Bazar)</th>
                        <th>Setoran (Harga Asli)</th>
                        <th>Margin Bersih</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($laporan_list)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Tidak ada data transaksi yang sesuai filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $grand_bazar = 0; 
                        $grand_asli = 0; 
                        foreach ($laporan_list as $l): 
                            if ($l['status'] === 'lunas') {
                                $grand_bazar += $l['total_bazar'];
                                $grand_asli += $l['total_asli'];
                            }
                            $margin = $l['total_bazar'] - $l['total_asli'];
                        ?>
                            <tr>
                                <td class="ps-3 fw-bold"><code><?= htmlspecialchars($l['kode']) ?></code></td>
                                <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($l['created_at'])) ?></td>
                                <td><?= htmlspecialchars($l['nama_kegiatan']) ?></td>
                                <td><?= htmlspecialchars($l['nama_kasir']) ?></td>
                                <td class="fw-bold text-primary">Rp <?= number_format($l['total_bazar'], 0, ',', '.') ?></td>
                                <td class="text-muted">Rp <?= number_format($l['total_asli'], 0, ',', '.') ?></td>
                                <td class="fw-bold text-success">Rp <?= number_format($margin, 0, ',', '.') ?></td>
                                <td>
                                    <?php if ($l['status'] === 'lunas'): ?>
                                        <span class="badge bg-success">Lunas</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Batal</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="4" class="ps-3 text-uppercase">Total Transaksi Lunas</td>
                            <td class="text-primary">Rp <?= number_format($grand_bazar, 0, ',', '.') ?></td>
                            <td class="text-muted">Rp <?= number_format($grand_asli, 0, ',', '.') ?></td>
                            <td class="text-success">Rp <?= number_format($grand_bazar - $grand_asli, 0, ',', '.') ?></td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>