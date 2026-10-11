<?php
$page_title = "Dashboard Kasir";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

require_role(['kasir', 'admin']);

// Cek Sesi Aktif
$stmt_sesi = $pdo->query("SELECT * FROM sesi_bazar WHERE status = 'buka' LIMIT 1");
$sesi_aktif = $stmt_sesi->fetch();

// Pengaturan Grafik Active
$settings = $pdo->query("SELECT kode, aktif FROM pengaturan_grafik")->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-grow-1 p-4 overflow-auto">
    <div class="mb-4">
        <p class="section-label mb-1">Ikhtisar Kasir</p>
        <h2 class="heading-display h3 m-0">Dashboard Panitia / Kasir</h2>
    </div>

    <?php if ($sesi_aktif): ?>
        <div class="alert alert-success d-flex justify-content-between align-items-center mb-4 py-3">
            <div>
                <strong>Sesi Aktif:</strong> <?= htmlspecialchars($sesi_aktif['nama_kegiatan']) ?> (<?= htmlspecialchars($sesi_aktif['lokasi']) ?>)
            </div>
            <a href="pesanan.php" class="btn btn-sky btn-sm">Mulai Catat Pesanan →</a>
        </div>
    <?php else: ?>
        <div class="alert alert-warning mb-4 py-3">
            ⚠️ Belum ada sesi bazar yang aktif saat ini. <a href="sesi.php" class="alert-link">Buka Sesi Bazar Baru</a> untuk memulai pencatatan pesanan.
        </div>
    <?php endif; ?>

    <!-- GRAFIK-GRAFIK KASIR -->
    <div class="row g-4">
        <?php if (!empty($settings['G1'])): ?>
            <div class="col-lg-6">
                <div class="card card-rebazaar p-3">
                    <h6 class="fw-bold mb-3">G1: Penjualan Hari Ini</h6>
                    <canvas id="chartG1" height="200"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($settings['G4'])): ?>
            <div class="col-lg-6">
                <div class="card card-rebazaar p-3">
                    <h6 class="fw-bold mb-3">G4: Pemasukan Bersih Per Sesi</h6>
                    <canvas id="chartG4" height="200"></canvas>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('chartG1')) {
        fetch('<?= BASE_URL ?>api/chart_penjualan.php?periode=hari')
            .then(res => res.json())
            .then(data => { new Chart(document.getElementById('chartG1'), { type: 'line', data: data }); });
    }

    if (document.getElementById('chartG4')) {
        fetch('<?= BASE_URL ?>api/chart_bersih.php')
            .then(res => res.json())
            .then(data => { new Chart(document.getElementById('chartG4'), { type: 'bar', data: data }); });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>