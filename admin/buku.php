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
        $delete = mysqli_query($conn, "DELETE FROM buku WHERE id_buku = $idHapus");
        if ($delete) {
            header("Location: /perpustakaan/admin/buku.php?status=hapus");
            exit();
        }
        $message = "Gagal menghapus buku: " . mysqli_error($conn);
        $alertType = "danger";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "tambah";
    $idBuku = (int) ($_POST["id_buku"] ?? 0);
    $idKategori = (int) ($_POST["id_kategori"] ?? 0);
    $judul = mysqli_real_escape_string($conn, trim($_POST["judul"] ?? ""));
    $penulis = mysqli_real_escape_string($conn, trim($_POST["penulis"] ?? ""));
    $tahunTerbit = mysqli_real_escape_string($conn, trim($_POST["tahun_terbit"] ?? ""));
    $deskripsi = mysqli_real_escape_string($conn, trim($_POST["deskripsi"] ?? ""));

    if ($idKategori <= 0 || $judul === "" || $penulis === "" || $tahunTerbit === "") {
        $message = "Data buku belum lengkap.";
        $alertType = "danger";
    } else {
        if ($action === "ubah" && $idBuku > 0) {
            $update = mysqli_query(
                $conn,
                "UPDATE buku
                 SET id_kategori = $idKategori,
                     judul = '$judul',
                     penulis = '$penulis',
                     tahun_terbit = '$tahunTerbit',
                     deskripsi = '$deskripsi'
                 WHERE id_buku = $idBuku"
            );

            if ($update) {
                header("Location: /perpustakaan/admin/buku.php?status=ubah");
                exit();
            }
            $message = "Gagal mengubah buku: " . mysqli_error($conn);
            $alertType = "danger";
        } else {
            $insert = mysqli_query(
                $conn,
                "INSERT INTO buku (id_kategori, judul, penulis, tahun_terbit, deskripsi)
                 VALUES ($idKategori, '$judul', '$penulis', '$tahunTerbit', '$deskripsi')"
            );

            if ($insert) {
                header("Location: /perpustakaan/admin/buku.php?status=tambah");
                exit();
            }
            $message = "Gagal menambah buku: " . mysqli_error($conn);
            $alertType = "danger";
        }
    }
}

if (isset($_GET["edit"])) {
    $idEdit = (int) $_GET["edit"];
    $editQuery = mysqli_query($conn, "SELECT * FROM buku WHERE id_buku = $idEdit LIMIT 1");
    if ($editQuery && mysqli_num_rows($editQuery) === 1) {
        $editData = mysqli_fetch_assoc($editQuery);
    }
}

if (isset($_GET["status"])) {
    if ($_GET["status"] === "tambah") {
        $message = "Buku berhasil ditambahkan.";
        $alertType = "success";
    } elseif ($_GET["status"] === "ubah") {
        $message = "Buku berhasil diubah.";
        $alertType = "success";
    } elseif ($_GET["status"] === "hapus") {
        $message = "Buku berhasil dihapus.";
        $alertType = "success";
    }
}

$kategori = mysqli_query($conn, "SELECT * FROM kategori ORDER BY id_kategori ASC");
$buku = mysqli_query(
    $conn,
    "SELECT buku.*, kategori.nama_kategori
     FROM buku
     JOIN kategori ON buku.id_kategori = kategori.id_kategori
     ORDER BY buku.id_buku DESC"
);

render_head("Kelola Buku");
render_nav("admin", "buku.php", $_SESSION["username"]);
?>
<div class="page-title">
    <span class="badge-soft mb-3">CRUD Buku</span>
    <h1 class="h3 mb-1">Data Buku</h1>
    <p class="text-muted mb-0">Tambahkan, ubah, dan hapus koleksi buku perpustakaan.</p>
</div>
<?php render_alert($message, $alertType); ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0"><?php echo $editData ? "Edit Buku" : "Tambah Buku"; ?></h2>
                    <?php if ($editData): ?>
                        <a href="/perpustakaan/admin/buku.php" class="btn btn-sm btn-outline-secondary">Batal</a>
                    <?php endif; ?>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="<?php echo $editData ? "ubah" : "tambah"; ?>">
                    <input type="hidden" name="id_buku" value="<?php echo (int) ($editData["id_buku"] ?? 0); ?>">
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="id_kategori" class="form-select" required>
                            <option value="">Pilih kategori</option>
                            <?php if ($kategori): ?>
                                <?php while ($rowKategori = mysqli_fetch_assoc($kategori)): ?>
                                    <?php $selectedKategori = (int) ($editData["id_kategori"] ?? ($_POST["id_kategori"] ?? 0)); ?>
                                    <option value="<?php echo (int) $rowKategori["id_kategori"]; ?>" <?php echo $selectedKategori === (int) $rowKategori["id_kategori"] ? "selected" : ""; ?>>
                                        <?php echo e($rowKategori["nama_kategori"]); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Judul</label>
                        <input type="text" name="judul" class="form-control" value="<?php echo e($editData["judul"] ?? ($_POST["judul"] ?? "")); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Penulis</label>
                        <input type="text" name="penulis" class="form-control" value="<?php echo e($editData["penulis"] ?? ($_POST["penulis"] ?? "")); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tahun Terbit</label>
                        <input type="text" name="tahun_terbit" class="form-control" value="<?php echo e($editData["tahun_terbit"] ?? ($_POST["tahun_terbit"] ?? "")); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="4"><?php echo e($editData["deskripsi"] ?? ($_POST["deskripsi"] ?? "")); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100"><?php echo $editData ? "Update Buku" : "Simpan Buku"; ?></button>
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
                                <th>Judul</th>
                                <th>Kategori</th>
                                <th>Penulis</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($buku && mysqli_num_rows($buku) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($buku)): ?>
                                    <tr>
                                        <td><?php echo (int) $row["id_buku"]; ?></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($row["judul"]); ?></div>
                                            <div class="small text-muted"><?php echo e($row["tahun_terbit"]); ?></div>
                                        </td>
                                        <td><?php echo e($row["nama_kategori"]); ?></td>
                                        <td><?php echo e($row["penulis"]); ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="/perpustakaan/admin/buku.php?edit=<?php echo (int) $row["id_buku"]; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <a href="/perpustakaan/admin/buku.php?hapus=<?php echo (int) $row["id_buku"]; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus buku ini?')">Hapus</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada data buku.</td>
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
