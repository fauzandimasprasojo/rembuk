<?php
require_once '../includes/init.php';
requireLogin();

// Upload & hapus dokumentasi: hanya BPH & Koordinator; anggota hanya melihat galeri.
$can_manage_docs = in_array($_SESSION['role'], ['BPH', 'Koordinator Divisi']);
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'upload_doc' && $can_manage_docs) {
        $proker_id = $_POST['proker_id'];
        $keterangan = trim($_POST['keterangan']);

        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            // Batas 5MB; hanya format gambar yang diizinkan; nama file diacak agar unik & aman.
            if ($_FILES['gambar']['size'] > 5 * 1024 * 1024) {
                $error_msg = "Ukuran file maksimal 5MB.";
            } else {
                $fileExtension = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $uploadDir = '../assets/uploads/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $newFileName = time() . '_' . uniqid() . '.' . $fileExtension;

                    if (move_uploaded_file($_FILES['gambar']['tmp_name'], $uploadDir . $newFileName)) {
                        $stmt = $pdo->prepare("INSERT INTO dokumentasi (proker_id, gambar, keterangan, uploaded_by) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$proker_id, $newFileName, $keterangan, $_SESSION['user_id']]);
                        flash('success', 'Dokumentasi berhasil diunggah.');
                        header("Location: index.php"); exit;
                    } else {
                        $error_msg = "Gagal memindahkan file ke folder uploads.";
                    }
                } else {
                    $error_msg = "Format file tidak didukung! Gunakan JPG, PNG, WEBP, atau GIF.";
                }
            }
        } else {
            $error_msg = "Silakan pilih foto/gambar terlebih dahulu!";
        }
    }

    if (($_POST['action'] ?? '') === 'delete_doc' && $can_manage_docs) {
        $stmt_get = $pdo->prepare("SELECT gambar FROM dokumentasi WHERE id = ?");
        $stmt_get->execute([$_POST['doc_id']]);
        $doc = $stmt_get->fetch();
        if ($doc) {
            $filePath = '../assets/uploads/' . $doc['gambar'];
            if (file_exists($filePath)) unlink($filePath);
            $stmt_del = $pdo->prepare("DELETE FROM dokumentasi WHERE id = ?");
            $stmt_del->execute([$_POST['doc_id']]);
        }
        flash('success', 'Dokumentasi berhasil dihapus.');
        header("Location: index.php"); exit;
    }
}

$pageTitle = 'Dokumentasi';
require_once '../includes/header.php';

$docs = $pdo->query("SELECT d.*, p.nama_proker FROM dokumentasi d JOIN proker p ON d.proker_id = p.id ORDER BY d.id DESC")->fetchAll();
$proker_list = $pdo->query("SELECT id, nama_proker FROM proker ORDER BY nama_proker ASC")->fetchAll();
?>
<div class="flex-between page-header">
    <h2 class="page-title">Dokumentasi Kegiatan</h2>
    <?php if ($can_manage_docs): ?>
        <button type="button" class="neo-btn neo-btn-primary" data-modal-open="modalUploadDoc" aria-haspopup="dialog" aria-controls="modalUploadDoc"><i class="fas fa-upload" aria-hidden="true"></i> Upload Dokumentasi</button>
    <?php endif; ?>
</div>

<?php if ($error_msg): ?><div class="neo-alert neo-alert-error" role="alert"><?= e($error_msg) ?></div><?php endif; ?>
<?php displayFlash(); ?>

<div class="documentation-gallery">
    <?php if (empty($docs)): ?>
        <div class="neo-card documentation-empty">
            <p class="documentation-empty__message">Belum ada foto dokumentasi.</p>
        </div>
    <?php else: ?>
        <?php foreach ($docs as $d): ?>
            <article class="neo-card documentation-card">
                <div>
                    <img src="../assets/uploads/<?= e($d['gambar']) ?>" alt="Dokumentasi <?= e($d['nama_proker']) ?>" class="documentation-image">
                    <div class="documentation-card__meta">
                        <span class="neo-badge documentation-project-badge"><i class="fas fa-tag" aria-hidden="true"></i> <?= e($d['nama_proker']) ?></span>
                        <p class="documentation-card__description">
                            <?php if (!empty($d['keterangan'])): ?>
                                <?= nl2br(e($d['keterangan'])) ?>
                            <?php else: ?>
                                <em class="documentation-card__description-empty">Tidak ada keterangan.</em>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <?php if ($can_manage_docs): ?>
                    <div class="documentation-card__actions">
                        <form method="POST" data-confirm="Hapus foto ini?">
            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_doc">
                            <input type="hidden" name="doc_id" value="<?= $d['id'] ?>">
                            <button type="submit" class="neo-btn neo-btn-danger neo-btn-sm" aria-label="Hapus foto dokumentasi <?= e($d['nama_proker']) ?>"><i class="fas fa-trash" aria-hidden="true"></i> Hapus</button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($can_manage_docs): ?>
<div id="modalUploadDoc" class="neo-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-upload-doc-title">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title" id="modal-upload-doc-title">Upload Foto Dokumentasi</h3>
            <button type="button" class="modal-close" data-modal-close="modalUploadDoc" aria-label="Tutup modal upload dokumentasi">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload_doc">
            <div class="modal-form-group">
                <label class="neo-label" for="documentation-project">Pilih Program Kerja</label>
                <select id="documentation-project" name="proker_id" class="neo-input" required>
                    <option value="">-- Pilih Proker --</option>
                    <?php foreach ($proker_list as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['nama_proker']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="modal-form-group">
                <label class="neo-label" for="documentation-image">File Gambar (JPG/PNG/WEBP, maks 5MB)</label>
                <input type="file" id="documentation-image" name="gambar" class="neo-input" accept="image/*" required>
            </div>
            <div class="modal-form-group modal-form-group--final">
                <label class="neo-label" for="documentation-description">Keterangan (Opsional)</label>
                <textarea id="documentation-description" name="keterangan" class="neo-input" rows="3"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="neo-btn modal-cancel-button" data-modal-close="modalUploadDoc">Batal</button>
                <button type="submit" class="neo-btn neo-btn-success">Upload Foto</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>
