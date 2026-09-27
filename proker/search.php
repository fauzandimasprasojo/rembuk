<?php
/**
 * Endpoint live-search Program Kerja (JSON untuk fetch di app.js).
 * Dipanggil dari proker/index.php: proker/search.php?q=...
 * Selalu balas JSON, memakai prepared statement (LIKE ?) supaya aman dari SQL Injection.
 * Hanya user login yang boleh mencari.
 */
require_once '../includes/init.php';
requireLogin(); // semua anggota yang login boleh mencari (mereka juga boleh melihat seluruh daftar proker)

header('Content-Type: application/json; charset=utf-8');

// Rate limit: maks 20 permintaan pencarian / 10 detik per sesi login, supaya
// endpoint ini tidak bisa dibanjiri (misalnya lewat script di luar debounce JS).
if (!rateLimit('proker_search', 20, 10)) {
    http_response_code(429);
    echo json_encode(['error' => 'Terlalu banyak permintaan pencarian. Coba lagi beberapa detik lagi.']);
    exit;
}

$q = trim($_GET['q'] ?? '');

// Query kosong -> tidak perlu buka koneksi/hasil, langsung balas array kosong
if ($q === '') {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT p.id, p.nama_proker, p.status, p.tanggal_pelaksanaan, d.nama_divisi
     FROM proker p
     JOIN divisi d ON d.id = p.divisi_id
     WHERE p.nama_proker LIKE ? ESCAPE '\\\\'
     ORDER BY p.tanggal_pelaksanaan DESC
     LIMIT 10"
);
$stmt->execute(['%' . escapeLike($q) . '%']);
$hasil = $stmt->fetchAll();

echo json_encode($hasil);
