<?php
// Template layout terproteksi: file pemanggil WAJIB sudah require init.php + requireLogin().
// Mencetak <head>, sidebar navigasi (menu Kelola hanya untuk BPH), dan topbar pengguna.
$user_nama = $_SESSION['nama_lengkap'] ?? 'Pengguna';
$user_role = $_SESSION['role'] ?? 'Anggota';
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' . APP_NAME : APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/logo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/neobrutalism.css">
</head>
<body>
    <div class="layout-wrapper">
        <button type="button" class="sidebar-overlay" id="sidebarOverlay" data-sidebar-toggle aria-label="Tutup menu navigasi"></button>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-mark">
                    <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="Logo <?= e(APP_NAME) ?>">
                    <span class="logo-mark-text"><?= APP_NAME ?></span>
                </div>
                <div class="sidebar-tagline"><?= e(APP_TAGLINE) ?></div>
            </div>
            <ul class="nav-links">
                <li><a href="<?= BASE_URL ?>/dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-house"></i> Dashboard</a></li>
                <li><a href="<?= BASE_URL ?>/proker/index.php" class="<?= $current_dir == 'proker' ? 'active' : '' ?>"><i class="fas fa-list-check"></i> Program Kerja</a></li>
                <li><a href="<?= BASE_URL ?>/kalender/index.php" class="<?= $current_dir == 'kalender' ? 'active' : '' ?>"><i class="fas fa-calendar-days"></i> Kalender</a></li>
                <li><a href="<?= BASE_URL ?>/rapat/index.php" class="<?= $current_dir == 'rapat' ? 'active' : '' ?>"><i class="fas fa-video"></i> Rapat &amp; Presensi</a></li>
                <li><a href="<?= BASE_URL ?>/keuangan/index.php" class="<?= $current_dir == 'keuangan' ? 'active' : '' ?>"><i class="fas fa-wallet"></i> Keuangan</a></li>
                <li><a href="<?= BASE_URL ?>/dokumentasi/index.php" class="<?= $current_dir == 'dokumentasi' ? 'active' : '' ?>"><i class="fas fa-camera-retro"></i> Dokumentasi</a></li>
                <li><a href="<?= BASE_URL ?>/surat/index.php" class="<?= $current_dir == 'surat' ? 'active' : '' ?>"><i class="fas fa-envelope"></i> Persuratan</a></li>
                <?php if ($user_role === 'BPH'): ?>
                <li><a href="<?= BASE_URL ?>/users/index.php" class="<?= $current_dir == 'users' ? 'active' : '' ?>"><i class="fas fa-user-gear"></i> Kelola User</a></li>
                <li><a href="<?= BASE_URL ?>/divisi/index.php" class="<?= $current_dir == 'divisi' ? 'active' : '' ?>"><i class="fas fa-sitemap"></i> Kelola Divisi</a></li>
                <li><a href="<?= BASE_URL ?>/laporan/index.php" class="<?= $current_dir == 'laporan' ? 'active' : '' ?>"><i class="fas fa-file-export"></i> Laporan</a></li>
                <?php endif; ?>
                <li><a href="<?= BASE_URL ?>/profil/index.php" class="<?= $current_dir == 'profil' ? 'active' : '' ?>"><i class="fas fa-user-circle"></i> Profil Saya</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button type="button" class="hamburger-btn" data-sidebar-toggle aria-label="Buka menu navigasi"><i class="fas fa-bars" aria-hidden="true"></i></button>
                    <h1 class="topbar-title"><?= isset($pageTitle) ? e($pageTitle) : 'Dashboard' ?></h1>
                </div>
                <div class="user-info">
                    <?php
                    // Badge notifikasi topbar: dihitung per request dari surat/rapat/proker/agenda.
                    // Dibungkus try/catch supaya topbar tidak pernah rusak kalau query gagal.
                    $notif_total = 0; $notif_items = [];
                    try { $notif = getNotifications($pdo); $notif_total = $notif['total']; $notif_items = $notif['items']; }
                    catch (Throwable $e) { $notif_total = 0; $notif_items = []; }
                    ?>
                    <div class="notif-wrap">
                        <button type="button" class="notif-bell" id="notifBell" aria-label="Notifikasi (<?= (int) $notif_total ?> perlu perhatian)" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($notif_total > 0): ?>
                                <span class="notif-badge"><?= $notif_total > 9 ? '9+' : (int) $notif_total ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="notif-dropdown" id="notifDropdown" role="menu" aria-label="Daftar pengingat">
                            <div class="notif-dropdown-title"><i class="fas fa-bell"></i> Butuh Perhatian (<?= (int) $notif_total ?>)</div>
                            <?php if (empty($notif_items)): ?>
                                <div class="notif-empty"><i class="fas fa-circle-check"></i> Semua beres, tidak ada pengingat.</div>
                            <?php else: ?>
                                <?php foreach ($notif_items as $n): ?>
                                    <a href="<?= e($n['url']) ?>" class="notif-item notif-tone-<?= e($n['tone']) ?>">
                                        <span class="notif-icon"><i class="fas <?= e($n['icon']) ?>"></i></span>
                                        <span class="notif-text"><strong><?= e($n['title']) ?></strong><small><?= e($n['desc']) ?></small></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/profil/index.php" class="user-greeting" style="text-decoration:none; color:inherit;">
                        <?= renderAvatar($_SESSION['avatar'] ?? null, 'sm') ?>
                        Halo, <?= e($user_nama) ?>! <span class="role-badge"><?= e($user_role) ?></span>
                    </a>
                    <a href="<?= BASE_URL ?>/auth/logout.php" class="neo-btn neo-btn-danger neo-btn-sm">Logout</a>
                </div>
            </header>

            <div class="content-area">
