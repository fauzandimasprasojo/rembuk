<?php
require_once '../includes/init.php';
requireRole(['BPH']); // Kelola divisi khusus BPH.

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'add') {
        // Tambah divisi baru; deskripsi boleh kosong.
        $nama = trim($_POST['nama_divisi']);
        $deskripsi = trim($_POST['deskripsi']);
        if ($nama) {
            $stmt = $pdo->prepare("INSERT INTO divisi (nama_divisi, deskripsi) VALUES (?, ?)");
            $stmt->execute([$nama, $deskripsi]);
            flash('success', 'Divisi "' . $nama . '" berhasil ditambahkan.');
        } else {
            flash('error', 'Nama divisi wajib diisi.');
        }
    }
    if (($_POST['action'] ?? '') === 'edit') {
        // Edit nama & deskripsi divisi yang sudah ada.
        $id = (int) ($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_divisi'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        if ($id && $nama) {
            $stmt = $pdo->prepare("UPDATE divisi SET nama_divisi = ?, deskripsi = ? WHERE id = ?");
            $stmt->execute([$nama, $deskripsi, $id]);
            flash('success', 'Divisi berhasil diperbarui.');
        } else {
            $error = "Nama divisi wajib diisi.";
        }
    }
    if (($_POST['action'] ?? '') === 'delete') {
        // Divisi yang masih dipakai user/proker akan gagal dihapus karena FK constraint — itu sengaja, agar data tidak yatim
        try {
            $stmt = $pdo->prepare("DELETE FROM divisi WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            flash('success', 'Divisi berhasil dihapus.');
        } catch (PDOException $e) {
            $error = "Divisi tidak bisa dihapus karena masih dipakai oleh user/proker/surat.";
        }
    }
    if (!$error) { header("Location: index.php"); exit; }
}

$semuaDivisi = $pdo->query(
    "SELECT d.*, (SELECT COUNT(*) FROM users WHERE divisi_id = d.id) AS jumlah_anggota
     FROM divisi d ORDER BY d.nama_divisi"
)->fetchAll();

$pageTitle = 'Kelola Divisi';
require_once '../includes/header.php';
?>
<div class="flex-between page-header">
    <h2 class="page-title">Kelola Divisi</h2>
    <button type="button" class="neo-btn neo-btn-primary" data-modal-open="modalDivisi" aria-haspopup="dialog" aria-controls="modalDivisi"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Divisi</button>
</div>

<?php if ($error): ?><div class="neo-alert neo-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php displayFlash(); ?>

<div class="neo-card organization-card">
    <h3 class="organization-title"><i class="fas fa-sitemap" aria-hidden="true"></i> Struktur Organisasi</h3>
    <?php include '../includes/bagan.php'; ?>
</div>

<h3 class="subsection-title"><i class="fas fa-layer-group" aria-hidden="true"></i> Daftar Divisi</h3>
<div class="grid-auto">
    <?php foreach ($semuaDivisi as $d): ?>
        <article class="neo-card">
            <h3 class="division-card__title"><?= e($d['nama_divisi']) ?></h3>
            <p class="division-card__description"><?= e($d['deskripsi'] ?: 'Belum ada deskripsi.') ?></p>
            <p class="division-card__count"><?= $d['jumlah_anggota'] ?> anggota</p>
            <div class="division-card__actions">
                <button type="button" class="neo-btn neo-btn-outline neo-btn-sm" data-modal="edit-divisi"
                        data-id="<?= $d['id'] ?>" data-nama="<?= e($d['nama_divisi']) ?>" data-deskripsi="<?= e($d['deskripsi'] ?? '') ?>"
                        aria-label="Edit divisi <?= e($d['nama_divisi']) ?>"><i class="fas fa-pen"></i> Edit</button>
                <form method="POST" data-confirm="Hapus divisi ini? Hanya bisa jika belum dipakai user/proker/surat.">
                <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                    <button type="submit" class="neo-btn neo-btn-danger neo-btn-sm" aria-label="Hapus divisi <?= e($d['nama_divisi']) ?>">Hapus</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<div id="modalDivisi" class="neo-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-divisi-title">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title" id="modal-divisi-title">Tambah Divisi</h3>
            <button type="button" class="modal-close" data-modal-close="modalDivisi" aria-label="Tutup modal tambah divisi">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-form-group">
                <label class="neo-label" for="division-name">Nama Divisi</label>
                <input type="text" id="division-name" name="nama_divisi" class="neo-input" required placeholder="Contoh: Divisi Acara">
            </div>
            <div class="modal-form-group modal-form-group--final">
                <label class="neo-label" for="division-description">Deskripsi (opsional)</label>
                <textarea id="division-description" name="deskripsi" class="neo-input" rows="3"></textarea>
            </div>
            <button type="submit" class="neo-btn neo-btn-success">Simpan</button>
        </form>
    </div>
</div>
<div id="modalEditDivisi" class="neo-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-edit-divisi-title">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title" id="modal-edit-divisi-title">Edit Divisi</h3>
            <button type="button" class="modal-close" data-modal-close="modalEditDivisi" aria-label="Tutup modal edit divisi">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_divisi_id">
            <div class="modal-form-group">
                <label class="neo-label" for="edit_divisi_nama">Nama Divisi</label>
                <input type="text" id="edit_divisi_nama" name="nama_divisi" class="neo-input" required>
            </div>
            <div class="modal-form-group modal-form-group--final">
                <label class="neo-label" for="edit_divisi_deskripsi">Deskripsi (opsional)</label>
                <textarea id="edit_divisi_deskripsi" name="deskripsi" class="neo-input" rows="3"></textarea>
            </div>
            <button type="submit" class="neo-btn neo-btn-success">Simpan Perubahan</button>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
