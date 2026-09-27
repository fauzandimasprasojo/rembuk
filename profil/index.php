<?php
require_once '../includes/init.php';
requireLogin();

$error_msg = '';
$success_msg = '';

// Ambil data akun sendiri langsung dari DB (bukan dari session) supaya selalu terbaru.
$stmt = $pdo->prepare(
    "SELECT u.*, d.nama_divisi FROM users u LEFT JOIN divisi d ON d.id = u.divisi_id WHERE u.id = ?"
);
$stmt->execute([$_SESSION['user_id']]);
$akun = $stmt->fetch();
if (!$akun) { header("Location: " . BASE_URL . "/auth/logout.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'ubah_password') {
        $password_lama = $_POST['password_lama'] ?? '';
        $password_baru = $_POST['password_baru'] ?? '';
        $password_konfirmasi = $_POST['password_konfirmasi'] ?? '';

        if (!password_verify($password_lama, $akun['password'])) {
            $error_msg = "Password lama yang kamu masukkan salah!";
        } elseif (strlen($password_baru) < 6) {
            $error_msg = "Password baru minimal 6 karakter.";
        } elseif ($password_baru !== $password_konfirmasi) {
            $error_msg = "Konfirmasi password baru tidak cocok.";
        } else {
            $hash = password_hash($password_baru, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $_SESSION['user_id']]);
            $success_msg = "Password berhasil diperbarui!";
        }
    }

    if (($_POST['action'] ?? '') === 'ubah_pin') {
        $password_konfirmasi_pin = $_POST['password_konfirmasi_pin'] ?? '';
        $pin_baru = trim($_POST['pin_baru'] ?? '');
        $pin_konfirmasi = trim($_POST['pin_konfirmasi'] ?? '');

        // Verifikasi pakai password (bukan PIN lama) karena PIN memang dirancang untuk dipakai
        // saat lupa password — kalau lupa PIN, seharusnya masih ingat password untuk mengubahnya.
        if (!password_verify($password_konfirmasi_pin, $akun['password'])) {
            $error_msg = "Password kamu salah, PIN tidak diubah.";
        } elseif (strlen($pin_baru) < 4) {
            $error_msg = "PIN baru minimal 4 karakter.";
        } elseif ($pin_baru !== $pin_konfirmasi) {
            $error_msg = "Konfirmasi PIN baru tidak cocok.";
        } else {
            $hash = password_hash($pin_baru, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET pin = ? WHERE id = ?")->execute([$hash, $_SESSION['user_id']]);
            $success_msg = "PIN keamanan berhasil diperbarui!";
        }
    }

    if (($_POST['action'] ?? '') === 'ubah_avatar') {
        $avatar_baru = $_POST['avatar_key'] ?? '';
        if (!array_key_exists($avatar_baru, avatarPresets())) {
            $error_msg = "Avatar tidak valid.";
        } else {
            $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$avatar_baru, $_SESSION['user_id']]);
            $_SESSION['avatar'] = $avatar_baru;
            $akun['avatar'] = $avatar_baru;
            $success_msg = "Avatar berhasil diganti!";
        }
    }
}

// --- Statistik ringkas untuk kartu profil ---
$stmtProker = $pdo->prepare("SELECT COUNT(*) FROM proker WHERE created_by = ?");
$stmtProker->execute([$_SESSION['user_id']]);
$totalProkerDibuat = (int) $stmtProker->fetchColumn();

$stmtPresensi = $pdo->prepare("SELECT status FROM presensi WHERE user_id = ?");
$stmtPresensi->execute([$_SESSION['user_id']]);
$semuaPresensi = $stmtPresensi->fetchAll();
$totalPertemuanDicatat = count($semuaPresensi);
$totalHadir = count(array_filter($semuaPresensi, fn($p) => $p['status'] === 'Hadir'));
$persenKehadiran = $totalPertemuanDicatat > 0 ? round(($totalHadir / $totalPertemuanDicatat) * 100) : null;

$pageTitle = 'Profil Saya';
require_once '../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title">Profil Saya</h2>
</div>

<?php if ($success_msg): ?><div class="neo-alert neo-alert-success"><?= e($success_msg) ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="neo-alert neo-alert-error"><?= e($error_msg) ?></div><?php endif; ?>

<div class="neo-card profil-hero">
    <div class="profil-hero-avatar-wrap">
        <?= renderAvatar($akun['avatar'] ?? null, 'lg') ?>
        <button type="button" class="avatar-edit-btn" data-modal-open="modalAvatar" aria-label="Ganti avatar">
            <i class="fas fa-pen"></i>
        </button>
    </div>
    <div class="profil-hero-info">
        <h3 class="profil-hero-name"><?= e($akun['nama_lengkap']) ?></h3>
        <p class="profil-hero-username">@<?= e($akun['username']) ?></p>
        <div class="profil-hero-badges">
            <span class="neo-badge role-badge"><?= e($akun['role']) ?></span>
            <?php if (!empty($akun['jabatan'])): ?><span class="neo-badge"><?= e($akun['jabatan']) ?></span><?php endif; ?>
            <span class="neo-badge"><?= e($akun['nama_divisi'] ?? 'Tanpa Divisi') ?></span>
        </div>
    </div>
</div>

<div class="profil-stats">
    <div class="profil-stat-card">
        <span class="profil-stat-number"><?= $totalProkerDibuat ?></span>
        <span class="profil-stat-label">Proker Dibuat</span>
    </div>
    <div class="profil-stat-card">
        <span class="profil-stat-number"><?= $persenKehadiran !== null ? $persenKehadiran . '%' : '-' ?></span>
        <span class="profil-stat-label">Tingkat Kehadiran Rapat</span>
    </div>
    <div class="profil-stat-card">
        <span class="profil-stat-number profil-stat-number--sm"><?= tanggalIndo($akun['created_at']) ?></span>
        <span class="profil-stat-label">Bergabung Sejak</span>
    </div>
