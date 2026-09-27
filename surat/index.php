<?php
require_once '../includes/init.php';
requireLogin();

$isBPH = $_SESSION['role'] === 'BPH';
$isKoor = $_SESSION['role'] === 'Koordinator Divisi';

// Pencarian & filter (GET): q (tentang/tujuan/nomor), status, dari/sampai (tanggal pengajuan),
// plus divisi khusus BPH. Selalu digabung dengan cakupan role di bawah.
$s_q = trim($_GET['q'] ?? '');
$s_status = in_array($_GET['status'] ?? '', ['Pending', 'Revisi', 'Diterima', 'Ditolak'], true) ? $_GET['status'] : '';
$s_dari = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari'] ?? '') ? $_GET['dari'] : '';
$s_sampai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai'] ?? '') ? $_GET['sampai'] : '';
$s_divisi = ($isBPH && ctype_digit((string) ($_GET['divisi'] ?? ''))) ? (int) $_GET['divisi'] : 0;
$semuaDivisiSurat = $isBPH ? $pdo->query("SELECT id, nama_divisi FROM divisi ORDER BY nama_divisi")->fetchAll() : [];

// Cakupan data per role: BPH melihat semua; Koordinator se-divisinya; Anggota miliknya sendiri.
// Query hitung total baris memakai WHERE yang SAMA PERSIS dengan query datanya,
// supaya jumlah halaman tidak pernah meleset dari cakupan role yang bersangkutan.
$conditions = [];
$count_params = [];
if ($isKoor) { $conditions[] = 's.divisi_id = :scope_divisi'; $count_params[':scope_divisi'] = $_SESSION['divisi_id']; }
elseif (!$isBPH) { $conditions[] = 's.user_id = :scope_user'; $count_params[':scope_user'] = $_SESSION['user_id']; }

if ($s_q !== '') { $conditions[] = "(s.tentang LIKE :fq1 ESCAPE '\\\\' OR s.tujuan LIKE :fq2 ESCAPE '\\\\' OR s.nomor_surat LIKE :fq3 ESCAPE '\\\\')"; $count_params[':fq1'] = "%" . escapeLike($s_q) . "%"; $count_params[':fq2'] = "%" . escapeLike($s_q) . "%"; $count_params[':fq3'] = "%" . escapeLike($s_q) . "%"; }
if ($s_status !== '') { $conditions[] = "s.status = :fstatus"; $count_params[':fstatus'] = $s_status; }
if ($s_dari !== '') { $conditions[] = "DATE(s.created_at) >= :fdari"; $count_params[':fdari'] = $s_dari; }
if ($s_sampai !== '') { $conditions[] = "DATE(s.created_at) <= :fsampai"; $count_params[':fsampai'] = $s_sampai; }
if ($s_divisi) { $conditions[] = "s.divisi_id = :fdivisi"; $count_params[':fdivisi'] = $s_divisi; }

$full_where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM surat s $full_where");
$stmt_count->execute($count_params);
$total_surat_rows = (int) $stmt_count->fetchColumn();
[$surat_page, $surat_total_pages, $surat_offset] = paginateParams($total_surat_rows);

$stmt = $pdo->prepare(
    "SELECT s.*, u.nama_lengkap, d.nama_divisi FROM surat s LEFT JOIN users u ON s.user_id = u.id LEFT JOIN divisi d ON s.divisi_id = d.id $full_where ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset"
);
foreach ($count_params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':limit', PAGINATE_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $surat_offset, PDO::PARAM_INT);
$stmt->execute();
$daftar_surat = $stmt->fetchAll();

$surat_filter_query = '';
if ($s_q !== '') $surat_filter_query .= '&q=' . urlencode($s_q);
if ($s_status !== '') $surat_filter_query .= '&status=' . urlencode($s_status);
if ($s_dari !== '') $surat_filter_query .= '&dari=' . urlencode($s_dari);
if ($s_sampai !== '') $surat_filter_query .= '&sampai=' . urlencode($s_sampai);
if ($s_divisi) $surat_filter_query .= '&divisi=' . $s_divisi;
$surat_filter_aktif = ($s_q !== '' || $s_status !== '' || $s_dari !== '' || $s_sampai !== '' || $s_divisi);

$statusClass = ['Pending' => 'status-pending', 'Revisi' => 'status-revisi', 'Diterima' => 'status-diterima', 'Ditolak' => 'status-ditolak'];

$pageTitle = 'Persuratan';
require_once '../includes/header.php';
?>
<div class="flex-between page-header">
    <div>
        <h2 class="page-title">Persuratan Organisasi</h2>
        <p class="page-subtitle">Download template atau ajukan pembuatan surat.</p>
    </div>
    <a href="tambah.php" class="neo-btn neo-btn-success no-link"><i class="fas fa-plus-circle"></i> Ajukan Surat Baru</a>
</div>
<?php displayFlash(); ?>

