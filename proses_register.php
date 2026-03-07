<?php
require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/include/auth.php";

if (is_logged_in()) {
    redirect_by_role();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /perpustakaan/register.php");
    exit();
}

$nama = mysqli_real_escape_string($conn, trim($_POST["nama"] ?? ""));
$username = mysqli_real_escape_string($conn, trim($_POST["username"] ?? ""));
$password = $_POST["password"] ?? "";
$email = mysqli_real_escape_string($conn, trim($_POST["email"] ?? ""));
$alamat = mysqli_real_escape_string($conn, trim($_POST["alamat"] ?? ""));
$noHandphone = mysqli_real_escape_string($conn, trim($_POST["no_handphone"] ?? ""));

if ($nama === "" || $username === "" || $password === "" || $email === "" || $alamat === "" || $noHandphone === "") {
    header("Location: /perpustakaan/register.php?status=gagal&pesan=" . urlencode("Semua field wajib diisi."));
    exit();
}

$cek = mysqli_query($conn, "SELECT id_user FROM user WHERE username = '$username' OR email = '$email' LIMIT 1");

if ($cek && mysqli_num_rows($cek) > 0) {
    header("Location: /perpustakaan/register.php?status=gagal&pesan=" . urlencode("Username atau email sudah dipakai."));
    exit();
}

$passwordPlain = mysqli_real_escape_string($conn, $password);
$insert = mysqli_query(
    $conn,
    "INSERT INTO user (nama, username, password, email, alamat, no_handphone, role)
     VALUES ('$nama', '$username', '$passwordPlain', '$email', '$alamat', '$noHandphone', 'anggota')"
);

if ($insert) {
    header("Location: /perpustakaan/register.php?status=sukses");
    exit();
}

$errorMessage = "Registrasi gagal: " . mysqli_error($conn);
header("Location: /perpustakaan/register.php?status=gagal&pesan=" . urlencode($errorMessage));
exit();
?>
