<?php
// Konfigurasi global: BASE_URL menyesuaikan nama folder di htdocs;
// APP_NAME/TAGLINE tampil di judul & sidebar; dua konstanta terakhir
// mengatur kunci akun sementara setelah gagal login berulang.
define('BASE_URL', '/rembuk2');
define('APP_NAME', 'REMBUK');
define('APP_TAGLINE', 'Riuh Gagasan Bermuara Jadi Tujuan.');

define('MAX_LOGIN_ATTEMPTS', 3);
define('LOCK_DURATION_MINUTES', 15);
