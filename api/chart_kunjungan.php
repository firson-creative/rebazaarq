<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

$hari = (int)($_GET['hari'] ?? 7);

$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(tanggal, '%d/%m') AS label, COUNT(*) AS total
    FROM kunjungan
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY tanggal
    ORDER BY tanggal ASC
");
$stmt->execute([$hari]);
$data = $stmt->fetchAll();

echo json_encode([
    'labels' => array_column($data, 'label'),
    'datasets' => [
        [
            'label' => 'Pengunjung Unik',
            'data' => array_map('intval', array_column($data, 'total')),
            'backgroundColor' => '#4FB3E8'
        ]
    ]
]);