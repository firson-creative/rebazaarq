<?php
require_once __DIR__ . '/config.php';

$role = $_SESSION['role'] ?? 'kasir';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Tombol Toggle Offcanvas untuk Layar Kecil -->
<div class="d-lg-none p-3 bg-white border-bottom w-100 position-fixed top-0 start-0 z-3 d-flex justify-content-between align-items-center">
    <span class="navbar-brand-wordmark fs-5"><?= APP_NAME ?></span>
    <button class="btn btn-sky-outline btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu">
        <i class="bi bi-list fs-5"></i>
    </button>
</div>

<!-- Sidebar Main Container -->
<div class="offcanvas-lg offcanvas-start sidebar d-flex flex-column flex-shrink-0 p-3 bg-white" tabindex="-1" id="sidebarMenu">
    <div class="d-flex align-items-center justify-content-between mb-4 px-2">
        <div>
            <h1 class="navbar-brand-wordmark fs-4 m-0"><?= APP_NAME ?></h1>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill text-uppercase px-2 py-1" style="font-size: 0.65rem;">
                <?= htmlspecialchars($role) ?>
            </span>
        </div>
        <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu"></button>
    </div>

    <ul class="nav nav-pills flex-column mb-auto">
        <?php if ($role === 'admin'): ?>
            <!-- Menu Admin -->
            <li class="nav-item">
                <a href="<?= BASE_URL ?>admin/dashboard.php" class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>admin/pengguna.php" class="nav-link <?= $current_page === 'pengguna.php' ? 'active' : '' ?>">
                    <i class="bi bi-people me-2"></i> Kelola Pengguna
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>admin/laporan.php" class="nav-link <?= $current_page === 'laporan.php' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i> Laporan
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>admin/grafik.php" class="nav-link <?= $current_page === 'grafik.php' ? 'active' : '' ?>">
                    <i class="bi bi-sliders me-2"></i> Kelola Grafik
                </a>
            </li>
        <?php else: ?>
            <!-- Menu Kasir / Panitia -->
            <li class="nav-item">
                <a href="<?= BASE_URL ?>kasir/dashboard.php" class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>kasir/sesi.php" class="nav-link <?= $current_page === 'sesi.php' ? 'active' : '' ?>">
                    <i class="bi bi-shop me-2"></i> Sesi Bazar
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>kasir/kategori.php" class="nav-link <?= $current_page === 'kategori.php' ? 'active' : '' ?>">
                    <i class="bi bi-tags me-2"></i> Kategori
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>kasir/produk.php" class="nav-link <?= $current_page === 'produk.php' ? 'active' : '' ?>">
                    <i class="bi bi-box-seam me-2"></i> Produk Menu
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>kasir/pesanan.php" class="nav-link <?= $current_page === 'pesanan.php' ? 'active' : '' ?>">
                    <i class="bi bi-cart-check me-2"></i> Catat Pesanan
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>kasir/pengeluaran.php" class="nav-link <?= $current_page === 'pengeluaran.php' ? 'active' : '' ?>">
                    <i class="bi bi-receipt me-2"></i> Pengeluaran
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>kasir/rekap.php" class="nav-link <?= $current_page === 'rekap.php' ? 'active' : '' ?>">
                    <i class="bi bi-journal-text me-2"></i> Rekap Hari Ini
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <hr class="my-3">

    <div class="dropdown px-2">
        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle fs-4 me-2 text-secondary"></i>
            <div class="lh-1 overflow-hidden">
                <div class="fw-bold text-truncate" style="max-width: 130px;"><?= htmlspecialchars($_SESSION['nama'] ?? 'Pengguna') ?></div>
                <small class="text-muted">@<?= htmlspecialchars($_SESSION['username'] ?? 'user') ?></small>
            </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="dropdownUser">
            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Keluar</a></li>
        </ul>
    </div>
</div>