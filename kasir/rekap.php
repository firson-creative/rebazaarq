<?php
$page_title = "Rekap Hari Ini";
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
    echo '<main class="flex-grow-1 p-4"><div class="alert alert-warning m-0">⚠️ Tidak ada sesi bazar yang aktif saat ini. Harap <a href="sesi.php" class="alert-link">Buka Sesi Bazar</a> terlebih dahulu untuk melihat rekap harian.</div></main>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$sesi_id = $sesi_aktif['id'];

// Ambil Seluruh Daftar Produk Aktif (Untuk Dropdown Modal Edit)
$stmt_prod_master = $pdo->query("SELECT p.*, k.nama AS nama_kategori FROM produk p JOIN kategori k ON p.kategori_id = k.id WHERE p.aktif = 1 ORDER BY k.nama, p.nama");
$daftar_produk = $stmt_prod_master->fetchAll();
$produk_json = json_encode($daftar_produk);

// 1. PROSES UPDATE STATUS & METODE BAYAR CEPAT (DARI TABEL)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_trx_quick') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrf_token)) {
        $trx_id = (int)($_POST['transaksi_id'] ?? 0);
        $status_baru = $_POST['status_bayar'] ?? 'lunas';
        $metode_baru = $_POST['metode_bayar'] ?? 'qris';

        if ($metode_baru === 'belum_dibayar') {
            $status_baru = 'belum_lunas';
        }

        $stmt_upd = $pdo->prepare("UPDATE transaksi SET status = ?, metode_bayar = ? WHERE id = ? AND sesi_id = ?");
        if ($stmt_upd->execute([$status_baru, $metode_baru, $trx_id, $sesi_id])) {
            $success = "Status transaksi #{$trx_id} berhasil diperbarui.";
        } else {
            $error = "Gagal memperbarui status transaksi.";
        }
    }
}

// 2. PROSES EDIT FULL PESANAN (NAMA, ITEM, QTY, METODE, STATUS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_transaksi_full') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Sesi formulir tidak sah.";
    } else {
        $trx_id       = (int)($_POST['transaksi_id'] ?? 0);
        $nama_pemesan = trim($_POST['nama_pemesan'] ?? '');
        $metode_bayar = $_POST['metode_bayar'] ?? 'qris';
        $status_bayar = $_POST['status_bayar'] ?? 'lunas';
        $produk_ids   = $_POST['produk_id'] ?? [];
        $qtys         = $_POST['qty'] ?? [];

        if ($metode_bayar === 'belum_dibayar') {
            $status_bayar = 'belum_lunas';
        }

        $cart = [];
        $total_bayar = 0;

        for ($i = 0; $i < count($produk_ids); $i++) {
            $p_id = (int)($produk_ids[$i] ?? 0);
            $qty  = (int)($qtys[$i] ?? 0);

            if ($p_id > 0 && $qty > 0) {
                $harga_bazar = 0;
                $harga_asli  = 0;

                foreach ($daftar_produk as $prod) {
                    if ($prod['id'] == $p_id) {
                        $harga_bazar = (float)$prod['harga_bazar'];
                        $harga_asli  = (float)$prod['harga_asli'];
                        break;
                    }
                }

                $subtotal = $qty * $harga_bazar;
                $total_bayar += $subtotal;

                $cart[] = [
                    'produk_id'  => $p_id,
                    'qty'        => $qty,
                    'harga_bazar'=> $harga_bazar,
                    'harga_asli' => $harga_asli
                ];
            }
        }

        if (empty($cart)) {
            $error = "Harap pilih minimal 1 produk dengan kuantitas lebih dari 0.";
        } else {
            try {
                $pdo->beginTransaction();

                // Update Header Transaksi
                $stmt_u = $pdo->prepare("
                    UPDATE transaksi 
                    SET nama_pemesan = ?, total = ?, metode_bayar = ?, status = ? 
                    WHERE id = ? AND sesi_id = ?
                ");
                $stmt_u->execute([
                    $nama_pemesan !== '' ? $nama_pemesan : NULL,
                    $total_bayar,
                    $metode_bayar,
                    $status_bayar,
                    $trx_id,
                    $sesi_id
                ]);

                // Hapus Detail Lama
                $stmt_d = $pdo->prepare("DELETE FROM transaksi_detail WHERE transaksi_id = ?");
                $stmt_d->execute([$trx_id]);

                // Insert Detail Baru
                $stmt_ins = $pdo->prepare("
                    INSERT INTO transaksi_detail (transaksi_id, produk_id, qty, harga_bazar, harga_asli) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($cart as $c) {
                    $stmt_ins->execute([
                        $trx_id,
                        $c['produk_id'],
                        $c['qty'],
                        $c['harga_bazar'],$c['harga_asli']
                    ]);
                }

                $pdo->commit();$success = "Data pesanan #{$trx_id} berhasil diperbarui!";
            } catch (Exception $e) {
                $pdo->rollBack();$error = "Gagal memperbarui pesanan: " . $e->getMessage();
            }
        }
    }
}

// 3. PROSES HAPUS TRANSAKSI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'hapus_transaksi') {
    $csrf_token =$_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrf_token)) {
        $trx_id = (int)($_POST['transaksi_id'] ?? 0);
        try {
            $pdo->beginTransaction();

            $stmt_d =$pdo->prepare("DELETE FROM transaksi_detail WHERE transaksi_id = ?");
            $stmt_d->execute([$trx_id]);

            $stmt_t =$pdo->prepare("DELETE FROM transaksi WHERE id = ? AND sesi_id = ?");
            $stmt_t->execute([$trx_id,$sesi_id]);

            $pdo->commit();
            $success = "Transaksi #{$trx_id} berhasil dihapus.";
        } catch (Exception $e) {
            $pdo->rollBack();$error = "Gagal menghapus transaksi: " . $e->getMessage();
        }
    }
}

