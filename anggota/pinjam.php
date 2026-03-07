<?php
/* FILE: anggota/pinjam.php — Redirect ke public/catalog.php
 * Fitur pinjam sudah digabungkan ke halaman Katalog di public/catalog.php
 * File ini dipertahankan agar link lama tetap bekerja.
 */
require_once __DIR__ . '/../include/auth.php';
require_login();
header('Location: /perpustakaan/public/catalog.php');
exit;
?>
