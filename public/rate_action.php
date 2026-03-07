<?php
/* FILE: public/rate_action.php — Endpoint POST untuk menyimpan rating/ulasan buku
 *
 * Validasi:
 *   - CSRF token wajib
 *   - Rating integer 1-5
 *   - User hanya boleh memberi rating jika sudah meminjam & mengembalikan buku
 *   - User hanya boleh memberi 1 rating per buku
 *
 * Ulasan (ulasan) bersifat opsional.
 * Redirect ke book_detail.php dengan flash message.
 *
 * Asumsi kolom rating: id_rating, id_user, id_buku, rating (INT), ulasan (TEXT NULL), tanggal_rating
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/flash.php';

// Auth check
if (!isset($_SESSION['id_user'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Hanya POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/public/catalog.php');
    exit;
}

require_csrf();

$idUser = (int)$_SESSION['id_user'];
$idBuku = (int)($_POST['id_buku'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$ulasan = trim($_POST['ulasan'] ?? '');

$redirect = BASE_URL . '/public/book_detail.php?id=' . $idBuku;

// Validasi ID buku
if ($idBuku <= 0) {
    set_flash('message', 'ID buku tidak valid.', 'danger');
    header('Location: ' . BASE_URL . '/public/catalog.php');
    exit;
}

// Validasi rating 1-5
if ($rating < 1 || $rating > 5) {
    set_flash('message', 'Rating harus antara 1 sampai 5.', 'danger');
    header("Location: {$redirect}");
    exit;
}

// Sanitasi ulasan (max 1000 karakter)
if (mb_strlen($ulasan) > 1000) {
    $ulasan = mb_substr($ulasan, 0, 1000);
}

try {
    // 0. Pastikan tabel rating ada — buat otomatis jika belum
    $pdo->exec("CREATE TABLE IF NOT EXISTS rating (
        id_rating INT AUTO_INCREMENT PRIMARY KEY,
        id_user INT NOT NULL,
        id_buku INT NOT NULL,
        rating INT NOT NULL,
        ulasan TEXT NULL,
        tanggal_rating DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 1. Cek apakah user pernah meminjam & mengembalikan buku ini
    $stmtCek = $pdo->prepare(
        "SELECT id_peminjaman FROM peminjaman
         WHERE id_user = :uid AND id_buku = :bid AND status_peminjaman = 'dikembalikan'
         LIMIT 1"
    );
    $stmtCek->execute(['uid' => $idUser, 'bid' => $idBuku]);

    if (!$stmtCek->fetch()) {
        set_flash('message', 'Anda hanya bisa memberi rating setelah meminjam dan mengembalikan buku ini.', 'danger');
        header("Location: {$redirect}");
        exit;
    }

    // 2. Cek duplikasi rating (1 user = 1 rating per buku)
    $stmtDup = $pdo->prepare("SELECT id_rating FROM rating WHERE id_user = :uid AND id_buku = :bid LIMIT 1");
    $stmtDup->execute(['uid' => $idUser, 'bid' => $idBuku]);

    if ($stmtDup->fetch()) {
        set_flash('message', 'Anda sudah memberikan rating untuk buku ini sebelumnya.', 'warning');
        header("Location: {$redirect}");
        exit;
    }

    // 3. Insert rating
    $stmtInsert = $pdo->prepare(
        "INSERT INTO rating (id_user, id_buku, rating, ulasan, tanggal_rating)
         VALUES (:uid, :bid, :r, :ul, NOW())"
    );
    $stmtInsert->execute([
        'uid' => $idUser,
        'bid' => $idBuku,
        'r'   => $rating,
        'ul'  => $ulasan !== '' ? $ulasan : null,
    ]);

    set_flash('message', 'Terima kasih! Rating & ulasan Anda berhasil disimpan.', 'success');

} catch (PDOException $e) {
    error_log('[Perpustakaan] Rating Error: ' . $e->getMessage());
    set_flash('message', 'Gagal menyimpan rating. Silakan coba lagi.', 'danger');
}

header("Location: {$redirect}");
exit;
