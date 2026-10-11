<?php
$page_title = "Dashboard Admin";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

require_role('admin');

// Total Pengguna & Kasir Aktif
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pending_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

// Total Pemasukan Kotor Global
$total_omset = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM transaksi WHERE status = 'lunas'")->fetchColumn();

// Pengaturan Grafik Active
$settings = $pdo->query("SELECT kode, aktif FROM pengaturan_grafik")->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-grow-1 p-4 overflow-auto">
    <div class="mb-4">
        <p class="section-label mb-1">Ikhtisar Sistem</p>
        <h2 class="heading-display h3 m-0">Dashboard Admin</h2>
    </div>

    <!-- METRIK CARD -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card card-rebazaar p-3">
                <span class="text-muted small fw-bold text-uppercase">Total Omset Penjualan</span>
                <h3 class="fw-bold text-primary my-1">Rp <?= number_format($total_omset, 0, ',', '.') ?></h3>
                <span class="small text-muted">Seluruh transaksi lunas</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-rebazaar p-3">
                <span class="text-muted small fw-bold text-uppercase">Pengguna Terdaftar</span>
                <h3 class="fw-bold text-dark my-1"><?= $total_users ?> Akun</h3>
                <span class="small text-muted">Admin & Kasir/Panitia</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-rebazaar p-3">
                <span class="text-muted small fw-bold text-uppercase">Persetujuan Akun Pending</span>
                <h3 class="fw-bold text-warning my-1"><?= $pending_users ?> Akun</h3>
                <a href="pengguna.php" class="small text-decoration-none">Kelola di menu pengguna →</a>
            </div>
        </div>
    </div>

    <!-- GRAFIK-GRAFIK -->
    <div class="row g-4">
        <?php if (!empty($settings['G1'])): ?>
            <div class="col-lg-6">
                <div class="card card-rebazaar p-3">
                    <h6 class="fw-bold mb-3">G1: Trend Penjualan</h6>
                    <canvas id="chartG1" height="200"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($settings['G2'])): ?>
            <div class="col-lg-6">
                <div class="card card-rebazaar p-3">
                    <h6 class="fw-bold mb-3">G2: Frekuensi Pesanan vs Sesi Lalu</h6>
                    <canvas id="chartG2" height="200"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($settings['G3'])): ?>
            <div class="col-lg-6">
                <div class="card card-rebazaar p-3">
                    <h6 class="fw-bold mb-3">G3: Kunjungan Website Unik</h6>
                    <canvas id="chartG3" height="200"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($settings['G4'])): ?>
            <div class="col-lg-6">
                <div class="card card-rebazaar p-3">
                    <h6 class="fw-bold mb-3">G4: Pemasukan Bersih Organisasi</h6>
                    <canvas id="chartG4" height="200"></canvas>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('chartG1')) {
        fetch('<?= BASE_URL ?>api/chart_penjualan.php?periode=hari')
            .then(res => res.json())
            .then(data => {
                new Chart(document.getElementById('chartG1'), { type: 'line', data: data });
            }).catch(e => console.error(e));
    }

    if (document.getElementById('chartG2')) {
        fetch('<?= BASE_URL ?>api/chart_frekuensi.php')
            .then(res => res.json())
            .then(data => {
                new Chart(document.getElementById('chartG2'), { type: 'line', data: data });
            }).catch(e => console.error(e));
    }

    if (document.getElementById('chartG3')) {
        fetch('<?= BASE_URL ?>api/chart_kunjungan.php?hari=7')
            .then(res => res.json())
            .then(data => {
                new Chart(document.getElementById('chartG3'), { type: 'bar', data: data });
            }).catch(e => console.error(e));
    }

    if (document.getElementById('chartG4')) {
        fetch('<?= BASE_URL ?>api/chart_pemasukan_bersih.php')
            .then(res => res.json())
            .then(data => {
                new Chart(document.getElementById('chartG4'), { type: 'bar', data: data });
            }).catch(e => console.error(e));
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>