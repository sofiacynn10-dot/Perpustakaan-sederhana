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
        $delete = mysqli_query($conn, "DELETE FROM user WHERE id_user = $idHapus AND role = 'anggota'");
        if ($delete) {
            header("Location: /perpustakaan/admin/anggota.php?status=hapus");
            exit();
        }
        $message = "Gagal menghapus anggota: " . mysqli_error($conn);
        $alertType = "danger";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "tambah";
    $idUser = (int) ($_POST["id_user"] ?? 0);
    $nama = mysqli_real_escape_string($conn, trim($_POST["nama"] ?? ""));
    $username = mysqli_real_escape_string($conn, trim($_POST["username"] ?? ""));
    $email = mysqli_real_escape_string($conn, trim($_POST["email"] ?? ""));
    $alamat = mysqli_real_escape_string($conn, trim($_POST["alamat"] ?? ""));
    $noHandphone = mysqli_real_escape_string($conn, trim($_POST["no_handphone"] ?? ""));
    $password = trim($_POST["password"] ?? "");

    if ($nama === "" || $username === "" || $email === "" || $alamat === "" || $noHandphone === "") {
        $message = "Semua field selain password wajib diisi.";
        $alertType = "danger";
    } elseif ($action === "tambah" && $password === "") {
        $message = "Password wajib diisi untuk anggota baru.";
        $alertType = "danger";
    } else {
        $cek = mysqli_query(
            $conn,
            "SELECT id_user
             FROM user
             WHERE (username = '$username' OR email = '$email')
             AND id_user != $idUser
             LIMIT 1"
        );

        if ($cek && mysqli_num_rows($cek) > 0) {
            $message = "Username atau email sudah digunakan.";
            $alertType = "danger";
        } else {
            if ($action === "ubah" && $idUser > 0) {
                $sql = "UPDATE user
                        SET nama = '$nama',
                            username = '$username',
                            email = '$email',
                            alamat = '$alamat',
                            no_handphone = '$noHandphone'
                        WHERE id_user = $idUser AND role = 'anggota'";

                if ($password !== "") {
                    $passwordPlain = mysqli_real_escape_string($conn, $password);
                    $sql = "UPDATE user
                            SET nama = '$nama',
                                username = '$username',
                                email = '$email',
                                alamat = '$alamat',
                                no_handphone = '$noHandphone',
                                password = '$passwordPlain'
                            WHERE id_user = $idUser AND role = 'anggota'";
                }

                $result = mysqli_query($conn, $sql);
                if ($result) {
                    header("Location: /perpustakaan/admin/anggota.php?status=ubah");
                    exit();
                }
                $message = "Gagal mengubah anggota: " . mysqli_error($conn);
                $alertType = "danger";
            } else {
                $passwordPlain = mysqli_real_escape_string($conn, $password);
                $insert = mysqli_query(
                    $conn,
                    "INSERT INTO user (nama, username, password, email, alamat, no_handphone, role)
                     VALUES ('$nama', '$username', '$passwordPlain', '$email', '$alamat', '$noHandphone', 'anggota')"
                );

                if ($insert) {
                    header("Location: /perpustakaan/admin/anggota.php?status=tambah");
                    exit();
                }
                $message = "Gagal menambah anggota: " . mysqli_error($conn);
                $alertType = "danger";
            }
        }
    }
}

if (isset($_GET["edit"])) {
    $idEdit = (int) $_GET["edit"];
    $editQuery = mysqli_query($conn, "SELECT * FROM user WHERE id_user = $idEdit AND role = 'anggota' LIMIT 1");
    if ($editQuery && mysqli_num_rows($editQuery) === 1) {
        $editData = mysqli_fetch_assoc($editQuery);
    }
}

if (isset($_GET["status"])) {
    if ($_GET["status"] === "tambah") {
        $message = "Anggota berhasil ditambahkan.";
        $alertType = "success";
    } elseif ($_GET["status"] === "ubah") {
        $message = "Data anggota berhasil diubah.";
        $alertType = "success";
    } elseif ($_GET["status"] === "hapus") {
        $message = "Anggota berhasil dihapus.";
        $alertType = "success";
    }
}

$anggota = mysqli_query(
    $conn,
    "SELECT id_user, nama, username, email, alamat, no_handphone
     FROM user
     WHERE role = 'anggota'
     ORDER BY id_user DESC"
);

render_head("Data Anggota");
render_nav("admin", "anggota.php", $_SESSION["username"]);
?>
<div class="page-title">
    <span class="badge-soft mb-3">CRUD Anggota</span>
    <h1 class="h3 mb-1">Kelola Data Anggota</h1>
    <p class="text-muted mb-0">Tambah anggota baru, edit profil, atau hapus data yang tidak diperlukan.</p>
</div>
<?php render_alert($message, $alertType); ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0"><?php echo $editData ? "Edit Anggota" : "Tambah Anggota"; ?></h2>
                    <?php if ($editData): ?>
                        <a href="/perpustakaan/admin/anggota.php" class="btn btn-sm btn-outline-secondary">Batal</a>
                    <?php endif; ?>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="<?php echo $editData ? "ubah" : "tambah"; ?>">
                    <input type="hidden" name="id_user" value="<?php echo (int) ($editData["id_user"] ?? 0); ?>">
                    <div class="mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" name="nama" class="form-control" value="<?php echo e($editData["nama"] ?? ($_POST["nama"] ?? "")); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo e($editData["username"] ?? ($_POST["username"] ?? "")); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo e($editData["email"] ?? ($_POST["email"] ?? "")); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="3" required><?php echo e($editData["alamat"] ?? ($_POST["alamat"] ?? "")); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No Handphone</label>
                        <input type="text" name="no_handphone" class="form-control" value="<?php echo e($editData["no_handphone"] ?? ($_POST["no_handphone"] ?? "")); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <?php echo $editData ? "(kosongkan jika tidak diubah)" : ""; ?></label>
                        <input type="password" name="password" class="form-control" <?php echo $editData ? "" : "required"; ?>>
                    </div>
                    <button type="submit" class="btn btn-success w-100"><?php echo $editData ? "Update Anggota" : "Simpan Anggota"; ?></button>
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
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>No HP</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($anggota && mysqli_num_rows($anggota) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($anggota)): ?>
                                    <tr>
                                        <td><?php echo (int) $row["id_user"]; ?></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($row["nama"]); ?></div>
                                            <div class="small text-muted"><?php echo e($row["alamat"]); ?></div>
                                        </td>
                                        <td><?php echo e($row["username"]); ?></td>
                                        <td><?php echo e($row["email"]); ?></td>
                                        <td><?php echo e($row["no_handphone"]); ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="/perpustakaan/admin/anggota.php?edit=<?php echo (int) $row["id_user"]; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <a href="/perpustakaan/admin/anggota.php?hapus=<?php echo (int) $row["id_user"]; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus anggota ini?')">Hapus</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada data anggota.</td>
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
