<?php
/* FILE: public/book_detail.php — Detail buku + Pinjam/Reservasi + rata-rata rating + form ulasan
 *
 * Menampilkan detail lengkap buku, rating dari semua user, dan form untuk
 * memberikan rating/ulasan (hanya jika user sudah mengembalikan buku tersebut).
 *
 * Asumsi kolom:
 *   buku: id_buku, id_kategori, judul, penulis, tahun_terbit, deskripsi, stok_tersedia
 *   rating: id_rating, id_user, id_buku, rating (1-5), ulasan (TEXT), tanggal_rating
 *   peminjaman: id_peminjaman, id_user, id_buku, status_peminjaman, tanggal_kembali_aktual
 */

require_once __DIR__ . '/../config.php';

$idBuku = (int)($_GET['id'] ?? 0);
if ($idBuku <= 0) {
    header('Location: ' . BASE_URL . '/public/catalog.php');
    exit;
}

try {
    // --- Fetch buku ---
    $stmt = $pdo->prepare(
        "SELECT b.*, k.nama_kategori
         FROM buku b
         JOIN kategori k ON b.id_kategori = k.id_kategori
         WHERE b.id_buku = :id"
    );
    $stmt->execute(['id' => $idBuku]);
    $buku = $stmt->fetch();

    if (!$buku) {
        header('Location: ' . BASE_URL . '/public/catalog.php');
        exit;
    }

    // Pastikan stok_tersedia ada (default 1 jika kolom belum ditambahkan)
    if (!isset($buku['stok_tersedia'])) {
        $buku['stok_tersedia'] = 1;
    }

    $pageTitle = $buku['judul'];

    $idUser = (int)($_SESSION['id_user'] ?? 0);

    // --- Cek apakah tabel rating ada ---
    $hasRatingTable = false;
    try {
        $pdo->query("SELECT 1 FROM rating LIMIT 1");
        $hasRatingTable = true;
    } catch (PDOException $e) {
        $hasRatingTable = false;
    }

    // --- Rating statistik ---
    $avg = 0;
    $totalR = 0;
    $ulasanList = [];
    $sudahRating = false;

    if ($hasRatingTable) {
        $stmtRating = $pdo->prepare(
            "SELECT COALESCE(AVG(rating), 0) AS avg_r, COUNT(*) AS total_r
             FROM rating WHERE id_buku = :id"
        );
        $stmtRating->execute(['id' => $idBuku]);
        $ratingInfo = $stmtRating->fetch();
        $avg    = round((float)$ratingInfo['avg_r'], 1);
        $totalR = (int)$ratingInfo['total_r'];

        // --- Semua ulasan ---
        $stmtUlasan = $pdo->prepare(
            "SELECT r.*, u.nama
             FROM rating r
             JOIN user u ON r.id_user = u.id_user
             WHERE r.id_buku = :id
             ORDER BY r.tanggal_rating DESC"
        );
        $stmtUlasan->execute(['id' => $idBuku]);
        $ulasanList = $stmtUlasan->fetchAll();

        // --- Cek apakah user sudah memberi rating ---
        $stmtCekRating = $pdo->prepare(
            "SELECT id_rating FROM rating WHERE id_user = :uid AND id_buku = :bid LIMIT 1"
        );
        $stmtCekRating->execute(['uid' => $idUser, 'bid' => $idBuku]);
        $sudahRating = (bool)$stmtCekRating->fetch();
    }

    // --- Cek apakah user sudah pernah pinjam & kembalikan buku ini ---
    $stmtCekPinjam = $pdo->prepare(
        "SELECT id_peminjaman FROM peminjaman
         WHERE id_user = :uid AND id_buku = :bid AND status_peminjaman = 'dikembalikan'
         LIMIT 1"
    );
    $stmtCekPinjam->execute(['uid' => $idUser, 'bid' => $idBuku]);
    $sudahKembalikan = (bool)$stmtCekPinjam->fetch();

    // --- Cek apakah user sudah punya reservasi aktif ---
    $reservasiAktif = false;
    try {
        $stmtCekRes = $pdo->prepare(
            "SELECT id_reservasi, posisi_antrian FROM reservasi
             WHERE id_user = :uid AND id_buku = :bid AND status = 'aktif' LIMIT 1"
        );
        $stmtCekRes->execute(['uid' => $idUser, 'bid' => $idBuku]);
        $reservasiAktif = $stmtCekRes->fetch();
    } catch (PDOException $e) {
        // Tabel reservasi belum ada
        $reservasiAktif = false;
    }

} catch (PDOException $ex) {
    error_log('[Perpustakaan] Book Detail Error: ' . $ex->getMessage());
    header('Location: ' . BASE_URL . '/public/catalog.php');
    exit;
}

