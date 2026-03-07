<?php
/* FILE: public/my_loans.php — Pinjaman aktif + riwayat + reservasi + tombol kembalikan + link rating
 *
 * Menampilkan:
 *   1. Pinjaman aktif (status = 'dipinjam') + sisa hari + tombol kembalikan
 *   2. Reservasi aktif (status = 'aktif')
 *   3. Riwayat peminjaman (status = 'dikembalikan') + denda + link rating
 *
 * Asumsi kolom peminjaman: id_peminjaman, id_user, id_buku, tanggal_peminjaman,
 *   tanggal_pengembalian (jatuh tempo), tanggal_kembali_aktual, status_peminjaman, denda
 * Asumsi kolom reservasi: id_reservasi, id_user, id_buku, posisi_antrian, status, tanggal_reservasi
 */

require_once __DIR__ . '/../config.php';
$pageTitle = 'Pinjaman Saya';

$idUser = (int)($_SESSION['id_user'] ?? 0);
if ($idUser <= 0) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$pinjamanAktif   = [];
$pinjamanRiwayat = [];
$reservasiAktif  = [];
$ratedBooks      = [];

try {
    // --- Cek kolom tambahan ---
    $colDenda = $pdo->query("SHOW COLUMNS FROM peminjaman LIKE 'denda'");
    $hasDenda = $colDenda && $colDenda->rowCount() > 0;
    $colKembali = $pdo->query("SHOW COLUMNS FROM peminjaman LIKE 'tanggal_kembali_aktual'");
    $hasKembali = $colKembali && $colKembali->rowCount() > 0;

    // --- Pinjaman aktif ---
    $stmtAktif = $pdo->prepare(
        "SELECT p.id_peminjaman, p.id_user, p.id_buku, p.tanggal_peminjaman,
                p.tanggal_pengembalian, p.status_peminjaman,
                b.judul, b.id_buku AS buku_id
         FROM peminjaman p
         JOIN buku b ON p.id_buku = b.id_buku
         WHERE p.id_user = :uid AND p.status_peminjaman = 'dipinjam'
         ORDER BY p.tanggal_peminjaman DESC"
    );
    $stmtAktif->execute(['uid' => $idUser]);
    $pinjamanAktif = $stmtAktif->fetchAll();

    // --- Riwayat (dikembalikan) ---
    $selectExtra = ($hasDenda ? ', p.denda' : ', 0 AS denda')
                 . ($hasKembali ? ', p.tanggal_kembali_aktual' : ', NULL AS tanggal_kembali_aktual');
    $orderCol = $hasKembali ? 'p.tanggal_kembali_aktual' : 'p.id_peminjaman';

    $stmtRiwayat = $pdo->prepare(
        "SELECT p.id_peminjaman, p.id_user, p.id_buku, p.tanggal_peminjaman,
                p.tanggal_pengembalian, p.status_peminjaman{$selectExtra},
                b.judul, b.id_buku AS buku_id
         FROM peminjaman p
         JOIN buku b ON p.id_buku = b.id_buku
         WHERE p.id_user = :uid AND p.status_peminjaman = 'dikembalikan'
         ORDER BY {$orderCol} DESC"
    );
    $stmtRiwayat->execute(['uid' => $idUser]);
    $pinjamanRiwayat = $stmtRiwayat->fetchAll();

    // --- Reservasi aktif ---
    try {
        $stmtReservasi = $pdo->prepare(
            "SELECT r.*, b.judul
             FROM reservasi r
             JOIN buku b ON r.id_buku = b.id_buku
             WHERE r.id_user = :uid AND r.status = 'aktif'
             ORDER BY r.tanggal_reservasi DESC"
        );
        $stmtReservasi->execute(['uid' => $idUser]);
        $reservasiAktif = $stmtReservasi->fetchAll();
    } catch (PDOException $e) {
        // Tabel reservasi belum ada
        $reservasiAktif = [];
    }

    // --- Daftar buku yang sudah di-rating user ---
    try {
        $stmtRated = $pdo->prepare("SELECT id_buku FROM rating WHERE id_user = :uid");
        $stmtRated->execute(['uid' => $idUser]);
        $ratedBooks = array_column($stmtRated->fetchAll(), 'id_buku');
    } catch (PDOException $e) {
        // Tabel rating belum ada
        $ratedBooks = [];
    }

} catch (PDOException $ex) {
    error_log('[Perpustakaan] My Loans Error: ' . $ex->getMessage());
}

require_once __DIR__ . '/../inc/header.php';
?>

