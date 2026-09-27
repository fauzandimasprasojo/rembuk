<?php
require_once '../includes/init.php';
requireRole(['BPH']); // Laporan resmi untuk LPJ ke pihak luar: khusus BPH.

$pageTitle = 'Laporan';
require_once '../includes/header.php';
?>
<div class="page-header">
    <h2 class="page-title">Laporan</h2>
</div>

<div class="grid-auto">
    <article class="neo-card">
        <h3 class="division-card__title"><i class="fas fa-wallet"></i> Laporan Keuangan</h3>
        <p class="division-card__description">Rekap pemasukan &amp; pengeluaran kas, lengkap dengan saldo awal dan saldo akhir periode.</p>
        <a href="keuangan.php" class="neo-btn neo-btn-primary neo-btn-sm no-link">Buka Laporan</a>
    </article>

    <article class="neo-card">
        <h3 class="division-card__title"><i class="fas fa-list-check"></i> Laporan Program Kerja</h3>
        <p class="division-card__description">Rekap proker per divisi beserta status, progres, dan jumlah jobdesk yang selesai.</p>
        <a href="proker.php" class="neo-btn neo-btn-primary neo-btn-sm no-link">Buka Laporan</a>
    </article>

    <article class="neo-card">
        <h3 class="division-card__title"><i class="fas fa-clipboard-check"></i> Laporan Presensi</h3>
        <p class="division-card__description">Rekap kehadiran anggota pada rapat rutin dalam suatu periode, per orang.</p>
        <a href="presensi.php" class="neo-btn neo-btn-primary neo-btn-sm no-link">Buka Laporan</a>
    </article>
</div>

<p class="laporan-meta" style="text-align:left; margin-top:24px;">
    <i class="fas fa-circle-info"></i> Setiap laporan bisa diunduh sebagai <strong>Excel</strong> (data mentah untuk diolah lebih lanjut)
    atau dicetak sebagai <strong>PDF</strong> (lewat dialog cetak browser, pilih tujuan "Simpan sebagai PDF").
</p>

<?php require_once '../includes/footer.php'; ?>
