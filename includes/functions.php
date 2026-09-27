<?php
/**
 * Helper terpusat (di-load lewat init.php di SETIAP file, sebelum logic apa pun):
 * - requireLogin/requireRole  : penjaga halaman & hak akses role (RBAC).
 * - generateNomorSurat        : penomoran surat otomatis per tahun.
 * - isAccountLocked/recordFailedAttempt/resetFailedAttempts : anti-bruteforce login.
 * - e()                       : escape output agar aman dari XSS.
 * - tanggalIndo()             : format tanggal Indonesia.
 */

// Menolak akses anonim: melempar ke halaman login bila belum ada sesi user.
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

// contoh: requireRole(['BPH']); atau requireRole(['BPH','Koordinator Divisi']);
// Membatasi halaman hanya untuk role tertentu; selain itu dikembalikan ke dashboard.
function requireRole(array $allowedRoles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        header('Location: ' . BASE_URL . '/index.php?error=akses_ditolak');
        exit;
    }
}

// Nomor surat otomatis: 001/REMBUK/IX/2026
function generateNomorSurat(PDO $pdo) {
    $romawiBulan = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
    $bulanRomawi = $romawiBulan[date('n') - 1];
    $tahun = date('Y');

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total FROM surat WHERE nomor_surat IS NOT NULL AND YEAR(created_at) = ?"
    );
    $stmt->execute([$tahun]);
    $total = (int) $stmt->fetch()['total'] + 1;
    $urutan = str_pad($total, 3, '0', STR_PAD_LEFT);

    return "{$urutan}/" . APP_NAME . "/{$bulanRomawi}/{$tahun}";
}

// --- Anti-bruteforce login ---
function isAccountLocked(PDO $pdo, string $username) {
    $stmt = $pdo->prepare("SELECT locked_until FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
        return strtotime($user['locked_until']) - time();
    }
    return false;
}

function recordFailedAttempt(PDO $pdo, string $username) {
    $stmt = $pdo->prepare("SELECT id, failed_attempts FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user) return;

    $attempts = $user['failed_attempts'] + 1;
    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $lockUntil = date('Y-m-d H:i:s', strtotime('+' . LOCK_DURATION_MINUTES . ' minutes'));
        $upd = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = ? WHERE id = ?");
        $upd->execute([$lockUntil, $user['id']]);
    } else {
        $upd = $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
        $upd->execute([$attempts, $user['id']]);
    }
}

function resetFailedAttempts(PDO $pdo, int $userId) {
    $upd = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?");
    $upd->execute([$userId]);
}

// Hitung berapa akun BPH lain (selain $excludeUserId) yang masih aktif.
// Dipakai sebagai pengaman di Kelola User: mencegah organisasi "terkunci"
// karena BPH terakhir tidak sengaja di-nonaktifkan atau diturunkan rolenya.
function countActiveBphExcluding(PDO $pdo, int $excludeUserId): int {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM users WHERE role = 'BPH' AND status = 'aktif' AND id != ?"
    );
    $stmt->execute([$excludeUserId]);
    return (int) $stmt->fetchColumn();
}

