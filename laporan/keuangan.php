<?php
require_once '../includes/init.php';
requireRole(['BPH']);

// Filter periode: default 1 bulan terakhir kalau belum diisi sama sekali.
$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$format = $_GET['format'] ?? '';

// Saldo awal = akumulasi seluruh transaksi SEBELUM tanggal mulai periode.
$stmtAwal = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN jenis = 'Pemasukan' THEN jumlah ELSE 0 END), 0) AS masuk,
        COALESCE(SUM(CASE WHEN jenis = 'Pengeluaran' THEN jumlah ELSE 0 END), 0) AS keluar
     FROM keuangan WHERE tanggal < ?"
);
$stmtAwal->execute([$dari]);
$saldoAwalRow = $stmtAwal->fetch();
$saldo_awal = (float) $saldoAwalRow['masuk'] - (float) $saldoAwalRow['keluar'];

// Transaksi dalam periode yang dipilih.
$stmt = $pdo->prepare(
    "SELECT k.*, p.nama_proker, u.nama_lengkap AS pencatat
     FROM keuangan k
     LEFT JOIN proker p ON p.id = k.proker_id
     LEFT JOIN users u ON u.id = k.created_by
     WHERE k.tanggal BETWEEN ? AND ?
     ORDER BY k.tanggal ASC, k.id ASC"
);
$stmt->execute([$dari, $sampai]);
$transaksi = $stmt->fetchAll();

$total_masuk = 0;
$total_keluar = 0;
foreach ($transaksi as $t) {
    if ($t['jenis'] === 'Pemasukan') $total_masuk += (float) $t['jumlah'];
    else $total_keluar += (float) $t['jumlah'];
}
$saldo_akhir = $saldo_awal + $total_masuk - $total_keluar;
$periodeText = 'Periode ' . tanggalIndo($dari, true) . ' s.d. ' . tanggalIndo($sampai, true);

