<?php
/* FILE: config.php — Konfigurasi PDO, BASE_URL, dan konstanta bisnis aplikasi
 *
 * File ini TERPISAH dari config/koneksi.php (yang menyediakan $conn mysqli).
 * File public/* menggunakan config.php ini untuk PDO + prepared statements.
 *
 * ======================= ASUMSI STRUKTUR TABEL ==========================
 *
 * Tabel `user`:
 *   id_user (PK AI), nama, username, password, email, alamat, no_handphone,
 *   role ENUM('admin','anggota')
 *
 * Tabel `kategori`:
 *   id_kategori (PK AI), nama_kategori
 *
 * Tabel `buku`:
 *   id_buku (PK AI), id_kategori (FK), judul, penulis, tahun_terbit,
 *   deskripsi (TEXT), stok_tersedia (INT DEFAULT 1)
 *   -- Jika kolom stok_tersedia belum ada, tambahkan:
 *   -- ALTER TABLE buku ADD COLUMN stok_tersedia INT NOT NULL DEFAULT 1;
 *
 * Tabel `peminjaman`:
 *   id_peminjaman (PK AI), id_user (FK), id_buku (FK),
 *   tanggal_peminjaman (DATE), tanggal_pengembalian (DATE — jatuh tempo),
 *   tanggal_kembali_aktual (DATE NULL — diisi saat dikembalikan),
 *   status_peminjaman ENUM('dipinjam','dikembalikan'),
 *   denda (INT DEFAULT 0)
 *   -- Jika kolom baru belum ada:
 *   -- ALTER TABLE peminjaman ADD COLUMN tanggal_kembali_aktual DATE NULL;
 *   -- ALTER TABLE peminjaman ADD COLUMN denda INT NOT NULL DEFAULT 0;
 *
 * Tabel `reservasi`:
 *   id_reservasi (PK AI), id_user (FK), id_buku (FK),
 *   posisi_antrian (INT), status ENUM('aktif','terpenuhi','dibatalkan'),
 *   tanggal_reservasi (DATETIME)
 *
 * Tabel `rating`:
 *   id_rating (PK AI), id_user (FK), id_buku (FK),
 *   rating (INT 1-5), ulasan (TEXT NULL), tanggal_rating (DATETIME)
 *
 * ========================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Konfigurasi Database (sama dengan config/koneksi.php, format PDO) ---
$db_host = 'localhost';
$db_name = 'perpustakaan';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('[Perpustakaan] DB Connection Error: ' . $e->getMessage());
    die('Koneksi database gagal. Silakan hubungi administrator.');
}

// --- Base URL (tanpa trailing slash) ---
define('BASE_URL', '/perpustakaan');

// --- Konstanta Bisnis (sesuaikan sesuai kebutuhan) ---
define('MAX_PINJAMAN', 5);           // Maks pinjaman aktif per user
define('DENDA_PER_HARI', 1000);      // Denda keterlambatan per hari (Rp)
define('DURASI_PINJAM_HARI', 14);    // Durasi peminjaman default (hari)
define('ITEMS_PER_PAGE', 10);        // Limit per halaman katalog

// --- Helper: HTML escape ---
if (!function_exists('e')) {
    function e(string $val): string
    {
        return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    }
}