// --- Escape output singkat ---
function e(?string $text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// --- Tanggal berbahasa Indonesia ---
// tanggalIndo('2026-04-07')                 -> 07 Apr 2026
// tanggalIndo('2026-04-07', true)           -> 07 April 2026
// tanggalIndo('2026-04-07', true, true)     -> Selasa, 07 April 2026
// tanggalIndo('2026-04-07 14:30:00', false, false, true) -> 07 Apr 2026, 14:30
function tanggalIndo($tanggal, bool $panjang = false, bool $denganHari = false, bool $denganJam = false): string {
    if (!$tanggal) return '-';
    $t = is_numeric($tanggal) ? (int) $tanggal : strtotime($tanggal);
    $bulanPanjang = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $bulanPendek = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $namaHari = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    $bulan = ($panjang ? $bulanPanjang : $bulanPendek)[(int) date('n', $t)];
    $teks = date('d', $t) . ' ' . $bulan . ' ' . date('Y', $t);
    if ($denganHari) $teks = $namaHari[(int) date('N', $t)] . ', ' . $teks;
    if ($denganJam) $teks .= ', ' . date('H:i', $t);
    return $teks;
}

// --- Avatar bertema neobrutalism (bukan foto, tapi ikon + warna aksen) ---
// Dipakai di halaman Profil (picker) dan topbar (mini avatar).
function avatarPresets(): array {
    return [
        'cyan-rocket'  => ['icon' => 'fa-rocket', 'bg' => 'var(--accent-cyan)',   'fg' => 'var(--main-black)'],
        'cyan-gem'     => ['icon' => 'fa-gem',    'bg' => 'var(--accent-cyan)',   'fg' => 'var(--main-black)'],
        'pink-star'    => ['icon' => 'fa-star',   'bg' => 'var(--accent-pink)',   'fg' => 'var(--main-black)'],
        'pink-heart'   => ['icon' => 'fa-heart',  'bg' => 'var(--accent-pink)',   'fg' => 'var(--main-black)'],
        'yellow-bolt'  => ['icon' => 'fa-bolt',   'bg' => 'var(--accent-yellow)', 'fg' => 'var(--main-black)'],
        'yellow-crown' => ['icon' => 'fa-crown',  'bg' => 'var(--accent-yellow)', 'fg' => 'var(--main-black)'],
        'green-leaf'   => ['icon' => 'fa-leaf',   'bg' => 'var(--accent-green)',  'fg' => 'var(--main-black)'],
        'green-paw'    => ['icon' => 'fa-paw',    'bg' => 'var(--accent-green)',  'fg' => 'var(--main-black)'],
        'red-fire'     => ['icon' => 'fa-fire',   'bg' => 'var(--accent-red)',    'fg' => '#fff'],
        'red-ghost'    => ['icon' => 'fa-ghost',  'bg' => 'var(--accent-red)',    'fg' => '#fff'],
    ];
}

// renderAvatar(null, 'lg') -> badge default (belum pilih avatar)
// renderAvatar('cyan-rocket', 'sm') -> badge kecil buat topbar
function renderAvatar(?string $key, string $size = 'md'): string {
    $presets = avatarPresets();
    $p = $presets[$key] ?? ['icon' => 'fa-user', 'bg' => '#fff', 'fg' => 'var(--main-black)'];
    return '<span class="avatar-badge avatar-badge--' . e($size) . '" style="background:' . $p['bg'] . '; color:' . $p['fg'] . ';">'
         . '<i class="fas ' . e($p['icon']) . '"></i></span>';
}

// ---------------------------------------------------------------------------
// CSRF Protection
// Satu token per sesi login (disimpan di $_SESSION), diverifikasi terpusat
// di includes/init.php untuk SETIAP request POST di seluruh situs.
// ---------------------------------------------------------------------------

// Ambil token CSRF sesi saat ini; buat baru kalau belum ada.
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Cetak hidden input CSRF; panggil dan echo hasil csrf_field() tepat setelah tag pembuka form method POST.
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// Tolak request POST yang tidak membawa token CSRF yang valid.
// Dipanggil otomatis dari includes/init.php untuk SEMUA POST, jadi halaman
// individual tidak perlu (dan tidak boleh lupa) memanggilnya sendiri.
function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
           . '<title>Sesi Tidak Valid</title></head><body style="font-family:sans-serif;max-width:480px;margin:80px auto;text-align:center;">'
           . '<h2>Sesi formulir sudah kedaluwarsa</h2>'
           . '<p>Ini bisa terjadi kalau halaman dibuka terlalu lama atau formulir dikirim dua kali. Silakan muat ulang halaman dan coba lagi.</p>'
           . '<a href="javascript:history.back()" style="display:inline-block;margin-top:10px;font-weight:700;">&larr; Kembali</a>'
           . '</body></html>';
        exit;
    }
}

// ---------------------------------------------------------------------------
// Pagination
// Dipakai di tabel yang bisa tumbuh panjang: Kelola User, Keuangan, Persuratan.
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
// Laporan (ekspor Excel & PDF untuk LPJ)
// Dua helper kecil dipakai bersama oleh laporan/keuangan.php, proker.php, presensi.php.
// ---------------------------------------------------------------------------

