<?php
require_once __DIR__ . "/include/auth.php";

if (is_logged_in()) {
    redirect_by_role();
}

header("Location: /perpustakaan/login.php");
exit();
?>
