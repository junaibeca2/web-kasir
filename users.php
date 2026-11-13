<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_permission('users.manage');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'create');
    try {
        if ($action === 'deactivate') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) throw new InvalidArgumentException('Akun tidak valid.');
            if ($id === (int) current_user()['id']) throw new InvalidArgumentException('Akun yang sedang digunakan tidak dapat dinonaktifkan.');
            $stmt = db()->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'employee'");
            $stmt->execute([$id]);
            set_flash('success', $stmt->rowCount() ? 'Akun karyawan dinonaktifkan.' : 'Akun tidak ditemukan atau bukan akun karyawan.');
            redirect('users.php');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) throw new InvalidArgumentException('Nama wajib diisi dan maksimal 100 karakter.');
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/', $username)) throw new InvalidArgumentException('Username harus 3–40 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.');
        if (strlen($password) < 10) throw new InvalidArgumentException('Kata sandi minimal 10 karakter.');
        $stmt = db()->prepare("INSERT INTO users (name, username, password_hash, role) VALUES (?, ?, ?, 'employee')");
        $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT)]);
        set_flash('success', 'Akun karyawan berhasil dibuat.');
        redirect('users.php');
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        $error = 'Username sudah dipakai atau akun tidak dapat disimpan.';
    }
}
$users = db()->query('SELECT id, name, username, role, is_active, created_at FROM users ORDER BY role, name')->fetchAll();
render_header('Karyawan');
?>
<div class="two-column-layout"><section class="panel"><div class="section-heading"><div><h2>Tambah karyawan</h2><p class="muted">Akun baru mendapat akses pesanan saja.</p></div></div><?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?><form method="post" class="stack-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><label>Nama<input name="name" maxlength="100" required value="<?= e($_POST['name'] ?? '') ?>"></label><label>Username<input name="username" minlength="3" maxlength="40" required autocomplete="off" value="<?= e($_POST['username'] ?? '') ?>"></label><label>Kata sandi <span class="hint">Minimal 10 karakter</span><input type="password" name="password" minlength="10" required autocomplete="new-password"></label><button class="button button-primary" type="submit">Buat akun karyawan</button></form><div class="permission-note"><strong>Izin karyawan</strong><span>Buat pesanan, edit pesanan, cetak struk.</span><span>Tidak dapat membuka laporan, mengubah menu, menambah akun, atau mengubah identitas toko.</span></div></section>
<section class="panel"><div class="section-heading"><div><h2>Akun pengguna</h2><p class="muted"><?= count($users) ?> akun terdaftar</p></div></div><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($users as $account): ?><tr><td><strong><?= e($account['name']) ?></strong></td><td><?= e($account['username']) ?></td><td><?= $account['role'] === 'admin' ? 'Super admin' : 'Karyawan' ?></td><td><span class="badge <?= (int) $account['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= (int) $account['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td><td><?php if ($account['role'] === 'employee' && (int) $account['is_active']): ?><form method="post" onsubmit="return confirm('Nonaktifkan akun karyawan ini?')"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= (int) $account['id'] ?>"><button class="text-button text-danger" type="submit">Nonaktifkan</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section></div>
<?php render_footer(); ?>
