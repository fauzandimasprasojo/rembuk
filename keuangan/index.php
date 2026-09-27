<?php
require_once '../includes/init.php';
requireLogin(); // semua bisa lihat rekap (transparansi kas), hanya BPH yang kelola transaksi

// Ingat filter & halaman terakhir (GET) supaya redirect sukses kembali ke
// tampilan yang sama, bukan ke daftar polos.
rememberListState('keuangan');

$can_manage_kas = $_SESSION['role'] === 'BPH';
$error_msg = '';

// Riwayat perubahan kas (audit log): siapa tambah/ubah/hapus apa dan kapan.
// Tabel keuangan_log dibuat lewat migration_keuangan_log.sql. Kalau tabel belum
// ada (misal migrasi belum dijalankan), fungsi ini diam-diam tidak mencatat
// supaya transaksi utama tidak ikut gagal.
function catatKeuanganLog(PDO $pdo, string $aksi, ?int $keuangan_id, ?array $data_lama, ?array $data_baru): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO keuangan_log (keuangan_id, aksi, keterangan, jumlah_lama, jumlah_baru, data_lama, data_baru, oleh_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $keuangan_id,
            $aksi,
            ($data_baru ?? $data_lama)['keterangan'] ?? null,
            $data_lama['jumlah'] ?? null,
            $data_baru['jumlah'] ?? null,
            $data_lama ? json_encode($data_lama, JSON_UNESCAPED_UNICODE) : null,
            $data_baru ? json_encode($data_baru, JSON_UNESCAPED_UNICODE) : null,
            $_SESSION['user_id'] ?? null,
        ]);
    } catch (Throwable $e) { /* tabel belum ada — abaikan */ }
}

