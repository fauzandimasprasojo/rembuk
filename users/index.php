<?php
require_once '../includes/init.php';
requireRole(['BPH']); // Kelola user & hak akses khusus BPH.

// Ingat filter & halaman terakhir (GET) supaya redirect sukses kembali ke
// tampilan yang sama, bukan ke daftar polos.
rememberListState('users');

$error_msg = '';
$semuaDivisi = $pdo->query("SELECT id, nama_divisi FROM divisi ORDER BY nama_divisi")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'update_role') {
        $target_user_id = (int) ($_POST['user_id'] ?? 0);
        $new_role = $_POST['role'];
        $new_divisi = $_POST['divisi_id'] ?: null;
        $allowed_roles = ['BPH', 'Koordinator Divisi', 'Anggota'];

        $stmt_cur = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
        $stmt_cur->execute([$target_user_id]);
        $current = $stmt_cur->fetch();

        // Proteksi: jangan sampai BPH aktif terakhir diturunkan rolenya sendiri,
        // karena organisasi bisa "terkunci" tanpa ada yang bisa kelola user/divisi lagi.
        $akanKehilanganBphTerakhir = $current
            && $current['role'] === 'BPH' && $current['status'] === 'aktif'
            && $new_role !== 'BPH'
            && countActiveBphExcluding($pdo, $target_user_id) === 0;

        if (!$current || !in_array($new_role, $allowed_roles)) {
            // data tidak valid (form select sudah dibatasi pilihannya, ini pengaman server-side)
            flash('error', 'Data pengguna tidak valid, perubahan dibatalkan.');
            header("Location: " . redirectList('index.php', 'users'));
            exit;
        } elseif ($akanKehilanganBphTerakhir) {
            $error_msg = "Tidak bisa mengubah role: ini satu-satunya akun BPH yang masih aktif. Jadikan user lain BPH dulu sebelum menurunkan role ini.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET role = ?, divisi_id = ? WHERE id = ?");
            $stmt->execute([$new_role, $new_divisi, $target_user_id]);

            if ($target_user_id == $_SESSION['user_id']) {
                $_SESSION['role'] = $new_role;
                $_SESSION['divisi_id'] = $new_divisi;
            }
            flash('success', 'Role & divisi pengguna berhasil diperbarui.');
            header("Location: " . redirectList('index.php', 'users'));
            exit;
        }
    }

    // Dulu di sini ada hard-delete user (DELETE FROM users). Diganti nonaktifkan/aktifkan:
    // menghapus akun sungguhan akan ikut menghapus/merusak riwayat proker, kas, surat, dan
    // rapat yang pernah dibuat user tsb. Nonaktifkan cukup mengunci login-nya, datanya aman.
    if (($_POST['action'] ?? '') === 'toggle_status') {
        $target_user_id = (int) ($_POST['user_id'] ?? 0);

        if ($target_user_id === (int) $_SESSION['user_id']) {
            $error_msg = "Kamu tidak bisa menonaktifkan akunmu sendiri saat sedang login!";
        } else {
            $stmt_cur = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
            $stmt_cur->execute([$target_user_id]);
            $target = $stmt_cur->fetch();

            if (!$target) {
                $error_msg = "User tidak ditemukan.";
            } else {
                $statusBaru = $target['status'] === 'aktif' ? 'nonaktif' : 'aktif';

                // Proteksi yang sama: jangan sampai BPH aktif terakhir dinonaktifkan.
                if ($statusBaru === 'nonaktif' && $target['role'] === 'BPH' && countActiveBphExcluding($pdo, $target_user_id) === 0) {
                    $error_msg = "Tidak bisa menonaktifkan: ini satu-satunya akun BPH yang masih aktif. Jadikan user lain BPH dulu.";
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                    $stmt->execute([$statusBaru, $target_user_id]);
                    flash('success', $statusBaru === 'aktif' ? 'Akun pengguna berhasil diaktifkan kembali.' : 'Akun pengguna berhasil dinonaktifkan.');
                    header("Location: " . redirectList('index.php', 'users'));
                    exit;
                }
            }
        }
    }
}

$total_user_rows = 0;
$user_page = 1; $user_total_pages = 1; $user_offset = 0;
$users = [];

