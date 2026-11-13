<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_permission('settings.manage');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['store_name'] ?? ''));
    $address = trim((string) ($_POST['store_address'] ?? ''));
    $phone = trim((string) ($_POST['store_phone'] ?? ''));
    $note = trim((string) ($_POST['receipt_note'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) {
        $error = 'Nama toko wajib diisi dan maksimal 120 karakter.';
    } elseif (mb_strlen($address) > 255 || mb_strlen($phone) > 40 || mb_strlen($note) > 255) {
        $error = 'Alamat maksimal 255 karakter, telepon 40, dan catatan struk 255.';
    } else {
        try {
            $stmt = db()->query('SELECT logo_path FROM shop_settings WHERE id = 1');
            $oldLogo = $stmt->fetchColumn() ?: null;
            $logoPath = $oldLogo;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['logo'];
                if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Unggah logo gagal. Pilih file lagi.');
                if ((int) $file['size'] > 2 * 1024 * 1024) throw new RuntimeException('Ukuran logo maksimal 2 MB.');
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($extensions[$mime]) || !getimagesize($file['tmp_name'])) throw new RuntimeException('Gunakan gambar JPG, PNG, atau WebP yang valid. SVG tidak diizinkan.');
                $uploadDir = __DIR__ . '/uploads';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) throw new RuntimeException('Folder uploads tidak dapat dibuat.');
                $fileName = 'logo_' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
                if (!move_uploaded_file($file['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $fileName)) throw new RuntimeException('Logo tidak dapat disimpan. Periksa izin folder uploads.');
                $logoPath = 'uploads/' . $fileName;
            }
            $save = db()->prepare('UPDATE shop_settings SET store_name = ?, store_address = ?, store_phone = ?, receipt_note = ?, logo_path = ? WHERE id = 1');
            $save->execute([$name, $address, $phone, $note, $logoPath]);
            set_flash('success', 'Identitas toko dan struk berhasil disimpan.');
            redirect('settings.php');
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}
$settings = db()->query('SELECT * FROM shop_settings WHERE id = 1')->fetch() ?: ['store_name' => 'Toko Saya', 'store_address' => '', 'store_phone' => '', 'receipt_note' => 'Terima kasih atas kunjungan Anda.', 'logo_path' => null];
render_header('Pengaturan toko');
?>
<div class="settings-layout"><section class="panel"><div class="section-heading"><div><h2>Identitas pada struk</h2><p class="muted">Logo akan tampil paling atas saat struk dicetak.</p></div></div><?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?><form method="post" enctype="multipart/form-data" class="stack-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><label>Nama toko<input name="store_name" maxlength="120" required value="<?= e($_POST['store_name'] ?? $settings['store_name']) ?>"></label><label>Alamat toko<textarea name="store_address" maxlength="255" rows="3" placeholder="Alamat yang dicetak di struk"><?= e($_POST['store_address'] ?? $settings['store_address']) ?></textarea></label><label>Nomor HP / telepon<input name="store_phone" maxlength="40" value="<?= e($_POST['store_phone'] ?? $settings['store_phone']) ?>" placeholder="08xxxxxxxxxx"></label><label>Logo toko <span class="hint">JPG, PNG, WebP · maks 2 MB</span><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label><label>Catatan bawah struk<textarea name="receipt_note" maxlength="255" rows="2"><?= e($_POST['receipt_note'] ?? $settings['receipt_note']) ?></textarea></label><button class="button button-primary" type="submit">Simpan identitas toko</button></form></section>
<aside class="panel settings-preview"><h2>Pratinjau logo</h2><?php if (!empty($settings['logo_path'])): ?><img src="<?= e(app_url($settings['logo_path'])) ?>" alt="Logo toko"><p class="muted">Logo saat ini. Pilih file baru untuk menggantinya.</p><?php else: ?><div class="logo-placeholder">Logo<br><small>belum dipilih</small></div><p class="muted">Belum ada logo. Nama toko akan tetap tampil di struk.</p><?php endif; ?><div class="mini-receipt"><strong><?= e($settings['store_name']) ?></strong><span><?= e($settings['store_address'] ?: 'Alamat toko') ?></span><span><?= e($settings['store_phone'] ?: 'Nomor telepon') ?></span><hr><span>Logo dan identitas toko akan dicetak di sini.</span></div></aside></div>
<?php render_footer(); ?>