// Kirim data sebagai file .xls yang bisa dibuka Excel/Google Sheets/LibreOffice,
// dibuat dari tabel HTML biasa (tanpa library eksternal, tidak butuh internet).
// $judul dipakai untuk nama file; $htmlTable = string HTML lengkap tag <table>...</table>.
function unduhExcel(string $judul, string $htmlTable): void {
    $namaFile = preg_replace('/[^A-Za-z0-9_-]+/', '_', $judul) . '_' . date('Y-m-d') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $namaFile . '"');
    header('Cache-Control: max-age=0');
    echo "ï»¿"; // BOM supaya karakter (Rp, é, dsb) tidak rusak saat dibuka Excel
    echo '<html><head><meta charset="UTF-8"></head><body>' . $htmlTable . '</body></html>';
    exit;
}

// Cetak kop laporan (nama organisasi, judul, periode, dicetak oleh & kapan) untuk versi PDF/cetak.
function cetakKopLaporan(string $judul, string $periode): void {
    ?>
    <div class="laporan-kop">
        <h1><?= e(APP_NAME) ?></h1>
        <p><?= e(APP_TAGLINE) ?></p>
        <h2><?= e($judul) ?></h2>
        <p class="laporan-periode"><?= e($periode) ?></p>
    </div>
    <p class="laporan-meta">Dicetak oleh <?= e($_SESSION['nama_lengkap'] ?? '-') ?> &middot; <?= tanggalIndo(time(), true, true, true) ?></p>
    <?php
}

// Nama pengurus inti (Ketua, Sekretaris, Bendahara) untuk bagan struktur organisasi
// (includes/bagan.php) & tanda tangan laporan (laporan/*.php). Ganti di sini saja kalau ada pergantian.
function baganBphNames(): array {
    return ['Ketua' => 'Fauzan Dimas Prasojo', 'Sekretaris' => 'Marva Ghevirani', 'Bendahara' => 'Adella Rizka Afifah'];
}

const PAGINATE_PER_PAGE = 10;

// Hitung [page, total_pages, offset] dari jumlah baris total & query string ?page=.
function paginateParams(int $total_rows, int $per_page = PAGINATE_PER_PAGE): array {
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;
    return [$page, $total_pages, $offset];
}

// Cetak navigasi halaman (Sebelumnya/nomor/Berikutnya). $extra_query untuk
// membawa filter GET lain kalau ada, contoh: "&status=Pending".
function renderPagination(int $page, int $total_pages, string $extra_query = ''): void {
    if ($total_pages <= 1) {
        return;
    }

    $link = function (int $p) use ($extra_query) {
        return '?page=' . $p . $extra_query;
    };

    echo '<nav class="pagination-nav" aria-label="Navigasi halaman">';

    if ($page <= 1) {
        echo '<span class="neo-btn neo-btn-outline neo-btn-sm pagination-btn pagination-disabled" aria-hidden="true"><i class="fas fa-chevron-left"></i></span>';
    } else {
        echo '<a href="' . e($link($page - 1)) . '" class="neo-btn neo-btn-outline neo-btn-sm pagination-btn" aria-label="Halaman sebelumnya"><i class="fas fa-chevron-left"></i></a>';
    }

    $start = max(1, $page - 2);
    $end = min($total_pages, $page + 2);

    if ($start > 1) {
        echo '<a href="' . e($link(1)) . '" class="neo-btn neo-btn-outline neo-btn-sm pagination-btn">1</a>';
        if ($start > 2) {
            echo '<span class="pagination-ellipsis">&hellip;</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $activeClass = $i === $page ? ' pagination-active' : '';
        echo '<a href="' . e($link($i)) . '" class="neo-btn neo-btn-outline neo-btn-sm pagination-btn' . $activeClass . '">' . $i . '</a>';
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            echo '<span class="pagination-ellipsis">&hellip;</span>';
        }
        echo '<a href="' . e($link($total_pages)) . '" class="neo-btn neo-btn-outline neo-btn-sm pagination-btn">' . $total_pages . '</a>';
    }

    if ($page >= $total_pages) {
        echo '<span class="neo-btn neo-btn-outline neo-btn-sm pagination-btn pagination-disabled" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>';
    } else {
        echo '<a href="' . e($link($page + 1)) . '" class="neo-btn neo-btn-outline neo-btn-sm pagination-btn" aria-label="Halaman berikutnya"><i class="fas fa-chevron-right"></i></a>';
    }

    echo '<span class="pagination-info">Halaman ' . $page . ' dari ' . $total_pages . '</span>';
    echo '</nav>';
}

