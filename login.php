<?php
require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/include/auth.php";
require_once __DIR__ . "/include/layout.php";

if (is_logged_in()) {
    redirect_by_role();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = mysqli_real_escape_string($conn, trim($_POST["username"] ?? ""));
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $message = "Username dan password wajib diisi.";
    } else {
        $query = mysqli_query($conn, "SELECT * FROM user WHERE username = '$username' LIMIT 1");

        if ($query && mysqli_num_rows($query) === 1) {
            $user = mysqli_fetch_assoc($query);
            $storedPassword = $user["password"];
            $valid = false;

            if ($password === $storedPassword) {
                $valid = true;
            }

            if ($valid) {
                $_SESSION["id_user"] = $user["id_user"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];
                $_SESSION["status"] = "login";
                redirect_by_role();
            }
        }

        $message = "Username atau password salah.";
    }
}

if (isset($_GET["status"]) && $_GET["status"] === "logout") {
    $message = "Kamu sudah logout.";
}

render_head("Login");
?>
<div class="auth-shell">
    <div class="card auth-card">
        <div class="card-body p-4 p-md-5">
            <div class="mb-4">
                <span class="badge-soft mb-3">Sistem Perpustakaan</span>
                <h1 class="h3 mb-2">Login</h1>
                <p class="text-muted mb-0">Masuk sesuai role untuk mengakses dashboard admin atau anggota.</p>
            </div>
            <?php render_alert($message, $message === "Kamu sudah logout." ? "success" : "danger"); ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="<?php echo e($_POST["username"] ?? ""); ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success w-100">Login</button>
            </form>
            <p class="text-muted mt-4 mb-0 text-center">
                Belum punya akun anggota?
                <a href="/perpustakaan/register.php" class="text-decoration-none">Daftar di sini</a>
            </p>
        </div>
    </div>
</div>
<?php
render_footer(false);
?>
