<?php
/* FILE: inc/header.php — Partial: HTML <head>, navbar (Katalog, Pinjaman Saya, Profil, Logout)
 *
 * Include Bootstrap 5 CDN + Bootstrap Icons + custom style.css.
 * Memerlukan: config.php sudah di-require sebelumnya ATAU akan di-require otomatis.
 * Variabel $pageTitle harus di-set sebelum include file ini.
 *
 * Auth guard: redirect ke login.php jika $_SESSION['id_user'] tidak ada.
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/csrf.php';

// --- Auth guard ---
if (!isset($_SESSION['id_user'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$_currentPage = basename($_SERVER['SCRIPT_NAME']);
$_navUsername  = e($_SESSION['username'] ?? 'User');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Perpustakaan Digital') ?> &mdash; Perpustakaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/public/catalog.php">
            <i class="bi bi-book-half"></i> Perpustakaan Digital
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPublic"
                aria-controls="navPublic" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navPublic">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $_currentPage === 'dashboard.php' ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>/public/dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $_currentPage === 'catalog.php' ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>/public/catalog.php">
                        <i class="bi bi-grid-3x3-gap-fill"></i> Katalog
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $_currentPage === 'my_loans.php' ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>/public/my_loans.php">
                        <i class="bi bi-journal-bookmark-fill"></i> Pinjaman Saya
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $_currentPage === 'profile.php' ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>/public/profile.php">
                        <i class="bi bi-person-circle"></i> Profil
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-fill"></i> <?= $_navUsername ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?= BASE_URL ?>/public/profile.php">
                                <i class="bi bi-gear"></i> Profil Saya
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
<?php render_flash('message'); ?>
