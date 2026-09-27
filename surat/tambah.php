<?php
require_once '../includes/init.php';
requireLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tentang = trim($_POST['tentang']);
    $tujuan  = trim($_POST['tujuan']);
    $file_name = null;

    if (isset($_FILES['file_surat']) && $_FILES['file_surat']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/surat/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        // Hanya PDF/DOC/DOCX; nama file dibersihkan dari karakter berbahaya.
        $file_ext = strtolower(pathinfo($_FILES['file_surat']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['pdf', 'doc', 'docx'])) {
            $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['file_surat']['name']);
            move_uploaded_file($_FILES['file_surat']['tmp_name'], $upload_dir . $file_name);
        } else {
            $error = 'Format file harus PDF, DOC, atau DOCX!';
        }
    }

    if (!$error) {
        $stmt = $pdo->prepare(
            "INSERT INTO surat (user_id, divisi_id, tentang, tujuan, file_surat, status) VALUES (?, ?, ?, ?, ?, 'Pending')"
        );
        $stmt->execute([$_SESSION['user_id'], $_SESSION['divisi_id'], $tentang, $tujuan, $file_name]);
        $suratId = $pdo->lastInsertId();

        $log = $pdo->prepare("INSERT INTO surat_log (surat_id, status, catatan, oleh_user_id) VALUES (?, 'Pending', 'Pengajuan baru dibuat.', ?)");
        $log->execute([$suratId, $_SESSION['user_id']]);

        flash('success', 'Pengajuan surat berhasil dikirim, menunggu peninjauan.');
        header('Location: detail.php?id=' . $suratId);
        exit;
    }
}

$pageTitle = 'Ajukan Surat';
require_once '../includes/header.php';
?>
<div class="narrow-600">
    <div class="neo-card letter-form-card">
        <h2 class="section-heading text-center text-heavy">Form Pengajuan Surat</h2>
        <?php if ($error): ?><div class="neo-alert neo-alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="neo-label">Surat Tentang / Perihal</label>
                <input type="text" name="tentang" class="neo-input" required placeholder="Contoh: Permohonan Izin Tempat Gedung A">
            </div>
            <div class="form-group">
                <label class="neo-label">Diajukan Untuk Siapa / Tujuan Surat</label>
                <input type="text" name="tujuan" class="neo-input" required placeholder="Contoh: Dekan Fakultas">
            </div>
            <div class="form-group form-group-lg">
                <label class="neo-label">Upload Draft Surat (Opsional - PDF/DOCX)</label>
                <input type="file" name="file_surat" class="neo-input input-file" accept=".pdf,.doc,.docx">
            </div>
            <div class="form-actions">
                <button type="submit" class="neo-btn neo-btn-success form-submit--grow">Kirim Pengajuan</button>
                <a href="index.php" class="neo-btn neo-btn-outline no-link">Batal</a>
            </div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
