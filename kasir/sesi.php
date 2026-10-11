<?php
$page_title = "Sesi Bazar";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['kasir', 'admin']);

$error = '';$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token =$_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {$error = "Sesi formulir tidak sah.";
    } else {
        $action =$_POST['action'] ?? '';

        if ($action === 'buka_sesi') {
            $nama_kegiatan = trim($_POST['nama_kegiatan'] ?? '');
            $lokasi        = trim($_POST['lokasi'] ?? '');
            $pemilik_usaha = trim($_POST['pemilik_usaha'] ?? '');

            if (empty($nama_kegiatan) || empty($lokasi) || empty($pemilik_usaha)) {
                $error = "Nama kegiatan, lokasi, dan pemilik usaha wajib diisi.";
            } else {
                $stmt_cek =$pdo->query("SELECT id FROM sesi_bazar WHERE status = 'buka'");
                if ($stmt_cek->fetch()) {$error = "Masih ada sesi bazar yang aktif. Harap tutup sesi aktif sebelum membuka sesi baru.";
                } else {
                    try {
                        $stmt_sesi =$pdo->prepare("
                            INSERT INTO sesi_bazar (nama_kegiatan, tanggal, lokasi, pemilik_usaha, waktu_buka, status, dibuka_oleh) 
                            VALUES (?, CURDATE(), ?, ?, NOW(), 'buka', ?)
                        ");
                        $stmt_sesi->execute([$nama_kegiatan,$lokasi, $pemilik_usaha,$_SESSION['user_id']]);

                        $success = "Sesi bazar baru berhasil dibuka!";
                    } catch (Exception $e) {$error = "Gagal membuka sesi: " . $e->getMessage();
                    }
                }
            }
        } elseif ($action === 'tutup_sesi') {
            $sesi_id = (int)($_POST['sesi_id'] ?? 0);
            $stmt_tutup =$pdo->prepare("UPDATE sesi_bazar SET status = 'tutup', waktu_tutup = NOW() WHERE id = ? AND status = 'buka'");
            if ($stmt_tutup->execute([$sesi_id])) {$success = "Sesi bazar berhasil ditutup.";
            } else {
                $error = "Gagal menutup sesi bazar.";
            }
        } elseif ($action === 'hapus_sesi') {
            $sesi_id = (int)($_POST['sesi_id'] ?? 0);
            try {
                $pdo->beginTransaction();

                $stmt_del_dt =$pdo->prepare("
                    DELETE td FROM transaksi_detail td 
                    JOIN transaksi t ON t.id = td.transaksi_id 
                    WHERE t.sesi_id = ?
                ");
                $stmt_del_dt->execute([$sesi_id]);

                $stmt_del_trx =$pdo->prepare("DELETE FROM transaksi WHERE sesi_id = ?");
                $stmt_del_trx->execute([$sesi_id]);

                $stmt_del_sesi =$pdo->prepare("DELETE FROM sesi_bazar WHERE id = ?");
                $stmt_del_sesi->execute([$sesi_id]);

                $pdo->commit();$success = "Sesi bazar berhasil dihapus.";
            } catch (Exception $e) {
                $pdo->rollBack();$error = "Gagal menghapus sesi: " . $e->getMessage();
            }
        }
    }
}

// Ambil Riwayat Sesi Bazar
$stmt_list =$pdo->query("SELECT * FROM sesi_bazar ORDER BY id DESC");
$sesi_list =$stmt_list->fetchAll();

// Cek Sesi Aktif
$stmt_aktif =$pdo->query("SELECT * FROM sesi_bazar WHERE status = 'buka' LIMIT 1");
$sesi_aktif =$stmt_aktif->fetch();

// Ambil Rekapan Data untuk Modal Detail
$rekap_per_sesi = [];
if (!empty($sesi_list)) {
    foreach ($sesi_list as$s_item) {
        $s_id =$s_item['id'];

        $stmt_sum =$pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN t.status = 'lunas' THEN t.total ELSE 0 END), 0) AS omset_lunas,
                COALESCE(SUM(CASE WHEN t.status = 'belum_lunas' THEN t.total ELSE 0 END), 0) AS omset_belum_lunas,
                COUNT(CASE WHEN t.status = 'lunas' THEN 1 END) AS count_lunas,
                COUNT(CASE WHEN t.status = 'belum_lunas' THEN 1 END) AS count_belum_lunas,
                COALESCE(SUM(CASE WHEN t.status = 'lunas' THEN td.qty * td.harga_asli ELSE 0 END), 0) AS bayar_pemilik_lunas,
                COALESCE(SUM(CASE WHEN t.status = 'lunas' THEN td.qty * (td.harga_bazar - td.harga_asli) ELSE 0 END), 0) AS profit_organisasi_lunas
            FROM transaksi t
            JOIN transaksi_detail td ON td.transaksi_id = t.id
            WHERE t.sesi_id = ?
        ");
        $stmt_sum->execute([$s_id]);
        $summary_data =$stmt_sum->fetch();

        $stmt_trx_list =$pdo->prepare("
            SELECT 
                t.id AS trx_id,
                COALESCE(t.nama_pemesan, '-') AS nama_pemesan,
                GROUP_CONCAT(CONCAT(p.nama, ' (x', td.qty, ')') SEPARATOR ', ') AS pesanan_menu,
                SUM(td.qty * td.harga_bazar) AS harga_jual,
                t.metode_bayar,
                t.status AS status_bayar
            FROM transaksi t
            JOIN transaksi_detail td ON td.transaksi_id = t.id
            JOIN produk p ON p.id = td.produk_id
            WHERE t.sesi_id = ?
            GROUP BY t.id
            ORDER BY t.id DESC
        ");
        $stmt_trx_list->execute([$s_id]);
        $trx_list =$stmt_trx_list->fetchAll(PDO::FETCH_ASSOC);

        $rekap_per_sesi[$s_id] = [
            'summary' => $summary_data,
            'transaksi' => $trx_list
        ];
    }
}

