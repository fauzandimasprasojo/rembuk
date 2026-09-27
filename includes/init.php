<?php
/**
 * Bootstrap setiap halaman: wajib di-require PALING ATAS (sebelum logic POST).
 * Menyiapkan session, memuat config/koneksi DB/helper, sehingga proses POST
 * tidak berjalan sebelum sesi & DB siap (menutup celah keamanan lama).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/notifications.php';

// CSRF: siapkan token di setiap request, dan tolak SEMUA POST tanpa token
// yang cocok, terpusat di sini (bukan diulang di tiap halaman) supaya tidak
// ada satu pun form yang lolos tanpa proteksi.
csrf_token();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}