<div class="page-header">
    <h1 class="h3"><i class="bi bi-journal-bookmark-fill"></i> Pinjaman Saya</h1>
    <p class="text-muted mb-0">Kelola pinjaman aktif, lihat riwayat, dan beri rating buku yang sudah dikembalikan.</p>
</div>

<!-- ===== Pinjaman Aktif ===== -->
<div class="card shadow-sm border-0 rounded-3 mb-4">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">
            <i class="bi bi-book"></i> Pinjaman Aktif
            <span class="badge bg-light text-dark ms-2"><?= count($pinjamanAktif) ?>/<?= MAX_PINJAMAN ?></span>
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pinjamanAktif)): ?>
            <p class="text-muted text-center py-4 mb-0">Tidak ada pinjaman aktif saat ini.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Buku</th>
                            <th>Tanggal Pinjam</th>
                            <th>Jatuh Tempo</th>
                            <th>Sisa Hari</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pinjamanAktif as $p):
                            $jatuhTempo = new DateTime($p['tanggal_pengembalian']);
                            $now        = new DateTime();
                            $diff       = (int)$now->diff($jatuhTempo)->format('%r%a');
                            $badgeClass = $diff < 0 ? 'bg-danger' : ($diff <= 3 ? 'bg-warning text-dark' : 'bg-info');
                            $sisaLabel  = $diff < 0
                                ? 'Terlambat ' . abs($diff) . ' hari'
                                : ($diff === 0 ? 'Hari ini!' : $diff . ' hari lagi');
                        ?>
                            <tr>
                                <td>
                                    <a href="<?= BASE_URL ?>/public/book_detail.php?id=<?= (int)$p['buku_id'] ?>"
                                       class="text-decoration-none fw-semibold">
                                        <?= e($p['judul']) ?>
                                    </a>
                                </td>
                                <td><?= e($p['tanggal_peminjaman']) ?></td>
                                <td><?= e($p['tanggal_pengembalian']) ?></td>
                                <td><span class="badge <?= $badgeClass ?>"><?= $sisaLabel ?></span></td>
                                <td>
                                    <form method="post" action="<?= BASE_URL ?>/public/return_action.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_peminjaman" value="<?= (int)$p['id_peminjaman'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success"
                                                onclick="return confirm('Kembalikan buku ini?')">
                                            <i class="bi bi-arrow-return-left"></i> Kembalikan
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Reservasi Aktif ===== -->
<?php if (!empty($reservasiAktif)): ?>
<div class="card shadow-sm border-0 rounded-3 mb-4">
    <div class="card-header bg-warning">
        <h5 class="mb-0">
            <i class="bi bi-clock-history"></i> Reservasi Aktif
            <span class="badge bg-dark ms-2"><?= count($reservasiAktif) ?></span>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Buku</th>
                        <th>Posisi Antrian</th>
                        <th>Tanggal Reservasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservasiAktif as $r): ?>
                        <tr>
                            <td><?= e($r['judul']) ?></td>
                            <td><span class="badge bg-dark">#<?= (int)$r['posisi_antrian'] ?></span></td>
                            <td><?= e($r['tanggal_reservasi']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== Riwayat Peminjaman ===== -->
<div class="card shadow-sm border-0 rounded-3">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0">
            <i class="bi bi-clock-history"></i> Riwayat Peminjaman
            <span class="badge bg-light text-dark ms-2"><?= count($pinjamanRiwayat) ?></span>
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pinjamanRiwayat)): ?>
            <p class="text-muted text-center py-4 mb-0">Belum ada riwayat pengembalian.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Buku</th>
                            <th>Tanggal Pinjam</th>
                            <th>Jatuh Tempo</th>
                            <th>Dikembalikan</th>
                            <th>Denda</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pinjamanRiwayat as $p): ?>
                            <tr>
                                <td>
                                    <a href="<?= BASE_URL ?>/public/book_detail.php?id=<?= (int)$p['buku_id'] ?>"
                                       class="text-decoration-none fw-semibold">
                                        <?= e($p['judul']) ?>
                                    </a>
                                </td>
                                <td><?= e($p['tanggal_peminjaman']) ?></td>
                                <td><?= e($p['tanggal_pengembalian']) ?></td>
                                <td><?= e($p['tanggal_kembali_aktual'] ?? '-') ?></td>
                                <td>
                                    <?php if ((int)($p['denda'] ?? 0) > 0): ?>
                                        <span class="text-danger fw-bold">
                                            Rp <?= number_format((int)$p['denda'], 0, ',', '.') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-success">Tidak ada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array($p['buku_id'], $ratedBooks)): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check"></i> Sudah
                                        </span>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/public/book_detail.php?id=<?= (int)$p['buku_id'] ?>#rating"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-star"></i> Beri Rating
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
