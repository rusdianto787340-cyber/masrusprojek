-- phpMyAdmin SQL Dump
-- version 5.1.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 18 Nov 2025 pada 08.20
-- Versi server: 10.4.18-MariaDB
-- Versi PHP: 8.0.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tfidf_search`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `simpan_hasil`
--

CREATE TABLE `simpan_hasil` (
  `Id` int(11) NOT NULL,
  `DocId` int(11) NOT NULL,
  `Hasil_Bobot_Akhir` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbcache`
--

CREATE TABLE `tbcache` (
  `Id` int(11) NOT NULL,
  `Query` varchar(100) NOT NULL,
  `DocId` int(11) NOT NULL,
  `Value` float NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbdokumen`
--

CREATE TABLE `tbdokumen` (
  `id` int(11) NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `isi_dokumen` text DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `tbdokumen`
--

INSERT INTO `tbdokumen` (`id`, `nama_file`, `isi_dokumen`, `file_type`, `file_size`, `file_path`) VALUES
(53, 'DOKUMEN4.pdf', 'Laporna harian tentang udang dan ikan di sungai', 'pdf', 36044, 'uploads/6915a4c66fb1c_1763026118.pdf'),
(54, 'DOKUMEN2.pdf', 'PRODUKSI IKAN TAHUN 2025', 'pdf', 38203, 'uploads/6915a4e674c81_1763026150.pdf'),
(55, 'Lembar Konsultasi.pdf', 'POLNES\n\nwebsite: www.polnes.ac.id\n\nKEMENTERIAN PENDIDIKAN DAN KEBUDAYAAN\nPOLITEKNIK NEGERI SAMARINDA\n\nJI. DR.Ciptomangunkusumo Kampus Gunung Lipan Samarinda 75131\nTelp. 0541-260588, 260553, 262018 Fax. 0541-260355\n\nLEMBAR KONSULTASI\nPRAKTEK KERJA LAPANGAN\nNAMA MAHASISWA Rusdianto\nNIM 236151025\nDOSEN PEMBIMBING Fajerin Biabdillah, M.Kom\nPARAF DOSEN\nNO. HARI/TANGGAL KEGIATAN KONSULTASI PEMBIMBING\n1 Selasa, 29 April 2025 | Perkenalan dengan pembimbing dan membahas\nproyek yang akan di buat. 4\n2 Kamis, 8 Mei 2025 Diskusi mengenai perubahan proyek PKL, dari\n\npembuatan aplikasi absensi kru studio ke\n\npembuatan video profil Islamic Center Kaltim.\n\n3 Selasa, 3 Juni 2025 Bertanya melalui Whatsaap mengenai laporan\nPKL pada bagian saran di bab 4.\n\n4 Rabu, 4 Juni 2025 Melakukan bimbingan atau meminta pendapat\nmengenai laporan yang sudah selesai ( revisi).\n\n5 Selasa, 10 Juni 2025 | Konfirmasi kembali mengenai laporan PKL\n\nyang sudah di revisi atau di perbaiki.\n\nA\nZz,\nZa\nLP\n\nDisetujui,\n\nDosen Pembimbing PKL\n\nFajerin Biabdillah, M.Kom\nNIP. 199409292024061001', 'pdf', 572500, 'uploads/691c09855aa27_1763445125.pdf'),
(56, 'Lembar Konsultasi.pdf', 'POLNES\n\nwebsite: www.polnes.ac.id\n\nKEMENTERIAN PENDIDIKAN DAN KEBUDAYAAN\nPOLITEKNIK NEGERI SAMARINDA\n\nJI. DR.Ciptomangunkusumo Kampus Gunung Lipan Samarinda 75131\nTelp. 0541-260588, 260553, 262018 Fax. 0541-260355\n\nLEMBAR KONSULTASI\nPRAKTEK KERJA LAPANGAN\nNAMA MAHASISWA Rusdianto\nNIM 236151025\nDOSEN PEMBIMBING Fajerin Biabdillah, M.Kom\nPARAF DOSEN\nNO. HARI/TANGGAL KEGIATAN KONSULTASI PEMBIMBING\n1 Selasa, 29 April 2025 | Perkenalan dengan pembimbing dan membahas\nproyek yang akan di buat. 4\n2 Kamis, 8 Mei 2025 Diskusi mengenai perubahan proyek PKL, dari\n\npembuatan aplikasi absensi kru studio ke\n\npembuatan video profil Islamic Center Kaltim.\n\n3 Selasa, 3 Juni 2025 Bertanya melalui Whatsaap mengenai laporan\nPKL pada bagian saran di bab 4.\n\n4 Rabu, 4 Juni 2025 Melakukan bimbingan atau meminta pendapat\nmengenai laporan yang sudah selesai ( revisi).\n\n5 Selasa, 10 Juni 2025 | Konfirmasi kembali mengenai laporan PKL\n\nyang sudah di revisi atau di perbaiki.\n\nA\nZz,\nZa\nLP\n\nDisetujui,\n\nDosen Pembimbing PKL\n\nFajerin Biabdillah, M.Kom\nNIP. 199409292024061001', 'pdf', 572500, 'uploads/691c0c23c3b9c_1763445795.pdf');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbindex`
--

CREATE TABLE `tbindex` (
  `Id` int(11) NOT NULL,
  `Term` varchar(30) NOT NULL,
  `DocId` int(11) NOT NULL,
  `Count` int(11) NOT NULL,
  `Bobot` float NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `tbindex`
--

INSERT INTO `tbindex` (`Id`, `Term`, `DocId`, `Count`, `Bobot`) VALUES
(1, 'laporna', 53, 1, 1.60944),
(2, 'harian', 53, 1, 1.60944),
(3, 'tentang', 53, 1, 1.60944),
(4, 'udang', 53, 1, 1.60944),
(5, 'ikan', 53, 1, 0.916291),
(6, 'sungai', 53, 1, 1.60944),
(7, 'produksi', 54, 1, 1.60944),
(8, 'ikan', 54, 1, 0.916291),
(9, 'polnes', 55, 2, 1.83258),
(10, 'website', 55, 1, 0.916291),
(11, 'www', 55, 1, 0.916291),
(12, 'kementerian', 55, 1, 0.916291),
(13, 'pendidikan', 55, 1, 0.916291),
(14, 'kebudayaan', 55, 1, 0.916291),
(15, 'politeknik', 55, 1, 0.916291),
(16, 'negeri', 55, 1, 0.916291),
(17, 'samarinda', 55, 2, 1.83258),
(18, 'ciptomangunkusumo', 55, 1, 0.916291),
(19, 'kampus', 55, 1, 0.916291),
(20, 'gunung', 55, 1, 0.916291),
(21, 'lipan', 55, 1, 0.916291),
(22, 'telp', 55, 1, 0.916291),
(23, 'fax', 55, 1, 0.916291),
(24, 'lembar', 55, 1, 0.916291),
(25, 'konsultasi', 55, 2, 1.83258),
(26, 'praktek', 55, 1, 0.916291),
(27, 'kerja', 55, 1, 0.916291),
(28, 'lapangan', 55, 1, 0.916291),
(29, 'nama', 55, 1, 0.916291),
(30, 'mahasiswa', 55, 1, 0.916291),
(31, 'rusdianto', 55, 1, 0.916291),
(32, 'nim', 55, 1, 0.916291),
(33, 'dosen', 55, 3, 2.74887),
(34, 'pembimbing', 55, 4, 3.66516),
(35, 'fajerin', 55, 2, 1.83258),
(36, 'biabdillah', 55, 2, 1.83258),
(37, 'kom', 55, 2, 1.83258),
(38, 'paraf', 55, 1, 0.916291),
(39, 'hari', 55, 1, 0.916291),
(40, 'tanggal', 55, 1, 0.916291),
(41, 'kegiatan', 55, 1, 0.916291),
(42, 'selasa', 55, 3, 2.74887),
(43, 'april', 55, 1, 0.916291),
(44, 'perkenalan', 55, 1, 0.916291),
(45, 'membahas', 55, 1, 0.916291),
(46, 'proyek', 55, 2, 1.83258),
(47, 'buat', 55, 1, 0.916291),
(48, 'kamis', 55, 1, 0.916291),
(49, 'mei', 55, 1, 0.916291),
(50, 'diskusi', 55, 1, 0.916291),
(51, 'mengenai', 55, 4, 3.66516),
(52, 'perubahan', 55, 1, 0.916291),
(53, 'pkl', 55, 4, 3.66516),
(54, 'pembuatan', 55, 2, 1.83258),
(55, 'aplikasi', 55, 1, 0.916291),
(56, 'absensi', 55, 1, 0.916291),
(57, 'kru', 55, 1, 0.916291),
(58, 'studio', 55, 1, 0.916291),
(59, 'video', 55, 1, 0.916291),
(60, 'profil', 55, 1, 0.916291),
(61, 'islamic', 55, 1, 0.916291),
(62, 'center', 55, 1, 0.916291),
(63, 'kaltim', 55, 1, 0.916291),
(64, 'juni', 55, 3, 2.74887),
(65, 'bertanya', 55, 1, 0.916291),
(66, 'melalui', 55, 1, 0.916291),
(67, 'whatsaap', 55, 1, 0.916291),
(68, 'laporan', 55, 3, 2.74887),
(69, 'bagian', 55, 1, 0.916291),
(70, 'saran', 55, 1, 0.916291),
(71, 'bab', 55, 1, 0.916291),
(72, 'rabu', 55, 1, 0.916291),
(73, 'melakukan', 55, 1, 0.916291),
(74, 'bimbingan', 55, 1, 0.916291),
(75, 'meminta', 55, 1, 0.916291),
(76, 'pendapat', 55, 1, 0.916291),
(77, 'sudah', 55, 2, 1.83258),
(78, 'selesai', 55, 1, 0.916291),
(79, 'revisi', 55, 2, 1.83258),
(80, 'konfirmasi', 55, 1, 0.916291),
(81, 'kembali', 55, 1, 0.916291),
(82, 'perbaiki', 55, 1, 0.916291),
(83, 'disetujui', 55, 1, 0.916291),
(84, 'nip', 55, 1, 0.916291),
(85, 'polnes', 56, 2, 1.83258),
(86, 'website', 56, 1, 0.916291),
(87, 'www', 56, 1, 0.916291),
(88, 'kementerian', 56, 1, 0.916291),
(89, 'pendidikan', 56, 1, 0.916291),
(90, 'kebudayaan', 56, 1, 0.916291),
(91, 'politeknik', 56, 1, 0.916291),
(92, 'negeri', 56, 1, 0.916291),
(93, 'samarinda', 56, 2, 1.83258),
(94, 'ciptomangunkusumo', 56, 1, 0.916291),
(95, 'kampus', 56, 1, 0.916291),
(96, 'gunung', 56, 1, 0.916291),
(97, 'lipan', 56, 1, 0.916291),
(98, 'telp', 56, 1, 0.916291),
(99, 'fax', 56, 1, 0.916291),
(100, 'lembar', 56, 1, 0.916291),
(101, 'konsultasi', 56, 2, 1.83258),
(102, 'praktek', 56, 1, 0.916291),
(103, 'kerja', 56, 1, 0.916291),
(104, 'lapangan', 56, 1, 0.916291),
(105, 'nama', 56, 1, 0.916291),
(106, 'mahasiswa', 56, 1, 0.916291),
(107, 'rusdianto', 56, 1, 0.916291),
(108, 'nim', 56, 1, 0.916291),
(109, 'dosen', 56, 3, 2.74887),
(110, 'pembimbing', 56, 4, 3.66516),
(111, 'fajerin', 56, 2, 1.83258),
(112, 'biabdillah', 56, 2, 1.83258),
(113, 'kom', 56, 2, 1.83258),
(114, 'paraf', 56, 1, 0.916291),
(115, 'hari', 56, 1, 0.916291),
(116, 'tanggal', 56, 1, 0.916291),
(117, 'kegiatan', 56, 1, 0.916291),
(118, 'selasa', 56, 3, 2.74887),
(119, 'april', 56, 1, 0.916291),
(120, 'perkenalan', 56, 1, 0.916291),
(121, 'membahas', 56, 1, 0.916291),
(122, 'proyek', 56, 2, 1.83258),
(123, 'buat', 56, 1, 0.916291),
(124, 'kamis', 56, 1, 0.916291),
(125, 'mei', 56, 1, 0.916291),
(126, 'diskusi', 56, 1, 0.916291),
(127, 'mengenai', 56, 4, 3.66516),
(128, 'perubahan', 56, 1, 0.916291),
(129, 'pkl', 56, 4, 3.66516),
(130, 'pembuatan', 56, 2, 1.83258),
(131, 'aplikasi', 56, 1, 0.916291),
(132, 'absensi', 56, 1, 0.916291),
(133, 'kru', 56, 1, 0.916291),
(134, 'studio', 56, 1, 0.916291),
(135, 'video', 56, 1, 0.916291),
(136, 'profil', 56, 1, 0.916291),
(137, 'islamic', 56, 1, 0.916291),
(138, 'center', 56, 1, 0.916291),
(139, 'kaltim', 56, 1, 0.916291),
(140, 'juni', 56, 3, 2.74887),
(141, 'bertanya', 56, 1, 0.916291),
(142, 'melalui', 56, 1, 0.916291),
(143, 'whatsaap', 56, 1, 0.916291),
(144, 'laporan', 56, 3, 2.74887),
(145, 'bagian', 56, 1, 0.916291),
(146, 'saran', 56, 1, 0.916291),
(147, 'bab', 56, 1, 0.916291),
(148, 'rabu', 56, 1, 0.916291),
(149, 'melakukan', 56, 1, 0.916291),
(150, 'bimbingan', 56, 1, 0.916291),
(151, 'meminta', 56, 1, 0.916291),
(152, 'pendapat', 56, 1, 0.916291),
(153, 'sudah', 56, 2, 1.83258),
(154, 'selesai', 56, 1, 0.916291),
(155, 'revisi', 56, 2, 1.83258),
(156, 'konfirmasi', 56, 1, 0.916291),
(157, 'kembali', 56, 1, 0.916291),
(158, 'perbaiki', 56, 1, 0.916291),
(159, 'disetujui', 56, 1, 0.916291),
(160, 'nip', 56, 1, 0.916291);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbkeyword`
--

CREATE TABLE `tbkeyword` (
  `Id` int(11) NOT NULL,
  `Term` varchar(100) NOT NULL,
  `Count` int(11) NOT NULL,
  `Bobot` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `tbkeyword`
--

INSERT INTO `tbkeyword` (`Id`, `Term`, `Count`, `Bobot`) VALUES
(689, 'januari', 1, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbstem`
--


--
-- Dumping data untuk tabel `tbstem`
--

---------------------------------------------------

--
-- Struktur dari tabel `tbvektor`
--

CREATE TABLE `tbvektor` (
  `DocId` int(11) NOT NULL,
  `Panjang` float NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `tbvektor`
--

INSERT INTO `tbvektor` (`DocId`, `Panjang`) VALUES
(53, 3.71363),
(54, 1.852),
(55, 12.3953),
(56, 12.3953);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbwdwdi`
--

CREATE TABLE `tbwdwdi` (
  `Id` int(11) NOT NULL,
  `Id_Doc` int(11) NOT NULL,
  `Hasil_p_Bobot` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `simpan_hasil`
--
ALTER TABLE `simpan_hasil`
  ADD PRIMARY KEY (`Id`);

--
-- Indeks untuk tabel `tbcache`
--
ALTER TABLE `tbcache`
  ADD PRIMARY KEY (`Id`);

--
-- Indeks untuk tabel `tbdokumen`
--
ALTER TABLE `tbdokumen`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `tbindex`
--
ALTER TABLE `tbindex`
  ADD PRIMARY KEY (`Id`);

--
-- Indeks untuk tabel `tbkeyword`
--
ALTER TABLE `tbkeyword`
  ADD PRIMARY KEY (`Id`);

--
-- Indeks untuk tabel `tbstem`
--
ALTER TABLE `tbstem`
  ADD PRIMARY KEY (`Id`);

--
-- Indeks untuk tabel `tbvektor`
--
ALTER TABLE `tbvektor`
  ADD PRIMARY KEY (`DocId`);

--
-- Indeks untuk tabel `tbwdwdi`
--
ALTER TABLE `tbwdwdi`
  ADD PRIMARY KEY (`Id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `simpan_hasil`
--
ALTER TABLE `simpan_hasil`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `tbcache`
--
ALTER TABLE `tbcache`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `tbdokumen`
--
ALTER TABLE `tbdokumen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT untuk tabel `tbindex`
--
ALTER TABLE `tbindex`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT untuk tabel `tbkeyword`
--
ALTER TABLE `tbkeyword`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=690;

--
-- AUTO_INCREMENT untuk tabel `tbstem`
--
ALTER TABLE `tbstem`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `tbwdwdi`
--
ALTER TABLE `tbwdwdi`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
