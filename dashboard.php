<?php
require_once 'includes/init.php';
requireLogin(); // Dashboard hanya untuk user yang sudah login.

$isBPH = $_SESSION['role'] === 'BPH';
$divisiUser = $_SESSION['divisi_id'];

// Statistik proker: semua orang melihat data seluruh organisasi
$filterDivisi = '';

$total_proker = $pdo->query("SELECT COUNT(*) FROM proker" . $filterDivisi)->fetchColumn() ?: 0;
$proker_done = $pdo->query("SELECT COUNT(*) FROM proker" . $filterDivisi . ($filterDivisi ? " AND" : " WHERE") . " status = 'Done'")->fetchColumn() ?: 0;
$proker_progress = $pdo->query("SELECT COUNT(*) FROM proker" . $filterDivisi . ($filterDivisi ? " AND" : " WHERE") . " status = 'In Progress'")->fetchColumn() ?: 0;

// Keuangan tetap organisasi-wide (transparansi kas untuk semua)
$total_pemasukan = $pdo->query("SELECT SUM(jumlah) FROM keuangan WHERE jenis = 'Pemasukan'")->fetchColumn() ?: 0;
$total_pengeluaran = $pdo->query("SELECT SUM(jumlah) FROM keuangan WHERE jenis = 'Pengeluaran'")->fetchColumn() ?: 0;
$saldo_kas = $total_pemasukan - $total_pengeluaran;

// Agregat status untuk diagram donat (dibaca app.js via atribut data-* pada <canvas>).
$stmt_chart = $pdo->query("SELECT status, COUNT(*) as total FROM proker" . $filterDivisi . " GROUP BY status");
$chart_raw = $stmt_chart->fetchAll(PDO::FETCH_KEY_PAIR);
$status_todo = $chart_raw['To-do'] ?? 0;
$status_in_progress = $chart_raw['In Progress'] ?? 0;
$status_done = $chart_raw['Done'] ?? 0;

$sqlTerbaru = "SELECT p.*, d.nama_divisi FROM proker p JOIN divisi d ON d.id = p.divisi_id" . $filterDivisi . " ORDER BY p.id DESC LIMIT 5";
$proker_terbaru = $pdo->query($sqlTerbaru)->fetchAll();

// Rata-rata progres proker per divisi
$sqlDivisi = "SELECT d.nama_divisi, COUNT(p.id) AS total, ROUND(AVG(p.progress_persen)) AS rata
              FROM divisi d JOIN proker p ON p.divisi_id = d.id"
           . " GROUP BY d.id, d.nama_divisi ORDER BY d.nama_divisi";
$progres_divisi = $pdo->query($sqlDivisi)->fetchAll();

$pageTitle = 'Dashboard';
require_once 'includes/header.php';
?>

<div class="dashboard-hero">
    <div class="dashboard-logo">
        <svg class="dashboard-hero-logo" width="60" height="60" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="5" y="5" width="90" height="90" rx="22" fill="#FF90E8" stroke="#1a1a1a" stroke-width="7"/>
            <path d="M25 32H65V55H40L25 65V55V32Z" fill="#00E5FF" stroke="#1a1a1a" stroke-width="5"/>
            <path d="M45 45H75V65H60L50 73V65V45Z" fill="#37EB5F" stroke="#1a1a1a" stroke-width="5"/>
            <circle cx="45" cy="42" r="4" fill="#1a1a1a"/>
            <circle cx="55" cy="42" r="4" fill="#1a1a1a"/>
        </svg>
        <div>
            <h2 class="dashboard-hero-title"><?= APP_NAME ?></h2>
            <p class="dashboard-hero-greeting">Halo, <?= e($_SESSION['nama_lengkap']) ?>!</p>
            <span class="dashboard-role-badge"><i class="fas fa-shield-halved"></i> <?= e($_SESSION['role']) ?></span>
        </div>
    </div>
    <div class="dashboard-date">
        <span class="dashboard-date-badge">
            <i class="fas fa-calendar-day"></i> <?php date_default_timezone_set('Asia/Jakarta'); echo tanggalIndo(time(), true, true); ?>
        </span>
    </div>
</div>

<div class="dashboard-quick-actions">
    <?php if (in_array($_SESSION['role'], ['BPH', 'Koordinator Divisi'])): ?>
        <a href="proker/index.php" class="neo-btn dashboard-action-cyan"><i class="fas fa-plus"></i> Buat Proker</a>
    <?php endif; ?>
    <?php if ($_SESSION['role'] === 'BPH'): ?>
        <a href="keuangan/index.php" class="neo-btn dashboard-action-green"><i class="fas fa-file-invoice-dollar"></i> Catat Kas</a>
    <?php endif; ?>
    <a href="kalender/index.php" class="neo-btn dashboard-action-pink"><i class="fas fa-calendar-days"></i> Lihat Kalender</a>
    <a href="dokumentasi/index.php" class="neo-btn dashboard-action-yellow"><i class="fas fa-images"></i> Galeri Foto</a>
