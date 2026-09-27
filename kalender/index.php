<?php
require_once '../includes/init.php';
requireLogin();

// Navigasi bulan/tahun via query string; otomatis melintasi batas tahun (Des↔Jan).
$bulan = (int) ($_GET['bulan'] ?? date('n'));
$tahun = (int) ($_GET['tahun'] ?? date('Y'));
if ($bulan < 1) { $bulan = 12; $tahun--; }
if ($bulan > 12) { $bulan = 1; $tahun++; }

$awalBulan = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-01";
$akhirBulan = date('Y-m-t', strtotime($awalBulan));

$isBPH = $_SESSION['role'] === 'BPH';
$divisiUser = $_SESSION['divisi_id'];

// Hapus agenda: BPH boleh menghapus semua agenda, Koordinator hanya agenda buatannya sendiri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_agenda') {
    $cek = $pdo->prepare("SELECT created_by FROM agenda WHERE id = ?");
    $cek->execute([$_POST['id'] ?? 0]);
    $agendaHapus = $cek->fetch();
    if ($agendaHapus && ($isBPH || $agendaHapus['created_by'] == $_SESSION['user_id'])) {
        $pdo->prepare("DELETE FROM agenda WHERE id = ?")->execute([$_POST['id']]);
        flash('success', 'Agenda berhasil dihapus.');
    } else {
        flash('error', 'Kamu tidak memiliki akses untuk menghapus agenda ini.');
    }
    header("Location: index.php?bulan=$bulan&tahun=$tahun&tanggal=" . urlencode($_GET['tanggal'] ?? ''));
    exit;
}

$sqlProker = "SELECT p.id, p.nama_proker AS judul, p.tanggal_pelaksanaan, p.tanggal_selesai, d.nama_divisi
              FROM proker p JOIN divisi d ON d.id = p.divisi_id
              WHERE (p.tanggal_pelaksanaan <= ? AND (p.tanggal_selesai IS NULL OR p.tanggal_selesai >= ?) OR (p.tanggal_selesai IS NULL AND p.tanggal_pelaksanaan BETWEEN ? AND ?))";
$paramsProker = [$akhirBulan, $awalBulan, $awalBulan, $akhirBulan];
$stmtProker = $pdo->prepare($sqlProker);
$stmtProker->execute($paramsProker);
$semuaProker = $stmtProker->fetchAll();

$sqlAgenda = "SELECT a.id, a.judul, a.tanggal, a.kategori, a.deskripsi, a.created_by, d.nama_divisi
              FROM agenda a LEFT JOIN divisi d ON d.id = a.divisi_id
              WHERE a.tanggal BETWEEN ? AND ?";
$paramsAgenda = [$awalBulan, $akhirBulan];
if (!$isBPH) { $sqlAgenda .= " AND (a.divisi_id IS NULL OR a.divisi_id = ?)"; $paramsAgenda[] = $divisiUser; }
$stmtAgenda = $pdo->prepare($sqlAgenda);
$stmtAgenda->execute($paramsAgenda);
$semuaAgenda = $stmtAgenda->fetchAll();

// Rapat rutin: berlaku untuk seluruh organisasi, jadi semua role melihat pertemuan yang sama.
$stmtRapat = $pdo->prepare(
    "SELECT id, judul, tanggal, jam_mulai FROM rapat_pertemuan WHERE tanggal BETWEEN ? AND ?"
);
$stmtRapat->execute([$awalBulan, $akhirBulan]);
$semuaRapat = $stmtRapat->fetchAll();

