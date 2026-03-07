<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../include/layout.php';

require_role('admin');

function stat_count(mysqli $conn, string $sql, array $params = []): int
{
    $stmt = $conn->prepare($sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();
    return (int) $total;
}

$countBuku         = stat_count($conn, "SELECT COUNT(*) FROM buku");
$countDipinjam     = stat_count($conn, "SELECT COUNT(*) FROM peminjaman WHERE status_peminjaman = 'dipinjam'");
$countAnggota      = stat_count($conn, "SELECT COUNT(*) FROM user WHERE role = 'anggota'");
$countTerlambat    = stat_count($conn, "SELECT COUNT(*) FROM peminjaman WHERE status_peminjaman = 'dipinjam' AND tanggal_pengembalian < CURDATE()");

$stmtRecent = $conn->prepare(
    "SELECT p.id_peminjaman, u.nama, b.judul, p.tanggal_peminjaman, p.status_peminjaman
     FROM peminjaman p
     JOIN user u ON p.id_user = u.id_user
     JOIN buku b ON p.id_buku = b.id_buku
     ORDER BY p.id_peminjaman DESC
     LIMIT 5"
);
$stmtRecent->execute();
$recentPinjam = $stmtRecent->get_result();

render_head('Dashboard Admin');
render_nav('admin', 'dashboard.php', $_SESSION['username']);
?>
<div class="page-title">
    <span class="badge-soft mb-3">Dashboard Admin</span>
    <h1 class="h3 mb-1">Panel Pengelolaan Perpustakaan</h1>
    <p class="text-muted mb-0">Kelola anggota, buku, kategori, dan transaksi peminjaman dari satu tempat.</p>
</div>
<div class="row g-4">
    <?php
    $cards = [
        ['Total Buku', $countBuku, 'bg-primary'],
        ['Buku Dipinjam', $countDipinjam, 'bg-warning'],
        ['Total Anggota', $countAnggota, 'bg-success'],
        ['Keterlambatan', $countTerlambat, 'bg-danger'],
    ];
    $icons = [
        '📚',
        '📖',
        '👥',
        '⏰',
    ];
    $i = 0;
    foreach ($cards as [$label, $value, $color]): ?>
    <div class="col-md-6 col-xl-3">
        <div class="panel card stat-card h-100 border-0">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon rounded-3 d-flex align-items-center justify-content-center <?php echo $color; ?> bg-opacity-10" style="width:56px;height:56px;font-size:1.6rem;flex-shrink:0;">
                    <?php echo $icons[$i]; ?>
                </div>
                <div>
                    <div class="text-muted small mb-1"><?php echo $label; ?></div>
                    <div class="display-6 fw-bold lh-1"><?php echo $value; ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php $i++; endforeach; ?>
</div>
<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="panel card">
            <div class="card-body">
                <h2 class="h5 mb-3">Tindakan Cepat</h2>
                <div class="d-flex flex-wrap gap-2">
                    <a href="/perpustakaan/admin/anggota.php" class="btn btn-outline-dark flex-fill text-center">Kelola Anggota</a>
                    <a href="/perpustakaan/admin/buku.php" class="btn btn-outline-dark flex-fill text-center">Kelola Buku</a>
                    <a href="/perpustakaan/admin/kategori.php" class="btn btn-outline-dark flex-fill text-center">Kelola Kategori</a>
                    <a href="/perpustakaan/admin/peminjaman.php" class="btn btn-outline-dark flex-fill text-center">Kelola Peminjaman</a>
                </div>
            </div>
        </div>
    </div>  
</div>
</div>
<?php
render_footer();
?>
