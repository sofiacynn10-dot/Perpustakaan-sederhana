<?php
/* FILE: anggota/dashboard.php — Redirect ke public/dashboard.php
 * File ini dipertahankan agar link lama tetap bekerja.
 */
require_once __DIR__ . '/../include/auth.php';
require_login();
header('Location: /perpustakaan/public/dashboard.php');
exit;
?>
