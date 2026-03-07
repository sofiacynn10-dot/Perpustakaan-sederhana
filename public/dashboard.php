<?php
/* FILE: public/dashboard.php — Dashboard utama anggota (top navbar layout)
 *
 * Menampilkan:
 *   - Ringkasan profil user
 *   - Statistik (pinjaman aktif, total dikembalikan, total denda)
 *   - Quick action buttons (Katalog, Pinjaman Saya, Profil)
 *   - Tabel pinjaman aktif (ringkas)
 *   - Riwayat peminjaman terakhir
 *
 * Menggunakan config.php ($pdo) + inc/header.php (top navbar) + inc/footer.php
 */

require_once __DIR__ . '/../config.php';

$pageTitle = 'Dashboard';
$idUser = (int)($_SESSION['id_user'] ?? 0);

if ($idUser <= 0) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

try {
    // --- Profil user ---
    $stmtUser = $pdo->prepare("SELECT * FROM user WHERE id_user = :id");
    $stmtUser->execute(['id' => $idUser]);
    $user = $stmtUser->fetch();
} catch (PDOException $ex) {
    error_log('[Perpustakaan] Dashboard User Error: ' . $ex->getMessage());
    $user = null;
}

// Default stats
$stats = ['aktif' => 0, 'selesai' => 0, 'total_denda' => 0];
$pinjamanAktif = [];
$riwayat = [];
$resCount = 0;

try {
    // --- Cek apakah kolom 'denda' ada di tabel peminjaman ---
    $colCheck = $pdo->query("SHOW COLUMNS FROM peminjaman LIKE 'denda'");
    $hasDenda = $colCheck && $colCheck->rowCount() > 0;

    // --- Statistik ---
    if ($hasDenda) {
        $stmtStats = $pdo->prepare(
            "SELECT
                SUM(CASE WHEN status_peminjaman = 'dipinjam' THEN 1 ELSE 0 END) AS aktif,
                SUM(CASE WHEN status_peminjaman = 'dikembalikan' THEN 1 ELSE 0 END) AS selesai,
                COALESCE(SUM(CASE WHEN denda > 0 THEN denda ELSE 0 END), 0) AS total_denda
             FROM peminjaman WHERE id_user = :uid"
        );
    } else {
        $stmtStats = $pdo->prepare(
            "SELECT
                SUM(CASE WHEN status_peminjaman = 'dipinjam' THEN 1 ELSE 0 END) AS aktif,
                SUM(CASE WHEN status_peminjaman = 'dikembalikan' THEN 1 ELSE 0 END) AS selesai,
                0 AS total_denda
             FROM peminjaman WHERE id_user = :uid"
        );
    }
    $stmtStats->execute(['uid' => $idUser]);
    $stats = $stmtStats->fetch() ?: $stats;

    // --- Pinjaman aktif ---
    $stmtAktif = $pdo->prepare(
        "SELECT p.id_peminjaman, b.judul, b.id_buku, p.tanggal_peminjaman, p.tanggal_pengembalian
         FROM peminjaman p
         JOIN buku b ON p.id_buku = b.id_buku
         WHERE p.id_user = :uid AND p.status_peminjaman = 'dipinjam'
         ORDER BY p.tanggal_peminjaman DESC
         LIMIT 5"
    );
    $stmtAktif->execute(['uid' => $idUser]);
    $pinjamanAktif = $stmtAktif->fetchAll();

    // --- Riwayat terakhir ---
    // Cek kolom tanggal_kembali_aktual dan denda
    $colCheck2 = $pdo->query("SHOW COLUMNS FROM peminjaman LIKE 'tanggal_kembali_aktual'");
    $hasKembaliAktual = $colCheck2 && $colCheck2->rowCount() > 0;

    if ($hasDenda && $hasKembaliAktual) {
        $sqlRiwayat = "SELECT p.id_peminjaman, b.judul, b.id_buku, p.tanggal_peminjaman,
                        p.tanggal_pengembalian, p.tanggal_kembali_aktual, p.status_peminjaman, p.denda
                 FROM peminjaman p
                 JOIN buku b ON p.id_buku = b.id_buku
                 WHERE p.id_user = :uid
                 ORDER BY p.id_peminjaman DESC
                 LIMIT 10";
    } else {
        $sqlRiwayat = "SELECT p.id_peminjaman, b.judul, b.id_buku, p.tanggal_peminjaman,
                        p.tanggal_pengembalian, p.status_peminjaman,
                        NULL AS tanggal_kembali_aktual, 0 AS denda
                 FROM peminjaman p
                 JOIN buku b ON p.id_buku = b.id_buku
                 WHERE p.id_user = :uid
                 ORDER BY p.id_peminjaman DESC
                 LIMIT 10";
    }
    $stmtRiwayat = $pdo->prepare($sqlRiwayat);
    $stmtRiwayat->execute(['uid' => $idUser]);
    $riwayat = $stmtRiwayat->fetchAll();

    // --- Reservasi aktif count ---
    try {
        $stmtResCount = $pdo->prepare(
            "SELECT COUNT(*) FROM reservasi WHERE id_user = :uid AND status = 'aktif'"
        );
        $stmtResCount->execute(['uid' => $idUser]);
        $resCount = (int)$stmtResCount->fetchColumn();
    } catch (PDOException $e) {
        // Tabel reservasi belum ada
        $resCount = 0;
    }

} catch (PDOException $ex) {
    error_log('[Perpustakaan] Dashboard Stats Error: ' . $ex->getMessage());
    // $user tetap aman karena sudah di-fetch di try/catch sebelumnya
}

