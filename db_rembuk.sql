-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 27, 2026 at 02:48 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_rembuk`
--
CREATE DATABASE IF NOT EXISTS `db_rembuk` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_rembuk`;

-- --------------------------------------------------------

--
-- Table structure for table `agenda`
--

DROP TABLE IF EXISTS `agenda`;
CREATE TABLE `agenda` (
  `id` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `tanggal` date NOT NULL,
  `kategori` enum('umum','penting','deadline') DEFAULT 'umum',
  `divisi_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `agenda`
--

INSERT INTO `agenda` (`id`, `judul`, `deskripsi`, `tanggal`, `kategori`, `divisi_id`, `created_by`, `created_at`) VALUES
(1, 'HUT RI Ke-81', 'Peringatan Hari Kemerdekaan Indonesia', '2026-08-17', 'umum', NULL, 1, '2026-09-24 10:26:45'),
(2, 'Rapat Bulanan', 'Rapat Bulanan Agustus', '2026-10-01', 'penting', NULL, 1, '2026-09-24 16:19:09'),
(3, 'Rapat Bulanan', 'Rapat Bulanan September', '2026-09-01', 'penting', NULL, 1, '2026-09-24 16:20:40'),
(4, 'Modul UAS Genap 2025/2026', 'Pengadaan Modul UAS Genap 2025/2026', '2026-09-27', 'deadline', NULL, 1, '2026-09-24 16:22:59');

-- --------------------------------------------------------

--
-- Table structure for table `divisi`
--

DROP TABLE IF EXISTS `divisi`;
CREATE TABLE `divisi` (
  `id` int(11) NOT NULL,
  `nama_divisi` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `divisi`
--

INSERT INTO `divisi` (`id`, `nama_divisi`, `deskripsi`, `created_at`) VALUES
(1, 'BPH', 'Badan Pengurus Harian', '2026-09-24 06:23:36'),
(2, 'Internal', 'Program Kerja Lingkup Kampus', '2026-09-24 06:23:36'),
(3, 'Eksternal', 'Program Kerja Lingkup Masyarakat & Lembaga Luar', '2026-09-24 06:23:36'),
(4, 'Litbang', 'Penelitian, Pengembangan, & Publikasi', '2026-09-24 06:23:36');

-- --------------------------------------------------------

--
-- Table structure for table `dokumentasi`
--

DROP TABLE IF EXISTS `dokumentasi`;
CREATE TABLE `dokumentasi` (
  `id` int(11) NOT NULL,
  `proker_id` int(11) NOT NULL,
  `gambar` varchar(255) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dokumentasi`
--

INSERT INTO `dokumentasi` (`id`, `proker_id`, `gambar`, `keterangan`, `uploaded_by`, `created_at`) VALUES
(1, 13, '1790269605_6ab558a5e8740.jpg', 'Panitia Gebstat 2025', 2, '2026-09-24 17:06:45'),
(2, 13, '1790269657_6ab558d9c506d.jpg', 'Finalis Gebstat', 2, '2026-09-24 17:07:37'),
(3, 13, '1790269713_6ab55911af1a8.jpg', 'Pengerjaan Tahap Final Gebstat', 2, '2026-09-24 17:08:33'),
(4, 13, '1790269795_6ab559638507d.jpg', 'Panitia Gebstat (2)', 2, '2026-09-24 17:09:55'),
(5, 6, '1790269829_6ab559854a829.jpg', 'Peserta Open House', 2, '2026-09-24 17:10:29'),
(6, 7, '1790269895_6ab559c73d15b.jpg', 'Maskot Imapolstat Expo', 2, '2026-09-24 17:11:35'),
(7, 7, '1790269968_6ab55a105c5c7.jpg', 'Stand Imapolstat Expo', 2, '2026-09-24 17:12:48'),
(8, 7, '1790270010_6ab55a3a05729.jpg', 'Penghargaan Imapolstat Expo', 2, '2026-09-24 17:13:30'),
(9, 8, '1790270122_6ab55aaa527c3.jpg', 'Acara Puncak Open Recruitment', 2, '2026-09-24 17:15:22');

-- --------------------------------------------------------

--
-- Table structure for table `keuangan`
--

DROP TABLE IF EXISTS `keuangan`;
CREATE TABLE `keuangan` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `jenis` enum('Pemasukan','Pengeluaran') NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `proker_id` int(11) DEFAULT NULL,
  `bukti_file` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `keuangan`
--

INSERT INTO `keuangan` (`id`, `tanggal`, `keterangan`, `jenis`, `jumlah`, `proker_id`, `bukti_file`, `created_by`, `created_at`) VALUES
(1, '2026-09-24', 'Uang Pendaftaran', 'Pemasukan', 10000000.00, 4, NULL, 1, '2026-09-24 16:24:01'),
(2, '2026-09-24', 'Insentif Pengajar', 'Pengeluaran', 4500000.00, 4, NULL, 1, '2026-09-24 16:24:47'),
(3, '2026-09-24', 'Sewa Kamera', 'Pengeluaran', 250000.00, 10, NULL, 1, '2026-09-24 16:25:17'),
(4, '2026-09-24', 'Pengadaan Lembar Soal', 'Pengeluaran', 120000.00, 13, NULL, 1, '2026-09-24 16:25:47'),
(5, '2026-09-24', 'Dekorasi', 'Pengeluaran', 175000.00, 7, NULL, 1, '2026-09-24 16:26:07'),
(6, '2026-09-24', 'Hadiah Games', 'Pengeluaran', 30000.00, 6, NULL, 1, '2026-09-24 16:26:45'),
(7, '2026-09-24', 'Sewa Tempat', 'Pengeluaran', 900000.00, 8, NULL, 1, '2026-09-24 16:27:28'),
(8, '2026-09-24', 'Canva Premium', 'Pengeluaran', 130000.00, 11, NULL, 1, '2026-09-24 16:27:56'),
(9, '2026-09-24', 'Konsumsi Pemateri', 'Pengeluaran', 40000.00, 12, NULL, 1, '2026-09-24 16:28:20'),
(10, '2026-09-24', 'Uang Pendaftaran', 'Pemasukan', 3100000.00, 9, NULL, 1, '2026-09-24 16:28:42'),
(11, '2026-09-24', 'Uang Pendaftaran', 'Pemasukan', 2750000.00, 2, NULL, 1, '2026-09-24 16:29:04'),
(12, '2026-09-27', 'Canva Premium', 'Pengeluaran', 99997.00, 11, NULL, 1, '2026-09-27 12:18:22');

-- --------------------------------------------------------

--
-- Table structure for table `keuangan_log`
--

DROP TABLE IF EXISTS `keuangan_log`;
CREATE TABLE `keuangan_log` (
  `id` int(11) NOT NULL,
  `keuangan_id` int(11) DEFAULT NULL,
  `aksi` enum('tambah','ubah','hapus') NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `jumlah_lama` decimal(15,2) DEFAULT NULL,
  `jumlah_baru` decimal(15,2) DEFAULT NULL,
  `data_lama` text DEFAULT NULL,
  `data_baru` text DEFAULT NULL,
  `oleh_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `keuangan_log`
--

INSERT INTO `keuangan_log` (`id`, `keuangan_id`, `aksi`, `keterangan`, `jumlah_lama`, `jumlah_baru`, `data_lama`, `data_baru`, `oleh_user_id`, `created_at`) VALUES
(3, 12, 'tambah', 'Canva Premium', NULL, 99997.00, NULL, '{\"tanggal\":\"2026-09-27\",\"keterangan\":\"Canva Premium\",\"jenis\":\"Pengeluaran\",\"jumlah\":99997,\"proker_id\":\"11\"}', 1, '2026-09-27 12:18:22');

-- --------------------------------------------------------

--
-- Table structure for table `presensi`
--

DROP TABLE IF EXISTS `presensi`;
CREATE TABLE `presensi` (
  `id` int(11) NOT NULL,
  `rapat_pertemuan_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('Hadir','Izin','Alpa') NOT NULL DEFAULT 'Hadir',
  `waktu_presensi` datetime DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `dicatat_oleh` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proker`
--

DROP TABLE IF EXISTS `proker`;
CREATE TABLE `proker` (
  `id` int(11) NOT NULL,
  `nama_proker` varchar(200) NOT NULL,
  `divisi_id` int(11) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `tanggal_pelaksanaan` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `status` enum('To-do','In Progress','Done') DEFAULT 'To-do',
  `progress_persen` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `proker`
--

INSERT INTO `proker` (`id`, `nama_proker`, `divisi_id`, `deskripsi`, `tanggal_pelaksanaan`, `tanggal_selesai`, `status`, `progress_persen`, `created_by`, `created_at`) VALUES
(2, 'Try Out Nasional (TONAS)', 3, NULL, '2026-08-30', '2026-08-31', 'Done', 100, 1, '2026-09-24 09:15:07'),
(4, 'Bimbingan Intensif', 3, NULL, '2026-07-07', '2026-07-26', 'To-do', 0, 1, '2026-09-24 09:27:37'),
(6, 'Open House', 2, NULL, '2026-03-30', '2026-04-02', 'In Progress', 50, 1, '2026-09-24 09:28:47'),
(7, 'Imapolstat Expo', 2, NULL, '2026-04-03', NULL, 'In Progress', 50, 1, '2026-09-24 09:29:18'),
(8, 'Open Recruitment', 2, NULL, '2026-04-06', '2026-05-15', 'Done', 100, 1, '2026-09-24 09:29:49'),
(9, 'Try Out Akbar (TOBAR)', 3, NULL, '2026-07-04', '2026-08-02', 'To-do', 0, 1, '2026-09-24 16:11:38'),
(10, 'Feed Kepengurusan', 4, NULL, '2026-05-30', NULL, 'To-do', 0, 1, '2026-09-24 16:12:50'),
(11, 'Pelatihan Design', 4, NULL, '2026-10-10', NULL, 'To-do', 0, 1, '2026-09-24 16:13:23'),
(12, 'Pelatihan Modul', 4, NULL, '2026-10-20', NULL, 'To-do', 0, 1, '2026-09-24 16:13:48'),
(13, 'Gebyar Statistika', 1, NULL, '2026-09-07', '2026-09-16', 'To-do', 0, 1, '2026-09-24 16:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `rapat_pertemuan`
--

DROP TABLE IF EXISTS `rapat_pertemuan`;
CREATE TABLE `rapat_pertemuan` (
  `id` int(11) NOT NULL,
  `rapat_rutin_id` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time DEFAULT NULL,
  `zoom_link` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rapat_pertemuan`
--

INSERT INTO `rapat_pertemuan` (`id`, `rapat_rutin_id`, `judul`, `tanggal`, `jam_mulai`, `jam_selesai`, `zoom_link`, `created_at`) VALUES
(1, 1, 'Rapat Rutin', '2026-10-02', '19:45:00', '21:00:00', 'https://us06web.zoom.us/j/3695195708?pwd=MC9HWHlhZkRTc0o5aUlKejJqMWx5dz09', '2026-09-27 07:42:54'),
(2, 1, 'Rapat Rutin', '2026-10-09', '19:45:00', '21:00:00', 'https://us06web.zoom.us/j/3695195708?pwd=MC9HWHlhZkRTc0o5aUlKejJqMWx5dz09', '2026-09-27 07:42:54'),
(3, 1, 'Rapat Rutin', '2026-10-16', '19:45:00', '21:00:00', 'https://us06web.zoom.us/j/3695195708?pwd=MC9HWHlhZkRTc0o5aUlKejJqMWx5dz09', '2026-09-27 07:42:54'),
(4, 1, 'Rapat Rutin', '2026-10-23', '19:45:00', '21:00:00', 'https://us06web.zoom.us/j/3695195708?pwd=MC9HWHlhZkRTc0o5aUlKejJqMWx5dz09', '2026-09-27 07:42:54');

-- --------------------------------------------------------

--
-- Table structure for table `rapat_rutin`
--

DROP TABLE IF EXISTS `rapat_rutin`;
CREATE TABLE `rapat_rutin` (
  `id` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `hari` tinyint(1) NOT NULL COMMENT '1=Senin ... 7=Minggu, cocok dgn date(N)',
  `pengulangan` enum('Mingguan','Dwi-Mingguan') NOT NULL DEFAULT 'Mingguan',
  `jam_mulai` time NOT NULL,
  `jam_selesai` time DEFAULT NULL,
  `zoom_link` varchar(500) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rapat_rutin`
--

INSERT INTO `rapat_rutin` (`id`, `judul`, `deskripsi`, `hari`, `pengulangan`, `jam_mulai`, `jam_selesai`, `zoom_link`, `created_by`, `created_at`) VALUES
(1, 'Rapat Rutin', 'Pembahasan Program Kerja', 5, 'Mingguan', '19:45:00', '21:00:00', 'https://us06web.zoom.us/j/3695195708?pwd=MC9HWHlhZkRTc0o5aUlKejJqMWx5dz09', 1, '2026-09-27 07:42:54');

-- --------------------------------------------------------

--
-- Table structure for table `surat`
--

DROP TABLE IF EXISTS `surat`;
CREATE TABLE `surat` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `divisi_id` int(11) DEFAULT NULL,
  `tentang` varchar(200) NOT NULL,
  `tujuan` varchar(200) NOT NULL,
  `file_surat` varchar(255) DEFAULT NULL,
  `nomor_surat` varchar(50) DEFAULT NULL,
  `status` enum('Pending','Revisi','Diterima','Ditolak') DEFAULT 'Pending',
  `catatan` text DEFAULT NULL,
  `file_final` varchar(255) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `surat`
--

INSERT INTO `surat` (`id`, `user_id`, `divisi_id`, `tentang`, `tujuan`, `file_surat`, `nomor_surat`, `status`, `catatan`, `file_final`, `reviewed_by`, `created_at`, `updated_at`) VALUES
(4, 17, 4, 'Peminjaman Inventaris', 'Sekretaris', '1790267674__BIMBEL__Format_Surat_Undangan.docx.pdf', NULL, 'Pending', NULL, NULL, NULL, '2026-09-24 16:34:34', '2026-09-24 16:34:34'),
(5, 5, 2, 'Undangan Gathering', 'Ketua', '1790267788__BIMBEL__Format_Surat_Undangan.docx.pdf', NULL, 'Ditolak', 'Kegiatan ketika perkuliahan berlangsung', NULL, 2, '2026-09-24 16:36:28', '2026-09-24 16:42:14'),
(6, 19, 4, 'Proposal Pelatihan', 'Sekretaris', '1790267853__BIMBEL__Format_Surat_Undangan.docx.pdf', NULL, 'Revisi', 'Sesuaikan timeline kampus', NULL, 2, '2026-09-24 16:37:33', '2026-09-24 16:41:44'),
(7, 30, 3, 'LPJ TONAS 2026', 'Ketua', '1790267929__BIMBEL__Format_Surat_Undangan.docx.pdf', '001/REMBUK/IX/2026', 'Diterima', '', '1790268067_final__BIMBEL__Format_Surat_Undangan.docx.pdf', 2, '2026-09-24 16:38:49', '2026-09-24 16:41:07'),
(8, 18, 4, 'Undangan Seminar', 'BPH', '1790267993__BIMBEL__Format_Surat_Undangan.docx.pdf', NULL, 'Revisi', 'Format masih salah', NULL, 2, '2026-09-24 16:39:53', '2026-09-24 16:40:48');

-- --------------------------------------------------------

--
-- Table structure for table `surat_log`
--

DROP TABLE IF EXISTS `surat_log`;
CREATE TABLE `surat_log` (
  `id` int(11) NOT NULL,
  `surat_id` int(11) NOT NULL,
  `status` enum('Pending','Revisi','Diterima','Ditolak') NOT NULL,
  `catatan` text DEFAULT NULL,
  `oleh_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `surat_log`
--

INSERT INTO `surat_log` (`id`, `surat_id`, `status`, `catatan`, `oleh_user_id`, `created_at`) VALUES
(5, 4, 'Pending', 'Pengajuan baru dibuat.', 17, '2026-09-24 16:34:34'),
(6, 5, 'Pending', 'Pengajuan baru dibuat.', 5, '2026-09-24 16:36:28'),
(7, 6, 'Pending', 'Pengajuan baru dibuat.', 19, '2026-09-24 16:37:33'),
(8, 7, 'Pending', 'Pengajuan baru dibuat.', 30, '2026-09-24 16:38:49'),
(9, 8, 'Pending', 'Pengajuan baru dibuat.', 18, '2026-09-24 16:39:53'),
(10, 8, 'Revisi', 'Format masih salah', 2, '2026-09-24 16:40:48'),
(11, 7, 'Diterima', '', 2, '2026-09-24 16:40:58'),
(12, 7, 'Diterima', 'Dokumen final diunggah.', 2, '2026-09-24 16:41:07'),
(13, 6, 'Revisi', 'Sesuaikan timeline kampus', 2, '2026-09-24 16:41:44'),
(14, 5, 'Ditolak', 'Kegiatan ketika perkuliahan berlangsung', 2, '2026-09-24 16:42:14');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `proker_id` int(11) NOT NULL,
  `detail_jobdesk` varchar(255) NOT NULL,
  `pic_id` int(11) DEFAULT NULL,
  `status` enum('To-do','In Progress','Done') DEFAULT 'To-do',
  `lampiran` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `proker_id`, `detail_jobdesk`, `pic_id`, `status`, `lampiran`, `created_at`) VALUES
(1, 2, 'Menyusun Proposal', 32, 'Done', '', '2026-09-24 09:25:28'),
(2, 6, 'Menyusun Proposal', 7, 'Done', '', '2026-09-24 16:29:33'),
(3, 6, 'Menyusun Rundown', 5, 'In Progress', '', '2026-09-24 16:29:45'),
(4, 7, 'Menyusun Proposal', 4, 'Done', '', '2026-09-24 16:30:11'),
(5, 7, 'Menyusun Ranggar', 13, 'In Progress', '', '2026-09-24 16:30:24'),
(6, 8, 'Menyusun tahapan', 15, 'Done', '', '2026-09-24 16:31:09'),
(7, 8, 'Menyusun Rubrik Penilaian', 14, 'Done', '', '2026-09-24 16:31:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama_lengkap` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `pin` varchar(255) NOT NULL,
  `role` enum('BPH','Koordinator Divisi','Anggota') NOT NULL DEFAULT 'Anggota',
  `divisi_id` int(11) DEFAULT NULL,
  `jabatan` varchar(100) DEFAULT NULL,
  `avatar` varchar(30) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama_lengkap`, `username`, `password`, `pin`, `role`, `divisi_id`, `jabatan`, `avatar`, `status`, `failed_attempts`, `locked_until`, `created_at`) VALUES
(1, 'Fauzan Dimas Prasojo', 'fauzandimas', '$2y$10$ez39M7K8m.RLRj2l7HZoQeeibpgDubJP90WoKniKdJTJQQ/wSJuHK', '$2y$10$vH8aeVCRkKKjGvck8YrzMOY56Att59Wq4YlTWjI6p.xeVYjd2TAS2', 'BPH', 1, NULL, 'yellow-crown', 'aktif', 0, NULL, '2026-09-24 06:24:08'),
(2, 'Marva Ghevirani', 'marvaghevirani', '$2y$10$tjvR3ZCiyIKPuS2z4gmiSeZCfGinNB60b8pRk7Rc0Iilv3/M4jAS2', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'BPH', 1, 'Sekretaris', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(3, 'Adella Rizka Afifah', 'adellarizka', '$2y$10$M9YEHYD8hqJBORpCRoY4D.aZ63PtPsO6HpTr.QhvorXpEOAdtH5c6', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'BPH', 1, 'Bendahara', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(4, 'Aulliah Effiana Arafat', 'aulliaheffiana', '$2y$10$Z10PjGCMBExHHeJnz/i0seTqE9VVnMN/.DSWsly87RcKsPc6Rtdky', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Koordinator Divisi', 2, 'Kepala Divisi Internal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(5, 'M. Hanif Indriawan', 'mhanif', '$2y$10$yhKmkhbmAPxWY0/RYSidwup64kQI.65ZWoNyXkZIlOfIPZchBi5ay', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 2, 'Anggota Internal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(6, 'Alvien Faiz Refansyah', 'alvienfaiz', '$2y$10$eK0ZYpie8b3tr..kQd6I7OGFEcF.c.KtKH8hPql8b1Kw.CpMu4BNa', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 2, 'Anggota Internal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(7, 'Aqilah Dzakiyah', 'aqilahdzakiyah', '$2y$10$lworzjRaxK7tHOE1VMv60uq3f82EwZGbOUJJzFam0cwRCfbRi5je6', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 2, 'Anggota Internal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(8, 'Yehiskiel Erwin Tambunan', 'yehiskielerwin', '$2y$10$bX.c60StuR.vMBEVEUFo2OdXApcrcKCcKQTimv/RKRYU5TJvzZj46', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 2, 'Anggota Internal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(9, 'Natzwa Uffatul Ismi', 'natzwauffatul', '$2y$10$HlWZpO02NxcqUCLPbZEweOAYHwQb6lG/pEWinpHxiP4rXxBZ1K9/m', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 2, 'Anggota Internal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(10, 'Kadek Desy Sita Dewi', 'kadekdesy', '$2y$10$t1QVyPpVGMpHsMiZA9e02uEPu3XtUSkTD.Yf1Oq.l.QgVBLMqEgxK', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 2, 'Anggota Internal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(11, 'Ahmad Faisal Dwi Siswanto', 'ahmadfaisal', '$2y$10$dmIHFir.miDWn6aYIqA8m.5/OJLAryiqYy5zk3tKqdcKtNvGYaS/y', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 2, 'Anggota Internal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(12, 'Cut Atikah Ramadhani', 'cutatikah', '$2y$10$7iNlzPbA7993Es9J7QA1eOEUxYWmnQCUF7pecD3fUjBI1bQa5SfSG', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 2, 'Anggota Internal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(13, 'I Gede Juliana Raga Saputra', 'igede', '$2y$10$Rk4UTfG4CfUAzSohfoTnpOW5ujDooZ5bQfXaBgDgQNcoeb4l00UDG', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 2, 'Anggota Internal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(14, 'Ibnu Raihan', 'ibnuraihan', '$2y$10$y8lC9IjKfpCwOkIlOXH.ueOC/Oo8/uVogUw0RaVwAZ4CsVnbPIT5m', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 2, 'Anggota Internal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(15, 'Aryadinata Akira Syadid', 'aryadinataakira', '$2y$10$CfUenyEDWafpzqda/AnJmumYRRogLUh4aIClLu.ugNVqukUYuqsjS', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 2, 'Anggota Internal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(16, 'Syifa Julia Putri', 'syifajulia', '$2y$10$IizW/CBQDsZwha63E3mO5OGOxyF60Nsdlqq2jwopHKJlUsV5nj/W6', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 2, 'Anggota Internal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(17, 'Imam Mansyur', 'imammansyur', '$2y$10$rdGLnLntG6JQOw0xonCETOIPxLEoR6gZlscymCErMMRbOqgqNzFNe', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Koordinator Divisi', 4, 'Kepala Divisi Litbang', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(18, 'Widyasti Bella Kurnia', 'widyastibella', '$2y$10$myg17SDkWa1Hq/wESrbe1OtFujf8V2CyAPWk8UbK53pYCM6yhQbry', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 4, 'Anggota Litbang', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(19, 'Raldin', 'raldin', '$2y$10$RKwcX/aI6N6kTP25p7Vxn.CQ9dqqrLm76Mm3SgwYupUm6C/Rvxdwe', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 4, 'Anggota Litbang', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(20, 'Evi Maslakhatul Ummah', 'evimaslakhatul', '$2y$10$xAvs5FLC7uXqt./ryE.VtOhrrsf8N6GXQ8gKGHYV9rWs5sF4WmZ82', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 4, 'Anggota Litbang', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(21, 'Naila Anindia Maharani', 'nailaanindia', '$2y$10$myCEDGsE6huljI16bA61x.geN5fmUEwCKSWw6MfK/Z1PSPx5FCFNq', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 4, 'Anggota Litbang', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(22, 'Asyam Irsyad Zahir', 'asyamirsyad', '$2y$10$OS2OwVeWgL7wcktSc6fE4uXcc6e3pQB9OkVpwbPxFFNb5qBXVG9Ia', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 4, 'Anggota Litbang', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(23, 'Sabilla Rizkia Rahmadini', 'sabillarizkia', '$2y$10$SEbGdklgWq8ESfbiWfwbKOL02fjDpe49UxjfF34OJhO8hOhU/EoUe', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 4, 'Anggota Litbang', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(24, 'Disnaysila Aulia Putri', 'disnaysilaaulia', '$2y$10$HhiT2z8wujm45mly.4g9nufrOy2LgKd2m/wFfJjWSnFTYiumOmgwm', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 4, 'Anggota Litbang', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(25, 'Nurlista Meytrisiana Bende', 'nurlistameytrisiana', '$2y$10$3s4dduz3.0BybkLz97OBreHi4UNY20DPXIEZ/jBGR9Q27WCHfBP4q', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 4, 'Anggota Litbang', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(26, 'Rachmat Bahtiar Zanuaji', 'rachmatbahtiar', '$2y$10$AD7GkkOEW/OZiul6hjxG0exoWuGYVZfP5loCroURCbZuMDuGZlZHi', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 4, 'Anggota Litbang', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(27, 'Muh. Luthfi Farel Gailea', 'muhluthfi', '$2y$10$11mUe0mVcOeq394r.tlCBe21AdvvzYY.WB.536vLuY67zwMhjVVvu', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 4, 'Anggota Litbang', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(28, 'Era Asta Lini', 'eraasta', '$2y$10$VpBnYcDHZoST8U3VAlISmeDOOYtW/roE2dcfz12.bHda66b3M1pN6', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 4, 'Anggota Litbang', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(29, 'Muhammad Thurfa Naundza', 'muhammadthurfa', '$2y$10$uOxT1c1hHtjL3698EaPRHee/oztgsfTC6aOk5gKn1LOtMQ8/n2N2u', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Koordinator Divisi', 3, 'Kepala Divisi Eksternal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(30, 'Anggia Maulida', 'anggiamaulida', '$2y$10$SJZtR1TvXmdv1w39OFewxuUF5coc2iF8uk0ygO5xoq0dfo2QTIxf.', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 3, 'Anggota Eksternal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(31, 'Futuh Rajana Adzikro', 'futuhrajana', '$2y$10$3XbkI221P0f06PedU8gzBes8SaFMCi7cF6.iEkEEfZhXSvrndOdOK', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 3, 'Anggota Eksternal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(32, 'Amaika Deppi Cahayani', 'amaikadeppi', '$2y$10$oO2OEbczFCj0sGqY861sG.kp/38EiCzbPe7p5EiQtlXXMyB4029UO', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 3, 'Anggota Eksternal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(33, 'Al-Aziz Kurniawan', 'alazizkurniawan', '$2y$10$YOO3BRZJbmKT/M9jHZsvRulofhP.Q5tkNALOdrly.OsrEB1z0Hiba', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 3, 'Anggota Eksternal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(34, 'Leony Marcella Lolo', 'leonymarcella', '$2y$10$zMQgZSYqbvBoE3pEe2Y6nuGlPfXhSmgXYjoUn..vZ5x6QEjPIPAI6', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 3, 'Anggota Eksternal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(35, 'Sultan Ahmad Alhidayah', 'sultanahmad', '$2y$10$dlgUpb3D2LOjkQXLQMTS7OUR.iA8cEsyOj4TPApz.YA/JjNpL7wAi', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 3, 'Anggota Eksternal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04'),
(36, 'May Honey Amaradana', 'mayhoney', '$2y$10$ASAKzO5nKQbFe5dlzytKi.M34ZmQ1YPqWpLCvEUSz1WyCZbJCsKX6', '$2y$10$O0olLhAjrt4Zi6F1rkKGgua3TFKRU8SLrhm6IxBwTzLvDBv9AOJvi', 'Anggota', 3, 'Anggota Eksternal', NULL, 'aktif', 0, NULL, '2026-09-24 06:39:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agenda`
--
ALTER TABLE `agenda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `divisi_id` (`divisi_id`),
  ADD KEY `agenda_ibfk_2` (`created_by`);

--
-- Indexes for table `divisi`
--
ALTER TABLE `divisi`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dokumentasi`
--
ALTER TABLE `dokumentasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proker_id` (`proker_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `keuangan`
--
ALTER TABLE `keuangan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proker_id` (`proker_id`),
  ADD KEY `keuangan_ibfk_2` (`created_by`);

--
-- Indexes for table `keuangan_log`
--
ALTER TABLE `keuangan_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `keuangan_id` (`keuangan_id`),
  ADD KEY `oleh_user_id` (`oleh_user_id`);

--
-- Indexes for table `presensi`
--
ALTER TABLE `presensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_pertemuan_user` (`rapat_pertemuan_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `dicatat_oleh` (`dicatat_oleh`);

--
-- Indexes for table `proker`
--
ALTER TABLE `proker`
  ADD PRIMARY KEY (`id`),
  ADD KEY `divisi_id` (`divisi_id`),
  ADD KEY `proker_ibfk_2` (`created_by`);

--
-- Indexes for table `rapat_pertemuan`
--
ALTER TABLE `rapat_pertemuan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rapat_rutin_id` (`rapat_rutin_id`),
  ADD KEY `tanggal` (`tanggal`);

--
-- Indexes for table `rapat_rutin`
--
ALTER TABLE `rapat_rutin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `surat`
--
ALTER TABLE `surat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_surat` (`nomor_surat`),
  ADD KEY `divisi_id` (`divisi_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `surat_ibfk_1` (`user_id`);

--
-- Indexes for table `surat_log`
--
ALTER TABLE `surat_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `surat_id` (`surat_id`),
  ADD KEY `surat_log_ibfk_2` (`oleh_user_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proker_id` (`proker_id`),
  ADD KEY `pic_id` (`pic_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `divisi_id` (`divisi_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agenda`
--
ALTER TABLE `agenda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `divisi`
--
ALTER TABLE `divisi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `dokumentasi`
--
ALTER TABLE `dokumentasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `keuangan`
--
ALTER TABLE `keuangan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `keuangan_log`
--
ALTER TABLE `keuangan_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `presensi`
--
ALTER TABLE `presensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proker`
--
ALTER TABLE `proker`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `rapat_pertemuan`
--
ALTER TABLE `rapat_pertemuan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `rapat_rutin`
--
ALTER TABLE `rapat_rutin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `surat`
--
ALTER TABLE `surat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `surat_log`
--
ALTER TABLE `surat_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agenda`
--
ALTER TABLE `agenda`
  ADD CONSTRAINT `agenda_ibfk_1` FOREIGN KEY (`divisi_id`) REFERENCES `divisi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agenda_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dokumentasi`
--
ALTER TABLE `dokumentasi`
  ADD CONSTRAINT `dokumentasi_ibfk_1` FOREIGN KEY (`proker_id`) REFERENCES `proker` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dokumentasi_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `keuangan`
--
ALTER TABLE `keuangan`
  ADD CONSTRAINT `keuangan_ibfk_1` FOREIGN KEY (`proker_id`) REFERENCES `proker` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `keuangan_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `keuangan_log`
--
ALTER TABLE `keuangan_log`
  ADD CONSTRAINT `keuangan_log_ibfk_1` FOREIGN KEY (`keuangan_id`) REFERENCES `keuangan` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `keuangan_log_ibfk_2` FOREIGN KEY (`oleh_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `presensi`
--
ALTER TABLE `presensi`
  ADD CONSTRAINT `fk_presensi_dicatat_oleh` FOREIGN KEY (`dicatat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_presensi_pertemuan` FOREIGN KEY (`rapat_pertemuan_id`) REFERENCES `rapat_pertemuan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_presensi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proker`
--
ALTER TABLE `proker`
  ADD CONSTRAINT `proker_ibfk_1` FOREIGN KEY (`divisi_id`) REFERENCES `divisi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proker_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `rapat_pertemuan`
--
ALTER TABLE `rapat_pertemuan`
  ADD CONSTRAINT `fk_pertemuan_rutin` FOREIGN KEY (`rapat_rutin_id`) REFERENCES `rapat_rutin` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rapat_rutin`
--
ALTER TABLE `rapat_rutin`
  ADD CONSTRAINT `fk_rapatrutin_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `surat`
--
ALTER TABLE `surat`
  ADD CONSTRAINT `surat_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `surat_ibfk_2` FOREIGN KEY (`divisi_id`) REFERENCES `divisi` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `surat_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `surat_log`
--
ALTER TABLE `surat_log`
  ADD CONSTRAINT `surat_log_ibfk_1` FOREIGN KEY (`surat_id`) REFERENCES `surat` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `surat_log_ibfk_2` FOREIGN KEY (`oleh_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`proker_id`) REFERENCES `proker` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`pic_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`divisi_id`) REFERENCES `divisi` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
