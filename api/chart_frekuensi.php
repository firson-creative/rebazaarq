<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

// Ambil Sesi Aktif
$stmt_aktif = $pdo->query("SELECT id, nama_kegiatan FROM sesi_bazar WHERE status = 'buka' LIMIT 1");
$sesi_aktif = $stmt_aktif->fetch();

// Ambil Sesi Sebelum Nya (Tutup)
$stmt_sebelum = $pdo->query("SELECT id, nama_kegiatan FROM sesi_bazar WHERE status = 'tutup' ORDER BY id DESC LIMIT 1");
$sesi_sebelum = $stmt_sebelum->fetch();

$labels = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00'];

function get_frekuensi_sesi($pdo, $sesi_id) {
    if (!$sesi_id) return array_fill(0, 15, 0);
    
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%H:00') AS jam, COUNT(*) AS total
        FROM transaksi
        WHERE sesi_id = ? AND status != 'batal'
        GROUP BY DATE_FORMAT(created_at, '%H')
    ");
    $stmt->execute([$sesi_id]);
    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $data = [];
    $hours = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00'];
    foreach ($hours as $h) {
        $data[] = (int)($results[$h] ?? 0);
    }
    return $data;
}

$data_aktif = get_frekuensi_sesi($pdo, $sesi_aktif['id'] ?? 0);
$data_sebelum = get_frekuensi_sesi($pdo, $sesi_sebelum['id'] ?? 0);

echo json_encode([
    'labels' => $labels,
    'datasets' => [
        [
            'label' => $sesi_aktif ? $sesi_aktif['nama_kegiatan'] . ' (Sesi Aktif)' : 'Sesi Aktif',
            'data' => $data_aktif,
            'borderColor' => '#1B6FA8',
            'backgroundColor' => '#1B6FA8',
            'tension' => 0.2
        ],
        [
            'label' => $sesi_sebelum ? $sesi_sebelum['nama_kegiatan'] . ' (Sesi Lalu)' : 'Sesi Lalu',
            'data' => $data_sebelum,
            'borderColor' => '#ccc',
            'backgroundColor' => '#ccc',
            'borderDash' => [5, 5],
            'tension' => 0.2
        ]
    ]
]);