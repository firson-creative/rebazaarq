<?php
$page_title = "Catat Pesanan (POS)";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['kasir', 'admin']);

$error = '';$success = '';

// Ambil Sesi Bazar Aktif
$stmt_sesi =$pdo->query("SELECT * FROM sesi_bazar WHERE status = 'buka' LIMIT 1");
$sesi_aktif =$stmt_sesi->fetch();

if (!$sesi_aktif) {
    require_once __DIR__ . '/../includes/header.php';
    require_once __DIR__ . '/../includes/sidebar.php';
    echo '<main class="flex-grow-1 p-4"><div class="alert alert-warning m-0">⚠️ Tidak ada sesi bazar yang aktif saat ini. Harap <a href="sesi.php" class="alert-link">Buka Sesi Bazar</a> terlebih dahulu untuk mencatat pesanan.</div></main>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Ambil Daftar Produk Aktif beserta Kategori
$stmt_produk =$pdo->prepare("
    SELECT p.*, k.nama AS nama_kategori 
    FROM produk p 
    JOIN kategori k ON p.kategori_id = k.id 
    WHERE p.aktif = 1 
    ORDER BY k.nama ASC, p.nama ASC
");
$stmt_produk->execute();
$produk_list =$stmt_produk->fetchAll();

// Map array produk untuk JS
$produk_json = json_encode($produk_list);

// Proses Simpan Transaksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token =$_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {$error = "Sesi formulir tidak sah.";
    } else {
        $nama_pemesan = trim($_POST['nama_pemesan'] ?? '');
        $produk_ids   =$_POST['produk_id'] ?? [];
        $qtys         =$_POST['qty'] ?? [];
        $metode_bayar =$_POST['metode_bayar'] ?? 'belum_dibayar';
        $status_bayar =$_POST['status_bayar'] ?? 'belum_lunas';

        // Jika metode bayar 'belum_dibayar', otomatis set status ke 'belum_lunas'
        if ($metode_bayar === 'belum_dibayar') {$status_bayar = 'belum_lunas';
        }

        $cart = [];$total_bayar = 0;

        for ($i = 0; $i < count($produk_ids);$i++) {
            $p_id = (int)($produk_ids[$i] ?? 0);$qty  = (int)($qtys[$i] ?? 0);

            if ($p_id > 0 && $qty > 0) {$harga_bazar = 0;
                $harga_asli  = 0;

                foreach ($produk_list as$prod) {
                    if ($prod['id'] ==$p_id) {
                        $harga_bazar = (float)$prod['harga_bazar'];
                        $harga_asli  = (float)$prod['harga_asli'];
                        break;
                    }
                }

                $subtotal = $qty * $harga_bazar;
                $total_bayar +=$subtotal;

                $cart[] = [
                    'produk_id'  => $p_id,
                    'qty'        => $qty,
                    'harga_bazar'=> $harga_bazar,
                    'harga_asli' => $harga_asli
                ];
            }
        }

        if (empty($cart)) {$error = "Harap tambahkan minimal 1 item produk dengan jumlah lebih dari 0.";
        } else {
            try {
                $pdo->beginTransaction();

                $kode_trx = "TRX-" . date('Ymd') . "-" . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

                $stmt_trx =$pdo->prepare("
                    INSERT INTO transaksi (sesi_id, kasir_id, nama_pemesan, kode, total, metode_bayar, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt_trx->execute([
                    $sesi_aktif['id'],$_SESSION['user_id'],
                    $nama_pemesan !== '' ?$nama_pemesan : NULL,
                    $kode_trx,$total_bayar,
                    $metode_bayar,$status_bayar
                ]);
                $transaksi_id =$pdo->lastInsertId();

                $stmt_detail =$pdo->prepare("
                    INSERT INTO transaksi_detail (transaksi_id, produk_id, qty, harga_bazar, harga_asli) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($cart as$c) {
                    $stmt_detail->execute([$transaksi_id,
                        $c['produk_id'],$c['qty'],
                        $c['harga_bazar'],$c['harga_asli']
                    ]);
                }

                $pdo->commit();

                $status_label = ($status_bayar === 'lunas') ? '<span class="badge bg-success">LUNAS</span>' : '<span class="badge bg-warning text-dark">BELUM LUNAS</span>';
                $pemesan_txt  = !empty($nama_pemesan) ? " (Pemesan: <strong>" . htmlspecialchars($nama_pemesan) . "</strong>)" : "";

                $success = "Transaksi berhasil disimpan! Kode: <strong>" . htmlspecialchars($kode_trx) . "</strong>{$pemesan_txt} — Status: {$status_label} (Total: Rp " . number_format($total_bayar, 0, ',', '.') . ")";
            } catch (Exception $e) {
                $pdo->rollBack();$error = "Gagal memproses transaksi: " . $e->getMessage();
            }
        }
    }
}

$token = generate_csrf_token();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-grow-1 p-4 overflow-auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="section-label mb-1">Kasir POS</p>
            <h2 class="heading-display h3 m-0">Catat Pesanan</h2>
            <small class="text-muted">Sesi Aktif: <strong><?= htmlspecialchars($sesi_aktif['nama_kegiatan']) ?></strong></small>
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
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="formPOS">
        <input type="hidden" name="csrf_token" value="<?= $token ?>">
        <div class="row g-4">
            <!-- TABEL PEMILIHAN ITEM PESANAN -->
            <div class="col-lg-8">
                <div class="card card-rebazaar overflow-hidden mb-3">
                    <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                        <h5 class="m-0 fs-6 fw-bold">Daftar Pesanan Item</h5>
                        <button type="button" class="btn btn-sm btn-sky" onclick="tambahBaris()">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Item
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tabelPesanan">
                            <thead class="table-light border-bottom">
                                <tr>
                                    <th style="width: 55%;">Produk</th>
                                    <th style="width: 20%;" class="text-center">Jumlah</th>
                                    <th style="width: 20%;" class="text-end">Subtotal</th>
                                    <th style="width: 5%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="bodyPesanan"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- RINGKASAN PEMBAYARAN & IDENTITAS -->
            <div class="col-lg-4">
                <div class="card card-rebazaar p-3 position-sticky" style="top: 20px;">
                    <h5 class="fw-bold fs-6 mb-3">Ringkasan Pembayaran</h5>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3 fs-4 fw-bold border-bottom pb-3">
                        <span>Total:</span>
                        <span class="text-primary" id="displayTotal">Rp 0</span>
                    </div>

                    <!-- NAMA PEMESAN -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Pemesan / Pelanggan (Opsional)</label>
                        <input type="text" name="nama_pemesan" class="form-control" placeholder="Contoh: Kak Andi / Meja 5">
                    </div>

                    <!-- METODE PEMBAYARAN -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Metode Pembayaran *</label>
                        <select name="metode_bayar" id="selectMetodeBayar" class="form-select" onchange="syncStatusByMetode(this.value)">
                            <option value="qris">QRIS / Transfer DANA/BRImo</option>
                            <option value="tunai">Tunai / Cash</option>
                            <option value="belum_dibayar">⏳ Belum Dibayar (Pending/Kasbon)</option>
                        </select>
                    </div>

                    <!-- STATUS PEMBAYARAN -->
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Status Pembayaran *</label>
                        <select name="status_bayar" id="selectStatusBayar" class="form-select" onchange="syncMetodeByStatus(this.value)">
                            <option value="lunas" selected>🟢 Lunas (Langsung Bayar)</option>
                            <option value="belum_lunas">🟡 Belum Lunas (Bayar Belakangan)</option>
                        </select>
                        <small class="text-muted d-block mt-1">Gunakan 'Belum Dibayar' / 'Belum Lunas' jika pesanan akan diselesaikan di akhir acara.</small>
                    </div>

                    <button type="submit" class="btn btn-sky w-100 py-2 fs-6 fw-bold">
                        Simpan Transaksi
                    </button>
                </div>
            </div>
        </div>
    </form>
</main>

<script>
const daftarProduk = <?= $produk_json ?>;

function tambahBaris() {
    const tbody = document.getElementById('bodyPesanan');
    const tr = document.createElement('tr');
    tr.className = 'baris-pesanan';

    let optionsHtml = '<option value="">-- Pilih Produk --</option>';
    daftarProduk.forEach(p => {
        optionsHtml += `<option value="${p.id}" data-harga="${p.harga_bazar}">[${p.nama_kategori}] ${p.nama} - Rp ${parseInt(p.harga_bazar).toLocaleString('id-ID')}</option>`;
    });

    tr.innerHTML = `
        <td>
            <select name="produk_id[]" class="form-select form-select-sm select-produk" onchange="hitungSubtotal(this)" required>
                ${optionsHtml}
            </select>
        </td>
        <td>
            <input type="number" name="qty[]" class="form-control form-control-sm text-center input-qty" value="1" min="1" oninput="hitungSubtotal(this)">
        </td>
        <td class="text-end fw-bold span-subtotal">
            Rp 0
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="hapusBaris(this)">
                ✕
            </button>
        </td>
    `;

    tbody.appendChild(tr);
    hitungGrandTotal();
}

function hitungSubtotal(inputEl) {
    const tr = inputEl.closest('tr');
    const selectEl = tr.querySelector('.select-produk');
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    
    const harga = parseFloat(selectedOption.getAttribute('data-harga')) || 0;
    const qty = parseInt(tr.querySelector('.input-qty').value) || 0;
    const subtotal = harga * qty;

    tr.querySelector('.span-subtotal').innerText = 'Rp ' + subtotal.toLocaleString('id-ID');
    hitungGrandTotal();
}

function hapusBaris(btnEl) {
    const tbody = document.getElementById('bodyPesanan');
    if (tbody.querySelectorAll('.baris-pesanan').length > 1) {
        btnEl.closest('tr').remove();
        hitungGrandTotal();
    } else {
        alert('Minimal harus ada 1 baris item pesanan.');
    }
}

function hitungGrandTotal() {
    let grandTotal = 0;
    const baris = document.querySelectorAll('.baris-pesanan');
    
    baris.forEach(tr => {
        const selectEl = tr.querySelector('.select-produk');
        const selectedOption = selectEl.options[selectEl.selectedIndex];
        const harga = parseFloat(selectedOption.getAttribute('data-harga')) || 0;
        const qty = parseInt(tr.querySelector('.input-qty').value) || 0;
        grandTotal += (harga * qty);
    });

    document.getElementById('displayTotal').innerText = 'Rp ' + grandTotal.toLocaleString('id-ID');
}

// SINKRONISASI DROPDOWN METODE & STATUS
function syncStatusByMetode(metode) {
    const selectStatus = document.getElementById('selectStatusBayar');
    if (metode === 'belum_dibayar') {
        selectStatus.value = 'belum_lunas';
    } else {
        selectStatus.value = 'lunas';
    }
}

function syncMetodeByStatus(status) {
    const selectMetode = document.getElementById('selectMetodeBayar');
    if (status === 'belum_lunas') {
        selectMetode.value = 'belum_dibayar';
    } else if (selectMetode.value === 'belum_dibayar') {
        selectMetode.value = 'qris';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    tambahBaris();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>