function snapshotKeuangan(PDO $pdo, int $id): ?array {
    try {
        $stmt = $pdo->prepare("SELECT id, tanggal, keterangan, jenis, jumlah, proker_id FROM keuangan WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

// Upload bukti transaksi (opsional): gambar atau PDF, maks 5MB, nama file diacak.
function uploadBuktiKas(array $file, ?string &$error)
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // Tidak upload apa-apa, bukan error.
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Gagal mengunggah bukti transaksi.";
        return null;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        $error = "Ukuran bukti transaksi maksimal 5MB.";
        return null;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) {
        $error = "Format bukti transaksi harus JPG, PNG, WEBP, atau PDF.";
        return null;
    }
    $uploadDir = '../assets/uploads/bukti/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $newFileName = time() . '_' . uniqid() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
        $error = "Gagal memindahkan file bukti ke folder uploads.";
        return null;
    }
    return $newFileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'add_kas' && $can_manage_kas) {
        $tanggal = $_POST['tanggal'];
        $keterangan = trim($_POST['keterangan']);
        $jenis = $_POST['jenis'];
        $jumlah = floatval($_POST['jumlah']);
        $proker_id = $_POST['proker_id'] ?: null;

        // Aturan: Pengeluaran WAJIB terhubung ke Proker (akuntabilitas kas).
        if ($jenis === 'Pengeluaran' && !$proker_id) {
            $error_msg = "Transaksi Pengeluaran wajib dihubungkan dengan Program Kerja terkait.";
        } elseif (!empty($tanggal) && !empty($keterangan) && $jumlah > 0) {
            $bukti_file = isset($_FILES['bukti']) ? uploadBuktiKas($_FILES['bukti'], $error_msg) : null;
            if (empty($error_msg)) {
                $stmt = $pdo->prepare("INSERT INTO keuangan (tanggal, keterangan, jenis, jumlah, proker_id, bukti_file, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tanggal, $keterangan, $jenis, $jumlah, $proker_id, $bukti_file, $_SESSION['user_id']]);
                $new_id = (int) $pdo->lastInsertId();
                catatKeuanganLog($pdo, 'tambah', $new_id, null, ['tanggal' => $tanggal, 'keterangan' => $keterangan, 'jenis' => $jenis, 'jumlah' => $jumlah, 'proker_id' => $proker_id]);
                flash('success', 'Transaksi kas berhasil disimpan.');
            }
        }
    }

    if (($_POST['action'] ?? '') === 'edit_kas' && $can_manage_kas) {
        $kas_id = (int) ($_POST['kas_id'] ?? 0);
        $tanggal = $_POST['tanggal'];
        $keterangan = trim($_POST['keterangan']);
        $jenis = $_POST['jenis'];
        $jumlah = floatval($_POST['jumlah']);
        $proker_id = $_POST['proker_id'] ?: null;

        // Aturan sama seperti tambah: Pengeluaran WAJIB terhubung ke Proker
        if ($jenis === 'Pengeluaran' && !$proker_id) {
            $error_msg = "Transaksi Pengeluaran wajib dihubungkan dengan Program Kerja terkait.";
        } elseif ($kas_id && !empty($tanggal) && !empty($keterangan) && $jumlah > 0) {
            $data_lama = snapshotKeuangan($pdo, $kas_id);
            $stmt_lama = $pdo->prepare("SELECT bukti_file FROM keuangan WHERE id = ?");
            $stmt_lama->execute([$kas_id]);
            $bukti_lama = $stmt_lama->fetchColumn();
            $bukti_file = $bukti_lama;

            $bukti_baru = isset($_FILES['bukti']) ? uploadBuktiKas($_FILES['bukti'], $error_msg) : null;
            if (empty($error_msg)) {
                if ($bukti_baru) {
                    if ($bukti_lama && file_exists('../assets/uploads/bukti/' . $bukti_lama)) {
                        unlink('../assets/uploads/bukti/' . $bukti_lama);
                    }
                    $bukti_file = $bukti_baru;
                } elseif (!empty($_POST['hapus_bukti']) && $bukti_lama) {
                    if (file_exists('../assets/uploads/bukti/' . $bukti_lama)) {
                        unlink('../assets/uploads/bukti/' . $bukti_lama);
                    }
                    $bukti_file = null;
                }

                $stmt = $pdo->prepare("UPDATE keuangan SET tanggal = ?, keterangan = ?, jenis = ?, jumlah = ?, proker_id = ?, bukti_file = ? WHERE id = ?");
                $stmt->execute([$tanggal, $keterangan, $jenis, $jumlah, $proker_id, $bukti_file, $kas_id]);
                catatKeuanganLog($pdo, 'ubah', $kas_id, $data_lama, ['tanggal' => $tanggal, 'keterangan' => $keterangan, 'jenis' => $jenis, 'jumlah' => $jumlah, 'proker_id' => $proker_id]);
                flash('success', 'Perubahan transaksi kas berhasil disimpan.');
            }
        } else {
            $error_msg = "Semua bidang wajib diisi!";
        }
    }

    if (($_POST['action'] ?? '') === 'delete_kas' && $can_manage_kas) {
        $kas_hapus_id = (int) ($_POST['kas_id'] ?? 0);
        $data_lama = $kas_hapus_id ? snapshotKeuangan($pdo, $kas_hapus_id) : null;
        $stmt_get = $pdo->prepare("SELECT bukti_file FROM keuangan WHERE id = ?");
        $stmt_get->execute([$_POST['kas_id']]);
        $bukti_hapus = $stmt_get->fetchColumn();
        if ($bukti_hapus && file_exists('../assets/uploads/bukti/' . $bukti_hapus)) {
            unlink('../assets/uploads/bukti/' . $bukti_hapus);
        }
        $stmt = $pdo->prepare("DELETE FROM keuangan WHERE id = ?");
        $stmt->execute([$_POST['kas_id']]);
        if ($data_lama) catatKeuanganLog($pdo, 'hapus', null, $data_lama, null);
        flash('success', 'Pencatatan kas berhasil dihapus.');
    }

    if (empty($error_msg)) { header("Location: " . redirectList('index.php', 'keuangan')); exit; }
}

