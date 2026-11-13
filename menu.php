<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_permission('menu.manage');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    try {
        if ($action === 'deactivate') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                throw new InvalidArgumentException('Menu tidak valid.');
            }
            $stmt = db()->prepare('UPDATE menu_items SET is_active = 0 WHERE id = ?');
            $stmt->execute([$id]);
            set_flash('success', 'Menu dinonaktifkan. Riwayat transaksi tetap tersimpan.');
            redirect('menu.php');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $priceCents = money_to_cents((string) ($_POST['price'] ?? ''));
        $id = (int) ($_POST['id'] ?? 0);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Nama menu wajib diisi, maksimal 120 karakter.');
        }
        if (mb_strlen($category) > 80) {
            throw new InvalidArgumentException('Kategori maksimal 80 karakter.');
        }
        if ($priceCents < 1) {
            throw new InvalidArgumentException('Harga harus lebih dari Rp 0.');
        }
        if ($id > 0) {
            $stmt = db()->prepare('UPDATE menu_items SET name = ?, category = ?, price = ? WHERE id = ?');
            $stmt->execute([$name, $category !== '' ? $category : null, cents_to_decimal($priceCents), $id]);
            set_flash('success', 'Menu berhasil diperbarui.');
        } else {
            $stmt = db()->prepare('INSERT INTO menu_items (name, category, price) VALUES (?, ?, ?)');
            $stmt->execute([$name, $category !== '' ? $category : null, cents_to_decimal($priceCents)]);
            set_flash('success', 'Menu berhasil ditambahkan.');
        }
        redirect('menu.php');
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}

$edit = null;
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($editId && $editId > 0) {
    $stmt = db()->prepare('SELECT * FROM menu_items WHERE id = ?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch() ?: null;
}
$items = db()->query('SELECT * FROM menu_items ORDER BY is_active DESC, name ASC')->fetchAll();
render_header('Menu');
?>
<div class="two-column-layout">
<section class="panel"><div class="section-heading"><div><h2><?= $edit ? 'Edit menu' : 'Tambah menu' ?></h2><p class="muted">Karyawan hanya bisa memilih menu saat membuat pesanan.</p></div></div>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="stack-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>"><input type="hidden" name="action" value="save">
<label>Nama menu<input name="name" maxlength="120" required value="<?= e($_POST['name'] ?? $edit['name'] ?? '') ?>" placeholder="Contoh: Kopi susu"></label>
<label>Kategori <span class="hint">Opsional</span><input name="category" maxlength="80" value="<?= e($_POST['category'] ?? $edit['category'] ?? '') ?>" placeholder="Minuman"></label>
<label>Harga (Rp)<input type="number" name="price" min="0.01" max="9999999999.99" step="0.01" required value="<?= e($_POST['price'] ?? ($edit ? number_format((float) $edit['price'], 2, '.', '') : '')) ?>" placeholder="15000"></label>
<div class="form-actions"><button class="button button-primary" type="submit"><?= $edit ? 'Simpan perubahan' : 'Tambah menu' ?></button><?php if ($edit): ?><a class="button button-quiet" href="<?= e(app_url('menu.php')) ?>">Batal</a><?php endif; ?></div></form></section>
<section class="panel"><div class="section-heading"><div><h2>Daftar menu</h2><p class="muted"><?= count(array_filter($items, static fn ($item) => (int) $item['is_active'] === 1)) ?> menu aktif</p></div></div>
<div class="table-wrap"><table><thead><tr><th>Menu</th><th>Kategori</th><th class="align-right">Harga</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (!$items): ?><tr><td colspan="5" class="empty-cell">Belum ada menu. Tambahkan item pertama di formulir.</td></tr><?php else: foreach ($items as $item): ?><tr><td><strong><?= e($item['name']) ?></strong></td><td><?= e($item['category'] ?: '—') ?></td><td class="align-right"><?= e(rupiah(amount_cents($item['price']))) ?></td><td><span class="badge <?= (int) $item['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= (int) $item['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td><td class="row-actions"><a class="text-link" href="<?= e(app_url('menu.php?edit=' . (int) $item['id'])) ?>">Edit</a><?php if ((int) $item['is_active']): ?><form method="post" onsubmit="return confirm('Nonaktifkan menu ini?')"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="text-button text-danger" type="submit">Nonaktifkan</button></form><?php endif; ?></td></tr><?php endforeach; endif; ?>
</tbody></table></div></section></div>
<?php render_footer(); ?>
