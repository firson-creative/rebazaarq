<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

// Ambil 5 sesi bazar terakhir
$stmt = $pdo->query("
    SELECT s.id, s.nama_kegiatan AS label,
           COALESCE(SUM(td.qty * (td.harga_bazar - td.harga_asli)), 0) AS bersih
    FROM sesi_bazar s
    LEFT JOIN transaksi t ON t.sesi_id = s.id AND t.status = 'lunas'
    LEFT JOIN transaksi_detail td ON td.transaksi_id = t.id
    GROUP BY s.id
    ORDER BY s.id DESC LIMIT 5
");
$data = array_reverse($stmt->fetchAll());

echo json_encode([
    'labels' => array_column($data, 'label'),
    'datasets' => [
        [
            'label' => 'Pemasukan Bersih (Rp)',
            'data' => array_map('floatval', array_column($data, 'bersih')),
            'backgroundColor' => '#F2B84B'
        ]
    ]
]);