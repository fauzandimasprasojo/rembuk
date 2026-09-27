<?php
/**
 * Notifikasi/pengingat terpusat untuk badge di topbar.
 *
 * Mengumpulkan hal yang butuh perhatian tanpa user harus buka satu-satu:
 *  - Surat menunggu peninjauan (Pending) — untuk BPH/Koordinator; untuk
 *    Anggota diganti jadi surat miliknya yang berstatus Revisi (butuh revisi).
 *  - Rapat hari ini & besok (rapat_pertemuan.tanggal BETWEEN today AND +1).
 *  - Deadline proker mendekat (H-7) + yang sudah lewat tapi belum Done.
 *  - Agenda kategori deadline dalam 7 hari ke depan.
 *
 * getNotifications() mengembalikan ['total' => int, 'items' => [...]].
 * Tiap item: ['icon','title','desc','url','tone'] — tone dipakai untuk
 * warna badge di dropdown (red/yellow/cyan/green).
 */
function getNotifications(PDO $pdo): array {
    $items = [];
    $role = $_SESSION['role'] ?? 'Anggota';
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $divisiId = $_SESSION['divisi_id'] ?? null;

    // --- 1. Surat menunggu perhatian ---
    try {
        if ($role === 'BPH') {
            $n = (int) $pdo->query("SELECT COUNT(*) FROM surat WHERE status = 'Pending'")->fetchColumn();
            if ($n > 0) {
                $items[] = [
                    'icon' => 'fa-envelope-open-text',
                    'title' => $n . ' surat menunggu peninjauan',
                    'desc' => 'Segera tinjau pengajuan surat yang masih Pending.',
                    'url' => BASE_URL . '/surat/index.php?status=Pending',
                    'tone' => 'red',
                ];
            }
        } elseif ($role === 'Koordinator Divisi' && $divisiId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM surat WHERE status = 'Pending' AND divisi_id = ?");
            $stmt->execute([$divisiId]);
            $n = (int) $stmt->fetchColumn();
            if ($n > 0) {
                $items[] = [
                    'icon' => 'fa-envelope-open-text',
                    'title' => $n . ' surat divisi menunggu peninjauan',
                    'desc' => 'Pengajuan dari divisimu yang masih Pending.',
                    'url' => BASE_URL . '/surat/index.php?status=Pending',
                    'tone' => 'red',
                ];
            }
        }
        // Semua role: surat milik sendiri yang diminta revisi.
        if ($userId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM surat WHERE user_id = ? AND status = 'Revisi'");
            $stmt->execute([$userId]);
            $nRevisi = (int) $stmt->fetchColumn();
            if ($nRevisi > 0) {
                $items[] = [
                    'icon' => 'fa-pen-to-square',
                    'title' => $nRevisi . ' surat butuh revisi',
                    'desc' => 'Perbarui draf suratmu yang dikembalikan peninjau.',
                    'url' => BASE_URL . '/surat/index.php?status=Revisi',
                    'tone' => 'yellow',
                ];
            }
        }
    } catch (Throwable $e) { /* tabel belum ada — abaikan, jangan rusak topbar */ }

    // --- 2. Rapat hari ini & besok ---
    try {
        $stmt = $pdo->query(
            "SELECT id, judul, tanggal, jam_mulai FROM rapat_pertemuan
             WHERE tanggal BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
             ORDER BY tanggal ASC, jam_mulai ASC LIMIT 5"
        );
        $rapat = $stmt ? $stmt->fetchAll() : [];
        if (!empty($rapat)) {
            $besok = date('Y-m-d', strtotime('+1 day'));
            foreach ($rapat as $r) {
                $kapan = $r['tanggal'] === date('Y-m-d') ? 'Hari ini' : 'Besok';
                $items[] = [
                    'icon' => 'fa-video',
                    'title' => $kapan . ': ' . $r['judul'],
                    'desc' => tanggalIndo($r['tanggal'], true, true) . ' · ' . substr($r['jam_mulai'], 0, 5) . ' WIB',
                    'url' => BASE_URL . '/rapat/presensi.php?id=' . $r['id'],
                    'tone' => $r['tanggal'] === $besok ? 'yellow' : 'red',
                ];
            }
        }
    } catch (Throwable $e) { /* abaikan */ }

    // --- 3. Deadline proker mendekat (H-7) + overdue belum Done ---
    try {
        $stmt = $pdo->query(
            "SELECT id, nama_proker, tanggal_pelaksanaan, tanggal_selesai,
                    LEAST(
                        DATEDIFF(COALESCE(tanggal_selesai, tanggal_pelaksanaan), CURDATE()),
                        DATEDIFF(tanggal_pelaksanaan, CURDATE())
                    ) AS sisa_hari
             FROM proker
             WHERE status != 'Done'
               AND (tanggal_pelaksanaan BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                    OR tanggal_selesai BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                    OR (COALESCE(tanggal_selesai, tanggal_pelaksanaan) < CURDATE()))
             ORDER BY (COALESCE(tanggal_selesai, tanggal_pelaksanaan) < CURDATE()) ASC,
                      ABS(DATEDIFF(COALESCE(tanggal_selesai, tanggal_pelaksanaan), CURDATE())) ASC
             LIMIT 5"
        );
        $deadlines = $stmt ? $stmt->fetchAll() : [];
        foreach ($deadlines as $d) {
            $tglAcuan = $d['tanggal_selesai'] ?? $d['tanggal_pelaksanaan'];
            $sisa = (int) round((strtotime($tglAcuan) - strtotime(date('Y-m-d'))) / 86400);
            $label = $sisa < 0 ? 'Terlewat ' . abs($sisa) . ' hari' : ($sisa === 0 ? 'Hari ini' : 'H-' . $sisa);
            $items[] = [
                'icon' => 'fa-flag-checkered',
                'title' => 'Deadline ' . $label . ': ' . $d['nama_proker'],
                'desc' => tanggalIndo($tglAcuan, true, true),
                'url' => BASE_URL . '/proker/detail.php?id=' . $d['id'],
                'tone' => $sisa < 0 ? 'red' : 'yellow',
            ];
        }
    } catch (Throwable $e) { /* abaikan */ }

    // --- 4. Agenda deadline 7 hari ke depan ---
    try {
        $stmt = $pdo->query(
            "SELECT id, judul, tanggal FROM agenda
             WHERE kategori = 'deadline' AND tanggal BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             ORDER BY tanggal ASC LIMIT 3"
        );
        $agendas = $stmt ? $stmt->fetchAll() : [];
        foreach ($agendas as $a) {
            $sisa = (int) round((strtotime($a['tanggal']) - strtotime(date('Y-m-d'))) / 86400);
            $items[] = [
                'icon' => 'fa-calendar-xmark',
                'title' => 'Agenda deadline H-' . $sisa . ': ' . $a['judul'],
                'desc' => tanggalIndo($a['tanggal'], true, true),
                'url' => BASE_URL . '/kalender/index.php?tanggal=' . $a['tanggal'],
                'tone' => 'cyan',
            ];
        }
    } catch (Throwable $e) { /* abaikan */ }

    return ['total' => count($items), 'items' => $items];
}
