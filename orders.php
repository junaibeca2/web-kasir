<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
$user = require_permission('orders.view');
$error = '';
$editId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$order = null;
$orderItems = [];
if ($editId && $editId > 0) {
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$editId]);
    $order = $stmt->fetch() ?: null;
    if (!$order) {
        http_response_code(404);
        $error = 'Pesanan tidak ditemukan.';
    } else {
        $stmt = db()->prepare('SELECT menu_item_id, menu_name, unit_price, quantity FROM order_items WHERE order_id = ? ORDER BY id');
        $stmt->execute([$editId]);
        $orderItems = $stmt->fetchAll();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $id = $id && $id > 0 ? $id : 0;
        if ($id > 0) {
            $exists = db()->prepare('SELECT id FROM orders WHERE id = ?');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) {
                throw new InvalidArgumentException('Pesanan tidak ditemukan.');
            }
        }
        $customer = trim((string) ($_POST['customer_name'] ?? ''));
        if (mb_strlen($customer) > 120) {
            throw new InvalidArgumentException('Nama pelanggan maksimal 120 karakter.');
        }
        $ids = $_POST['menu_item_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        if (!is_array($ids) || !is_array($quantities) || count($ids) !== count($quantities)) {
            throw new InvalidArgumentException('Daftar menu tidak valid.');
        }
        $chosen = [];
        $lookup = db()->prepare('SELECT id, name, price FROM menu_items WHERE id = ? AND is_active = 1');
        foreach ($ids as $index => $rawId) {
            $rawId = trim((string) $rawId);
            $rawQuantity = trim((string) ($quantities[$index] ?? ''));
            if ($rawId === '' && $rawQuantity === '') {
                continue;
            }
            $menuId = filter_var($rawId, FILTER_VALIDATE_INT);
            $quantity = filter_var($rawQuantity, FILTER_VALIDATE_INT);
            if (!$menuId || $menuId < 1 || $quantity === false || $quantity < 1 || $quantity > 999) {
                throw new InvalidArgumentException('Pilih menu dan jumlah 1–999 untuk setiap baris.');
            }
            $lookup->execute([$menuId]);
            $menu = $lookup->fetch();
            if (!$menu) {
                throw new InvalidArgumentException('Salah satu menu tidak aktif atau tidak ditemukan.');
            }
            $chosen[] = ['id' => (int) $menu['id'], 'name' => $menu['name'], 'price' => (string) $menu['price'], 'quantity' => $quantity];
        }
        if (!$chosen) {
            throw new InvalidArgumentException('Tambahkan minimal satu menu ke pesanan.');
        }
        $totalCents = calculate_order_total($chosen);
        $pdo = db();
        $pdo->beginTransaction();
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE orders SET customer_name = ?, total = ? WHERE id = ?');
            $stmt->execute([$customer !== '' ? $customer : null, cents_to_decimal($totalCents), $id]);
            $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$id]);
            $orderId = $id;
            $message = 'Pesanan diperbarui.';
        } else {
            $code = 'TRX-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $stmt = $pdo->prepare('INSERT INTO orders (order_code, customer_name, total, created_by) VALUES (?, ?, ?, ?)');
            $stmt->execute([$code, $customer !== '' ? $customer : null, cents_to_decimal($totalCents), (int) $user['id']]);
            $orderId = (int) $pdo->lastInsertId();
            $message = 'Pesanan disimpan.';
        }
        $insert = $pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, menu_name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($chosen as $item) {
            $lineCents = money_to_cents($item['price']) * $item['quantity'];
            $insert->execute([$orderId, $item['id'], $item['name'], $item['price'], $item['quantity'], cents_to_decimal($lineCents)]);
        }
        $pdo->commit();
        set_flash('success', $message);
        redirect('receipt.php?id=' . $orderId);
    } catch (InvalidArgumentException $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Pesanan belum tersimpan. Periksa data lalu coba lagi.';
    }
}

