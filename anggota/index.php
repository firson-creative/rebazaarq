<?php
$page_title = "Rekap Publik Bazar";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/track.php';

// Catat Kunjungan
track_visitor('/anggota');

// Ambil Riwayat Sesi Bazar
$stmt_sesi = $pdo->query("
    SELECT s.id, s.nama_kegiatan, s.tanggal, s.lokasi, s.status,
           COALESCE(SUM(td.qty * td.harga_bazar), 0) AS kotor,
           COALESCE(SUM(td.qty * td.harga_asli), 0) AS biaya_mitra
    FROM sesi_bazar s
    LEFT JOIN transaksi t ON t.sesi_id = s.id AND t.status = 'lunas'
    LEFT JOIN transaksi_detail td ON td.transaksi_id = t.id
    GROUP BY s.id
    ORDER BY s.id DESC
");
$sesi_list = $stmt_sesi->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg bg-white border-bottom py-3">
    <div class="container">
        <a class="navbar-brand-wordmark fs-4 text-decoration-none" href="#"><?= APP_NAME ?></a>
        <div>
            <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-sky-outline btn-sm">Sign In Panitia</a>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="text-center mb-5">
        <p class="section-label mb-1">Transparansi Organisasi</p>
        <h1 class="heading-display display-5 fw-bold">Rekapitulasi Penjualan Bazar</h1>
        <p class="text-muted">Pantau grafik penjualan dan rekapitulasi pemasukan bazar secara langsung.</p>
    </div>

    <!-- GRAFIK PENJUALAN UNTUK ANGGOTA (G1) -->
    <div class="card card-rebazaar p-4 mb-5">
        <h5 class="fw-bold mb-3">Grafik Penjualan Hari Ini</h5>
        <canvas id="chartG1Public" height="100"></canvas>
    </div>

    <!-- TABEL REKAP BAZAR -->
    <div class="card card-rebazaar overflow-hidden">
        <div class="px-4 py-3 border-bottom bg-white">
            <h5 class="m-0 fs-6 fw-bold">Daftar Sesi Bazar Organisasi</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Kegiatan</th>
                        <th>Tanggal</th>
                        <th>Lokasi</th>
                        <th>Total Pemasukan Kotor</th>
                        <th>Biaya Mitra Warkop</th>
                        <th>Pemasukan Bersih</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sesi_list)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Belum ada data rekap bazar.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sesi_list as $sl): ?>
                            <?php $bersih = $sl['kotor'] - $sl['biaya_mitra']; ?>
                            <tr>
                                <td class="ps-3 fw-bold"><?= htmlspecialchars($sl['nama_kegiatan']) ?></td>
                                <td><?= date('d/m/Y', strtotime($sl['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($sl['lokasi']) ?></td>
                                <td class="fw-bold text-primary">Rp <?= number_format($sl['kotor'], 0, ',', '.') ?></td>
                                <td class="text-muted">Rp <?= number_format($sl['biaya_mitra'], 0, ',', '.') ?></td>
                                <td class="fw-bold text-success">Rp <?= number_format($bersih, 0, ',', '.') ?></td>
                                <td>
                                    <?php if ($sl['status'] === 'buka'): ?>
                                        <span class="badge bg-success">Sedang Berlangsung</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Selesai</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    fetch('<?= BASE_URL ?>api/chart_penjualan.php?periode=hari')
        .then(res => res.json())
        .then(data => {
            new Chart(document.getElementById('chartG1Public'), { type: 'line', data: data });
        });
});
</script>

</body>
</html>