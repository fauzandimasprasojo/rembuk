<?php
require_once '../includes/init.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username      = trim($_POST['username']);
    $nama_lengkap  = trim($_POST['nama_lengkap']);
    $pin           = trim($_POST['pin']);
    $password_baru = $_POST['password_baru'];

    $locked = isAccountLocked($pdo, $username);
    if ($locked !== false) {
        $menit = ceil($locked / 60);
        $error = "Verifikasi terkunci sementara. Coba lagi dalam {$menit} menit.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND nama_lengkap = ?");
        $stmt->execute([$username, $nama_lengkap]);
        $user = $stmt->fetch();

        // PIN sekarang di-hash, jadi verifikasi pakai password_verify, bukan pembandingan langsung
        // PIN disimpan ter-hash, jadi verifikasi memakai password_verify.
        if ($user && password_verify($pin, $user['pin'])) {
            resetFailedAttempts($pdo, $user['id']);
            $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");

            if ($update->execute([$hashed_password, $user['id']])) {
                $success = "Password berhasil diperbarui! Silakan login.";
            } else {
                $error = "Gagal memperbarui password. Coba lagi.";
            }
        } else {
            if ($user) recordFailedAttempt($pdo, $username);
            $error = "Data verifikasi (Username, Nama Lengkap, atau PIN) tidak cocok!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/neobrutalism.css">
</head>
<body>
    <main class="auth-container auth-container--decorated">
        <div class="auth-decoration auth-decoration--pink" aria-hidden="true"></div>
        <div class="auth-decoration auth-decoration--cyan-sm" aria-hidden="true"></div>

        <section class="neo-card auth-box auth-box--forgot-password auth-pop-in" aria-labelledby="forgot-password-title">
            <header class="auth-header">
                <div class="logo-mark">
                    <img src="../assets/img/logo.svg" alt="Logo <?= e(APP_NAME) ?>">
                </div>
                <h1 class="auth-title" id="forgot-password-title">Reset Password</h1>
            </header>

            <?php if ($error): ?><div class="neo-alert neo-alert-error auth-alert-shake" role="alert"><?= e($error) ?></div><?php endif; ?>
            <?php if ($success): ?>
                <div class="neo-alert neo-alert-success" role="status">
                    <?= e($success) ?><br>
                    <a class="auth-text-link" href="login.php">Ke Halaman Login</a>
                </div>
            <?php endif; ?>

            <form method="POST">
            <?= csrf_field() ?>
                <div class="auth-form-group auth-form-group--compact">
                    <label class="neo-label" for="forgot-username">Username</label>
                    <input type="text" id="forgot-username" name="username" class="neo-input" required autocomplete="username">
                </div>
                <div class="auth-form-group auth-form-group--compact">
                    <label class="neo-label" for="forgot-name">Nama Lengkap</label>
                    <input type="text" id="forgot-name" name="nama_lengkap" class="neo-input" required autocomplete="name">
                </div>
                <div class="auth-form-group auth-form-group--compact">
                    <label class="neo-label" for="forgot-pin">PIN Keamanan (6 Digit)</label>
                    <input type="password" id="forgot-pin" name="pin" maxlength="6" class="neo-input" required inputmode="numeric">
                </div>
                <div class="auth-form-group auth-form-group--final">
                    <label class="neo-label" for="new-password">Password Baru</label>
                    <div class="auth-password-wrap">
                        <input type="password" id="new-password" name="password_baru" class="neo-input" required minlength="6" autocomplete="new-password">
                        <button type="button" class="auth-password-toggle" data-toggle-password="new-password" aria-label="Tampilkan password"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="neo-btn auth-submit">Reset Password</button>
            </form>
            <p class="auth-footer auth-footer--spaced">
                Ingat password kamu? <a class="auth-text-link" href="login.php">Login di sini</a>
            </p>
        </section>
    </main>
    <script src="../assets/js/auth.js"></script>
</body>
</html>
