<?php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../include/auth.php";
require_once __DIR__ . "/../include/layout.php";

require_role("admin");

$message = "";
$alertType = "info";
$editData = null;

if (isset($_GET["hapus"])) {
    $idHapus = (int) $_GET["hapus"];
    if ($idHapus > 0) {
        $delete = mysqli_query($conn, "DELETE FROM peminjaman WHERE id_peminjaman = $idHapus");
        if ($delete) {
            header("Location: /perpustakaan/admin/peminjaman.php?status=hapus");
            exit();
        }
        $message = "Gagal menghapus peminjaman: " . mysqli_error($conn);
        $alertType = "danger";
    }
}

if (isset($_GET["kembali"])) {
    $idPeminjaman = (int) $_GET["kembali"];
    if ($idPeminjaman > 0) {
        $today = date("Y-m-d");
        $update = mysqli_query(
            $conn,
            "UPDATE peminjaman
             SET status_peminjaman = 'dikembalikan',
                 tanggal_pengembalian = '$today'
             WHERE id_peminjaman = $idPeminjaman"
        );

        if ($update) {
            header("Location: /perpustakaan/admin/peminjaman.php?status=kembali");
            exit();
        }
        $message = "Gagal mengubah status pengembalian.";
        $alertType = "danger";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "tambah";
    $idPeminjaman = (int) ($_POST["id_peminjaman"] ?? 0);
    $idUser = (int) ($_POST["id_user"] ?? 0);
    $idBuku = (int) ($_POST["id_buku"] ?? 0);
    $tanggalPinjam = mysqli_real_escape_string($conn, trim($_POST["tanggal_peminjaman"] ?? ""));
    $tanggalKembali = mysqli_real_escape_string($conn, trim($_POST["tanggal_pengembalian"] ?? ""));
    $statusPeminjaman = mysqli_real_escape_string($conn, trim($_POST["status_peminjaman"] ?? "dipinjam"));

    if ($idUser <= 0 || $idBuku <= 0 || $tanggalPinjam === "" || $tanggalKembali === "") {
        $message = "Data peminjaman belum lengkap.";
        $alertType = "danger";
    } else {
        if ($action === "ubah" && $idPeminjaman > 0) {
            $update = mysqli_query(
                $conn,
                "UPDATE peminjaman
                 SET id_user = $idUser,
                     id_buku = $idBuku,
                     tanggal_peminjaman = '$tanggalPinjam',
                     tanggal_pengembalian = '$tanggalKembali',
                     status_peminjaman = '$statusPeminjaman'
                 WHERE id_peminjaman = $idPeminjaman"
            );

            if ($update) {
                header("Location: /perpustakaan/admin/peminjaman.php?status=ubah");
                exit();
            }
            $message = "Gagal mengubah peminjaman: " . mysqli_error($conn);
            $alertType = "danger";
        } else {
            $insert = mysqli_query(
                $conn,
                "INSERT INTO peminjaman (id_user, id_buku, tanggal_peminjaman, tanggal_pengembalian, status_peminjaman)
                 VALUES ($idUser, $idBuku, '$tanggalPinjam', '$tanggalKembali', '$statusPeminjaman')"
            );

            if ($insert) {
                header("Location: /perpustakaan/admin/peminjaman.php?status=tambah");
                exit();
            }
            $message = "Gagal menambah peminjaman: " . mysqli_error($conn);
            $alertType = "danger";
        }
    }
}

if (isset($_GET["edit"])) {
    $idEdit = (int) $_GET["edit"];
    $editQuery = mysqli_query($conn, "SELECT * FROM peminjaman WHERE id_peminjaman = $idEdit LIMIT 1");
    if ($editQuery && mysqli_num_rows($editQuery) === 1) {
        $editData = mysqli_fetch_assoc($editQuery);
    }
}

if (isset($_GET["status"])) {
    if ($_GET["status"] === "tambah") {
        $message = "Peminjaman berhasil ditambahkan.";
        $alertType = "success";
    } elseif ($_GET["status"] === "ubah") {
        $message = "Peminjaman berhasil diubah.";
        $alertType = "success";
    } elseif ($_GET["status"] === "hapus") {
        $message = "Peminjaman berhasil dihapus.";
        $alertType = "success";
    } elseif ($_GET["status"] === "kembali") {
        $message = "Status peminjaman berhasil diubah menjadi dikembalikan.";
        $alertType = "success";
    }
}

$anggota = mysqli_query($conn, "SELECT id_user, nama, username FROM user WHERE role = 'anggota' ORDER BY nama ASC");
$buku = mysqli_query($conn, "SELECT id_buku, judul FROM buku ORDER BY judul ASC");
$peminjaman = mysqli_query(
    $conn,
    "SELECT peminjaman.*, user.nama, user.username, buku.judul
     FROM peminjaman
     JOIN user ON peminjaman.id_user = user.id_user
     JOIN buku ON peminjaman.id_buku = buku.id_buku
     ORDER BY peminjaman.id_peminjaman DESC"
);

render_head("Data Peminjaman");
render_nav("admin", "peminjaman.php", $_SESSION["username"]);
?>
<div class="page-title">
    <span class="badge-soft mb-3">CRUD Peminjaman</span>
    <h1 class="h3 mb-1">Kelola Transaksi Peminjaman</h1>
    <p class="text-muted mb-0">Admin bisa menambah transaksi, mengubah jatuh tempo, mengubah status, atau menghapus data.</p>
</div>
<?php render_alert($message, $alertType); ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0"><?php echo $editData ? "Edit Peminjaman" : "Tambah Peminjaman"; ?></h2>
                    <?php if ($editData): ?>
                        <a href="/perpustakaan/admin/peminjaman.php" class="btn btn-sm btn-outline-secondary">Batal</a>
                    <?php endif; ?>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="<?php echo $editData ? "ubah" : "tambah"; ?>">
                    <input type="hidden" name="id_peminjaman" value="<?php echo (int) ($editData["id_peminjaman"] ?? 0); ?>">
                    <div class="mb-3">
                        <label class="form-label">Anggota</label>
                        <select name="id_user" class="form-select" required>
                            <option value="">Pilih anggota</option>
                            <?php if ($anggota): ?>
                                <?php while ($rowAnggota = mysqli_fetch_assoc($anggota)): ?>
                                    <?php $selectedUser = (int) ($editData["id_user"] ?? ($_POST["id_user"] ?? 0)); ?>
                                    <option value="<?php echo (int) $rowAnggota["id_user"]; ?>" <?php echo $selectedUser === (int) $rowAnggota["id_user"] ? "selected" : ""; ?>>
                                        <?php echo e($rowAnggota["nama"] . " (" . $rowAnggota["username"] . ")"); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Buku</label>
                        <select name="id_buku" class="form-select" required>
                            <option value="">Pilih buku</option>
                            <?php if ($buku): ?>
                                <?php while ($rowBuku = mysqli_fetch_assoc($buku)): ?>
                                    <?php $selectedBuku = (int) ($editData["id_buku"] ?? ($_POST["id_buku"] ?? 0)); ?>
                                    <option value="<?php echo (int) $rowBuku["id_buku"]; ?>" <?php echo $selectedBuku === (int) $rowBuku["id_buku"] ? "selected" : ""; ?>>
                                        <?php echo e($rowBuku["judul"]); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Peminjaman</label>
                        <input type="date" name="tanggal_peminjaman" class="form-control" value="<?php echo e($editData["tanggal_peminjaman"] ?? ($_POST["tanggal_peminjaman"] ?? date("Y-m-d"))); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Pengembalian</label>
                        <input type="date" name="tanggal_pengembalian" class="form-control" value="<?php echo e($editData["tanggal_pengembalian"] ?? ($_POST["tanggal_pengembalian"] ?? date("Y-m-d", strtotime("+7 days")))); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <?php $selectedStatus = $editData["status_peminjaman"] ?? ($_POST["status_peminjaman"] ?? "dipinjam"); ?>
                        <select name="status_peminjaman" class="form-select" required>
                            <option value="dipinjam" <?php echo $selectedStatus === "dipinjam" ? "selected" : ""; ?>>Dipinjam</option>
                            <option value="dikembalikan" <?php echo $selectedStatus === "dikembalikan" ? "selected" : ""; ?>>Dikembalikan</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success w-100"><?php echo $editData ? "Update Peminjaman" : "Simpan Peminjaman"; ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="panel card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Anggota</th>
                                <th>Buku</th>
                                <th>Pinjam</th>
                                <th>Kembali</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($peminjaman && mysqli_num_rows($peminjaman) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($peminjaman)): ?>
                                    <tr>
                                        <td><?php echo (int) $row["id_peminjaman"]; ?></td>
                                        <td><?php echo e($row["nama"] . " (" . $row["username"] . ")"); ?></td>
                                        <td><?php echo e($row["judul"]); ?></td>
                                        <td><?php echo e($row["tanggal_peminjaman"]); ?></td>
                                        <td><?php echo e($row["tanggal_pengembalian"]); ?></td>
                                        <td>
                                            <span class="badge text-bg-<?php echo $row["status_peminjaman"] === "dipinjam" ? "warning" : "success"; ?>">
                                                <?php echo e($row["status_peminjaman"]); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="/perpustakaan/admin/peminjaman.php?edit=<?php echo (int) $row["id_peminjaman"]; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <?php if ($row["status_peminjaman"] === "dipinjam"): ?>
                                                    <a href="/perpustakaan/admin/peminjaman.php?kembali=<?php echo (int) $row["id_peminjaman"]; ?>" class="btn btn-sm btn-outline-success">Set Kembali</a>
                                                <?php endif; ?>
                                                <a href="/perpustakaan/admin/peminjaman.php?hapus=<?php echo (int) $row["id_peminjaman"]; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus data peminjaman ini?')">Hapus</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Belum ada data peminjaman.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
render_footer();
?>