// Pencarian & filter (GET): q (nama/username), role, divisi_id, status.
// Dipakai bareng pagination — extra_query membawa filter ke semua link halaman.
$f_q = trim($_GET['q'] ?? '');
$f_role = $_GET['role'] ?? '';
$f_divisi = $_GET['divisi'] ?? '';
$f_status = $_GET['status'] ?? '';
$allowed_roles_filter = ['BPH', 'Koordinator Divisi', 'Anggota'];
if (!in_array($f_role, $allowed_roles_filter, true)) $f_role = '';
if (!in_array($f_status, ['aktif', 'nonaktif'], true)) $f_status = '';
$f_divisi_id = ctype_digit((string) $f_divisi) ? (int) $f_divisi : 0;

$user_where = [];
$user_params = [];
if ($f_q !== '') { $user_where[] = "(u.nama_lengkap LIKE ? ESCAPE '\\\\' OR u.username LIKE ? ESCAPE '\\\\')"; $user_params[] = "%" . escapeLike($f_q) . "%"; $user_params[] = "%" . escapeLike($f_q) . "%"; }
if ($f_role !== '') { $user_where[] = "u.role = ?"; $user_params[] = $f_role; }
if ($f_divisi_id) { $user_where[] = "u.divisi_id = ?"; $user_params[] = $f_divisi_id; }
if ($f_status !== '') { $user_where[] = "u.status = ?"; $user_params[] = $f_status; }
$user_where_sql = $user_where ? ' WHERE ' . implode(' AND ', $user_where) : '';

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM users u" . $user_where_sql);
$stmt_count->execute($user_params);
$total_user_rows = (int) $stmt_count->fetchColumn();
[$user_page, $user_total_pages, $user_offset] = paginateParams($total_user_rows);

$stmt_users = $pdo->prepare(
    "SELECT u.id, u.nama_lengkap, u.username, u.role, u.divisi_id, u.status, d.nama_divisi
     FROM users u LEFT JOIN divisi d ON d.id = u.divisi_id" . $user_where_sql . " ORDER BY u.nama_lengkap ASC
     LIMIT ? OFFSET ?"
);
// Semua parameter posisional (filter + pagination) supaya tidak tercampur dengan named parameter.
foreach ($user_params as $i => $v) { $stmt_users->bindValue($i + 1, $v); }
$stmt_users->bindValue(count($user_params) + 1, PAGINATE_PER_PAGE, PDO::PARAM_INT);
$stmt_users->bindValue(count($user_params) + 2, $user_offset, PDO::PARAM_INT);
$stmt_users->execute();
$users = $stmt_users->fetchAll();

// Query string filter untuk dibawa pagination & tombol reset.
$filter_query = '';
if ($f_q !== '') $filter_query .= '&q=' . urlencode($f_q);
if ($f_role !== '') $filter_query .= '&role=' . urlencode($f_role);
if ($f_divisi_id) $filter_query .= '&divisi=' . $f_divisi_id;
if ($f_status !== '') $filter_query .= '&status=' . urlencode($f_status);
$filter_aktif = ($f_q !== '' || $f_role !== '' || $f_divisi_id || $f_status !== '');

$pageTitle = 'Kelola User';
require_once '../includes/header.php';
?>
<div class="flex-between page-header">
    <h2 class="page-title">Pengelolaan User &amp; Hak Akses</h2>
    <span class="neo-badge access-badge"><i class="fas fa-user-shield" aria-hidden="true"></i> Akses Khusus BPH</span>
</div>

<?php if ($error_msg): ?><div class="neo-alert neo-alert-error" role="alert"><?= e($error_msg) ?></div><?php endif; ?>
<?php displayFlash(); ?>

