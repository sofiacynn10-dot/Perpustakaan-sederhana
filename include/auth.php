<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in()
{
    return isset($_SESSION["status"]) && $_SESSION["status"] === "login";
}

function require_login()
{
    if (!is_logged_in()) {
        header("Location: /perpustakaan/login.php");
        exit();
    }
}

function require_role($role)
{
    require_login();
    if (!isset($_SESSION["role"]) || $_SESSION["role"] !== $role) {
        header("Location: /perpustakaan/login.php");
        exit();
    }
}

function redirect_by_role()
{
    if (!is_logged_in()) {
        return;
    }

    if ($_SESSION["role"] === "admin") {
        header("Location: /perpustakaan/admin/dashboard.php");
        exit();
    }

    header("Location: /perpustakaan/public/dashboard.php");
    exit();
}

function current_user(mysqli $conn)
{
    if (!isset($_SESSION["id_user"])) {
        return null;
    }

    $idUser = (int) $_SESSION["id_user"];
    $result = mysqli_query($conn, "SELECT * FROM user WHERE id_user = $idUser");

    if (!$result || mysqli_num_rows($result) === 0) {
        return null;
    }

    return mysqli_fetch_assoc($result);
}
?>