$rekap_json = json_encode($rekap_per_sesi);$token = generate_csrf_token();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-grow-1 p-4 overflow-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="section-label mb-1">Operasional Bazar</p>
            <h2 class="heading-display h3 m-0">Sesi Bazar</h2>
        </div>
        <?php if (!$sesi_aktif): ?>
            <button class="btn btn-sky" data-bs-toggle="modal" data-bs-target="#modalBukaSesi">
                <i class="bi bi-plus-lg me-1"></i> Buka Sesi Bazar Baru
            </button>
        <?php else: ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menutup sesi bazar ini?');">
                <input type="hidden" name="csrf_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="tutup_sesi">
                <input type="hidden" name="sesi_id" value="<?= $sesi_aktif['id'] ?>">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-x-circle me-1"></i> Tutup Sesi Aktif (<?= htmlspecialchars($sesi_aktif['nama_kegiatan']) ?>)
                </button>
            </form>
        <?php endif; ?>
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

    <?php if (!$sesi_aktif): ?>
        <div class="alert alert-info mb-4">
            ℹ️ Belum ada sesi bazar yang aktif saat ini. Silakan klik tombol <strong>"+ Buka Sesi Bazar Baru"</strong> untuk memulai pencatatan pesanan.
        </div>
    <?php endif; ?>

    <div class="card card-rebazaar overflow-hidden">
        <div class="px-4 py-3 border-bottom bg-light">
            <h5 class="m-0 fs-6 fw-bold text-muted">Riwayat Sesi Bazar</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light border-bottom">
                    <tr>
                        <th class="ps-3">Kegiatan</th>
                        <th>Tanggal</th>
                        <th>Lokasi</th>
                        <th>Pemilik Usaha</th>
                        <th>Waktu Buka</th>
                        <th>Waktu Tutup</th>
                        <th>Status</th>
                        <th class="text-center" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sesi_list)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Belum ada riwayat sesi bazar.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sesi_list as$s): ?>
                            <tr>
                                <td class="ps-3 fw-bold">
                                    <a href="javascript:void(0)" class="text-decoration-none text-primary" onclick="bukaModalRekap(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['nama_kegiatan'])) ?>', '<?= htmlspecialchars(addslashes($s['lokasi'])) ?>')">
                                        <?= htmlspecialchars($s['nama_kegiatan']) ?>
                                    </a>
                                </td>
                                <td><?= date('d/m/Y', strtotime($s['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($s['lokasi']) ?></td>
                                <td><?= htmlspecialchars($s['pemilik_usaha']) ?></td>
                                <td class="small text-muted"><?= date('H:i', strtotime($s['waktu_buka'])) ?></td>
                                <td class="small text-muted"><?= $s['waktu_tutup'] ? date('H:i', strtotime($s['waktu_tutup'])) : '-' ?></td>
                                <td>
                                    <?php if ($s['status'] === 'buka'): ?>
                                        <span class="badge bg-success">Berlangsung</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Selesai</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-light border me-1" onclick="bukaModalRekap(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['nama_kegiatan'])) ?>', '<?= htmlspecialchars(addslashes($s['lokasi'])) ?>')" title="Lihat Rekapan Sesi">
                                        <i class="bi bi-bar-chart-line text-primary"></i>
                                    </button>

                                    <?php if ($s['status'] === 'tutup'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus sesi bazar ini? Semua transaksi terkait akan ikut terhapus!');">
                                            <input type="hidden" name="csrf_token" value="<?= $token ?>">
                                            <input type="hidden" name="action" value="hapus_sesi">
                                            <input type="hidden" name="sesi_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-light border" title="Hapus Sesi">
                                                <i class="bi bi-trash text-danger"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL BUKA SESI BAZAR BARU -->
<div class="modal fade" id="modalBukaSesi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="buka_sesi">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold">Buka Sesi Bazar Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Kegiatan *</label>
                        <input type="text" name="nama_kegiatan" class="form-control" placeholder="Misal: Bazar Dies Natalis" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Lokasi Bazar *</label>
                        <input type="text" name="lokasi" class="form-control" placeholder="Misal: Warkop RMS" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Pemilik Usaha / Mitra *</label>
                        <input type="text" name="pemilik_usaha" class="form-control" placeholder="Misal: Ibu Maya" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sky btn-sm">Buka Sesi Bazar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETAIL REKAPAN SESI -->
<div class="modal fade" id="modalDetailRekap" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fs-6 fw-bold" id="m_kegiatan_title">Detail Rekap Sesi</h5>
                    <small class="text-muted" id="m_lokasi_subtitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card p-3 bg-light border-0">
                            <span class="text-muted small fw-bold text-uppercase">Total Omset Lunas</span>
                            <h4 class="fw-bold text-success my-1" id="m_omset_lunas">Rp 0</h4>
                            <span class="small text-muted" id="m_count_lunas">0 Transaksi</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 bg-light border-0">
                            <span class="text-muted small fw-bold text-uppercase">Belum Lunas</span>
                            <h4 class="fw-bold text-warning my-1" id="m_omset_belum">Rp 0</h4>
                            <span class="small text-muted" id="m_count_belum">0 Transaksi</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 bg-light border-0">
                            <span class="text-muted small fw-bold text-uppercase">Setoran Ke Pemilik</span>
                            <h4 class="fw-bold text-info my-1" id="m_bayar_pemilik">Rp 0</h4>
                            <span class="small text-muted">Modal / Harga Asli</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 bg-light border-0">
                            <span class="text-muted small fw-bold text-uppercase">Profit Organisasi</span>
                            <h4 class="fw-bold text-primary my-1" id="m_profit_organisasi">Rp 0</h4>
                            <span class="small text-muted">Margin Bersih</span>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-3">Daftar Transaksi Sesi Ini</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Nama Pemesan</th>
                                <th>Pesanan Item</th>
                                <th class="text-end">Total Bayar</th>
                                <th>Metode</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="m_tbody_transaksi">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
const dataRekap = <?= $rekap_json ?>;

function bukaModalRekap(sesiId, namaKegiatan, lokasi) {
    document.getElementById('m_kegiatan_title').innerText = 'Rekapan: ' + namaKegiatan;
    document.getElementById('m_lokasi_subtitle').innerText = 'Lokasi: ' + lokasi;

    const r = dataRekap[sesiId] || { summary: {}, transaksi: [] };
    const s = r.summary || {};

    document.getElementById('m_omset_lunas').innerText = 'Rp ' + (parseFloat(s.omset_lunas) || 0).toLocaleString('id-ID');
    document.getElementById('m_count_lunas').innerText = (s.count_lunas || 0) + ' Transaksi Lunas';

    document.getElementById('m_omset_belum').innerText = 'Rp ' + (parseFloat(s.omset_belum_lunas) || 0).toLocaleString('id-ID');
    document.getElementById('m_count_belum').innerText = (s.count_belum_lunas || 0) + ' Transaksi Pending';

    document.getElementById('m_bayar_pemilik').innerText = 'Rp ' + (parseFloat(s.bayar_pemilik_lunas) || 0).toLocaleString('id-ID');
    document.getElementById('m_profit_organisasi').innerText = 'Rp ' + (parseFloat(s.profit_organisasi_lunas) || 0).toLocaleString('id-ID');

    const tbody = document.getElementById('m_tbody_transaksi');
    tbody.innerHTML = '';

    if (r.transaksi && r.transaksi.length > 0) {
        let no = 1;
        r.transaksi.forEach(t => {
            const tr = document.createElement('tr');
            const statusBadge = (t.status_bayar === 'lunas') 
                ? '<span class="badge bg-success">🟢 Lunas</span>' 
                : '<span class="badge bg-warning text-dark">🟡 Belum Lunas</span>';
            
            const metodeTxt = (t.metode_bayar === 'belum_dibayar') ? '⏳ Belum Dibayar' : t.metode_bayar.toUpperCase();

            tr.innerHTML = `
                <td>${no++}</td>
                <td class="fw-semibold">${t.nama_pemesan}</td>
                <td class="small">${t.pesanan_menu}</td>
                <td class="text-end fw-bold">Rp ${parseFloat(t.harga_jual).toLocaleString('id-ID')}</td>
                <td><span class="badge bg-light text-dark border">${metodeTxt}</span></td>
                <td>${statusBadge}</td>
            `;
            tbody.appendChild(tr);
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada transaksi pada sesi ini.</td></tr>';
    }

    const modal = new bootstrap.Modal(document.getElementById('modalDetailRekap'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>