// FITUR EKSPOR SPREADSHEET (CSV / EXCEL)
// FITUR EKSPOR SPREADSHEET (CSV / EXCEL FORMATTED FOR EXCEL)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Set Header khusus untuk Microsoft Excel
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Rekap_Bazar_' . preg_replace('/[^a-zA-Z0-9]/', '_', $sesi_aktif['nama_kegiatan']) . '_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');

    // Tambahkan UTF-8 BOM agar Microsoft Excel otomatis membaca karakter & format tabel dengan benar
    fputs($output, "\xEF\xBB\xBF");

    // Header Tabel (Gunakan delimiter titik koma ';')
    $delimiter = ";";

    fputcsv($output, [
        'No', 
        'Kode Transaksi', 
        'Nama Pemesan', 
        'Pesanan (Menu)', 
        'Kategori', 
        'Harga Jual (Rp)', 
        'Harga Asli (Rp)', 
        'Margin Profit (Rp)', 
        'Metode Pembayaran', 
        'Status Pembayaran', 
        'Waktu Transaksi'
    ], $delimiter);

    $stmt_exp = $pdo->prepare("
        SELECT 
            t.id AS trx_id,
            t.kode,
            COALESCE(t.nama_pemesan, '-') AS nama_pemesan,
            GROUP_CONCAT(CONCAT(p.nama, ' (x', td.qty, ')') SEPARATOR ', ') AS item_pesanan,
            GROUP_CONCAT(DISTINCT k.nama SEPARATOR ', ') AS kategori_pesanan,
            SUM(td.qty * td.harga_bazar) AS total_harga_jual,
            SUM(td.qty * td.harga_asli) AS total_harga_asli,
            SUM(td.qty * (td.harga_bazar - td.harga_asli)) AS total_margin,
            t.metode_bayar,
            t.status,
            t.created_at
        FROM transaksi t
        JOIN transaksi_detail td ON td.transaksi_id = t.id
        JOIN produk p ON p.id = td.produk_id
        JOIN kategori k ON k.id = p.kategori_id
        WHERE t.sesi_id = ?
        GROUP BY t.id
        ORDER BY t.id DESC
    ");
    $stmt_exp->execute([$sesi_id]);
    
    $no = 1;
    while ($row = $stmt_exp->fetch(PDO::FETCH_ASSOC)) {
        // Format metode bayar & status bayar agar rapi
        $metode_txt = ($row['metode_bayar'] === 'belum_dibayar') ? 'Belum Dibayar' : strtoupper($row['metode_bayar']);
        $status_txt = ($row['status'] === 'lunas') ? 'LUNAS' : 'BELUM LUNAS';

        fputcsv($output, [
            $no++,
            $row['kode'],
            $row['nama_pemesan'],
            $row['item_pesanan'],
            $row['kategori_pesanan'],
            $row['total_harga_jual'],
            $row['total_harga_asli'],
            $row['total_margin'],
            $metode_txt,
            $status_txt,
            $row['created_at']
        ], $delimiter);
    }

    fclose($output);
    exit;
}