// Petakan setiap proker/agenda/rapat ke tanggal-tanggalnya agar bisa digambar di grid kalender.
$eventPerHari = [];
foreach ($semuaProker as $p) {
    $mulai = max(strtotime($p['tanggal_pelaksanaan']), strtotime($awalBulan));
    $selesai = min(strtotime($p['tanggal_selesai'] ?? $p['tanggal_pelaksanaan']), strtotime($akhirBulan));
    for ($t = $mulai; $t <= $selesai; $t += 86400) {
        $eventPerHari[date('Y-m-d', $t)][] = ['tipe' => 'proker', 'judul' => $p['judul'], 'divisi' => $p['nama_divisi'], 'id' => $p['id']];
    }
}
foreach ($semuaAgenda as $a) {
    $eventPerHari[$a['tanggal']][] = ['tipe' => 'agenda', 'id' => $a['id'], 'created_by' => $a['created_by'], 'judul' => $a['judul'], 'kategori' => $a['kategori'], 'deskripsi' => $a['deskripsi'], 'divisi' => $a['nama_divisi']];
}
foreach ($semuaRapat as $r) {
    $eventPerHari[$r['tanggal']][] = ['tipe' => 'rapat', 'id' => $r['id'], 'judul' => $r['judul'], 'jam' => substr($r['jam_mulai'], 0, 5)];
}

$namaBulan = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$hariDalamBulan = (int) date('t', strtotime($awalBulan));
$hariPertama = (int) date('N', strtotime($awalBulan));
$hariIni = date('Y-m-d');
$tanggalDipilih = $_GET['tanggal'] ?? null;

$pageTitle = 'Kalender';
require_once '../includes/header.php';
?>
<?php displayFlash(); ?>
<div class="flex-between page-header">
    <h2 class="page-title"><?= $namaBulan[$bulan] ?> <?= $tahun ?></h2>
    <div class="calendar-toolbar">
        <a href="?bulan=<?= $bulan - 1 ?>&amp;tahun=<?= $tahun ?>" class="neo-btn neo-btn-outline neo-btn-sm no-link" aria-label="Bulan sebelumnya">&larr;</a>
        <a href="?bulan=<?= date('n') ?>&amp;tahun=<?= date('Y') ?>" class="neo-btn neo-btn-outline neo-btn-sm no-link">Hari Ini</a>
        <a href="?bulan=<?= $bulan + 1 ?>&amp;tahun=<?= $tahun ?>" class="neo-btn neo-btn-outline neo-btn-sm no-link" aria-label="Bulan berikutnya">&rarr;</a>
        <?php if (in_array($_SESSION['role'], ['BPH', 'Koordinator Divisi'])): ?>
            <a href="tambah.php" class="neo-btn neo-btn-primary neo-btn-sm no-link">+ Hari Penting</a>
        <?php endif; ?>
    </div>
</div>

<div class="neo-card table-card calendar-card">
    <div class="calendar-scroll">
    <div class="calendar-weekdays">
        <?php foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $h): ?>
            <div class="calendar-weekday"><?= $h ?></div>
        <?php endforeach; ?>
    </div>
    <div class="calendar-grid">
        <?php for ($i = 1; $i < $hariPertama; $i++): ?>
            <div class="calendar-day calendar-day--empty" aria-hidden="true"></div>
        <?php endfor; ?>
        <?php for ($tgl = 1; $tgl <= $hariDalamBulan; $tgl++): ?>
            <?php
            $keyTanggal = sprintf('%04d-%02d-%02d', $tahun, $bulan, $tgl);
            $events = $eventPerHari[$keyTanggal] ?? [];
            $isToday = $keyTanggal === $hariIni;
            $isSelected = $keyTanggal === $tanggalDipilih;
            ?>
            <a href="?bulan=<?= $bulan ?>&amp;tahun=<?= $tahun ?>&amp;tanggal=<?= $keyTanggal ?>" class="calendar-day<?= $isSelected ? ' is-selected' : '' ?>">
                <span class="calendar-day-number<?= $isToday ? ' is-today' : '' ?>"><?= $tgl ?></span>
                <?php foreach (array_slice($events, 0, 2) as $ev): ?>
                    <?php
                    $isDeadline = $ev['tipe'] === 'agenda' && ($ev['kategori'] ?? '') === 'deadline';
                    $toneClass = $ev['tipe'] === 'proker' ? 'calendar-tone-proker' : ($ev['tipe'] === 'rapat' ? 'calendar-tone-rapat' : ($isDeadline ? 'calendar-tone-deadline' : 'calendar-tone-general'));
                    ?>
                    <div class="calendar-event <?= $toneClass ?><?= $isDeadline ? ' calendar-event--deadline' : '' ?>"><?= e($ev['judul']) ?></div>
                <?php endforeach; ?>
                <?php if (count($events) > 2): ?>
                    <div class="calendar-more text-subtle">+<?= count($events) - 2 ?> lagi</div>
                <?php endif; ?>
            </a>
        <?php endfor; ?>
    </div>
    </div>
