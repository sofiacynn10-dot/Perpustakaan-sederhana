<?php
/* FILE: inc/flash.php — Helper set/get flash messages via session
 *
 * set_flash('message', 'Berhasil!', 'success');  → simpan ke session
 * render_flash('message');                         → tampilkan & hapus dari session
 */

/**
 * Simpan flash message ke session.
 *
 * @param string $key   Kunci flash (misal 'message')
 * @param string $msg   Isi pesan
 * @param string $type  Tipe Bootstrap alert: success|danger|warning|info
 */
function set_flash(string $key, string $msg, string $type = 'info'): void
{
    $_SESSION['_flash'][$key] = [
        'message' => $msg,
        'type'    => $type,
    ];
}

/**
 * Ambil flash message (sekali baca lalu hapus).
 */
function get_flash(string $key): ?array
{
    if (isset($_SESSION['_flash'][$key])) {
        $flash = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $flash;
    }
    return null;
}

/**
 * Render flash message sebagai Bootstrap alert (auto-dismissible).
 */
function render_flash(string $key = 'message'): void
{
    $flash = get_flash($key);
    if ($flash) {
        $msg  = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
        $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
        echo $msg;
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>';
        echo '</div>';
    }
}
