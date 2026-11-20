-- =====================================================
-- SIPN - Sistem Informasi Pengelolaan Nilai
-- Skema mengikuti soal (TIDAK diubah): db_siacad_smk_2
-- Tiap tabel berisi minimal 10 data.
-- =====================================================
CREATE DATABASE IF NOT EXISTS db_siacad_smk_2 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE db_siacad_smk_2;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS nilai_rapor;
DROP TABLE IF EXISTS mengajar;
DROP TABLE IF EXISTS mapel;
DROP TABLE IF EXISTS siswa;
DROP TABLE IF EXISTS guru;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT(11) NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','guru','siswa') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE siswa (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nis VARCHAR(10) NOT NULL,
  nama VARCHAR(100) NOT NULL,
  kelas VARCHAR(10) NOT NULL,
  jenis_kelamin ENUM('L','P') NOT NULL,
  user_id INT(11) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_nis (nis),
  KEY idx_siswa_user (user_id),
  CONSTRAINT fk_siswa_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE guru (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nip VARCHAR(20) NOT NULL,
  nama VARCHAR(100) NOT NULL,
  jenis_kelamin ENUM('L','P') NOT NULL,
  user_id INT(11) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_nip (nip),
  KEY idx_guru_user (user_id),
  CONSTRAINT fk_guru_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mapel (
  id INT(11) NOT NULL AUTO_INCREMENT,
  kode_mapel VARCHAR(10) NOT NULL,
  nama_mapel VARCHAR(100) NOT NULL,
  guru_id INT(11) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_mapel_guru (guru_id),
  CONSTRAINT fk_mapel_guru FOREIGN KEY (guru_id) REFERENCES guru(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mengajar (
  id INT(11) NOT NULL AUTO_INCREMENT,
  mapel_id INT(11) NOT NULL,
  siswa_id INT(11) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_mengajar_mapel (mapel_id),
  KEY idx_mengajar_siswa (siswa_id),
  CONSTRAINT fk_mengajar_mapel FOREIGN KEY (mapel_id) REFERENCES mapel(id),
  CONSTRAINT fk_mengajar_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE nilai_rapor (
  id INT(11) NOT NULL AUTO_INCREMENT,
  siswa_id INT(11) NOT NULL,
  mapel_id INT(11) NOT NULL,
  nilai_kkm INT(11) NOT NULL,
  nilai_pengetahuan INT(11) NOT NULL,
  nilai_keterampilan INT(11) NOT NULL,
  Keterangan VARCHAR(50) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_nilai_siswa (siswa_id),
  KEY idx_nilai_mapel (mapel_id),
  CONSTRAINT fk_nilai_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id),
  CONSTRAINT fk_nilai_mapel FOREIGN KEY (mapel_id) REFERENCES mapel(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- users ----------
INSERT INTO users (id, username, password, role) VALUES
(1, 'admin', '$2y$10$zMY2BM0YW.DNoUTVnsYI7Oo2mnwb3bsREQTQ7gIEE.DuSfaN0GKfa', 'admin'),
(2, 'admin2', '$2y$10$zMY2BM0YW.DNoUTVnsYI7Oo2mnwb3bsREQTQ7gIEE.DuSfaN0GKfa', 'admin'),
(3, 'guru1', '$2y$10$VGxn8sTd4eYGTJxOV0.FTezKN2AIrUx0dHyuS4FjuPQWVj1SwaBEC', 'guru'),
(4, 'guru2', '$2y$10$VGxn8sTd4eYGTJxOV0.FTezKN2AIrUx0dHyuS4FjuPQWVj1SwaBEC', 'guru'),
(5, 'guru3', '$2y$10$VGxn8sTd4eYGTJxOV0.FTezKN2AIrUx0dHyuS4FjuPQWVj1SwaBEC', 'guru'),
(6, 'guru4', '$2y$10$VGxn8sTd4eYGTJxOV0.FTezKN2AIrUx0dHyuS4FjuPQWVj1SwaBEC', 'guru'),
(7, 'guru5', '$2y$10$VGxn8sTd4eYGTJxOV0.FTezKN2AIrUx0dHyuS4FjuPQWVj1SwaBEC', 'guru'),
(8, 'guru6', '$2y$10$VGxn8sTd4eYGTJxOV0.FTezKN2AIrUx0dHyuS4FjuPQWVj1SwaBEC', 'guru'),
(9, 'guru7', '$2y$10$VGxn8sTd4eYGTJxOV0.FTezKN2AIrUx0dHyuS4FjuPQWVj1SwaBEC', 'guru'),
(10, 'guru8', '$2y$10$VGxn8sTd4eYGTJxOV0.FTezKN2AIrUx0dHyuS4FjuPQWVj1SwaBEC', 'guru'),
(11, 'guru9', '$2y$10$VGxn8sTd4eYGTJxOV0.FTezKN2AIrUx0dHyuS4FjuPQWVj1SwaBEC', 'guru'),
(12, 'guru10', '$2y$10$VGxn8sTd4eYGTJxOV0.FTezKN2AIrUx0dHyuS4FjuPQWVj1SwaBEC', 'guru'),
(13, 'siswa1', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(14, 'siswa2', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(15, 'siswa3', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(16, 'siswa4', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(17, 'siswa5', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(18, 'siswa6', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(19, 'siswa7', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(20, 'siswa8', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(21, 'siswa9', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(22, 'siswa10', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(23, 'siswa11', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(24, 'siswa12', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(25, 'siswa13', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(26, 'siswa14', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa'),
(27, 'siswa15', '$2y$10$FGqMgsr8iulyYYlcMHo8du/nMFpDmyFHvp.zAiO9eN7SIJrkND75O', 'siswa');

-- ---------- guru ----------
INSERT INTO guru (id, nip, nama, jenis_kelamin, user_id) VALUES
(1, '198503122010011001', 'Sukanda Wiguna, S.T.,Gr.', 'L', 3),
(2, '198807202012012002', 'Rina Marlina, S.Kom.', 'P', 4),
(3, '198211052009011003', 'Dedi Kurniawan, S.T.', 'L', 5),
(4, '199001182015012004', 'Siti Nurhaliza, S.Pd.', 'P', 6),
(5, '197909252005011005', 'Agus Setiawan, S.Pd.', 'L', 7),
(6, '198605142011012006', 'Maya Puspita, S.Pd.', 'P', 8),
(7, '198312302008011007', 'Hendra Gunawan, S.Pd.', 'L', 9),
(8, '198108082007011008', 'Ade Rahmat, S.Ag.', 'L', 10),
(9, '199203272016012009', 'Lilis Suryani, S.Pd.', 'P', 11),
(10, '198410102010011010', 'Yusuf Ramadhan, S.Pd.', 'L', 12);

-- ---------- siswa ----------
INSERT INTO siswa (id, nis, nama, kelas, jenis_kelamin, user_id) VALUES
(1, '2627001', 'Ahmad Fauzi', 'XI-RPL-1', 'L', 13),
(2, '2627002', 'Bunga Citra Lestari', 'XI-RPL-4', 'P', 14),
(3, '2627003', 'Cahya Nugraha', 'XI-RPL-1', 'L', 15),
(4, '2627004', 'Dewi Anggraini', 'XI-RPL-4', 'P', 16),
(5, '2627005', 'Eka Prasetyo', 'XI-RPL-1', 'L', 17),
(6, '2627006', 'Fitri Handayani', 'XI-RPL-4', 'P', 18),
(7, '2627007', 'Galih Permana', 'XI-RPL-1', 'L', 19),
(8, '2627008', 'Hana Salsabila', 'XI-RPL-4', 'P', 20),
(9, '2627009', 'Irfan Maulana', 'XI-RPL-1', 'L', 21),
(10, '2627010', 'Jihan Aulia', 'XI-RPL-4', 'P', 22),
(11, '2627011', 'Kevin Ardiansyah', 'XI-RPL-1', 'L', 23),
(12, '2627012', 'Lestari Wulandari', 'XI-RPL-4', 'P', 24),
(13, '2627013', 'Muhammad Rizky', 'XI-RPL-1', 'L', 25),
(14, '2627014', 'Nadia Putri', 'XI-RPL-4', 'P', 26),
(15, '2627015', 'Oky Firmansyah', 'XI-RPL-1', 'L', 27);

-- ---------- mapel (1 guru pengampu per mapel) ----------
INSERT INTO mapel (id, kode_mapel, nama_mapel, guru_id) VALUES
(1, 'PW', 'Pemrograman Web', 1),
(2, 'BD', 'Basis Data', 2),
(3, 'PBO', 'Pemrograman Berorientasi Objek', 3),
(4, 'PD', 'Pemrograman Dasar', 4),
(5, 'MTK', 'Matematika', 5),
(6, 'BIN', 'Bahasa Indonesia', 6),
(7, 'BIG', 'Bahasa Inggris', 7),
(8, 'PAI', 'Pendidikan Agama Islam', 8),
(9, 'PKN', 'Pendidikan Kewarganegaraan', 9),
(10, 'SEJ', 'Sejarah Indonesia', 10);

-- ---------- mengajar (siswa mengikuti mapel) ----------
INSERT INTO mengajar (id, mapel_id, siswa_id) VALUES
(1, 1, 1),
(2, 2, 1),
(3, 3, 1),
(4, 4, 1),
(5, 5, 1),
(6, 1, 2),
(7, 2, 2),
(8, 3, 2),
(9, 4, 2),
(10, 5, 2),
(11, 1, 3),
(12, 2, 3),
(13, 3, 3),
(14, 4, 3),
(15, 5, 3),
(16, 1, 4),
(17, 2, 4),
(18, 3, 4),
(19, 4, 4),
(20, 5, 4),
(21, 1, 5),
(22, 2, 5),
(23, 3, 5),
(24, 4, 5),
(25, 5, 5),
(26, 1, 6),
(27, 2, 6),
(28, 3, 6),
(29, 4, 6),
(30, 5, 6),
(31, 1, 7),
(32, 2, 7),
(33, 3, 7),
(34, 4, 7),
(35, 5, 7),
(36, 1, 8),
(37, 2, 8),
(38, 3, 8),
(39, 4, 8),
(40, 5, 8),
(41, 1, 9),
(42, 2, 9),
(43, 3, 9),
(44, 4, 9),
(45, 5, 9),
(46, 1, 10),
(47, 2, 10),
(48, 3, 10),
(49, 4, 10),
(50, 5, 10),
(51, 1, 11),
(52, 2, 11),
(53, 3, 11),
(54, 4, 11),
(55, 5, 11),
(56, 1, 12),
(57, 2, 12),
(58, 3, 12),
(59, 4, 12),
(60, 5, 12),
(61, 1, 13),
(62, 2, 13),
(63, 3, 13),
(64, 4, 13),
(65, 5, 13),
(66, 1, 14),
(67, 2, 14),
(68, 3, 14),
(69, 4, 14),
(70, 5, 14),
(71, 1, 15),
(72, 2, 15),
(73, 3, 15),
(74, 4, 15),
(75, 5, 15);

-- ---------- nilai_rapor ----------
INSERT INTO nilai_rapor (id, siswa_id, mapel_id, nilai_kkm, nilai_pengetahuan, nilai_keterampilan, Keterangan) VALUES
(1, 1, 1, 75, 69, 85, 'Tuntas'),
(2, 1, 2, 75, 94, 97, 'Tuntas'),
(3, 1, 3, 75, 68, 79, 'Belum Tuntas'),
(4, 2, 1, 75, 97, 91, 'Tuntas'),
(5, 2, 2, 75, 98, 96, 'Tuntas'),
(6, 2, 3, 75, 90, 80, 'Tuntas'),
(7, 3, 1, 75, 62, 70, 'Belum Tuntas'),
(8, 3, 2, 75, 69, 83, 'Tuntas'),
(9, 3, 3, 75, 68, 93, 'Tuntas'),
(10, 4, 1, 75, 62, 96, 'Tuntas'),
(11, 4, 2, 75, 82, 78, 'Tuntas'),
(12, 4, 3, 75, 87, 81, 'Tuntas'),
(13, 5, 1, 75, 84, 87, 'Tuntas'),
(14, 5, 2, 75, 86, 97, 'Tuntas'),
(15, 5, 3, 75, 66, 86, 'Tuntas'),
(16, 6, 1, 75, 67, 83, 'Tuntas'),
(17, 6, 2, 75, 80, 94, 'Tuntas'),
(18, 6, 3, 75, 71, 84, 'Tuntas'),
(19, 7, 1, 75, 63, 88, 'Tuntas'),
(20, 7, 2, 75, 85, 94, 'Tuntas'),
(21, 7, 3, 75, 89, 70, 'Tuntas'),
(22, 8, 1, 75, 87, 96, 'Tuntas'),
(23, 8, 2, 75, 69, 92, 'Tuntas'),
(24, 8, 3, 75, 94, 96, 'Tuntas'),
(25, 9, 1, 75, 87, 98, 'Tuntas'),
(26, 9, 2, 75, 78, 91, 'Tuntas'),
(27, 9, 3, 75, 98, 95, 'Tuntas'),
(28, 10, 1, 75, 94, 98, 'Tuntas'),
(29, 10, 2, 75, 63, 79, 'Belum Tuntas'),
(30, 10, 3, 75, 70, 68, 'Belum Tuntas'),
(31, 11, 1, 75, 95, 71, 'Tuntas'),
(32, 11, 2, 75, 89, 95, 'Tuntas'),
(33, 11, 3, 75, 71, 95, 'Tuntas'),
(34, 12, 1, 75, 76, 72, 'Belum Tuntas'),
(35, 12, 2, 75, 91, 95, 'Tuntas'),
(36, 12, 3, 75, 96, 81, 'Tuntas'),
(37, 13, 1, 75, 97, 90, 'Tuntas'),
(38, 13, 2, 75, 81, 97, 'Tuntas'),
(39, 13, 3, 75, 92, 90, 'Tuntas'),
(40, 14, 1, 75, 89, 77, 'Tuntas'),
(41, 14, 2, 75, 80, 82, 'Tuntas'),
(42, 14, 3, 75, 62, 67, 'Belum Tuntas'),
(43, 15, 1, 75, 71, 94, 'Tuntas'),
(44, 15, 2, 75, 95, 96, 'Tuntas'),
(45, 15, 3, 75, 85, 77, 'Tuntas');