</div>

<div class="calendar-legend">
    <span class="calendar-legend-item"><span class="calendar-legend-swatch calendar-tone-proker"></span> Program Kerja</span>
    <span class="calendar-legend-item"><span class="calendar-legend-swatch calendar-tone-rapat"></span> Rapat Rutin</span>
    <span class="calendar-legend-item"><span class="calendar-legend-swatch calendar-tone-general"></span> Hari Penting / Umum</span>
    <span class="calendar-legend-item"><span class="calendar-legend-swatch calendar-tone-deadline"></span> Deadline</span>
</div>

<?php if ($tanggalDipilih && isset($eventPerHari[$tanggalDipilih])): ?>
    <div class="neo-card calendar-detail-card">
        <h3 class="section-heading"><?= tanggalIndo($tanggalDipilih, true) ?></h3>
        <div class="calendar-detail-list">
            <?php foreach ($eventPerHari[$tanggalDipilih] as $ev): ?>
                <?php
                $isDeadline = $ev['tipe'] === 'agenda' && ($ev['kategori'] ?? '') === 'deadline';
                $toneClass = $ev['tipe'] === 'proker' ? 'calendar-tone-proker' : ($ev['tipe'] === 'rapat' ? 'calendar-tone-rapat' : ($isDeadline ? 'calendar-tone-deadline' : 'calendar-tone-general'));
                ?>
                <div class="calendar-detail-event <?= $toneClass ?>">
                    <?php if ($ev['tipe'] === 'proker'): ?>
                        <a href="../proker/detail.php?id=<?= $ev['id'] ?>" class="no-link text-heavy"><?= e($ev['judul']) ?></a>
                    <?php elseif ($ev['tipe'] === 'rapat'): ?>
                        <a href="../rapat/presensi.php?id=<?= $ev['id'] ?>" class="no-link text-heavy"><?= e($ev['judul']) ?></a>
                    <?php else: ?>
                        <strong class="text-heavy"><?= e($ev['judul']) ?></strong>
                    <?php endif; ?>
                    <p class="calendar-event-meta text-sm text-muted text-bold">
                        <?php if ($ev['tipe'] === 'proker'): ?>
                            Program Kerja
                        <?php elseif ($ev['tipe'] === 'rapat'): ?>
                            Rapat Rutin &middot; <?= e($ev['jam']) ?> WIB
                        <?php else: ?>
                            <?= ucfirst($ev['kategori']) ?>
                        <?php endif; ?>
                        <?php if (!empty($ev['divisi'])): ?> &middot; <?= e($ev['divisi']) ?><?php endif; ?>
                    </p>
                    <?php if (!empty($ev['deskripsi'])): ?><p class="calendar-event-description text-sm"><?= e($ev['deskripsi']) ?></p><?php endif; ?>
                    <?php if ($ev['tipe'] === 'rapat'): ?>
                        <a href="../rapat/presensi.php?id=<?= $ev['id'] ?>" class="neo-btn neo-btn-outline neo-btn-sm"><i class="fas fa-clipboard-check"></i> Presensi</a>
                    <?php endif; ?>
                    <?php if ($ev['tipe'] === 'agenda' && ($isBPH || $ev['created_by'] == $_SESSION['user_id'])): ?>
                        <form method="POST" class="calendar-event-form" data-confirm="Hapus agenda ini?">
            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_agenda">
                            <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                            <button type="submit" class="neo-btn neo-btn-danger neo-btn-sm"><i class="fas fa-trash"></i> Hapus</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
