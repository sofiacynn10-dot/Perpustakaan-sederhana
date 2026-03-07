<?php
/* FILE: public/reserve_action.php — Endpoint POST untuk membuat reservasi
 *
 * Aturan:
 *   - Hanya bisa reservasi jika stok_tersedia == 0
 *   - Tidak boleh reservasi lebih dari sekali untuk buku yang sama (status='aktif')
 *   - posisi_antrian = MAX(posisi_antrian) + 1 untuk buku tersebut
 *
 * Redirect ke book_detail.php dengan flash message.
 *
 * Asumsi kolom reservasi: id_reservasi, id_user, id_buku, posisi_antrian,
 *   status ENUM('aktif','terpenuhi','dibatalkan'), tanggal_reservasi (DATETIME)
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

if ($idBuku <= 0) {
    set_flash('message', 'ID buku tidak valid.', 'danger');
    header('Location: ' . BASE_URL . '/public/catalog.php');
    exit;
}

$redirect = BASE_URL . '/public/book_detail.php?id=' . $idBuku;

try {
    $pdo->beginTransaction();

    // 1. Cek buku & stok (lock row)
    $stmt = $pdo->prepare("SELECT id_buku, judul, stok_tersedia FROM buku WHERE id_buku = :id FOR UPDATE");
    $stmt->execute(['id' => $idBuku]);
    $buku = $stmt->fetch();

    if (!$buku) {
        throw new RuntimeException('Buku tidak ditemukan.');
    }

    if ((int)$buku['stok_tersedia'] > 0) {
        throw new RuntimeException('Stok buku "' . $buku['judul'] . '" masih tersedia. Silakan pinjam langsung.');
    }

    // 2. Cek duplikasi reservasi aktif untuk user + buku ini
    $stmtDup = $pdo->prepare(
        "SELECT id_reservasi FROM reservasi
         WHERE id_user = :uid AND id_buku = :bid AND status = 'aktif'
         LIMIT 1"
    );
    $stmtDup->execute(['uid' => $idUser, 'bid' => $idBuku]);
    if ($stmtDup->fetch()) {
        throw new RuntimeException('Anda sudah memiliki reservasi aktif untuk buku "' . $buku['judul'] . '".');
    }

    // 3. Hitung posisi antrian berikutnya
    $stmtPos = $pdo->prepare(
        "SELECT COALESCE(MAX(posisi_antrian), 0) + 1 FROM reservasi
         WHERE id_buku = :bid AND status = 'aktif'"
    );
    $stmtPos->execute(['bid' => $idBuku]);
    $nextPos = (int)$stmtPos->fetchColumn();

    // 4. Insert reservasi
    $stmtInsert = $pdo->prepare(
        "INSERT INTO reservasi (id_user, id_buku, posisi_antrian, status, tanggal_reservasi)
         VALUES (:uid, :bid, :pos, 'aktif', NOW())"
    );
    $stmtInsert->execute([
        'uid' => $idUser,
        'bid' => $idBuku,
        'pos' => $nextPos,
    ]);

    $pdo->commit();

    set_flash('message',
        'Reservasi berhasil untuk buku "' . $buku['judul'] . '". Posisi antrian Anda: #' . $nextPos . '. '
        . 'Anda akan mendapat peminjaman otomatis saat buku tersedia.',
        'success'
    );

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('message', $e->getMessage(), 'danger');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[Perpustakaan] Reserve Error: ' . $e->getMessage());
    set_flash('message', 'Terjadi kesalahan sistem saat reservasi. Silakan coba lagi.', 'danger');
}

header("Location: {$redirect}");
exit;
