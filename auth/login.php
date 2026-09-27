<?php
require_once '../includes/init.php';

// Sudah login? Langsung lempar ke dashboard agar tidak login dua kali.
if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

$error = '';
if (isset($_GET['registered'])) $success = 'Registrasi berhasil! Silakan login.';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Cek kunci bruteforce dulu sebelum memverifikasi password.
    $locked = isAccountLocked($pdo, $username);
    if ($locked !== false) {
        $menit = ceil($locked / 60);
        $error = "Akun terkunci sementara akibat percobaan gagal berulang. Coba lagi dalam {$menit} menit.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password']) && $user['status'] !== 'aktif') {
            // Password benar tapi akun sudah dinonaktifkan BPH: tolak masuk, jangan dianggap percobaan gagal.
            $error = "Akun ini sudah dinonaktifkan. Hubungi BPH kalau ini keliru.";
        } elseif ($user && password_verify($password, $user['password'])) {
            resetFailedAttempts($pdo, $user['id']); // Login sukses: nolkan hitungan gagal.
            session_regenerate_id(true); // Cegah session fixation: ganti ID sesi setiap login berhasil.
            // Simpan identitas ke sesi lalu masuk ke dashboard.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['divisi_id'] = $user['divisi_id'];
            $_SESSION['avatar'] = $user['avatar'] ?? null;

            header("Location: ../dashboard.php");
            exit;
        } else {
            if ($user) recordFailedAttempt($pdo, $username);
            $error = "Username atau Password salah!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/neobrutalism.css">
</head>
<body>
    <main class="auth-container auth-container--decorated">
        <div class="auth-decoration auth-decoration--yellow" aria-hidden="true"></div>
        <div class="auth-decoration auth-decoration--pink" aria-hidden="true"></div>
        <div class="auth-decoration auth-decoration--cyan" aria-hidden="true"></div>

        <section class="neo-card auth-box auth-box--login auth-pop-in" aria-labelledby="login-title">
            <header class="auth-header auth-header--spaced">
                <div class="logo-mark auth-logo--tilt-left">
                    <img src="../assets/img/logo.svg" alt="Logo <?= e(APP_NAME) ?>">
                </div>
                <h1 class="auth-title auth-title--portal" id="login-title">Portal Login</h1>
                <p class="auth-tagline"><?= e(APP_TAGLINE) ?></p>
            </header>

            <?php if ($error): ?><div class="neo-alert neo-alert-error auth-alert-shake" role="alert"><?= e($error) ?></div><?php endif; ?>
            <?php if (isset($success)): ?><div class="neo-alert neo-alert-success" role="status"><?= e($success) ?></div><?php endif; ?>

            <form method="POST">
            <?= csrf_field() ?>
                <div class="auth-form-group">
                    <label class="neo-label" for="login-username">Username</label>
                    <input type="text" id="login-username" name="username" class="neo-input" required placeholder="Masukkan username" autocomplete="username" autofocus>
                </div>
                <div class="auth-form-group auth-form-group--final">
                    <label class="neo-label" for="login-password">Password</label>
                    <div class="auth-password-wrap">
                        <input type="password" id="login-password" name="password" class="neo-input" required placeholder="*****" autocomplete="current-password">
                        <button type="button" class="auth-password-toggle" data-toggle-password="login-password" aria-label="Tampilkan password"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="neo-btn neo-btn-success auth-submit">Masuk ke Dashboard</button>
            </form>

            <p class="auth-footer">
                <a class="auth-text-link" href="forgot_password.php">Lupa Password?</a>
            </p>
            <p class="auth-footer auth-footer--compact">
                Belum punya akun? <a class="auth-text-link" href="register.php">Daftar di sini</a>
            </p>
        </section>
    </main>
    <script src="../assets/js/auth.js"></script>
</body>
</html>
