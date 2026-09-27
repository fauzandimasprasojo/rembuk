<?php
require_once '../includes/init.php';
requireLogin();   // <-- perbaikan kritis: dulu proses POST berjalan SEBELUM baris ini ada

$proker_id = $_GET['id'] ?? null;
if (!$proker_id) { header("Location: index.php"); exit; }

// Ambil proker dulu untuk keperluan cek RBAC divisi
$stmt = $pdo->prepare("SELECT * FROM proker WHERE id = ?");
$stmt->execute([$proker_id]);
$proker = $stmt->fetch();
if (!$proker) { header("Location: index.php"); exit; }

$isBPH = $_SESSION['role'] === 'BPH';
$isKoorDivisiIni = $_SESSION['role'] === 'Koordinator Divisi' && $_SESSION['divisi_id'] == $proker['divisi_id'];

// Semua anggota boleh melihat detail proker; hanya BPH atau Koordinator divisi terkait yang boleh kelola tasks
$bolehKelola = $isBPH || $isKoorDivisiIni;

// Menghitung ulang progres & status proker dari task-nya: 0 task = To-do,
// semua Done = Done (100%), selebihnya In Progress. Dipanggil tiap ada perubahan task.
function recalculateProkerProgress($pdo, $id) {
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE proker_id = ?");
    $stmt_total->execute([$id]);
    $total = $stmt_total->fetchColumn();

    if ($total == 0) {
        $progress = 0; $status = 'To-do';
    } else {
        $stmt_done = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE proker_id = ? AND status = 'Done'");
        $stmt_done->execute([$id]);
        $done = $stmt_done->fetchColumn();
        $progress = round(($done / $total) * 100);
        $status = $progress == 100 ? 'Done' : ($progress > 0 ? 'In Progress' : 'To-do');
    }

    $stmt_upd = $pdo->prepare("UPDATE proker SET progress_persen = ?, status = ? WHERE id = ?");
    $stmt_upd->execute([$progress, $status, $id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Semua aksi tulis butuh hak kelola; tanpa itu kembali tanpa mengubah data.
    if (!$bolehKelola) { header("Location: detail.php?id=" . $proker_id); exit; }

    if (($_POST['action'] ?? '') === 'add_task') {
        $detail_jobdesk = trim($_POST['detail_jobdesk']);
        $pic_id = !empty($_POST['pic_id']) ? $_POST['pic_id'] : null;
        $lampiran = trim($_POST['lampiran']);

        if (!empty($detail_jobdesk)) {
            $stmt = $pdo->prepare("INSERT INTO tasks (proker_id, detail_jobdesk, pic_id, status, lampiran) VALUES (?, ?, ?, 'To-do', ?)");
            $stmt->execute([$proker_id, $detail_jobdesk, $pic_id, $lampiran]);
            recalculateProkerProgress($pdo, $proker_id);
            flash('success', 'Jobdesk berhasil ditambahkan.');
        }
    }

    if (($_POST['action'] ?? '') === 'update_task') {
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, lampiran = ? WHERE id = ? AND proker_id = ?");
        $stmt->execute([$_POST['status'], trim($_POST['lampiran']), $_POST['task_id'], $proker_id]);
        recalculateProkerProgress($pdo, $proker_id);
        flash('success', 'Jobdesk berhasil diperbarui.');
    }

    if (($_POST['action'] ?? '') === 'delete_proker') {
        if (!$isBPH) { header("Location: detail.php?id=" . $proker_id); exit; }

        // Proker yang sudah punya catatan keuangan tidak boleh dihapus (supaya saldo & laporan tidak berubah)
        $cek = $pdo->prepare("SELECT COUNT(*) FROM keuangan WHERE proker_id = ?");
        $cek->execute([$proker_id]);
        if ($cek->fetchColumn() > 0) {
            header("Location: detail.php?id=" . $proker_id . "&error=keuangan");
            exit;
        }

        // Catat nama file foto dokumentasi dulu, karena barisnya ikut terhapus (CASCADE) bersama tasks
        $stmt = $pdo->prepare("SELECT gambar FROM dokumentasi WHERE proker_id = ?");
        $stmt->execute([$proker_id]);
        $fotoList = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $pdo->prepare("DELETE FROM proker WHERE id = ?")->execute([$proker_id]);

        foreach ($fotoList as $foto) {
            $path = '../assets/uploads/' . basename($foto);
            if (is_file($path)) unlink($path);
        }
        header("Location: index.php?msg=dihapus");
        exit;
    }

    if (($_POST['action'] ?? '') === 'delete_task') {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND proker_id = ?");
        $stmt->execute([$_POST['task_id'], $proker_id]);
        recalculateProkerProgress($pdo, $proker_id);
        flash('success', 'Jobdesk berhasil dihapus.');
    }

    header("Location: detail.php?id=" . $proker_id);
    exit;
}

$pageTitle = $proker['nama_proker'];
require_once '../includes/header.php';

$stmt_tasks = $pdo->prepare("SELECT t.*, u.nama_lengkap as pic_nama FROM tasks t LEFT JOIN users u ON t.pic_id = u.id WHERE t.proker_id = ?");
$stmt_tasks->execute([$proker_id]);
$tasks = $stmt_tasks->fetchAll();

// PIC hanya dari divisi yang sama (bukan seluruh user organisasi)
$users_list = $pdo->prepare("SELECT id, nama_lengkap FROM users WHERE divisi_id = ? ORDER BY nama_lengkap ASC");
$users_list->execute([$proker['divisi_id']]);
$users_list = $users_list->fetchAll();
?>

<div class="detail-back">
    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Proker</a>
</div>

<?php if (($_GET['error'] ?? '') === 'keuangan'): ?>
    <div class="neo-alert neo-alert-error">Proker tidak bisa dihapus karena sudah punya catatan keuangan. Hapus atau pindahkan catatan keuangannya dulu.</div>
<?php endif; ?>
<?php displayFlash(); ?>

<div class="neo-card detail-summary">
    <div class="flex-between">
        <h2 class="page-title"><?= e($proker['nama_proker']) ?></h2>
        <div class="detail-actions">
            <span class="neo-btn neo-btn-outline neo-btn-static"><?= $proker['status'] ?></span>
            <?php if ($isBPH): ?>
                <form method="POST" class="inline-form" data-confirm="Hapus proker ini beserta semua jobdesk dan foto dokumentasinya? Tindakan ini tidak bisa dibatalkan.">
            <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_proker">
                    <button type="submit" class="neo-btn neo-btn-danger neo-btn-sm"><i class="fas fa-trash"></i> Hapus Proker</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <p class="detail-date">Tanggal Pelaksanaan: <?= tanggalIndo($proker['tanggal_pelaksanaan']) ?><?= $proker['tanggal_selesai'] ? ' &ndash; ' . tanggalIndo($proker['tanggal_selesai']) : '' ?></p>
    <div class="detail-progress">
        <span class="text-bold">Progress Total: <?= $proker['progress_persen'] ?>%</span>
        <div class="progress-bar-bg progress-track-detail">
            <div class="progress-bar-fill" data-progress="<?= (int) $proker['progress_persen'] ?>"></div>
        </div>
    </div>
</div>

<div class="flex-between tasks-header">
    <h3 class="page-title">Daftar Jobdesk / Tasks</h3>
    <?php if ($bolehKelola): ?>
        <button type="button" class="neo-btn neo-btn-success" data-modal-open="modalAddTask"><i class="fas fa-plus"></i> Tambah Jobdesk</button>
    <?php endif; ?>
</div>

<div class="neo-card table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Jobdesk</th>
                <th>PIC</th>
                <th>Status</th>
                <th>Lampiran</th>
                <?php if ($bolehKelola): ?><th class="table-action">Aksi</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tasks)): ?>
                <tr><td colspan="5" class="table-empty">Belum ada jobdesk.</td></tr>
            <?php else: ?>
                <?php foreach ($tasks as $t): ?>
                    <?php $task_status_class = $t['status'] == 'Done' ? 'done' : ($t['status'] == 'In Progress' ? 'progress' : 'todo'); ?>
                    <tr>
                        <td><?= e($t['detail_jobdesk']) ?></td>
                        <td><?= e($t['pic_nama'] ?? 'Belum ada PIC') ?></td>
                        <td>
                            <span class="neo-badge status-<?= $task_status_class ?>"><?= $t['status'] ?></span>
                        </td>
                        <td>
                            <?php if (!empty($t['lampiran'])): ?>
                                <a href="<?= e($t['lampiran']) ?>" target="_blank" rel="noopener" class="attachment-link"><i class="fas fa-link"></i> Buka</a>
                            <?php else: ?><span class="text-subtle">-</span><?php endif; ?>
                        </td>
                        <?php if ($bolehKelola): ?>
                        <td class="table-action">
                            <button type="button" class="neo-btn neo-btn-warning neo-btn-sm" data-modal="edit-task" data-id="<?= $t['id'] ?>" data-status="<?= e($t['status']) ?>" data-lampiran="<?= e($t['lampiran'] ?? '') ?>">Edit</button>
                            <form method="POST" class="inline-form" data-confirm="Hapus jobdesk ini?">
            <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_task">
                                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="neo-btn neo-btn-danger neo-btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($bolehKelola): ?>