$menu = db()->query('SELECT id, name, category, price FROM menu_items WHERE is_active = 1 ORDER BY category, name')->fetchAll();
$recent = db()->query('SELECT o.id, o.order_code, o.customer_name, o.total, o.created_at, u.name AS cashier FROM orders o JOIN users u ON u.id = o.created_by ORDER BY o.created_at DESC LIMIT 60')->fetchAll();
$formRows = $orderItems;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error) {
    $postedIds = is_array($_POST['menu_item_ids'] ?? null) ? $_POST['menu_item_ids'] : [];
    $postedQuantities = is_array($_POST['quantities'] ?? null) ? $_POST['quantities'] : [];
    $formRows = [];
    foreach ($postedIds as $i => $postedId) {
        $formRows[] = ['menu_item_id' => $postedId, 'quantity' => $postedQuantities[$i] ?? '1'];
    }
    if (!$formRows) $formRows = [['menu_item_id' => '', 'quantity' => '1']];
}
if (!$formRows) $formRows = [['menu_item_id' => '', 'quantity' => '1']];
render_header($order ? 'Edit pesanan' : 'Pesanan');
?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<div class="two-column-layout order-layout"><section class="panel"><div class="section-heading"><div><h2><?= $order ? 'Ubah pesanan' : 'Pesanan baru' ?></h2><p class="muted">Harga diambil ulang dari database saat disimpan.</p></div></div>
<?php if (!$menu): ?><div class="notice"><strong>Belum ada menu aktif.</strong><p>Hubungi super admin untuk menambahkan menu sebelum transaksi.</p><?php if ($user['role'] === 'admin'): ?><a class="text-link" href="<?= e(app_url('menu.php')) ?>">Kelola menu →</a><?php endif; ?></div><?php else: ?>
<form method="post" id="order-form" class="stack-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) ($order['id'] ?? 0) ?>">
<label>Nama pelanggan <span class="hint">Opsional</span><input name="customer_name" maxlength="120" value="<?= e($_POST['customer_name'] ?? $order['customer_name'] ?? '') ?>" placeholder="Nama pelanggan"></label>
<div class="line-items-head"><span>Menu pesanan</span><span>Jumlah</span><span></span></div><div id="order-lines">
<?php foreach ($formRows as $row): ?><div class="order-line"><select name="menu_item_ids[]" class="menu-select"><option value="">Pilih menu</option><?php foreach ($menu as $item): ?><option value="<?= (int) $item['id'] ?>" data-price="<?= e($item['price']) ?>" <?= (string) ($row['menu_item_id'] ?? '') === (string) $item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?> · <?= e(rupiah(amount_cents($item['price']))) ?></option><?php endforeach; ?></select><input type="number" name="quantities[]" min="1" max="999" value="<?= e($row['quantity'] ?? '1') ?>" aria-label="Jumlah"><button class="remove-line" type="button" aria-label="Hapus baris">×</button></div><?php endforeach; ?></div>
<button type="button" class="button button-quiet" id="add-order-line">+ Tambah baris menu</button><div class="order-total-preview"><span>Perkiraan total</span><strong id="order-total">Rp 0</strong></div>
<div class="form-actions"><button class="button button-primary" type="submit"><?= $order ? 'Simpan perubahan' : 'Simpan pesanan & cetak struk' ?></button><?php if ($order): ?><a class="button button-quiet" href="<?= e(app_url('orders.php')) ?>">Batal</a><?php endif; ?></div></form><?php endif; ?></section>
<section class="panel"><div class="section-heading"><div><h2>Pesanan terbaru</h2><p class="muted">Maksimal 60 transaksi terakhir</p></div></div><div class="table-wrap"><table><thead><tr><th>Pesanan</th><th>Waktu</th><th class="align-right">Total</th><th></th></tr></thead><tbody><?php if (!$recent): ?><tr><td colspan="4" class="empty-cell">Belum ada pesanan.</td></tr><?php else: foreach ($recent as $item): ?><tr><td><strong><?= e($item['order_code']) ?></strong><small class="table-subline"><?= e($item['customer_name'] ?: $item['cashier']) ?></small></td><td><?= e(date('d/m H:i', strtotime($item['created_at']))) ?></td><td class="align-right"><?= e(rupiah(amount_cents($item['total']))) ?></td><td class="row-actions"><a class="text-link" href="<?= e(app_url('orders.php?id=' . (int) $item['id'])) ?>">Edit</a><a class="text-link" href="<?= e(app_url('receipt.php?id=' . (int) $item['id'])) ?>">Struk</a></td></tr><?php endforeach; endif; ?></tbody></table></div></section></div>
<?php render_footer(); ?>
