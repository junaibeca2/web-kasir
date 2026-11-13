<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
$user = require_login();
$today = date('Y-m-d');
$todayCount = 0;
$todayTotal = 0;
if ($user['role'] === 'admin') {
    $stmt = db()->prepare('SELECT COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS total FROM orders WHERE DATE(created_at) = ?');
    $stmt->execute([$today]);
    $summary = $stmt->fetch();
    $todayCount = (int) $summary['order_count'];
    $todayTotal = amount_cents($summary['total']);
}
$menuCount = 0;
if ($user['role'] === 'admin') {
    $menuCount = (int) db()->query('SELECT COUNT(*) FROM menu_items WHERE is_active = 1')->fetchColumn();
}
render_header('Beranda');
?>
<section class="welcome-panel"><div><p class="eyebrow">Kasir siap digunakan</p><h2>Halo, <?= e($user['name']) ?>.</h2><p>Pilih tindakan yang ingin dikerjakan.</p></div><a class="button button-primary" href="<?= e(app_url('orders.php')) ?>">+ Buat pesanan</a></section>
<?php if ($user['role'] === 'admin'): ?>
<section class="stats-grid"><article class="stat-card"><span>Transaksi hari ini</span><strong><?= number_format($todayCount, 0, ',', '.') ?></strong><small>Semua kasir</small></article><article class="stat-card"><span>Penjualan hari ini</span><strong><?= e(rupiah($todayTotal)) ?></strong><small>Total transaksi tersimpan</small></article><article class="stat-card"><span>Menu aktif</span><strong><?= number_format($menuCount, 0, ',', '.') ?></strong><small>Item yang bisa dipesan</small></article></section>
<?php else: ?>
<section class="notice"><strong>Akun karyawan</strong><p>Kamu dapat membuat atau mengedit pesanan dan mencetak struk. Laporan, menu, akun karyawan, serta pengaturan toko hanya untuk super admin.</p></section>
<?php endif; ?>
<section class="quick-grid"><a class="quick-card" href="<?= e(app_url('orders.php')) ?>"><span class="quick-icon">01</span><span><strong>Pesanan</strong><small>Input menu, ubah pesanan, cetak struk</small></span></a>
<?php if ($user['role'] === 'admin'): ?><a class="quick-card" href="<?= e(app_url('menu.php')) ?>"><span class="quick-icon">02</span><span><strong>Kelola menu</strong><small><?= number_format($menuCount, 0, ',', '.') ?> item aktif</small></span></a><a class="quick-card" href="<?= e(app_url('reports.php')) ?>"><span class="quick-icon">03</span><span><strong>Laporan penjualan</strong><small>Ringkasan transaksi per tanggal</small></span></a><a class="quick-card" href="<?= e(app_url('settings.php')) ?>"><span class="quick-icon">04</span><span><strong>Identitas toko</strong><small>Alamat, telepon, dan logo struk</small></span></a><?php endif; ?></section>
<?php if ($user['role'] === 'admin'): ?><section class="panel"><div class="section-heading"><div><h2>Transaksi terbaru</h2><p class="muted">10 pesanan terakhir</p></div><a class="text-link" href="<?= e(app_url('orders.php')) ?>">Semua pesanan →</a></div><div class="table-wrap"><table><thead><tr><th>No. pesanan</th><th>Waktu</th><th>Kasir</th><th class="align-right">Total</th><th></th></tr></thead><tbody>
<?php $recent = db()->query('SELECT o.id, o.order_code, o.total, o.created_at, u.name FROM orders o JOIN users u ON u.id=o.created_by ORDER BY o.created_at DESC LIMIT 10')->fetchAll(); ?>
<?php if (!$recent): ?><tr><td colspan="5" class="empty-cell">Belum ada transaksi.</td></tr><?php else: foreach ($recent as $order): ?><tr><td><strong><?= e($order['order_code']) ?></strong></td><td><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td><td><?= e($order['name']) ?></td><td class="align-right"><?= e(rupiah(amount_cents($order['total']))) ?></td><td><a class="text-link" href="<?= e(app_url('receipt.php?id=' . (int) $order['id'])) ?>">Struk</a></td></tr><?php endforeach; endif; ?>
</tbody></table></div></section><?php endif; ?>
<?php render_footer(); ?>
