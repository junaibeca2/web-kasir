<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_permission('reports.view');

$today = date('Y-m-d');
$from = (string) ($_GET['from'] ?? date('Y-m-01'));
$to = (string) ($_GET['to'] ?? $today);
$isDate = static fn (string $date): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && date('Y-m-d', strtotime($date)) === $date;
$error = '';
$rows = [];
$summary = ['order_count' => 0, 'total' => '0.00'];
if (!$isDate($from) || !$isDate($to) || $from > $to) {
    $error = 'Rentang tanggal tidak valid.';
} else {
    $stmt = db()->prepare('SELECT COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS total FROM orders WHERE DATE(created_at) BETWEEN ? AND ?');
    $stmt->execute([$from, $to]);
    $summary = $stmt->fetch();
    $stmt = db()->prepare('SELECT o.id, o.order_code, o.customer_name, o.total, o.created_at, u.name AS cashier FROM orders o JOIN users u ON u.id=o.created_by WHERE DATE(o.created_at) BETWEEN ? AND ? ORDER BY o.created_at DESC LIMIT 500');
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();
}
render_header('Laporan penjualan');
?>
<section class="panel"><form method="get" class="filter-form"><label>Dari tanggal<input type="date" name="from" value="<?= e($from) ?>" required></label><label>Sampai tanggal<input type="date" name="to" value="<?= e($to) ?>" required></label><button class="button button-primary" type="submit">Tampilkan</button></form></section>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php else: ?><section class="stats-grid report-stats"><article class="stat-card"><span>Jumlah transaksi</span><strong><?= number_format((int) $summary['order_count'], 0, ',', '.') ?></strong><small><?= e(date('d/m/Y', strtotime($from))) ?> – <?= e(date('d/m/Y', strtotime($to))) ?></small></article><article class="stat-card"><span>Total penjualan</span><strong><?= e(rupiah(amount_cents($summary['total']))) ?></strong><small>Nilai dari pesanan tersimpan</small></article></section><section class="panel"><div class="section-heading"><div><h2>Rincian transaksi</h2><p class="muted"><?= count($rows) ?> transaksi ditampilkan, maksimum 500</p></div></div><div class="table-wrap"><table><thead><tr><th>No. pesanan</th><th>Tanggal & waktu</th><th>Pelanggan</th><th>Kasir</th><th class="align-right">Total</th><th></th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="6" class="empty-cell">Tidak ada transaksi pada rentang ini.</td></tr><?php else: foreach ($rows as $row): ?><tr><td><strong><?= e($row['order_code']) ?></strong></td><td><?= e(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td><td><?= e($row['customer_name'] ?: '—') ?></td><td><?= e($row['cashier']) ?></td><td class="align-right"><?= e(rupiah(amount_cents($row['total']))) ?></td><td><a class="text-link" href="<?= e(app_url('receipt.php?id=' . (int) $row['id'])) ?>">Struk</a></td></tr><?php endforeach; endif; ?></tbody></table></div></section><?php endif; ?>
<?php render_footer(); ?>