$semuaProker = $pdo->query("SELECT id, nama_proker FROM proker ORDER BY nama_proker")->fetchAll();

$pageTitle = 'Keuangan';
require_once '../includes/header.php';

// Pencarian & filter (GET): q (keterangan), jenis, proker_id, dari/sampai (rentang tanggal).
// Ringkasan saldo di bawah otomatis mengikuti filter yang aktif.
$k_q = trim($_GET['q'] ?? '');
$k_jenis = in_array($_GET['jenis'] ?? '', ['Pemasukan', 'Pengeluaran'], true) ? $_GET['jenis'] : '';
$k_proker = ctype_digit((string) ($_GET['proker'] ?? '')) ? (int) $_GET['proker'] : 0;
$k_dari = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari'] ?? '') ? $_GET['dari'] : '';
$k_sampai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai'] ?? '') ? $_GET['sampai'] : '';

$kas_where = [];
$kas_params = [];
if ($k_q !== '') { $kas_where[] = "k.keterangan LIKE ? ESCAPE '\\\\'"; $kas_params[] = "%" . escapeLike($k_q) . "%"; }
if ($k_jenis !== '') { $kas_where[] = "k.jenis = ?"; $kas_params[] = $k_jenis; }
if ($k_proker) { $kas_where[] = "k.proker_id = ?"; $kas_params[] = $k_proker; }
if ($k_dari !== '') { $kas_where[] = "k.tanggal >= ?"; $kas_params[] = $k_dari; }
if ($k_sampai !== '') { $kas_where[] = "k.tanggal <= ?"; $kas_params[] = $k_sampai; }
$kas_where_sql = $kas_where ? ' WHERE ' . implode(' AND ', $kas_where) : '';

$stmt_sum = $pdo->prepare(
    "SELECT SUM(CASE WHEN k.jenis = 'Pemasukan' THEN k.jumlah ELSE 0 END) AS masuk,
            SUM(CASE WHEN k.jenis = 'Pengeluaran' THEN k.jumlah ELSE 0 END) AS keluar
     FROM keuangan k" . $kas_where_sql
);
$stmt_sum->execute($kas_params);
$sum = $stmt_sum->fetch();
$total_pemasukan = (float) ($sum['masuk'] ?? 0);
$total_pengeluaran = (float) ($sum['keluar'] ?? 0);
$saldo_akhir = $total_pemasukan - $total_pengeluaran;

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM keuangan k" . $kas_where_sql);
$stmt_count->execute($kas_params);
$total_kas_rows = (int) $stmt_count->fetchColumn();
[$kas_page, $kas_total_pages, $kas_offset] = paginateParams($total_kas_rows);

$stmt_kas = $pdo->prepare(
    "SELECT k.*, u.nama_lengkap, p.nama_proker FROM keuangan k
     LEFT JOIN users u ON k.created_by = u.id
     LEFT JOIN proker p ON k.proker_id = p.id"
    . $kas_where_sql .
    " ORDER BY k.tanggal DESC, k.id DESC
     LIMIT ? OFFSET ?"
);
// Semua parameter posisional (filter + pagination) supaya tidak tercampur dengan named parameter.
foreach ($kas_params as $i => $v) { $stmt_kas->bindValue($i + 1, $v); }
$stmt_kas->bindValue(count($kas_params) + 1, PAGINATE_PER_PAGE, PDO::PARAM_INT);
$stmt_kas->bindValue(count($kas_params) + 2, $kas_offset, PDO::PARAM_INT);
$stmt_kas->execute();
$kas_list = $stmt_kas->fetchAll();

$kas_filter_query = '';
if ($k_q !== '') $kas_filter_query .= '&q=' . urlencode($k_q);
if ($k_jenis !== '') $kas_filter_query .= '&jenis=' . urlencode($k_jenis);
if ($k_proker) $kas_filter_query .= '&proker=' . $k_proker;
if ($k_dari !== '') $kas_filter_query .= '&dari=' . urlencode($k_dari);
if ($k_sampai !== '') $kas_filter_query .= '&sampai=' . urlencode($k_sampai);
$kas_filter_aktif = ($k_q !== '' || $k_jenis !== '' || $k_proker || $k_dari !== '' || $k_sampai !== '');

