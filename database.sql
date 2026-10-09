-- ============================================================
-- Database: pdcs_db (ERD akademik: jurusan, mahasiswa, matakuliah)
-- Jalankan lewat setup.php, HeidiSQL, DBeaver, atau:
--   mysql -u root < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS pdcs_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE pdcs_db;

DROP TABLE IF EXISTS matakuliah;
DROP TABLE IF EXISTS mahasiswa;
DROP TABLE IF EXISTS jurusan;

CREATE TABLE jurusan (
    id            INT(11) AUTO_INCREMENT PRIMARY KEY,
    kode_jurusan  VARCHAR(32) NOT NULL UNIQUE,
    nama_jurusan  VARCHAR(64) NOT NULL,
    fakultas      VARCHAR(64)
);

CREATE TABLE mahasiswa (
    id            INT(11) AUTO_INCREMENT PRIMARY KEY,
    nama          VARCHAR(100) NOT NULL,
    nim           VARCHAR(32)  NOT NULL UNIQUE,
    jurusan_id    INT(11)      NOT NULL,
    created_at    DATE,
    CONSTRAINT fk_mahasiswa_jurusan
        FOREIGN KEY (jurusan_id) REFERENCES jurusan(id)
);

CREATE TABLE matakuliah (
    id            INT(11) AUTO_INCREMENT PRIMARY KEY,
    kode_mk       VARCHAR(32) NOT NULL UNIQUE,
    nama_mk       VARCHAR(64) NOT NULL,
    sks           INT(8)      NOT NULL,
    semester      INT(8)      NOT NULL,
    jurusan_id    INT(11)     NOT NULL,
    CONSTRAINT fk_matakuliah_jurusan
        FOREIGN KEY (jurusan_id) REFERENCES jurusan(id)
);

-- Data awal
INSERT INTO jurusan (kode_jurusan, nama_jurusan, fakultas) VALUES
('TI', 'Teknik Informatika', 'Teknologi Informasi'),
('BD', 'Bisnis Digital',     'Ekonomi dan Bisnis');

INSERT INTO mahasiswa (nama, nim, jurusan_id, created_at) VALUES
('Budi Santoso',  '2301001', 1, CURDATE()),
('Siti Aminah',   '2301002', 1, CURDATE()),
('Rian Hidayat',  '2303001', 2, CURDATE()),
('Putu Agus',     '2303002', 2, CURDATE()),
('Octavia Sari',  '2303003', 2, CURDATE()),
('Siska Pertiwi', '2303004', 2, CURDATE());

INSERT INTO matakuliah (kode_mk, nama_mk, sks, semester, jurusan_id) VALUES
('TI101', 'Pemrograman Dasar',                 3, 1, 1),
('TI201', 'Pemrograman Database Client Server', 3, 4, 1),
('BD101', 'Dasar Bisnis Digital',              2, 1, 2);
