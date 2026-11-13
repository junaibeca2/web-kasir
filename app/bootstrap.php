<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/domain.php';
date_default_timezone_set(APP_TIMEZONE);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/web-kasir/index.php'));
    if ($base === '/' || $base === '.') {
        $base = '';
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): void
{
    echo '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = (string) ($_POST['_csrf'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Token formulir tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function show_flash(): void
{
    if (empty($_SESSION['_flash'])) {
        return;
    }
    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    echo '<div class="flash flash-' . e($flash['type']) . '">' . e($flash['message']) . '</div>';
}

function current_user(): ?array
{
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id < 1) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, name, username, role, is_active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user || !(int) $user['is_active']) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        set_flash('error', 'Silakan masuk terlebih dahulu.');
        redirect('login.php');
    }
    return $user;
}

function require_permission(string $ability): array
{
    $user = require_login();
    if (!role_can($user['role'], $ability)) {
        http_response_code(403);
        render_header('Akses ditolak');
        echo '<section class="panel"><h1>Akses ditolak</h1><p>Akun karyawan tidak memiliki izin untuk halaman ini.</p><a class="button" href="' . e(app_url('dashboard.php')) . '">Kembali ke beranda</a></section>';
        render_footer();
        exit;
    }
    return $user;
}

function render_header(string $title): void
{
    $user = current_user();
    $isAdmin = $user && $user['role'] === 'admin';
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . ' · ' . e(APP_TITLE) . '</title><link rel="stylesheet" href="' . e(app_url('assets/app.css')) . '"></head><body>';
    echo '<header class="topbar"><a class="brand" href="' . e(app_url('dashboard.php')) . '"><span class="brand-mark">K</span><span>' . e(APP_TITLE) . '<small>POS · Toko</small></span></a>';
    if ($user) {
        echo '<nav class="nav">';
        echo '<a href="' . e(app_url('dashboard.php')) . '">Beranda</a><a href="' . e(app_url('orders.php')) . '">Pesanan</a>';
        if ($isAdmin) {
            echo '<a href="' . e(app_url('menu.php')) . '">Menu</a><a href="' . e(app_url('reports.php')) . '">Laporan</a><a href="' . e(app_url('users.php')) . '">Karyawan</a><a href="' . e(app_url('settings.php')) . '">Pengaturan</a>';
        }
        echo '</nav><div class="account"><span class="account-name">' . e($user['name']) . '<small>' . ($isAdmin ? 'Super admin' : 'Karyawan') . '</small></span><form method="post" action="' . e(app_url('logout.php')) . '">';
        csrf_field();
        echo '<button class="button button-quiet button-small" type="submit">Keluar</button></form></div>';
    }
    echo '</header><main class="page"><div class="page-heading"><div><p class="eyebrow">' . e(date('l, d F Y')) . '</p><h1>' . e($title) . '</h1></div></div>';
    show_flash();
}

function render_footer(): void
{
    echo '</main><script src="' . e(app_url('assets/app.js')) . '" defer></script></body></html>';
}

function amount_cents(mixed $decimal): int
{
    return money_to_cents((string) $decimal);
}