<div id="modalAddTask" class="neo-modal-overlay">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title">Tambah Jobdesk Baru</h3>
            <button type="button" class="modal-close" data-modal-close="modalAddTask" aria-label="Tambah Jobdesk Baru">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_task">
            <div class="form-group">
                <label class="neo-label">Detail Jobdesk</label>
                <input type="text" name="detail_jobdesk" class="neo-input" required placeholder="Contoh: Membuat Proposal Sponsor">
            </div>
            <div class="form-group">
                <label class="neo-label">Pilih PIC (dari divisi ini)</label>
                <select name="pic_id" class="neo-input">
                    <option value="">-- Tanpa PIC --</option>
                    <?php foreach ($users_list as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= e($u['nama_lengkap']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group form-group-lg">
                <label class="neo-label">Link Lampiran (Opsional)</label>
                <input type="url" name="lampiran" class="neo-input" placeholder="https://docs.google.com/...">
            </div>
            <div class="form-actions">
                <button type="button" class="neo-btn neo-btn-muted" data-modal-close="modalAddTask">Batal</button>
                <button type="submit" class="neo-btn neo-btn-success">Simpan Jobdesk</button>
            </div>
        </form>
    </div>
</div>

<div id="modalEditTask" class="neo-modal-overlay">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title">Update Progress Jobdesk</h3>
            <button type="button" class="modal-close" data-modal-close="modalEditTask" aria-label="Update Progress Jobdesk">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_task">
            <input type="hidden" name="task_id" id="edit_task_id">
            <div class="form-group">
                <label class="neo-label">Status Progress</label>
                <select name="status" id="edit_status" class="neo-input">
                    <option value="To-do">To-do</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Done">Done</option>
                </select>
            </div>
            <div class="form-group form-group-lg">
                <label class="neo-label">Link Lampiran</label>
                <input type="url" name="lampiran" id="edit_lampiran" class="neo-input">
            </div>
            <div class="form-actions">
                <button type="button" class="neo-btn neo-btn-muted" data-modal-close="modalEditTask">Batal</button>
                <button type="submit" class="neo-btn neo-btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
