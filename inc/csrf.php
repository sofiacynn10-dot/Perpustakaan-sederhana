<?php
/* FILE: inc/csrf.php — Helper generate/verify CSRF token (session-based)
 *
 * Gunakan csrf_field() di dalam <form> POST.
 * Gunakan require_csrf() di awal setiap endpoint POST.
 */

/**
 * Ambil atau buat CSRF token dari session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Hasilkan hidden input field berisi CSRF token.
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
}

/**
 * Verifikasi CSRF token dari POST data.
 */
function verify_csrf(string $token): bool
{
    return isset($_SESSION['_csrf_token'])
        && $token !== ''
        && hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * Wajibkan CSRF token valid — hentikan eksekusi jika gagal.
 */
function require_csrf(): void
{
    $token = $_POST['_csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        http_response_code(403);
        die('CSRF token tidak valid. Silakan kembali ke halaman sebelumnya dan coba lagi.');
    }
}
