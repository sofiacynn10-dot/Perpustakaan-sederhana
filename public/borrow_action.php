<?php
/* FILE: public/borrow_action.php — Endpoint POST untuk proses peminjaman buku
 *
 * Langkah:
 *   1. Validasi CSRF token
 *   2. Cek stok_tersedia > 0
 *   3. Cek batas pinjaman aktif user (MAX_PINJAMAN)
 *   4. Cek duplikasi (user belum pinjam buku yang sama & belum dikembalikan)
 *   5. Kurangi stok_tersedia
 *   6. Insert peminjaman (tanggal_peminjaman=CURDATE, tanggal_pengembalian=+14 hari)
 *
 * Redirect kembali ke catalog.php dengan flash message.
 *
 * Asumsi kolom:
 *   buku: id_buku, judul, stok_tersedia
 *   peminjaman: id_peminjaman, id_user, id_buku, tanggal_peminjaman,
 *     tanggal_pengembalian (jatuh tempo), tanggal_kembali_aktual (NULL),
 *     status_peminjaman, denda (default 0)
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

try {
    $pdo->beginTransaction();

    // 1. Cek buku & stok (lock row)
    // Cek apakah kolom stok_tersedia ada
    $colStok = $pdo->query("SHOW COLUMNS FROM buku LIKE 'stok_tersedia'");
    $hasStok = $colStok && $colStok->rowCount() > 0;

    if ($hasStok) {
        $stmt = $pdo->prepare("SELECT id_buku, judul, stok_tersedia FROM buku WHERE id_buku = :id FOR UPDATE");
    } else {
        $stmt = $pdo->prepare("SELECT id_buku, judul, 1 AS stok_tersedia FROM buku WHERE id_buku = :id FOR UPDATE");
    }
    $stmt->execute(['id' => $idBuku]);
    $buku = $stmt->fetch();

    if (!$buku) {
        throw new RuntimeException('Buku tidak ditemukan.');
    }

    if ($hasStok && (int)$buku['stok_tersedia'] <= 0) {
        throw new RuntimeException('Stok buku "' . $buku['judul'] . '" habis. Silakan gunakan fitur Reservasi.');
    }

    // 2. Cek batas pinjaman aktif
    $stmtCount = $pdo->prepare(
        "SELECT COUNT(*) FROM peminjaman WHERE id_user = :uid AND status_peminjaman = 'dipinjam'"
    );
    $stmtCount->execute(['uid' => $idUser]);
    $activeLoanCount = (int)$stmtCount->fetchColumn();

    if ($activeLoanCount >= MAX_PINJAMAN) {
        throw new RuntimeException(
            'Anda sudah mencapai batas maksimal pinjaman aktif (' . MAX_PINJAMAN . ' buku). '
            . 'Kembalikan buku terlebih dahulu.'
        );
    }

    // 3. Cek duplikasi — tidak boleh pinjam buku yang sama saat masih dipinjam
    $stmtDup = $pdo->prepare(
        "SELECT id_peminjaman FROM peminjaman
         WHERE id_user = :uid AND id_buku = :bid AND status_peminjaman = 'dipinjam'
         LIMIT 1"
    );
    $stmtDup->execute(['uid' => $idUser, 'bid' => $idBuku]);
    if ($stmtDup->fetch()) {
        throw new RuntimeException('Anda sudah meminjam buku "' . $buku['judul'] . '" dan belum mengembalikannya.');
    }

    // 4. Kurangi stok
    if ($hasStok) {
        $stmtStock = $pdo->prepare(
            "UPDATE buku SET stok_tersedia = stok_tersedia - 1 WHERE id_buku = :id AND stok_tersedia > 0"
        );
        $stmtStock->execute(['id' => $idBuku]);

        if ($stmtStock->rowCount() === 0) {
            throw new RuntimeException('Gagal mengurangi stok. Silakan coba lagi.');
        }
    }

    // 5. Insert peminjaman
    // Cek kolom opsional
    $colDenda = $pdo->query("SHOW COLUMNS FROM peminjaman LIKE 'denda'");
    $hasDenda = $colDenda && $colDenda->rowCount() > 0;
    $colKembali = $pdo->query("SHOW COLUMNS FROM peminjaman LIKE 'tanggal_kembali_aktual'");
    $hasKembali = $colKembali && $colKembali->rowCount() > 0;

    $insertCols = 'id_user, id_buku, tanggal_peminjaman, tanggal_pengembalian, status_peminjaman';
    $insertVals = ':uid, :bid, CURDATE(), DATE_ADD(CURDATE(), INTERVAL :dur DAY), \'dipinjam\'';
    if ($hasKembali) {
        $insertCols .= ', tanggal_kembali_aktual';
        $insertVals .= ', NULL';
    }
    if ($hasDenda) {
        $insertCols .= ', denda';
        $insertVals .= ', 0';
    }

    $stmtInsert = $pdo->prepare(
        "INSERT INTO peminjaman ({$insertCols}) VALUES ({$insertVals})"
    );
    $stmtInsert->execute([
        'uid' => $idUser,
        'bid' => $idBuku,
        'dur' => DURASI_PINJAM_HARI,
    ]);

    $pdo->commit();

    $jatuhTempo = date('d M Y', strtotime('+' . DURASI_PINJAM_HARI . ' days'));
    set_flash('message',
        'Berhasil meminjam buku "' . $buku['judul'] . '". Jatuh tempo pengembalian: ' . $jatuhTempo . '.',
        'success'
    );

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('message', $e->getMessage(), 'danger');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[Perpustakaan] Borrow Error: ' . $e->getMessage());
    set_flash('message', 'Terjadi kesalahan sistem saat peminjaman. Silakan coba lagi.', 'danger');
}

header('Location: ' . BASE_URL . '/public/catalog.php');
exit;
