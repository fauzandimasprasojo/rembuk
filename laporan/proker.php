<?php
require_once '../includes/init.php';
requireRole(['BPH']);

$divisi_id = (int) ($_GET['divisi_id'] ?? 0);
$status = in_array($_GET['status'] ?? '', ['To-do', 'In Progress', 'Done'], true) ? $_GET['status'] : '';
$format = $_GET['format'] ?? '';

$semuaDivisi = $pdo->query("SELECT id, nama_divisi FROM divisi WHERE nama_divisi <> 'BPH' ORDER BY nama_divisi")->fetchAll();

$where = [];
$params = [];
if ($divisi_id) { $where[] = 'p.divisi_id = ?'; $params[] = $divisi_id; }
if ($status) { $where[] = 'p.status = ?'; $params[] = $status; }
$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare(
    "SELECT p.*, d.nama_divisi, u.nama_lengkap AS pembuat,
        (SELECT COUNT(*) FROM tasks t WHERE t.proker_id = p.id) AS total_jobdesk,
        (SELECT COUNT(*) FROM tasks t WHERE t.proker_id = p.id AND t.status = 'Done') AS jobdesk_selesai
     FROM proker p
     JOIN divisi d ON d.id = p.divisi_id
     LEFT JOIN users u ON u.id = p.created_by
     $sqlWhere
     ORDER BY d.nama_divisi, p.tanggal_pelaksanaan"
);
$stmt->execute($params);
$daftar = $stmt->fetchAll();

$rekap = ['To-do' => 0, 'In Progress' => 0, 'Done' => 0];
foreach ($daftar as $p) { $rekap[$p['status']]++; }

$divisiTerpilih = $divisi_id ? (array_values(array_filter($semuaDivisi, fn($d) => $d['id'] == $divisi_id))[0]['nama_divisi'] ?? '') : 'Semua Divisi';
$periodeText = $divisiTerpilih . ($status ? " - Status: $status" : '');

