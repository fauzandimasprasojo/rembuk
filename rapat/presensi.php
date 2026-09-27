<?php
require_once '../includes/init.php';
requireLogin();

$pertemuan_id = (int) ($_GET['id'] ?? 0);
if (!$pertemuan_id) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare(
    "SELECT rp.*, rr.judul AS judul_seri, rr.created_by
     FROM rapat_pertemuan rp JOIN rapat_rutin rr ON rr.id = rp.rapat_rutin_id
     WHERE rp.id = ?"
);
$stmt->execute([$pertemuan_id]);
$pertemuan = $stmt->fetch();
if (!$pertemuan) { header("Location: index.php"); exit; }

$isBPH = $_SESSION['role'] === 'BPH';
$isKoor = $_SESSION['role'] === 'Koordinator Divisi';
// Rapat rutin berlaku untuk seluruh organisasi, jadi BPH & SEMUA Koordinator boleh mengoreksi presensi siapapun.
$bolehKoreksi = $isBPH || $isKoor;
$hariIni = date('Y-m-d');
$adalahHariH = $pertemuan['tanggal'] === $hariIni;

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'checkin') {
        if (!$adalahHariH) {
            $error_msg = "Presensi mandiri hanya bisa dilakukan pada hari pelaksanaan rapat.";
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO presensi (rapat_pertemuan_id, user_id, status, waktu_presensi, keterangan, dicatat_oleh)
                 VALUES (?, ?, 'Hadir', NOW(), NULL, NULL)
                 ON DUPLICATE KEY UPDATE status = 'Hadir', waktu_presensi = NOW(), keterangan = NULL, dicatat_oleh = NULL"
            );
            $stmt->execute([$pertemuan_id, $_SESSION['user_id']]);
            $success_msg = "Presensi berhasil dicatat. Terima kasih!";
        }
    }

    if (($_POST['action'] ?? '') === 'koreksi' && $bolehKoreksi) {
        $target_user = (int) ($_POST['user_id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['Hadir', 'Izin', 'Alpa'], true) ? $_POST['status'] : 'Alpa';
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($target_user) {
            // Kalau dikoreksi jadi Hadir, catat waktu sekarang sebagai jam presensi manual.
            $waktu = $status === 'Hadir' ? date('Y-m-d H:i:s') : null;
            $stmt = $pdo->prepare(
                "INSERT INTO presensi (rapat_pertemuan_id, user_id, status, waktu_presensi, keterangan, dicatat_oleh)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), waktu_presensi = VALUES(waktu_presensi), keterangan = VALUES(keterangan), dicatat_oleh = VALUES(dicatat_oleh)"
            );
            $stmt->execute([$pertemuan_id, $target_user, $status, $waktu, $keterangan ?: null, $_SESSION['user_id']]);
            $success_msg = "Presensi anggota berhasil diperbarui.";
        }
    }
}

$pageTitle = $pertemuan['judul'];
require_once '../includes/header.php';

$semuaUser = $pdo->query("SELECT id, nama_lengkap FROM users WHERE status = 'aktif' ORDER BY nama_lengkap ASC")->fetchAll();

$stmtPresensi = $pdo->prepare("SELECT * FROM presensi WHERE rapat_pertemuan_id = ?");
$stmtPresensi->execute([$pertemuan_id]);
$presensiByUser = array_column($stmtPresensi->fetchAll(), null, 'user_id');

$rekap = ['Hadir' => 0, 'Izin' => 0, 'Alpa' => 0, 'Belum' => 0];
foreach ($semuaUser as $u) {
    $st = $presensiByUser[$u['id']]['status'] ?? 'Belum';
    $rekap[$st]++;
}

$presensiSaya = $presensiByUser[$_SESSION['user_id']] ?? null;
$sudahHadirHariIni = $presensiSaya && $presensiSaya['status'] === 'Hadir';
?>

<div class="detail-back">
    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Rapat Rutin</a>
</div>

<?php if ($success_msg): ?><div class="neo-alert neo-alert-success"><?= e($success_msg) ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="neo-alert neo-alert-error"><?= e($error_msg) ?></div><?php endif; ?>

<div class="neo-card presensi-hero">
    <div class="flex-between">
        <div>
            <h2 class="page-title" style="margin:0;"><?= e($pertemuan['judul']) ?></h2>
            <p class="presensi-hero-meta"><?= tanggalIndo($pertemuan['tanggal'], true, true) ?> &middot; <?= substr($pertemuan['jam_mulai'], 0, 5) ?><?= $pertemuan['jam_selesai'] ? ' - ' . substr($pertemuan['jam_selesai'], 0, 5) : '' ?> WIB</p>
        </div>
        <a href="<?= e($pertemuan['zoom_link']) ?>" target="_blank" rel="noopener" class="neo-btn neo-btn-primary"><i class="fas fa-video"></i> Buka Zoom</a>
    </div>

    <div class="presensi-hero-actions">
        <?php if ($sudahHadirHariIni): ?>
            <span class="presensi-checked-note"><i class="fas fa-circle-check"></i> Kamu sudah presensi hari ini, jam <?= date('H:i', strtotime($presensiSaya['waktu_presensi'])) ?>.</span>
        <?php elseif ($adalahHariH): ?>
            <form method="POST" class="inline-form">
            <?= csrf_field() ?>
                <input type="hidden" name="action" value="checkin">
                <button type="submit" class="neo-btn neo-btn-success"><i class="fas fa-hand"></i> Saya Hadir</button>
            </form>
        <?php else: ?>
            <span class="text-subtle text-sm">Presensi mandiri hanya dibuka pada hari pelaksanaan rapat (<?= tanggalIndo($pertemuan['tanggal']) ?>).</span>
        <?php endif; ?>
    </div>
</div>

<div class="grid-auto presensi-summary">
    <div class="neo-card stat-card stat-card-green">
        <span class="stat-label">Hadir</span>
        <h2 class="stat-value"><?= $rekap['Hadir'] ?></h2>
    </div>
    <div class="neo-card stat-card">
        <span class="stat-label">Izin</span>
        <h2 class="stat-value"><?= $rekap['Izin'] ?></h2>
    </div>
    <div class="neo-card stat-card stat-card-red">
        <span class="stat-label">Alpa</span>
        <h2 class="stat-value"><?= $rekap['Alpa'] ?></h2>
    </div>
    <div class="neo-card stat-card stat-card-white">
        <span class="stat-label">Belum Presensi</span>
        <h2 class="stat-value"><?= $rekap['Belum'] ?></h2>
    </div>
</div>

<div class="neo-card table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Status</th>
                <th>Waktu</th>
                <th>Keterangan</th>
                <?php if ($bolehKoreksi): ?><th class="table-action">Aksi</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($semuaUser as $u): ?>
                <?php
                    $p = $presensiByUser[$u['id']] ?? null;
                    $status = $p['status'] ?? 'Belum';
                    $statusClass = strtolower($status);
                ?>
                <tr>
                    <td><?= e($u['nama_lengkap']) ?><?= $u['id'] == $_SESSION['user_id'] ? ' <span class="text-subtle text-xs">(kamu)</span>' : '' ?></td>
                    <td><span class="neo-badge status-<?= $statusClass ?>"><?= $status === 'Belum' ? 'Belum Presensi' : $status ?></span></td>
                    <td><?= $p && $p['waktu_presensi'] ? date('d/m H:i', strtotime($p['waktu_presensi'])) : '-' ?></td>
                    <td><?= $p && $p['keterangan'] ? e($p['keterangan']) : '<span class="text-subtle">-</span>' ?></td>
                    <?php if ($bolehKoreksi): ?>
                    <td class="table-action">
                        <button type="button" class="neo-btn neo-btn-warning neo-btn-sm"
                            data-modal="koreksi-presensi"
                            data-user-id="<?= $u['id'] ?>"
                            data-nama="<?= e($u['nama_lengkap']) ?>"
                            data-status="<?= $status === 'Belum' ? 'Hadir' : e($status) ?>"
                            data-keterangan="<?= e($p['keterangan'] ?? '') ?>">
                            <i class="fas fa-pen"></i>
                        </button>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($bolehKoreksi): ?>
<div id="modalKoreksiPresensi" class="neo-modal-overlay">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title">Koreksi Presensi &mdash; <span id="koreksi_nama_target"></span></h3>
            <button type="button" class="modal-close" data-modal-close="modalKoreksiPresensi" aria-label="Tutup modal">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="koreksi">
            <input type="hidden" name="user_id" id="koreksi_user_id">
            <div class="form-group">
                <label class="neo-label">Status Kehadiran</label>
                <select name="status" id="koreksi_status" class="neo-input" required>
                    <option value="Hadir">Hadir</option>
                    <option value="Izin">Izin</option>
                    <option value="Alpa">Alpa</option>
                </select>
            </div>
            <div class="form-group form-group-lg">
                <label class="neo-label">Keterangan (opsional)</label>
                <input type="text" name="keterangan" id="koreksi_keterangan" class="neo-input" placeholder="Contoh: Izin sakit, ada kelas pengganti, dll.">
            </div>
            <div class="form-actions">
                <button type="button" class="neo-btn neo-btn-muted" data-modal-close="modalKoreksiPresensi">Batal</button>
                <button type="submit" class="neo-btn neo-btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
