<?php
require_once '../includes/init.php';
requireRole(['BPH']);

$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$rapat_rutin_id = (int) ($_GET['rapat_rutin_id'] ?? 0);
$format = $_GET['format'] ?? '';

$semuaSeri = $pdo->query("SELECT id, judul FROM rapat_rutin ORDER BY judul")->fetchAll();

// Hanya pertemuan yang SUDAH TERLAKSANA (tanggal <= hari ini) yang masuk rekap,
// supaya pertemuan mendatang tidak ikut dihitung sebagai "Alpa".
$where = ['rp.tanggal BETWEEN ? AND ?', 'rp.tanggal <= CURDATE()'];
$params = [$dari, $sampai];
if ($rapat_rutin_id) { $where[] = 'rp.rapat_rutin_id = ?'; $params[] = $rapat_rutin_id; }
$sqlWhere = 'WHERE ' . implode(' AND ', $where);

$stmtPertemuan = $pdo->prepare("SELECT rp.id, rp.judul, rp.tanggal FROM rapat_pertemuan rp $sqlWhere ORDER BY rp.tanggal");
$stmtPertemuan->execute($params);
$pertemuanList = $stmtPertemuan->fetchAll();
$totalPertemuan = count($pertemuanList);
$idPertemuan = array_column($pertemuanList, 'id');

// Semua presensi tercatat untuk pertemuan-pertemuan itu, dikelompokkan per user.
$presensiPerUser = [];
if ($idPertemuan) {
    $placeholder = implode(',', array_fill(0, count($idPertemuan), '?'));
    $stmtPresensi = $pdo->prepare("SELECT rapat_pertemuan_id, user_id, status FROM presensi WHERE rapat_pertemuan_id IN ($placeholder)");
    $stmtPresensi->execute($idPertemuan);
    foreach ($stmtPresensi->fetchAll() as $row) {
        $presensiPerUser[$row['user_id']][$row['rapat_pertemuan_id']] = $row['status'];
    }
}

// Rekap per anggota aktif. Pertemuan yang sudah lewat tanpa baris presensi dihitung Alpa (tidak presensi & tidak dikoreksi BPH/Koordinator).
$semuaUser = $pdo->query("SELECT u.id, u.nama_lengkap, d.nama_divisi FROM users u LEFT JOIN divisi d ON d.id = u.divisi_id WHERE u.status = 'aktif' ORDER BY u.nama_lengkap")->fetchAll();
$rekapUser = [];
foreach ($semuaUser as $u) {
    $hadir = $izin = $alpa = 0;
    foreach ($idPertemuan as $pid) {
        $st = $presensiPerUser[$u['id']][$pid] ?? 'Alpa'; // belum ada baris presensi = dianggap Alpa
        if ($st === 'Hadir') $hadir++;
        elseif ($st === 'Izin') $izin++;
        else $alpa++;
    }
    $persen = $totalPertemuan ? round($hadir / $totalPertemuan * 100) : 0;
    $rekapUser[] = ['nama' => $u['nama_lengkap'], 'divisi' => $u['nama_divisi'] ?? '-', 'hadir' => $hadir, 'izin' => $izin, 'alpa' => $alpa, 'persen' => $persen];
}

$seriTerpilih = $rapat_rutin_id ? (array_values(array_filter($semuaSeri, fn($s) => $s['id'] == $rapat_rutin_id))[0]['judul'] ?? '') : 'Semua Rapat Rutin';
$periodeText = $seriTerpilih . ' &middot; ' . tanggalIndo($dari, true) . ' s.d. ' . tanggalIndo($sampai, true) . " ({$totalPertemuan} pertemuan terlaksana)";

// ---------- Ekspor Excel ----------
if ($format === 'excel') {
    ob_start();
    ?>
    <table border="1">
        <tr><th colspan="6"><?= e(APP_NAME) ?> - Laporan Presensi (<?= e(strip_tags($periodeText)) ?>)</th></tr>
        <tr><td colspan="6"></td></tr>
        <tr><th>Nama</th><th>Divisi</th><th>Hadir</th><th>Izin</th><th>Alpa</th><th>Persentase Kehadiran</th></tr>
        <?php foreach ($rekapUser as $r): ?>
        <tr>
            <td><?= e($r['nama']) ?></td>
            <td><?= e($r['divisi']) ?></td>
            <td><?= $r['hadir'] ?></td>
            <td><?= $r['izin'] ?></td>
            <td><?= $r['alpa'] ?></td>
            <td><?= $r['persen'] ?>%</td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php
    $html = ob_get_clean();
    unduhExcel('Laporan_Presensi', $html);
}

