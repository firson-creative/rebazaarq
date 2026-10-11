<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

$periode = $_GET['periode'] ?? 'hari'; // hari, minggu, bulan, tahun

if ($periode === 'hari') {
    // Per jam untuk hari ini
    $sql = "
        SELECT DATE_FORMAT(created_at, '%H:00') AS label, COALESCE(SUM(total), 0) AS total
        FROM transaksi
        WHERE status = 'lunas' AND DATE(created_at) = CURDATE()
        GROUP BY DATE_FORMAT(created_at, '%H')
        ORDER BY label ASC
    ";
} elseif ($periode === 'minggu') {
    // 7 hari terakhir
    $sql = "
        SELECT DATE_FORMAT(created_at, '%d/%m') AS label, COALESCE(SUM(total), 0) AS total
        FROM transaksi
        WHERE status = 'lunas' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY created_at ASC
    ";
} elseif ($periode === 'bulan') {
    // 30 hari terakhir
    $sql = "
        SELECT DATE_FORMAT(created_at, '%d/%m') AS label, COALESCE(SUM(total), 0) AS total
        FROM transaksi
        WHERE status = 'lunas' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY created_at ASC
    ";
} else {
    // 12 bulan terakhir
    $sql = "
        SELECT DATE_FORMAT(created_at, '%b %Y') AS label, COALESCE(SUM(total), 0) AS total
        FROM transaksi
        WHERE status = 'lunas' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY created_at ASC
    ";
}

$stmt = $pdo->query($sql);
$data = $stmt->fetchAll();

$labels = array_column($data, 'label');
$totals = array_map('floatval', array_column($data, 'total'));

echo json_encode([
    'labels' => $labels,
    'datasets' => [
        [
            'label' => 'Penjualan (Rp)',
            'data' => $totals,
            'borderColor' => '#4FB3E8',
            'backgroundColor' => 'rgba(79, 179, 232, 0.2)',
            'fill' => true,
            'tension' => 0.3
        ]
    ]
]);