// 4. KARTU RINGKASAN METRIK KEUANGAN
$stmt_summary =$pdo->prepare("
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
$stmt_summary->execute([$sesi_id]);
$summary =$stmt_summary->fetch();

// 5. RINCIAN TRANSAKSI PER ITEM BAZAR
$stmt_list =$pdo->prepare("
    SELECT 
        t.id AS trx_id,
        t.kode,
        COALESCE(t.nama_pemesan, '') AS nama_pemesan,
        GROUP_CONCAT(CONCAT(p.nama, ' (x', td.qty, ')') SEPARATOR '<br>') AS pesanan_menu,
        GROUP_CONCAT(DISTINCT k.nama SEPARATOR ', ') AS kategori,
        SUM(td.qty * td.harga_bazar) AS harga_jual,
        SUM(td.qty * td.harga_asli) AS harga_asli,
        SUM(td.qty * (td.harga_bazar - td.harga_asli)) AS margin_profit,
        t.metode_bayar,
        t.status AS status_bayar,
        t.created_at
    FROM transaksi t
    JOIN transaksi_detail td ON td.transaksi_id = t.id
    JOIN produk p ON p.id = td.produk_id
    JOIN kategori k ON k.id = p.kategori_id
    WHERE t.sesi_id = ?
    GROUP BY t.id
    ORDER BY t.id DESC
");
$stmt_list->execute([$sesi_id]);
$rekap_list =$stmt_list->fetchAll();

// 6. AMBIL DETAIL ITEMS PER TRANSAKSI UNTUK MODAL EDIT JS
$trx_items = [];
if (!empty($rekap_list)) {
    $stmt_it =$pdo->prepare("SELECT transaksi_id, produk_id, qty FROM transaksi_detail");
    $stmt_it->execute();
    while ($row =$stmt_it->fetch(PDO::FETCH_ASSOC)) {
        $trx_items[$row['transaksi_id']][] = [
            'produk_id' => $row['produk_id'],
            'qty' => $row['qty']
        ];
    }
}
$trx_items_json = json_encode($trx_items);

$token = generate_csrf_token();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-grow-1 p-4 overflow-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="section-label mb-1">Laporan Operasional</p>
            <h2 class="heading-display h3 m-0">Rekap Sesi Aktif</h2>
            <small class="text-muted">Kegiatan: <strong><?= htmlspecialchars($sesi_aktif['nama_kegiatan']) ?></strong> (<?= htmlspecialchars($sesi_aktif['lokasi']) ?>)</small>
        </div>
        <div>
            <a href="rekap.php?export=excel" class="btn btn-success font-semibold">
                <i class="bi bi-file-earmark-excel me-1"></i> Ekspor Spreadsheet (.csv)
            </a>
        </div>
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

    <!-- RINGKASAN METRIK KEUANGAN -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card card-rebazaar p-3 border-start border-4 border-success">
                <span class="text-muted small fw-bold text-uppercase">Total Omset Lunas</span>
                <h4 class="fw-bold text-success my-1">Rp <?= number_format($summary['omset_lunas'], 0, ',', '.') ?></h4>
                <span class="small text-muted"><?= $summary['count_lunas'] ?> Transaksi Lunas</span>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card card-rebazaar p-3 border-start border-4 border-warning">
                <span class="text-muted small fw-bold text-uppercase">Transaksi Belum Lunas</span>
                <h4 class="fw-bold text-warning my-1">Rp <?= number_format($summary['omset_belum_lunas'], 0, ',', '.') ?></h4>
                <span class="small text-muted"><?= $summary['count_belum_lunas'] ?> Transaksi Pending/Kasbon</span>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card card-rebazaar p-3 border-start border-4 border-info">
                <span class="text-muted small fw-bold text-uppercase">Dibayarkan ke Pemilik Usaha</span>
                <h4 class="fw-bold text-info my-1">Rp <?= number_format($summary['bayar_pemilik_lunas'], 0, ',', '.') ?></h4>
                <span class="small text-muted">Berdasarkan Modal / Harga Asli</span>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card card-rebazaar p-3 border-start border-4 border-primary">
                <span class="text-muted small fw-bold text-uppercase">Profit Bersih Organisasi</span>
                <h4 class="fw-bold text-primary my-1">Rp <?= number_format($summary['profit_organisasi_lunas'], 0, ',', '.') ?></h4>
                <span class="small text-muted">Margin Keuntungan Bersih</span>
            </div>
        </div>
    </div>

    <!-- TABEL REKAP TRANSAKSI -->
    <div class="card card-rebazaar overflow-hidden">
        <div class="px-4 py-3 border-bottom bg-light d-flex justify-content-between align-items-center">
            <h5 class="m-0 fs-6 fw-bold text-muted">Daftar Transaksi Sesi Ini</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light border-bottom">
                    <tr>
                        <th class="ps-3" style="width: 40px;">No</th>
                        <th>Nama Pemesan</th>
                        <th>Pesanan (*Menu)</th>
                        <th>Kategori</th>
                        <th class="text-end">Harga Jual</th>
                        <th class="text-end">Harga Asli</th>
                        <th class="text-end">Margin (Profit)</th>
                        <th style="width: 140px;">Metode Bayar</th>
                        <th style="width: 150px;">Status Pembayaran</th>
                        <th class="text-center" style="width: 110px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rekap_list)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">Belum ada transaksi pada sesi bazar ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($rekap_list as$r): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-muted"><?= $no++ ?></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($r['nama_pemesan'] !== '' ?$r['nama_pemesan'] : '-') ?></td>
                                <td class="small"><?= $r['pesanan_menu'] ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['kategori']) ?></span></td>
                                <td class="text-end fw-bold">Rp <?= number_format($r['harga_jual'], 0, ',', '.') ?></td>
                                <td class="text-end text-muted">Rp <?= number_format($r['harga_asli'], 0, ',', '.') ?></td>
                                <td class="text-end fw-bold text-success">Rp <?= number_format($r['margin_profit'], 0, ',', '.') ?></td>
                                
                                <!-- DROPDOWN METODE BAYAR CEPAT -->
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= $token ?>">
                                        <input type="hidden" name="action" value="update_trx_quick">
                                        <input type="hidden" name="transaksi_id" value="<?= $r['trx_id'] ?>">
                                        <select name="metode_bayar" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="qris" <?= $r['metode_bayar'] === 'qris' ? 'selected' : '' ?>>QRIS</option>
                                            <option value="tunai" <?= $r['metode_bayar'] === 'tunai' ? 'selected' : '' ?>>Tunai</option>
                                            <option value="belum_dibayar" <?= $r['metode_bayar'] === 'belum_dibayar' ? 'selected' : '' ?>>⏳ Belum Dibayar</option>
                                        </select>
                                </td>
                                <td>
                                        <select name="status_bayar" class="form-select form-select-sm fw-semibold <?= $r['status_bayar'] === 'lunas' ? 'text-success border-success' : 'text-warning border-warning' ?>" onchange="this.form.submit()">
                                            <option value="lunas" <?= $r['status_bayar'] === 'lunas' ? 'selected' : '' ?>>🟢 Lunas</option>
                                            <option value="belum_lunas" <?= $r['status_bayar'] === 'belum_lunas' ? 'selected' : '' ?>>🟡 Belum Lunas</option>
                                        </select>
                                    </form>
                                </td>

                                <!-- TOMBOL EDIT & HAPUS -->
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary border-0 me-1" onclick="bukaModalEdit(<?= htmlspecialchars(json_encode($r)) ?>)" title="Edit Pesanan">
                                        ✏️
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= $token ?>">
                                        <input type="hidden" name="action" value="hapus_transaksi">
                                        <input type="hidden" name="transaksi_id" value="<?= $r['trx_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0" title="Hapus Transaksi">
                                            🗑️
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL EDIT PESANAN FULL -->
<div class="modal fade" id="modalEditPesanan" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="edit_transaksi_full">
                <input type="hidden" name="transaksi_id" id="edit_transaksi_id">

                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold">Edit Pesanan Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nama Pemesan / Pelanggan</label>
                            <input type="text" name="nama_pemesan" id="edit_nama_pemesan" class="form-control form-control-sm" placeholder="Contoh: Kak Andi">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Metode Bayar *</label>
                            <select name="metode_bayar" id="edit_metode_bayar" class="form-select form-select-sm">
                                <option value="qris">QRIS</option>
                                <option value="tunai">Tunai</option>
                                <option value="belum_dibayar">⏳ Belum Dibayar</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Status *</label>
                            <select name="status_bayar" id="edit_status_bayar" class="form-select form-select-sm">
                                <option value="lunas">🟢 Lunas</option>
                                <option value="belum_lunas">🟡 Belum Lunas</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label small fw-semibold m-0">Item Pesanan Menu *</label>
                        <button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="tambahEditBaris()">+ Tambah Item</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th style="width: 120px;" class="text-center">Jumlah</th>
                                    <th style="width: 50px;" class="text-center">Hapus</th>
                                </tr>
                            </thead>
                            <tbody id="editBodyPesanan"></tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sky btn-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const masterProduk = <?= $produk_json ?>;
const allTrxItems = <?= $trx_items_json ?>;

function bukaModalEdit(trx) {
    document.getElementById('edit_transaksi_id').value = trx.trx_id;
    document.getElementById('edit_nama_pemesan').value = trx.nama_pemesan;
    document.getElementById('edit_metode_bayar').value = trx.metode_bayar;
    document.getElementById('edit_status_bayar').value = trx.status_bayar;

    const tbody = document.getElementById('editBodyPesanan');
    tbody.innerHTML = '';

    const items = allTrxItems[trx.trx_id] || [];
    if (items.length > 0) {
        items.forEach(it => {
            tambahEditBaris(it.produk_id, it.qty);
        });
    } else {
        tambahEditBaris();
    }

    const modal = new bootstrap.Modal(document.getElementById('modalEditPesanan'));
    modal.show();
}

function tambahEditBaris(pId = '', qty = 1) {
    const tbody = document.getElementById('editBodyPesanan');
    const tr = document.createElement('tr');

    let optionsHtml = '<option value="">-- Pilih Produk --</option>';
    masterProduk.forEach(p => {
        const selected = (p.id == pId) ? 'selected' : '';
        optionsHtml += `<option value="${p.id}" ${selected}>[${p.nama_kategori}] ${p.nama} - Rp ${parseInt(p.harga_bazar).toLocaleString('id-ID')}</option>`;
    });

    tr.innerHTML = `
        <td>
            <select name="produk_id[]" class="form-select form-select-sm" required>
                ${optionsHtml}
            </select>
        </td>
        <td>
            <input type="number" name="qty[]" class="form-control form-control-sm text-center" value="${qty}" min="1" required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('tr').remove()">✕</button>
        </td>
    `;
    tbody.appendChild(tr);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>