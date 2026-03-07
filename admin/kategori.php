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
        $delete = mysqli_query($conn, "DELETE FROM kategori WHERE id_kategori = $idHapus");
        if ($delete) {
            header("Location: /perpustakaan/admin/kategori.php?status=hapus");
            exit();
        }
        $message = "Kategori tidak bisa dihapus jika masih dipakai buku.";
        $alertType = "danger";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "tambah";
    $idKategori = (int) ($_POST["id_kategori"] ?? 0);
    $namaKategori = mysqli_real_escape_string($conn, trim($_POST["nama_kategori"] ?? ""));

    if ($namaKategori === "") {
        $message = "Nama kategori wajib diisi.";
        $alertType = "danger";
    } else {
        $cek = mysqli_query(
            $conn,
            "SELECT id_kategori
             FROM kategori
             WHERE nama_kategori = '$namaKategori'
             AND id_kategori != $idKategori
             LIMIT 1"
        );

        if ($cek && mysqli_num_rows($cek) > 0) {
            $message = "Nama kategori sudah ada.";
            $alertType = "danger";
        } else {
            if ($action === "ubah" && $idKategori > 0) {
                $update = mysqli_query(
                    $conn,
                    "UPDATE kategori
                     SET nama_kategori = '$namaKategori'
                     WHERE id_kategori = $idKategori"
                );

                if ($update) {
                    header("Location: /perpustakaan/admin/kategori.php?status=ubah");
                    exit();
                }
                $message = "Gagal mengubah kategori: " . mysqli_error($conn);
                $alertType = "danger";
            } else {
                $insert = mysqli_query($conn, "INSERT INTO kategori (nama_kategori) VALUES ('$namaKategori')");
                if ($insert) {
                    header("Location: /perpustakaan/admin/kategori.php?status=tambah");
                    exit();
                }
                $message = "Gagal menambah kategori: " . mysqli_error($conn);
                $alertType = "danger";
            }
        }
    }
}

if (isset($_GET["edit"])) {
    $idEdit = (int) $_GET["edit"];
    $editQuery = mysqli_query($conn, "SELECT * FROM kategori WHERE id_kategori = $idEdit LIMIT 1");
    if ($editQuery && mysqli_num_rows($editQuery) === 1) {
        $editData = mysqli_fetch_assoc($editQuery);
    }
}

if (isset($_GET["status"])) {
    if ($_GET["status"] === "tambah") {
        $message = "Kategori berhasil ditambahkan.";
        $alertType = "success";
    } elseif ($_GET["status"] === "ubah") {
        $message = "Kategori berhasil diubah.";
        $alertType = "success";
    } elseif ($_GET["status"] === "hapus") {
        $message = "Kategori berhasil dihapus.";
        $alertType = "success";
    }
}

$kategori = mysqli_query(
    $conn,
    "SELECT kategori.*, COUNT(buku.id_buku) AS total_buku
     FROM kategori
     LEFT JOIN buku ON buku.id_kategori = kategori.id_kategori
     GROUP BY kategori.id_kategori
     ORDER BY kategori.id_kategori ASC"
);

render_head("Kelola Kategori");
render_nav("admin", "kategori.php", $_SESSION["username"]);
?>
<div class="page-title">
    <span class="badge-soft mb-3">CRUD Kategori</span>
    <h1 class="h3 mb-1">Kategori Buku</h1>
    <p class="text-muted mb-0">Gunakan kategori untuk mengelompokkan data buku agar lebih rapi.</p>
</div>
<?php render_alert($message, $alertType); ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0"><?php echo $editData ? "Edit Kategori" : "Tambah Kategori"; ?></h2>
                    <?php if ($editData): ?>
                        <a href="/perpustakaan/admin/kategori.php" class="btn btn-sm btn-outline-secondary">Batal</a>
                    <?php endif; ?>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="<?php echo $editData ? "ubah" : "tambah"; ?>">
                    <input type="hidden" name="id_kategori" value="<?php echo (int) ($editData["id_kategori"] ?? 0); ?>">
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="nama_kategori" class="form-control" value="<?php echo e($editData["nama_kategori"] ?? ($_POST["nama_kategori"] ?? "")); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100"><?php echo $editData ? "Update Kategori" : "Simpan Kategori"; ?></button>
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
                                <th>Nama Kategori</th>
                                <th>Jumlah Buku</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($kategori && mysqli_num_rows($kategori) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($kategori)): ?>
                                    <tr>
                                        <td><?php echo (int) $row["id_kategori"]; ?></td>
                                        <td><?php echo e($row["nama_kategori"]); ?></td>
                                        <td><?php echo (int) $row["total_buku"]; ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="/perpustakaan/admin/kategori.php?edit=<?php echo (int) $row["id_kategori"]; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <a href="/perpustakaan/admin/kategori.php?hapus=<?php echo (int) $row["id_kategori"]; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus kategori ini?')">Hapus</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Belum ada kategori.</td>
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
