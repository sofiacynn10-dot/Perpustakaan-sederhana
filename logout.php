<?php
session_start();
session_unset();
session_destroy();

header("Location: /perpustakaan/login.php?status=logout");
exit();
?>