// ---------- Tampilan halaman & cetak PDF ----------
$pageTitle = 'Laporan Presensi';
require_once '../includes/header.php';

if ($format === 'pdf'):
    ?>
    <button type="button" class="neo-btn neo-btn-primary laporan-print-btn" onclick="window.print()"><i class="fas fa-print"></i> Cetak / Simpan sebagai PDF</button>

    <?php cetakKopLaporan('Laporan Presensi', strip_tags(str_replace('&middot;', '-', $periodeText))); ?>

    <table class="laporan-table">
        <thead>
            <tr><th>Nama</th><th>Divisi</th><th class="num">Hadir</th><th class="num">Izin</th><th class="num">Alpa</th><th class="num">Persentase</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rekapUser as $r): ?>
            <tr>
                <td><?= e($r['nama']) ?></td>
                <td><?= e($r['divisi']) ?></td>
                <td class="num"><?= $r['hadir'] ?></td>
                <td class="num"><?= $r['izin'] ?></td>
                <td class="num"><?= $r['alpa'] ?></td>
                <td class="num"><?= $r['persen'] ?>%</td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rekapUser) || $totalPertemuan === 0): ?>
                <tr><td colspan="6" style="text-align:center; color:#777;">Belum ada pertemuan terlaksana pada periode ini.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="laporan-ttd">
        <div class="laporan-ttd-box">
            <p>Mengetahui,</p>
            <div class="laporan-ttd-space"></div>
            <p class="laporan-ttd-nama"><?= e(baganBphNames()['Sekretaris']) ?><br><small>Sekretaris</small></p>
        </div>
    </div>
<?php endif; ?>

<?php if ($format !== 'pdf'): ?>
<div class="detail-back"><a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Laporan</a></div>

<div class="neo-card">
    <form method="GET" class="laporan-filter-form">
        <div class="modal-form-group">
            <label class="neo-label" for="dari">Dari Tanggal</label>
            <input type="date" id="dari" name="dari" class="neo-input" value="<?= e($dari) ?>">
        </div>
        <div class="modal-form-group">
            <label class="neo-label" for="sampai">Sampai Tanggal</label>
            <input type="date" id="sampai" name="sampai" class="neo-input" value="<?= e($sampai) ?>">
        </div>
        <div class="modal-form-group">
            <label class="neo-label" for="rapat_rutin_id">Rapat Rutin</label>
            <select id="rapat_rutin_id" name="rapat_rutin_id" class="neo-input">
                <option value="">Semua Rapat Rutin</option>
                <?php foreach ($semuaSeri as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $rapat_rutin_id == $s['id'] ? 'selected' : '' ?>><?= e($s['judul']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="neo-btn neo-btn-outline">Terapkan</button>
    </form>
    <div class="laporan-actions">
        <a href="?dari=<?= e($dari) ?>&amp;sampai=<?= e($sampai) ?>&amp;rapat_rutin_id=<?= $rapat_rutin_id ?>&amp;format=excel" class="neo-btn neo-btn-success no-link"><i class="fas fa-file-excel"></i> Unduh Excel</a>
        <a href="?dari=<?= e($dari) ?>&amp;sampai=<?= e($sampai) ?>&amp;rapat_rutin_id=<?= $rapat_rutin_id ?>&amp;format=pdf" target="_blank" class="neo-btn neo-btn-danger no-link"><i class="fas fa-file-pdf"></i> Cetak PDF</a>
    </div>
    <p class="laporan-meta" style="text-align:left;"><i class="fas fa-circle-info"></i> <?= $totalPertemuan ?> pertemuan terlaksana pada periode ini. Pertemuan yang belum lewat tidak dihitung.</p>
</div>

<div class="neo-card">
    <table class="laporan-table">
        <thead><tr><th>Nama</th><th>Divisi</th><th class="num">Hadir</th><th class="num">Izin</th><th class="num">Alpa</th><th class="num">Persentase</th></tr></thead>
        <tbody>
            <?php foreach ($rekapUser as $r): ?>
            <tr>
                <td><?= e($r['nama']) ?></td>
                <td><?= e($r['divisi']) ?></td>
                <td class="num"><?= $r['hadir'] ?></td>
                <td class="num"><?= $r['izin'] ?></td>
                <td class="num"><?= $r['alpa'] ?></td>
                <td class="num"><?= $r['persen'] ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
