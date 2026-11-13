<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
date_default_timezone_set(APP_TIMEZONE);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['_setup_csrf'])) {
    $_SESSION['_setup_csrf'] = bin2hex(random_bytes(32));
}

function setup_db(): PDO
{
    $server = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $server->exec('USE `' . DB_NAME . '`');
    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Berkas database/schema.sql tidak ditemukan.');
    }
    $statements = preg_split('/;\s*(?:\r?\n|$)/', trim($schema));
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $server->exec($statement);
        }
    }
    return $server;
}

$error = '';
$success = false;
$installed = false;
try {
    $pdo = setup_db();
    $installed = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
} catch (Throwable $exception) {
    $error = 'Tidak dapat menyiapkan database. Pastikan MySQL sudah dijalankan dari XAMPP, lalu periksa pengaturan di config.php. Rincian: ' . $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && !$installed) {
    if (!hash_equals($_SESSION['_setup_csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        $error = 'Sesi formulir kedaluwarsa. Muat ulang halaman.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) {
            $error = 'Nama wajib diisi dan maksimal 100 karakter.';
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/', $username)) {
            $error = 'Username harus 3–40 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.';
        } elseif (strlen($password) < 10) {
            $error = 'Kata sandi super admin minimal 10 karakter.';
        } elseif (!hash_equals($password, $confirm)) {
            $error = 'Konfirmasi kata sandi tidak sama.';
        } else {
            try {
                $check = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
                if ((int) $check > 0) {
                    $installed = true;
                } else {
                    $stmt = $pdo->prepare('INSERT INTO users (name, username, password_hash, role) VALUES (?, ?, ?, \'admin\')');
                    $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT)]);
                    $success = true;
                    $installed = true;
                }
            } catch (Throwable $exception) {
                $error = 'Akun belum dapat dibuat. Pastikan username belum digunakan. Rincian: ' . $exception->getMessage();
            }
        }
    }
}
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pasang Kasir Toko</title><link rel="stylesheet" href="assets/app.css"></head>
<body class="auth-body"><main class="auth-card"><a class="brand auth-brand" href="index.php"><span class="brand-mark">K</span><span>Kasir Toko<small>Instalasi awal</small></span></a>
<?php if ($success): ?>
  <div class="notice notice-success"><h1>Database siap</h1><p>Akun super admin berhasil dibuat. Masuk untuk mulai mengatur toko.</p></div><a class="button button-primary button-wide" href="login.php">Masuk ke aplikasi</a>
<?php elseif ($installed): ?>
  <div class="notice notice-success"><h1>Aplikasi sudah dipasang</h1><p>Akun awal sudah tersedia. Halaman instalasi dikunci agar akun tidak dapat dibuat ulang.</p></div><a class="button button-primary button-wide" href="login.php">Ke halaman masuk</a>
<?php else: ?>
  <p class="eyebrow">Langkah pertama</p><h1>Siapkan super admin</h1><p class="muted">Database <strong>web_kasir</strong> akan dibuat otomatis. Tidak ada kata sandi bawaan.</p>
  <?php if ($error): ?><div class="flash flash-error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
  <form method="post" class="stack-form" autocomplete="off"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['_setup_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <label>Nama super admin<input name="name" maxlength="100" required value="<?= htmlspecialchars((string) ($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
    <label>Username<input name="username" minlength="3" maxlength="40" required autocomplete="username" value="<?= htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
    <label>Kata sandi <span class="hint">Minimal 10 karakter</span><input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
    <label>Ulangi kata sandi<input type="password" name="confirm_password" minlength="10" required autocomplete="new-password"></label>
    <button class="button button-primary button-wide" type="submit">Buat akun dan database</button>
  </form>
<?php endif; ?>
<footer class="auth-footer">Jalankan MySQL dari XAMPP sebelum membuka halaman ini.</footer></main></body></html>