// Riwayat perubahan kas (20 terakhir) untuk transparansi: siapa ubah apa & kapan.
$kas_logs = [];
try {
    $kas_logs = $pdo->query(
        "SELECT l.*, u.nama_lengkap AS pelaku FROM keuangan_log l
         LEFT JOIN users u ON u.id = l.oleh_user_id
         ORDER BY l.created_at DESC LIMIT 20"
    )->fetchAll();
} catch (Throwable $e) { $kas_logs = []; }

// Tren kas 6 bulan terakhir untuk bar chart (dibaca app.js via atribut data-* pada <canvas>).
$tren_raw = $pdo->query(
    "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS bulan,
            SUM(CASE WHEN jenis = 'Pemasukan' THEN jumlah ELSE 0 END) AS pemasukan,
            SUM(CASE WHEN jenis = 'Pengeluaran' THEN jumlah ELSE 0 END) AS pengeluaran
     FROM keuangan
     WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
     GROUP BY bulan ORDER BY bulan ASC"
)->fetchAll(PDO::FETCH_ASSOC);
$tren_raw = array_column($tren_raw, null, 'bulan');

$namaBulan = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];
$tren_labels = [];
$tren_pemasukan = [];
$tren_pengeluaran = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i month"));
    $tren_labels[] = $namaBulan[substr($key, 5, 2)] . ' ' . substr($key, 0, 4);
    $tren_pemasukan[] = (float) ($tren_raw[$key]['pemasukan'] ?? 0);
    $tren_pengeluaran[] = (float) ($tren_raw[$key]['pengeluaran'] ?? 0);
}
?>

<div class="flex-between page-header">
    <h2 class="page-title">Kebendaharaan / Kas Organisasi</h2>
    <?php if ($can_manage_kas): ?>
        <button type="button" class="neo-btn neo-btn-success" data-modal-open="modalAddKas"><i class="fas fa-plus"></i> Catat Transaksi</button>
    <?php endif; ?>
</div>

<?php if (!empty($error_msg)): ?><div class="neo-alert neo-alert-error"><?= e($error_msg) ?></div><?php endif; ?>
<?php displayFlash(); ?>

<div class="grid-auto finance-summary-grid">
    <div class="neo-card finance-summary-card--income">
        <small class="text-bold text-uppercase">Total Pemasukan</small>
        <h2 class="finance-summary-value">Rp <?= number_format($total_pemasukan, 0, ',', '.') ?></h2>
    </div>
    <div class="neo-card finance-summary-card--expense">
        <small class="text-bold text-uppercase">Total Pengeluaran</small>
        <h2 class="finance-summary-value">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></h2>
    </div>
    <div class="neo-card finance-summary-card--balance">
        <small class="text-bold text-uppercase">Saldo Akhir Kas</small>
        <h2 class="finance-summary-value">Rp <?= number_format($saldo_akhir, 0, ',', '.') ?></h2>
    </div>
</div>

<div class="neo-card finance-trend-card">
    <h3 class="section-title"><i class="fas fa-chart-column"></i> Tren Kas 6 Bulan Terakhir</h3>
    <div class="finance-trend-chart">
        <canvas id="kasTrendChart" data-chart="kas-trend"
            data-labels='<?= e(json_encode($tren_labels)) ?>'
            data-pemasukan='<?= e(json_encode($tren_pemasukan)) ?>'
            data-pengeluaran='<?= e(json_encode($tren_pengeluaran)) ?>'></canvas>
    </div>
</div>

