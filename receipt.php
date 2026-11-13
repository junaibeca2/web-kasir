<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_permission('receipts.print');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Nomor pesanan tidak valid.');
}
$stmt = db()->prepare('SELECT o.*, u.name AS cashier FROM orders o JOIN users u ON u.id=o.created_by WHERE o.id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}
$stmt = db()->prepare('SELECT menu_name, unit_price, quantity, line_total FROM order_items WHERE order_id = ? ORDER BY id');
$stmt->execute([$id]);
$items = $stmt->fetchAll();
$shop = db()->query('SELECT * FROM shop_settings WHERE id = 1')->fetch() ?: ['store_name' => 'Toko Saya', 'store_address' => '', 'store_phone' => '', 'receipt_note' => '', 'logo_path' => null];
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Struk <?= e($order['order_code']) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/app.css')) ?>"><style>
.receipt-page{max-width:760px;margin:24px auto;padding:0 16px}.receipt-sheet{width:min(100%,320px);margin:20px auto;padding:24px 20px;background:#fff;color:#171717;font:13px/1.45 "Courier New",monospace;box-shadow:0 12px 40px #10182820}.receipt-center{text-align:center}.receipt-logo{display:block;max-width:150px;max-height:100px;object-fit:contain;margin:0 auto 10px}.receipt-title{font:bold 17px/1.25 Arial,sans-serif;margin:0 0 5px}.receipt-meta{font-size:11px}.receipt-divider{border:0;border-top:1px dashed #555;margin:12px 0}.receipt-line{display:flex;justify-content:space-between;gap:10px}.receipt-item{margin:10px 0}.receipt-item-name{font-weight:bold;overflow-wrap:anywhere}.receipt-total{font-weight:bold;font-size:15px}.receipt-note{margin-top:15px}.receipt-actions{display:flex;justify-content:center;gap:8px;flex-wrap:wrap}.receipt-actions form{margin:0}@media print{@page{size:80mm auto;margin:4mm}body{background:#fff!important}.topbar,.page-heading,.receipt-actions,.flash{display:none!important}.receipt-page{margin:0;padding:0;max-width:none}.receipt-sheet{width:72mm;margin:0;padding:3mm 2mm;box-shadow:none}.page{padding:0!important}.receipt-sheet *{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body><header class="topbar"><a class="brand" href="<?= e(app_url('dashboard.php')) ?>"><span class="brand-mark">K</span><span><?= e(APP_TITLE) ?><small>Struk transaksi</small></span></a></header><main class="receipt-page"><div class="receipt-actions"><button class="button button-primary" onclick="window.print()" type="button">Cetak struk</button><a class="button button-quiet" href="<?= e(app_url('orders.php?id=' . (int) $order['id'])) ?>">Edit pesanan</a><a class="button button-quiet" href="<?= e(app_url('orders.php')) ?>">Kembali</a></div><article class="receipt-sheet" id="receipt">
<div class="receipt-center"><?php if (!empty($shop['logo_path'])): ?><img class="receipt-logo" src="<?= e(app_url($shop['logo_path'])) ?>" alt="Logo <?= e($shop['store_name']) ?>"><?php endif; ?><h1 class="receipt-title"><?= e($shop['store_name']) ?></h1><?php if ($shop['store_address']): ?><div><?= nl2br(e($shop['store_address'])) ?></div><?php endif; ?><?php if ($shop['store_phone']): ?><div>Telp: <?= e($shop['store_phone']) ?></div><?php endif; ?></div>
<hr class="receipt-divider"><div>No: <?= e($order['order_code']) ?></div><div>Waktu: <?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></div><div>Kasir: <?= e($order['cashier']) ?></div><?php if ($order['customer_name']): ?><div>Pelanggan: <?= e($order['customer_name']) ?></div><?php endif; ?><hr class="receipt-divider">
<?php foreach ($items as $item): ?><div class="receipt-item"><div class="receipt-item-name"><?= e($item['menu_name']) ?></div><div class="receipt-line"><span><?= (int) $item['quantity'] ?> × <?= e(rupiah(amount_cents($item['unit_price']))) ?></span><span><?= e(rupiah(amount_cents($item['line_total']))) ?></span></div></div><?php endforeach; ?><hr class="receipt-divider"><div class="receipt-line receipt-total"><span>TOTAL</span><span><?= e(rupiah(amount_cents($order['total']))) ?></span></div><hr class="receipt-divider"><div class="receipt-center receipt-note"><?= nl2br(e($shop['receipt_note'] ?? '')) ?></div></article></main></body></html>