require_once __DIR__ . '/../inc/header.php';
?>

<!-- ===== Header ===== -->
<div class="page-header">
    <h1 class="h3">
        <i class="bi bi-speedometer2"></i>
        Selamat datang, <?= e($user['nama'] ?? $_SESSION['username'] ?? 'User') ?>!
    </h1>
    <p class="text-muted mb-0">Kelola pinjaman, cari buku, dan pantau riwayatmu dari sini.</p>
</div>

<!-- ===== Stat Cards ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 bg-success bg-opacity-10 d-flex align-items-center justify-content-center"
                     style="width:50px;height:50px;font-size:1.5rem;flex-shrink:0;">
                    <i class="bi bi-book text-success"></i>
                </div>
                <div>
                    <div class="text-muted small">Pinjaman Aktif</div>
                    <div class="h4 mb-0 fw-bold"><?= (int)($stats['aktif'] ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                     style="width:50px;height:50px;font-size:1.5rem;flex-shrink:0;">
                    <i class="bi bi-check-circle text-primary"></i>
                </div>
                <div>
                    <div class="text-muted small">Dikembalikan</div>
                    <div class="h4 mb-0 fw-bold"><?= (int)($stats['selesai'] ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 bg-warning bg-opacity-10 d-flex align-items-center justify-content-center"
                     style="width:50px;height:50px;font-size:1.5rem;flex-shrink:0;">
                    <i class="bi bi-clock-history text-warning"></i>
                </div>
                <div>
                    <div class="text-muted small">Reservasi</div>
                    <div class="h4 mb-0 fw-bold"><?= $resCount ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 <?= (int)($stats['total_denda'] ?? 0) > 0 ? 'bg-danger bg-opacity-10' : 'bg-secondary bg-opacity-10' ?> d-flex align-items-center justify-content-center"
                     style="width:50px;height:50px;font-size:1.5rem;flex-shrink:0;">
                    <i class="bi bi-cash-coin <?= (int)($stats['total_denda'] ?? 0) > 0 ? 'text-danger' : 'text-secondary' ?>"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Denda</div>
                    <div class="h5 mb-0 fw-bold <?= (int)($stats['total_denda'] ?? 0) > 0 ? 'text-danger' : '' ?>">
                        Rp <?= number_format((int)($stats['total_denda'] ?? 0), 0, ',', '.') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- ===== Kiri: Profil + Quick Actions ===== -->
    <div class="col-lg-4">

        <!-- Profil -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body text-center py-4">
                <div class="profile-avatar mx-auto mb-3">
                    <?= strtoupper(mb_substr($user['nama'] ?? 'U', 0, 1)) ?>
                </div>
                <h5 class="mb-1"><?= e($user['nama'] ?? '-') ?></h5>
                <p class="text-muted small mb-2">@<?= e($user['username'] ?? '-') ?></p>
                <span class="badge bg-success"><?= e(ucfirst($user['role'] ?? 'anggota')) ?></span>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted"><i class="bi bi-envelope"></i> Email</span>
                    <span><?= e($user['email'] ?? '-') ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted"><i class="bi bi-telephone"></i> HP</span>
                    <span><?= e($user['no_handphone'] ?? '-') ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted"><i class="bi bi-geo-alt"></i> Alamat</span>
                    <span><?= e(mb_strimwidth($user['alamat'] ?? '-', 0, 30, '...')) ?></span>
                </li>
            </ul>
            <div class="card-body">
                <a href="<?= BASE_URL ?>/public/profile.php" class="btn btn-outline-dark btn-sm w-100">
                    <i class="bi bi-pencil-square"></i> Edit Profil
                </a>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-lightning"></i> Aksi Cepat</h6>
                <div class="d-grid gap-2">
                    <a href="<?= BASE_URL ?>/public/catalog.php" class="btn btn-success">
                        <i class="bi bi-grid-3x3-gap-fill"></i> Jelajahi Katalog
                    </a>
                    <a href="<?= BASE_URL ?>/public/my_loans.php" class="btn btn-outline-dark">
                        <i class="bi bi-journal-bookmark-fill"></i> Pinjaman Saya
                    </a>
                    <a href="<?= BASE_URL ?>/public/profile.php" class="btn btn-outline-dark">
                        <i class="bi bi-shield-lock"></i> Ganti Password
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Kanan: Pinjaman aktif + Riwayat ===== -->
    <div class="col-lg-8">

        <!-- Pinjaman Aktif -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-book"></i> Pinjaman Aktif</h5>
                <span class="badge bg-light text-dark"><?= (int)($stats['aktif'] ?? 0) ?>/<?= MAX_PINJAMAN ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pinjamanAktif)): ?>
                    <p class="text-muted text-center py-4 mb-0">
                        <i class="bi bi-inbox" style="font-size:1.5rem;"></i><br>
                        Tidak ada pinjaman aktif. <a href="<?= BASE_URL ?>/public/catalog.php">Cari buku?</a>
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Buku</th>
                                    <th>Tgl Pinjam</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Sisa</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pinjamanAktif as $p):
                                    $jatuhTempo = new DateTime($p['tanggal_pengembalian']);
                                    $now = new DateTime();
                                    $diff = (int)$now->diff($jatuhTempo)->format('%r%a');
                                    $badgeClass = $diff < 0 ? 'bg-danger' : ($diff <= 3 ? 'bg-warning text-dark' : 'bg-info');
                                    $sisaLabel  = $diff < 0
                                        ? 'Terlambat ' . abs($diff) . 'hr'
                                        : ($diff === 0 ? 'Hari ini!' : $diff . ' hari');
                                ?>
                                    <tr>
                                        <td>
                                            <a href="<?= BASE_URL ?>/public/book_detail.php?id=<?= (int)$p['id_buku'] ?>"
                                               class="text-decoration-none fw-semibold"><?= e($p['judul']) ?></a>
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
                                                    <i class="bi bi-arrow-return-left"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ((int)($stats['aktif'] ?? 0) > 5): ?>
                        <div class="p-2 text-center">
                            <a href="<?= BASE_URL ?>/public/my_loans.php" class="text-decoration-none small">
                                Lihat semua pinjaman &raquo;
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Riwayat Peminjaman -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Riwayat Terbaru</h5>
                <a href="<?= BASE_URL ?>/public/my_loans.php" class="btn btn-sm btn-light">Lihat Semua</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($riwayat)): ?>
                    <p class="text-muted text-center py-4 mb-0">Belum ada riwayat peminjaman.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Buku</th>
                                    <th>Tgl Pinjam</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riwayat as $row): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= BASE_URL ?>/public/book_detail.php?id=<?= (int)$row['id_buku'] ?>"
                                               class="text-decoration-none"><?= e($row['judul']) ?></a>
                                        </td>
                                        <td><?= e($row['tanggal_peminjaman']) ?></td>
                                        <td><?= e($row['tanggal_pengembalian']) ?></td>
                                        <td>
                                            <?php if ($row['status_peminjaman'] === 'dipinjam'): ?>
                                                <span class="badge bg-warning text-dark">Dipinjam</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Dikembalikan</span>
                                            <?php endif; ?>
                                            <?php if ((int)($row['denda'] ?? 0) > 0): ?>
                                                <br><small class="text-danger">Denda: Rp <?= number_format((int)$row['denda'], 0, ',', '.') ?></small>
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
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
