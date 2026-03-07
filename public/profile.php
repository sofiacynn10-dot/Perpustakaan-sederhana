<?php
/* FILE: public/profile.php — View & edit biodata (nama, telepon, email, alamat) + ganti password
 *
 * Password baru disimpan dengan password_hash() (bcrypt).
 * Verifikasi password lama mendukung both plain-text (legacy) dan bcrypt hash.
 *
 * Asumsi kolom user: id_user, nama, username, password, email, alamat, no_handphone, role
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/flash.php';

// Auth check
if (!isset($_SESSION['id_user'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$idUser = (int)$_SESSION['id_user'];

// ===== Handle POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['form_action'] ?? '';

    if ($action === 'update_profile') {
        $nama  = trim($_POST['nama'] ?? '');
        $noHp  = trim($_POST['no_handphone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');

        if ($nama === '' || $noHp === '') {
            set_flash('message', 'Nama dan No Handphone wajib diisi.', 'danger');
        } else {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE user SET nama = :nama, no_handphone = :hp, email = :email, alamat = :alamat
                     WHERE id_user = :id"
                );
                $stmt->execute([
                    'nama'  => $nama,
                    'hp'    => $noHp,
                    'email' => $email,
                    'alamat' => $alamat,
                    'id'    => $idUser,
                ]);

                // Update session username jika perlu (nama tidak ubah session,
                // tapi bisa ditambahkan jika nama ditampilkan di navbar)
                set_flash('message', 'Profil berhasil diperbarui.', 'success');
            } catch (PDOException $ex) {
                error_log('[Perpustakaan] Profile Update Error: ' . $ex->getMessage());
                set_flash('message', 'Gagal memperbarui profil. Silakan coba lagi.', 'danger');
            }
        }
    } elseif ($action === 'change_password') {
        $oldPass  = $_POST['old_password'] ?? '';
        $newPass  = $_POST['new_password'] ?? '';
        $confPass = $_POST['confirm_password'] ?? '';

        if ($oldPass === '' || $newPass === '' || $confPass === '') {
            set_flash('message', 'Semua field password wajib diisi.', 'danger');
        } elseif (strlen($newPass) < 6) {
            set_flash('message', 'Password baru minimal 6 karakter.', 'danger');
        } elseif ($newPass !== $confPass) {
            set_flash('message', 'Konfirmasi password tidak cocok.', 'danger');
        } else {
            try {
                $stmtPw = $pdo->prepare("SELECT password FROM user WHERE id_user = :id");
                $stmtPw->execute(['id' => $idUser]);
                $stored = $stmtPw->fetchColumn();

                // Support both bcrypt hash & plain-text legacy passwords
                $valid = false;
                if ($stored && (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2b$'))) {
                    $valid = password_verify($oldPass, $stored);
                } else {
                    // Legacy plain text
                    $valid = ($oldPass === $stored);
                }

                if (!$valid) {
                    set_flash('message', 'Password lama salah.', 'danger');
                } else {
                    $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                    $stmtUpd = $pdo->prepare("UPDATE user SET password = :pw WHERE id_user = :id");
                    $stmtUpd->execute(['pw' => $hashed, 'id' => $idUser]);
                    set_flash('message', 'Password berhasil diganti. Gunakan password baru saat login berikutnya.', 'success');
                }
            } catch (PDOException $ex) {
                error_log('[Perpustakaan] Password Change Error: ' . $ex->getMessage());
                set_flash('message', 'Gagal mengganti password. Silakan coba lagi.', 'danger');
            }
        }
    }

    // PRG pattern — redirect setelah POST
    header('Location: ' . BASE_URL . '/public/profile.php');
    exit;
}

// ===== GET — Load profil =====
try {
    $stmtUser = $pdo->prepare("SELECT * FROM user WHERE id_user = :id");
    $stmtUser->execute(['id' => $idUser]);
    $user = $stmtUser->fetch();
} catch (PDOException $ex) {
    error_log('[Perpustakaan] Profile Load Error: ' . $ex->getMessage());
    $user = null;
}

if (!$user) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Statistik singkat
$stats = ['aktif' => 0, 'selesai' => 0, 'total_denda' => 0];
try {
    // Cek apakah kolom denda ada
    $colDenda = $pdo->query("SHOW COLUMNS FROM peminjaman LIKE 'denda'");
    $hasDenda = $colDenda && $colDenda->rowCount() > 0;

    $dendaSql = $hasDenda
        ? "COALESCE(SUM(CASE WHEN denda > 0 THEN denda ELSE 0 END), 0) AS total_denda"
        : "0 AS total_denda";

    $stmtStats = $pdo->prepare(
        "SELECT
            SUM(CASE WHEN status_peminjaman = 'dipinjam' THEN 1 ELSE 0 END) AS aktif,
            SUM(CASE WHEN status_peminjaman = 'dikembalikan' THEN 1 ELSE 0 END) AS selesai,
            {$dendaSql}
         FROM peminjaman WHERE id_user = :uid"
    );
    $stmtStats->execute(['uid' => $idUser]);
    $stats = $stmtStats->fetch() ?: $stats;
} catch (PDOException $ex) {
    // stats tetap default
}

$pageTitle = 'Profil Saya';
require_once __DIR__ . '/../inc/header.php';
?>

<div class="page-header">
    <h1 class="h3"><i class="bi bi-person-circle"></i> Profil Saya</h1>
    <p class="text-muted mb-0">Kelola informasi pribadi dan keamanan akun Anda.</p>
</div>

<div class="row g-4">
    <!-- ===== Sidebar Profil ===== -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-3 text-center">
            <div class="card-body py-4">
                <div class="profile-avatar mx-auto mb-3">
                    <?= strtoupper(mb_substr($user['nama'] ?? 'U', 0, 1)) ?>
                </div>
                <h5 class="mb-1"><?= e($user['nama']) ?></h5>
                <p class="text-muted small mb-2">@<?= e($user['username']) ?></p>
                <span class="badge bg-success"><?= e(ucfirst($user['role'])) ?></span>
            </div>
        </div>

        <!-- Statistik -->
        <div class="card shadow-sm border-0 rounded-3 mt-3">
            <div class="card-body">
                <h6 class="text-muted mb-3">Statistik</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span>Pinjaman Aktif</span>
                    <strong><?= (int)($stats['aktif'] ?? 0) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Dikembalikan</span>
                    <strong><?= (int)($stats['selesai'] ?? 0) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Total Denda</span>
                    <strong class="<?= (int)($stats['total_denda'] ?? 0) > 0 ? 'text-danger' : 'text-success' ?>">
                        Rp <?= number_format((int)($stats['total_denda'] ?? 0), 0, ',', '.') ?>
                    </strong>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Form Edit ===== -->
    <div class="col-lg-8">
        <!-- Edit Biodata -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Biodata</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="update_profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control"
                                   value="<?= e($user['nama']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No Handphone <span class="text-danger">*</span></label>
                            <input type="text" name="no_handphone" class="form-control"
                                   value="<?= e($user['no_handphone'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= e($user['email'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alamat</label>
                            <textarea name="alamat" class="form-control" rows="2"><?= e($user['alamat'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success mt-3">
                        <i class="bi bi-check-lg"></i> Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>

        <!-- Ganti Password -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Ganti Password</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="change_password">
                    <div class="mb-3">
                        <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru <span class="text-danger">*</span> <small class="text-muted">(min. 6 karakter)</small></label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key"></i> Ganti Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