<div class="neo-card template-section">
    <h2 class="section-heading template-heading"><i class="fas fa-file-arrow-down"></i> Template Surat Siap Pakai</h2>
    <div class="grid-auto template-grid">
        <div class="neo-card template-card">
            <div><strong>Surat Undangan</strong><br><small class="text-muted">PDF - Google Drive</small></div>
            <a href="https://docs.google.com/document/d/1PFtYHvQbMNWWB5XBmSRXHRayys8IocNQ/edit?usp=sharing&amp;ouid=111595575091640024737&amp;rtpof=true&amp;sd=true" target="_blank" rel="noopener" class="neo-btn neo-btn-sm no-link">Download</a>
        </div>
        <div class="neo-card template-card">
            <div><strong>Proposal Kegiatan</strong><br><small class="text-muted">PDF - Google Drive</small></div>
            <a href="https://docs.google.com/document/d/1lA7F4G9zbpfWJf6RJt1OwNJwjXLG9Hjz/edit?usp=sharing&amp;ouid=111595575091640024737&amp;rtpof=true&amp;sd=true" target="_blank" rel="noopener" class="neo-btn neo-btn-primary neo-btn-sm no-link">Download</a>
        </div>
        <div class="neo-card template-card">
            <div><strong>Laporan Pertanggungjawabaan</strong><br><small class="text-muted">PDF - Google Drive</small></div>
            <a href="https://docs.google.com/document/d/1ErsvFPFo6FE81gvepdwshjifgrs5CmYu/edit?usp=sharing&amp;ouid=111595575091640024737&amp;rtpof=true&amp;sd=true" target="_blank" rel="noopener" class="neo-btn neo-btn-success neo-btn-sm no-link">Download</a>
        </div>
    </div>
</div>

<form method="GET" class="filter-bar" role="search" aria-label="Cari dan filter surat">
    <div class="filter-field filter-field--grow">
        <label for="sq">Cari tentang / tujuan / nomor</label>
        <input type="text" id="sq" name="q" class="neo-input" value="<?= e($s_q) ?>" placeholder="Contoh: proposal / 001/...">
    </div>
    <div class="filter-field">
        <label for="sstatus">Status</label>
        <select id="sstatus" name="status" class="neo-input">
            <option value="">Semua status</option>
            <?php foreach (['Pending', 'Revisi', 'Diterima', 'Ditolak'] as $st): ?>
                <option value="<?= $st ?>" <?= $s_status === $st ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($isBPH): ?>
    <div class="filter-field">
        <label for="sdivisi">Divisi</label>
        <select id="sdivisi" name="divisi" class="neo-input">
            <option value="">Semua divisi</option>
            <?php foreach ($semuaDivisiSurat as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $s_divisi == $d['id'] ? 'selected' : '' ?>><?= e($d['nama_divisi']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="filter-field">
        <label for="sdari">Diajukan dari</label>
        <input type="date" id="sdari" name="dari" class="neo-input" value="<?= e($s_dari) ?>">
    </div>
    <div class="filter-field">
        <label for="ssampai">Sampai</label>
        <input type="date" id="ssampai" name="sampai" class="neo-input" value="<?= e($s_sampai) ?>">
    </div>
    <div class="filter-actions">
        <button type="submit" class="neo-btn neo-btn-primary neo-btn-sm"><i class="fas fa-search"></i> Cari</button>
        <?php if ($surat_filter_aktif): ?><a href="index.php" class="neo-btn neo-btn-muted neo-btn-sm">Reset</a><?php endif; ?>
    </div>
</form>
<?php if ($surat_filter_aktif): ?><p class="filter-active-note"><?= (int) $total_surat_rows ?> surat cocok dengan filter. <a href="index.php">Tampilkan semua</a></p><?php endif; ?>

<div class="neo-card table-card">
    <table class="data-table data-table--cyan">
        <thead>
            <tr>
                <th>Pengaju</th>
                <th>Tentang</th>
                <th>Divisi</th>
                <th>No. Surat</th>
                <th>Status</th>
                <th scope="col">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($daftar_surat)): ?>
                <tr><td colspan="6" class="table-empty table-empty-large">Belum ada pengajuan surat.</td></tr>
            <?php else: ?>
                <?php foreach ($daftar_surat as $s): ?>
                    <tr>
                        <td><strong><?= e($s['nama_lengkap'] ?? 'Pengguna telah dihapus') ?></strong></td>
                        <td><?= e($s['tentang']) ?></td>
                        <td><?= $s['nama_divisi'] ? e($s['nama_divisi']) : '-' ?></td>
                        <td><?= $s['nomor_surat'] ? '<strong>' . e($s['nomor_surat']) . '</strong>' : '-' ?></td>
                        <td><span class="neo-badge <?= $statusClass[$s['status']] ?>"><?= $s['status'] ?></span></td>
                        <td class="text-right"><a href="detail.php?id=<?= $s['id'] ?>" class="neo-btn neo-btn-sm no-link">Buka</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php renderPagination($surat_page, $surat_total_pages, $surat_filter_query); ?>

<?php require_once '../includes/footer.php'; ?>
