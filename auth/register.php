<?php
require_once '../includes/init.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

$error = '';
$success = '';
$semuaDivisi = $pdo->query("SELECT id, nama_divisi FROM divisi ORDER BY nama_divisi")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username     = trim($_POST['username']);
    $password     = $_POST['password'];
    $pin          = trim($_POST['pin']);
    $divisi_id    = $_POST['divisi_id'] ?: null;

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);

    if ($stmt->rowCount() > 0) {
        $error = "Username sudah digunakan, silakan pilih yang lain!";
    } elseif (strlen($pin) !== 6 || !ctype_digit($pin)) {
        $error = "PIN harus 6 digit angka.";
    } else {
        // Hash password & PIN (tidak pernah disimpan plain), akun baru selalu role Anggota.
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);

        $stmt_insert = $pdo->prepare(
            "INSERT INTO users (nama_lengkap, username, password, pin, role, divisi_id) VALUES (?, ?, ?, ?, 'Anggota', ?)"
        );

        if ($stmt_insert->execute([$nama_lengkap, $username, $hashed_password, $hashed_pin, $divisi_id])) {
            header("Location: login.php?registered=1");
            exit;
        } else {
            $error = "Terjadi kesalahan sistem.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/neobrutalism.css">
</head>
<body>
    <main class="auth-container auth-container--decorated">
        <div class="auth-decoration auth-decoration--cyan" aria-hidden="true"></div>
        <div class="auth-decoration auth-decoration--yellow-sm" aria-hidden="true"></div>

        <section class="neo-card auth-box auth-box--register auth-pop-in" aria-labelledby="register-title">
            <header class="auth-header">
                <div class="logo-mark auth-logo--tilt-right">
                    <img src="../assets/img/logo.svg" alt="Logo <?= e(APP_NAME) ?>">
                </div>
                <h1 class="auth-title" id="register-title">Buat Akun Baru</h1>
            </header>

            <?php if ($error): ?><div class="neo-alert neo-alert-error auth-alert-shake" role="alert"><?= e($error) ?></div><?php endif; ?>

            <form method="POST">
            <?= csrf_field() ?>
                <div class="auth-form-group auth-form-group--dense">
                    <label class="neo-label" for="register-name">Nama Lengkap</label>
                    <input type="text" id="register-name" name="nama_lengkap" class="neo-input" required placeholder="Fauzan Dimas Prasojo" autocomplete="name">
                </div>
                <div class="auth-form-group auth-form-group--dense">
                    <label class="neo-label" for="register-username">Username</label>
                    <input type="text" id="register-username" name="username" class="neo-input" required placeholder="fauzandimas" autocomplete="username">
                </div>
                <div class="auth-form-group auth-form-group--dense">
                    <label class="neo-label" for="register-division">Divisi</label>
                    <select id="register-division" name="divisi_id" class="neo-input" required>
                        <option value="">-- pilih divisi --</option>
                        <?php foreach ($semuaDivisi as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['nama_divisi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="auth-form-group auth-form-group--dense">
                    <label class="neo-label" for="register-password">Password</label>
                    <div class="auth-password-wrap">
                        <input type="password" id="register-password" name="password" class="neo-input" required minlength="6" placeholder="*****" autocomplete="new-password" data-strength-target="register-password-strength">
                        <button type="button" class="auth-password-toggle" data-toggle-password="register-password" aria-label="Tampilkan password"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="auth-strength-meter" id="register-password-strength" aria-hidden="true">
                        <span></span><span></span><span></span><span></span>
                    </div>
                    <small class="auth-strength-label" id="register-password-strength-label"></small>
                </div>
                <div class="auth-form-group auth-form-group--final">
                    <label class="neo-label" for="register-pin">PIN Keamanan (6 Digit)</label>
                    <input type="password" id="register-pin" name="pin" maxlength="6" pattern="\d{6}" inputmode="numeric" class="neo-input" required placeholder="123456">
                </div>
                <button type="submit" class="neo-btn neo-btn-warning auth-submit">Daftar Sekarang</button>
            </form>
            <p class="auth-footer auth-footer--spaced">
                Sudah punya akun? <a class="auth-text-link" href="login.php">Login di sini</a>
            </p>
        </section>
    </main>
    <script src="../assets/js/auth.js"></script>
</body>
</html>