<form method="GET" class="filter-bar" role="search" aria-label="Cari dan filter pengguna">
    <div class="filter-field filter-field--grow">
        <label for="fq">Cari nama / username</label>
        <input type="text" id="fq" name="q" class="neo-input" value="<?= e($f_q) ?>" placeholder="Contoh: marva / anggota...">
    </div>
    <div class="filter-field">
        <label for="frole">Role</label>
        <select id="frole" name="role" class="neo-input">
            <option value="">Semua role</option>
            <?php foreach (['BPH', 'Koordinator Divisi', 'Anggota'] as $r): ?>
                <option value="<?= e($r) ?>" <?= $f_role === $r ? 'selected' : '' ?>><?= e($r) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="fdivisi">Divisi</label>
        <select id="fdivisi" name="divisi" class="neo-input">
            <option value="">Semua divisi</option>
            <?php foreach ($semuaDivisi as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $f_divisi_id == $d['id'] ? 'selected' : '' ?>><?= e($d['nama_divisi']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="fstatus">Status</label>
        <select id="fstatus" name="status" class="neo-input">
            <option value="">Aktif + Nonaktif</option>
            <option value="aktif" <?= $f_status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
            <option value="nonaktif" <?= $f_status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
        </select>
    </div>
    <div class="filter-actions">
        <button type="submit" class="neo-btn neo-btn-primary neo-btn-sm"><i class="fas fa-search"></i> Cari</button>
        <?php if ($filter_aktif): ?><a href="index.php" class="neo-btn neo-btn-muted neo-btn-sm">Reset</a><?php endif; ?>
    </div>
</form>
<?php if ($filter_aktif): ?><p class="filter-active-note"><?= (int) $total_user_rows ?> pengguna cocok dengan filter. <a href="index.php">Tampilkan semua</a></p><?php endif; ?>

<div class="neo-card table-card table-card--stackable">
    <table class="user-table" aria-label="Daftar pengguna dan hak akses">
        <thead>
            <tr>
                <th scope="col">Nama Lengkap</th>
                <th scope="col">Username</th>
                <th scope="col">Divisi</th>
                <th scope="col">Role</th>
                <th scope="col">Status</th>
                <th scope="col">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td data-label="Nama">
                        <?= e($u['nama_lengkap']) ?>
                        <?php if ($u['id'] == $_SESSION['user_id']): ?><span class="self-badge">Kamu</span><?php endif; ?>
                    </td>
                    <td data-label="Username"><?= e($u['username']) ?></td>
                    <td data-label="Divisi & Role">
                        <form method="POST" class="user-assignment-form">
            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_role">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <select name="divisi_id" class="neo-input user-select" aria-label="Divisi untuk pengguna <?= e($u['username']) ?>">
                                <option value="">-- tanpa divisi --</option>
                                <?php foreach ($semuaDivisi as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= $d['id'] == $u['divisi_id'] ? 'selected' : '' ?>><?= e($d['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="role" class="neo-input user-select user-select--role user-select--role-<?= $u['role'] == 'BPH' ? 'bph' : ($u['role'] == 'Koordinator Divisi' ? 'coordinator' : 'member') ?>" aria-label="Role untuk pengguna <?= e($u['username']) ?>">
                                <option value="BPH" <?= $u['role'] == 'BPH' ? 'selected' : '' ?>>BPH</option>
                                <option value="Koordinator Divisi" <?= $u['role'] == 'Koordinator Divisi' ? 'selected' : '' ?>>Koordinator Divisi</option>
                                <option value="Anggota" <?= $u['role'] == 'Anggota' ? 'selected' : '' ?>>Anggota</option>
                            </select>
                            <button type="submit" class="neo-btn neo-btn-sm user-save-button" aria-label="Simpan perubahan akses untuk <?= e($u['username']) ?>">Simpan</button>
                        </form>
                    </td>
                    <td data-label="Role"><span class="neo-badge user-role-badge"><?= e($u['role']) ?></span></td>
                    <td data-label="Status">
                        <span class="neo-badge <?= $u['status'] === 'aktif' ? 'status-diterima' : 'status-ditolak' ?>">
                            <?= $u['status'] === 'aktif' ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <td data-label="Aksi">
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" class="user-delete-form" data-confirm="<?= $u['status'] === 'aktif' ? 'Nonaktifkan akun ini? User tidak akan bisa login sampai diaktifkan lagi.' : 'Aktifkan kembali akun ini?' ?>">
            <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <?php if ($u['status'] === 'aktif'): ?>
                                    <button type="submit" class="neo-btn neo-btn-danger neo-btn-sm" aria-label="Nonaktifkan akun <?= e($u['username']) ?>"><i class="fas fa-user-slash" aria-hidden="true"></i></button>
                                <?php else: ?>
                                    <button type="submit" class="neo-btn neo-btn-success neo-btn-sm" aria-label="Aktifkan akun <?= e($u['username']) ?>"><i class="fas fa-user-check" aria-hidden="true"></i></button>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <span class="user-current-placeholder">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php renderPagination($user_page, $user_total_pages, $filter_query); ?>
<?php require_once '../includes/footer.php'; ?>
