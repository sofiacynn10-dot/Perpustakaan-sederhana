<?php
/* FILE: public/api/search.php — Endpoint JSON read-only untuk search katalog buku
 *
 * Parameter GET:
 *   q        = keyword pencarian (judul/penulis), opsional
 *   kategori = id_kategori filter, opsional
 *   page     = halaman (default 1)
 *   limit    = jumlah per halaman (default 10, max 50)
 *
 * Response: JSON
 *   {
 *     "success": true,
 *     "data": [ { id_buku, judul, penulis, tahun_terbit, stok_tersedia, nama_kategori, avg_rating }, ... ],
 *     "pagination": { page, limit, total, totalPages }
 *   }
 *
 * Contoh curl:
 *   curl "http://localhost/perpustakaan/public/api/search.php?q=harry&kategori=1&page=1&limit=5"
 *
 * Auth: Memerlukan session login (hapus blok auth di bawah jika ingin publik).
 *
 * Asumsi kolom buku: id_buku, id_kategori, judul, penulis, tahun_terbit, stok_tersedia
 * Asumsi kolom rating: id_rating, id_buku, rating
 */

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// --- Auth check (hapus blok ini jika API ingin diakses tanpa login) ---
if (!isset($_SESSION['id_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Silakan login terlebih dahulu.']);
    exit;
}

// --- Parameter ---
$search     = trim($_GET['q'] ?? '');
$kategoriId = (int)($_GET['kategori'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = min(50, max(1, (int)($_GET['limit'] ?? ITEMS_PER_PAGE)));
$offset     = ($page - 1) * $limit;

try {
    // Build WHERE
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

    // Count total
    $stmtCount = $pdo->prepare("SELECT COUNT(DISTINCT b.id_buku) FROM buku b {$whereSql}");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    // Fetch data + avg rating
    $sql = "SELECT b.id_buku, b.judul, b.penulis, b.tahun_terbit, b.stok_tersedia,
                   k.nama_kategori,
                   COALESCE(AVG(r.rating), 0) AS avg_rating,
                   COUNT(r.id_rating) AS total_ulasan
            FROM buku b
            JOIN kategori k ON b.id_kategori = k.id_kategori
            LEFT JOIN rating r ON r.id_buku = b.id_buku
            {$whereSql}
            GROUP BY b.id_buku
            ORDER BY b.id_buku DESC
            LIMIT :lim OFFSET :off";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(":{$k}", $v);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll();

    // Format numeric fields
    foreach ($data as &$row) {
        $row['id_buku']         = (int)$row['id_buku'];
        $row['stok_tersedia']   = (int)$row['stok_tersedia'];
        $row['avg_rating']      = round((float)$row['avg_rating'], 1);
        $row['total_ulasan']    = (int)$row['total_ulasan'];
    }
    unset($row);

    echo json_encode([
        'success'    => true,
        'data'       => $data,
        'pagination' => [
            'page'       => $page,
            'limit'      => $limit,
            'total'      => $total,
            'totalPages' => max(1, (int)ceil($total / $limit)),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    error_log('[Perpustakaan] API Search Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server.']);
}
