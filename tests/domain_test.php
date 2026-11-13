<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/domain.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }
    echo "PASS: {$message}\n";
}

check(role_can('admin', 'reports.view'), 'admin can view reports');
check(role_can('employee', 'orders.create'), 'employee can create orders');
check(role_can('employee', 'orders.edit'), 'employee can edit orders');
check(role_can('employee', 'receipts.print'), 'employee can print receipts');
check(!role_can('employee', 'reports.view'), 'employee cannot view reports');
check(!role_can('employee', 'menu.manage'), 'employee cannot manage menu catalog');
check(!role_can('employee', 'users.manage'), 'employee cannot add employees');
check(calculate_order_total([
    ['price' => '12.50', 'quantity' => 2],
    ['price' => '7.25', 'quantity' => 1],
]) === 3225, 'order total uses exact cents');

try {
    calculate_order_total([['price' => '10.00', 'quantity' => 0]]);
    check(false, 'zero quantity is rejected');
} catch (InvalidArgumentException) {
    check(true, 'zero quantity is rejected');
}

echo "{$checks} checks passed.\n";