// ---------- Ekspor Excel ----------
if ($format === 'excel') {
    ob_start();
    ?>
    <table border="1">
        <tr><th colspan="7"><?= e(APP_NAME) ?> - Laporan Keuangan (<?= e($periodeText) ?>)</th></tr>
        <tr><td colspan="7"></td></tr>
        <tr><th>Tanggal</th><th>Keterangan</th><th>Jenis</th><th>Program Kerja</th><th>Jumlah</th><th>Dicatat Oleh</th><th>Bukti</th></tr>
        <tr><td colspan="4">Saldo Awal</td><td><?= (int) $saldo_awal ?></td><td colspan="2"></td></tr>
        <?php foreach ($transaksi as $t): ?>
        <tr>
            <td><?= e($t['tanggal']) ?></td>
            <td><?= e($t['keterangan']) ?></td>
            <td><?= e($t['jenis']) ?></td>
            <td><?= e($t['nama_proker'] ?? '-') ?></td>
            <td><?= (int) $t['jumlah'] ?></td>
            <td><?= e($t['pencatat'] ?? '-') ?></td>
            <td><?= e($t['bukti_file'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
        <tr><td colspan="4">Total Pemasukan</td><td><?= (int) $total_masuk ?></td><td colspan="2"></td></tr>
        <tr><td colspan="4">Total Pengeluaran</td><td><?= (int) $total_keluar ?></td><td colspan="2"></td></tr>
        <tr><td colspan="4"><b>Saldo Akhir</b></td><td><b><?= (int) $saldo_akhir ?></b></td><td colspan="2"></td></tr>
    </table>
    <?php
    $html = ob_get_clean();
    unduhExcel('Laporan_Keuangan', $html);
}

// ---------- Tampilan halaman & cetak PDF ----------
$pageTitle = 'Laporan Keuangan';
require_once '../includes/header.php';

if ($format === 'pdf'):
    // Layout khusus cetak: kop, tabel, tanda tangan Bendahara. Sidebar/topbar disembunyikan lewat CSS @media print.
    ?>
    <button type="button" class="neo-btn neo-btn-primary laporan-print-btn" onclick="window.print()"><i class="fas fa-print"></i> Cetak / Simpan sebagai PDF</button>

    <?php cetakKopLaporan('Laporan Keuangan', $periodeText); ?>

    <table class="laporan-table">
        <thead>
            <tr><th>Tanggal</th><th>Keterangan</th><th>Jenis</th><th>Program Kerja</th><th class="num">Jumlah</th></tr>
        </thead>
        <tbody>
            <tr><td colspan="4">Saldo Awal</td><td class="num">Rp <?= number_format($saldo_awal, 0, ',', '.') ?></td></tr>
            <?php foreach ($transaksi as $t): ?>
            <tr>
                <td><?= tanggalIndo($t['tanggal']) ?></td>
                <td><?= e($t['keterangan']) ?></td>
                <td><?= e($t['jenis']) ?></td>
                <td><?= e($t['nama_proker'] ?? '-') ?></td>
                <td class="num"><?= $t['jenis'] === 'Pengeluaran' ? '- ' : '' ?>Rp <?= number_format($t['jumlah'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($transaksi)): ?>
                <tr><td colspan="5" style="text-align:center; color:#777;">Tidak ada transaksi pada periode ini.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="4">Total Pemasukan</td><td class="num">Rp <?= number_format($total_masuk, 0, ',', '.') ?></td></tr>
            <tr><td colspan="4">Total Pengeluaran</td><td class="num">Rp <?= number_format($total_keluar, 0, ',', '.') ?></td></tr>
            <tr><td colspan="4"><b>Saldo Akhir</b></td><td class="num"><b>Rp <?= number_format($saldo_akhir, 0, ',', '.') ?></b></td></tr>
        </tfoot>
    </table>

    <div class="laporan-ttd">
        <div class="laporan-ttd-box">
            <p>Mengetahui,</p>
            <div class="laporan-ttd-space"></div>
            <p class="laporan-ttd-nama"><?= e(baganBphNames()['Bendahara']) ?><br><small>Bendahara</small></p>
        </div>
    </div>
    <?php
endif;
?>

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
        <button type="submit" class="neo-btn neo-btn-outline">Terapkan</button>
    </form>
    <div class="laporan-actions">
        <a href="?dari=<?= e($dari) ?>&amp;sampai=<?= e($sampai) ?>&amp;format=excel" class="neo-btn neo-btn-success no-link"><i class="fas fa-file-excel"></i> Unduh Excel</a>
        <a href="?dari=<?= e($dari) ?>&amp;sampai=<?= e($sampai) ?>&amp;format=pdf" target="_blank" class="neo-btn neo-btn-danger no-link"><i class="fas fa-file-pdf"></i> Cetak PDF</a>
    </div>
</div>

<div class="neo-card">
    <div class="laporan-rekap">
        <div><span>Saldo Awal</span>Rp <?= number_format($saldo_awal, 0, ',', '.') ?></div>
        <div><span>Pemasukan</span>Rp <?= number_format($total_masuk, 0, ',', '.') ?></div>
        <div><span>Pengeluaran</span>Rp <?= number_format($total_keluar, 0, ',', '.') ?></div>
        <div><span>Saldo Akhir</span>Rp <?= number_format($saldo_akhir, 0, ',', '.') ?></div>
    </div>
    <table class="laporan-table">
        <thead><tr><th>Tanggal</th><th>Keterangan</th><th>Jenis</th><th>Program Kerja</th><th class="num">Jumlah</th></tr></thead>
        <tbody>
            <?php foreach ($transaksi as $t): ?>
            <tr>
                <td><?= tanggalIndo($t['tanggal']) ?></td>
                <td><?= e($t['keterangan']) ?></td>
                <td><span class="neo-badge status-<?= $t['jenis'] == 'Pemasukan' ? 'income' : 'expense' ?>"><?= e($t['jenis']) ?></span></td>
                <td><?= e($t['nama_proker'] ?? '-') ?></td>
                <td class="num"><?= $t['jenis'] === 'Pengeluaran' ? '- ' : '' ?>Rp <?= number_format($t['jumlah'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($transaksi)): ?>
                <tr><td colspan="5" style="text-align:center; color:#777;">Tidak ada transaksi pada periode ini.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