</div>

<div class="grid-auto dashboard-stats">
    <div class="stat-card stat-card-cyan">
        <small class="stat-label stat-label-spaced">Total Program Kerja</small>
        <h2 class="stat-value stat-value-large"><?= $total_proker ?></h2>
        <small class="stat-meta"><?= $proker_done ?> Selesai | <?= $proker_progress ?> Berjalan</small>
    </div>
    <div class="stat-card stat-card-green">
        <small class="stat-label">Total Pemasukan</small>
        <h2 class="stat-value">Rp <?= number_format($total_pemasukan, 0, ',', '.') ?></h2>
    </div>
    <div class="stat-card stat-card-red">
        <small class="stat-label">Total Pengeluaran</small>
        <h2 class="stat-value">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></h2>
    </div>
    <div class="stat-card stat-card-white">
        <small class="stat-label">Saldo Kas Saat Ini</small>
        <h2 class="stat-value<?= $saldo_kas >= 0 ? '' : ' stat-negative' ?>">Rp <?= number_format($saldo_kas, 0, ',', '.') ?></h2>
    </div>
</div>

<div class="grid-2">
    <div class="neo-card">
        <h3 class="section-title"><i class="fas fa-chart-pie"></i> Status Program Kerja</h3>
        <div class="dashboard-chart">
            <canvas id="prokerChart" data-chart="proker" data-todo="<?= (int) $status_todo ?>" data-in-progress="<?= (int) $status_in_progress ?>" data-done="<?= (int) $status_done ?>"></canvas>
            <div class="dashboard-chart-total">
                <span class="dashboard-chart-total-value"><?= $total_proker ?></span>
                <small class="dashboard-chart-total-label">Proker</small>
            </div>
        </div>

        <div class="dashboard-chart-legend">
            <?php foreach ([['To-do', $status_todo, 'todo'], ['In Progress', $status_in_progress, 'progress'], ['Done', $status_done, 'done']] as [$label, $jumlah, $status_class]): ?>
                <div class="dashboard-chart-legend-row">
                    <span class="dashboard-chart-swatch dashboard-chart-swatch-<?= $status_class ?>"></span>
                    <span class="dashboard-chart-legend-label"><?= $label ?></span>
                    <span><?= $jumlah ?></span>
                    <span class="dashboard-chart-legend-percent"><?= $total_proker ? round($jumlah / $total_proker * 100) : 0 ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($progres_divisi)): ?>
            <div class="dashboard-division-section">
                <h4 class="dashboard-division-title">Progres per Divisi</h4>
                <?php foreach ($progres_divisi as $pd): ?>
                    <div class="dashboard-division-item">
                        <div class="dashboard-division-meta">
                            <span><?= e($pd['nama_divisi']) ?> <small class="dashboard-division-count">(<?= $pd['total'] ?> proker)</small></span>
                            <span><?= (int) $pd['rata'] ?>%</span>
                        </div>
                        <div class="progress-bar-bg progress-track-division">
                            <div class="progress-bar-fill progress-fill-division" data-progress="<?= (int) $pd['rata'] ?>"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="neo-card">
        <h3 class="section-title"><i class="fas fa-list-check"></i> Proker Terbaru</h3>
        <?php if (empty($proker_terbaru)): ?>
            <p class="dashboard-latest-empty">Belum ada program kerja.</p>
        <?php else: ?>
            <ul class="dashboard-latest-list">
                <?php foreach ($proker_terbaru as $p): ?>
                    <?php $latest_status_class = $p['status'] == 'Done' ? 'done' : ($p['status'] == 'In Progress' ? 'progress' : 'todo'); ?>
                    <li class="dashboard-latest-item">
                        <div>
                            <a href="proker/detail.php?id=<?= $p['id'] ?>" class="dashboard-latest-link"><?= e($p['nama_proker']) ?></a><br>
                            <small class="dashboard-latest-meta">Divisi: <?= e($p['nama_divisi']) ?></small>
                        </div>
                        <span class="neo-badge status-<?= $latest_status_class ?>"><?= $p['status'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="neo-card dashboard-org-card">
    <div class="flex-between dashboard-org-header">
        <h3 class="page-title"><i class="fas fa-sitemap"></i> Struktur Organisasi</h3>
        <?php if ($isBPH): ?><a href="divisi/index.php" class="neo-btn neo-btn-sm neo-btn-outline">Kelola Divisi</a><?php endif; ?>
    </div>
    <?php include 'includes/bagan.php'; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