<form method="GET" class="filter-bar" role="search" aria-label="Cari dan filter transaksi kas">
    <div class="filter-bar-title"><i class="fas fa-filter" aria-hidden="true"></i> Cari &amp; Filter Transaksi</div>
    <div class="filter-field filter-field--grow">
        <label for="kq">Cari keterangan</label>
        <input type="text" id="kq" name="q" class="neo-input" value="<?= e($k_q) ?>" placeholder="Contoh: konsumsi / kas bulanan...">
    </div>
    <div class="filter-field">
        <label for="kjenis">Jenis</label>
        <select id="kjenis" name="jenis" class="neo-input">
            <option value="">Semua jenis</option>
            <option value="Pemasukan" <?= $k_jenis === 'Pemasukan' ? 'selected' : '' ?>>Pemasukan</option>
            <option value="Pengeluaran" <?= $k_jenis === 'Pengeluaran' ? 'selected' : '' ?>>Pengeluaran</option>
        </select>
    </div>
    <div class="filter-field">
        <label for="kproker">Proker</label>
        <select id="kproker" name="proker" class="neo-input">
            <option value="">Semua proker</option>
            <?php foreach ($semuaProker as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $k_proker == $p['id'] ? 'selected' : '' ?>><?= e($p['nama_proker']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="kdari">Dari tanggal</label>
        <input type="date" id="kdari" name="dari" class="neo-input" value="<?= e($k_dari) ?>">
    </div>
    <div class="filter-field">
        <label for="ksampai">Sampai tanggal</label>
        <input type="date" id="ksampai" name="sampai" class="neo-input" value="<?= e($k_sampai) ?>">
    </div>
    <div class="filter-actions">
        <button type="submit" class="neo-btn neo-btn-primary neo-btn-sm"><i class="fas fa-search"></i> Cari</button>
        <?php if ($kas_filter_aktif): ?><a href="index.php" class="neo-btn neo-btn-muted neo-btn-sm">Reset</a><?php endif; ?>
    </div>
</form>
<?php if ($kas_filter_aktif): ?><p class="filter-active-note"><?= (int) $total_kas_rows ?> transaksi cocok — ringkasan saldo di atas mengikuti filter. <a href="index.php">Tampilkan semua</a></p><?php endif; ?>

<div class="neo-card table-card table-card--stackable">
    <table class="data-table">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Keterangan</th>
                <th>Proker Terkait</th>
                <th>Jenis</th>
                <th>Jumlah</th>
                <th>Pencatat</th>
                <th class="table-action">Bukti</th>
                <?php if ($can_manage_kas): ?><th class="table-action">Aksi</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($kas_list)): ?>
                <tr><td colspan="8" class="table-empty">Belum ada catatan transaksi kas.</td></tr>
            <?php else: ?>
                <?php foreach ($kas_list as $k): ?>
                    <tr>
                        <td data-label="Tanggal"><strong><?= tanggalIndo($k['tanggal']) ?></strong></td>
                        <td data-label="Keterangan"><strong><?= e($k['keterangan']) ?></strong></td>
                        <td data-label="Proker"><?= $k['nama_proker'] ? e($k['nama_proker']) : '<span class="text-subtle">Kas umum</span>' ?></td>
                        <td data-label="Jenis">
                            <span class="neo-badge status-<?= $k['jenis'] == 'Pemasukan' ? 'income' : 'expense' ?>"><?= $k['jenis'] ?></span>
                        </td>
                        <td data-label="Jumlah"><strong>Rp <?= number_format($k['jumlah'], 0, ',', '.') ?></strong></td>
                        <td data-label="Pencatat"><?= e($k['nama_lengkap'] ?? 'Sistem') ?></td>
                        <td class="table-action" data-label="Bukti">
                            <?php if ($k['bukti_file']): ?>
                                <a href="<?= BASE_URL ?>/assets/uploads/bukti/<?= e($k['bukti_file']) ?>" target="_blank" rel="noopener" class="neo-btn neo-btn-outline neo-btn-sm" title="Lihat bukti transaksi"><i class="fas fa-receipt"></i></a>
                            <?php else: ?>
                                <span class="text-subtle">-</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($can_manage_kas): ?>
                        <td class="table-action" data-label="Aksi">
                            <button type="button" class="neo-btn neo-btn-warning neo-btn-sm"
                                data-modal="edit-kas"
                                data-id="<?= $k['id'] ?>"
                                data-tanggal="<?= e($k['tanggal']) ?>"
                                data-jenis="<?= e($k['jenis']) ?>"
                                data-proker="<?= $k['proker_id'] ?? '' ?>"
                                data-keterangan="<?= e($k['keterangan']) ?>"
                                data-jumlah="<?= e((string)$k['jumlah']) ?>"
                                data-bukti="<?= e($k['bukti_file'] ?? '') ?>"
                                title="Edit Transaksi">
                                <i class="fas fa-pen"></i>
                            </button>
                            <form method="POST" class="inline-form" data-confirm="Hapus pencatatan kas ini?">
            <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_kas">
                                <input type="hidden" name="kas_id" value="<?= $k['id'] ?>">
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
<?php renderPagination($kas_page, $kas_total_pages, $kas_filter_query); ?>

<div class="neo-card audit-log-card">
    <h3 class="section-title"><i class="fas fa-clock-rotate-left"></i> Riwayat Perubahan Kas</h3>
    <p class="text-subtle text-sm">Transparansi kas: siapa mencatat/mengubah/menghapus apa dan kapan. Menampilkan 20 aktivitas terakhir.</p>
    <?php if (empty($kas_logs)): ?>
        <p class="text-subtle">Belum ada riwayat perubahan tercatat. (Jalankan <code>migration_keuangan_log.sql</code> bila tabel belum ada.)</p>
    <?php else: ?>
        <div class="audit-log-list">
            <?php
            $aksi_label = ['tambah' => 'Mencatat', 'ubah' => 'Mengubah', 'hapus' => 'Menghapus'];
            $aksi_class = ['tambah' => 'audit-badge-tambah', 'ubah' => 'audit-badge-ubah', 'hapus' => 'audit-badge-hapus'];
            foreach ($kas_logs as $log):
                $detail = '';
                if ($log['aksi'] === 'ubah' && $log['jumlah_lama'] != $log['jumlah_baru']) {
                    $detail = 'Rp ' . number_format((float) $log['jumlah_lama'], 0, ',', '.') . ' → Rp ' . number_format((float) $log['jumlah_baru'], 0, ',', '.');
                } elseif (in_array($log['aksi'], ['tambah', 'hapus']) && $log['jumlah_baru'] !== null) {
                    $detail = 'Rp ' . number_format((float) ($log['aksi'] === 'tambah' ? $log['jumlah_baru'] : $log['jumlah_lama']), 0, ',', '.');
                } elseif ($log['aksi'] === 'hapus' && $log['jumlah_lama'] !== null) {
                    $detail = 'Rp ' . number_format((float) $log['jumlah_lama'], 0, ',', '.');
                }
            ?>
                <div class="audit-log-item">
                    <span class="neo-badge <?= $aksi_class[$log['aksi']] ?? '' ?>"><?= e($aksi_label[$log['aksi']] ?? $log['aksi']) ?></span>
                    <div>
                        <strong><?= e($log['keterangan'] ?? '(tanpa keterangan)') ?></strong>
                        <?php if ($detail): ?> <span><?= e($detail) ?></span><?php endif; ?>
                        <div class="audit-log-meta">oleh <?= e($log['pelaku'] ?? 'Pengguna telah dihapus') ?> · <?= tanggalIndo($log['created_at'], false, false, true) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($can_manage_kas): ?>
<div id="modalAddKas" class="neo-modal-overlay">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title">Catat Transaksi Kas</h3>
            <button type="button" class="modal-close" data-modal-close="modalAddKas" aria-label="Tutup modal">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_kas">
            <div class="form-group">
                <label class="neo-label">Tanggal Transaksi</label>
                <input type="date" name="tanggal" class="neo-input" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label class="neo-label">Jenis Transaksi</label>
                <select name="jenis" class="neo-input" required>
                    <option value="">-- pilih jenis --</option>
                    <option value="Pemasukan">Pemasukan (+)</option>
                    <option value="Pengeluaran">Pengeluaran (-)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="neo-label">Terhubung dengan Proker (wajib untuk Pengeluaran)</label>
                <select name="proker_id" class="neo-input">
                    <option value="">-- kas umum organisasi --</option>
                    <?php foreach ($semuaProker as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['nama_proker']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="neo-label">Keterangan / Keperluan</label>
                <input type="text" name="keterangan" class="neo-input" required placeholder="Contoh: Uang Kas Bulanan / Konsumsi Raker">
            </div>
            <div class="form-group form-group-lg">
                <label class="neo-label">Jumlah (Rp)</label>
                <input type="number" name="jumlah" class="neo-input" min="1" required placeholder="Contoh: 50000">
            </div>
            <div class="form-group">
                <label class="neo-label">Bukti Transaksi (opsional)</label>
                <input type="file" name="bukti" class="neo-input input-file" accept=".jpg,.jpeg,.png,.webp,.pdf">
                <small class="text-subtle">Foto nota/struk atau PDF, maksimal 5MB.</small>
            </div>
            <div class="form-actions">
                <button type="button" class="neo-btn neo-btn-muted" data-modal-close="modalAddKas">Batal</button>
                <button type="submit" class="neo-btn neo-btn-success">Simpan Transaksi</button>
            </div>
        </form>
    </div>
</div>

<div id="modalEditKas" class="neo-modal-overlay">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title">Edit Transaksi Kas</h3>
            <button type="button" class="modal-close" data-modal-close="modalEditKas" aria-label="Tutup modal">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_kas">
            <input type="hidden" name="kas_id" id="edit_kas_id">
            <div class="form-group">
                <label class="neo-label">Tanggal Transaksi</label>
                <input type="date" name="tanggal" id="edit_kas_tanggal" class="neo-input" required>
            </div>
            <div class="form-group">
                <label class="neo-label">Jenis Transaksi</label>
                <select name="jenis" id="edit_kas_jenis" class="neo-input" required>
                    <option value="">-- pilih jenis --</option>
                    <option value="Pemasukan">Pemasukan (+)</option>
                    <option value="Pengeluaran">Pengeluaran (-)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="neo-label">Terhubung dengan Proker (wajib untuk Pengeluaran)</label>
                <select name="proker_id" id="edit_kas_proker" class="neo-input">
                    <option value="">-- kas umum organisasi --</option>
                    <?php foreach ($semuaProker as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['nama_proker']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="neo-label">Keterangan / Keperluan</label>
                <input type="text" name="keterangan" id="edit_kas_keterangan" class="neo-input" required>
            </div>
            <div class="form-group form-group-lg">
                <label class="neo-label">Jumlah (Rp)</label>
                <input type="number" name="jumlah" id="edit_kas_jumlah" class="neo-input" min="1" required>
            </div>
            <div class="form-group">
                <label class="neo-label">Bukti Transaksi</label>
                <p id="edit_kas_bukti_current" class="text-subtle"></p>
                <input type="file" name="bukti" class="neo-input input-file" accept=".jpg,.jpeg,.png,.webp,.pdf">
                <small class="text-subtle">Kosongkan kalau tidak ingin mengganti bukti. Foto/PDF, maksimal 5MB.</small>
                <label class="neo-checkbox-label">
                    <input type="checkbox" name="hapus_bukti" id="edit_kas_hapus_bukti" value="1"> Hapus bukti yang ada
                </label>
            </div>
            <div class="form-actions">
                <button type="button" class="neo-btn neo-btn-muted" data-modal-close="modalEditKas">Batal</button>
                <button type="submit" class="neo-btn neo-btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
