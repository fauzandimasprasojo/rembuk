<?php
require_once '../includes/init.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT s.*, u.nama_lengkap AS pemohon, d.nama_divisi, r.nama_lengkap AS peninjau
     FROM surat s LEFT JOIN users u ON u.id = s.user_id
     LEFT JOIN divisi d ON d.id = s.divisi_id
     LEFT JOIN users r ON r.id = s.reviewed_by
     WHERE s.id = ?"
);
$stmt->execute([$id]);
$surat = $stmt->fetch();
if (!$surat) { header('Location: index.php'); exit; }

$isPemohon = $surat['user_id'] == $_SESSION['user_id'];
$isKoorDivisi = $_SESSION['role'] === 'Koordinator Divisi' && $_SESSION['divisi_id'] == $surat['divisi_id'];
$isBPH = $_SESSION['role'] === 'BPH';
if (!$isPemohon && !$isKoorDivisi && !$isBPH) { header('Location: index.php'); exit; }

// Hak per status alur surat: review (Koor/BPH saat Pending), revisi (pemohon saat Revisi),
// upload final (BPH saat Diterima & belum ada file), hapus (pemohon/BPH selama belum bernomor).
$bisaReview = ($isKoorDivisi || $isBPH) && $surat['status'] === 'Pending';
$bisaEditRevisi = $isPemohon && $surat['status'] === 'Revisi';
$bisaUploadFinal = $isBPH && $surat['status'] === 'Diterima' && !$surat['file_final'];
// Surat yang sudah diterima (sudah bernomor) tidak boleh dihapus, supaya penomoran otomatis tidak bentrok
$bisaHapus = ($isPemohon || $isBPH) && $surat['status'] !== 'Diterima' && empty($surat['nomor_surat']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus_surat' && $bisaHapus) {
        $pdo->prepare("DELETE FROM surat WHERE id = ?")->execute([$id]);   // surat_log ikut terhapus (CASCADE)
        foreach ([$surat['file_surat'], $surat['file_final']] as $f) {
            $path = '../uploads/surat/' . basename((string) $f);
            if ($f && is_file($path)) unlink($path);
        }
        flash('success', 'Pengajuan surat berhasil dihapus.');
        header('Location: index.php');
        exit;
    }

    if ($aksi === 'review' && $bisaReview) {
        $statusBaru = $_POST['status_baru'];
        $catatan = trim($_POST['catatan']);

        // Nomor surat resmi diterbitkan otomatis tepat saat status menjadi Diterima.
        $nomorSurat = $surat['nomor_surat'];
        if ($statusBaru === 'Diterima' && !$nomorSurat) {
            $nomorSurat = generateNomorSurat($pdo);
        }

        $upd = $pdo->prepare("UPDATE surat SET status = ?, catatan = ?, reviewed_by = ?, nomor_surat = ? WHERE id = ?");
        $upd->execute([$statusBaru, $catatan, $_SESSION['user_id'], $nomorSurat, $id]);

        $log = $pdo->prepare("INSERT INTO surat_log (surat_id, status, catatan, oleh_user_id) VALUES (?, ?, ?, ?)");
        $log->execute([$id, $statusBaru, $catatan, $_SESSION['user_id']]);

        $flashReview = ['Diterima' => 'Surat diterima, nomor resmi berhasil diterbitkan.', 'Revisi' => 'Surat dikembalikan untuk direvisi.', 'Ditolak' => 'Surat ditolak.'];
        flash('success', $flashReview[$statusBaru] ?? 'Peninjauan surat berhasil disimpan.');
        header('Location: detail.php?id=' . $id);
        exit;
    }

    if ($aksi === 'update_draft' && $bisaEditRevisi) {
        $tentangBaru = trim($_POST['tentang']);
        $tujuanBaru = trim($_POST['tujuan']);
        $fileSurat = $surat['file_surat'];

        if (!empty($_FILES['file_surat']['name'])) {
            $ext = strtolower(pathinfo($_FILES['file_surat']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'doc', 'docx'])) {
                $fileSurat = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['file_surat']['name']);
                move_uploaded_file($_FILES['file_surat']['tmp_name'], '../uploads/surat/' . $fileSurat);
            }
        }

        $upd = $pdo->prepare("UPDATE surat SET tentang = ?, tujuan = ?, file_surat = ?, status = 'Pending' WHERE id = ?");
        $upd->execute([$tentangBaru, $tujuanBaru, $fileSurat, $id]);

        $log = $pdo->prepare("INSERT INTO surat_log (surat_id, status, catatan, oleh_user_id) VALUES (?, 'Pending', 'Draf diperbarui oleh pemohon, menunggu peninjauan ulang.', ?)");
        $log->execute([$id, $_SESSION['user_id']]);

        flash('success', 'Revisi berhasil dikirim ulang, menunggu peninjauan.');
        header('Location: detail.php?id=' . $id);
        exit;
    }

    if ($aksi === 'upload_final' && $bisaUploadFinal) {
        if (!empty($_FILES['file_final']['name'])) {
            $ext = strtolower(pathinfo($_FILES['file_final']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                $fileFinal = time() . '_final_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['file_final']['name']);
                move_uploaded_file($_FILES['file_final']['tmp_name'], '../uploads/surat/' . $fileFinal);

                $upd = $pdo->prepare("UPDATE surat SET file_final = ? WHERE id = ?");
                $upd->execute([$fileFinal, $id]);

                $log = $pdo->prepare("INSERT INTO surat_log (surat_id, status, catatan, oleh_user_id) VALUES (?, 'Diterima', 'Dokumen final diunggah.', ?)");
                $log->execute([$id, $_SESSION['user_id']]);
                flash('success', 'Dokumen final berhasil diunggah.');
            } else {
                flash('error', 'Format dokumen final harus PDF/JPG/PNG.');
            }
        } else {
            flash('error', 'Pilih file dokumen final terlebih dahulu.');
        }
        header('Location: detail.php?id=' . $id);
        exit;
    }
}