</div>

<div class="profil-actions">
    <button type="button" class="neo-btn neo-btn-primary" data-modal-open="modalPassword"><i class="fas fa-key"></i> Ubah Password</button>
    <button type="button" class="neo-btn neo-btn-warning" data-modal-open="modalPin"><i class="fas fa-shield-halved"></i> Ubah PIN Keamanan</button>
</div>

<p class="text-subtle text-sm" style="margin-top:14px;">Untuk mengubah nama, username, atau divisi, hubungi BPH melalui menu Kelola User.</p>

<!-- Modal: Pilih Avatar -->
<div id="modalAvatar" class="neo-modal-overlay">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title">Pilih Avatar</h3>
            <button type="button" class="modal-close" data-modal-close="modalAvatar" aria-label="Tutup">&times;</button>
        </div>
        <p class="text-subtle text-sm" style="margin-bottom:14px;">Pilih avatar bertema neobrutalism buat profil kamu. Klik salah satu untuk langsung menyimpan.</p>
        <div class="avatar-picker-grid">
            <?php foreach (avatarPresets() as $key => $p): ?>
                <form method="POST" class="avatar-picker-form">
            <?= csrf_field() ?>
                    <input type="hidden" name="action" value="ubah_avatar">
                    <input type="hidden" name="avatar_key" value="<?= e($key) ?>">
                    <button type="submit"
                            class="avatar-picker-option<?= ($akun['avatar'] ?? '') === $key ? ' is-selected' : '' ?>"
                            style="background:<?= $p['bg'] ?>; color:<?= $p['fg'] ?>;"
                            aria-label="Pilih avatar <?= e($key) ?>">
                        <i class="fas <?= e($p['icon']) ?>"></i>
                        <?php if (($akun['avatar'] ?? '') === $key): ?><span class="avatar-picker-check"><i class="fas fa-check"></i></span><?php endif; ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal: Ubah Password -->
<div id="modalPassword" class="neo-modal-overlay">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title">Ubah Password</h3>
            <button type="button" class="modal-close" data-modal-close="modalPassword" aria-label="Tutup">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="ubah_password">
            <div class="form-group">
                <label class="neo-label" for="password_lama">Password Lama</label>
                <div class="auth-password-wrap">
                    <input type="password" name="password_lama" id="password_lama" class="neo-input" required autocomplete="current-password">
                    <button type="button" class="auth-password-toggle" data-toggle-password="password_lama" aria-label="Tampilkan password"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <div class="form-group">
                <label class="neo-label" for="password_baru">Password Baru</label>
                <div class="auth-password-wrap">
                    <input type="password" name="password_baru" id="password_baru" class="neo-input" required minlength="6" autocomplete="new-password">
                    <button type="button" class="auth-password-toggle" data-toggle-password="password_baru" aria-label="Tampilkan password"><i class="fas fa-eye"></i></button>
                </div>
                <small class="text-subtle">Minimal 6 karakter.</small>
            </div>
            <div class="form-group form-group-lg">
                <label class="neo-label" for="password_konfirmasi">Konfirmasi Password Baru</label>
                <div class="auth-password-wrap">
                    <input type="password" name="password_konfirmasi" id="password_konfirmasi" class="neo-input" required minlength="6" autocomplete="new-password">
                    <button type="button" class="auth-password-toggle" data-toggle-password="password_konfirmasi" aria-label="Tampilkan password"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="neo-btn neo-btn-muted" data-modal-close="modalPassword">Batal</button>
                <button type="submit" class="neo-btn neo-btn-primary"><i class="fas fa-key"></i> Simpan Password Baru</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Ubah PIN -->
<div id="modalPin" class="neo-modal-overlay">
    <div class="neo-modal-box">
        <div class="flex-between modal-header">
            <h3 class="modal-title">Ubah PIN Keamanan</h3>
            <button type="button" class="modal-close" data-modal-close="modalPin" aria-label="Tutup">&times;</button>
        </div>
        <p class="text-subtle text-sm">PIN ini dipakai untuk verifikasi di halaman "Lupa Password". Ubah PIN kalau kamu curiga PIN lama sudah diketahui orang lain.</p>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="ubah_pin">
            <div class="form-group">
                <label class="neo-label" for="password_konfirmasi_pin">Password Kamu (verifikasi)</label>
                <div class="auth-password-wrap">
                    <input type="password" name="password_konfirmasi_pin" id="password_konfirmasi_pin" class="neo-input" required autocomplete="current-password">
                    <button type="button" class="auth-password-toggle" data-toggle-password="password_konfirmasi_pin" aria-label="Tampilkan password"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <div class="form-group">
                <label class="neo-label" for="pin_baru">PIN Baru</label>
                <input type="text" inputmode="numeric" name="pin_baru" id="pin_baru" class="neo-input" required minlength="4" placeholder="Contoh: 4 digit angka atau kode singkat">
            </div>
            <div class="form-group form-group-lg">
                <label class="neo-label" for="pin_konfirmasi">Konfirmasi PIN Baru</label>
                <input type="text" inputmode="numeric" name="pin_konfirmasi" id="pin_konfirmasi" class="neo-input" required minlength="4">
            </div>
            <div class="form-actions">
                <button type="button" class="neo-btn neo-btn-muted" data-modal-close="modalPin">Batal</button>
                <button type="submit" class="neo-btn neo-btn-warning"><i class="fas fa-shield-halved"></i> Simpan PIN Baru</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
