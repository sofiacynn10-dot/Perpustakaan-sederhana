<?php
/* FILE: public/return_action.php — Endpoint POST untuk proses pengembalian buku
 *
 * Langkah:
 *   1. Validasi CSRF & kepemilikan peminjaman
 *   2. Hitung keterlambatan: max(0, datediff(NOW, tanggal_pengembalian)) * DENDA_PER_HARI
 *   3. Update peminjaman: tanggal_kembali_aktual = CURDATE, status = 'dikembalikan', denda
 *   4. Tambah stok_tersedia + 1
 *   5. Cek reservasi aktif untuk buku ini
 *      - Jika ada: alokasikan ke antrian teratas → ubah status = 'terpenuhi',
 *        buat peminjaman baru otomatis, kurangi stok lagi, log notifikasi.
 *
 * Redirect ke my_loans.php dengan flash message.
 *
 * Asumsi kolom:
 *   peminjaman: tanggal_pengembalian (jatuh tempo), tanggal_kembali_aktual, denda
 *   reservasi: id_reservasi, id_user, id_buku, posisi_antrian, status
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
    header('Location: ' . BASE_URL . '/public/my_loans.php');
    exit;
}

require_csrf();

$idUser       = (int)$_SESSION['id_user'];
$idPeminjaman = (int)($_POST['id_peminjaman'] ?? 0);

if ($idPeminjaman <= 0) {
    set_flash('message', 'Data peminjaman tidak valid.', 'danger');
    header('Location: ' . BASE_URL . '/public/my_loans.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // --- Cek kolom opsional ---
    $colKembali = $pdo->query("SHOW COLUMNS FROM peminjaman LIKE 'tanggal_kembali_aktual'");
    $hasKembali = $colKembali && $colKembali->rowCount() > 0;
    $colDenda = $pdo->query("SHOW COLUMNS FROM peminjaman LIKE 'denda'");
    $hasDenda = $colDenda && $colDenda->rowCount() > 0;
    $colStok = $pdo->query("SHOW COLUMNS FROM buku LIKE 'stok_tersedia'");
    $hasStok = $colStok && $colStok->rowCount() > 0;

    // 1. Ambil data peminjaman (pastikan milik user ini & masih dipinjam)
    $stmt = $pdo->prepare(
        "SELECT p.id_peminjaman, p.id_user, p.id_buku, p.tanggal_peminjaman,
                p.tanggal_pengembalian, p.status_peminjaman,
                b.judul, b.id_buku AS buku_id
         FROM peminjaman p
         JOIN buku b ON p.id_buku = b.id_buku
         WHERE p.id_peminjaman = :pid AND p.id_user = :uid AND p.status_peminjaman = 'dipinjam'
         FOR UPDATE"
    );
    $stmt->execute(['pid' => $idPeminjaman, 'uid' => $idUser]);
    $pinjam = $stmt->fetch();

    if (!$pinjam) {
        throw new RuntimeException('Peminjaman tidak ditemukan atau sudah dikembalikan.');
    }

    // 2. Hitung denda keterlambatan
    $jatuhTempo    = new DateTime($pinjam['tanggal_pengembalian']);
    $sekarang      = new DateTime('now');
    $selisihHari   = (int)$sekarang->diff($jatuhTempo)->format('%r%a'); // negatif = terlambat
    $hariTerlambat = max(0, -$selisihHari);
    $denda         = $hariTerlambat * DENDA_PER_HARI;

    // 3. Update peminjaman
    $updateSets = ["status_peminjaman = 'dikembalikan'"];
    $updateParams = ['pid' => $idPeminjaman];

    if ($hasKembali) {
        $updateSets[] = "tanggal_kembali_aktual = CURDATE()";
    }
    if ($hasDenda) {
        $updateSets[] = "denda = :denda";
        $updateParams['denda'] = $denda;
    }

    $stmtUpdate = $pdo->prepare(
        "UPDATE peminjaman SET " . implode(', ', $updateSets) . " WHERE id_peminjaman = :pid"
    );
    $stmtUpdate->execute($updateParams);

    // 4. Tambah stok buku
    if ($hasStok) {
        $stmtStockUp = $pdo->prepare("UPDATE buku SET stok_tersedia = stok_tersedia + 1 WHERE id_buku = :bid");
        $stmtStockUp->execute(['bid' => $pinjam['id_buku']]);
    }

    // 5. Cek reservasi aktif untuk buku ini (antrian teratas)
    $reservasiDialokasikan = false;
    try {
        $stmtRes = $pdo->prepare(
            "SELECT r.id_reservasi, r.id_user, u.nama
             FROM reservasi r
             JOIN user u ON r.id_user = u.id_user
             WHERE r.id_buku = :bid AND r.status = 'aktif'
             ORDER BY r.posisi_antrian ASC
             LIMIT 1
             FOR UPDATE"
        );
        $stmtRes->execute(['bid' => $pinjam['id_buku']]);
        $reservasi = $stmtRes->fetch();

        if ($reservasi) {
            // 5a. Ubah status reservasi → terpenuhi
            $stmtResFulfill = $pdo->prepare(
                "UPDATE reservasi SET status = 'terpenuhi' WHERE id_reservasi = :rid"
            );
            $stmtResFulfill->execute(['rid' => $reservasi['id_reservasi']]);

            // 5b. Buat peminjaman otomatis untuk user yang reservasi
            $autoInsertCols = 'id_user, id_buku, tanggal_peminjaman, tanggal_pengembalian, status_peminjaman';
            $autoInsertVals = ':uid, :bid, CURDATE(), DATE_ADD(CURDATE(), INTERVAL :dur DAY), \'dipinjam\'';
            if ($hasKembali) {
                $autoInsertCols .= ', tanggal_kembali_aktual';
                $autoInsertVals .= ', NULL';
            }
            if ($hasDenda) {
                $autoInsertCols .= ', denda';
                $autoInsertVals .= ', 0';
            }

            $stmtAutoLoan = $pdo->prepare(
                "INSERT INTO peminjaman ({$autoInsertCols}) VALUES ({$autoInsertVals})"
            );
            $stmtAutoLoan->execute([
                'uid' => $reservasi['id_user'],
                'bid' => $pinjam['id_buku'],
                'dur' => DURASI_PINJAM_HARI,
            ]);

            // 5c. Kurangi stok lagi (dialokasikan ke reservasi)
            if ($hasStok) {
                $stmtStockDown = $pdo->prepare(
                    "UPDATE buku SET stok_tersedia = stok_tersedia - 1 WHERE id_buku = :bid AND stok_tersedia > 0"
                );
                $stmtStockDown->execute(['bid' => $pinjam['id_buku']]);
            }

            $reservasiDialokasikan = true;

            // 5d. Log notifikasi ke file
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $logMessage = sprintf(
                "[%s] RESERVASI TERPENUHI: Buku \"%s\" (ID:%d) otomatis dialokasikan ke user \"%s\" (ID:%d) dari reservasi ID:%d\n",
                date('Y-m-d H:i:s'),
                $pinjam['judul'],
                (int)$pinjam['id_buku'],
                $reservasi['nama'],
                (int)$reservasi['id_user'],
                (int)$reservasi['id_reservasi']
            );
            @file_put_contents($logDir . '/notifications.log', $logMessage, FILE_APPEND | LOCK_EX);
        }
    } catch (PDOException $e) {
        // Tabel reservasi belum ada — skip saja
    }

    $pdo->commit();

    // Compose flash message
    $msg = 'Buku "' . $pinjam['judul'] . '" berhasil dikembalikan.';
    if ($hariTerlambat > 0 && $hasDenda) {
        $msg .= ' Terlambat ' . $hariTerlambat . ' hari — Denda: Rp ' . number_format($denda, 0, ',', '.') . '.';
    }
    if ($reservasiDialokasikan) {
        $msg .= ' Buku otomatis dialokasikan ke antrean reservasi berikutnya.';
    }
    set_flash('message', $msg, 'success');

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('message', $e->getMessage(), 'danger');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[Perpustakaan] Return Error: ' . $e->getMessage());
    set_flash('message', 'Terjadi kesalahan sistem saat pengembalian. Silakan coba lagi.', 'danger');
}

header('Location: ' . BASE_URL . '/public/my_loans.php');
exit;
