<?php
declare(strict_types=1);

function role_can(string $role, string $ability): bool
{
    $permissions = [
        'admin' => [
            'orders.view', 'orders.create', 'orders.edit', 'receipts.print',
            'menu.manage', 'users.manage', 'reports.view', 'settings.manage',
        ],
        'employee' => ['orders.view', 'orders.create', 'orders.edit', 'receipts.print'],
    ];

    return in_array($ability, $permissions[$role] ?? [], true);
}

function money_to_cents(string|int|float $value): int
{
    $text = trim((string) $value);
    if (!preg_match('/^(?:0|[1-9][0-9]*)(?:\.([0-9]{1,2}))?$/', $text, $matches)) {
        throw new InvalidArgumentException('Harga harus berupa angka dengan maksimal dua angka desimal.');
    }

    [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
    $fraction = str_pad($fraction, 2, '0');
    if (strlen($whole) > 10) {
        throw new InvalidArgumentException('Harga terlalu besar.');
    }

    return ((int) $whole * 100) + (int) $fraction;
}

function calculate_order_total(array $items): int
{
    $total = 0;
    foreach ($items as $item) {
        $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1 || $quantity > 999) {
            throw new InvalidArgumentException('Jumlah item harus antara 1 sampai 999.');
        }
        $line = money_to_cents($item['price'] ?? '');
        if ($line > intdiv(PHP_INT_MAX - $total, $quantity)) {
            throw new InvalidArgumentException('Total pesanan terlalu besar.');
        }
        $total += $line * $quantity;
    }

    return $total;
}

function cents_to_decimal(int $cents): string
{
    if ($cents < 0) {
        throw new InvalidArgumentException('Nilai uang tidak boleh negatif.');
    }
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function rupiah(int $cents): string
{
    return 'Rp ' . number_format($cents / 100, 0, ',', '.');
}