require_once __DIR__ . '/../inc/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/public/catalog.php">Katalog</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($buku['judul']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <!-- ===== Detail Buku (kiri) ===== -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <span class="badge bg-secondary"><?= e($buku['nama_kategori']) ?></span>
                    <?php if ((int)$buku['stok_tersedia'] > 0): ?>
                        <span class="badge bg-success">Stok: <?= (int)$buku['stok_tersedia'] ?></span>
                    <?php else: ?>
                        <span class="badge bg-danger">Habis</span>
                    <?php endif; ?>
                </div>
                <h2 class="mb-1"><?= e($buku['judul']) ?></h2>
                <p class="text-muted mb-2">
                    <i class="bi bi-person"></i> <?= e($buku['penulis']) ?>
                    &middot; <i class="bi bi-calendar3"></i> <?= e($buku['tahun_terbit']) ?>
                </p>

                <!-- Rating -->
                <div class="star-rating mb-3">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?= $i <= round($avg)
                            ? '<i class="bi bi-star-fill"></i>'
                            : '<i class="bi bi-star"></i>' ?>
                    <?php endfor; ?>
                    <span class="text-muted ms-1">(<?= $avg ?>/5 dari <?= $totalR ?> ulasan)</span>
                </div>

                <hr>

                <h5>Deskripsi</h5>
                <p class="mb-0"><?= nl2br(e($buku['deskripsi'] ?? 'Tidak ada deskripsi.')) ?></p>
            </div>
        </div>

        <!-- ===== Ulasan ===== -->
        <div class="card shadow-sm border-0 rounded-3 mt-4" id="ulasan">
            <div class="card-body">
                <h4 class="mb-3"><i class="bi bi-chat-quote"></i> Ulasan (<?= $totalR ?>)</h4>

                <?php if (empty($ulasanList)): ?>
                    <p class="text-muted">Belum ada ulasan untuk buku ini.</p>
                <?php else: ?>
                    <?php foreach ($ulasanList as $ul): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between">
                                <strong><?= e($ul['nama']) ?></strong>
                                <small class="text-muted"><?= e($ul['tanggal_rating']) ?></small>
                            </div>
                            <div class="star-rating small">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?= $i <= (int)$ul['rating']
                                        ? '<i class="bi bi-star-fill"></i>'
                                        : '<i class="bi bi-star"></i>' ?>
                                <?php endfor; ?>
                            </div>
                            <?php if (!empty($ul['ulasan'])): ?>
                                <p class="mb-0 mt-1"><?= nl2br(e($ul['ulasan'])) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== Sidebar (kanan) ===== -->
    <div class="col-lg-4">
        <!-- Aksi Pinjam / Reservasi -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-lightning"></i> Aksi</h5>

                <?php if ((int)$buku['stok_tersedia'] > 0): ?>
                    <form method="post" action="<?= BASE_URL ?>/public/borrow_action.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_buku" value="<?= (int)$buku['id_buku'] ?>">
                        <button type="submit" class="btn btn-success w-100 mb-2">
                            <i class="bi bi-bookmark-plus"></i> Pinjam Buku Ini
                        </button>
                    </form>
                <?php else: ?>
                    <?php if ($reservasiAktif): ?>
                        <div class="alert alert-info small mb-2">
                            <i class="bi bi-info-circle"></i>
                            Anda sudah reservasi buku ini. Posisi antrian: <strong>#<?= (int)$reservasiAktif['posisi_antrian'] ?></strong>
                        </div>
                    <?php else: ?>
                        <p class="text-danger small">
                            <i class="bi bi-exclamation-triangle"></i> Stok habis. Anda dapat melakukan reservasi.
                        </p>
                        <form method="post" action="<?= BASE_URL ?>/public/reserve_action.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_buku" value="<?= (int)$buku['id_buku'] ?>">
                            <button type="submit" class="btn btn-warning w-100 mb-2">
                                <i class="bi bi-clock"></i> Reservasi
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/public/catalog.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-left"></i> Kembali ke Katalog
                </a>
            </div>
        </div>

        <!-- Form Rating (hanya muncul jika sudah mengembalikan & belum rating) -->
        <?php if ($sudahKembalikan && !$sudahRating): ?>
            <div class="card shadow-sm border-0 rounded-3" id="rating">
                <div class="card-body">
                    <h5 class="mb-3"><i class="bi bi-star"></i> Beri Rating & Ulasan</h5>
                    <form method="post" action="<?= BASE_URL ?>/public/rate_action.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_buku" value="<?= (int)$buku['id_buku'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Rating <span class="text-danger">*</span></label>
                            <select name="rating" class="form-select" required>
                                <option value="">Pilih rating</option>
                                <option value="5">&#11088;&#11088;&#11088;&#11088;&#11088; (5 &mdash; Sangat Bagus)</option>
                                <option value="4">&#11088;&#11088;&#11088;&#11088; (4 &mdash; Bagus)</option>
                                <option value="3">&#11088;&#11088;&#11088; (3 &mdash; Cukup)</option>
                                <option value="2">&#11088;&#11088; (2 &mdash; Kurang)</option>
                                <option value="1">&#11088; (1 &mdash; Buruk)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ulasan (opsional)</label>
                            <textarea name="ulasan" class="form-control" rows="3" maxlength="1000"
                                      placeholder="Tulis pendapatmu tentang buku ini..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-send"></i> Kirim Rating
                        </button>
                    </form>
                </div>
            </div>
        <?php elseif ($sudahRating): ?>
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body text-center text-muted">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 2rem;"></i>
                    <p class="mb-0 mt-2">Anda sudah memberikan rating untuk buku ini.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
