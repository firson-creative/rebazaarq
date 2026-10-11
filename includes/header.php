<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_guard.php';

// Ambil info sesi bazar aktif untuk pengumuman bar atas (opsional)
$sesi_aktif_nama = $_SESSION['sesi_aktif_nama'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?><?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-light">

<?php if ($sesi_aktif_nama): ?>
    <div class="announcement-bar">
        ⚡ Sesi Bazar Aktif: <strong><?= htmlspecialchars($sesi_aktif_nama) ?></strong>
    </div>
<?php endif; ?>

<div class="d-flex min-vh-100">