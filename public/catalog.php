<?php
/* FILE: public/catalog.php — Halaman daftar buku (search + filter kategori + pagination)
 *
 * Menampilkan semua buku dengan card layout. Tombol Pinjam jika stok > 0,
 * tombol Reservasi jika stok == 0. Rata-rata rating ditampilkan per buku.
 *
 * Memerlukan: config.php ($pdo, BASE_URL, ITEMS_PER_PAGE)
 * Asumsi kolom buku: id_buku, id_kategori, judul, penulis, tahun_terbit, deskripsi, stok_tersedia
 * Asumsi kolom rating: id_rating, id_buku, rating (1-5)
 */

require_once __DIR__ . '/../config.php';
$pageTitle = 'Katalog Buku';

// --- Ambil parameter ---
$search     = trim($_GET['q'] ?? '');
$kategoriId = (int)($_GET['kategori'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * ITEMS_PER_PAGE;

try {
    // --- Daftar kategori untuk filter dropdown ---
    $stmtKat = $pdo->query("SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
    $kategoriList = $stmtKat->fetchAll();

    // --- Cek apakah kolom stok_tersedia ada di tabel buku ---
    $colStok = $pdo->query("SHOW COLUMNS FROM buku LIKE 'stok_tersedia'");
    $hasStok = $colStok && $colStok->rowCount() > 0;

    // --- Cek apakah tabel rating ada ---
    $hasRating = false;
    try {
        $pdo->query("SELECT 1 FROM rating LIMIT 1");
        $hasRating = true;
    } catch (PDOException $e) {
        $hasRating = false;
    }

    // --- Build WHERE clause ---
    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]       = "(b.judul LIKE :q1 OR b.penulis LIKE :q2)";
        $params['q1']  = "%{$search}%";
        $params['q2']  = "%{$search}%";
    }
    if ($kategoriId > 0) {
        $where[]        = "b.id_kategori = :kat";
        $params['kat']  = $kategoriId;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // --- Count total buku ---
    $stmtCount = $pdo->prepare("SELECT COUNT(DISTINCT b.id_buku) FROM buku b {$whereSql}");
    $stmtCount->execute($params);
    $totalItems = (int)$stmtCount->fetchColumn();
    $totalPages = max(1, (int)ceil($totalItems / ITEMS_PER_PAGE));

    // --- Build SELECT columns ---
    $selectStok   = $hasStok   ? 'b.stok_tersedia' : '1 AS stok_tersedia';
    $selectRating = $hasRating
        ? 'COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.id_rating) AS total_rating'
        : '0 AS avg_rating, 0 AS total_rating';
    $joinRating   = $hasRating ? 'LEFT JOIN rating r ON r.id_buku = b.id_buku' : '';

    // --- Fetch buku ---
    $sql = "SELECT b.id_buku, b.id_kategori, b.judul, b.penulis, b.tahun_terbit, b.deskripsi,
                   {$selectStok}, k.nama_kategori,
                   {$selectRating}
            FROM buku b
            JOIN kategori k ON b.id_kategori = k.id_kategori
            {$joinRating}
            {$whereSql}
            GROUP BY b.id_buku
            ORDER BY b.id_buku DESC
            LIMIT :lim OFFSET :off";

    $stmtBuku = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmtBuku->bindValue(":{$k}", $v);
    }
    $stmtBuku->bindValue(':lim', ITEMS_PER_PAGE, PDO::PARAM_INT);
    $stmtBuku->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmtBuku->execute();
    $bukuList = $stmtBuku->fetchAll();

} catch (PDOException $ex) {
    error_log('[Perpustakaan] Catalog Error: ' . $ex->getMessage());
    $bukuList     = [];
    $kategoriList = [];
    $totalItems   = 0;
    $totalPages   = 1;
}

require_once __DIR__ . '/../inc/header.php';
?>

<!-- ===== Search + Filter ===== -->
<div class="page-header">
    <h1 class="h3"><i class="bi bi-grid-3x3-gap-fill"></i> Katalog Buku</h1>
    <p class="text-muted mb-0">Cari dan temukan buku favoritmu. Total: <strong><?= $totalItems ?></strong> buku.</p>
</div>

<form method="get" class="row g-2 mb-4">
    <div class="col-md-5">
        <input type="text" name="q" class="form-control" placeholder="Cari judul atau penulis..."
               value="<?= e($search) ?>">
    </div>
    <div class="col-md-4">
        <select name="kategori" class="form-select">
            <option value="0">&mdash; Semua Kategori &mdash;</option>
            <?php foreach ($kategoriList as $kat): ?>
                <option value="<?= (int)$kat['id_kategori'] ?>"
                    <?= $kategoriId === (int)$kat['id_kategori'] ? 'selected' : '' ?>>
                    <?= e($kat['nama_kategori']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <button type="submit" class="btn btn-dark w-100"><i class="bi bi-search"></i> Cari</button>
    </div>
</form>

<!-- ===== Daftar Buku ===== -->
<?php if (empty($bukuList)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
        <p class="mt-2">Tidak ada buku ditemukan.</p>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($bukuList as $bk): ?>
            <div class="col">
                <div class="card book-card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <!-- Kategori & Stok -->
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-secondary"><?= e($bk['nama_kategori']) ?></span>
                            <?php if ((int)$bk['stok_tersedia'] > 0): ?>
                                <span class="badge bg-success badge-stock">Stok: <?= (int)$bk['stok_tersedia'] ?></span>
                            <?php else: ?>
                                <span class="badge bg-danger badge-stock">Habis</span>
                            <?php endif; ?>
                        </div>

                        <!-- Judul & Penulis -->
                        <h5 class="card-title mb-1"><?= e($bk['judul']) ?></h5>
                        <p class="text-muted small mb-1">
                            <i class="bi bi-person"></i> <?= e($bk['penulis']) ?> &middot; <?= e($bk['tahun_terbit']) ?>
                        </p>

                        <!-- Rating -->
                        <?php $avg = round((float)$bk['avg_rating'], 1); ?>
                        <div class="star-rating small mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?= $i <= round($avg)
                                    ? '<i class="bi bi-star-fill"></i>'
                                    : '<i class="bi bi-star"></i>' ?>
                            <?php endfor; ?>
                            <span class="text-muted ms-1">(<?= $avg ?>/5, <?= (int)$bk['total_rating'] ?> ulasan)</span>
                        </div>

                        <!-- Deskripsi singkat -->
                        <p class="card-text small text-muted flex-grow-1">
                            <?= e(mb_strimwidth($bk['deskripsi'] ?? '', 0, 120, '...')) ?>
                        </p>

                        <!-- Aksi -->
                        <div class="d-flex gap-2 mt-auto">
                            <a href="<?= BASE_URL ?>/public/book_detail.php?id=<?= (int)$bk['id_buku'] ?>"
                               class="btn btn-outline-dark btn-sm flex-fill">
                                <i class="bi bi-eye"></i> Detail
                            </a>
                            <?php if ((int)$bk['stok_tersedia'] > 0): ?>
                                <form method="post" action="<?= BASE_URL ?>/public/borrow_action.php" class="flex-fill">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_buku" value="<?= (int)$bk['id_buku'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm w-100">
                                        <i class="bi bi-bookmark-plus"></i> Pinjam
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= BASE_URL ?>/public/reserve_action.php" class="flex-fill">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_buku" value="<?= (int)$bk['id_buku'] ?>">
                                    <button type="submit" class="btn btn-warning btn-sm w-100">
                                        <i class="bi bi-clock"></i> Reservasi
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ===== Pagination ===== -->
    <?php if ($totalPages > 1): ?>
        <?php
        // Query string tanpa page
        $qsArr = array_filter(['q' => $search, 'kategori' => $kategoriId ?: null]);
        $qs    = $qsArr ? '&' . http_build_query($qsArr) : '';

        // Window pagination: tampilkan 5 halaman di sekitar current page
        $winStart = max(1, $page - 2);
        $winEnd   = min($totalPages, $page + 2);
        ?>
        <nav class="mt-4" aria-label="Pagination">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?><?= $qs ?>">&laquo; Prev</a>
                </li>
                <?php if ($winStart > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1<?= $qs ?>">1</a>
                    </li>
                    <?php if ($winStart > 2): ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $winStart; $p <= $winEnd; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?><?= $qs ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($winEnd < $totalPages): ?>
                    <?php if ($winEnd < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $totalPages ?><?= $qs ?>"><?= $totalPages ?></a>
                    </li>
                <?php endif; ?>

                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?><?= $qs ?>">Next &raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