$logs = $pdo->prepare("SELECT sl.*, u.nama_lengkap FROM surat_log sl LEFT JOIN users u ON u.id = sl.oleh_user_id WHERE sl.surat_id = ? ORDER BY sl.created_at ASC");
$logs->execute([$id]);
$logs = $logs->fetchAll();

$statusClass = ['Pending' => 'status-pending', 'Revisi' => 'status-revisi', 'Diterima' => 'status-diterima', 'Ditolak' => 'status-ditolak'];

$pageTitle = $surat['tentang'];
require_once '../includes/header.php';
?>
<div class="narrow-700">
    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali</a>

    <div class="neo-card detail-card detail-card--first">
        <div class="flex-between">
            <div>
                <h2 class="page-title"><?= e($surat['tentang']) ?></h2>
                <p class="detail-meta">
                    Diajukan oleh <?= e($surat['pemohon'] ?? 'Pengguna telah dihapus') ?><?php if ($surat['nama_divisi']): ?> &middot; <?= e($surat['nama_divisi']) ?><?php endif; ?>
                </p>
                <?php if ($surat['nomor_surat']): ?><p class="detail-number text-heavy">No. <?= e($surat['nomor_surat']) ?></p><?php endif; ?>
            </div>
            <div class="detail-header-actions">
                <span class="neo-badge <?= $statusClass[$surat['status']] ?>"><?= $surat['status'] ?></span>
                <?php if ($bisaHapus): ?>
                    <form method="POST" class="inline-form" data-confirm="Hapus surat ini beserta filenya? Tindakan ini tidak bisa dibatalkan.">
            <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="hapus_surat">
                        <button type="submit" class="neo-btn neo-btn-danger neo-btn-sm"><i class="fas fa-trash"></i> Hapus</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <p class="detail-purpose">Tujuan: <?= e($surat['tujuan']) ?></p>

        <?php if ($surat['file_surat']): ?>
            <p class="detail-document-row"><a href="../uploads/surat/<?= e($surat['file_surat']) ?>" target="_blank" rel="noopener" class="no-link"><i class="fas fa-file-pdf"></i> Lihat Draf Surat</a></p>
        <?php endif; ?>

        <?php if ($surat['catatan']): ?>
            <div class="neo-alert evaluation-note">
                <strong>Catatan Evaluasi<?= $surat['peninjau'] ? ' (' . e($surat['peninjau']) . ')' : '' ?>:</strong><br><?= nl2br(e($surat['catatan'])) ?>
            </div>
        <?php endif; ?>

        <?php if ($surat['file_final']): ?>
            <div class="neo-alert neo-alert-success detail-notice">
                Dokumen final tersedia: <a href="../uploads/surat/<?= e($surat['file_final']) ?>" target="_blank" rel="noopener" class="final-document-link">Unduh di sini</a>
            </div>
        <?php endif; ?>
    </div>

    <?php displayFlash(); ?>

    <?php if ($bisaReview): ?>
        <div class="neo-card detail-card detail-card--review">
            <h3 class="section-heading">Tinjau Pengajuan</h3>
            <form method="POST">
            <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="review">
                <div class="form-group">
                    <label class="neo-label">Catatan Evaluasi</label>
                    <textarea name="catatan" class="neo-input" rows="3" placeholder="Opsional untuk Diterima, wajib untuk Revisi/Ditolak"></textarea>
                </div>
                <div class="review-actions">
                    <button type="submit" name="status_baru" value="Diterima" class="neo-btn neo-btn-success">Diterima</button>
                    <button type="submit" name="status_baru" value="Revisi" class="neo-btn neo-btn-warning">Minta Revisi</button>
                    <button type="submit" name="status_baru" value="Ditolak" class="neo-btn neo-btn-danger">Ditolak</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($bisaEditRevisi): ?>
        <div class="neo-card detail-card detail-card--revision">
            <h3 class="section-heading">Perbarui Pengajuan (Status: Revisi)</h3>
            <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="update_draft">
                <div class="form-group">
                    <label class="neo-label">Tentang / Perihal</label>
                    <input type="text" name="tentang" class="neo-input" required value="<?= e($surat['tentang']) ?>">
                </div>
                <div class="form-group">
                    <label class="neo-label">Tujuan</label>
                    <input type="text" name="tujuan" class="neo-input" required value="<?= e($surat['tujuan']) ?>">
                </div>
                <div class="form-group form-group-lg">
                    <label class="neo-label">Ganti Draf Surat (opsional)</label>
                    <input type="file" name="file_surat" class="neo-input input-file">
                </div>
                <button type="submit" class="neo-btn neo-btn-primary">Kirim Ulang untuk Ditinjau</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($bisaUploadFinal): ?>
        <div class="neo-card detail-card detail-card--final-upload">
            <h3 class="section-heading">Unggah Dokumen Final</h3>
            <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="upload_final">
                <div class="form-group form-group-lg">
                    <label class="neo-label">File Bertanda Tangan &amp; Stempel (PDF/JPG/PNG)</label>
                    <input type="file" name="file_final" class="neo-input input-file" required>
                </div>
                <button type="submit" class="neo-btn neo-btn-primary">Unggah</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="neo-card detail-card">
        <h3 class="section-heading">Riwayat</h3>
        <div class="history-list">
            <?php foreach ($logs as $l): ?>
                <div class="history-item">
                    <strong><?= $l['status'] ?></strong> oleh <?= e($l['nama_lengkap'] ?? 'Pengguna telah dihapus') ?>
                    <span class="history-meta">&middot; <?= tanggalIndo($l['created_at'], false, false, true) ?></span>
                    <?php if ($l['catatan']): ?><p class="history-note text-muted"><?= e($l['catatan']) ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
