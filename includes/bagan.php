<?php
// Bagan struktur organisasi (di-include dashboard & kelola divisi; $pdo sudah tersedia).
// Nama BPH ditulis manual di bawah; nama Koordinator diambil otomatis dari tabel users.
$baganBph = baganBphNames();

// Nama koordinator diambil otomatis: user dengan role "Koordinator Divisi" di tiap divisi.
$baganDivisi = $pdo->query(
    "SELECT d.nama_divisi, GROUP_CONCAT(u.nama_lengkap SEPARATOR ', ') AS koor
     FROM divisi d
     LEFT JOIN users u ON u.divisi_id = d.id AND u.role = 'Koordinator Divisi'
     WHERE d.nama_divisi <> 'BPH'
     GROUP BY d.id, d.nama_divisi ORDER BY d.nama_divisi"
)->fetchAll();
?>
<div class="org-scroll"><div class="org-tree"><ul><li>
    <div class="org-node">
        <div class="org-head org-head-chair">Ketua</div>
        <div class="org-name"><?= e($baganBph['Ketua']) ?></div>
    </div>
    <ul><li>
        <div class="org-group org-child">
            <?php foreach (['Sekretaris', 'Bendahara'] as $jabatan): ?>
                <div class="org-node">
                    <div class="org-head org-head-officer"><?= $jabatan ?></div>
                    <div class="org-name"><?= e($baganBph[$jabatan]) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($baganDivisi): ?>
        <ul>
            <?php foreach ($baganDivisi as $d): ?>
                <li>
                    <div class="org-node org-child">
                        <div class="org-head org-head-division"><?= e($d['nama_divisi']) ?></div>
                        <div class="org-name"><span class="org-sub">Koordinator</span><?= e($d['koor'] ?: '-') ?></div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </li></ul>
</li></ul></div></div>
