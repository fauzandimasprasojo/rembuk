<?php
require_once '../includes/init.php';
requireRole(['BPH', 'Koordinator Divisi']); // Tambah agenda: BPH & Koordinator saja.

// BPH boleh menarget divisi mana pun (atau global); Koordinator terkunci ke divisinya.
$isBPH = $_SESSION['role'] === 'BPH';
$semuaDivisi = $pdo->query("SELECT id, nama_divisi FROM divisi ORDER BY nama_divisi")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = trim($_POST['judul']);
    $deskripsi = trim($_POST['deskripsi']);
    $tanggal = $_POST['tanggal'];
    $kategori = $_POST['kategori'];
    $divisi_id = $isBPH ? ($_POST['divisi_id'] ?: null) : $_SESSION['divisi_id'];

    $stmt = $pdo->prepare("INSERT INTO agenda (judul, deskripsi, tanggal, kategori, divisi_id, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$judul, $deskripsi, $tanggal, $kategori, $divisi_id, $_SESSION['user_id']]);

    flash('success', 'Hari penting berhasil ditambahkan ke kalender.');
    header('Location: index.php?bulan=' . date('n', strtotime($tanggal)) . '&tahun=' . date('Y', strtotime($tanggal)) . '&tanggal=' . $tanggal);
    exit;
}

$pageTitle = 'Tambah Hari Penting';
require_once '../includes/header.php';
?>
<div class="narrow-560">
    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Kalender</a>
    <div class="neo-card calendar-form-card">
        <h2 class="section-heading">Tambah Hari Penting</h2>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="neo-label">Judul</label>
                <input type="text" name="judul" class="neo-input" required placeholder="Contoh: Rapat Kerja Nasional">
            </div>
            <div class="form-group">
                <label class="neo-label">Tanggal</label>
                <input type="date" name="tanggal" class="neo-input" required>
            </div>
            <div class="form-group">
                <label class="neo-label">Kategori</label>
                <select name="kategori" class="neo-input" required>
                    <option value="">-- pilih kategori --</option>
                    <option value="umum">Umum</option>
                    <option value="penting">Penting</option>
                    <option value="deadline">Deadline</option>
                </select>
            </div>
            <?php if ($isBPH): ?>
                <div class="form-group">
                    <label class="neo-label">Target Divisi</label>
                    <select name="divisi_id" class="neo-input">
                        <option value="">Semua Divisi (Global)</option>
                        <?php foreach ($semuaDivisi as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['nama_divisi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-group form-group-lg">
                <label class="neo-label">Deskripsi (opsional)</label>
                <textarea name="deskripsi" class="neo-input" rows="4"></textarea>
            </div>
            <button type="submit" class="neo-btn neo-btn-primary">Simpan</button>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
