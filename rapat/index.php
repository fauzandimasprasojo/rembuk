<?php
require_once '../includes/init.php';
requireLogin();

// Kelola jadwal rapat rutin: BPH & Koordinator Divisi boleh membuat.
// Rapat rutin berlaku untuk SELURUH organisasi (tidak dibatasi per divisi),
// tapi hak edit/hapus seri mengikuti pola yang sama dengan Agenda: BPH bebas semua,
// Koordinator hanya boleh mengelola seri yang ia buat sendiri.
$isBPH = $_SESSION['role'] === 'BPH';
$isKoor = $_SESSION['role'] === 'Koordinator Divisi';
$can_manage_rapat = $isBPH || $isKoor;

$namaHari = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'tambah_seri') {
    if (!$can_manage_rapat) {
        $error_msg = "Anda tidak memiliki akses untuk membuat jadwal rapat rutin!";
    } else {
        $judul = trim($_POST['judul'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $hari = (int) ($_POST['hari'] ?? 0);
        $pengulangan = ($_POST['pengulangan'] ?? '') === 'Dwi-Mingguan' ? 'Dwi-Mingguan' : 'Mingguan';
        $jam_mulai = $_POST['jam_mulai'] ?? '';
        $jam_selesai = $_POST['jam_selesai'] ?: null;
        $tanggal_mulai = $_POST['tanggal_mulai'] ?? '';
        $jumlah_pertemuan = max(1, min(52, (int) ($_POST['jumlah_pertemuan'] ?? 0)));
        $zoom_link = trim($_POST['zoom_link'] ?? '');

        if (empty($judul) || $hari < 1 || $hari > 7 || empty($jam_mulai) || empty($tanggal_mulai) || empty($zoom_link)) {
            $error_msg = "Semua bidang wajib diisi (kecuali deskripsi & jam selesai)!";
        } else {
            // Snap tanggal mulai ke hari yang dipilih: kalau tanggal yang diinput belum jatuh
            // pada hari tsb, majukan otomatis ke kemunculan hari itu berikutnya.
            $ts = strtotime($tanggal_mulai);
            $hariDariTanggal = (int) date('N', $ts);
            if ($hariDariTanggal !== $hari) {
                $selisih = ($hari - $hariDariTanggal + 7) % 7;
                $ts = strtotime("+{$selisih} days", $ts);
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO rapat_rutin (judul, deskripsi, hari, pengulangan, jam_mulai, jam_selesai, zoom_link, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$judul, $deskripsi ?: null, $hari, $pengulangan, $jam_mulai, $jam_selesai, $zoom_link, $_SESSION['user_id']]);
            $rapat_rutin_id = (int) $pdo->lastInsertId();

            $langkahHari = $pengulangan === 'Dwi-Mingguan' ? 14 : 7;
            $stmtInsert = $pdo->prepare(
                "INSERT INTO rapat_pertemuan (rapat_rutin_id, judul, tanggal, jam_mulai, jam_selesai, zoom_link) VALUES (?, ?, ?, ?, ?, ?)"
            );
            for ($i = 0; $i < $jumlah_pertemuan; $i++) {
                $tanggalPertemuan = date('Y-m-d', $ts + ($i * $langkahHari * 86400));
                $stmtInsert->execute([$rapat_rutin_id, $judul, $tanggalPertemuan, $jam_mulai, $jam_selesai, $zoom_link]);
            }
            $pdo->commit();
            $success_msg = "Jadwal rapat rutin berhasil dibuat, {$jumlah_pertemuan} pertemuan sudah otomatis masuk ke kalender.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_seri') {
    $seri_id = (int) ($_POST['rapat_rutin_id'] ?? 0);
    $stmt_lama = $pdo->prepare("SELECT created_by FROM rapat_rutin WHERE id = ?");
    $stmt_lama->execute([$seri_id]);
    $seri_lama = $stmt_lama->fetch();
    $bolehEdit = $seri_lama && ($isBPH || ($isKoor && $seri_lama['created_by'] == $_SESSION['user_id']));

    if (!$seri_id || !$bolehEdit) {
        $error_msg = "Anda tidak memiliki akses untuk mengedit jadwal rapat ini!";
    } else {
        $judul = trim($_POST['judul'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $jam_mulai = $_POST['jam_mulai'] ?? '';
        $jam_selesai = $_POST['jam_selesai'] ?: null;
        $zoom_link = trim($_POST['zoom_link'] ?? '');

        if (empty($judul) || empty($jam_mulai) || empty($zoom_link)) {
            $error_msg = "Semua bidang wajib diisi (kecuali deskripsi & jam selesai)!";
        } else {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE rapat_rutin SET judul = ?, deskripsi = ?, jam_mulai = ?, jam_selesai = ?, zoom_link = ? WHERE id = ?")
                ->execute([$judul, $deskripsi ?: null, $jam_mulai, $jam_selesai, $zoom_link, $seri_id]);
            // Hanya pertemuan yang BELUM lewat yang ikut diperbarui, supaya histori presensi lama tetap apa adanya.
            $pdo->prepare("UPDATE rapat_pertemuan SET judul = ?, jam_mulai = ?, jam_selesai = ?, zoom_link = ? WHERE rapat_rutin_id = ? AND tanggal >= CURDATE()")
                ->execute([$judul, $jam_mulai, $jam_selesai, $zoom_link, $seri_id]);
            $pdo->commit();
            $success_msg = "Jadwal rapat rutin berhasil diperbarui (berlaku untuk pertemuan yang belum lewat).";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus_seri') {
    $seri_id = (int) ($_POST['rapat_rutin_id'] ?? 0);
    $stmt_lama = $pdo->prepare("SELECT created_by FROM rapat_rutin WHERE id = ?");
    $stmt_lama->execute([$seri_id]);
    $seri_lama = $stmt_lama->fetch();
    $bolehHapus = $seri_lama && ($isBPH || ($isKoor && $seri_lama['created_by'] == $_SESSION['user_id']));

    if ($seri_id && $bolehHapus) {
        $pdo->prepare("DELETE FROM rapat_rutin WHERE id = ?")->execute([$seri_id]);
        $success_msg = "Jadwal rapat rutin beserta seluruh pertemuannya berhasil dihapus.";
    } else {
        $error_msg = "Anda tidak memiliki akses untuk menghapus jadwal rapat ini!";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus_pertemuan') {
    $pertemuan_id = (int) ($_POST['pertemuan_id'] ?? 0);
    $stmt_lama = $pdo->prepare(
        "SELECT rr.created_by FROM rapat_pertemuan rp JOIN rapat_rutin rr ON rr.id = rp.rapat_rutin_id WHERE rp.id = ?"
    );
    $stmt_lama->execute([$pertemuan_id]);
    $row = $stmt_lama->fetch();
    $bolehHapus = $row && ($isBPH || ($isKoor && $row['created_by'] == $_SESSION['user_id']));

    if ($pertemuan_id && $bolehHapus) {
        $pdo->prepare("DELETE FROM rapat_pertemuan WHERE id = ?")->execute([$pertemuan_id]);
        $success_msg = "Pertemuan berhasil dihapus dari jadwal.";
    } else {
        $error_msg = "Anda tidak memiliki akses untuk menghapus pertemuan ini!";
    }
}

$pageTitle = 'Rapat Rutin & Presensi';
require_once '../includes/header.php';

$semuaSeri = $pdo->query(
    "SELECT rr.*, u.nama_lengkap AS pembuat
     FROM rapat_rutin rr LEFT JOIN users u ON u.id = rr.created_by
     ORDER BY rr.created_at DESC"
)->fetchAll();

$stmtPertemuan = $pdo->prepare(
    "SELECT id, tanggal, jam_mulai, jam_selesai FROM rapat_pertemuan WHERE rapat_rutin_id = ? ORDER BY tanggal ASC"
);
$hariIni = date('Y-m-d');
?>

<div class="page-header">
    <h2 class="page-title">Rapat Rutin &amp; Presensi</h2>
    <?php if ($can_manage_rapat): ?>
        <button type="button" class="neo-btn neo-btn-primary" data-modal-open="modalTambahSeri"><i class="fas fa-plus"></i> Buat Jadwal Rapat Rutin</button>
    <?php endif; ?>
</div>

<?php if ($success_msg): ?><div class="neo-alert neo-alert-success"><?= e($success_msg) ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="neo-alert neo-alert-error"><?= e($error_msg) ?></div><?php endif; ?>

<div class="rapat-rutin-grid">
    <?php if (empty($semuaSeri)): ?>
        <p class="rapat-empty">Belum ada jadwal rapat rutin. Anggota yang belum punya jadwal tetap bisa dipantau lewat menu ini setelah BPH/Koordinator membuatnya.</p>
    <?php else: ?>
        <?php foreach ($semuaSeri as $seri): ?>
            <?php
                $bolehKelolaIni = $can_manage_rapat && ($isBPH || $seri['created_by'] == $_SESSION['user_id']);
                $stmtPertemuan->execute([$seri['id']]);
                $pertemuanList = $stmtPertemuan->fetchAll();
            ?>
            <div class="neo-card rapat-rutin-card">
                <div class="flex-between">
                    <h3 class="proker-card-title" style="margin:0;"><?= e($seri['judul']) ?></h3>
                    <span class="neo-badge status-todo"><?= e($seri['pengulangan']) ?></span>
                </div>
                <?php if (!empty($seri['deskripsi'])): ?>
                    <p class="rapat-rutin-desc"><?= e($seri['deskripsi']) ?></p>
                <?php endif; ?>
                <div class="rapat-rutin-meta">
                    <span><i class="fas fa-calendar-day"></i> Setiap <?= $namaHari[$seri['hari']] ?></span>
                    <span><i class="fas fa-clock"></i> <?= substr($seri['jam_mulai'], 0, 5) ?><?= $seri['jam_selesai'] ? ' - ' . substr($seri['jam_selesai'], 0, 5) : '' ?></span>
                </div>
                <p class="text-subtle text-sm" style="margin-top:10px;">Dibuat oleh <?= e($seri['pembuat'] ?? 'Pengguna telah dihapus') ?></p>
                <a href="<?= e($seri['zoom_link']) ?>" target="_blank" rel="noopener" class="neo-btn neo-btn-outline neo-btn-sm" style="margin-top:10px; align-self:flex-start;"><i class="fas fa-video"></i> Link Zoom</a>

                <?php if ($bolehKelolaIni): ?>
                <div class="rapat-rutin-actions">
                    <button type="button" class="neo-btn neo-btn-warning neo-btn-sm"
                        data-modal="edit-seri-rapat"
                        data-id="<?= $seri['id'] ?>"
                        data-judul="<?= e($seri['judul']) ?>"
                        data-deskripsi="<?= e($seri['deskripsi'] ?? '') ?>"
                        data-jam-mulai="<?= e($seri['jam_mulai']) ?>"
                        data-jam-selesai="<?= e($seri['jam_selesai'] ?? '') ?>"
                        data-zoom="<?= e($seri['zoom_link']) ?>">
                        <i class="fas fa-pen"></i> Edit
                    </button>
                    <form method="POST" class="inline-form" data-confirm="Hapus seluruh jadwal rapat rutin ini beserta semua pertemuan & presensinya? Tindakan ini tidak bisa dibatalkan.">
            <?= csrf_field() ?>
                        <input type="hidden" name="action" value="hapus_seri">
                        <input type="hidden" name="rapat_rutin_id" value="<?= $seri['id'] ?>">
                        <button type="submit" class="neo-btn neo-btn-danger neo-btn-sm"><i class="fas fa-trash"></i> Hapus Seri</button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="rapat-occurrence-list">
                    <?php if (empty($pertemuanList)): ?>
                        <p class="text-subtle text-sm">Belum ada pertemuan.</p>
                    <?php else: ?>
                        <?php foreach ($pertemuanList as $pt): ?>
                            <?php
                                $statusClass = $pt['tanggal'] < $hariIni ? 'done' : ($pt['tanggal'] === $hariIni ? 'progress' : 'todo');
                                $statusLabel = $pt['tanggal'] < $hariIni ? 'Selesai' : ($pt['tanggal'] === $hariIni ? 'Hari Ini' : 'Terjadwal');
                            ?>
                            <div class="rapat-occurrence-row">
                                <div>
                                    <div class="rapat-occurrence-date"><?= tanggalIndo($pt['tanggal'], true, true) ?></div>
                                    <span class="neo-badge status-<?= $statusClass ?> text-xs"><?= $statusLabel ?></span>
                                </div>
                                <div class="rapat-occurrence-actions">
                                    <a href="presensi.php?id=<?= $pt['id'] ?>" class="neo-btn neo-btn-outline neo-btn-sm"><i class="fas fa-clipboard-check"></i> Presensi</a>
                                    <?php if ($bolehKelolaIni): ?>
                                        <form method="POST" class="inline-form" data-confirm="Hapus pertemuan tanggal ini dari jadwal?">
            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="hapus_pertemuan">
                                            <input type="hidden" name="pertemuan_id" value="<?= $pt['id'] ?>">
                                            <button type="submit" class="neo-btn neo-btn-danger neo-btn-sm"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($can_manage_rapat): ?>
<div id="modalTambahSeri" class="neo-modal-overlay">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title">Buat Jadwal Rapat Rutin</h3>
            <button type="button" class="modal-close" data-modal-close="modalTambahSeri" aria-label="Tutup modal">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="tambah_seri">
            <div class="form-group">
                <label class="neo-label">Judul Rapat</label>
                <input type="text" name="judul" class="neo-input" required placeholder="Contoh: Rapat Koordinasi Mingguan">
            </div>
            <div class="form-group">
                <label class="neo-label">Deskripsi (opsional)</label>
                <input type="text" name="deskripsi" class="neo-input" placeholder="Contoh: Evaluasi progres proker tiap divisi">
            </div>
            <div class="form-group">
                <label class="neo-label">Pengulangan</label>
                <select name="pengulangan" class="neo-input" required>
                    <option value="Mingguan">Setiap Minggu</option>
                    <option value="Dwi-Mingguan">Setiap 2 Minggu</option>
                </select>
            </div>
            <div class="form-group">
                <label class="neo-label">Hari</label>
                <select name="hari" class="neo-input" required>
                    <option value="1">Senin</option>
                    <option value="2">Selasa</option>
                    <option value="3">Rabu</option>
                    <option value="4">Kamis</option>
                    <option value="5">Jumat</option>
                    <option value="6">Sabtu</option>
                    <option value="7">Minggu</option>
                </select>
            </div>
            <div class="form-group">
                <label class="neo-label">Jam Mulai</label>
                <input type="time" name="jam_mulai" class="neo-input" required>
            </div>
            <div class="form-group">
                <label class="neo-label">Jam Selesai (opsional)</label>
                <input type="time" name="jam_selesai" class="neo-input">
            </div>
            <div class="form-group">
                <label class="neo-label">Tanggal Pertemuan Pertama</label>
                <input type="date" name="tanggal_mulai" class="neo-input" required value="<?= date('Y-m-d') ?>">
                <small class="text-subtle">Kalau tanggal ini tidak jatuh di hari yang dipilih di atas, sistem otomatis memajukan ke kemunculan hari tsb berikutnya.</small>
            </div>
            <div class="form-group">
                <label class="neo-label">Jumlah Pertemuan yang Dibuat Sekaligus</label>
                <input type="number" name="jumlah_pertemuan" class="neo-input" min="1" max="52" value="8" required>
                <small class="text-subtle">Bisa tambah lagi kapan saja dengan membuat seri baru.</small>
            </div>
            <div class="form-group form-group-lg">
                <label class="neo-label">Link Zoom (dipakai sama untuk semua pertemuan)</label>
                <input type="url" name="zoom_link" class="neo-input" required placeholder="https://zoom.us/j/...">
            </div>
            <div class="form-actions">
                <button type="button" class="neo-btn neo-btn-muted" data-modal-close="modalTambahSeri">Batal</button>
                <button type="submit" class="neo-btn neo-btn-success">Buat Jadwal</button>
            </div>
        </form>
    </div>
</div>

<div id="modalEditSeriRapat" class="neo-modal-overlay">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title">Edit Jadwal Rapat Rutin</h3>
            <button type="button" class="modal-close" data-modal-close="modalEditSeriRapat" aria-label="Tutup modal">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_seri">
            <input type="hidden" name="rapat_rutin_id" id="edit_rapat_id">
            <div class="form-group">
                <label class="neo-label">Judul Rapat</label>
                <input type="text" name="judul" id="edit_rapat_judul" class="neo-input" required>
            </div>
            <div class="form-group">
                <label class="neo-label">Deskripsi (opsional)</label>
                <input type="text" name="deskripsi" id="edit_rapat_deskripsi" class="neo-input">
            </div>
            <div class="form-group">
                <label class="neo-label">Jam Mulai</label>
                <input type="time" name="jam_mulai" id="edit_rapat_jam_mulai" class="neo-input" required>
            </div>
            <div class="form-group">
                <label class="neo-label">Jam Selesai (opsional)</label>
                <input type="time" name="jam_selesai" id="edit_rapat_jam_selesai" class="neo-input">
            </div>
            <div class="form-group form-group-lg">
                <label class="neo-label">Link Zoom</label>
                <input type="url" name="zoom_link" id="edit_rapat_zoom" class="neo-input" required>
            </div>
            <p class="text-subtle text-sm">Perubahan hanya berlaku untuk pertemuan yang belum lewat.</p>
            <div class="form-actions">
                <button type="button" class="neo-btn neo-btn-muted" data-modal-close="modalEditSeriRapat">Batal</button>
                <button type="submit" class="neo-btn neo-btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
