<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
if (current_user()) {
    redirect('dashboard.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    try {
        $stmt = db()->prepare('SELECT id, password_hash, is_active FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $account = $stmt->fetch();
        if ($account && (int) $account['is_active'] === 1 && password_verify($password, $account['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['id'];
            redirect('dashboard.php');
        }
        $error = 'Username atau kata sandi salah, atau akun dinonaktifkan.';
    } catch (PDOException $exception) {
        $error = 'Database belum siap. Jalankan setup.php terlebih dahulu.';
    }
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Masuk · <?= e(APP_TITLE) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/app.css')) ?>"></head>
<body class="auth-body"><main class="auth-card"><a class="brand auth-brand" href="<?= e(app_url('index.php')) ?>"><span class="brand-mark">K</span><span><?= e(APP_TITLE) ?><small>Sistem kasir toko</small></span></a>
<p class="eyebrow">Selamat datang</p><h1>Masuk ke akun</h1><p class="muted">Gunakan username dan kata sandi yang sudah dibuat.</p>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<?php show_flash(); ?>
<form method="post" class="stack-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><label>Username<input name="username" required autocomplete="username" autofocus value="<?= e($_POST['username'] ?? '') ?>"></label><label>Kata sandi<input type="password" name="password" required autocomplete="current-password"></label><button class="button button-primary button-wide" type="submit">Masuk</button></form>
<footer class="auth-footer"><a href="<?= e(app_url('setup.php')) ?>">Instalasi pertama kali</a></footer></main></body></html>
