<?php
$page_title = "Kelola Pengaturan Grafik";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role('admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Sesi formulir tidak sah.";
    } else {
        $grafik_status = $_POST['status'] ?? []; // Array [kode => 1/0]
        $periode_default = $_POST['periode'] ?? []; // Array [kode => periode]

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE pengaturan_grafik SET aktif = ?, periode_default = ? WHERE kode = ?");
            
            $kodes = ['G1', 'G2', 'G3', 'G4'];
            foreach ($kodes as $kode) {
                $aktif = isset($grafik_status[$kode]) ? 1 : 0;
                $periode = $periode_default[$kode] ?? 'hari';
                $stmt->execute([$aktif, $periode, $kode]);
            }

            $pdo->commit();
            $success = "Pengaturan grafik berhasil diperbarui.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Gagal memperbarui pengaturan grafik.";
        }
    }
}

$stmt = $pdo->query("SELECT * FROM pengaturan_grafik ORDER BY kode ASC");
$grafik_settings = $stmt->fetchAll(PDO::FETCH_UNIQUE);

$token = generate_csrf_token();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-grow-1 p-4 overflow-auto">
    <div class="mb-4">
        <p class="section-label mb-1">Pengaturan Dashboard</p>
        <h2 class="heading-display h3 m-0">Kelola Tampilan Grafik</h2>
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

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $token ?>">
        <div class="card card-rebazaar p-4 mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Kode</th>
                            <th>Judul Grafik</th>
                            <th>Status Aktif</th>
                            <th>Periode Default</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $info_grafik = [
                            'G1' => 'Grafik Penjualan (Omset)',
                            'G2' => 'Frekuensi Pemesanan vs Sesi Lalu',
                            'G3' => 'Kunjungan Website Unik',
                            'G4' => 'Pemasukan Bersih Organisasi'
                        ];
                        foreach ($info_grafik as $kode => $judul): 
                            $setting = $grafik_settings[$kode] ?? ['aktif' => 1, 'periode_default' => 'hari'];
                        ?>
                            <tr>
                                <td class="ps-3 fw-bold"><code><?= $kode ?></code></td>
                                <td><?= $judul ?></td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="status[<?= $kode ?>]" value="1" <?= $setting['aktif'] ? 'checked' : '' ?>>
                                        <label class="form-check-label small"><?= $setting['aktif'] ? 'Aktif' : 'Nonaktif' ?></label>
                                    </div>
                                </td>
                                <td>
                                    <select name="periode[<?= $kode ?>]" class="form-select form-select-sm" style="width: 150px;">
                                        <option value="hari" <?= $setting['periode_default'] === 'hari' ? 'selected' : '' ?>>Hari Ini</option>
                                        <option value="minggu" <?= $setting['periode_default'] === 'minggu' ? 'selected' : '' ?>>1 Minggu</option>
                                        <option value="bulan" <?= $setting['periode_default'] === 'bulan' ? 'selected' : '' ?>>1 Bulan</option>
                                        <option value="tahun" <?= $setting['periode_default'] === 'tahun' ? 'selected' : '' ?>>1 Tahun</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-end">
                <button type="submit" class="btn btn-sky">Simpan Pengaturan Grafik</button>
            </div>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>