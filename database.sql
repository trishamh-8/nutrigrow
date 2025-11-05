-- ============================================
-- Database Schema NutriGrow V3 (FINAL)
-- Compatible with all PHP files
-- ============================================

-- Buat database
CREATE DATABASE IF NOT EXISTS nutrigrow_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nutrigrow_db;

-- ============================================
-- TABEL AKUN (Base Table)
-- ============================================
CREATE TABLE IF NOT EXISTS akun (
    id_akun INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nomor_telepon VARCHAR(20),
    status_akun ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    tanggal_aktif TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    username VARCHAR(50) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABEL ROLE
-- ============================================

-- Tabel Admin
CREATE TABLE IF NOT EXISTS admin (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    id_akun INT NOT NULL,
    level_admin ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    FOREIGN KEY (id_akun) REFERENCES akun(id_akun) ON DELETE CASCADE,
    INDEX idx_akun (id_akun)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Pengguna (Orang Tua)
CREATE TABLE IF NOT EXISTS pengguna (
    id_pengguna INT AUTO_INCREMENT PRIMARY KEY,
    id_akun INT NOT NULL,
    alamat TEXT,
    FOREIGN KEY (id_akun) REFERENCES akun(id_akun) ON DELETE CASCADE,
    INDEX idx_akun (id_akun)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Tenaga Kesehatan
CREATE TABLE IF NOT EXISTS tenaga_kesehatan (
    id_tenaga_kesehatan INT AUTO_INCREMENT PRIMARY KEY,
    id_akun INT NOT NULL,
    sertifikasi VARCHAR(100),
    spesialisasi VARCHAR(100),
    FOREIGN KEY (id_akun) REFERENCES akun(id_akun) ON DELETE CASCADE,
    INDEX idx_akun (id_akun)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABEL DATA BALITA
-- ============================================

-- Tabel Balita
CREATE TABLE IF NOT EXISTS balita (
    id_balita INT AUTO_INCREMENT PRIMARY KEY,
    id_akun INT NOT NULL,
    nama_balita VARCHAR(100) NOT NULL,
    tanggal_lahir DATE NOT NULL,
    jenis_kelamin ENUM('L', 'P') NOT NULL,
    foto_profil VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_akun) REFERENCES akun(id_akun) ON DELETE CASCADE,
    INDEX idx_akun (id_akun)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE balita
ADD COLUMN alamat_balita TEXT AFTER jenis_kelamin;

-- Tabel Pertumbuhan
CREATE TABLE IF NOT EXISTS pertumbuhan (
    id_pertumbuhan INT AUTO_INCREMENT PRIMARY KEY,
    id_balita INT NOT NULL,
    id_akun INT NOT NULL,
    tanggal_pemeriksaan DATE NOT NULL,
    berat_badan DECIMAL(5,2) NOT NULL,
    tinggi_badan DECIMAL(5,2) NOT NULL,
    lingkar_kepala DECIMAL(5,2),
    status_gizi VARCHAR(50),
    zscore DECIMAL(5,2),
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_balita) REFERENCES balita(id_balita) ON DELETE CASCADE,
    FOREIGN KEY (id_akun) REFERENCES akun(id_akun) ON DELETE CASCADE,
    INDEX idx_balita (id_balita),
    INDEX idx_tanggal (tanggal_pemeriksaan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Asupan Harian
CREATE TABLE IF NOT EXISTS asupan_harian (
    id_asupan INT AUTO_INCREMENT PRIMARY KEY,
    id_balita INT NOT NULL,
    tanggal_catatan DATE NOT NULL,
    jenis_makanan VARCHAR(100),
    porsi VARCHAR(50),
    kalori_total DECIMAL(7,2),
    protein DECIMAL(6,2),
    karbohidrat DECIMAL(6,2),
    lemak DECIMAL(6,2),
    waktu_makan ENUM('sarapan', 'makan_siang', 'makan_malam', 'camilan') NOT NULL,
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_balita) REFERENCES balita(id_balita) ON DELETE CASCADE,
    INDEX idx_balita (id_balita),
    INDEX idx_tanggal (tanggal_catatan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Rekomendasi Gizi
CREATE TABLE IF NOT EXISTS rekomendasi_gizi (
    id_rekomendasi INT AUTO_INCREMENT PRIMARY KEY,
    id_balita INT NOT NULL,
    id_pertumbuhan INT,
    id_akun INT NOT NULL,
    sumber VARCHAR(100),
    isi_rekomendasi TEXT NOT NULL,
    tanggal_rekomendasi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    prioritas ENUM('rendah', 'sedang', 'tinggi') DEFAULT 'sedang',
    status ENUM('aktif', 'selesai', 'diabaikan') DEFAULT 'aktif',
    FOREIGN KEY (id_balita) REFERENCES balita(id_balita) ON DELETE CASCADE,
    FOREIGN KEY (id_pertumbuhan) REFERENCES pertumbuhan(id_pertumbuhan) ON DELETE SET NULL,
    FOREIGN KEY (id_akun) REFERENCES akun(id_akun) ON DELETE CASCADE,
    INDEX idx_balita (id_balita),
    INDEX idx_tanggal (tanggal_rekomendasi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABEL KONTEN
-- ============================================

-- Tabel Artikel
CREATE TABLE IF NOT EXISTS artikel (
    id_artikel INT AUTO_INCREMENT PRIMARY KEY,
    id_admin INT NOT NULL,
    kategori VARCHAR(50),
    judul_artikel VARCHAR(200) NOT NULL,
    isi_artikel TEXT NOT NULL,
    penulis VARCHAR(100),
    tgl_terbit DATE NOT NULL,
    gambar_cover VARCHAR(255),
    views INT DEFAULT 0,
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_admin) REFERENCES admin(id_admin) ON DELETE CASCADE,
    INDEX idx_kategori (kategori),
    INDEX idx_tanggal (tgl_terbit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABEL JADWAL & LAPORAN
-- ============================================

-- Tabel Jadwal
CREATE TABLE IF NOT EXISTS jadwal (
    id_jadwal INT AUTO_INCREMENT PRIMARY KEY,
    id_balita INT NOT NULL,
    jenis VARCHAR(100) NOT NULL,
    tanggal DATETIME NOT NULL,
    lokasi VARCHAR(200),
    status ENUM('terjadwal', 'selesai', 'dibatalkan') DEFAULT 'terjadwal',
    catatan_hasil TEXT,
    reminder_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_balita) REFERENCES balita(id_balita) ON DELETE CASCADE,
    INDEX idx_balita (id_balita),
    INDEX idx_tanggal (tanggal),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Laporan
CREATE TABLE IF NOT EXISTS laporan (
    id_laporan INT AUTO_INCREMENT PRIMARY KEY,
    id_balita INT NOT NULL,
    periode_awal DATE NOT NULL,
    periode_akhir DATE NOT NULL,
    ringkasan_zscore TEXT,
    ringkasan_asupan TEXT,
    rekomendasi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_balita) REFERENCES balita(id_balita) ON DELETE CASCADE,
    INDEX idx_balita (id_balita),
    INDEX idx_periode (periode_awal, periode_akhir)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DATA DUMMY UNTUK TESTING
-- ============================================

-- Insert Akun (Password: 123456 untuk semua)
INSERT INTO akun (nama, email, password, nomor_telepon, username, status_akun) VALUES
('Dr. Budi Santoso', 'budi@nutrigrow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567890', 'dr.budi', 'aktif'),
('Siti Nurhaliza', 'siti@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567891', 'siti', 'aktif'),
('Ahmad Wijaya', 'ahmad@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567892', 'ahmad', 'aktif'),
('Admin NutriGrow', 'admin@nutrigrow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567893', 'admin', 'aktif');

-- Insert Role
INSERT INTO tenaga_kesehatan (id_akun, sertifikasi, spesialisasi) VALUES
(1, 'SIP-12345', 'Gizi Anak');

INSERT INTO pengguna (id_akun, alamat) VALUES
(2, 'Jl. Mawar No. 10, Malang, Jawa Timur'),
(3, 'Jl. Melati No. 5, Surabaya, Jawa Timur');

INSERT INTO admin (id_akun, level_admin) VALUES
(4, 'super_admin');

-- Insert Balita
INSERT INTO balita (id_akun, nama_balita, tanggal_lahir, jenis_kelamin) VALUES
(2, 'Ahmad Zaki', '2023-01-15', 'L'),
(2, 'Siti Rahma', '2022-06-20', 'P'),
(3, 'Budi Santoso Jr', '2023-03-10', 'L');

-- Insert Pertumbuhan
INSERT INTO pertumbuhan (id_balita, id_akun, tanggal_pemeriksaan, berat_badan, tinggi_badan, lingkar_kepala, status_gizi, zscore) VALUES
(1, 2, '2025-10-20', 12.9, 89.0, 47.5, 'Normal', -0.5),
(1, 2, '2025-09-20', 12.6, 88.0, 47.0, 'Normal', -0.6),
(2, 2, '2025-10-20', 11.5, 82.0, 46.0, 'Normal', 0.2),
(3, 3, '2025-10-20', 13.2, 90.0, 48.0, 'Normal', 0.1);

-- Insert Asupan Harian
INSERT INTO asupan_harian (id_balita, tanggal_catatan, jenis_makanan, porsi, kalori_total, protein, karbohidrat, lemak, waktu_makan) VALUES
(1, '2025-11-01', 'Nasi + Ayam + Sayur', '1 porsi', 350, 15, 45, 12, 'makan_siang'),
(1, '2025-11-01', 'Bubur Kacang Hijau', '1 mangkok', 200, 8, 35, 5, 'sarapan'),
(1, '2025-11-02', 'Nasi + Ikan + Tempe', '1 porsi', 380, 18, 50, 10, 'makan_siang'),
(1, '2025-11-02', 'Telur + Roti', '2 potong', 250, 12, 30, 8, 'sarapan'),
(1, '2025-11-03', 'Nasi + Tahu + Sayur', '1 porsi', 320, 14, 42, 9, 'makan_siang');

-- Insert Rekomendasi Gizi
INSERT INTO rekomendasi_gizi (id_balita, id_akun, sumber, isi_rekomendasi, prioritas, status) VALUES
(1, 1, 'Dr. Budi Santoso - Konsultasi Rutin', 'Tingkatkan asupan kalori dengan menambahkan 2x camilan sehat per hari. Berikan buah-buahan segar dan susu.', 'tinggi', 'aktif'),
(1, 1, 'Dr. Budi Santoso - Pemeriksaan Bulanan', 'Pertahankan asupan protein yang sudah baik. Variasikan sumber protein dari ikan, telur, dan tahu/tempe.', 'sedang', 'aktif');

-- Insert Artikel
INSERT INTO artikel (id_admin, kategori, judul_artikel, isi_artikel, penulis, tgl_terbit, status) VALUES
(1, 'Nutrisi', 'Pentingnya Protein untuk Pertumbuhan Balita', 'Protein merupakan zat gizi yang sangat penting untuk pertumbuhan dan perkembangan balita. Protein berfungsi sebagai zat pembangun tubuh, membantu pembentukan otot, tulang, dan organ-organ penting lainnya...', 'Dr. Budi Santoso', '2025-10-15', 'published'),
(1, 'Kesehatan', 'Tips Menjaga Imunitas Balita', 'Sistem imun balita masih dalam tahap perkembangan. Berikut tips untuk menjaga imunitas: 1) Berikan ASI eksklusif 6 bulan, 2) MPASI bergizi seimbang, 3) Jaga kebersihan lingkungan...', 'Dr. Budi Santoso', '2025-10-20', 'published'),
(1, 'Gizi', 'Menu MPASI Sehat dan Bergizi', 'Makanan Pendamping ASI (MPASI) harus memenuhi kebutuhan nutrisi balita. Panduan menu MPASI: Usia 6 bulan (bubur halus), 9 bulan (makanan cincang), 12 bulan (makanan keluarga)...', 'Dr. Budi Santoso', '2025-10-25', 'published'),
(1, 'Tips', 'Cara Mengatasi Balita GTM (Gerakan Tutup Mulut)', 'GTM adalah fase normal yang dialami balita. Tips mengatasinya: 1) Jangan paksa makan, 2) Sajikan makanan menarik, 3) Makan bersama keluarga, 4) Batasi camilan manis...', 'Dr. Budi Santoso', '2025-10-28', 'published'),
(1, 'Kesehatan', 'Pentingnya Imunisasi Lengkap untuk Balita', 'Imunisasi melindungi anak dari penyakit berbahaya. Jadwal imunisasi dasar lengkap: BCG, Hepatitis B, Polio, DPT, Campak. Pastikan balita mendapat imunisasi tepat waktu...', 'Dr. Budi Santoso', '2025-11-01', 'published'),
(1, 'Nutrisi', 'Mengenal Gizi Seimbang untuk Balita', 'Gizi seimbang balita terdiri dari: Karbohidrat 50-60%, Protein 10-15%, Lemak 30-40%. Contoh sumber: nasi, daging, ikan, telur, sayur, buah. Porsi disesuaikan usia...', 'Dr. Budi Santoso', '2025-11-03', 'published');

-- Insert Jadwal
INSERT INTO jadwal (id_balita, jenis, tanggal, lokasi, status) VALUES
(1, 'Imunisasi DPT 4', '2025-11-25 09:00:00', 'Posyandu Melati 3', 'terjadwal'),
(1, 'Konsultasi Gizi Rutin', '2025-11-28 10:30:00', 'Puskesmas Harapan Sehat', 'terjadwal'),
(1, 'Pemeriksaan Tumbuh Kembang', '2025-12-05 08:00:00', 'Posyandu Melati 3', 'terjadwal'),
(2, 'Imunisasi Campak', '2025-11-20 09:00:00', 'Posyandu Mawar 2', 'terjadwal');

-- Insert Laporan
INSERT INTO laporan (id_balita, periode_awal, periode_akhir, ringkasan_zscore, ringkasan_asupan, rekomendasi) VALUES
(1, '2025-09-01', '2025-11-03', 'Z-Score stabil di -0.5 (Normal). Pertumbuhan berat badan +0.3 kg, tinggi badan +1 cm dalam 1 bulan terakhir.', 'Rata-rata asupan harian: Kalori 1095 kcal (91%), Protein 54g (154%), Karbohidrat 145g (97%), Lemak 35g (88%). Asupan protein sangat baik, namun kalori dan lemak masih sedikit di bawah target.', 'Tingkatkan asupan kalori dan lemak dengan menambahkan camilan bergizi 2x sehari seperti buah, susu, atau kacang-kacangan. Pertahankan asupan protein yang sudah sangat baik. Lanjutkan pola makan seimbang dan kontrol rutin setiap bulan.');

-- ============================================
-- SELESAI
-- ============================================

-- Tampilkan info
SELECT 'Database NutriGrow V3 berhasil dibuat!' as Status;
SELECT COUNT(*) as 'Total Tabel' FROM information_schema.tables WHERE table_schema = 'nutrigrow_db';
SELECT COUNT(*) as 'Total Akun' FROM akun;
SELECT COUNT(*) as 'Total Balita' FROM balita;
SELECT COUNT(*) as 'Total Artikel' FROM artikel;