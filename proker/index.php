<?php
require_once '../includes/init.php';
requireLogin();

// Hak tambah/edit: hanya BPH & Koordinator (Koordinator terbatas ke divisinya sendiri).
$isBPH = $_SESSION['role'] === 'BPH';
$isKoor = $_SESSION['role'] === 'Koordinator Divisi';
$can_add_proker = $isBPH || $isKoor;

$semuaDivisi = $pdo->query("SELECT id, nama_divisi FROM divisi ORDER BY nama_divisi")->fetchAll();

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_proker') {
    if (!$can_add_proker) {
        $error_msg = "Anda tidak memiliki akses untuk menambah Program Kerja!";
    } else {
        $nama_proker = trim($_POST['nama_proker']);
        // Koordinator dikunci ke divisinya sendiri; BPH bebas pilih divisi.
        $divisi_id = $isBPH ? (int) $_POST['divisi_id'] : (int) $_SESSION['divisi_id'];
        $tanggal_pelaksanaan = $_POST['tanggal_pelaksanaan'];
        $tanggal_selesai = $_POST['tanggal_selesai'] ?: null;

        if (!empty($nama_proker) && $divisi_id && !empty($tanggal_pelaksanaan)) {
            $stmt = $pdo->prepare(
                "INSERT INTO proker (nama_proker, divisi_id, tanggal_pelaksanaan, tanggal_selesai, status, progress_persen, created_by)
                 VALUES (?, ?, ?, ?, 'To-do', 0, ?)"
            );
            $stmt->execute([$nama_proker, $divisi_id, $tanggal_pelaksanaan, $tanggal_selesai, $_SESSION['user_id']]);
            $success_msg = "Program Kerja berhasil ditambahkan!";
        } else {
            $error_msg = "Semua bidang wajib diisi!";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_proker') {
    $edit_id = (int) ($_POST['proker_id'] ?? 0);

    // Ambil data lama dulu untuk cek kepemilikan divisi (Koordinator hanya boleh edit proker divisinya sendiri)
    $stmt_lama = $pdo->prepare("SELECT divisi_id FROM proker WHERE id = ?");
    $stmt_lama->execute([$edit_id]);
    $proker_lama = $stmt_lama->fetch();

    $bolehEdit = $can_add_proker && $proker_lama && ($isBPH || ($isKoor && $proker_lama['divisi_id'] == $_SESSION['divisi_id']));

    if (!$edit_id || !$bolehEdit) {
        $error_msg = "Anda tidak memiliki akses untuk mengedit Program Kerja ini!";
    } else {
        $nama_proker = trim($_POST['nama_proker']);
        // Koordinator tidak bisa memindahkan proker ke divisi lain
        $divisi_id = $isBPH ? (int) $_POST['divisi_id'] : (int) $proker_lama['divisi_id'];
        $tanggal_pelaksanaan = $_POST['tanggal_pelaksanaan'];
        $tanggal_selesai = $_POST['tanggal_selesai'] ?: null;

        if (!empty($nama_proker) && $divisi_id && !empty($tanggal_pelaksanaan)) {
            $stmt = $pdo->prepare(
                "UPDATE proker SET nama_proker = ?, divisi_id = ?, tanggal_pelaksanaan = ?, tanggal_selesai = ? WHERE id = ?"
            );
            $stmt->execute([$nama_proker, $divisi_id, $tanggal_pelaksanaan, $tanggal_selesai, $edit_id]);
            $success_msg = "Program Kerja berhasil diperbarui!";
        } else {
            $error_msg = "Semua bidang wajib diisi!";
        }
    }
}

// Semua anggota boleh MELIHAT seluruh proker; hak tambah/kelola dibatasi terpisah di bawah
$sql = "SELECT p.*, d.nama_divisi FROM proker p JOIN divisi d ON d.id = p.divisi_id";
$params = [];
$sql .= " ORDER BY p.tanggal_pelaksanaan ASC";
$stmt_proker = $pdo->prepare($sql);
$stmt_proker->execute($params);
$all_proker = $stmt_proker->fetchAll();

$pageTitle = 'Program Kerja';
if (($_GET['msg'] ?? '') === 'dihapus') { $success_msg = 'Program kerja berhasil dihapus.'; }
require_once '../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title">Daftar Program Kerja</h2>
    <div class="proker-toolbar">
        <div class="proker-search-wrap">
            <input type="text" id="prokerSearchInput" class="neo-input" placeholder="Cari nama proker..." autocomplete="off" aria-label="Cari nama proker">
            <div id="prokerSearchSuggestions" class="proker-search-suggestions"></div>
        </div>
        <?php if ($can_add_proker): ?>
            <button type="button" class="neo-btn neo-btn-primary" data-modal-open="modalProker"><i class="fas fa-plus"></i> Tambah Proker</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($success_msg): ?><div class="neo-alert neo-alert-success"><?= e($success_msg) ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="neo-alert neo-alert-error"><?= e($error_msg) ?></div><?php endif; ?>

<div class="proker-grid">
    <?php if (empty($all_proker)): ?>
        <p class="proker-empty">Belum ada Program Kerja.</p>
    <?php else: ?>
        <?php foreach ($all_proker as $p): ?>
            <?php
                $badge_class = $p['status'] == 'Done' ? 'done' : ($p['status'] == 'In Progress' ? 'progress' : 'todo');
                $bolehEditIni = $can_add_proker && ($isBPH || ($isKoor && $p['divisi_id'] == $_SESSION['divisi_id']));
            ?>
            <div class="neo-card proker-card">
                <div>
                    <div class="flex-between">
                        <span class="neo-badge status-<?= $badge_class ?>"><?= $p['status'] ?></span>
                        <small class="proker-card-date"><i class="fas fa-calendar"></i> <?= tanggalIndo($p['tanggal_pelaksanaan']) ?></small>
                    </div>
                    <h3 class="proker-card-title"><?= e($p['nama_proker']) ?></h3>
                    <p class="proker-card-division">Divisi: <?= e($p['nama_divisi']) ?></p>
                    <div class="proker-card-progress">
                        <small class="text-bold">Progress: <?= $p['progress_persen'] ?>%</small>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" data-progress="<?= (int) $p['progress_persen'] ?>"></div></div>
                    </div>
                </div>
                <div class="proker-card-actions">
                    <a href="detail.php?id=<?= $p['id'] ?>" class="neo-btn neo-btn-warning proker-detail-link">Detail &amp; Tasks <i class="fas fa-arrow-right"></i></a>
                    <?php if ($bolehEditIni): ?>
                        <button type="button" class="neo-btn neo-btn-sm neo-btn-outline"
                            data-modal="edit-proker"
                            data-id="<?= $p['id'] ?>"
                            data-nama="<?= e($p['nama_proker']) ?>"
                            data-divisi="<?= $p['divisi_id'] ?>"
                            data-mulai="<?= e($p['tanggal_pelaksanaan']) ?>"
                            data-selesai="<?= e($p['tanggal_selesai'] ?? '') ?>"
                            title="Edit Proker">
                            <i class="fas fa-pen"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($can_add_proker): ?>
<div id="modalProker" class="neo-modal-overlay">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title">Tambah Program Kerja</h3>
            <button type="button" class="modal-close" data-modal-close="modalProker" aria-label="Tambah Program Kerja">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_proker">
            <div class="form-group">
                <label class="neo-label">Nama Program Kerja</label>
                <input type="text" name="nama_proker" class="neo-input" required placeholder="Contoh: Raker Internal 2026">
            </div>
            <?php if ($isBPH): ?>
                <div class="form-group">
                    <label class="neo-label">Divisi Penanggung Jawab</label>
                    <select name="divisi_id" class="neo-input" required>
                        <option value="">-- pilih divisi --</option>
                        <?php foreach ($semuaDivisi as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['nama_divisi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <p class="form-note">Proker ini akan tercatat untuk divisimu.</p>
            <?php endif; ?>
            <div class="form-group">
                <label class="neo-label">Tanggal Pelaksanaan</label>
                <input type="date" name="tanggal_pelaksanaan" class="neo-input" required>
            </div>
            <div class="form-group form-group-lg">
                <label class="neo-label">Tanggal Selesai (opsional, untuk proker berdurasi)</label>
                <input type="date" name="tanggal_selesai" class="neo-input">
            </div>
            <div class="form-actions">
                <button type="button" class="neo-btn neo-btn-muted" data-modal-close="modalProker">Batal</button>
                <button type="submit" class="neo-btn neo-btn-success">Simpan Proker</button>
            </div>
        </form>
    </div>
</div>

<div id="modalEditProker" class="neo-modal-overlay">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title">Edit Program Kerja</h3>
            <button type="button" class="modal-close" data-modal-close="modalEditProker" aria-label="Edit Program Kerja">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_proker">
            <input type="hidden" name="proker_id" id="edit_proker_id">
            <div class="form-group">
                <label class="neo-label">Nama Program Kerja</label>
                <input type="text" name="nama_proker" id="edit_proker_nama" class="neo-input" required>
            </div>
            <?php if ($isBPH): ?>
                <div class="form-group">
                    <label class="neo-label">Divisi Penanggung Jawab</label>
                    <select name="divisi_id" id="edit_proker_divisi" class="neo-input" required>
                        <option value="">-- pilih divisi --</option>
                        <?php foreach ($semuaDivisi as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['nama_divisi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label class="neo-label">Tanggal Pelaksanaan</label>
                <input type="date" name="tanggal_pelaksanaan" id="edit_proker_mulai" class="neo-input" required>
            </div>
            <div class="form-group form-group-lg">
                <label class="neo-label">Tanggal Selesai (opsional, untuk proker berdurasi)</label>
                <input type="date" name="tanggal_selesai" id="edit_proker_selesai" class="neo-input">
            </div>
            <div class="form-actions">
                <button type="button" class="neo-btn neo-btn-muted" data-modal-close="modalEditProker">Batal</button>
                <button type="submit" class="neo-btn neo-btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