// ---------------------------------------------------------------------------
// Flash message (notifikasi sekali tampil setelah redirect, pola PRG)
// Contoh: flash('success', 'Transaksi kas berhasil disimpan!');
//         header('Location: index.php'); exit;
// Lalu di halaman tujuan, tepat setelah error alert, panggil displayFlash().
// ---------------------------------------------------------------------------

// Simpan pesan ke session; dibaca & dihapus oleh displayFlash() di request berikutnya.
function flash(string $type, string $message): void {
    if (!in_array($type, ['success', 'error'], true)) {
        $type = 'success';
    }
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

// Cetak semua flash yang tersimpan (gaya neo-alert), lalu hapus dari session
// supaya tidak muncul lagi saat halaman di-refresh.
function displayFlash(): void {
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $f) {
        $cls = (($f['type'] ?? '') === 'error') ? 'neo-alert-error' : 'neo-alert-success';
        $icon = (($f['type'] ?? '') === 'error') ? 'fa-circle-exclamation' : 'fa-circle-check';
        echo '<div class="neo-alert ' . $cls . '" role="alert"><i class="fas ' . $icon . '" aria-hidden="true"></i> ' . e($f['message'] ?? '') . '</div>';
    }
}

// ---------------------------------------------------------------------------
// Escape wildcard untuk pencarian LIKE: karakter % _ dan backslash dari input
// user dinetralkan supaya dicari sebagai teks biasa, bukan wildcard.
// Pakai bersama klausa ESCAPE di SQL, contoh:
//   $where[] = "u.nama_lengkap LIKE ? ESCAPE '\\\\'";
//   $params[] = '%' . escapeLike($q) . '%';
// ---------------------------------------------------------------------------
function escapeLike(string $s): string {
    return addcslashes($s, '\\%_');
}

// ---------------------------------------------------------------------------
// Ingat & kembalikan state daftar (filter + pagination) saat POST-redirect-GET
// Panggil rememberListState('users') di halaman daftar saat request GET;
// redirect sukses memakai redirectList('index.php', 'users') agar user kembali
// ke filter/halaman yang sama, bukan ke tampilan polos.
// ---------------------------------------------------------------------------
function rememberListState(string $key): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $_SESSION['list_state_' . $key] = $_SERVER['QUERY_STRING'] ?? '';
    }
}

// Bangun URL redirect ke halaman daftar dengan membawa state yang diingat
// (atau URL polos bila belum ada state tersimpan).
function redirectList(string $page, string $key): string {
    $qs = $_SESSION['list_state_' . $key] ?? '';
    return $page . ($qs !== '' ? '?' . $qs : '');
}

// ---------------------------------------------------------------------------
// Rate limiting sederhana (berbasis session, tanpa perlu Redis/Memcached)
// ---------------------------------------------------------------------------

// rateLimit('proker_search', 20, 10) -> true kalau masih dalam batas (dan
// langsung mencatat hit ini); false kalau sudah melebihi $maxRequests dalam
// $windowSeconds detik terakhir untuk sesi (user login) yang sama.
function rateLimit(string $key, int $maxRequests, int $windowSeconds): bool {
    $now = microtime(true);
    $sessionKey = 'rate_' . $key;
    $hits = $_SESSION[$sessionKey] ?? [];

    // Buang catatan yang sudah di luar jendela waktu (sliding window).
    $hits = array_values(array_filter($hits, function ($t) use ($now, $windowSeconds) {
        return ($now - $t) < $windowSeconds;
    }));

    if (count($hits) >= $maxRequests) {
        $_SESSION[$sessionKey] = $hits;
        return false;
    }

    $hits[] = $now;
    $_SESSION[$sessionKey] = $hits;
    return true;
}
