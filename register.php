<?php
require_once __DIR__ . "/include/auth.php";
require_once __DIR__ . "/include/layout.php";

if (is_logged_in()) {
    redirect_by_role();
}

$message = "";
$messageClass = "";

if (isset($_GET["status"])) {
    if ($_GET["status"] === "sukses") {
        $message = "Registrasi berhasil. Silakan login.";
        $messageClass = "alert-ok";
    } elseif ($_GET["status"] === "gagal" && isset($_GET["pesan"])) {
        $message = trim($_GET["pesan"]);
        $messageClass = "alert-warn";
    }
}

render_head("Register");
?>
<div class="auth-shell">
    <div class="card auth-card">
        <div class="card-body p-4 p-md-5">
            <div class="mb-4">
                <span class="badge-soft mb-3">Registrasi Anggota</span>
                <h1 class="h3 mb-2">Buat Akun</h1>
                <p class="text-muted mb-0">Akun yang dibuat dari halaman ini otomatis memiliki role <strong>anggota</strong>.</p>
            </div>
            <?php
            if ($message !== "") {
                render_alert($message, $messageClass === "alert-ok" ? "success" : "danger");
            }
            ?>
            <form method="post" action="/perpustakaan/proses_register.php">
                <div class="mb-3">
                    <label class="form-label">Nama</label>
                    <input type="text" name="nama" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" class="form-control" rows="3" required></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label">No Handphone</label>
                    <input type="text" name="no_handphone" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success w-100">Daftar</button>
            </form>
            <p class="text-muted mt-4 mb-0 text-center">
                Sudah punya akun?
                <a href="/perpustakaan/login.php" class="text-decoration-none">Kembali ke login</a>
            </p>
        </div>
    </div>
</div>
<?php
render_footer(false);
?>
