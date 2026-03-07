<?php
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function render_head($title)
{
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?> - Perpustakaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      :root {
        --sidebar-bg: #1f2937;
        --sidebar-link: #d1d5db;
        --sidebar-active: #0f766e;
        --content-bg: #f3f4f6;
        --card-radius: 18px;
      }
      body { min-height: 100vh; background: var(--content-bg); }
      .admin-shell { display: flex; min-height: 100vh; }
      .sidebar {
        width: 280px;
        background: linear-gradient(180deg, #111827 0%, #1f2937 100%);
        color: #fff;
        padding: 24px 18px;
        display: flex;
        flex-direction: column;
        gap: 18px;
        box-shadow: 8px 0 30px rgba(17, 24, 39, 0.16);
      }
      .brand {
        display: block;
        padding: 10px 12px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.06);
        color: #fff;
        font-size: 1.2rem;
        font-weight: 700;
        text-decoration: none;
      }
      .sidebar-label {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #9ca3af;
        margin: 8px 12px 0;
      }
      .sidebar .nav-link {
        color: var(--sidebar-link);
        border-radius: 12px;
        padding: 12px 14px;
        font-weight: 600;
      }
      .sidebar .nav-link:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.08);
      }
      .sidebar .nav-link.active {
        color: #fff;
        background: var(--sidebar-active);
      }
      .user-box {
        margin-top: auto;
        padding: 14px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.06);
      }
      .content-area {
        flex: 1;
        padding: 28px;
        overflow-x: auto;
      }
      .page-title {
        margin-bottom: 24px;
      }
      .panel {
        background: #fff;
        border: 0;
        border-radius: var(--card-radius);
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
      }
      .table thead th {
        background: #f8fafc;
        border-bottom-width: 1px;
      }
      .table td, .table th {
        vertical-align: middle;
      }
      .auth-shell {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background:
          radial-gradient(circle at top right, rgba(15, 118, 110, 0.18), transparent 36%),
          radial-gradient(circle at bottom left, rgba(14, 165, 233, 0.16), transparent 34%),
          #eef2ff;
      }
      .auth-card {
        width: 100%;
        max-width: 480px;
        border: 0;
        border-radius: 24px;
        box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12);
      }
      .stat-card {
        border-radius: var(--card-radius);
        border: 0;
        overflow: hidden;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      }
      .badge-soft {
        display: inline-block;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
        background: #ecfeff;
        color: #115e59;
      }
      @media (max-width: 900px) {
        .admin-shell { flex-direction: column; }
        .sidebar { width: 100%; }
      }
    </style>
  </head>
  <body>
<?php
}

function render_nav($role, $currentPage, $username)
{
?>
<div class="admin-shell">
  <aside class="sidebar">
    <a href="<?php echo $role === "admin" ? "/perpustakaan/admin/dashboard.php" : "/perpustakaan/public/dashboard.php"; ?>" class="brand">
      <?php echo $role === "admin" ? "Admin Perpustakaan" : "Portal Anggota"; ?>
    </a>
    <?php if ($role === "admin"): ?>
      <div class="sidebar-label">Menu</div>
      <ul class="nav flex-column gap-1">
        <li><a href="/perpustakaan/admin/dashboard.php" class="nav-link <?php echo $currentPage === "dashboard.php" ? "active" : ""; ?>">Home</a></li>
        <li><a href="/perpustakaan/admin/anggota.php" class="nav-link <?php echo $currentPage === "anggota.php" ? "active" : ""; ?>">Anggota</a></li>
        <li><a href="/perpustakaan/admin/buku.php" class="nav-link <?php echo $currentPage === "buku.php" ? "active" : ""; ?>">Buku</a></li>
        <li><a href="/perpustakaan/admin/kategori.php" class="nav-link <?php echo $currentPage === "kategori.php" ? "active" : ""; ?>">Kategori</a></li>
        <li><a href="/perpustakaan/admin/peminjaman.php" class="nav-link <?php echo $currentPage === "peminjaman.php" ? "active" : ""; ?>">Peminjaman</a></li>
      </ul>
    <?php endif; ?>
    <div class="user-box">
      <div class="small text-secondary-emphasis">Login sebagai</div>
      <div class="fw-bold"><?php echo e($username); ?></div>
      <a href="/perpustakaan/logout.php" class="btn btn-light btn-sm mt-3">Logout</a>
    </div>
  </aside>
  <main class="content-area">
<?php
}

function render_alert($message, $type = "info")
{
    if ($message === "") {
        return;
    }
?>
<div class="alert alert-<?php echo e($type); ?>"><?php echo e($message); ?></div>
<?php
}

function render_footer($insideShell = true)
{
?>
<?php if ($insideShell): ?>
  </main>
</div>
<?php endif; ?>
  </body>
</html>
<?php
}
?>