// ---------- Ekspor Excel ----------
if ($format === 'excel') {
    ob_start();
    ?>
    <table border="1">
        <tr><th colspan="8"><?= e(APP_NAME) ?> - Laporan Program Kerja (<?= e($periodeText) ?>)</th></tr>
        <tr><td colspan="8"></td></tr>
        <tr><th>Nama Proker</th><th>Divisi</th><th>Tanggal Pelaksanaan</th><th>Tanggal Selesai</th><th>Status</th><th>Progres (%)</th><th>Jobdesk Selesai</th><th>Dibuat Oleh</th></tr>
        <?php foreach ($daftar as $p): ?>
        <tr>
            <td><?= e($p['nama_proker']) ?></td>
            <td><?= e($p['nama_divisi']) ?></td>
            <td><?= e($p['tanggal_pelaksanaan']) ?></td>
            <td><?= e($p['tanggal_selesai'] ?? '-') ?></td>
            <td><?= e($p['status']) ?></td>
            <td><?= (int) $p['progress_persen'] ?></td>
            <td><?= (int) $p['jobdesk_selesai'] ?>/<?= (int) $p['total_jobdesk'] ?></td>
            <td><?= e($p['pembuat'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php
    $html = ob_get_clean();
    unduhExcel('Laporan_Proker', $html);
}

// ---------- Tampilan halaman & cetak PDF ----------
$pageTitle = 'Laporan Program Kerja';
require_once '../includes/header.php';

if ($format === 'pdf'):
    ?>
    <button type="button" class="neo-btn neo-btn-primary laporan-print-btn" onclick="window.print()"><i class="fas fa-print"></i> Cetak / Simpan sebagai PDF</button>

    <?php cetakKopLaporan('Laporan Program Kerja', $periodeText); ?>

    <div class="laporan-rekap">
        <div><span>To-do</span><?= $rekap['To-do'] ?></div>
        <div><span>In Progress</span><?= $rekap['In Progress'] ?></div>
        <div><span>Done</span><?= $rekap['Done'] ?></div>
        <div><span>Total</span><?= count($daftar) ?></div>
    </div>

    <table class="laporan-table">
        <thead>
            <tr><th>Nama Proker</th><th>Divisi</th><th>Pelaksanaan</th><th>Status</th><th class="num">Progres</th><th>Jobdesk</th></tr>
        </thead>
        <tbody>
            <?php foreach ($daftar as $p): ?>
            <tr>
                <td><?= e($p['nama_proker']) ?></td>
                <td><?= e($p['nama_divisi']) ?></td>
                <td><?= tanggalIndo($p['tanggal_pelaksanaan']) ?><?= $p['tanggal_selesai'] ? ' &ndash; ' . tanggalIndo($p['tanggal_selesai']) : '' ?></td>
                <td><?= e($p['status']) ?></td>
                <td class="num"><?= (int) $p['progress_persen'] ?>%</td>
                <td><?= (int) $p['jobdesk_selesai'] ?>/<?= (int) $p['total_jobdesk'] ?> selesai</td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($daftar)): ?>
                <tr><td colspan="6" style="text-align:center; color:#777;">Tidak ada program kerja untuk filter ini.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="laporan-ttd">
        <div class="laporan-ttd-box">
            <p>Mengetahui,</p>
            <div class="laporan-ttd-space"></div>
            <p class="laporan-ttd-nama"><?= e(baganBphNames()['Ketua']) ?><br><small>Ketua</small></p>
        </div>
    </div>
<?php endif; ?>

<?php if ($format !== 'pdf'): ?>
<div class="detail-back"><a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Laporan</a></div>

<div class="neo-card">
    <form method="GET" class="laporan-filter-form">
        <div class="modal-form-group">
            <label class="neo-label" for="divisi_id">Divisi</label>
            <select id="divisi_id" name="divisi_id" class="neo-input">
                <option value="">Semua Divisi</option>
                <?php foreach ($semuaDivisi as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $divisi_id == $d['id'] ? 'selected' : '' ?>><?= e($d['nama_divisi']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="modal-form-group">
            <label class="neo-label" for="status">Status</label>
            <select id="status" name="status" class="neo-input">
                <option value="">Semua Status</option>
                <option value="To-do" <?= $status === 'To-do' ? 'selected' : '' ?>>To-do</option>
                <option value="In Progress" <?= $status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="Done" <?= $status === 'Done' ? 'selected' : '' ?>>Done</option>
            </select>
        </div>
        <button type="submit" class="neo-btn neo-btn-outline">Terapkan</button>
    </form>
    <div class="laporan-actions">
        <a href="?divisi_id=<?= $divisi_id ?>&amp;status=<?= e($status) ?>&amp;format=excel" class="neo-btn neo-btn-success no-link"><i class="fas fa-file-excel"></i> Unduh Excel</a>
        <a href="?divisi_id=<?= $divisi_id ?>&amp;status=<?= e($status) ?>&amp;format=pdf" target="_blank" class="neo-btn neo-btn-danger no-link"><i class="fas fa-file-pdf"></i> Cetak PDF</a>
    </div>
</div>

<div class="neo-card">
    <div class="laporan-rekap">
        <div><span>To-do</span><?= $rekap['To-do'] ?></div>
        <div><span>In Progress</span><?= $rekap['In Progress'] ?></div>
        <div><span>Done</span><?= $rekap['Done'] ?></div>
        <div><span>Total</span><?= count($daftar) ?></div>
    </div>
    <table class="laporan-table">
        <thead><tr><th>Nama Proker</th><th>Divisi</th><th>Pelaksanaan</th><th>Status</th><th class="num">Progres</th><th>Jobdesk</th></tr></thead>
        <tbody>
            <?php foreach ($daftar as $p): ?>
            <tr>
                <td><?= e($p['nama_proker']) ?></td>
                <td><?= e($p['nama_divisi']) ?></td>
                <td><?= tanggalIndo($p['tanggal_pelaksanaan']) ?></td>
                <td><span class="neo-badge status-<?= $p['status'] == 'Done' ? 'done' : ($p['status'] == 'In Progress' ? 'progress' : 'todo') ?>"><?= e($p['status']) ?></span></td>
                <td class="num"><?= (int) $p['progress_persen'] ?>%</td>
                <td><?= (int) $p['jobdesk_selesai'] ?>/<?= (int) $p['total_jobdesk'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($daftar)): ?>
                <tr><td colspan="6" style="text-align:center; color:#777;">Tidak ada program kerja untuk filter ini.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
