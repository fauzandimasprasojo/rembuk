<?php
require_once 'includes/init.php';

// Sudah login? Langsung ke dashboard, tidak perlu melihat landing page lagi.
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$pageTitle = 'Beranda';

// Statistik ringkas untuk landing page
$totalProker = (int) $pdo->query("SELECT COUNT(*) FROM proker")->fetchColumn();
$prokerSelesai = (int) $pdo->query("SELECT COUNT(*) FROM proker WHERE status = 'Done'")->fetchColumn();
$totalAnggota = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'aktif'")->fetchColumn();
$totalDivisi = (int) $pdo->query("SELECT COUNT(*) FROM divisi")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= e(APP_TAGLINE) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/neobrutalism.css">
</head>
<body>
    <header class="landing-navbar">
        <div class="logo-mark">
            <img src="assets/img/logo.svg" alt="Logo <?= e(APP_NAME) ?>">
            <span class="logo-mark-text"><?= APP_NAME ?></span>
        </div>
        <nav class="landing-navbar-actions">
            <a href="auth/login.php" class="neo-btn neo-btn-outline no-link">Masuk</a>
            <a href="auth/register.php" class="neo-btn neo-btn-primary no-link">Daftar</a>
        </nav>
    </header>

    <main class="landing-wrapper">
        <section class="landing-hero" id="landingHero">
            <div class="landing-hero-decoration" aria-hidden="true"></div>
            <div class="deco-wrap" style="top:14%; left:8%;" data-depth="18" aria-hidden="true"><div class="deco-shape deco-square"></div></div>
            <div class="deco-wrap" style="top:62%; left:6%;" data-depth="26" aria-hidden="true"><div class="deco-shape deco-circle" style="animation-delay:-1.5s;"></div></div>
            <div class="deco-wrap" style="top:20%; left:88%;" data-depth="22" aria-hidden="true"><div class="deco-shape deco-diamond" style="animation-delay:-3s;"></div></div>
            <div class="deco-wrap" style="top:78%; left:80%;" data-depth="14" aria-hidden="true"><div class="deco-shape deco-square deco-square-sm" style="animation-delay:-4.5s;"></div></div>
            <div class="deco-wrap" style="top:44%; left:94%;" data-depth="30" aria-hidden="true"><div class="deco-shape deco-circle deco-circle-sm" style="animation-delay:-2.2s;"></div></div>
            <div class="landing-hero-content">
                <h1 class="landing-hero-title"><?= APP_NAME ?></h1>
                <p class="landing-hero-tagline"><?= e(APP_TAGLINE) ?></p>
                <p class="landing-hero-desc">
                    Satu tempat untuk mengelola program kerja, jobdesk, kalender kegiatan,
                    keuangan, dokumentasi, hingga persuratan organisasi. Masuk untuk melihat
                    dashboard, statistik, dan detail lengkap sesuai peran Anda.
                </p>
                <div class="landing-hero-cta">
                    <a href="auth/login.php" class="neo-btn neo-btn-success">Masuk ke Dashboard <i class="fas fa-arrow-right"></i></a>
                    <a href="auth/register.php" class="neo-btn neo-btn-outline">Buat Akun Baru</a>
                </div>
            </div>
        </section>

        <section class="landing-stats reveal">
            <div class="landing-stat-card">
                <span class="landing-stat-number" data-count-to="<?= $totalProker ?>">0</span>
                <span class="landing-stat-label">Program Kerja</span>
            </div>
            <div class="landing-stat-card">
                <span class="landing-stat-number" data-count-to="<?= $prokerSelesai ?>">0</span>
                <span class="landing-stat-label">Proker Selesai</span>
            </div>
            <div class="landing-stat-card">
                <span class="landing-stat-number" data-count-to="<?= $totalAnggota ?>">0</span>
                <span class="landing-stat-label">Anggota Aktif</span>
            </div>
            <div class="landing-stat-card">
                <span class="landing-stat-number" data-count-to="<?= $totalDivisi ?>">0</span>
                <span class="landing-stat-label">Divisi</span>
            </div>
        </section>

        <section class="landing-preview reveal">
            <h2 class="section-title"><i class="fas fa-desktop"></i> Intip Tampilan Dashboard</h2>
            <div class="browser-mock" id="browserMock">
                <div class="browser-mock-bar">
                    <span class="browser-dot browser-dot-red"></span>
                    <span class="browser-dot browser-dot-yellow"></span>
                    <span class="browser-dot browser-dot-green"></span>
                    <span class="browser-mock-url"><i class="fas fa-lock"></i> rembuk2/dashboard</span>
                </div>
                <div class="browser-mock-body">
                    <div class="browser-mock-sidebar">
                        <div class="browser-mock-sidebar-logo"></div>
                        <div class="browser-mock-sidebar-item is-active"></div>
                        <div class="browser-mock-sidebar-item"></div>
                        <div class="browser-mock-sidebar-item"></div>
                        <div class="browser-mock-sidebar-item"></div>
                        <div class="browser-mock-sidebar-item"></div>
                    </div>
                    <div class="browser-mock-main">
                        <div class="browser-mock-stats">
                            <div class="browser-mock-stat browser-mock-stat-cyan"></div>
                            <div class="browser-mock-stat browser-mock-stat-green"></div>
                            <div class="browser-mock-stat browser-mock-stat-red"></div>
                            <div class="browser-mock-stat browser-mock-stat-white"></div>
                        </div>
                        <div class="browser-mock-panels">
                            <div class="browser-mock-panel">
                                <div class="browser-mock-donut"></div>
                            </div>
                            <div class="browser-mock-panel">
                                <div class="browser-mock-list-row"></div>
                                <div class="browser-mock-list-row"></div>
                                <div class="browser-mock-list-row"></div>
                                <div class="browser-mock-list-row"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="browser-mock-caption">Ilustrasi tampilan dashboard setelah masuk.</p>
        </section>

        <section class="landing-features reveal">
            <h2 class="section-title"><i class="fas fa-layer-group"></i> Yang Bisa Dikelola</h2>
            <div class="grid-auto">
                <div class="neo-card landing-feature-card">
                    <i class="fas fa-list-check landing-feature-icon" style="color: var(--accent-pink)"></i>
                    <h3>Program Kerja</h3>
                    <p>Jobdesk, PIC, dan progres tiap proker terpantau otomatis.</p>
                </div>
                <div class="neo-card landing-feature-card">
                    <i class="fas fa-calendar-days landing-feature-icon" style="color: var(--accent-cyan)"></i>
                    <h3>Kalender</h3>
                    <p>Jadwal proker dan hari penting organisasi dalam satu tampilan.</p>
                </div>
                <div class="neo-card landing-feature-card">
                    <i class="fas fa-wallet landing-feature-icon" style="color: var(--accent-yellow)"></i>
                    <h3>Keuangan</h3>
                    <p>Rekap kas transparan untuk seluruh anggota organisasi.</p>
                </div>
                <div class="neo-card landing-feature-card">
                    <i class="fas fa-envelope landing-feature-icon" style="color: var(--accent-green)"></i>
                    <h3>Persuratan</h3>
                    <p>Ajukan, tinjau, dan lacak status surat sampai bernomor resmi.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="landing-footer">
        &copy; <?= date('Y') ?> <?= APP_NAME ?>. <?= e(APP_TAGLINE) ?>
    </footer>

    <script>
        // Reveal section saat masuk viewport (fallback: langsung tampil kalau browser tidak dukung)
        var revealEls = document.querySelectorAll('.reveal');
        if ('IntersectionObserver' in window) {
            var revealObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15 });
            revealEls.forEach(function (el) { revealObserver.observe(el); });
        } else {
            revealEls.forEach(function (el) { el.classList.add('is-visible'); });
        }

        // Count-up angka statistik saat kartu stats kelihatan
        var statNumbers = document.querySelectorAll('[data-count-to]');
        function animateCount(el) {
            var target = parseInt(el.getAttribute('data-count-to'), 10) || 0;
            var duration = 900;
            var start = null;
            function step(ts) {
                if (!start) start = ts;
                var progress = Math.min((ts - start) / duration, 1);
                el.textContent = Math.floor(progress * target);
                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    el.textContent = target;
                }
            }
            requestAnimationFrame(step);
        }
        if ('IntersectionObserver' in window && statNumbers.length) {
            var statObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        animateCount(entry.target);
                        statObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.4 });
            statNumbers.forEach(function (el) { statObserver.observe(el); });
        } else {
            statNumbers.forEach(function (el) { el.textContent = el.getAttribute('data-count-to'); });
        }

        // Parallax dekorasi hero: bentuk bergerak sedikit ngikutin posisi mouse
        var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var heroSection = document.getElementById('landingHero');
        if (heroSection && !prefersReducedMotion && window.matchMedia('(pointer: fine)').matches) {
            var decoWraps = heroSection.querySelectorAll('.deco-wrap');
            heroSection.addEventListener('mousemove', function (e) {
                var rect = heroSection.getBoundingClientRect();
                var relX = (e.clientX - rect.left) / rect.width - 0.5;
                var relY = (e.clientY - rect.top) / rect.height - 0.5;
                decoWraps.forEach(function (wrap) {
                    var depth = parseFloat(wrap.getAttribute('data-depth')) || 15;
                    wrap.style.transform = 'translate(' + (relX * depth) + 'px, ' + (relY * depth) + 'px)';
                });
            });
            heroSection.addEventListener('mouseleave', function () {
                decoWraps.forEach(function (wrap) { wrap.style.transform = 'translate(0, 0)'; });
            });
        }

        // Tilt 3D ringan di kartu mockup dashboard saat mouse di atasnya
        var browserMock = document.getElementById('browserMock');
        if (browserMock && !prefersReducedMotion && window.matchMedia('(pointer: fine)').matches) {
            browserMock.addEventListener('mousemove', function (e) {
                var rect = browserMock.getBoundingClientRect();
                var relX = (e.clientX - rect.left) / rect.width - 0.5;
                var relY = (e.clientY - rect.top) / rect.height - 0.5;
                browserMock.style.transform = 'perspective(900px) rotateY(' + (relX * 8) + 'deg) rotateX(' + (relY * -8) + 'deg) scale(1.01)';
            });
            browserMock.addEventListener('mouseleave', function () {
                browserMock.style.transform = 'perspective(900px) rotateY(0deg) rotateX(0deg) scale(1)';
            });
        }
    </script>
</body>
</html>
